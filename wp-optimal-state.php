<?php
/**
 * Plugin Name: WP Optimal State ✔
 * Plugin URI: https://spiritualseek.com/wp-optimal-state-wordpress-plugin/
 * Description: Advanced WordPress optimization and cleaning plugin with an integrated database backup manager. It cleans your database, optimizes your tables, and allows you to create, restore, and manage database backups.
 * Version: 1.0.7
 * Author: Luke Garrison / The Spiritual Seek
 * Author URI: https://spiritualseek.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-optimal-state
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class for WP Optimal State
 * Handles database optimization, cleanup, and performance improvements
 */
class WP_Optimal_State {
    
    private $plugin_name = 'WP Optimal State';
    private $version = '1.0.7';
    private $option_name = 'wp_opt_state_settings';
    private $nonce_action = 'wp_opt_state_nonce';
    private $db_backup_manager;
    private $log_option_name = 'wp_opt_state_optimization_log';
    
public function __construct() {
    $this->log_file_path = plugin_dir_path(__FILE__) . 'optimization-log.json';
    $this->db_backup_manager = new DB_Backup_Manager($this->log_file_path);

    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    add_action('plugins_loaded', array($this, 'load_textdomain'));
    
    $this->register_ajax_handlers();
    add_action('wp_opt_state_scheduled_cleanup', array($this, 'run_scheduled_cleanup'));
    add_action('update_option_wp_opt_state_settings', array($this, 'handle_settings_update'), 10, 3);
    add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));
    add_action('wp_ajax_wp_opt_state_save_auto_settings', array($this, 'ajax_save_auto_settings'));
    add_action('init', array($this, 'maybe_reschedule_cron'));
}
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-optimal-state', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Register all AJAX handlers for the optimization features
     */
private function register_ajax_handlers() {
    $handlers = array(
        'get_stats', 'clean_item', 'optimize_tables', 
        'one_click_optimize', 'get_db_size', 
        'optimize_autoload', 'analyze_repair_tables',
        'get_optimization_log', 'save_max_backups'
    );
        
        foreach ($handlers as $handler) {
            add_action('wp_ajax_wp_opt_state_' . $handler, array($this, 'ajax_' . $handler));
        }
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_menu_page(
            $this->plugin_name,
            'Optimal State',
            'manage_options',
            'wp-optimal-state',
            array($this, 'display_admin_page'),
            'dashicons-performance',
            80
        );
    }
    
    /**
 * AJAX handler for saving the auto optimization days setting.
 */
public function ajax_save_auto_settings() {
    check_ajax_referer($this->nonce_action, 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
        return;
    }

    $days = isset($_POST['auto_optimize_days']) ? absint($_POST['auto_optimize_days']) : 0;

    $options = get_option($this->option_name, array('auto_optimize_days' => 0));
    $options['auto_optimize_days'] = min(max($days, 0), 365);
    
    // Manually update the option
    update_option($this->option_name, $options);
    
    // Trigger the scheduling logic manually
    $this->handle_settings_update(null, null, $options);

    wp_send_json_success(array(
        'message' => __('Automatic optimization setting saved successfully!', 'wp-optimal-state'),
        'days' => $options['auto_optimize_days']
    ));
}
    
