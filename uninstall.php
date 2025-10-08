<?php
/**
 * WP Optimal State - Uninstall Script
 * This file is executed when the plugin is uninstalled.
 * It cleans up all plugin data from the database and file system.
 * 
 * @package WP_Optimal_State
 * @version 1.0.7
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
 * Check if we should remove data on uninstall
 * 
 * This respects the user's choice if they have a setting for data removal.
 * By default, we remove all plugin data.
 */
function wp_opt_state_should_remove_data() {
    $settings = get_option('wp_opt_state_settings');
    
    // If settings exist and user has chosen to keep data, respect that
    if ($settings && isset($settings['keep_data_on_uninstall'])) {
        return !(bool) $settings['keep_data_on_uninstall'];
    }
    
    // Default behavior: remove data
    return true;
}

/**
 * Remove database backup files and directory
 */
function wp_opt_state_remove_backup_files() {
    $backup_dir = WP_CONTENT_DIR . '/uploads/db-backups/';
    
    // Check if backup directory exists
    if (!is_dir($backup_dir)) {
        return;
    }
    
    // Get all backup files
    $backup_files = glob($backup_dir . '*.sql');
    $protection_files = array(
        $backup_dir . '.htaccess',
        $backup_dir . 'index.php'
    );
    
    // Delete all backup files
    if (is_array($backup_files)) {
        foreach ($backup_files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    // Delete protection files
    foreach ($protection_files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
    
    // Remove the backup directory if empty
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        if (is_array($files) && count($files) <= 2) { // Only . and .. remain
            @rmdir($backup_dir);
        }
    }
}

/**
 * Remove optimization log file
 */
function wp_opt_state_remove_log_files() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $log_file = $plugin_dir . 'optimization-log.json';
    
    // Delete the optimization log file if it exists
    if (file_exists($log_file)) {
        @unlink($log_file);
    }
    
    // Also check for any other log files in the plugin directory
    $log_files = glob($plugin_dir . '*.log');
    if (is_array($log_files)) {
        foreach ($log_files as $log_file) {
            if (is_file($log_file)) {
                @unlink($log_file);
            }
        }
    }
}

/**
 * Clean up plugin data from database
 */
function wp_opt_state_uninstall_cleanup() {
    global $wpdb;
    
    // Plugin options to remove
    $options = array(
        'wp_opt_state_settings',
        'wp_opt_state_backup_reminder',
        'wp_opt_state_version',
        'wp_opt_state_activation_time',
        'wp_opt_state_last_cleanup',
        'wp_opt_state_stats_cache',
        'wp_opt_state_schedule',
        'wp_opt_state_optimization_log', // Old log option (if exists)
    );
    
    // Remove options safely
    foreach ($options as $option) {
        delete_option($option);
        delete_site_option($option); // For multisite
    }
    
    // Remove transients created by the plugin with prepared statement
    $transient_pattern = $wpdb->esc_like('_transient_wp_opt_state_') . '%';
    $timeout_pattern = $wpdb->esc_like('_transient_timeout_wp_opt_state_') . '%';
    $site_transient_pattern = $wpdb->esc_like('_site_transient_wp_opt_state_') . '%';
    $site_timeout_pattern = $wpdb->esc_like('_site_transient_timeout_wp_opt_state_') . '%';
    
    // Rate limiting transients
    $rate_limit_patterns = array(
        $wpdb->esc_like('_transient_wp_opt_state_rate_limit_') . '%',
        $wpdb->esc_like('_transient_timeout_wp_opt_state_rate_limit_') . '%',
    );
    
    $transient_queries = array(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $transient_pattern,
            $timeout_pattern,
            $site_transient_pattern,
            $site_timeout_pattern
        ),
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $rate_limit_patterns[0],
            $rate_limit_patterns[1]
        )
    );
    
    $all_transients = array();
    foreach ($transient_queries as $query) {
        $results = $wpdb->get_col($query);
        if (is_array($results)) {
            $all_transients = array_merge($all_transients, $results);
        }
    }
    
    // Delete each transient properly
    if (!empty($all_transients)) {
        foreach ($all_transients as $transient) {
            if (strpos($transient, '_site_transient_') === 0) {
                $transient_name = str_replace('_site_transient_timeout_', '', $transient);
                $transient_name = str_replace('_site_transient_', '', $transient_name);
                delete_site_transient($transient_name);
            } else {
                $transient_name = str_replace('_transient_timeout_', '', $transient);
                $transient_name = str_replace('_transient_', '', $transient_name);
                delete_transient($transient_name);
            }
        }
    }
    
    // Remove scheduled events
    $scheduled_hooks = array(
        'wp_opt_state_scheduled_cleanup',
        'wp_opt_state_daily_cleanup',
        'wp_opt_state_weekly_optimization'
    );
    
    foreach ($scheduled_hooks as $hook) {
        $timestamp = wp_next_scheduled($hook);
        if ($timestamp) {
            wp_unschedule_event($timestamp, $hook);
        }
        wp_clear_scheduled_hook($hook);
    }
    
    // Remove any custom user meta
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wp_opt_state_%'");
    
    // Remove file-based data
    wp_opt_state_remove_backup_files();
    wp_opt_state_remove_log_files();
    
    // Clear any cached data
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
    
    /**
     * Fire action hook for developers to add custom cleanup
     */
    do_action('wp_opt_state_before_cleanup_complete');
}

/**
 * Handle multisite uninstallation
 */
function wp_opt_state_multisite_uninstall() {
    global $wpdb;
    
    // Get all blog IDs in the network
    $blog_ids = $wpdb->get_col("SELECT blog_id FROM {$wpdb->blogs} WHERE archived = 0 AND deleted = 0 AND spam = 0");
    
    if (!is_array($blog_ids) || empty($blog_ids)) {
        return;
    }
    
    // Store original blog ID
    $original_blog_id = get_current_blog_id();
    
    // Loop through each site and clean up
    foreach ($blog_ids as $blog_id) {
        switch_to_blog($blog_id);
        
        // Check if we should clean this site's data
        if (wp_opt_state_should_remove_data()) {
            wp_opt_state_uninstall_cleanup();
        }
        
        restore_current_blog();
    }
    
    // Switch back to original blog
    switch_to_blog($original_blog_id);
    
    // Remove network-wide options
    $network_options = array(
        'wp_opt_state_network_settings',
        'wp_opt_state_network_version',
        'wp_opt_state_network_activation_time',
        'wp_opt_state_network_stats',
    );
    
    foreach ($network_options as $option) {
        delete_site_option($option);
    }
    
    // Remove network-wide file data
    wp_opt_state_remove_backup_files();
    wp_opt_state_remove_log_files();
}

/**
 * Main uninstall execution
 */
try {
    if ($is_multisite) {
        // Handle multisite installations
        wp_opt_state_multisite_uninstall();
    } else {
        // Handle single site installations
        if (wp_opt_state_should_remove_data()) {
            wp_opt_state_uninstall_cleanup();
        }
    }
    
    // Final cleanup action hook
    do_action('wp_opt_state_after_uninstall');
    
} catch (Exception $e) {
    // Log any errors during uninstall
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        error_log('WP Optimal State uninstall error: ' . $e->getMessage());
    }
    
    // Don't throw the exception to prevent WordPress from showing errors to users
    return;
}