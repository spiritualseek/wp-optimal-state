jQuery(document).ready(function($) {
    'use strict';
    
    // --- Reusable Notice Function ---
    function showNotice(message, type) {
        const notice = $('#backup-notice');
        notice.removeClass('notice-success notice-error notice-warning');
        notice.addClass('notice notice-' + type);
        notice.html('<p>' + message + '</p>');
        notice.fadeIn();
        
        setTimeout(function() {
            notice.fadeOut();
        }, 5000);
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
                        showNotice(response.data.message, 'success');
                        updateBackupsList(response.data.backups);
                    } else {
                        showNotice(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 429) {
                        showNotice('Please wait before creating another backup.', 'error');
                    } else {
                        showNotice('An error occurred while creating the backup.', 'error');
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
                        showNotice(response.data.message, 'success');
                        row.fadeOut(300, function() {
                            $(this).remove();
                            if ($('#backups-list tr').length === 0) {
                                const emptyMsg = '<tr><td colspan="4" class="db-backup-empty">No backups found. Create your first backup!</td></tr>';
                                $('#backups-list').html(emptyMsg);
                            }
                        });
                    } else {
                        showNotice(response.data.message, 'error');
                        btn.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    showNotice('An error occurred while deleting the backup.', 'error');
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
                        showNotice(response.data.message + ' Page will reload in 5 seconds...', 'success');
                        
                        setTimeout(function() {
                            location.reload();
                        }, 5000);
                    } else {
                        showNotice(response.data.message, 'error');
                        btn.prop('disabled', false);
                        btn.html('<span class="dashicons dashicons-backup"></span> Restore');
                    }
                },
                error: function(xhr, status, error) {
                    if (xhr.status === 429) {
                        showNotice('Please wait before restoring another backup.', 'error');
                    } else {
                        showNotice('An error occurred during the restore process.', 'error');
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
                    showNotice('Maximum backups setting saved successfully!', 'success');
                } else {
                    showNotice(response.data.message || 'Failed to save setting.', 'error');
                }
            },
            error: function() {
                showNotice('An error occurred while saving the setting.', 'error');
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
        }, 4000);
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
            const operation = entry.operation || 'Full Optimization';
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
                    setTimeout(loadStats, 1500);
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
                const message = `✓ Successfully optimized ${response.data.optimized} tables!`;
                $('#wp-opt-state-table-results').addClass('show').html(`<div class="wp-opt-state-success">${message}</div>`).hide().fadeIn(300);
                showToast(message, 'success');
            }
            btn.removeClass('loading').prop('disabled', false).text('⚡Optimize All Tables');
        })
        .fail(function(xhr) {
            isProcessing = false;
            if (xhr.status === 429) {
                showToast('Please wait before optimizing again.', 'error');
            } else {
                showToast('Optimization failed', 'error');
            }
            btn.removeClass('loading').prop('disabled', false).text('⚡Optimize All Tables');
        });
    });
    
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

                let message = '';
                const analyzedCount = response.data.analyzed;
                const repairedCount = response.data.repaired;
            
                if (analyzedCount > 0) {
                    if (repairedCount > 0) {
                        message = `✓ Analysis complete. Successfully repaired ${repairedCount} table(s).`;
                    } else {
                        message = '✓ Analysis complete. All tables are in optimal condition; no repairs were needed.';
                    }
                } else {
                    message = 'No tables were found to analyze.';
                }

                $('#wp-opt-state-table-results').addClass('show').html(`<div class="wp-opt-state-success">${message}</div>`).hide().fadeIn(300);
                showToast(message, 'success');
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
                const message = `✓ Optimized ${response.data.optimized} large autoloaded options (found ${response.data.found} total)`;
                $('#wp-opt-state-table-results').addClass('show').html(`<div class="wp-opt-state-success">${message}</div>`).hide().fadeIn(300);
                showToast(message, 'success');
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
                btn.removeClass('loading').prop('disabled', false).text('Optimize Now');
                setTimeout(loadStats, 1500);
            })
            .fail(function(xhr) {
                isProcessing = false;
                if (xhr.status === 429) {
                    showToast('Please wait before optimizing again.', 'error');
                } else {
                    showToast('Optimization failed', 'error');
                }
                btn.removeClass('loading').prop('disabled', false).text('Optimize Now');
            });
        }, false);
    });
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('settings-updated') && urlParams.get('settings-updated') === 'true') {
        showToast('Settings saved successfully!', 'success');
    }
    
    // Initial load
    loadStats();
});