/**
     * Enqueue combined admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wp-optimal-state') {
            return;
        }
        
        // Enqueue the combined CSS file
        wp_enqueue_style(
            'wp-opt-state-admin-styles',
            plugin_dir_url(__FILE__) . 'css/admin.css',
            array(),
            $this->version
        );
        
        // Enqueue the combined JavaScript file
        wp_enqueue_script(
            'wp-opt-state-admin-script',
            plugin_dir_url(__FILE__) . 'js/admin.js',
            array('jquery'),
            $this->version,
            true
        );
        
        // Localize scripts for the Optimizer
        $optimizer_data = array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce($this->nonce_action),
            'settings_updated' => (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true')
        );
        wp_localize_script('wp-opt-state-admin-script', 'wpOptStateAjax', $optimizer_data);

        // Localize scripts for the Backup Manager
        wp_localize_script('wp-opt-state-admin-script', 'dbBackupManager', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('db_backup_nonce')
        ));
    }

    /**
     * Display the main admin page with integrated backup UI
     */
    public function display_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-optimal-state'));
    }
    
    // Data for the optimizer settings form
    $options = get_option($this->option_name, array());
    $auto_optimize_days = isset($options['auto_optimize_days']) ? intval($options['auto_optimize_days']) : 0;
        
        // Data for the optimizer settings form
        $options = get_option($this->option_name, array());
        $auto_optimize_days = isset($options['auto_optimize_days']) ? intval($options['auto_optimize_days']) : 0;

        // Data for the backup manager UI
        $backups = $this->db_backup_manager->get_backups();
        ?>
        <div class="wrap wp-opt-state-wrap">
            <h1 class="wp-opt-state-title"><span class="dashicons dashicons-performance"></span> <?php echo esc_html($this->plugin_name); ?></h1>
                <div class="db-backup-wrap" style="margin: 0;">
                    
				<div class="wp-opt-state-container">
                <div class="wp-opt-state-notice">
                    <strong>ℹ️ <?php esc_html_e('Need Help?', 'wp-optimal-state'); ?></strong> <?php esc_html_e('Check out the full plugin manual for detailed instructions and best practices.', 'wp-optimal-state'); ?>
                    <a href="https://spiritualseek.com/wp-optimal-state-wordpress-plugin/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read the Manual 📘', 'wp-optimal-state'); ?></a>
                </div>
                
                    <div class="db-backup-card">
                        
                        
                        
       <div class="db-backup-card">
    <h2><span class="dashicons dashicons-database-export" style="font-size: 24px; height: 24px; width: 24px;"></span> <?php esc_html_e('1. Create a Database Backup', 'wp-optimal-state'); ?></h2>
    <p style="line-height: 1.7em;"><?php echo wp_kses(sprintf(__('💾 Always backup your database before performing cleanup operations. <br>✔ You will be able to restore it if something goes wrong during cleanup.<br>📁 Backups are stored in your <span style="color: #7C092E;">/wp-content/uploads/db-backups</span> folder and can <span style="text-decoration: underline;">consume disk space</span>.', 'wp-optimal-state')), array('strong' => array(), 'br' => array(), 'span' => array('style' => array()))); ?></p>
    
    
    <div style="margin-bottom: 15px;">
        <label for="max_backups_setting" style="display: block; margin-bottom: 5px; font-weight: 600;">
            <?php esc_html_e('Maximum Backups to Keep:', 'wp-optimal-state'); ?>
        </label>
        <select style="font-weight: bold; width: 100px;" name="max_backups_setting" id="max_backups_setting">
            <?php
            $current_max = isset($options['max_backups']) ? intval($options['max_backups']) : 3;
            for ($i = 1; $i <= 10; $i++) {
                $selected = ($i === $current_max) ? 'selected' : '';
                echo '<option value="' . esc_attr($i) . '" ' . $selected . '>' . esc_html($i) . '</option>';
            }
            ?>
        </select>
        <button type="button" class="button" id="save-max-backups-btn" style="margin-left: 10px;">
            <?php esc_html_e('✔ Save', 'wp-optimal-state'); ?>
        </button>
        <p class="description" style="margin-top: 5px;">
            <?php esc_html_e('⚠️ Older backups will be automatically deleted when this limit is reached.', 'wp-optimal-state'); ?>
        </p>
    </div>
       <button type="button" class="button button-primary button-large" id="create-backup-btn">
                            <span class="dashicons dashicons-plus-alt"></span> <?php esc_html_e('Create Backup Now', 'wp-optimal-state'); ?>
                        </button>
                        <div class="db-backup-spinner" id="backup-spinner" style="display:none;">
                            <span class="spinner is-active"></span>
                            <span><?php esc_html_e('Creating backup...', 'wp-optimal-state'); ?></span>
                        </div>
                    </div>
                    
                    <div class="db-backup-card">
                        <h2><span class="dashicons dashicons-database-view" style="font-size: 24px; height: 24px; width: 24px;"></span> <?php esc_html_e('1.1 Manage Existing Backups', 'wp-optimal-state'); ?></h2>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Backup Name', 'wp-optimal-state'); ?></th>
                                    <th><?php esc_html_e('Date Created', 'wp-optimal-state'); ?></th>
                                    <th><?php esc_html_e('Size', 'wp-optimal-state'); ?></th>
                                    <th><?php esc_html_e('Actions', 'wp-optimal-state'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="backups-list">
                                <?php if (empty($backups)): ?>
                                    <tr>
                                        <td colspan="4" class="db-backup-empty"><?php esc_html_e('No backups found. Create your first backup!', 'wp-optimal-state'); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($backups as $backup): ?>
                                        <tr data-file="<?php echo esc_attr($backup['filename']); ?>">
                                            <td><strong><?php echo esc_html($backup['filename']); ?></strong></td>
                                            <td><?php echo esc_html($backup['date']); ?></td>
                                            <td><?php echo esc_html($backup['size']); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url($backup['download_url']); ?>" class="button download-backup">
                                                    <span class="dashicons dashicons-download"></span> <?php esc_html_e('Download', 'wp-optimal-state'); ?>
                                                </a>
                                                <button class="button restore-backup" data-file="<?php echo esc_attr($backup['filename']); ?>">
                                                    <span class="dashicons dashicons-backup"></span> <?php esc_html_e('Restore', 'wp-optimal-state'); ?>
                                                </button>
                                                <button class="button delete-backup" data-file="<?php echo esc_attr($backup['filename']); ?>">
                                                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'wp-optimal-state'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="wp-opt-state-grid-2">
                    <div class="wp-opt-state-card wp-opt-state-card-highlight">
                        <h2>💥 <?php esc_html_e('2. One-Click Optimization', 'wp-optimal-state'); ?></h2>
                        <p><?php esc_html_e('Perform all safe optimizations with one click', 'wp-optimal-state'); ?></p>
                        <button class="button button-primary button-hero wp-opt-state-one-click" id="wp-opt-state-one-click">
                            <?php esc_html_e('🚀 Optimize Now', 'wp-optimal-state'); ?>
                        </button>
                        <div id="wp-opt-state-one-click-results" class="wp-opt-state-results"></div>
                    </div>
                    
<div class="wp-opt-state-card">
    <h2>📊 <?php esc_html_e('3. Database Statistics', 'wp-optimal-state'); ?></h2>
    <div id="wp-opt-state-stats-loading" class="wp-opt-state-loading"><?php esc_html_e('Loading statistics...', 'wp-optimal-state'); ?></div>
    
    <div id="wp-opt-state-stats-wrapper" class="wp-opt-state-stats-wrapper">
        <div id="wp-opt-state-stats" class="wp-opt-state-stats"></div>
    </div>
    <button class="button wp-opt-state-toggle-stats" id="wp-opt-state-toggle-stats" style="margin-bottom: 15px; width: 100%; display: none;">
        <?php esc_html_e('Show More Stats ↓', 'wp-optimal-state'); ?>
    </button>
    <div id="wp-opt-state-db-size" class="wp-opt-state-db-size">
        <strong><?php esc_html_e('Total Database Size:', 'wp-optimal-state'); ?></strong> <span id="wp-opt-state-db-size-value"><?php esc_html_e('Calculating...', 'wp-optimal-state'); ?></span>
    </div>
    <button class="button wp-opt-state-refresh-stats" id="wp-opt-state-refresh-stats"><?php esc_html_e('⟲ Refresh Stats', 'wp-optimal-state'); ?></button>
</div>
                </div>
                
                <div class="wp-opt-state-card">
                    <h2>🧹 <?php esc_html_e('4. Detailed Database Cleanup', 'wp-optimal-state'); ?></h2>
                    <div class="wp-opt-state-cleanup-grid" id="wp-opt-state-cleanup-items"></div>
                </div>
                
                <div class="wp-opt-state-card">
                    <h2>🗄️ <?php esc_html_e( '5. Advanced Database Optimization', 'wp-optimal-state'); ?></h2>
                    <p><?php esc_html_e('🔹 Optimize and repair database tables to improve performance.', 'wp-optimal-state'); ?><br>
                    <strong>‼️ Caution</strong>: These operations may make your website <u>unresponsive</u> for a few minutes, especially if your database is large and has never been optimized!
                    <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                        <button class="button wp-opt-state-refresh-stats" id="wp-opt-state-optimize-tables"><?php esc_html_e('⚡ Optimize All Tables', 'wp-optimal-state'); ?></button>
                        <button class="button wp-opt-state-refresh-stats" id="wp-opt-state-analyze-repair-tables"><?php esc_html_e('🛠️ Analyze & Repair Tables', 'wp-optimal-state'); ?></button>
                        <button class="button wp-opt-state-refresh-stats" id="wp-opt-state-optimize-autoload"><?php esc_html_e('💾 Optimize Autoloaded Options', 'wp-optimal-state'); ?></button>
                    </div>
                    <div id="wp-opt-state-table-results" class="wp-opt-state-results"></div>
                    <div style="margin-top: 20px; line-height: 1.7em;">
<strong>⚡ Optimize Tables</strong>: Runs <u>OPTIMIZE TABLE</u> on all database tables to reclaim space and improve query speed.<br>
<strong>🛠️ Analyze & Repair</strong>: Checks tables for errors/corruption (<u>CHECK TABLE</u>), then runs <u>REPAIR TABLE</u> to fix issues.<br>
<strong>💾 Autoloaded Options</strong>: Identifies large autoloaded options and sets them to <u>non-autoload</u> to boost site speed.
</div>
                </div>
                
<div class="wp-opt-state-card">
    <h2><span class="dashicons dashicons-database-export" style="font-size: 24px; height: 24px; width: 19px;"></span>🧹 <?php esc_html_e('6. Automatic Backup and Cleaning', 'wp-optimal-state'); ?></h2>
    <div id="wp-opt-state-auto-settings-form">
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Run Tasks Automatically Every', 'wp-optimal-state'); ?></th>
                <td>
                    <input type="number" style="font-weight: bold;" id="auto_optimize_days" name="<?php echo esc_attr($this->option_name); ?>[auto_optimize_days]" value="<?php echo esc_attr($auto_optimize_days); ?>" min="0" max="365"> <?php echo __('<strong>DAYS</strong> (0 to disable)', 'wp-optimal-state'); ?>
                    <p class="description">
                        <?php if ($auto_optimize_days > 0): ?>
                            <span id="auto-status-enabled">✅ <?php echo sprintf(esc_html__('Automated optimization is enabled and will run every %d days.', 'wp-optimal-state'), $auto_optimize_days); ?></span>
                            <span id="auto-status-disabled" style="display:none;">🔴 <?php esc_html_e('Automated optimization is currently disabled.', 'wp-optimal-state'); ?></span>
                        <?php else: ?>
                            <span id="auto-status-enabled" style="display:none;">✅ <?php echo sprintf(esc_html__('Automated optimization is enabled and will run every %d days.', 'wp-optimal-state'), $auto_optimize_days); ?></span>
                            <span id="auto-status-disabled">🔴 <?php esc_html_e('Automated optimization is currently disabled.', 'wp-optimal-state'); ?></span>
                        <?php endif; ?>
                        <br>
                       <?php echo 'ℹ️ ' . __('When enabled, the plugin will automatically: 1. <u>Backup your database</u>; 2. <u>Perform One-Click Optimization</u> on the specified schedule.', 'wp-optimal-state'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <button type="submit" class="button button-primary" style="font-size: 1.1em; padding: 5px 16px; margin: 8px 0 20px 0;" id="save-auto-optimize-btn">
            <?php esc_html_e('✓ Save Settings', 'wp-optimal-state'); ?>
        </button>
    </div>
    <div id="wp-opt-state-settings-log"></div>
</div>
            </div>
        </div>
        <?php
    }
    
/**
 * Log optimization execution to file
 */
private function log_optimization($type = 'scheduled', $operation = 'One-Click Optimization + Database Backup', $backup_filename = '') {
    $log_entries = $this->get_optimization_log();
    
    $log_entry = array(
        'timestamp' => current_time('timestamp'),
        'type' => $type,
        'date' => current_time('Y-m-d H:i:s'),
        'operation' => $operation,
        'backup_filename' => $backup_filename
    );
    
    // Keep only last 20 entries to prevent log from growing too large
    array_unshift($log_entries, $log_entry);
    $log_entries = array_slice($log_entries, 0, 30);
    
    // Save to file
    $this->save_log_to_file($log_entries);
}

/**
 * Save log entries to file
 */
private function save_log_to_file($log_entries) {
    $json_data = json_encode($log_entries, JSON_PRETTY_PRINT);
    if ($json_data !== false) {
        @file_put_contents($this->log_file_path, $json_data);
    }
}

/**
 * Get optimization log from file
 */
private function get_optimization_log() {
    if (!file_exists($this->log_file_path)) {
        return array();
    }
    
    $json_data = @file_get_contents($this->log_file_path);
    if ($json_data === false) {
        return array();
    }
    
    $log_entries = json_decode($json_data, true);
    return is_array($log_entries) ? $log_entries : array();
}
    
    /**
     * Set optimization limits for memory and time
     */
    private function set_optimization_limits() {
        @set_time_limit(300); // 5 minutes
        @ini_set('memory_limit', '256M');
    }
    
    /**
     * AJAX handler for getting database statistics
     */
public function ajax_get_stats() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
        return;
    }
    
    // Check if we should bypass cache
    $force_refresh = isset($_POST['force_refresh']) && $_POST['force_refresh'] == 'true';
    
    // Check if we have cached stats (unless forcing refresh)
    if (!$force_refresh) {
        $cached_stats = get_transient('wp_opt_state_stats_cache');
        if ($cached_stats !== false) {
            wp_send_json_success($cached_stats);
            return;
        }
    }

    global $wpdb;
    
    // Initialize default metrics
    $db_metrics = (object) [
        'total_overhead' => 0,
        'total_indexes_size' => 0,
        'total_tables_count' => 0
    ];

    // Fetch overall DB technical metrics
    $query_result = $wpdb->get_row($wpdb->prepare("
        SELECT 
            SUM(data_free) as total_overhead,
            SUM(index_length) as total_indexes_size,
            COUNT(*) as total_tables_count,
            MIN(create_time) as db_creation_date
        FROM information_schema.TABLES
        WHERE table_schema = %s
    ", DB_NAME));

    if (!is_wp_error($query_result) && !is_null($query_result)) {
        $db_metrics = $query_result;
    }

    // Get autoloaded options data
    $autoload_data = $wpdb->get_row("
        SELECT 
            COUNT(*) as autoload_count,
            SUM(LENGTH(option_value)) as autoload_size
        FROM {$wpdb->options} 
        WHERE autoload = 'yes'
    ");

    // Prepare stats array
    $stats = array(
        'post_revisions' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'")),
        'auto_drafts' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'auto-draft'")),
        'trashed_posts' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'trash'")),
        'spam_comments' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'")),
        'trashed_comments' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'trash'")),
        'orphaned_postmeta' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})")),
        'orphaned_commentmeta' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_id FROM {$wpdb->comments})")),
        'orphaned_relationships' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts})")),
        'expired_transients' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()")),
        'all_transients' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'")),
        'duplicate_postmeta' => absint($this->count_duplicate_postmeta()),
        'duplicate_commentmeta' => absint($this->count_duplicate_commentmeta()),
        'orphaned_usermeta' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users})")),
        'unapproved_comments' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '0'")),
        'pingbacks' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'pingback'")),
        'trackbacks' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_type = 'trackback'")),
        'table_overhead' => is_numeric($db_metrics->total_overhead) ? size_format($db_metrics->total_overhead, 2) : '0 B',
        'total_indexes_size' => is_numeric($db_metrics->total_indexes_size) ? size_format($db_metrics->total_indexes_size, 2) : '0 B',
        'autoload_options' => absint($autoload_data->autoload_count),
        'autoload_size' => is_numeric($autoload_data->autoload_size) ? size_format($autoload_data->autoload_size, 2) : '0 B',
        'total_tables_count' => absint($db_metrics->total_tables_count),
        'db_creation_date' => get_option('wp_opt_state_activation_time') ?  date('Y-m-d', get_option('wp_opt_state_activation_time')) : (get_option('wp_install') ? date('Y-m-d H:i', strtotime(get_option('wp_install'))) : 
        'Unknown')
    );
    
    // Cache stats for 15 minutes
    set_transient('wp_opt_state_stats_cache', $stats, 15 * MINUTE_IN_SECONDS);
    
    wp_send_json_success($stats);
}
    
    /**
     * AJAX handler for cleaning specific items
     */
    public function ajax_clean_item() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
        }
        
        $this->set_optimization_limits();
        
        $item_type = sanitize_key($_POST['item_type']);
        
        $method = 'clean_' . $item_type;
        
        if (method_exists($this, $method)) {
            $cleaned = $this->$method();
            // Clear stats cache after cleanup
            delete_transient('wp_opt_state_stats_cache');
            wp_send_json_success($cleaned);
        } else {
            wp_send_json_error(__('Invalid cleanup type', 'wp-optimal-state'));
        }
    }
    
    /**
     * AJAX handler for one-click optimization
     */
