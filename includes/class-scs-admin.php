<?php
/**
 * Admin interface for Smart Catalog Sync
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCS_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_scs_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_scs_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_nopriv_scs_cron_trigger', array($this, 'ajax_cron_trigger'));
        add_action('wp_ajax_scs_cron_trigger', array($this, 'ajax_cron_trigger'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Smart Catalog Sync', 'smart-catalog-sync'),
            __('Catalog Sync', 'smart-catalog-sync'),
            'manage_options',
            'smart-catalog-sync',
            array($this, 'render_admin_page'),
            'dashicons-update',
            56
        );
    }

    public function register_settings() {
        register_setting('scs_settings_group', 'scs_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['webhook_url'] = esc_url_raw($input['webhook_url']);
        $sanitized['sync_interval'] = sanitize_text_field($input['sync_interval']);
        $sanitized['sync_enabled'] = isset($input['sync_enabled']) ? true : false;
        $sanitized['include_images'] = isset($input['include_images']) ? true : false;
        $sanitized['include_variations'] = isset($input['include_variations']) ? true : false;
        $sanitized['include_categories'] = isset($input['include_categories']) ? true : false;
        $sanitized['last_sync'] = isset($input['last_sync']) ? intval($input['last_sync']) : 0;

        // Cron token - generate if empty
        if (isset($input['cron_token']) && !empty($input['cron_token'])) {
            $sanitized['cron_token'] = sanitize_text_field($input['cron_token']);
        } else {
            $old_settings = get_option('scs_settings');
            $sanitized['cron_token'] = !empty($old_settings['cron_token']) ? $old_settings['cron_token'] : wp_generate_password(32, false);
        }

        // Reschedule cron if interval changed
        $old_settings = get_option('scs_settings');
        if ($old_settings['sync_interval'] !== $sanitized['sync_interval']) {
            $this->reschedule_cron($sanitized['sync_interval']);
        }

        return $sanitized;
    }

    private function reschedule_cron($interval) {
        $timestamp = wp_next_scheduled('scs_auto_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'scs_auto_sync');
        }
        wp_schedule_event(time(), $interval, 'scs_auto_sync');
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_smart-catalog-sync' !== $hook) {
            return;
        }

        wp_enqueue_style('scs-admin-css', SCS_PLUGIN_URL . 'assets/css/admin.css', array(), SCS_VERSION);
        wp_enqueue_script('scs-admin-js', SCS_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), SCS_VERSION, true);

        wp_localize_script('scs-admin-js', 'scsAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('scs_admin_nonce'),
            'strings' => array(
                'syncInProgress' => __('Sincronizando...', 'smart-catalog-sync'),
                'syncSuccess' => __('Sincronización completada con éxito', 'smart-catalog-sync'),
                'syncError' => __('Error en la sincronización', 'smart-catalog-sync'),
                'testSuccess' => __('Conexión exitosa', 'smart-catalog-sync'),
                'testError' => __('Error de conexión', 'smart-catalog-sync'),
            )
        ));
    }

    public function ajax_manual_sync() {
        check_ajax_referer('scs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $sync_engine = new SCS_Sync_Engine();
        $result = $sync_engine->sync_products();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    public function ajax_test_connection() {
        check_ajax_referer('scs_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $settings = get_option('scs_settings');
        $webhook_url = $settings['webhook_url'];

        if (empty($webhook_url)) {
            wp_send_json_error(array('message' => __('URL no configurada', 'smart-catalog-sync')));
        }

        $response = wp_remote_post($webhook_url, array(
            'method' => 'POST',
            'headers' => array('Content-Type' => 'application/json'),
            'body' => json_encode(array('test' => true, 'message' => 'Connection test from Smart Catalog Sync')),
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }

        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code >= 200 && $status_code < 300) {
            wp_send_json_success(array('message' => __('Conexión exitosa', 'smart-catalog-sync'), 'status_code' => $status_code));
        } else {
            wp_send_json_error(array('message' => sprintf(__('Error HTTP %d', 'smart-catalog-sync'), $status_code)));
        }
    }

    public function ajax_cron_trigger() {
        // Get settings
        $settings = get_option('scs_settings');

        // Verify token
        $provided_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($provided_token) || empty($settings['cron_token']) || $provided_token !== $settings['cron_token']) {
            status_header(403);
            echo json_encode(array(
                'success' => false,
                'message' => 'Invalid token'
            ));
            exit;
        }

        // Check if sync is enabled
        if (empty($settings['sync_enabled'])) {
            echo json_encode(array(
                'success' => false,
                'message' => 'Automatic sync is disabled'
            ));
            exit;
        }

        // Execute sync
        $sync_engine = new SCS_Sync_Engine();
        $sync_engine->auto_sync();

        // Return success
        echo json_encode(array(
            'success' => true,
            'message' => 'Sync executed successfully',
            'timestamp' => current_time('mysql')
        ));
        exit;
    }

    public function render_admin_page() {
        $settings = get_option('scs_settings');
        $last_sync = $settings['last_sync'];
        $last_sync_text = $last_sync ? human_time_diff($last_sync, current_time('timestamp')) . ' ago' : __('Nunca', 'smart-catalog-sync');

        // Get product count
        $product_count = wp_count_posts('product')->publish;

        ?>
        <div class="wrap scs-admin-wrap">
            <div class="scs-header">
                <h1>
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Smart Catalog Sync', 'smart-catalog-sync'); ?>
                </h1>
                <p class="scs-subtitle"><?php _e('Sincroniza tu catálogo de productos con sistemas externos de IA', 'smart-catalog-sync'); ?></p>
            </div>

            <div class="scs-container">
                <!-- Stats Cards -->
                <div class="scs-stats-row">
                    <div class="scs-stat-card">
                        <div class="scs-stat-icon">
                            <span class="dashicons dashicons-products"></span>
                        </div>
                        <div class="scs-stat-content">
                            <h3><?php echo number_format($product_count); ?></h3>
                            <p><?php _e('Productos Totales', 'smart-catalog-sync'); ?></p>
                        </div>
                    </div>

                    <div class="scs-stat-card">
                        <div class="scs-stat-icon">
                            <span class="dashicons dashicons-clock"></span>
                        </div>
                        <div class="scs-stat-content">
                            <h3><?php echo $last_sync_text; ?></h3>
                            <p><?php _e('Última Sincronización', 'smart-catalog-sync'); ?></p>
                        </div>
                    </div>

                    <div class="scs-stat-card">
                        <div class="scs-stat-icon <?php echo $settings['sync_enabled'] ? 'active' : 'inactive'; ?>">
                            <span class="dashicons dashicons-<?php echo $settings['sync_enabled'] ? 'yes-alt' : 'dismiss'; ?>"></span>
                        </div>
                        <div class="scs-stat-content">
                            <h3><?php echo $settings['sync_enabled'] ? __('Activo', 'smart-catalog-sync') : __('Inactivo', 'smart-catalog-sync'); ?></h3>
                            <p><?php _e('Estado de Sincronización', 'smart-catalog-sync'); ?></p>
                        </div>
                    </div>
                </div>

                <form method="post" action="options.php" class="scs-form">
                    <?php settings_fields('scs_settings_group'); ?>

                    <!-- Configuration Section -->
                    <div class="scs-section">
                        <h2 class="scs-section-title">
                            <span class="dashicons dashicons-admin-settings"></span>
                            <?php _e('Configuración', 'smart-catalog-sync'); ?>
                        </h2>

                        <div class="scs-form-group">
                            <label for="webhook_url" class="scs-label">
                                <?php _e('URL de Destino', 'smart-catalog-sync'); ?>
                                <span class="required">*</span>
                            </label>
                            <div class="scs-input-group">
                                <input
                                    type="url"
                                    id="webhook_url"
                                    name="scs_settings[webhook_url]"
                                    value="<?php echo esc_attr($settings['webhook_url']); ?>"
                                    placeholder="https://tu-dominio.com/api/productos"
                                    class="scs-input"
                                    required
                                >
                                <button type="button" id="scs-test-connection" class="scs-btn scs-btn-secondary">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php _e('Probar Conexión', 'smart-catalog-sync'); ?>
                                </button>
                            </div>
                            <p class="scs-description"><?php _e('URL donde se enviarán los datos de productos en formato JSON', 'smart-catalog-sync'); ?></p>
                        </div>

                        <div class="scs-form-group">
                            <label for="sync_interval" class="scs-label">
                                <?php _e('Frecuencia de Sincronización', 'smart-catalog-sync'); ?>
                            </label>
                            <select id="sync_interval" name="scs_settings[sync_interval]" class="scs-select">
                                <option value="every_15_minutes" <?php selected($settings['sync_interval'], 'every_15_minutes'); ?>>
                                    <?php _e('Cada 15 minutos', 'smart-catalog-sync'); ?>
                                </option>
                                <option value="every_30_minutes" <?php selected($settings['sync_interval'], 'every_30_minutes'); ?>>
                                    <?php _e('Cada 30 minutos', 'smart-catalog-sync'); ?>
                                </option>
                                <option value="hourly" <?php selected($settings['sync_interval'], 'hourly'); ?>>
                                    <?php _e('Cada hora', 'smart-catalog-sync'); ?>
                                </option>
                                <option value="twicedaily" <?php selected($settings['sync_interval'], 'twicedaily'); ?>>
                                    <?php _e('Dos veces al día', 'smart-catalog-sync'); ?>
                                </option>
                                <option value="daily" <?php selected($settings['sync_interval'], 'daily'); ?>>
                                    <?php _e('Una vez al día', 'smart-catalog-sync'); ?>
                                </option>
                            </select>
                            <p class="scs-description"><?php _e('Define cada cuánto tiempo se sincronizarán automáticamente los productos', 'smart-catalog-sync'); ?></p>
                        </div>

                        <div class="scs-form-group">
                            <label class="scs-toggle">
                                <input
                                    type="checkbox"
                                    name="scs_settings[sync_enabled]"
                                    value="1"
                                    <?php checked($settings['sync_enabled'], true); ?>
                                >
                                <span class="scs-toggle-slider"></span>
                                <span class="scs-toggle-label"><?php _e('Habilitar Sincronización Automática', 'smart-catalog-sync'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Data Options Section -->
                    <div class="scs-section">
                        <h2 class="scs-section-title">
                            <span class="dashicons dashicons-database"></span>
                            <?php _e('Opciones de Datos', 'smart-catalog-sync'); ?>
                        </h2>

                        <div class="scs-checkboxes">
                            <label class="scs-checkbox">
                                <input
                                    type="checkbox"
                                    name="scs_settings[include_images]"
                                    value="1"
                                    <?php checked($settings['include_images'], true); ?>
                                >
                                <span><?php _e('Incluir imágenes de productos', 'smart-catalog-sync'); ?></span>
                            </label>

                            <label class="scs-checkbox">
                                <input
                                    type="checkbox"
                                    name="scs_settings[include_variations]"
                                    value="1"
                                    <?php checked($settings['include_variations'], true); ?>
                                >
                                <span><?php _e('Incluir variaciones de productos', 'smart-catalog-sync'); ?></span>
                            </label>

                            <label class="scs-checkbox">
                                <input
                                    type="checkbox"
                                    name="scs_settings[include_categories]"
                                    value="1"
                                    <?php checked($settings['include_categories'], true); ?>
                                >
                                <span><?php _e('Incluir categorías y etiquetas', 'smart-catalog-sync'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- Cron Configuration Section -->
                    <div class="scs-section">
                        <h2 class="scs-section-title">
                            <span class="dashicons dashicons-clock"></span>
                            <?php _e('Configuración de Cron Externo', 'smart-catalog-sync'); ?>
                        </h2>

                        <p class="scs-description" style="margin-bottom: 20px;">
                            <?php _e('Si el cron de WordPress está deshabilitado o quieres usar solo este evento, configura un cron job en tu servidor con la URL de abajo:', 'smart-catalog-sync'); ?>
                        </p>

                        <div class="scs-form-group">
                            <label for="cron_token" class="scs-label">
                                <?php _e('Token de Seguridad', 'smart-catalog-sync'); ?>
                            </label>
                            <div class="scs-input-group">
                                <input
                                    type="text"
                                    id="cron_token"
                                    name="scs_settings[cron_token]"
                                    value="<?php echo esc_attr($settings['cron_token']); ?>"
                                    class="scs-input"
                                    readonly
                                >
                                <button type="button" id="scs-regenerate-token" class="scs-btn scs-btn-secondary" onclick="if(confirm('¿Regenerar token? Deberás actualizar tu cron job.')){ document.getElementById('cron_token').value = '<?php echo wp_generate_password(32, false); ?>'; }">
                                    <span class="dashicons dashicons-update"></span>
                                    <?php _e('Regenerar', 'smart-catalog-sync'); ?>
                                </button>
                            </div>
                            <p class="scs-description"><?php _e('Token único para proteger la URL del cron. Mantenlo en secreto.', 'smart-catalog-sync'); ?></p>
                        </div>

                        <div class="scs-form-group">
                            <label class="scs-label">
                                <?php _e('URL para Cron Job del Servidor', 'smart-catalog-sync'); ?>
                            </label>
                            <?php
                            $cron_url = add_query_arg(array(
                                'action' => 'scs_cron_trigger',
                                'token' => $settings['cron_token']
                            ), admin_url('admin-ajax.php'));
                            ?>
                            <div class="scs-input-group">
                                <input
                                    type="text"
                                    id="cron_url"
                                    value="<?php echo esc_url($cron_url); ?>"
                                    class="scs-input"
                                    readonly
                                >
                                <button type="button" class="scs-btn scs-btn-secondary" onclick="navigator.clipboard.writeText(document.getElementById('cron_url').value); alert('URL copiada!');">
                                    <span class="dashicons dashicons-clipboard"></span>
                                    <?php _e('Copiar', 'smart-catalog-sync'); ?>
                                </button>
                            </div>
                            <p class="scs-description">
                                <?php _e('Configura tu cron job así (cada 15 minutos):', 'smart-catalog-sync'); ?><br>
                                <code>*/15 * * * * curl -s "<?php echo esc_url($cron_url); ?>" >/dev/null 2>&1</code>
                            </p>
                        </div>
                    </div>

                    <input type="hidden" name="scs_settings[last_sync]" value="<?php echo esc_attr($settings['last_sync']); ?>">

                    <div class="scs-actions">
                        <?php submit_button(__('Guardar Configuración', 'smart-catalog-sync'), 'primary scs-btn scs-btn-primary', 'submit', false); ?>
                        <button type="button" id="scs-manual-sync" class="scs-btn scs-btn-sync">
                            <span class="dashicons dashicons-update"></span>
                            <?php _e('Sincronizar Ahora', 'smart-catalog-sync'); ?>
                        </button>
                    </div>
                </form>

                <!-- Info Section -->
                <div class="scs-info-section">
                    <h3><?php _e('Formato de Datos JSON', 'smart-catalog-sync'); ?></h3>
                    <p><?php _e('Los productos se envían en el siguiente formato optimizado para IA:', 'smart-catalog-sync'); ?></p>
                    <pre class="scs-code-block"><code>{
  "sync_date": "2025-12-01T10:30:00Z",
  "store_info": {
    "name": "Mi Tienda",
    "url": "https://mitienda.com"
  },
  "products": [
    {
      "id": 123,
      "name": "Producto Ejemplo",
      "sku": "PROD-001",
      "price": 99.99,
      "regular_price": 129.99,
      "stock_quantity": 50,
      "stock_status": "instock",
      "description": "Descripción del producto...",
      "short_description": "Descripción corta...",
      "categories": ["Electrónica", "Accesorios"],
      "tags": ["nuevo", "oferta"],
      "images": ["url1.jpg", "url2.jpg"],
      "permalink": "https://mitienda.com/producto"
    }
  ]
}</code></pre>
                </div>

                <!-- Footer -->
                <div class="scs-footer">
                    <p>
                        Smart Catalog Sync v<?php echo SCS_VERSION; ?> |
                        <?php _e('Desarrollado por', 'smart-catalog-sync'); ?>
                        <strong>Wilmer Uzcategui</strong>
                    </p>
                </div>
            </div>

            <div id="scs-notification" class="scs-notification"></div>
        </div>
        <?php
    }
}
