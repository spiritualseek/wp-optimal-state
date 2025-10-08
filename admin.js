jQuery(document).ready(function($) {
    'use strict';
    
    // Add this utility function to format bytes to human-readable sizes
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}
    
    // --- Reusable Confirmation Modal ---
    function showConfirmationModal(title, message, onConfirm) {
        // Remove any existing modals
        $('.db-backup-modal-overlay').remove();

        const modalHTML = `
            <div class="db-backup-modal-overlay">
                <div class="db-backup-modal">
                    <div class="db-backup-modal-header">
                        <span class="dashicons dashicons-warning"></span>
                        <span>${title}</span>
                    </div>
                    <div class="db-backup-modal-content">${message}</div>
                    <div class="db-backup-modal-footer">
                        <button type="button" class="button button-secondary" id="modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="modal-confirm">Confirm</button>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML);
        const overlay = $('.db-backup-modal-overlay');
        overlay.fadeIn(200);

        overlay.on('click', '#modal-confirm', function() {
            onConfirm();
            overlay.fadeOut(200, function() { $(this).remove(); });
        });

        overlay.on('click', '#modal-cancel', function() {
            overlay.fadeOut(200, function() { $(this).remove(); });
        });
    }
    
    // --- Update Backups Table Function ---
    function updateBackupsList(backups) {
        const tbody = $('#backups-list');
        tbody.empty();
        
        if (backups.length === 0) {
            const emptyMsg = '<tr><td colspan="4" class="db-backup-empty">No backups found. Create your first backup!</td></tr>';
            tbody.html(emptyMsg);
        } else {
            backups.forEach(function(backup) {
                const row = `
                    <tr data-file="${backup.filename}">
                        <td><strong>${backup.filename}</strong></td>
                        <td>${backup.date}</td>
                        <td>${backup.size}</td>
                        <td>
                            <a href="${backup.download_url}" class="button download-backup">
                                <span class="dashicons dashicons-download"></span> Download
                            </a>
                            <button class="button restore-backup" data-file="${backup.filename}">
                                <span class="dashicons dashicons-backup"></span> Restore
                            </button>
                            <button class="button delete-backup" data-file="${backup.filename}">
                                <span class="dashicons dashicons-trash"></span> Delete
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
    }
    
    // --- Event Handler: Create Backup ---
    $('#create-backup-btn').on('click', function() {
        const btn = $(this);
        const spinner = $('#backup-spinner');

        showConfirmationModal('Create Backup', 'Create a new database backup?\nThis may take a few moments.', function() {
            btn.prop('disabled', true);
            spinner.show();
            
            $.ajax({
                url: dbBackupManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'create_backup',
                    nonce: dbBackupManager.nonce
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        updateBackupsList(response.data.backups);
                    } else {
                        showToast(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 429) {
                        showToast('Please wait before creating another backup.', 'error');
                    } else {
                        showToast('An error occurred while creating the backup.', 'error');
                    }
                },
                complete: function() {
                    btn.prop('disabled', false);
                    spinner.hide();
                }
            });
        });
    });
    
    // --- Event Handler: Delete Backup ---
    $(document).on('click', '.delete-backup', function() {
        const btn = $(this);
        const filename = btn.data('file');
        const row = btn.closest('tr');
        
        const message = `Are you sure you want to delete this backup?\n\nBackup: ${filename}\n\nThis action cannot be undone.`;

        showConfirmationModal('Confirm Deletion', message, function() {
            btn.prop('disabled', true);
            
            $.ajax({
                url: dbBackupManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'delete_backup',
                    nonce: dbBackupManager.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message, 'success');
                        row.fadeOut(300, function() {
                            $(this).remove();
                            if ($('#backups-list tr').length === 0) {
                                const emptyMsg = '<tr><td colspan="4" class="db-backup-empty">No backups found. Create your first backup!</td></tr>';
                                $('#backups-list').html(emptyMsg);
                            }
                        });
                    } else {
                        showToast(response.data.message, 'error');
                        btn.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showToast('An error occurred while deleting the backup.', 'error');
                    btn.prop('disabled', false);
                }
            });
        });
    });
    
    // --- Event Handler: Restore Backup ---
    $(document).on('click', '.restore-backup', function() {
        const btn = $(this);
        const filename = btn.data('file');
        
        const message = `This will restore your database from:\n\n${filename}\n\nALL CURRENT DATA WILL BE REPLACED!\n\nAre you absolutely sure you want to continue?`;
        
        showConfirmationModal('WARNING: Restore Database', message, function() {
            btn.prop('disabled', true);
            btn.html('<span class="spinner is-active" style="float:none;"></span> Restoring...');
            
            $.ajax({
                url: dbBackupManager.ajax_url,
                type: 'POST',
                data: {
                    action: 'restore_backup',
                    nonce: dbBackupManager.nonce,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        showToast(response.data.message + ' Page will reload in 5 seconds...', 'success');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 6000);
                    } else {
                        showToast(response.data.message, 'error');
                        btn.prop('disabled', false);
                        btn.html('<span class="dashicons dashicons-backup"></span> Restore');
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 429) {
                        showToast('Please wait before restoring another backup.', 'error');
                    } else {
                        showToast('An error occurred during the restore process.', 'error');
                    }
                    btn.prop('disabled', false);
                    btn.html('<span class="dashicons dashicons-backup"></span> Restore');
                }
            });
        });
});
    
    // --- Event Handler: Save Max Backups Setting ---
    $('#save-max-backups-btn').on('click', function() {
        const btn = $(this);
        const maxBackups = $('#max_backups_setting').val();
        
        btn.prop('disabled', true).text('✔ Saving...');
        
        $.ajax({
            url: wpOptStateAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_opt_state_save_max_backups',
                nonce: wpOptStateAjax.nonce,
                max_backups: maxBackups
            },
            success: function(response) {
                if (response.success) {
                    showToast('Maximum backups setting saved successfully!', 'success');
                } else {
                    showToast(response.data.message || 'Failed to save setting.', 'error');
                }
            },
            error: function() {
                showToast('An error occurred while saving the setting.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('✔ Save');
            }
        });
    });

    let isProcessing = false;

    const labels = {
        // Grouped for better display logic
        'post_revisions': 'Post Revisions',
        'post_revisions_size': 'Revisions Data Size',
        
        'expired_transients': 'Expired Transients',
        'expired_transients_size': 'Expired Transients Data Size',

        // Core Database Metrics
        'table_overhead': 'Database Overhead', 
        'total_indexes_size': 'Total Indexes Size',
        'total_tables_count': 'Number of Tables',
        'db_creation_date': 'Database Created On',
        'autoload_options': 'Autoloaded Options',
        'autoload_size': 'Autoload Data Size',
        
        // Cleanup Counts
        'auto_drafts': 'Auto Drafts', 
        'trashed_posts': 'Trashed Posts',
        'spam_comments': 'Spam Comments', 
        'trashed_comments': 'Trashed Comments', 
        'orphaned_postmeta': 'Orphaned Post Meta',
        'orphaned_commentmeta': 'Orphaned Comment Meta', 
        'orphaned_relationships': 'Orphaned Term Relationships',
        'orphaned_usermeta': 'Orphaned User Meta',
        'duplicate_postmeta': 'Duplicate Post Meta',
        'duplicate_commentmeta': 'Duplicate Comment Meta',
        'unapproved_comments': 'Unapproved Comments',
        'pingbacks': 'Pingbacks',
        'trackbacks': 'Trackbacks',
        'all_transients': 'All Transients (Non-expired)'
    };
    
    function showOptStateModal(title, message, onConfirm, isDanger) {
        const $overlay = $('<div class="wp-opt-state-modal-overlay"></div>');
        const dangerClass = isDanger ? ' wp-opt-state-modal-danger' : '';
        const $modal = $(`<div class="wp-opt-state-modal${dangerClass}">
                      <div class="wp-opt-state-modal-header"><h3>${title}</h3><button class="wp-opt-state-modal-close">&times;</button></div>
                      <div class="wp-opt-state-modal-body">${message}</div>
                      <div class="wp-opt-state-modal-footer">
                          <button class="button wp-opt-state-modal-cancel">Cancel</button>
                          <button class="button button-primary wp-opt-state-modal-confirm">Confirm</button>
                      </div>
                      </div>`);
        
        $('body').append($overlay).append($modal);
        
        setTimeout(() => { $overlay.addClass('show'); $modal.addClass('show'); }, 10);
        
        const closeModal = () => {
            $overlay.removeClass('show');
            $modal.removeClass('show');
            setTimeout(() => { $overlay.remove(); $modal.remove(); }, 300);
        };
        
        $modal.find('.wp-opt-state-modal-close, .wp-opt-state-modal-cancel').on('click', closeModal);
        $overlay.on('click', closeModal);
        
        $modal.find('.wp-opt-state-modal-confirm').on('click', function() {
            closeModal();
            if (onConfirm) onConfirm();
        });
        
        $modal.on('click', (e) => e.stopPropagation());
    }
    
    function showToast(message, type = 'success') {
        const $toast = $(`<div class="wp-opt-state-toast wp-opt-state-toast-${type}">
                      <span class="wp-opt-state-toast-icon"></span>${message}</div>`);
        $('body').append($toast);
        
        setTimeout(() => { $toast.addClass('show'); }, 100);
        setTimeout(() => {
            $toast.removeClass('show');
            setTimeout(() => { $toast.remove(); }, 300);
        }, 8000);
    }
    
    function handleAjaxError(xhr, status, error) {
        console.error('AJAX Error:', status, error);
        if (xhr.status === 429) {
            showToast('Please wait before performing this action again.', 'error');
        } else {
            showToast('An error occurred. Please try again.', 'error');
        }
        isProcessing = false;
    }
    
    // UPDATED loadStats FUNCTION
 function loadStats(forceRefresh = false) {
    $('#wp-opt-state-stats-loading').fadeIn(200);
    $('#wp-opt-state-stats').empty();
    
    const data = {
        action: 'wp_opt_state_get_stats',
        nonce: wpOptStateAjax.nonce
    };
    
    // Add force refresh parameter if needed
    if (forceRefresh) {
        data.force_refresh = true;
    }
    
    $.post(wpOptStateAjax.ajaxurl, data)
    .done(function(response) {
        $('#wp-opt-state-stats-loading').fadeOut(200);
        if (response.success && response.data) {
            const stats = response.data;
            displayStats(stats);
            displayCleanupItems(stats);

            // Your existing stats toggle logic...
            const statsCount = Object.keys(stats).filter(key => labels[key]).length;
            
            if (statsCount > 6) { 
                $('#wp-opt-state-toggle-stats').fadeIn(300);
                $('#wp-opt-state-stats-wrapper').removeClass('expanded');
                $('#wp-opt-state-toggle-stats').html('Show More Stats ↓').removeClass('less');
            } else {
                $('#wp-opt-state-stats-wrapper').addClass('expanded');
                $('#wp-opt-state-toggle-stats').hide();
            }
        } else {
            showToast('Failed to load statistics', 'error');
        }
    })
    .fail(handleAjaxError);
    
    // Your existing DB size code...
    $.post(wpOptStateAjax.ajaxurl, {
        action: 'wp_opt_state_get_db_size',
        nonce: wpOptStateAjax.nonce
    })
    .done(function(response) {
        if (response.success && response.data) {
            $('#wp-opt-state-db-size-value').text(response.data.size);
        }
    })
    .fail(() => { $('#wp-opt-state-db-size-value').text('Error'); });
}