public function ajax_one_click_optimize() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
    }
    
    $this->set_optimization_limits();
    
    $cleaned = $this->perform_optimizations(true);
    $this->log_optimization('manual', 'One-Click Optimization');
    
    // Clear stats cache after optimization
    delete_transient('wp_opt_state_stats_cache');
    
    wp_send_json_success($cleaned);
}
    
    /**
     * AJAX handler for getting optimization log
     */
    public function ajax_get_optimization_log() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
        }
        
        $log = $this->get_optimization_log();
        wp_send_json_success($log);
    }
    
    /**
 * AJAX handler for saving max backups setting
 */
public function ajax_save_max_backups() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized access', 'wp-optimal-state')));
    }
    
    $max_backups = isset($_POST['max_backups']) ? intval($_POST['max_backups']) : 5;
    $max_backups = min(max($max_backups, 1), 10);
    
    $options = get_option($this->option_name, array());
    $options['max_backups'] = $max_backups;
    
    update_option($this->option_name, $options);
    
    wp_send_json_success(array('message' => __('Automatic optimization setting saved successfully!', 'wp-optimal-state')));
}
    
    /**
     * Perform optimizations (used for both AJAX and scheduled; returns data if $return_data true)
     */
    private function perform_optimizations($return_data = false) {
        $cleaned = array();
        
        $cleaned['post_revisions'] = $this->clean_post_revisions();
        $cleaned['auto_drafts'] = $this->clean_auto_drafts();
        $cleaned['trashed_posts'] = $this->clean_trashed_posts();
        $cleaned['spam_comments'] = $this->clean_spam_comments();
        $cleaned['trashed_comments'] = $this->clean_trashed_comments();
        $cleaned['orphaned_postmeta'] = $this->clean_orphaned_postmeta();
        $cleaned['orphaned_commentmeta'] = $this->clean_orphaned_commentmeta();
        $cleaned['orphaned_relationships'] = $this->clean_orphaned_relationships();
        $cleaned['expired_transients'] = $this->clean_expired_transients();
        $cleaned['duplicate_postmeta'] = $this->clean_duplicate_postmeta();
        $cleaned['duplicate_commentmeta'] = $this->clean_duplicate_commentmeta();
        $cleaned['orphaned_usermeta'] = $this->clean_orphaned_usermeta();
        $cleaned['pingbacks'] = $this->clean_pingbacks();
        $cleaned['trackbacks'] = $this->clean_trackbacks();
        
        $this->perform_optimize_tables(false); // Silent optimize
        
        if ($return_data) {
            return $cleaned;
        }
    }
    
