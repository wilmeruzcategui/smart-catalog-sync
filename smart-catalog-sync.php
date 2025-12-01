<?php
/**
 * Plugin Name: Smart Catalog Sync
 * Plugin URI: https://github.com/wilmeruzcategui/smart-catalog-sync
 * Description: Sincroniza productos de WooCommerce con sistemas externos mediante JSON, optimizado para alimentar IAs con datos actualizados de inventario, precios y stock.
 * Version: 1.0.0
 * Author: Wilmer Uzcategui
 * Author URI: https://github.com/wilmeruzcategui
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: smart-catalog-sync
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SCS_VERSION', '1.0.0');
define('SCS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SCS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SCS_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Smart_Catalog_Sync {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load dependencies
        $this->load_dependencies();

        // Initialize admin
        if (is_admin()) {
            new SCS_Admin();
        }

        // Initialize sync engine
        new SCS_Sync_Engine();
    }

    private function load_dependencies() {
        require_once SCS_PLUGIN_DIR . 'includes/class-scs-admin.php';
        require_once SCS_PLUGIN_DIR . 'includes/class-scs-sync-engine.php';
        require_once SCS_PLUGIN_DIR . 'includes/class-scs-product-formatter.php';
    }

    public function activate() {
        // Create default options
        $default_options = array(
            'webhook_url' => '',
            'sync_interval' => 'hourly',
            'last_sync' => 0,
            'sync_enabled' => false,
            'include_images' => true,
            'include_variations' => true,
            'include_categories' => true,
        );

        add_option('scs_settings', $default_options);

        // Schedule cron job
        if (!wp_next_scheduled('scs_auto_sync')) {
            wp_schedule_event(time(), 'hourly', 'scs_auto_sync');
        }
    }

    public function deactivate() {
        // Clear scheduled cron
        $timestamp = wp_next_scheduled('scs_auto_sync');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'scs_auto_sync');
        }
    }

    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('Smart Catalog Sync requiere WooCommerce para funcionar. Por favor, instala y activa WooCommerce.', 'smart-catalog-sync'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
Smart_Catalog_Sync::get_instance();