// Update the refresh button handler
$('#wp-opt-state-refresh-stats').on('click', function() {
    if (isProcessing) return;
    loadStats(true); // Pass true to force refresh
    showToast('Statistics refreshed', 'info');
});
    // END UPDATED loadStats FUNCTION
    
    // admin.js

// --- Event Handler: Save Automatic Optimization Setting ---
$('#save-auto-optimize-btn').on('click', function() {
    const btn = $(this);
    const autoOptimizeDays = $('#auto_optimize_days').val();
    
    // Simple validation
    if (isNaN(autoOptimizeDays) || autoOptimizeDays < 0 || autoOptimizeDays > 365) {
        showToast('Please enter a number between 0 and 365.', 'error');
        return;
    }

    btn.prop('disabled', true).text('✔ Saving...');
    
    $.ajax({
        url: wpOptStateAjax.ajaxurl, // Use the localized ajaxurl
        type: 'POST',
        data: {
            action: 'wp_opt_state_save_auto_settings', // The new AJAX action
            nonce: wpOptStateAjax.nonce,
            auto_optimize_days: autoOptimizeDays
        },
        success: function(response) {
            if (response.success) {
                showToast(response.data.message, 'success');
                const days = response.data.days;
                const enabledSpan = $('#auto-status-enabled');
                const disabledSpan = $('#auto-status-disabled');

                // Update status message dynamically
                if (days > 0) {
                    enabledSpan.html(`✅ Automated optimization is enabled and will run every ${days} days.`);
                    enabledSpan.show();
                    disabledSpan.hide();
                } else {
                    disabledSpan.show();
                    enabledSpan.hide();
                }
            } else {
                showToast(response.data.message || 'Failed to save setting.', 'error');
            }
        },
        error: function() {
            showToast('An error occurred while saving the setting.', 'error');
        },
        complete: function() {
            btn.prop('disabled', false).text('✓ Save Settings');
        }
    });
});
    