/**
 * Enhanced AJAX handler for optimizing tables
 */
public function ajax_optimize_tables() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
    }
    
    $result = $this->perform_optimize_tables(true);
    
    // Clear stats cache after optimization
    delete_transient('wp_opt_state_stats_cache');
    
    wp_send_json_success($result);
}
    
/**
 * Optimize tables with better error handling and progress tracking
 */
private function perform_optimize_tables($return_data = false) {
    global $wpdb;
    
    $this->set_optimization_limits();
    
    // Get all tables with their current status
    $tables = $wpdb->get_results("
        SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH, DATA_FREE
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        AND TABLE_TYPE = 'BASE TABLE'
    ", ARRAY_A);
    
    $results = [
        'optimized' => 0,
        'skipped' => 0,
        'failed' => 0,
        'reclaimed' => 0,
        'details' => []
    ];
    
    foreach ($tables as $table) {
        $table_name = $table['TABLE_NAME'];
        $initial_overhead = $table['DATA_FREE'] ?: 0;
        
        // Skip tables that don't need optimization
        if ($this->should_skip_table_optimization($table)) {
            $results['skipped']++;
            $results['details'][] = [
                'table' => $table_name,
                'status' => 'skipped',
                'reason' => 'No overhead or not supported'
            ];
            continue;
        }
        
        try {
            // Use proper table escaping
            $escaped_table_name = $wpdb->_escape($table_name);
            $result = $wpdb->query("OPTIMIZE TABLE `$escaped_table_name`");
            
            if ($result !== false) {
                // Get post-optimization stats
                $optimized_stats = $wpdb->get_row($wpdb->prepare("
                    SELECT DATA_FREE 
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() 
                    AND TABLE_NAME = %s
                ", $table_name), ARRAY_A);
                
                $final_overhead = $optimized_stats['DATA_FREE'] ?: 0;
                $reclaimed = max(0, $initial_overhead - $final_overhead);
                
                $results['optimized']++;
                $results['reclaimed'] += $reclaimed;
                $results['details'][] = [
                    'table' => $table_name,
                    'status' => 'optimized',
                    'reclaimed' => size_format($reclaimed, 2),
                    'initial_overhead' => size_format($initial_overhead, 2)
                ];
            } else {
                $results['failed']++;
                $results['details'][] = [
                    'table' => $table_name,
                    'status' => 'failed',
                    'error' => $wpdb->last_error
                ];
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'table' => $table_name,
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
        
        // Prevent server overload with large databases
        if (count($tables) > 50) {
            usleep(100000); // 0.1 second delay between large operations
        }
    }
    
    if ($return_data) {
        return $results;
    }
}

/**
 * Determine if a table should be skipped for optimization
 */
private function should_skip_table_optimization($table) {
    // Skip views
    if (isset($table['TABLE_TYPE']) && $table['TABLE_TYPE'] !== 'BASE TABLE') {
        return true;
    }
    
    // Skip tables with no rows
    if (empty($table['TABLE_ROWS']) || $table['TABLE_ROWS'] == 0) {
        return true;
    }
    
    // Skip tables with minimal overhead (less than 1KB)
    $min_overhead = 1024;
    if (empty($table['DATA_FREE']) || $table['DATA_FREE'] < $min_overhead) {
        return true;
    }
    
    // Skip MEMORY tables (they don't benefit from OPTIMIZE)
    if (isset($table['ENGINE']) && strtoupper($table['ENGINE']) === 'MEMORY') {
        return true;
    }
    
    return false;
}
    
/**
 * AJAX handler for analyze and repair tables with better diagnostics
 */
public function ajax_analyze_repair_tables() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
    }
    
    $this->set_optimization_limits();
    
    global $wpdb;
    
    $results = [
        'analyzed' => 0,
        'repaired' => 0,
        'corrupted' => 0,
        'optimized' => 0,
        'failed' => 0,
        'details' => []
    ];
    
    // Get all tables with their current status
    $tables = $wpdb->get_results("
        SELECT TABLE_NAME, ENGINE, TABLE_ROWS, TABLE_COLLATION
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
        AND TABLE_TYPE = 'BASE TABLE'
    ", ARRAY_A);
    
    foreach ($tables as $table) {
        $table_name = $table['TABLE_NAME'];
        $results['analyzed']++;
        
        try {
            // Step 1: Analyze table for corruption
            $escaped_table_name = $wpdb->_escape($table_name);
            $check_result = $wpdb->get_results("CHECK TABLE `$escaped_table_name`", ARRAY_A);
            
            $needs_repair = false;
            $corruption_found = false;
            
            foreach ($check_result as $check_row) {
                $msg_type = strtolower($check_row['Msg_type']);
                $msg_text = strtolower($check_row['Msg_text']);
                
                if ($msg_type === 'error' || 
                    strpos($msg_text, 'corrupt') !== false ||
                    strpos($msg_text, 'error') !== false) {
                    $needs_repair = true;
                    $corruption_found = true;
                    $results['corrupted']++;
                    break;
                }
            }
            
            // Step 2: Repair if needed
            if ($needs_repair) {
                $repair_result = $wpdb->get_results("REPAIR TABLE `$escaped_table_name`", ARRAY_A);
                
                $repair_success = false;
                foreach ($repair_result as $repair_row) {
                    if (strtolower($repair_row['Msg_type']) === 'status' && 
                        strpos(strtolower($repair_row['Msg_text']), 'ok') !== false) {
                        $repair_success = true;
                        $results['repaired']++;
                        break;
                    }
                }
                
                if (!$repair_success) {
                    $results['failed']++;
                }
            }
            
            // Step 3: Always optimize after repair (or if table is large)
            if ($needs_repair || $table['TABLE_ROWS'] > 1000) {
                $optimize_result = $wpdb->query("OPTIMIZE TABLE `$escaped_table_name`");
                if ($optimize_result) {
                    $results['optimized']++;
                }
            }
            
            // Record details
            $results['details'][] = [
                'table' => $table_name,
                'corrupted' => $corruption_found,
                'repaired' => $needs_repair && isset($repair_success) ? $repair_success : null,
                'optimized' => $needs_repair || $table['TABLE_ROWS'] > 1000
            ];
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['details'][] = [
                'table' => $table_name,
                'error' => $e->getMessage()
            ];
        }
        
        // Prevent server overload
        if (count($tables) > 30) {
            usleep(150000); // 0.15 second delay
        }
    }
    
    // Clear stats cache after repair
    delete_transient('wp_opt_state_stats_cache');
    
    wp_send_json_success($results);
}
    
/**
 * AJAX handler for optimizing autoload with smarter analysis
 */
public function ajax_optimize_autoload() {
    check_ajax_referer($this->nonce_action, 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
    }
    
    global $wpdb;
    
    $results = [
        'optimized' => 0,
        'skipped' => 0,
        'total_found' => 0,
        'total_size_reduced' => 0,
        'details' => []
    ];
    
    // Define thresholds based on site size
    $site_size_factor = $this->get_site_size_factor();
    $size_threshold = 1024 * (10 * $site_size_factor); // 10KB * factor
    $total_autoload_size = 0;
    
    // First, analyze autoloaded options
    $autoload_analysis = $wpdb->get_results("
        SELECT 
            option_name,
            LENGTH(option_value) as option_size,
            option_value
        FROM {$wpdb->options} 
        WHERE autoload = 'yes'
        ORDER BY LENGTH(option_value) DESC
    ", ARRAY_A);
    
    $results['total_found'] = count($autoload_analysis);
    
    // Calculate total autoload size
    foreach ($autoload_analysis as $option) {
        $total_autoload_size += $option['option_size'];
    }
    
    // Identify candidates for optimization
    foreach ($autoload_analysis as $option) {
        $option_name = $option['option_name'];
        $option_size = $option['option_size'];
        
        // Skip essential WordPress options
        if ($this->is_essential_autoload_option($option_name)) {
            $results['skipped']++;
            $results['details'][] = [
                'option' => $option_name,
                'size' => size_format($option_size, 2),
                'status' => 'skipped',
                'reason' => 'Essential WordPress option'
            ];
            continue;
        }
        
        // Apply optimization criteria
        $should_optimize = $this->should_optimize_autoload_option($option_name, $option_size, $size_threshold, $total_autoload_size);
        
        if ($should_optimize) {
            $update_result = $wpdb->update(
                $wpdb->options,
                array('autoload' => 'no'),
                array('option_name' => $option_name)
            );
            
            if ($update_result !== false) {
                $results['optimized']++;
                $results['total_size_reduced'] += $option_size;
                $results['details'][] = [
                    'option' => $option_name,
                    'size' => size_format($option_size, 2),
                    'status' => 'optimized'
                ];
            }
        } else {
            $results['skipped']++;
        }
    }
    
    // Clear stats cache after optimization
    delete_transient('wp_opt_state_stats_cache');
    
    wp_send_json_success($results);
}

/**
 * Determine if an autoload option is essential
 */
private function is_essential_autoload_option($option_name) {
    $essential_options = [
        'active_plugins',
        'template',
        'stylesheet',
        'current_theme',
        'theme_mods_',
        'widget_',
        'sidebars_widgets',
        'cron',
        'rewrite_rules',
        'wp_user_roles',
        'blogname',
        'blogdescription',
        'siteurl',
        'home',
        'admin_email',
        'WPLANG'
    ];
    
    foreach ($essential_options as $essential) {
        if (strpos($option_name, $essential) === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Determine if an autoload option should be optimized
 */
private function should_optimize_autoload_option($option_name, $option_size, $size_threshold, $total_autoload_size) {
    // Always optimize very large options (> 100KB)
    if ($option_size > (1024 * 100)) {
        return true;
    }
    
    // Optimize options larger than threshold
    if ($option_size > $size_threshold) {
        return true;
    }
    
    // Optimize transient-related options that are large
    if (strpos($option_name, '_transient_') !== false && $option_size > (1024 * 5)) {
        return true;
    }
    
    // Optimize if this option represents a significant portion of total autoload size
    if ($total_autoload_size > 0 && ($option_size / $total_autoload_size) > 0.05) {
        return true; // More than 5% of total autoload size
    }
    
    return false;
}

/**
 * Get site size factor for dynamic thresholds
 */
private function get_site_size_factor() {
    global $wpdb;
    
    $total_size = $wpdb->get_var("
        SELECT SUM(DATA_LENGTH + INDEX_LENGTH) 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE()
    ");
    
    if ($total_size > 100 * 1024 * 1024) { // > 100MB
        return 2; // Larger threshold for big sites
    } elseif ($total_size > 50 * 1024 * 1024) { // > 50MB
        return 1.5;
    }
    
    return 1; // Default for small sites
}
    
    /**
     * AJAX handler for getting DB size
     */
    public function ajax_get_db_size() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
        }
        
        global $wpdb;
        
        $size = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(data_length + index_length) 
             FROM information_schema.TABLES 
             WHERE table_schema = %s",
            DB_NAME
        ));
        
        wp_send_json_success(array('size' => size_format($size, 2)));
    }
    
/**
 * Handle settings update to reschedule cron
 */
public function handle_settings_update($old_value, $new_value, $option) {
    wp_clear_scheduled_hook('wp_opt_state_scheduled_cleanup');
    
    $days = isset($new_value['auto_optimize_days']) ? intval($new_value['auto_optimize_days']) : 0;
    
    if ($days > 0) {
        $next_run = time() + (1 * DAY_IN_SECONDS);
        
        // Determine recurrence
        if ($days == 1) {
            $recurrence = 'daily';
        } elseif ($days == 7) {
            $recurrence = 'weekly';
        } else {
            $recurrence = "every_{$days}_days";
            // Make sure the custom interval is registered
            add_filter('cron_schedules', array($this, 'add_custom_cron_interval'));
        }

        $scheduled = wp_schedule_event($next_run, $recurrence, 'wp_opt_state_scheduled_cleanup');
    }

    delete_transient('wp_opt_state_stats_cache');
}
    
/**
 * Add custom cron intervals
 */
public function add_custom_cron_interval($schedules) {
    $options = get_option($this->option_name, array());
    $days = isset($options['auto_optimize_days']) ? intval($options['auto_optimize_days']) : 0;
    
    if ($days > 1 && $days != 7) {
        $schedules["every_{$days}_days"] = array(
            'interval' => $days * DAY_IN_SECONDS,
            'display' => sprintf(__('Every %d Days', 'wp-optimal-state'), $days)
        );
    }
    
    return $schedules;
}

/**
 * Check and reschedule cron if needed (runs on init)
 */
public function maybe_reschedule_cron() {
    // Only run in admin and for privileged users
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    
    $options = get_option($this->option_name, array());
    $days = isset($options['auto_optimize_days']) ? intval($options['auto_optimize_days']) : 0;
    
    // Check if we have a valid schedule but no cron job
    if ($days > 0) {
        $next_scheduled = wp_next_scheduled('wp_opt_state_scheduled_cleanup');
        if (!$next_scheduled) {
            // Reschedule the event
            $this->handle_settings_update(null, $options, $this->option_name);
            error_log("WP Optimal State: Rescheduled missing cron job");
        }
    }
}
    
/**
 * Run scheduled cleanup (backup + optimize)
 */
public function run_scheduled_cleanup() {
    $this->set_optimization_limits();
    $this->db_backup_manager->create_backup_silent();
    $this->perform_optimizations();
    $this->log_optimization('scheduled', 'One-Click Optimization + Database Backup');
    
    // Clear stats cache after scheduled cleanup
    delete_transient('wp_opt_state_stats_cache');
}
    
    // --- Cleanup Methods (unchanged) ---
    
    private function clean_post_revisions() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
    }
    
    private function clean_auto_drafts() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
    }
    
    private function clean_trashed_posts() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");
    }
    
    private function clean_spam_comments() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
    }
    
    private function clean_trashed_comments() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
    }
    
    private function clean_orphaned_postmeta() {
        global $wpdb;
        return $wpdb->query("DELETE pm FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE p.ID IS NULL");
    }
    
    private function clean_orphaned_commentmeta() {
        global $wpdb;
        return $wpdb->query("DELETE cm FROM {$wpdb->commentmeta} cm LEFT JOIN {$wpdb->comments} c ON cm.comment_id = c.comment_ID WHERE c.comment_ID IS NULL");
    }
    
    private function clean_orphaned_relationships() {
        global $wpdb;
        return $wpdb->query("DELETE tr FROM {$wpdb->term_relationships} tr LEFT JOIN {$wpdb->posts} p ON tr.object_id = p.ID WHERE p.ID IS NULL");
    }
    
    private function clean_expired_transients() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
    }
    
    private function clean_all_transients() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
    }
    
    private function clean_duplicate_postmeta() {
        global $wpdb;
        $duplicates = $wpdb->get_results("SELECT meta_key, post_id, COUNT(*) as count FROM {$wpdb->postmeta} GROUP BY meta_key, post_id, meta_value HAVING count > 1");
        $cleaned = 0;
        
        foreach ($duplicates as $dup) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s AND post_id = %d LIMIT %d",
                $dup->meta_key, $dup->post_id, $dup->count - 1
            ));
            $cleaned += $dup->count - 1;
        }
        
        return $cleaned;
    }
    
    private function clean_duplicate_commentmeta() {
        global $wpdb;
        $duplicates = $wpdb->get_results("SELECT meta_key, comment_id, COUNT(*) as count FROM {$wpdb->commentmeta} GROUP BY meta_key, comment_id, meta_value HAVING count > 1");
        $cleaned = 0;
        
        foreach ($duplicates as $dup) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->commentmeta} WHERE meta_key = %s AND comment_id = %d LIMIT %d",
                $dup->meta_key, $dup->comment_id, $dup->count - 1
            ));
            $cleaned += $dup->count - 1;
        }
        
        return $cleaned;
    }
    
    private function clean_orphaned_usermeta() {
        global $wpdb;
        return $wpdb->query("DELETE um FROM {$wpdb->usermeta} um LEFT JOIN {$wpdb->users} u ON um.user_id = u.ID WHERE u.ID IS NULL");
    }
    
    private function clean_unapproved_comments() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = '0'");
    }
    
    private function clean_pingbacks() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_type = 'pingback'");
    }
    
    private function clean_trackbacks() {
        global $wpdb;
        return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_type = 'trackback'");
    }
    
    private function count_duplicate_postmeta() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM (SELECT COUNT(*) as count FROM {$wpdb->postmeta} GROUP BY meta_key, post_id, meta_value HAVING count > 1) as dup");
    }
    
    private function count_duplicate_commentmeta() {
        global $wpdb;
        return $wpdb->get_var("SELECT COUNT(*) FROM (SELECT COUNT(*) as count FROM {$wpdb->commentmeta} GROUP BY meta_key, comment_id, meta_value HAVING count > 1) as dup");
    }
}

