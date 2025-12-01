<?php
/**
 * Uninstall Smart Catalog Sync
 *
 * This file runs when the plugin is uninstalled (deleted).
 * It cleans up all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('scs_settings');

// Clear scheduled cron events
$timestamp = wp_next_scheduled('scs_auto_sync');
if ($timestamp) {
    wp_unschedule_event($timestamp, 'scs_auto_sync');
}

// Clear all cron schedules for this plugin
wp_clear_scheduled_hook('scs_auto_sync');