function displayStats(stats) {
    let html = '';
    for (const key in stats) {
        if (labels[key]) {
            let value = (stats[key] === false || stats[key] === null) ? '0 B' : stats[key];
            
            // Ensure date values are treated as text for proper display
            if (key === 'db_creation_date') {
                value = '<span style="white-space: nowrap;">' + value + '</span>';
            }
            
            html += `<div class="wp-opt-state-stat-item">
                     <div class="wp-opt-state-stat-label">${labels[key]}</div>
                     <div class="wp-opt-state-stat-value">${value}</div>
                     </div>`;
        }
    }
    $('#wp-opt-state-stats').html(html).hide().fadeIn(300);
    
    // Load optimization log
    loadOptimizationLog();
}

function loadOptimizationLog() {
    $.post(wpOptStateAjax.ajaxurl, {
        action: 'wp_opt_state_get_optimization_log',
        nonce: wpOptStateAjax.nonce
    })
    .done(function(response) {
        if (response.success && response.data) {
            displayOptimizationLog(response.data);
        }
    })
    .fail(() => {
        console.log('Failed to load optimization log');
    });
}

function displayOptimizationLog(log) {
    let html = '<div class="wp-opt-state-log"><h3><span class="dashicons dashicons-backup"></span> Optimization History</h3><div class="wp-opt-state-log-list">';
    
    if (log.length === 0) {
        html += '<div class="wp-opt-state-log-empty">No optimization runs recorded yet.</div>';
    } else {
        log.forEach(entry => {
            const typeClass = entry.type === 'manual' ? 'manual' : 'scheduled';
            const typeLabel = entry.type === 'manual' ? 'Manual' : 'Scheduled';
            const operation = entry.operation || 'One-Click Optimization';
            html += `<div class="wp-opt-state-log-item">
                    <span class="wp-opt-state-log-date">${entry.date}</span>
                    <span class="wp-opt-state-log-operation">${operation}</span>
                    <span class="wp-opt-state-log-type ${typeClass}">${typeLabel}</span>
                    </div>`;
        });
    }
    
    html += '</div></div>';
    $('#wp-opt-state-settings-log').html(html).hide().fadeIn(300);
}
    
    function displayCleanupItems(stats) {
        const items = [
            {key: 'post_revisions', title: 'Post Revisions', desc: 'Old versions of posts and pages', safe: true},
            {key: 'auto_drafts', title: 'Auto Drafts', desc: 'Automatically saved drafts', safe: true},
            {key: 'trashed_posts', title: 'Trashed Posts', desc: 'Posts in trash', safe: false},
            {key: 'spam_comments', title: 'Spam Comments', desc: 'Comments marked as spam', safe: true},
            {key: 'trashed_comments', title: 'Trashed Comments', desc: 'Comments in trash', safe: true},
            {key: 'orphaned_postmeta', title: 'Orphaned Post Meta', desc: 'Metadata for deleted posts', safe: true},
            {key: 'orphaned_commentmeta', title: 'Orphaned Comment Meta', desc: 'Metadata for deleted comments', safe: true},
            {key: 'orphaned_relationships', title: 'Orphaned Relationships', desc: 'Term relationships for deleted posts', safe: true},
            {key: 'expired_transients', title: 'Expired Transients', desc: 'Expired temporary options', safe: true},
            {key: 'all_transients', title: 'All Transients', desc: 'All cached temporary data', safe: false},
            {key: 'duplicate_postmeta', title: 'Duplicate Post Meta', desc: 'Duplicate metadata entries', safe: true},
            {key: 'duplicate_commentmeta', title: 'Duplicate Comment Meta', desc: 'Duplicate comment metadata', safe: true},
            {key: 'orphaned_usermeta', title: 'Orphaned User Meta', desc: 'Metadata for deleted users', safe: true},
            {key: 'unapproved_comments', title: 'Unapproved Comments', desc: 'Comments awaiting moderation', safe: false},
            {key: 'pingbacks', title: 'Pingbacks', desc: 'Pingback notifications', safe: true},
            {key: 'trackbacks', title: 'Trackbacks', desc: 'Trackback notifications', safe: true}
        ];
        
        let html = '';
        items.forEach(item => {
            const count = stats[item.key] || 0;
            const warningIcon = !item.safe ? '<span class="wp-opt-state-warning-icon" title="Review before cleaning">⚠️</span>' : '';
            const disabled = count == 0 ? ' disabled' : '';
            const countClass = count > 0 ? 'has-items' : '';
            
            html += `<div class="wp-opt-state-cleanup-item ${countClass}">
                     <div class="wp-opt-state-cleanup-header">
                         <span class="wp-opt-state-cleanup-title">${item.title} ${warningIcon}</span>
                         <span class="wp-opt-state-cleanup-count">${count}</span>
                     </div>
                     <div class="wp-opt-state-cleanup-desc">${item.desc}</div>
                     <button class="wp-opt-state-clean-btn" data-type="${item.key}" data-safe="${item.safe}"${disabled}>Clean Now</button>
                     </div>`;
        });
        $('#wp-opt-state-cleanup-items').html(html).hide().fadeIn(300);
    }

    // --- NEW STATS TOGGLE (SHOW MORE/LESS) LOGIC ---
    $('#wp-opt-state-toggle-stats').on('click', function() {
        const $wrapper = $('#wp-opt-state-stats-wrapper');
        const $button = $(this);
        const isExpanded = $wrapper.hasClass('expanded');

        $wrapper.toggleClass('expanded');
        $button.toggleClass('less');

        if (isExpanded) {
            // Collapse section
            $button.html('Show More Stats ↓');
        } else {
            // Expand section
            $button.html('Show Less Stats ↑');
        }
    });
    // --- END NEW STATS TOGGLE LOGIC ---
    
    $(document).on('click', '.wp-opt-state-clean-btn:not(:disabled)', function() {
        if (isProcessing) return;
        
        const btn = $(this);
        const itemType = btn.data('type');
        const isSafe = btn.data('safe');
        
        const confirmMsg = isSafe ? 'Clean this item? This action cannot be undone.' : 'This operation should be reviewed carefully. Are you sure you want to continue?';
        const title = isSafe ? 'Confirm Cleanup' : '⚠️ Warning';
        
        showOptStateModal(title, confirmMsg, function() {
            isProcessing = true;
            btn.prop('disabled', true).addClass('loading').text('Cleaning...');
            
            $.post(wpOptStateAjax.ajaxurl, {
                action: 'wp_opt_state_clean_item',
                nonce: wpOptStateAjax.nonce,
                item_type: itemType
            })
            .done(function(response) {
                isProcessing = false;
                if (response.success) {
                    btn.removeClass('loading').addClass('success').text('Cleaned ✓');
                    showToast('Successfully cleaned!', 'success');
                    setTimeout(loadStats, 3000);
                } else {
                    btn.removeClass('loading').prop('disabled', false).text('Error - Try Again');
                    showToast(response.data || 'Cleanup failed', 'error');
                }
            })
            .fail(function(xhr) {
                isProcessing = false;
                if (xhr.status === 429) {
                    showToast('Please wait before cleaning again.', 'error');
                } else {
                    showToast('Error - Try Again', 'error');
                }
                btn.removeClass('loading').prop('disabled', false).text('Error - Try Again');
            });
        }, !isSafe);
    });
    
    $('#wp-opt-state-refresh-stats').on('click', function() {
        if (isProcessing) return;
        loadStats();
        showToast('Statistics refreshed', 'info');
    });
    