/**
 * Database Backup Manager Class
 */
class DB_Backup_Manager {
    
    private $backup_dir;
    private $max_backups;
    private $log_file_path;
    
    public function __construct($log_file_path = '') {
        $this->backup_dir = WP_CONTENT_DIR . '/uploads/db-backups/';
        $this->log_file_path = $log_file_path;
        
        // Get max backups from settings, default to 3
        $options = get_option('wp_opt_state_settings', array());
        $this->max_backups = isset($options['max_backups']) ? intval($options['max_backups']) : 3;
        
        add_action('wp_ajax_create_backup', array($this, 'ajax_create_backup'));
        add_action('wp_ajax_delete_backup', array($this, 'ajax_delete_backup'));
        add_action('wp_ajax_restore_backup', array($this, 'ajax_restore_backup'));
        add_action('init', array($this, 'handle_download_backup'));
        add_action('init', array($this, 'protect_backup_directory'));
    }
    
    /**
     * Protect backup directory with .htaccess
     */
    public function protect_backup_directory() {
        if (!is_dir($this->backup_dir)) {
            return;
        }
        
        $htaccess_file = $this->backup_dir . '.htaccess';
        if (!file_exists($htaccess_file)) {
            @file_put_contents($htaccess_file, "Order deny,allow\nDeny from all\n<Files ~ \"\\.sql$\">\nAllow from all\n</Files>");
        }
        
        // Add index.php to prevent directory listing
        $index_file = $this->backup_dir . 'index.php';
        if (!file_exists($index_file)) {
            @file_put_contents($index_file, "<?php\n// Silence is golden");
        }
    }
    
/**
 * Check rate limiting for backup operations
 */
private function check_rate_limit($action) {
    $transient_name = 'wp_opt_state_rate_limit_' . $action;
    $last_called = get_transient($transient_name);
    if ($last_called !== false) {
        if (time() - $last_called < 30) {
            return false;
        }
    }
    
    set_transient($transient_name, time(), 30);
    return true;
}
    
