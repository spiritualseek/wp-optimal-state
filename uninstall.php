<?php
/**
 * Safe Uninstall WP Optimal State Plugin
 * 
 * @package WP_Optimal_State
 * @version 1.0.7
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN') || !defined('ABSPATH')) {
    exit;
}

// Prevent unauthorized access
if (!current_user_can('activate_plugins')) {
    wp_die('You do not have sufficient permissions to uninstall this plugin.');
}

/**
 * Safe uninstaller with user data preservation
 */
class WP_Optimal_State_Safe_Uninstaller {
    
    private $backup_dir;
    private $settings_file;
    private $log_file;
    
    public function __construct() {
        $this->backup_dir = WP_CONTENT_DIR . '/uploads/db-backups/';
        $this->settings_file = plugin_dir_path(__FILE__) . 'settings.json';
        $this->log_file = plugin_dir_path(__FILE__) . 'optimization-log.json';
    }
    
    /**
     * Main uninstallation routine - PRESERVES USER DATA
     */
    public function uninstall() {
        $this->log_uninstall_start();
        
        // Step 1: Clear scheduled events (SAFE)
        $this->clear_scheduled_events();
        
        // Step 2: Remove ONLY plugin settings (SAFE)
        $this->remove_plugin_settings();
        
        // Step 3: REMOVED - Do NOT delete backup files (USER DATA!)
        // $this->remove_backup_files(); // INTENTIONALLY COMMENTED OUT
        
        // Step 4: Remove plugin config files (SAFE)
        $this->remove_plugin_config_files();
        
        $this->log_uninstall_complete();
    }
    
    /**
     * Clear scheduled events - SAFE
     */
    private function clear_scheduled_events() {
        $events = [
            'wp_opt_state_scheduled_cleanup'
        ];
        
        foreach ($events as $event) {
            $timestamp = wp_next_scheduled($event);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $event);
            }
            wp_clear_scheduled_hook($event);
        }
        
        $this->log("Cleared scheduled events");
    }
    
    /**
     * Remove ONLY plugin-specific settings - SAFE
     */
    private function remove_plugin_settings() {
        // ONLY remove options that are clearly plugin-specific
        $plugin_options = [
            'wp_opt_state_settings',
            'wp_opt_state_activation_time',
        ];
        
        foreach ($plugin_options as $option) {
            if (get_option($option)) {
                delete_option($option);
                $this->log("Removed option: {$option}");
            }
        }
        
        // Remove plugin transients with explicit naming
        $this->remove_plugin_transients();
    }
    
    /**
     * Remove ONLY plugin-specific transients - SAFE
     */
    private function remove_plugin_transients() {
        global $wpdb;
        
        // Very specific transient patterns to avoid deleting anything else
        $transient_patterns = [
            '_transient_wp_opt_state_%',
            '_transient_timeout_wp_opt_state_%',
            '_transient_wp_opt_state_rate_limit_%',
            '_transient_timeout_wp_opt_state_rate_limit_%',
        ];
        
        foreach ($transient_patterns as $pattern) {
            $transients = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
            
            foreach ($transients as $transient) {
                $transient_name = str_replace(['_transient_', '_transient_timeout_'], '', $transient);
                delete_transient($transient_name);
                $this->log("Removed transient: {$transient_name}");
            }
        }
        
        // Clear stats cache transient
        delete_transient('wp_opt_state_stats_cache');
    }
    
    /**
     * Remove plugin configuration files - SAFE (only our files)
     */
    private function remove_plugin_config_files() {
        $safe_to_remove = [];
        
        // Only remove files that WE created in OUR plugin directory
        if (file_exists($this->settings_file) && $this->is_plugin_file($this->settings_file)) {
            $safe_to_remove[] = $this->settings_file;
        }
        
        if (file_exists($this->log_file) && $this->is_plugin_file($this->log_file)) {
            $safe_to_remove[] = $this->log_file;
        }
        
        foreach ($safe_to_remove as $file) {
            if (is_file($file) && is_writable($file)) {
                if (@unlink($file)) {
                    $this->log("Removed plugin file: " . basename($file));
                }
            }
        }
    }
    
    /**
     * Verify file is actually within our plugin directory - SAFETY CHECK
     */
    private function is_plugin_file($file_path) {
        $plugin_dir = plugin_dir_path(__FILE__);
        $real_file_path = realpath($file_path);
        $real_plugin_dir = realpath($plugin_dir);
        
        return ($real_file_path && $real_plugin_dir && 
                strpos($real_file_path, $real_plugin_dir) === 0);
    }
    
    /**
     * INTENTIONALLY DOES NOT DELETE BACKUP FILES - USER DATA!
     * This preserves user's database backups which may be critical
     */
    private function preserve_backup_files() {
        $this->log("BACKUP FILES PRESERVED in: " . $this->backup_dir);
        $this->log("User must manually delete backups if desired");
        
        // Optional: Count backups for info
        if (is_dir($this->backup_dir)) {
            $backup_files = glob($this->backup_dir . '*.sql');
            $count = count($backup_files);
            $this->log("Preserved {$count} database backup files");
        }
    }
    
    /**
     * Logging methods
     */
    private function log_uninstall_start() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WP Optimal State: Starting SAFE uninstallation (preserving user data)");
        }
    }
    
    private function log_uninstall_complete() {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WP Optimal State: SAFE uninstallation completed - user backups preserved");
        }
    }
    
    private function log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("WP Optimal State Safe Uninstaller: " . $message);
        }
    }
}

/**
 * Execute SAFE uninstallation
 */
try {
    $uninstaller = new WP_Optimal_State_Safe_Uninstaller();
    $uninstaller->uninstall();
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("WP Optimal State: Uninstalled safely. User backup files preserved.");
    }
    
} catch (Exception $e) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log("WP Optimal State Uninstall Error: " . $e->getMessage());
    }
}