// Replace the existing table optimization handler
$('#wp-opt-state-optimize-tables').on('click', function() {
    if (isProcessing) return;
    
    const btn = $(this);
    isProcessing = true;
    btn.prop('disabled', true).addClass('loading').text('Optimizing...');
    
    $.post(wpOptStateAjax.ajaxurl, {
        action: 'wp_opt_state_optimize_tables',
        nonce: wpOptStateAjax.nonce
    })
    .done(function(response) {
        isProcessing = false;
        if (response.success) {
            const data = response.data;
            let message = `✓ Successfully optimized ${data.optimized} tables!`;
            
            if (data.reclaimed > 0) {
            const reclaimedFormatted = formatBytes(data.reclaimed);
            message += ` Reclaimed ${reclaimedFormatted} of space.`;
            }
            
            if (data.skipped > 0) {
                message += ` ${data.skipped} tables skipped (no optimization needed).`;
            }
            
            if (data.failed > 0) {
                message += ` ${data.failed} tables failed to optimize.`;
            }
            
            // Show detailed results
            let detailsHtml = `<div class="wp-opt-state-success">${message}</div>`;
            
            if (data.details && data.details.length > 0) {
                detailsHtml += `<div class="wp-opt-state-details" style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">`;
                detailsHtml += `<strong>Detailed Results:</strong><ul style="margin: 5px 0; font-size: 12px;">`;
                
                data.details.forEach(detail => {
                    let statusIcon = '⏭️';
                    if (detail.status === 'optimized') statusIcon = '✅';
                    else if (detail.status === 'failed') statusIcon = '❌';
                    else if (detail.status === 'error') statusIcon = '⚠️';
                    
                    detailsHtml += `<li>${statusIcon} ${detail.table}: ${detail.status}`;
                    if (detail.reclaimed) detailsHtml += ` (reclaimed ${detail.reclaimed})`;
                    if (detail.error) detailsHtml += ` - ${detail.error}`;
                    detailsHtml += `</li>`;
                });
                
                detailsHtml += `</ul></div>`;
            }
            
            $('#wp-opt-state-table-results').addClass('show').html(detailsHtml).hide().fadeIn(300);
            showToast(message, data.failed > 0 ? 'warning' : 'success');
            
            // Refresh stats to show updated overhead
            setTimeout(loadStats, 1500);
        }
        btn.removeClass('loading').prop('disabled', false).text('⚡ Optimize All Tables');
    })
    .fail(function(xhr) {
        isProcessing = false;
        if (xhr.status === 429) {
            showToast('Please wait before optimizing again.', 'error');
        } else {
            showToast('Optimization failed', 'error');
        }
        btn.removeClass('loading').prop('disabled', false).text('⚡ Optimize All Tables');
    });
});

