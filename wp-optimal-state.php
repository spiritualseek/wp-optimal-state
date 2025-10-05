<?php
/**
 * Plugin Name: WP Optimal State
 * Plugin URI: https://spiritualseek.com/wp-optimal-state-wordpress-plugin/
 * Description: Advanced WordPress optimization and cleaning plugin. It cleans your database, optimizes your tables, removes old and unused data, and keeps your site lightning fast.
 * Version: 1.0.3
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
    private $version = '1.0.3';
    private $option_name = 'wp_opt_state_settings';
    private $nonce_action = 'wp_opt_state_nonce';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        $this->register_ajax_handlers();
    }
    
    /**
     * Load plugin text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain('wp-optimal-state', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Register all AJAX handlers
     */
    private function register_ajax_handlers() {
        $handlers = array(
            'get_stats', 'clean_item', 'optimize_tables', 
            'one_click_optimize', 'get_db_size', 'clean_old_revisions', 
            'optimize_autoload', 'analyze_repair_tables'
        );
        
        foreach ($handlers as $handler) {
            add_action('wp_ajax_wp_opt_state_' . $handler, array($this, 'ajax_' . $handler));
        }
        
        add_action('wp_opt_state_scheduled_cleanup', array($this, 'run_scheduled_cleanup'));
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
     * Enqueue admin styles and scripts
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'toplevel_page_wp-optimal-state') {
            return;
        }
        
        wp_enqueue_style(
            'wp-opt-state-admin-styles',
            plugin_dir_url(__FILE__) . 'css/admin-styles.css',
            array(),
            $this->version
        );
        
        wp_enqueue_script('jquery');
        add_action('admin_footer', array($this, 'output_inline_scripts'));
    }
    
    /**
     * Output inline JavaScript for AJAX functionality
     */
    public function output_inline_scripts() {
        $labels = array(
            'post_revisions' => __('Post Revisions', 'wp-optimal-state'),
            'auto_drafts' => __('Auto Drafts', 'wp-optimal-state'),
            'trashed_posts' => __('Trashed Posts', 'wp-optimal-state'),
            'spam_comments' => __('Spam Comments', 'wp-optimal-state'),
            'trashed_comments' => __('Trashed Comments', 'wp-optimal-state'),
            'orphaned_postmeta' => __('Orphaned Post Meta', 'wp-optimal-state'),
            'orphaned_commentmeta' => __('Orphaned Comment Meta', 'wp-optimal-state'),
            'expired_transients' => __('Expired Transients', 'wp-optimal-state'),
            'all_transients' => __('All Transients', 'wp-optimal-state'),
            'autoload_options' => __('Autoloaded Options', 'wp-optimal-state'),
            'autoload_size' => __('Autoload Size', 'wp-optimal-state')
        );
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            'use strict';
            
            var wpOptStateAjax = {
                ajaxurl: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                nonce: <?php echo wp_json_encode(wp_create_nonce($this->nonce_action)); ?>,
                isProcessing: false
            };
            
            var labels = <?php echo wp_json_encode($labels); ?>;
            
            function showModal(title, message, onConfirm, isDanger) {
                var $overlay = $('<div class="wp-opt-state-modal-overlay"></div>');
                var dangerClass = isDanger ? ' wp-opt-state-modal-danger' : '';
                var $modal = $('<div class="wp-opt-state-modal' + dangerClass + '">' +
                              '<div class="wp-opt-state-modal-header">' +
                              '<h3>' + title + '</h3>' +
                              '<button class="wp-opt-state-modal-close">&times;</button>' +
                              '</div>' +
                              '<div class="wp-opt-state-modal-body">' + message + '</div>' +
                              '<div class="wp-opt-state-modal-footer">' +
                              '<button class="button wp-opt-state-modal-cancel"><?php echo esc_js(__('Cancel', 'wp-optimal-state')); ?></button>' +
                              '<button class="button button-primary wp-opt-state-modal-confirm"><?php echo esc_js(__('Confirm', 'wp-optimal-state')); ?></button>' +
                              '</div>' +
                              '</div>');
                
                $('body').append($overlay).append($modal);
                
                setTimeout(function() {
                    $overlay.addClass('show');
                    $modal.addClass('show');
                }, 10);
                
                function closeModal() {
                    $overlay.removeClass('show');
                    $modal.removeClass('show');
                    setTimeout(function() {
                        $overlay.remove();
                        $modal.remove();
                    }, 300);
                }
                
                $modal.find('.wp-opt-state-modal-close, .wp-opt-state-modal-cancel').on('click', closeModal);
                $overlay.on('click', closeModal);
                
                $modal.find('.wp-opt-state-modal-confirm').on('click', function() {
                    closeModal();
                    if (onConfirm) onConfirm();
                });
                
                $modal.on('click', function(e) {
                    e.stopPropagation();
                });
            }
            
            function showToast(message, type) {
                type = type || 'success';
                var $toast = $('<div class="wp-opt-state-toast wp-opt-state-toast-' + type + '">' + 
                              '<span class="wp-opt-state-toast-icon"></span>' + message + '</div>');
                $('body').append($toast);
                
                setTimeout(function() { $toast.addClass('show'); }, 100);
                setTimeout(function() {
                    $toast.removeClass('show');
                    setTimeout(function() { $toast.remove(); }, 300);
                }, 3000);
            }
            
            function handleAjaxError(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                showToast(<?php echo wp_json_encode(__('An error occurred. Please try again.', 'wp-optimal-state')); ?>, 'error');
                wpOptStateAjax.isProcessing = false;
            }
            
            function loadStats() {
                $('#wp-opt-state-stats-loading').fadeIn(200);
                $('#wp-opt-state-stats').empty();
                
                $.post(wpOptStateAjax.ajaxurl, {
                    action: 'wp_opt_state_get_stats',
                    nonce: wpOptStateAjax.nonce
                })
                .done(function(response) {
                    $('#wp-opt-state-stats-loading').fadeOut(200);
                    if (response.success && response.data) {
                        displayStats(response.data);
                        displayCleanupItems(response.data);
                    } else {
                        showToast(<?php echo wp_json_encode(__('Failed to load statistics', 'wp-optimal-state')); ?>, 'error');
                    }
                })
                .fail(handleAjaxError);
                
                $.post(wpOptStateAjax.ajaxurl, {
                    action: 'wp_opt_state_get_db_size',
                    nonce: wpOptStateAjax.nonce
                })
                .done(function(response) {
                    if (response.success && response.data) {
                        $('#wp-opt-state-db-size-value').text(response.data.size);
                    }
                })
                .fail(function() {
                    $('#wp-opt-state-db-size-value').text(<?php echo wp_json_encode(__('Error', 'wp-optimal-state')); ?>);
                });
            }
            
            function displayStats(stats) {
                var html = '';
                for (var key in stats) {
                    if (labels[key]) {
                        html += '<div class="wp-opt-state-stat-item">' +
                                '<div class="wp-opt-state-stat-label">' + labels[key] + '</div>' +
                                '<div class="wp-opt-state-stat-value">' + stats[key] + '</div>' +
                                '</div>';
                    }
                }
                $('#wp-opt-state-stats').html(html).hide().fadeIn(300);
            }
            
            function displayCleanupItems(stats) {
                var items = [
                    {key: 'post_revisions', title: <?php echo wp_json_encode(__('Post Revisions', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Old versions of posts and pages', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'auto_drafts', title: <?php echo wp_json_encode(__('Auto Drafts', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Automatically saved drafts', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'trashed_posts', title: <?php echo wp_json_encode(__('Trashed Posts', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Posts in trash', 'wp-optimal-state')); ?>, safe: false},
                    {key: 'spam_comments', title: <?php echo wp_json_encode(__('Spam Comments', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Comments marked as spam', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'trashed_comments', title: <?php echo wp_json_encode(__('Trashed Comments', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Comments in trash', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'orphaned_postmeta', title: <?php echo wp_json_encode(__('Orphaned Post Meta', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Metadata for deleted posts', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'orphaned_commentmeta', title: <?php echo wp_json_encode(__('Orphaned Comment Meta', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Metadata for deleted comments', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'orphaned_relationships', title: <?php echo wp_json_encode(__('Orphaned Relationships', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Term relationships for deleted posts', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'expired_transients', title: <?php echo wp_json_encode(__('Expired Transients', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Expired temporary options', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'all_transients', title: <?php echo wp_json_encode(__('All Transients', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('All cached temporary data', 'wp-optimal-state')); ?>, safe: false},
                    {key: 'duplicate_postmeta', title: <?php echo wp_json_encode(__('Duplicate Post Meta', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Duplicate metadata entries', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'duplicate_commentmeta', title: <?php echo wp_json_encode(__('Duplicate Comment Meta', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Duplicate comment metadata', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'orphaned_usermeta', title: <?php echo wp_json_encode(__('Orphaned User Meta', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Metadata for deleted users', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'unapproved_comments', title: <?php echo wp_json_encode(__('Unapproved Comments', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Comments awaiting moderation', 'wp-optimal-state')); ?>, safe: false},
                    {key: 'pingbacks', title: <?php echo wp_json_encode(__('Pingbacks', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Pingback notifications', 'wp-optimal-state')); ?>, safe: true},
                    {key: 'trackbacks', title: <?php echo wp_json_encode(__('Trackbacks', 'wp-optimal-state')); ?>, desc: <?php echo wp_json_encode(__('Trackback notifications', 'wp-optimal-state')); ?>, safe: true}
                ];
                
                var html = '';
                items.forEach(function(item) {
                    var count = stats[item.key] || 0;
                    var warningIcon = !item.safe ? '<span class="wp-opt-state-warning-icon" title="' + <?php echo wp_json_encode(__('Review before cleaning', 'wp-optimal-state')); ?> + '">⚠️</span>' : '';
                    var disabled = count == 0 ? ' disabled' : '';
                    var countClass = count > 0 ? 'has-items' : '';
                    
                    html += '<div class="wp-opt-state-cleanup-item ' + countClass + '">' +
                            '<div class="wp-opt-state-cleanup-header">' +
                            '<span class="wp-opt-state-cleanup-title">' + item.title + ' ' + warningIcon + '</span>' +
                            '<span class="wp-opt-state-cleanup-count">' + count + '</span>' +
                            '</div>' +
                            '<div class="wp-opt-state-cleanup-desc">' + item.desc + '</div>' +
                            '<button class="wp-opt-state-clean-btn" data-type="' + item.key + '" data-safe="' + item.safe + '"' + disabled + '>' +
                            <?php echo wp_json_encode(__('Clean Now', 'wp-optimal-state')); ?> +
                            '</button>' +
                            '</div>';
                });
                $('#wp-opt-state-cleanup-items').html(html).hide().fadeIn(300);
            }
            
            $(document).on('click', '.wp-opt-state-clean-btn:not(:disabled)', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                var itemType = btn.data('type');
                var isSafe = btn.data('safe');
                
                var confirmMsg = isSafe ? 
                    <?php echo wp_json_encode(__('Clean this item? This action cannot be undone.', 'wp-optimal-state')); ?> :
                    <?php echo wp_json_encode(__('This operation should be reviewed carefully. Are you sure you want to continue?', 'wp-optimal-state')); ?>;
                
                var title = isSafe ? 
                    <?php echo wp_json_encode(__('Confirm Cleanup', 'wp-optimal-state')); ?> : 
                    <?php echo wp_json_encode(__('⚠️ Warning', 'wp-optimal-state')); ?>;
                
                showModal(title, confirmMsg, function() {
                    wpOptStateAjax.isProcessing = true;
                    btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Cleaning...', 'wp-optimal-state')); ?>);
                    
                    $.post(wpOptStateAjax.ajaxurl, {
                        action: 'wp_opt_state_clean_item',
                        nonce: wpOptStateAjax.nonce,
                        item_type: itemType
                    })
                    .done(function(response) {
                        wpOptStateAjax.isProcessing = false;
                        if (response.success) {
                            btn.removeClass('loading').addClass('success').text(<?php echo wp_json_encode(__('Cleaned ✓', 'wp-optimal-state')); ?>);
                            showToast(<?php echo wp_json_encode(__('Successfully cleaned!', 'wp-optimal-state')); ?>, 'success');
                            setTimeout(function() { loadStats(); }, 1500);
                        } else {
                            btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Error - Try Again', 'wp-optimal-state')); ?>);
                            showToast(response.data || <?php echo wp_json_encode(__('Cleanup failed', 'wp-optimal-state')); ?>, 'error');
                        }
                    })
                    .fail(function() {
                        wpOptStateAjax.isProcessing = false;
                        btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Error - Try Again', 'wp-optimal-state')); ?>);
                        handleAjaxError();
                    });
                }, !isSafe);
            });
            
            $('#wp-opt-state-refresh-stats').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                loadStats();
                showToast(<?php echo wp_json_encode(__('Statistics refreshed', 'wp-optimal-state')); ?>, 'info');
            });
            
            $('#wp-opt-state-optimize-tables').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                wpOptStateAjax.isProcessing = true;
                btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Optimizing...', 'wp-optimal-state')); ?>);
                
                $.post(wpOptStateAjax.ajaxurl, {
                    action: 'wp_opt_state_optimize_tables',
                    nonce: wpOptStateAjax.nonce
                })
                .done(function(response) {
                    wpOptStateAjax.isProcessing = false;
                    if (response.success) {
                        var message = <?php echo wp_json_encode(__('✓ Successfully optimized', 'wp-optimal-state')); ?> + ' ' + response.data.optimized + ' ' + <?php echo wp_json_encode(__('tables!', 'wp-optimal-state')); ?>;
                        $('#wp-opt-state-table-results').addClass('show').html(
                            '<div class="wp-opt-state-success">' + message + '</div>'
                        ).hide().fadeIn(300);
                        showToast(message, 'success');
                    }
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize All Tables', 'wp-optimal-state')); ?>);
                })
                .fail(function() {
                    wpOptStateAjax.isProcessing = false;
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize All Tables', 'wp-optimal-state')); ?>);
                    handleAjaxError();
                });
            });
            
            $('#wp-opt-state-analyze-repair-tables').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                wpOptStateAjax.isProcessing = true;
                btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Analyzing...', 'wp-optimal-state')); ?>);
                
                $.post(wpOptStateAjax.ajaxurl, {
                    action: 'wp_opt_state_analyze_repair_tables',
                    nonce: wpOptStateAjax.nonce
                })
                .done(function(response) {
                    wpOptStateAjax.isProcessing = false;
                    if (response.success) {
                        var message = '';
                        if (response.data.analyzed > 0) {
                            message = <?php echo wp_json_encode(__('✓ Analyzed', 'wp-optimal-state')); ?> + ' ' + response.data.analyzed + ' ' + <?php echo wp_json_encode(__('tables', 'wp-optimal-state')); ?>;
                            if (response.data.repaired > 0) {
                                message += ', ' + <?php echo wp_json_encode(__('repaired', 'wp-optimal-state')); ?> + ' ' + response.data.repaired + ' ' + <?php echo wp_json_encode(__('tables', 'wp-optimal-state')); ?>;
                            }
                            message += '!';
                        } else {
                            message = <?php echo wp_json_encode(__('No tables need repair', 'wp-optimal-state')); ?>;
                        }
                        
                        $('#wp-opt-state-table-results').addClass('show').html(
                            '<div class="wp-opt-state-success">' + message + '</div>'
                        ).hide().fadeIn(300);
                        showToast(message, 'success');
                    }
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Analyze & Repair Tables', 'wp-optimal-state')); ?>);
                })
                .fail(function() {
                    wpOptStateAjax.isProcessing = false;
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Analyze & Repair Tables', 'wp-optimal-state')); ?>);
                    handleAjaxError();
                });
            });
            
            $('#wp-opt-state-optimize-autoload').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                wpOptStateAjax.isProcessing = true;
                btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Optimizing...', 'wp-optimal-state')); ?>);
                
                $.post(wpOptStateAjax.ajaxurl, {
                    action: 'wp_opt_state_optimize_autoload',
                    nonce: wpOptStateAjax.nonce
                })
                .done(function(response) {
                    wpOptStateAjax.isProcessing = false;
                    if (response.success) {
                        var message = <?php echo wp_json_encode(__('✓ Optimized', 'wp-optimal-state')); ?> + ' ' + response.data.optimized + ' ' + 
                                     <?php echo wp_json_encode(__('large autoloaded options (found', 'wp-optimal-state')); ?> + ' ' + response.data.found + ' ' + 
                                     <?php echo wp_json_encode(__('total)', 'wp-optimal-state')); ?>;
                        $('#wp-opt-state-table-results').addClass('show').html(
                            '<div class="wp-opt-state-success">' + message + '</div>'
                        ).hide().fadeIn(300);
                        showToast(message, 'success');
                        setTimeout(function() { loadStats(); }, 1500);
                    }
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize Autoloaded Options', 'wp-optimal-state')); ?>);
                })
                .fail(function() {
                    wpOptStateAjax.isProcessing = false;
                    btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize Autoloaded Options', 'wp-optimal-state')); ?>);
                    handleAjaxError();
                });
            });
            
            $('#wp-opt-state-clean-old-revisions').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                var days = $('input[name="<?php echo esc_js($this->option_name); ?>[revision_days]"]').val() || 30;
                
                var confirmMessage = <?php echo wp_json_encode(__('Delete all revisions older than', 'wp-optimal-state')); ?> + ' ' + days + ' ' + <?php echo wp_json_encode(__('days? This action cannot be undone.', 'wp-optimal-state')); ?>;
                
                showModal(<?php echo wp_json_encode(__('Confirm Deletion', 'wp-optimal-state')); ?>, confirmMessage, function() {
                    wpOptStateAjax.isProcessing = true;
                    btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Cleaning...', 'wp-optimal-state')); ?>);
                    
                    $.post(wpOptStateAjax.ajaxurl, {
                        action: 'wp_opt_state_clean_old_revisions',
                        nonce: wpOptStateAjax.nonce,
                        days: days
                    })
                    .done(function(response) {
                        wpOptStateAjax.isProcessing = false;
                        if (response.success) {
                            var message = <?php echo wp_json_encode(__('✓ Deleted', 'wp-optimal-state')); ?> + ' ' + response.data.deleted + ' ' + <?php echo wp_json_encode(__('old revisions', 'wp-optimal-state')); ?>;
                            showToast(message, 'success');
                            loadStats();
                        }
                        btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Clean Old Revisions Now', 'wp-optimal-state')); ?>);
                    })
                    .fail(function() {
                        wpOptStateAjax.isProcessing = false;
                        btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Clean Old Revisions Now', 'wp-optimal-state')); ?>);
                        handleAjaxError();
                    });
                }, false);
            });
            
            $('#wp-opt-state-one-click').on('click', function() {
                if (wpOptStateAjax.isProcessing) return;
                
                var btn = $(this);
                var message = <?php echo wp_json_encode(__('This will perform a full database optimization including:<br><br>• Clean post revisions<br>• Remove auto-drafts<br>• Delete spam comments<br>• Remove orphaned data<br>• Optimize database tables<br><br>This is safe but cannot be undone.', 'wp-optimal-state')); ?>;
                
                showModal(<?php echo wp_json_encode(__('🚀 Full Optimization', 'wp-optimal-state')); ?>, message, function() {
                    wpOptStateAjax.isProcessing = true;
                    btn.prop('disabled', true).addClass('loading').text(<?php echo wp_json_encode(__('Optimizing...', 'wp-optimal-state')); ?>);
                    
                    $.post(wpOptStateAjax.ajaxurl, {
                        action: 'wp_opt_state_one_click_optimize',
                        nonce: wpOptStateAjax.nonce
                    })
                    .done(function(response) {
                        wpOptStateAjax.isProcessing = false;
                        var html = '<div class="wp-opt-state-success"><strong>' + <?php echo wp_json_encode(__('✓ Optimization Complete!', 'wp-optimal-state')); ?> + '</strong></div>';
                        
                        if (response.success) {
                            for (var key in response.data) {
                                html += '<div class="wp-opt-state-result-item">' + 
                                       <?php echo wp_json_encode(__('Cleaned', 'wp-optimal-state')); ?> + ' ' + response.data[key] + ' ' + 
                                       key.replace(/_/g, ' ') + '</div>';
                            }
                            showToast(<?php echo wp_json_encode(__('Optimization completed successfully!', 'wp-optimal-state')); ?>, 'success');
                        }
                        
                        $('#wp-opt-state-one-click-results').addClass('show').html(html).hide().fadeIn(300);
                        btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize Now', 'wp-optimal-state')); ?>);
                        setTimeout(function() { loadStats(); }, 1500);
                    })
                    .fail(function() {
                        wpOptStateAjax.isProcessing = false;
                        btn.removeClass('loading').prop('disabled', false).text(<?php echo wp_json_encode(__('Optimize Now', 'wp-optimal-state')); ?>);
                        handleAjaxError();
                    });
                }, false);
            });
            
            loadStats();
        });
        </script>
        <?php
    }
    
    /**
     * Display the main admin page
     */
    public function display_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'wp-optimal-state'));
        }
        
        $options = get_option($this->option_name, array());
        $revision_days = isset($options['revision_days']) ? intval($options['revision_days']) : 30;
        ?>
        <div class="wrap wp-opt-state-wrap">
            <h1 style="font-size: 1.8em; font-weight: 600;"><span class="dashicons dashicons-performance"></span> <?php echo esc_html($this->plugin_name); ?></h1>
            <?php settings_errors(); ?>
            
            <div class="wp-opt-state-container">
                <div class="wp-opt-state-notice wp-opt-state-notice-warning">
                    <strong>⚠️ <?php esc_html_e('Important:', 'wp-optimal-state'); ?></strong> <?php esc_html_e('Always backup your database before performing cleanup operations.', 'wp-optimal-state'); ?> 
                    <a href="<?php echo esc_url(admin_url('export.php')); ?>" target="_blank"><?php esc_html_e('Export your data here', 'wp-optimal-state'); ?></a>
                </div>

                <div class="wp-opt-state-notice">
                    <strong>📖 <?php esc_html_e('Need Help?', 'wp-optimal-state'); ?></strong> <?php esc_html_e('Check out the full plugin manual for detailed instructions and best practices.', 'wp-optimal-state'); ?>
                    <a href="https://spiritualseek.com/wp-optimal-state-wordpress-plugin/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Read the Manual', 'wp-optimal-state'); ?></a>
                </div>
                
                <div class="wp-opt-state-grid">
                    <div class="wp-opt-state-card wp-opt-state-card-highlight">
                        <h2>🚀 <?php esc_html_e('One-Click Optimization', 'wp-optimal-state'); ?></h2>
                        <p><?php esc_html_e('Perform all safe optimizations with one click', 'wp-optimal-state'); ?></p>
                        <button class="button button-primary button-hero wp-opt-state-one-click" id="wp-opt-state-one-click">
                            <?php esc_html_e('Optimize Now', 'wp-optimal-state'); ?>
                        </button>
                        <div id="wp-opt-state-one-click-results" class="wp-opt-state-results"></div>
                    </div>
                    
                    <div class="wp-opt-state-card">
                        <h2>📊 <?php esc_html_e('Database Statistics', 'wp-optimal-state'); ?></h2>
                        <div id="wp-opt-state-stats-loading" class="wp-opt-state-loading"><?php esc_html_e('Loading statistics...', 'wp-optimal-state'); ?></div>
                        <div id="wp-opt-state-stats" class="wp-opt-state-stats"></div>
                        <div id="wp-opt-state-db-size" class="wp-opt-state-db-size">
                            <strong><?php esc_html_e('Total Database Size:', 'wp-optimal-state'); ?></strong> <span id="wp-opt-state-db-size-value"><?php esc_html_e('Calculating...', 'wp-optimal-state'); ?></span>
                        </div>
                        <button class="button wp-opt-state-refresh-stats" id="wp-opt-state-refresh-stats"><?php esc_html_e('Refresh Stats', 'wp-optimal-state'); ?></button>
                    </div>
                </div>
                
                <div class="wp-opt-state-card">
                    <h2>🧹 <?php esc_html_e('Database Cleanup', 'wp-optimal-state'); ?></h2>
                    <div class="wp-opt-state-cleanup-grid" id="wp-opt-state-cleanup-items"></div>
                </div>
                
                <div class="wp-opt-state-card">
                    <h2>🗄️ <?php esc_html_e('Database Optimization', 'wp-optimal-state'); ?></h2>
                    <p><?php esc_html_e('Optimize and repair database tables to improve performance', 'wp-optimal-state'); ?></p>
                    <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                        <button class="button button-secondary" id="wp-opt-state-optimize-tables"><?php esc_html_e('Optimize All Tables', 'wp-optimal-state'); ?></button>
                        <button class="button button-secondary" id="wp-opt-state-analyze-repair-tables"><?php esc_html_e('Analyze & Repair Tables', 'wp-optimal-state'); ?></button>
                        <button class="button button-secondary" id="wp-opt-state-optimize-autoload"><?php esc_html_e('Optimize Autoloaded Options', 'wp-optimal-state'); ?></button>
                    </div>
                    <div id="wp-opt-state-table-results" class="wp-opt-state-results"></div>
                </div>
                
                <div class="wp-opt-state-card">
                    <h2>⚙️ <?php esc_html_e('Settings', 'wp-optimal-state'); ?></h2>
                    <form method="post" action="options.php">
                        <?php settings_fields('wp_opt_state_settings_group'); ?>
                        <table class="form-table">
                            <tr>
                                <th><?php esc_html_e('Delete Revisions Older Than', 'wp-optimal-state'); ?></th>
                                <td>
                                    <input type="number" name="<?php echo esc_attr($this->option_name); ?>[revision_days]" value="<?php echo esc_attr($revision_days); ?>" min="0" max="365"> <?php esc_html_e('days', 'wp-optimal-state'); ?>
                                    <p class="description"><?php esc_html_e('Automatically delete post revisions older than specified days (0 = disabled)', 'wp-optimal-state'); ?></p>
                                    <button type="button" class="button" id="wp-opt-state-clean-old-revisions"><?php esc_html_e('Clean Old Revisions Now', 'wp-optimal-state'); ?></button>
                                </td>
                            </tr>
                        </table>
                        <?php submit_button(); ?>
                    </form>
                </div>
            </div>
        </div>
        <?php
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
        
        global $wpdb;
        
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
            'autoload_options' => absint($wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->options} WHERE autoload = 'yes'")),
            'autoload_size' => $this->get_autoload_size(),
        );
        
        wp_send_json_success($stats);
    }
    
    /**
     * AJAX handler for cleaning individual items
     */
    public function ajax_clean_item() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        $item_type = isset($_POST['item_type']) ? sanitize_text_field(wp_unslash($_POST['item_type'])) : '';
        
        if (empty($item_type)) {
            wp_send_json_error(__('Invalid item type', 'wp-optimal-state'));
            return;
        }
        
        $result = $this->clean_item($item_type);
        
        if ($result !== false) {
            wp_send_json_success(array('deleted' => $result));
        } else {
            wp_send_json_error(__('Cleanup failed', 'wp-optimal-state'));
        }
    }
    
    /**
     * Clean specific item type from database
     */
    private function clean_item($item_type) {
        global $wpdb;
        
        $allowed_types = array(
            'post_revisions', 'auto_drafts', 'trashed_posts', 'spam_comments',
            'trashed_comments', 'orphaned_postmeta', 'orphaned_commentmeta',
            'orphaned_relationships', 'expired_transients', 'all_transients',
            'duplicate_postmeta', 'duplicate_commentmeta', 'orphaned_usermeta',
            'unapproved_comments', 'pingbacks', 'trackbacks'
        );
        
        if (!in_array($item_type, $allowed_types, true)) {
            return false;
        }
        
        switch ($item_type) {
            case 'post_revisions':
                return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");
                
            case 'auto_drafts':
                return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft'");
                
            case 'trashed_posts':
                return $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash'");
                
            case 'spam_comments':
                return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'spam'");
                
            case 'trashed_comments':
                return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = 'trash'");
                
            case 'orphaned_postmeta':
                return $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT ID FROM {$wpdb->posts})");
                
            case 'orphaned_commentmeta':
                return $wpdb->query("DELETE FROM {$wpdb->commentmeta} WHERE comment_id NOT IN (SELECT comment_id FROM {$wpdb->comments})");
                
            case 'orphaned_relationships':
                return $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id NOT IN (SELECT ID FROM {$wpdb->posts})");
                
            case 'expired_transients':
                return $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_%' AND option_value < UNIX_TIMESTAMP()");
                
            case 'all_transients':
                return $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
                
            case 'duplicate_postmeta':
                return $this->delete_duplicate_postmeta();
                
            case 'duplicate_commentmeta':
                return $this->delete_duplicate_commentmeta();
                
            case 'orphaned_usermeta':
                return $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id NOT IN (SELECT ID FROM {$wpdb->users})");
                
            case 'unapproved_comments':
                return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved = '0'");
                
            case 'pingbacks':
                return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_type = 'pingback'");
                
            case 'trackbacks':
                return $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_type = 'trackback'");
                
            default:
                return false;
        }
    }
    
    /**
     * AJAX handler for optimizing database tables
     */
    public function ajax_optimize_tables() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $optimized = 0;
        
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (isset($table[0])) {
                    $table_name = esc_sql($table[0]);
                    $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
                    $optimized++;
                }
            }
        }
        
        wp_send_json_success(array('optimized' => $optimized));
    }
    
    /**
     * AJAX handler for one-click optimization
     */
    public function ajax_one_click_optimize() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        $safe_items = array(
            'post_revisions', 'auto_drafts', 'spam_comments', 'trashed_comments',
            'orphaned_postmeta', 'orphaned_commentmeta', 'orphaned_relationships',
            'expired_transients', 'orphaned_usermeta', 'pingbacks', 'trackbacks'
        );
        
        $results = array();
        foreach ($safe_items as $item) {
            $deleted = $this->clean_item($item);
            if ($deleted !== false && $deleted > 0) {
                $results[$item] = $deleted;
            }
        }
        
        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (isset($table[0])) {
                    $table_name = esc_sql($table[0]);
                    $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
                }
            }
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * AJAX handler for getting database size
     */
    public function ajax_get_db_size() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        global $wpdb;
        $size = $wpdb->get_var($wpdb->prepare("
            SELECT SUM(data_length + index_length) 
            FROM information_schema.TABLES 
            WHERE table_schema = %s
        ", DB_NAME));
        
        wp_send_json_success(array('size' => $this->format_bytes($size)));
    }
    
    /**
     * AJAX handler for cleaning old revisions
     */
    public function ajax_clean_old_revisions() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 30;
        $days = min(max($days, 0), 365);
        
        global $wpdb;
        $deleted = $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->posts} 
            WHERE post_type = 'revision' 
            AND post_modified < DATE_SUB(NOW(), INTERVAL %d DAY)
        ", $days));
        
        wp_send_json_success(array('deleted' => absint($deleted)));
    }
    
    /**
     * AJAX handler for optimizing autoload options
     */
    public function ajax_optimize_autoload() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        global $wpdb;
        
        $large_options = $wpdb->get_results("
            SELECT option_name, LENGTH(option_value) as size 
            FROM {$wpdb->options} 
            WHERE autoload = 'yes' 
            AND LENGTH(option_value) > 102400
            ORDER BY size DESC
            LIMIT 10
        ");
        
        $optimized = 0;
        $excluded = array('active_plugins', 'cron', 'rewrite_rules');
        
        if (is_array($large_options)) {
            foreach ($large_options as $option) {
                if (!in_array($option->option_name, $excluded, true)) {
                    $wpdb->update(
                        $wpdb->options,
                        array('autoload' => 'no'),
                        array('option_name' => $option->option_name),
                        array('%s'),
                        array('%s')
                    );
                    $optimized++;
                }
            }
        }
        
        wp_send_json_success(array(
            'optimized' => $optimized,
            'found' => is_array($large_options) ? count($large_options) : 0
        ));
    }
    
    /**
     * AJAX handler for analyzing and repairing database tables
     */
    public function ajax_analyze_repair_tables() {
        check_ajax_referer($this->nonce_action, 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Unauthorized access', 'wp-optimal-state'));
            return;
        }
        
        global $wpdb;
        
        // Get all database tables
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        $analyzed = 0;
        $repaired = 0;
        $results = array();
        
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (isset($table[0])) {
                    $table_name = $table[0];
                    $analyzed++;
                    
                    // Analyze the table
                    $analysis = $wpdb->get_row($wpdb->prepare("CHECK TABLE `%s`", $table_name));
                    
                    if ($analysis && isset($analysis->Msg_text)) {
                        $message = $analysis->Msg_text;
                        
                        // Check if table needs repair
                        if (in_array(strtoupper($message), array('OK', 'TABLE IS ALREADY UP TO DATE'))) {
                            // Table is fine
                            $results[$table_name] = __('OK', 'wp-optimal-state');
                        } else {
                            // Table needs repair - attempt to repair it
                            $repair_result = $wpdb->get_row($wpdb->prepare("REPAIR TABLE `%s`", $table_name));
                            
                            if ($repair_result && isset($repair_result->Msg_text)) {
                                $repair_message = $repair_result->Msg_text;
                                
                                if (in_array(strtoupper($repair_message), array('OK', 'TABLE IS ALREADY UP TO DATE'))) {
                                    $results[$table_name] = __('Repaired', 'wp-optimal-state');
                                    $repaired++;
                                } else {
                                    $results[$table_name] = sprintf(__('Repair failed: %s', 'wp-optimal-state'), $repair_message);
                                }
                            } else {
                                $results[$table_name] = __('Repair analysis failed', 'wp-optimal-state');
                            }
                        }
                    } else {
                        $results[$table_name] = __('Analysis failed', 'wp-optimal-state');
                    }
                    
                    // Add small delay to prevent server overload
                    if (count($tables) > 10) {
                        usleep(100000); // 0.1 second delay for large databases
                    }
                }
            }
        }
        
        // Log results for debugging
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('WP Optimal State - Table analysis completed: ' . $analyzed . ' analyzed, ' . $repaired . ' repaired');
        }
        
        wp_send_json_success(array(
            'analyzed' => $analyzed,
            'repaired' => $repaired,
            'results' => $results
        ));
    }
    
    /**
     * Run scheduled cleanup tasks
     */
    public function run_scheduled_cleanup() {
        $safe_items = array(
            'expired_transients', 'orphaned_postmeta',
            'orphaned_commentmeta', 'spam_comments'
        );
        
        foreach ($safe_items as $item) {
            $this->clean_item($item);
        }
        
        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);
        if (is_array($tables)) {
            foreach ($tables as $table) {
                if (isset($table[0])) {
                    $table_name = esc_sql($table[0]);
                    $wpdb->query("OPTIMIZE TABLE `{$table_name}`");
                }
            }
        }
    }
    
    /**
     * Count duplicate postmeta entries
     */
    private function count_duplicate_postmeta() {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM (
            SELECT post_id, meta_key, COUNT(*) as cnt 
            FROM {$wpdb->postmeta} 
            GROUP BY post_id, meta_key 
            HAVING cnt > 1
        ) as duplicates";
        return absint($wpdb->get_var($query));
    }
    
    /**
     * Delete duplicate postmeta entries
     */
    private function delete_duplicate_postmeta() {
        global $wpdb;
        return absint($wpdb->query("
            DELETE pm1 FROM {$wpdb->postmeta} pm1
            INNER JOIN {$wpdb->postmeta} pm2 
            WHERE pm1.meta_id < pm2.meta_id 
            AND pm1.post_id = pm2.post_id 
            AND pm1.meta_key = pm2.meta_key
        "));
    }
    
    /**
     * Count duplicate commentmeta entries
     */
    private function count_duplicate_commentmeta() {
        global $wpdb;
        $query = "SELECT COUNT(*) FROM (
            SELECT comment_id, meta_key, COUNT(*) as cnt 
            FROM {$wpdb->commentmeta} 
            GROUP BY comment_id, meta_key 
            HAVING cnt > 1
        ) as duplicates";
        return absint($wpdb->get_var($query));
    }
    
    /**
     * Delete duplicate commentmeta entries
     */
    private function delete_duplicate_commentmeta() {
        global $wpdb;
        return absint($wpdb->query("
            DELETE cm1 FROM {$wpdb->commentmeta} cm1
            INNER JOIN {$wpdb->commentmeta} cm2 
            WHERE cm1.meta_id < cm2.meta_id 
            AND cm1.comment_id = cm2.comment_id 
            AND cm1.meta_key = cm2.meta_key
        "));
    }
    
    /**
     * Get total size of autoloaded options
     */
    private function get_autoload_size() {
        global $wpdb;
        $size = $wpdb->get_var("
            SELECT SUM(LENGTH(option_value)) 
            FROM {$wpdb->options} 
            WHERE autoload = 'yes'
        ");
        return $this->format_bytes($size);
    }
    
    /**
     * Format bytes to human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}

new WP_Optimal_State();

register_activation_hook(__FILE__, 'wp_opt_state_activate');
function wp_opt_state_activate() {
    $default_settings = array(
        'schedule' => 'disabled',
        'keep_revisions' => 5,
        'revision_days' => 30,
        'optimize_images' => 0
    );
    add_option('wp_opt_state_settings', $default_settings);
    add_option('wp_opt_state_backup_reminder', 1);
    add_option('wp_opt_state_activation_time', time());
}

register_deactivation_hook(__FILE__, 'wp_opt_state_deactivate');
function wp_opt_state_deactivate() {
    wp_clear_scheduled_hook('wp_opt_state_scheduled_cleanup');
}

add_action('admin_init', 'wp_opt_state_register_settings');
function wp_opt_state_register_settings() {
    register_setting(
        'wp_opt_state_settings_group',
        'wp_opt_state_settings',
        array(
            'sanitize_callback' => 'wp_opt_state_sanitize_settings',
            'default' => array('revision_days' => 30)
        )
    );
}

function wp_opt_state_sanitize_settings($input) {
    $sanitized = array();
    
    if (isset($input['revision_days'])) {
        $sanitized['revision_days'] = absint($input['revision_days']);
        $sanitized['revision_days'] = min(max($sanitized['revision_days'], 0), 365);
    }
    
    if (isset($input['schedule'])) {
        $allowed_schedules = array('disabled', 'daily', 'weekly');
        $sanitized['schedule'] = in_array($input['schedule'], $allowed_schedules, true) ? $input['schedule'] : 'disabled';
    }
    
    return $sanitized;
}