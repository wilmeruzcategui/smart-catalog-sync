<?php
/**
 * Sync Engine - Handles product synchronization
 */

if (!defined('ABSPATH')) {
    exit;
}

class SCS_Sync_Engine {

    public function __construct() {
        add_action('scs_auto_sync', array($this, 'auto_sync'));
        $this->register_custom_cron_intervals();
    }

    /**
     * Register custom cron intervals
     */
    public function register_custom_cron_intervals() {
        add_filter('cron_schedules', function($schedules) {
            $schedules['every_15_minutes'] = array(
                'interval' => 15 * 60,
                'display' => __('Cada 15 minutos', 'smart-catalog-sync')
            );
            $schedules['every_30_minutes'] = array(
                'interval' => 30 * 60,
                'display' => __('Cada 30 minutos', 'smart-catalog-sync')
            );
            return $schedules;
        });
    }

    /**
     * Automatic sync triggered by cron
     */
    public function auto_sync() {
        $settings = get_option('scs_settings');

        // Check if sync is enabled
        if (!$settings['sync_enabled']) {
            return;
        }

        // Check if webhook URL is configured
        if (empty($settings['webhook_url'])) {
            error_log('Smart Catalog Sync: Webhook URL not configured');
            return;
        }

        $this->sync_products();
    }

    /**
     * Manual sync triggered from admin
     */
    public function sync_products() {
        $settings = get_option('scs_settings');

        if (empty($settings['webhook_url'])) {
            return array(
                'success' => false,
                'message' => __('URL de destino no configurada', 'smart-catalog-sync'),
            );
        }

        // Format products
        $formatter = new SCS_Product_Formatter();
        $data = $formatter->format_all_products();

        // Send data
        $response = wp_remote_post($settings['webhook_url'], array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
                'User-Agent' => 'Smart-Catalog-Sync/' . SCS_VERSION,
            ),
            'body' => json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'timeout' => 30,
            'data_format' => 'body',
        ));

        // Update last sync time
        $settings['last_sync'] = current_time('timestamp');
        update_option('scs_settings', $settings);

        // Handle response
        if (is_wp_error($response)) {
            error_log('Smart Catalog Sync Error: ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => $response->get_error_message(),
            );
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        if ($status_code >= 200 && $status_code < 300) {
            return array(
                'success' => true,
                'message' => sprintf(__('Se sincronizaron %d productos exitosamente', 'smart-catalog-sync'), $data['total_products']),
                'products_count' => $data['total_products'],
                'status_code' => $status_code,
                'response' => $response_body,
            );
        } else {
            error_log('Smart Catalog Sync HTTP Error: ' . $status_code . ' - ' . $response_body);
            return array(
                'success' => false,
                'message' => sprintf(__('Error HTTP %d', 'smart-catalog-sync'), $status_code),
                'status_code' => $status_code,
                'response' => $response_body,
            );
        }
    }

    /**
     * Get sync status
     */
    public function get_sync_status() {
        $settings = get_option('scs_settings');
        $next_scheduled = wp_next_scheduled('scs_auto_sync');

        return array(
            'enabled' => $settings['sync_enabled'],
            'last_sync' => $settings['last_sync'],
            'next_sync' => $next_scheduled,
            'interval' => $settings['sync_interval'],
        );
    }
}
