<?php
/**
 * WP Optimal State - Uninstall Script
 * This file is executed when the plugin is uninstalled.
 * It cleans up all plugin data from the database and file system.
 * * @package WP_Optimal_State
 * @version 1.0.8
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN') || !defined('ABSPATH')) {
    exit;
}

// Verify user capabilities
if (!current_user_can('activate_plugins')) {
    wp_die(esc_html__('You do not have sufficient permissions to uninstall plugins.', 'wp-optimal-state'));
}

// Check if this is a multisite installation
$is_multisite = is_multisite();

/**
 * MODIFIED: Check if we should remove data on uninstall by reading from settings.json
 * * This respects the user's choice if they have a setting for data removal.
 * By default, we remove all plugin data.
 */
function wp_opt_state_should_remove_data() {
    $settings_file = plugin_dir_path(__FILE__) . 'settings.json';

    // Default to removing data if the settings file doesn't exist.
    if (!file_exists($settings_file)) {
        return true;
    }

    // Read settings from the JSON file.
    $settings_json = @file_get_contents($settings_file);
    if (empty($settings_json)) {
        return true; // Remove data if file is empty or unreadable.
    }

    $settings = json_decode($settings_json, true);
    if (!is_array($settings)) {
        return true; // Remove data if JSON is invalid.
    }
    
    // If user has explicitly chosen to keep data, respect that choice.
    if (isset($settings['keep_data_on_uninstall']) && $settings['keep_data_on_uninstall']) {
        return false; // Do NOT remove data.
    }
    
    // Default behavior is to remove all data.
    return true;
}

/**
 * Remove database backup files and the backup directory.
 */
function wp_opt_state_remove_backup_files() {
    $backup_dir = WP_CONTENT_DIR . '/uploads/db-backups/';
    
    if (!is_dir($backup_dir)) {
        return;
    }
    
    $files_to_delete = array_merge(
        glob($backup_dir . '*.sql'),
        [
            $backup_dir . '.htaccess',
            $backup_dir . 'index.php'
        ]
    );
    
    foreach ($files_to_delete as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    
    // Remove the backup directory if it's now empty.
    if (is_dir($backup_dir) && count(scandir($backup_dir)) <= 2) {
        @rmdir($backup_dir);
    }
}

/**
 * MODIFIED: Remove all data files from within the plugin's own directory.
 */
function wp_opt_state_remove_plugin_data_files() {
    $plugin_dir = plugin_dir_path(__FILE__);
    
    $files_to_delete = [
        $plugin_dir . 'optimization-log.json',
        $plugin_dir . 'settings.json',           // ADDED: Removes the new settings file.
        $plugin_dir . '.htaccess'                // ADDED: Removes the security .htaccess file.
    ];
    
    foreach ($files_to_delete as $file) {
        if (file_exists($file) && is_file($file)) {
            @unlink($file);
        }
    }
}

/**
 * Clean up all plugin data from the database and file system.
 */
function wp_opt_state_uninstall_cleanup() {
    global $wpdb;
    
    // --- Database Options Cleanup ---
    $options_to_delete = [
        'wp_opt_state_settings', // Legacy settings option
        'wp_opt_state_backup_reminder',
        'wp_opt_state_activation_time'
    ];
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
        delete_site_option($option); // For multisite
    }
    
    // --- Transients Cleanup ---
    // Remove any transients created by the plugin to avoid clutter.
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wp\_opt\_state\_%' OR option_name LIKE '\_transient\_timeout\_wp\_opt\_state\_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_site\_transient\_wp\_opt\_state\_%' OR option_name LIKE '\_site\_transient\_timeout\_wp\_opt\_state\_%'");
    
    // --- Cron/Scheduled Events Cleanup ---
    wp_clear_scheduled_hook('wp_opt_state_scheduled_cleanup');
    
    // --- File System Cleanup ---
    wp_opt_state_remove_backup_files();
    wp_opt_state_remove_plugin_data_files(); // MODIFIED: Call the updated function.
    
    // --- Cache Cleanup ---
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

/**
 * Handle multisite uninstallation.
 */
function wp_opt_state_multisite_uninstall() {
    global $wpdb;
    
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs}");
    
    if (empty($blog_ids)) {
        return;
    }
    
    $original_blog_id = get_current_blog_id();
    
    // Loop through each site in the network.
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        if (wp_opt_state_should_remove_data()) {
            wp_opt_state_uninstall_cleanup();
        }
        
        restore_current_blog();
    }
    
    // Switch back to the original blog.
    switch_to_blog($original_blog_id);
    
    // Remove any remaining network-wide options.
    delete_site_option('wp_opt_state_network_settings');
}

/**
 * Main uninstall execution logic.
 */
if ($is_multisite) {
    wp_opt_state_multisite_uninstall();
} else {
    if (wp_opt_state_should_remove_data()) {
        wp_opt_state_uninstall_cleanup();
    }
}