// Replace the existing analyze & repair handler
$('#wp-opt-state-analyze-repair-tables').on('click', function() {
    if (isProcessing) return;
    
    const btn = $(this);
    isProcessing = true;
    btn.prop('disabled', true).addClass('loading').text('Analyzing...');
    
    $.post(wpOptStateAjax.ajaxurl, {
        action: 'wp_opt_state_analyze_repair_tables',
        nonce: wpOptStateAjax.nonce
    })
    .done(function(response) {
        isProcessing = false;
        if (response.success) {
            const data = response.data;
            let message = '';
            
            if (data.analyzed > 0) {
                message = `✓ Analyzed ${data.analyzed} tables. `;
                
                if (data.corrupted > 0) {
                    message += `Found ${data.corrupted} corrupted tables. `;
                }
                
                if (data.repaired > 0) {
                    message += `Successfully repaired ${data.repaired} tables. `;
                }
                
                if (data.optimized > 0) {
                    message += `Optimized ${data.optimized} tables. `;
                }
                
                if (data.failed > 0) {
                    message += `${data.failed} operations failed.`;
                }
                
                if (data.corrupted === 0) {
                    message += 'All tables are in optimal condition!';
                }
            } else {
                message = 'No tables were found to analyze.';
            }

            // Show detailed results
            let detailsHtml = `<div class="wp-opt-state-success">${message}</div>`;
            
            if (data.details && data.details.length > 0) {
                detailsHtml += `<div class="wp-opt-state-details" style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">`;
                detailsHtml += `<strong>Table Analysis Details:</strong><ul style="margin: 5px 0; font-size: 12px;">`;
                
                data.details.forEach(detail => {
                    let statusIcons = '';
                    if (detail.corrupted) statusIcons += '🔴';
                    if (detail.repaired) statusIcons += '🛠️';
                    if (detail.optimized) statusIcons += '⚡';
                    if (!detail.corrupted && !detail.repaired && !detail.optimized) statusIcons = '✅';
                    
                    detailsHtml += `<li>${statusIcons} ${detail.table}: `;
                    if (detail.corrupted) detailsHtml += 'Corrupted → ';
                    if (detail.repaired) detailsHtml += 'Repaired ';
                    if (detail.optimized) detailsHtml += 'Optimized';
                    if (!detail.corrupted && !detail.repaired && !detail.optimized) detailsHtml += 'Healthy';
                    if (detail.error) detailsHtml += ` - Error: ${detail.error}`;
                    detailsHtml += `</li>`;
                });
                
                detailsHtml += `</ul></div>`;
            }
            
            $('#wp-opt-state-table-results').addClass('show').html(detailsHtml).hide().fadeIn(300);
            showToast(message, data.failed > 0 ? 'warning' : 'success');
            
            // Refresh stats
            setTimeout(loadStats, 1500);
        }
        btn.removeClass('loading').prop('disabled', false).text('🛠️ Analyze & Repair Tables');
    })
    .fail(function(xhr) {
        isProcessing = false;
        if (xhr.status === 429) {
            showToast('Please wait before analyzing again.', 'error');
        } else {
            showToast('Analysis failed', 'error');
        }
        btn.removeClass('loading').prop('disabled', false).text('🛠️ Analyze & Repair Tables');
    });
});