    /**
     * Set backup operation limits
     */
    private function set_backup_limits() {
        @set_time_limit(600); // 10 minutes
        @ini_set('memory_limit', '512M');
    }
    
    public function get_backups() {
        $backups = glob($this->backup_dir . '*.sql');
        $backup_list = array();
        
        foreach ($backups as $file) {
            $filename = basename($file);
            $download_url = add_query_arg(array(
                'action' => 'db_backup_download',
                'file' => $filename,
                '_wpnonce' => wp_create_nonce('db_backup_download_nonce')
            ), admin_url());
            
            $backup_list[] = array(
                'filename' => $filename,
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'size' => size_format(filesize($file), 2),
                'filepath' => $file,
                'download_url' => $download_url
            );
        }
        
        usort($backup_list, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backup_list;
    }
    
    public function ajax_create_backup() {
        check_ajax_referer('db_backup_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }
        
        // Check rate limiting
if (!$this->check_rate_limit('create_backup')) {
    wp_send_json_error(array('message' => 'Please wait 30 seconds before performing a new backup.'));
    return;
}
        
        $this->set_backup_limits();
        
        if (!is_dir($this->backup_dir)) {
            if (!@mkdir($this->backup_dir, 0755, true)) {
                wp_send_json_error(array('message' => 'Failed to create backup directory. Please check permissions.'));
            }
        }
        
        $filename = 'db-backup-' . date('Y-m-d-H-i-s') . '.sql';
        $filepath = $this->backup_dir . $filename;
        
        global $wpdb;
        
        $handle = @fopen($filepath, 'w');
        if (!$handle) {
            wp_send_json_error(array('message' => 'Failed to create backup file. Please check directory permissions.'));
        }
        
        try {
            // Write SQL header (phpMyAdmin style)
            fwrite($handle, "-- WordPress Database Backup\n");
            fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Database: " . DB_NAME . "\n");
            fwrite($handle, "-- PHP Version: " . PHP_VERSION . "\n");
            fwrite($handle, "-- WordPress Version: " . get_bloginfo('version') . "\n");
            fwrite($handle, "-- ------------------------------------------------------\n\n");
            
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n");
            fwrite($handle, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
            fwrite($handle, "/*!40103 SET TIME_ZONE='+00:00' */;\n");
            fwrite($handle, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
            fwrite($handle, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
            fwrite($handle, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
            fwrite($handle, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
            
            // Get all tables
            $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
            
            if (empty($tables)) {
                fclose($handle);
                @unlink($filepath);
                wp_send_json_error(array('message' => 'No database tables found.'));
            }
            
            // Disable foreign key checks during backup creation
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                
                fwrite($handle, "-- ------------------------------------------------------\n");
                fwrite($handle, "-- Table structure for `{$table_name}`\n");
                fwrite($handle, "-- ------------------------------------------------------\n\n");
                
                // Drop table if exists
                fwrite($handle, "DROP TABLE IF EXISTS `{$table_name}`;\n");
                
                // Get table creation SQL
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
                if ($create_table && isset($create_table[1])) {
                    fwrite($handle, $create_table[1] . ";\n\n");
                } else {
                    throw new Exception("Failed to get structure for table: {$table_name}");
                }
                
                // Get table data using optimized batch processing
                $this->backup_table_data($handle, $table_name);
                
                fwrite($handle, "-- ------------------------------------------------------\n\n");
            }
            
            // Re-enable foreign key checks
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n\n");
            
            // Write SQL footer
            fwrite($handle, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
            fwrite($handle, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
            fwrite($handle, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
            fwrite($handle, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
            
            fclose($handle);
            
            // Verify backup file
            if (!file_exists($filepath) || filesize($filepath) < 100) {
                @unlink($filepath);
                wp_send_json_error(array('message' => 'Backup file is invalid or empty.'));
            }
            
            $this->enforce_backup_limit();
            
            $backups = $this->get_backups();
            
            wp_send_json_success(array(
                'message' => 'Backup created successfully!',
                'backups' => $backups
            ));
            
        } catch (Exception $e) {
            if ($handle) {
                fclose($handle);
            }
            @unlink($filepath);
            wp_send_json_error(array('message' => 'Backup failed: ' . $e->getMessage()));
        }
    }
    
    /**
     * Backup table data with memory-efficient batch processing
     */
    private function backup_table_data($handle, $table_name) {
        global $wpdb;
        
        $row_count = $wpdb->get_var("SELECT COUNT(*) FROM `{$table_name}`");
        
        if ($row_count > 0) {
            fwrite($handle, "--\n");
            fwrite($handle, "-- Dumping data for table `{$table_name}`\n");
            fwrite($handle, "--\n\n");
            
            // Use smaller batch size for memory efficiency
            $batch_size = 50;
            $offset = 0;
            
            // Get column information
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `{$table_name}`", ARRAY_A);
            $column_names = array();
            foreach ($columns as $column) {
                $column_names[] = $column['Field'];
            }
            
            while ($offset < $row_count) {
                $rows = $wpdb->get_results(
                    $wpdb->prepare("SELECT * FROM `{$table_name}` LIMIT %d OFFSET %d", $batch_size, $offset),
                    ARRAY_A
                );
                
                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $values = array();
                        
                        foreach ($column_names as $column_name) {
                            if (!isset($row[$column_name])) {
                                $values[] = 'NULL';
                            } else {
                                $value = $row[$column_name];
                                
                                // Handle different data types properly
                                if (is_null($value)) {
                                    $values[] = 'NULL';
                                } elseif (is_numeric($value) && !is_string($value)) {
                                    $values[] = $value;
                                } else {
                                    // Proper SQL escaping (phpMyAdmin style)
                                    $escaped_value = str_replace(
                                        array('\\', "\0", "\n", "\r", "'", '"', "\x1a"),
                                        array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'),
                                        $value
                                    );
                                    $values[] = "'" . $escaped_value . "'";
                                }
                            }
                        }
                        
                        fwrite($handle, "INSERT INTO `{$table_name}` (`" . implode('`, `', $column_names) . "`) VALUES (" . implode(', ', $values) . ");\n");
                    }
                }
                
                // Free memory
                unset($rows);
                
                $offset += $batch_size;
            }
            
            fwrite($handle, "\n");
        }
    }
    
    /**
     * Silent backup creation for scheduled tasks
     */
    public function create_backup_silent() {
        if (!is_dir($this->backup_dir)) {
            if (!@mkdir($this->backup_dir, 0755, true)) {
                return false;
            }
        }
        
        $filename = 'db-backup-' . date('Y-m-d-H-i-s') . '.sql';
        $filepath = $this->backup_dir . $filename;
        
        global $wpdb;
        
        $handle = @fopen($filepath, 'w');
        if (!$handle) {
            return false;
        }
        
        try {
            // Write SQL header (phpMyAdmin style)
            fwrite($handle, "-- WordPress Database Backup\n");
            fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- Database: " . DB_NAME . "\n");
            fwrite($handle, "-- PHP Version: " . PHP_VERSION . "\n");
            fwrite($handle, "-- WordPress Version: " . get_bloginfo('version') . "\n");
            fwrite($handle, "-- ------------------------------------------------------\n\n");
            
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40101 SET NAMES utf8mb4 */;\n");
            fwrite($handle, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
            fwrite($handle, "/*!40103 SET TIME_ZONE='+00:00' */;\n");
            fwrite($handle, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
            fwrite($handle, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
            fwrite($handle, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
            fwrite($handle, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
            
            // Get all tables
            $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
            
            if (empty($tables)) {
                fclose($handle);
                @unlink($filepath);
                return false;
            }
            
            // Disable foreign key checks during backup creation
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
            
            foreach ($tables as $table) {
                $table_name = $table[0];
                
                fwrite($handle, "-- ------------------------------------------------------\n");
                fwrite($handle, "-- Table structure for `{$table_name}`\n");
                fwrite($handle, "-- ------------------------------------------------------\n\n");
                
                // Drop table if exists
                fwrite($handle, "DROP TABLE IF EXISTS `{$table_name}`;\n");
                
                // Get table creation SQL
                $create_table = $wpdb->get_row("SHOW CREATE TABLE `{$table_name}`", ARRAY_N);
                if ($create_table && isset($create_table[1])) {
                    fwrite($handle, $create_table[1] . ";\n\n");
                } else {
                    throw new Exception("Failed to get structure for table: {$table_name}");
                }
                
                // Get table data using optimized batch processing
                $this->backup_table_data($handle, $table_name);
                
                fwrite($handle, "-- ------------------------------------------------------\n\n");
            }
            
            // Re-enable foreign key checks
            fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n\n");
            
            // Write SQL footer
            fwrite($handle, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
            fwrite($handle, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
            fwrite($handle, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
            fwrite($handle, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
            fwrite($handle, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
            fwrite($handle, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
            fwrite($handle, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
            
            fclose($handle);
            
            // Verify backup file
            if (!file_exists($filepath) || filesize($filepath) < 100) {
                @unlink($filepath);
                return false;
            }
            
            $this->enforce_backup_limit();
            
            return true;
            
        } catch (Exception $e) {
            if ($handle) {
                fclose($handle);
            }
            @unlink($filepath);
            return false;
        }
    }
    
    private function enforce_backup_limit() {
        $backups = $this->get_backups();
        
        if (count($backups) > $this->max_backups) {
            $to_delete = array_slice($backups, $this->max_backups);
            
            foreach ($to_delete as $backup) {
                if (file_exists($backup['filepath'])) {
                    unlink($backup['filepath']);
                }
            }
        }
    }
    
    public function ajax_delete_backup() {
        check_ajax_referer('db_backup_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
        }
        
        $filename = sanitize_file_name($_POST['filename']);
        
        // SECURITY FIX: Prevent directory traversal
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            wp_send_json_error(array('message' => 'Invalid filename.'));
        }
        
        $filepath = $this->backup_dir . $filename;
        
        // SECURITY FIX: Ensure the file is within the backup directory
        $real_filepath = realpath($filepath);
        $real_backup_dir = realpath($this->backup_dir);
        
        if ($real_filepath === false || strpos($real_filepath, $real_backup_dir) !== 0) {
            wp_send_json_error(array('message' => 'Invalid file path.'));
        }
        
        if (!file_exists($filepath)) {
            wp_send_json_error(array('message' => 'Backup file not found.'));
        }
        
        if (unlink($filepath)) {
            wp_send_json_success(array('message' => 'Backup deleted successfully!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to delete backup.'));
        }
    }
    
public function ajax_restore_backup() {
    check_ajax_referer('db_backup_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Insufficient permissions.'));
    }
    
    // Check rate limiting
    if (!$this->check_rate_limit('restore_backup')) {
        wp_send_json_error(array('message' => 'Please wait 30 seconds before restoring another backup.'));
        return;
    }
    
    $this->set_backup_limits();
    
    $filename = sanitize_file_name($_POST['filename']);
    
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        wp_send_json_error(array('message' => 'Invalid filename.'));
    }
    
    $filepath = $this->backup_dir . $filename;
    
    $real_filepath = realpath($filepath);
    $real_backup_dir = realpath($this->backup_dir);
    
    if ($real_filepath === false || strpos($real_filepath, $real_backup_dir) !== 0) {
        wp_send_json_error(array('message' => 'Invalid file path.'));
    }
    
    if (!file_exists($filepath)) {
        wp_send_json_error(array('message' => 'Backup file not found.'));
    }
    
    global $wpdb;

    try {
        // Open the backup file for reading instead of loading it all into memory
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            wp_send_json_error(array('message' => 'Failed to open backup file for reading.'));
        }

        // Disable foreign key checks and start a transaction
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        $wpdb->query('SET AUTOCOMMIT = 0');
        $wpdb->query('START TRANSACTION');
        
        $query_buffer = ''; // This will hold the current query as we build it from lines
        $executed_queries = 0;
        $errors = array();

        // Read the file line-by-line
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            // Skip empty lines and SQL comments
            if (empty($line) || strpos($line, '--') === 0 || strpos($line, '/*') === 0 || strpos($line, '#') === 0) {
                continue;
            }
             
            // Append the current line to the buffer
            $query_buffer .= $line;

            // If the line ends with a semicolon, we have a complete query
            if (substr($line, -1) === ';') {
                $result = $wpdb->query($query_buffer);

                if ($result === false) {
                    $error_msg = $wpdb->last_error;
                    if (!(preg_match('/^DROP TABLE/i', $query_buffer) && strpos($error_msg, "doesn't exist") !== false)) {
                         $errors[] = array(
                            'query' => substr($query_buffer, 0, 150) . '...',
                            'error' => $error_msg
                        );
                    }
                } else {
                    $executed_queries++;
                }

                // Reset the buffer for the next query
                $query_buffer = '';
            }
        }
        
        fclose($handle); // Close the file handle

        // Commit changes and re-enable checks
        $wpdb->query('COMMIT');
        $wpdb->query('SET AUTOCOMMIT = 1');
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        // Log the restore operation
        $this->log_restore_operation($filename, $executed_queries);
        
        if (!empty($errors)) {
            wp_send_json_error(array(
                'message' => 'Database restore completed with some errors.',
                'executed' => $executed_queries,
                'total_errors' => count($errors),
                'first_error' => $errors[0]['error']
            ));
        }
        
        if ($executed_queries === 0) {
            wp_send_json_error(array('message' => 'No queries were executed. The backup file might be empty or corrupted.'));
        }
        
        delete_transient('wp_opt_state_stats_cache');
        
        wp_send_json_success(array(
            'message' => 'Database restored successfully! ' . $executed_queries . ' queries executed.',
            'executed' => $executed_queries
        ));
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        $wpdb->query('SET AUTOCOMMIT = 1');
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        wp_send_json_error(array('message' => 'Restore failed: ' . $e->getMessage()));
    }
}

/**
 * Log database restore operation
 */
private function log_restore_operation($backup_filename, $queries_executed) {
    if (empty($this->log_file_path) || !file_exists($this->log_file_path)) {
        return;
    }
    
    $log_entries = array();
    $json_data = @file_get_contents($this->log_file_path);
    if ($json_data !== false) {
        $log_entries = json_decode($json_data, true);
        if (!is_array($log_entries)) {
            $log_entries = array();
        }
    }
    
    $log_entry = array(
        'timestamp' => current_time('timestamp'),
        'type' => 'manual',
        'date' => current_time('Y-m-d H:i:s'),
        'operation' => 'Database Backup Restored',
        'backup_filename' => $backup_filename,
        'queries_executed' => $queries_executed
    );
    
    // Keep only last 20 entries
    array_unshift($log_entries, $log_entry);
    $log_entries = array_slice($log_entries, 0, 20);
    
    // Save to file
    $json_data = json_encode($log_entries, JSON_PRETTY_PRINT);
    if ($json_data !== false) {
        @file_put_contents($this->log_file_path, $json_data);
    }
}

/**
 * Split SQL queries properly while handling semicolons within strings
 */
private function split_sql_queries($sql) {
    $queries = array();
    $current_query = '';
    $in_string = false;
    $string_char = '';
    $escaped = false;
    $in_comment = false;
    $comment_type = ''; // --, #, or /*
    
    for ($i = 0; $i < strlen($sql); $i++) {
        $char = $sql[$i];
        $next_char = isset($sql[$i + 1]) ? $sql[$i + 1] : '';
        
        // Handle comments
        if (!$in_string && !$escaped) {
            // Start of single-line comment
            if (!$in_comment && (($char == '-' && $next_char == '-') || $char == '#')) {
                $in_comment = true;
                $comment_type = ($char == '#') ? '#' : '--';
                $i += ($comment_type == '--') ? 1 : 0;
                continue;
            }
            // Start of multi-line comment
            elseif (!$in_comment && $char == '/' && $next_char == '*') {
                $in_comment = true;
                $comment_type = '/*';
                $i += 1;
                continue;
            }
            // End of multi-line comment
            elseif ($in_comment && $comment_type == '/*' && $char == '*' && $next_char == '/') {
                $in_comment = false;
                $comment_type = '';
                $i += 1;
                continue;
            }
            // End of single-line comment
            elseif ($in_comment && ($comment_type == '--' || $comment_type == '#') && ($char == "\n" || $char == "\r")) {
                $in_comment = false;
                $comment_type = '';
            }
        }
        
        // If we're in a comment, skip this character
        if ($in_comment) {
            continue;
        }
        
        // Handle string escaping
        if (!$escaped && $char == "\\") {
            $escaped = true;
            $current_query .= $char;
            continue;
        }
        
        // Handle string boundaries
        if (!$escaped && ($char == "'" || $char == '"')) {
            if (!$in_string) {
                $in_string = true;
                $string_char = $char;
            } elseif ($in_string && $char == $string_char) {
                $in_string = false;
                $string_char = '';
            }
        }
        
        // Reset escape flag
        if ($escaped) {
            $escaped = false;
        }
        
        // Handle query termination
        if (!$in_string && $char == ';') {
            $current_query = trim($current_query);
            if (!empty($current_query)) {
                $queries[] = $current_query;
            }
            $current_query = '';
            continue;
        }
        
        $current_query .= $char;
    }
    
    // Add the last query if any
    $current_query = trim($current_query);
    if (!empty($current_query)) {
        $queries[] = $current_query;
    }
    
    return $queries;
}
    
    public function handle_download_backup() {
        if (isset($_GET['action']) && $_GET['action'] === 'db_backup_download' && isset($_GET['file'])) {
            
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'db_backup_download_nonce')) {
                wp_die('Security check failed.');
            }

            if (!current_user_can('manage_options')) {
                wp_die('You do not have sufficient permissions to download this file.');
            }
            
            $filename = sanitize_file_name($_GET['file']);
            
            // SECURITY FIX: Prevent directory traversal
            if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
                wp_die('Invalid filename.');
            }
            
            $filepath = $this->backup_dir . $filename;
            
            // SECURITY FIX: Ensure the file is within the backup directory
            $real_filepath = realpath($filepath);
            $real_backup_dir = realpath($this->backup_dir);
            
            if ($real_filepath === false || strpos($real_filepath, $real_backup_dir) !== 0) {
                wp_die('Invalid file path.');
            }
            
            if (file_exists($filepath)) {
                // SECURITY FIX: Add security headers
                header('Content-Description: File Transfer');
                header('Content-Type: application/sql');
                header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                header('X-Content-Type-Options: nosniff');
                header('X-Frame-Options: DENY');
                header('X-XSS-Protection: 1; mode=block');
                flush();
                readfile($filepath);
                exit;
            } else {
                wp_die('File not found.');
            }
        }
    }
}


// --- Global Functions and Hooks ---

// Initialize the main plugin class
new WP_Optimal_State();

// Activation and deactivation hooks
register_activation_hook(__FILE__, 'wp_opt_state_activate');
function wp_opt_state_activate() {
    $default_settings = array(
        'schedule' => 'disabled',
        'keep_revisions' => 5,
        'auto_optimize_days' => 0,
        'optimize_images' => 0
    );
    add_option('wp_opt_state_settings', $default_settings);
    add_option('wp_opt_state_backup_reminder', 1);
    add_option('wp_opt_state_activation_time', time());
    delete_option('wp_opt_state_optimization_log');
}

register_deactivation_hook(__FILE__, 'wp_opt_state_deactivate');
function wp_opt_state_deactivate() {
    wp_clear_scheduled_hook('wp_opt_state_scheduled_cleanup');
}

// Settings registration
add_action('admin_init', 'wp_opt_state_register_settings');
function wp_opt_state_register_settings() {
    register_setting(
        'wp_opt_state_settings_group',
        'wp_opt_state_settings',
        array(
            'sanitize_callback' => 'wp_opt_state_sanitize_settings',
            'default' => array('auto_optimize_days' => 0)
        )
    );
}

function wp_opt_state_sanitize_settings($input) {
    $sanitized = array();
    
    if (isset($input['auto_optimize_days'])) {
        $sanitized['auto_optimize_days'] = absint($input['auto_optimize_days']);
        $sanitized['auto_optimize_days'] = min(max($sanitized['auto_optimize_days'], 0), 365);
    }
    
    if (isset($input['schedule'])) {
        $allowed_schedules = array('disabled', 'daily', 'weekly');
        $sanitized['schedule'] = in_array($input['schedule'], $allowed_schedules, true) ? $input['schedule'] : 'disabled';
    }
    
    if (isset($input['max_backups'])) {
        $sanitized['max_backups'] = absint($input['max_backups']);
        $sanitized['max_backups'] = min(max($sanitized['max_backups'], 1), 10);
    }
    
    return $sanitized;
}