// Replace the existing autoload optimization handler
$('#wp-opt-state-optimize-autoload').on('click', function() {
    if (isProcessing) return;
    
    const btn = $(this);
    isProcessing = true;
    btn.prop('disabled', true).addClass('loading').text('Optimizing...');
    
    $.post(wpOptStateAjax.ajaxurl, {
        action: 'wp_opt_state_optimize_autoload',
        nonce: wpOptStateAjax.nonce
    })
    .done(function(response) {
        isProcessing = false;
        if (response.success) {
            const data = response.data;
            let message = '';
            
            if (data.optimized > 0) {
                message = `✓ Optimized ${data.optimized} autoloaded options`;
                
                if (data.total_size_reduced > 0) {
                    // Convert bytes to readable format
                    const sizeReduced = (data.total_size_reduced / 1024 / 1024).toFixed(2);
                    message += `, reduced autoload size by ${sizeReduced} MB`;
                }
                
                if (data.skipped > 0) {
                    message += `, ${data.skipped} essential options preserved`;
                }
            } else {
                message = 'No autoloaded options needed optimization. Your autoloaded options are already optimized!';
            }

            // Show detailed results
            let detailsHtml = `<div class="wp-opt-state-success">${message}</div>`;
            
            if (data.details && data.details.length > 0 && data.optimized > 0) {
                detailsHtml += `<div class="wp-opt-state-details" style="margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #f9f9f9;">`;
                detailsHtml += `<strong>Optimized Options (${data.optimized} total):</strong><ul style="margin: 5px 0; font-size: 12px;">`;
                
                // Show first 10 optimized options
                data.details.slice(0, 10).forEach(detail => {
                    if (detail.status === 'optimized') {
                        detailsHtml += `<li>✅ ${detail.option} (${detail.size})</li>`;
                    }
                });
                
                if (data.optimized > 10) {
                    detailsHtml += `<li>... and ${data.optimized - 10} more options optimized</li>`;
                }
                
                detailsHtml += `</ul></div>`;
            }
            
            $('#wp-opt-state-table-results').addClass('show').html(detailsHtml).hide().fadeIn(300);
            showToast(message, 'success');
            
            // Refresh stats to show updated autoload info
            setTimeout(loadStats, 1500);
        }
        btn.removeClass('loading').prop('disabled', false).text('💾 Optimize Autoloaded Options');
    })
    .fail(function(xhr) {
        isProcessing = false;
        if (xhr.status === 429) {
            showToast('Please wait before optimizing again.', 'error');
        } else {
            showToast('Optimization failed', 'error');
        }
        btn.removeClass('loading').prop('disabled', false).text('💾 Optimize Autoloaded Options');
    });
});
    
$('#wp-opt-state-one-click').on('click', function() {
        if (isProcessing) return;
        
        const btn = $(this);
        const message = 'This will perform a full database optimization including:<br><br>• Clean post revisions<br>• Remove auto-drafts<br>• Delete spam comments<br>• Remove orphaned data<br>• Optimize database tables<br><br>This is safe but cannot be undone.';
        
        showOptStateModal('🚀 Full Optimization', message, function() {
            isProcessing = true;
            btn.prop('disabled', true).addClass('loading').text('Optimizing...');
            
            $.post(wpOptStateAjax.ajaxurl, {
                action: 'wp_opt_state_one_click_optimize',
                nonce: wpOptStateAjax.nonce
            })
            .done(function(response) {
                isProcessing = false;
                let html = '<div class="wp-opt-state-success"><strong>✓ Optimization Complete!</strong></div>';
                
                if (response.success) {
                    for (const key in response.data) {
                        html += `<div class="wp-opt-state-result-item">Cleaned ${response.data[key]} ${key.replace(/_/g, ' ')}</div>`;
                    }
                    showToast('Optimization completed successfully!', 'success');
                }
                
                $('#wp-opt-state-one-click-results').addClass('show').html(html).hide().fadeIn(300);
                btn.removeClass('loading').prop('disabled', false).text('🚀 Optimize Now');
                setTimeout(loadStats, 1500);
            })
            .fail(function(xhr) {
                isProcessing = false;
                if (xhr.status === 429) {
                    showToast('Please wait before optimizing again.', 'error');
                } else {
                    showToast('Optimization failed', 'error');
                }
                btn.removeClass('loading').prop('disabled', false).text('🚀 Optimize Now');
            });
        }, false);
    });
    
    // Initial load
    loadStats();
});