jQuery(document).ready(function($) {
    
    // Initialize UI
    initializeUI();
    
    function initializeUI() {
        // Set up toggle switches
        $('#enable-date-filter').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('.pie-date-controls').toggle(isEnabled);
            $('#date_from, #date_to').prop('disabled', !isEnabled);
            if (!isEnabled) {
                $('#date_from, #date_to').val('');
            }
            updateLiveCount();
        });
        
        $('#enable-taxonomy-filter').on('change', function() {
            const isEnabled = $(this).is(':checked');
            $('.pie-taxonomy-controls').toggle(isEnabled);
            $('#product_categories, #product_tags').prop('disabled', !isEnabled);
            if (!isEnabled) {
                $('#product_categories, #product_tags').val(null).trigger('change');
            }
            updateLiveCount();
        });
        
        // Date presets
        $('.pie-date-preset').on('click', function() {
            const days = $(this).data('days');
            const today = new Date();
            const fromDate = new Date(today.getTime() - (days * 24 * 60 * 60 * 1000));
            
            $('#date_from').val(formatDate(fromDate));
            $('#date_to').val(formatDate(today));
            updateLiveCount();
        });
        
        // Quick actions
        $('.pie-btn-select-all').on('click', function() {
            $('input[type="checkbox"]').prop('checked', true);
            updateLiveCount();
        });
        
        $('.pie-btn-select-none').on('click', function() {
            $('input[type="checkbox"]').prop('checked', false);
            updateLiveCount();
        });
        
        $('.pie-btn-reset').on('click', function() {
            // Reset to default state
            $('input[name="product_status[]"][value="publish"]').prop('checked', true);
            $('input[name="product_status[]"]').not('[value="publish"]').prop('checked', false);
            $('input[name="product_types[]"]').prop('checked', true);
            $('input[name="stock_status[]"]').prop('checked', true);
            $('#enable-date-filter, #enable-taxonomy-filter').prop('checked', false).trigger('change');
            $('input[name^="include_"]').prop('checked', true);
            $('input[name="export_format"][value="json"]').prop('checked', true);
            updateLiveCount();
        });
        
        // Live count updates
        $('input[type="checkbox"], input[type="radio"], select').on('change', updateLiveCount);
        $('input[type="date"]').on('change', updateLiveCount);
        
        // Close preview
        /*
        $('.pie-preview-close').on('click', function() {
            $('#preview-results').hide();
        });
        */
        
        // Help button
        $('.pie-btn-help').on('click', function() {
            showHelpDialog();
        });
        
        // Initialize Select2 if available
        if ($.fn.select2) {
            $('.enhanced-select').select2({
                width: '100%',
                placeholder: 'Choose options...',
                allowClear: true
            });
        }
        
        // Initial count update
        updateLiveCount();
    }
    
    function formatDate(date) {
        return date.getFullYear() + '-' + 
               String(date.getMonth() + 1).padStart(2, '0') + '-' + 
               String(date.getDate()).padStart(2, '0');
    }
    
    let liveCountTimeout;
    
    function updateLiveCount() {
        // Simple validation for export button
        const hasStatus = $('input[name="product_status[]"]:checked').length > 0;
        const hasType = $('input[name="product_types[]"]:checked').length > 0;
        
        $('#export-btn').prop('disabled', !(hasStatus && hasType));
    }
    
    function showHelpDialog() {
        const helpContent = `
            <div style="max-width: 500px; line-height: 1.6;">
                <h3>Export Help</h3>
                <p><strong>Basic Filters:</strong> Choose which product statuses and types to include in your export.</p>
                <p><strong>Date Range:</strong> Toggle on to filter products by their creation date. Use the preset buttons for quick selections.</p>
                <p><strong>Categories & Tags:</strong> Toggle on to export only products from specific categories or with specific tags.</p>
                <p><strong>Export Options:</strong> Choose what data to include and the file format. JSON is recommended for re-importing.</p>
                <p><strong>Batch Processing:</strong> Large exports are processed in batches of 5 products to prevent timeouts.</p>
            </div>
        `;
        
        // Simple modal implementation
        const modal = $('<div class="pie-modal-overlay"></div>');
        const modalContent = $('<div class="pie-modal-content"></div>').html(helpContent);
        const closeBtn = $('<button class="pie-modal-close">×</button>');
        
        modalContent.prepend(closeBtn);
        modal.append(modalContent);
        $('body').append(modal);
        
        // Add modal styles
        if (!$('#pie-modal-styles').length) {
            $('head').append(`
                <style id="pie-modal-styles">
                    .pie-modal-overlay {
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0,0,0,0.7);
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        z-index: 999999;
                    }
                    .pie-modal-content {
                        background: white;
                        padding: 2rem;
                        border-radius: 8px;
                        position: relative;
                        max-height: 80vh;
                        overflow-y: auto;
                    }
                    .pie-modal-close {
                        position: absolute;
                        top: 1rem;
                        right: 1rem;
                        background: none;
                        border: none;
                        font-size: 1.5rem;
                        cursor: pointer;
                        color: #666;
                    }
                </style>
            `);
        }
        
        // Close modal functionality
        closeBtn.on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    // Preview functionality removed
    /*
    $('#preview-btn').on('click', function() {
        // ... code removed ...
    });
    */
    
    // Export functionality
    $('#export-btn').on('click', function() {
        const formData = getFormData();
        formData.append('action', 'pie_init_export');
        
        // Debug: Log what's being sent
        console.log('Export FormData contents:');
        for (const [key, value] of formData.entries()) {
            console.log(key + ': ' + value);
        }
        
        // Reset UI
        $('#export-progress').show();
        $('#export-result').hide();
        $('#export-btn').prop('disabled', true).text('Initializing...');
        updateProgressBar('#export-progress', 0);
        $('#export-status').text('Initializing export...');
        
        // Step 1: Initialize Export
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const totalProducts = response.data.total;
                    const batchSize = response.data.batch_size;
                    
                    if (totalProducts === 0) {
                        $('#export-status').text('No products found to export.');
                        $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
                        return;
                    }
                    
                    $('#export-status').text('Starting export of ' + totalProducts + ' products...');
                    processBatch(1, totalProducts);
                } else {
                    let errorMessage = 'Initialization failed: ';
                    if (typeof response.data === 'string') {
                        errorMessage += response.data;
                    } else if (response.data && response.data.message) {
                        errorMessage += response.data.message;
                    } else if (response.data && response.data.debug) {
                        errorMessage += 'Debug info available in console.';
                        console.log('Export Debug Info:', response.data.debug);
                    } else {
                        errorMessage += 'Unknown error';
                    }
                    $('#export-status').text(errorMessage);
                    $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
                }
            },
            error: function(xhr, status, error) {
                let errorMessage = 'Export failed: ' + error;
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response.data && typeof response.data === 'string') {
                        errorMessage = 'Export failed: ' + response.data;
                    } else if (response.data && response.data.message) {
                        errorMessage = 'Export failed: ' + response.data.message;
                    }
                } catch (e) {
                    // Use default error message
                }
                $('#export-status').text(errorMessage);
                $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
            }
        });
    });
    
    function processBatch(page, totalProducts) {
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: {
                action: 'pie_process_export_batch',
                nonce: productIE.nonce,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const percentage = Math.min(100, Math.round((data.processed_count / totalProducts) * 100));
                    
                    updateProgressBar('#export-progress', percentage);
                    $('#export-status').text('Processed ' + data.processed_count + ' of ' + totalProducts + ' products (' + percentage + '%)');
                    
                    if (!data.done) {
                        // Process next batch
                        processBatch(data.next_page, totalProducts);
                    } else {
                        // Finish export
                        finishExport();
                    }
                } else {
                    let errorMsg = 'Batch processing failed: ' + response.data;
                    if (response.data && response.data.errors && response.data.errors.length > 0) {
                        errorMsg += '\\n\\nErrors:\\n' + response.data.errors.join('\\n');
                    }
                    console.error('Batch processing error:', response);
                    $('#export-status').text(errorMsg);
                    $('#export-btn').prop('disabled', false).html('<span class=\"dashicons dashicons-download\"></span> Export Products');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                let errorMsg = 'Batch processing error: ' + error;
                if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                    errorMsg += '\\n\\nServer response: ' + xhr.responseText.substring(0, 200);
                }
                $('#export-status').text(errorMsg);
                $('#export-btn').prop('disabled', false).html('<span class=\"dashicons dashicons-download\"></span> Export Products');
            }
        });
    }
    
    function finishExport() {
        $('#export-status').text('Finalizing export file...');
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: {
                action: 'pie_finish_export',
                nonce: productIE.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateProgressBar('#export-progress', 100);
                    $('#export-status').text('Export completed successfully!');
                    
                    // Trigger automatic download
                    const downloadUrl = response.data.download_url;
                    const filename = downloadUrl.split('/').pop();
                    
                    // Create temporary download link
                    const tempLink = document.createElement('a');
                    tempLink.href = downloadUrl;
                    tempLink.download = filename;
                    tempLink.style.display = 'none';
                    document.body.appendChild(tempLink);
                    tempLink.click();
                    document.body.removeChild(tempLink);
                    
                    setTimeout(function() {
                        $('#export-progress').hide();
                        $('#export-result').show();
                        $('#export-message').html('Export completed successfully!<br><strong>File:</strong> ' + filename);
                        $('#download-link').attr('href', downloadUrl).attr('download', filename).show();
                        $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
                        
                        // Show success notice
                        showNotice('success', 'Export completed! File has been downloaded automatically.');
                    }, 1000);
                } else {
                    $('#export-status').text('Finalization failed: ' + response.data);
                    $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
                }
            },
            error: function(xhr, status, error) {
                $('#export-status').text('Finalization error: ' + error);
                $('#export-btn').prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Export Products');
            }
        });
    }
    
    // Import functionality
    $('#import-btn').on('click', function() {
        const form = $('#import-form')[0];
        const formData = new FormData(form);
        formData.append('action', 'pie_init_import');
        formData.append('nonce', productIE.nonce);
        
        if (!$('#import_file').val()) {
            alert('Please select a file to import');
            return;
        }
        
        $('#import-progress').show();
        $('#import-result').hide();
        $('#import-btn').prop('disabled', true).text('Initializing Import...');
        
        updateProgressBar('#import-progress', 0);
        $('#import-status').text('Initializing import...');
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const importData = response.data;
                    $('#import-status').text('Starting import of ' + importData.total + ' products...');
                    
                    // Start batch processing
                    processImportBatch(1, importData.total, importData.filename, importData.batch_size, getImportOptions());
                } else {
                    $('#import-status').text('Import initialization failed: ' + response.data);
                    $('#import-btn').prop('disabled', false).text('Import Products');
                }
            },
            error: function(xhr, status, error) {
                console.error('Import initialization error:', {xhr: xhr, status: status, error: error});
                let errorMsg = 'Import initialization failed: ' + error;
                if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                    errorMsg += '\n\nServer response: ' + xhr.responseText.substring(0, 200);
                }
                $('#import-status').text(errorMsg);
                $('#import-btn').prop('disabled', false).text('Import Products');
            }
        });
    });
    
    function getImportOptions() {
        const options = {
            update_existing: $('#import-form input[name="update_existing"]').is(':checked') ? '1' : '0',
            skip_images: $('#import-form input[name="skip_images"]').is(':checked') ? '1' : '0',
            preserve_ids: $('#import-form input[name="preserve_ids"]').is(':checked') ? '1' : '0',
            import_status: $('#import-form input[name="import_status"]').is(':checked') ? 'draft' : 'publish'
        };
        console.log('Import Options:', options);
        return options;
    }
    
    function processImportBatch(page, totalProducts, filename, batchSize, options) {
        const formData = new FormData();
        formData.append('action', 'pie_process_import_batch');
        formData.append('nonce', productIE.nonce);
        formData.append('page', page);
        formData.append('filename', filename);
        formData.append('batch_size', batchSize);
        formData.append('update_existing', options.update_existing);
        formData.append('skip_images', options.skip_images);
        formData.append('preserve_ids', options.preserve_ids);
        formData.append('import_status', options.import_status);
        console.log('Sending import_status to server:', options.import_status);
        
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    const percentage = data.percentage;
                    const isComplete = data.is_complete || percentage >= 100;
                    
                    updateProgressBar('#import-progress', percentage);
                    
                    let statusText = 'Processing batch ' + page + '... (' + percentage + '%)';
                    if (data.results) {
                        statusText += ' | Imported: ' + data.results.imported + 
                                    ' | Updated: ' + data.results.updated + 
                                    ' | Failed: ' + data.results.failed;
                    }
                    if (data.processed && data.total) {
                        statusText += ' | Progress: ' + data.processed + '/' + data.total;
                    }
                    $('#import-status').text(statusText);
                    
                    if (!isComplete && percentage < 100) {
                        // Process next batch
                        processImportBatch(page + 1, totalProducts, filename, batchSize, options);
                    } else {
                        // Import complete
                        finishImport(data.results || {imported: 0, updated: 0, failed: 0, errors: []});
                    }
                } else {
                    let errorMsg = 'Import batch processing failed: ' + response.data;
                    console.error('Import batch processing error:', response);
                    $('#import-status').text(errorMsg);
                    $('#import-btn').prop('disabled', false).text('Import Products');
                }
            },
            error: function(xhr, status, error) {
                console.error('Import AJAX Error:', {xhr: xhr, status: status, error: error});
                let errorMsg = 'Import batch processing error: ' + error;
                if (xhr.responseText) {
                    console.error('Response text:', xhr.responseText);
                    errorMsg += '\n\nServer response: ' + xhr.responseText.substring(0, 200);
                }
                $('#import-status').text(errorMsg);
                $('#import-btn').prop('disabled', false).text('Import Products');
            }
        });
    }
    
    function finishImport(results) {
        updateProgressBar('#import-progress', 100);
        $('#import-status').text('Import completed successfully!');
        
        setTimeout(function() {
            $('#import-progress').hide();
            $('#import-result').show();
            
            let message = 'Import completed successfully!<br><br>';
            message += '<strong>Results:</strong><br>';
            message += 'Imported: ' + results.imported + ' products<br>';
            message += 'Updated: ' + results.updated + ' products<br>';
            
            if (results.failed > 0) {
                message += 'Failed: ' + results.failed + ' products<br>';
                
                if (results.errors && results.errors.length > 0) {
                    message += '<br><strong>Errors:</strong><br>';
                    message += results.errors.join('<br>');
                }
            }
            
            $('#import-message').html(message);
            $('#import-btn').prop('disabled', false).text('Import Products');
            
            // Show success notice
            showNotice('success', 'Import completed! ' + results.imported + ' imported, ' + results.updated + ' updated.');
        }, 1000);
    }
    
    // Helper function to get form data
    function getFormData() {
        const formData = new FormData();
        formData.append('nonce', productIE.nonce);
        
        // Get checkbox values for status
        const statuses = $('input[name="product_status[]"]:checked');
        if (statuses.length > 0) {
            statuses.each(function() {
                formData.append('product_status[]', $(this).val());
            });
        } else {
            formData.append('product_status', '');
        }
        
        // Get checkbox values for types
        const types = $('input[name="product_types[]"]:checked');
        if (types.length > 0) {
            types.each(function() {
                formData.append('product_types[]', $(this).val());
            });
        } else {
            formData.append('product_types', '');
        }
        
        // Get checkbox values for stock status
        const stocks = $('input[name="stock_status[]"]:checked');
        if (stocks.length > 0) {
            stocks.each(function() {
                formData.append('stock_status[]', $(this).val());
            });
        } else {
            formData.append('stock_status', '');
        }
        
        // Get date values only if date filter is enabled
        if ($('#enable-date-filter').is(':checked')) {
            formData.append('date_from', $('#date_from').val());
            formData.append('date_to', $('#date_to').val());
        } else {
            formData.append('date_from', '');
            formData.append('date_to', '');
        }
        
        // Get select values only if taxonomy filter is enabled
        if ($('#enable-taxonomy-filter').is(':checked')) {
            const categories = $('#product_categories').val();
            if (categories) {
                categories.forEach(cat => formData.append('product_categories[]', cat));
            }
            
            const tags = $('#product_tags').val();
            if (tags) {
                tags.forEach(tag => formData.append('product_tags[]', tag));
            }
        }
        
        const shippingClasses = $('#shipping_classes').val();
        if (shippingClasses) {
            shippingClasses.forEach(cls => formData.append('shipping_classes[]', cls));
        }
        
        // Get export options
        $('input[name^="include_"]:checked').each(function() {
            formData.append($(this).attr('name'), $(this).val());
        });
        
        // Get export format
        const selectedFormat = $('input[name="export_format"]:checked').val() || 'json';
        formData.append('export_format', selectedFormat);
        
        return formData;
    }
    
    function updateProgressBar(selector, percentage) {
        const $container = $(selector);
        $container.find('.progress-fill, .pie-progress-fill').css('width', percentage + '%');
        $container.find('.pie-progress-text').text(percentage + '%');
    }
    
    function showNotice(type, message) {
        // Remove existing notices
        $('.pie-notice').remove();
        
        const noticeClass = type === 'error' ? 'notice-error' : 'notice-success';
        const iconClass = type === 'error' ? 'dashicons-warning' : 'dashicons-yes';
        
        const notice = $(`
            <div class="notice ${noticeClass} pie-notice is-dismissible" style="display: none; margin: 1rem 0; position: relative; padding-left: 3rem;">
                <span class="dashicons ${iconClass}" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);"></span>
                <p style="margin: 0.5rem 0;">${message}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Dismiss this notice.</span>
                </button>
            </div>
        `);
        
        $('.pie-header').after(notice);
        notice.slideDown();
        
        // Auto dismiss after 5 seconds
        setTimeout(function() {
            notice.slideUp(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual dismiss
        notice.find('.notice-dismiss').on('click', function() {
            notice.slideUp(function() {
                $(this).remove();
            });
        });
    }
    
    // Debug log functionality
    $('#view-logs-btn').on('click', function() {
        $(this).prop('disabled', true).text('Loading...');
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: {
                action: 'pie_view_logs',
                nonce: productIE.nonce
            },
            success: function(response) {
                if (response.success) {
                    showLogModal(response.data.logs, response.data.log_file);
                } else {
                    alert('Failed to load logs: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to load logs: ' + error);
            },
            complete: function() {
                $('#view-logs-btn').prop('disabled', false).html('<span class="dashicons dashicons-text-page"></span> View Debug Logs');
            }
        });
    });
    
    $('#clear-logs-btn').on('click', function() {
        if (!confirm('Are you sure you want to clear all debug logs?')) {
            return;
        }
        
        $(this).prop('disabled', true).text('Clearing...');
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: {
                action: 'pie_clear_logs',
                nonce: productIE.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('Logs cleared successfully');
                } else {
                    alert('Failed to clear logs: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to clear logs: ' + error);
            },
            complete: function() {
                $('#clear-logs-btn').prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear Logs');
            }
        });
    });
    
    function showLogModal(logs, logFile) {
        const modal = $(`
            <div class="pie-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                <div class="pie-modal-content" style="background: white; padding: 20px; border-radius: 8px; max-width: 90%; max-height: 90%; overflow: hidden; display: flex; flex-direction: column;">
                    <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #ddd; padding-bottom: 15px;">
                        <h3 style="margin: 0; flex-grow: 1;">Debug Logs</h3>
                        <span style="font-size: 12px; color: #666; margin-right: 15px;">File: ${logFile}</span>
                        <button class="pie-modal-close" style="background: none; border: none; font-size: 20px; cursor: pointer; padding: 5px;">&times;</button>
                    </div>
                    <div style="flex-grow: 1; overflow: auto;">
                        <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; font-size: 11px; line-height: 1.4; margin: 0; white-space: pre-wrap; word-wrap: break-word;">${logs || 'No logs found'}</pre>
                    </div>
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        modal.find('.pie-modal-close').on('click', function() {
            modal.remove();
        });
        
        modal.on('click', function(e) {
            if (e.target === this) {
                modal.remove();
            }
        });
    }
    
    // Initialize Select2 if available
    if ($.fn.select2) {
        $('.enhanced-select').select2({
            width: '100%'
        });
    }
    
    // Enhanced Import functionality for ZIP files
    initializeEnhancedImport();
    
    function initializeEnhancedImport() {
        const $uploadArea = $('#upload-area');
        const $fileInput = $('#import_file');
        const $fileInfo = $('#file-info');
        const $importBtn = $('#import-btn');
        const $analyzeBtn = $('#analyze-btn');
        
        if ($uploadArea.length === 0) return; // Not on import page
        
        // File upload handling
        $uploadArea.on('click', function() {
            $fileInput.click();
        });
        
        $uploadArea.on('dragover', function(e) {
            e.preventDefault();
            $(this).addClass('drag-over');
        });
        
        $uploadArea.on('dragleave', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
        });
        
        $uploadArea.on('drop', function(e) {
            e.preventDefault();
            $(this).removeClass('drag-over');
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                $fileInput[0].files = files;
                handleFileSelection();
            }
        });
        
        $fileInput.on('change', handleFileSelection);
        
        function handleFileSelection() {
            const file = $fileInput[0].files[0];
            if (file) {
                const fileName = file.name;
                const fileSize = formatFileSize(file.size);
                const fileType = file.type || 'Unknown';
                
                $('#file-name').text(fileName);
                $('#file-size').text(fileSize);
                $('#file-type').text(fileType);
                
                $fileInfo.show();
                $importBtn.prop('disabled', false);
                $analyzeBtn.prop('disabled', false);
                
                // Validate file type
                const extension = fileName.split('.').pop().toLowerCase();
                if (!['zip', 'json'].includes(extension)) {
                    showImportError('Invalid file type. Please select a ZIP or JSON file.');
                    $importBtn.prop('disabled', true);
                    $analyzeBtn.prop('disabled', true);
                }
            }
        }
        
        $('#remove-file').on('click', function() {
            $fileInput.val('');
            $fileInfo.hide();
            $importBtn.prop('disabled', true);
            $analyzeBtn.prop('disabled', true);
        });
        
        // Analyze import file
        $analyzeBtn.on('click', function() {
            if (!$fileInput.val()) {
                showImportError('Please select a file to analyze');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'pie_analyze_import');
            formData.append('nonce', productIE.nonce);
            formData.append('import_file', $fileInput[0].files[0]);
            
            $analyzeBtn.prop('disabled', true).text('Analyzing...');
            
            $.ajax({
                url: productIE.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showAnalysisResults(response.data);
                    } else {
                        showImportError('Analysis failed: ' + response.data);
                    }
                },
                error: function() {
                    showImportError('Analysis request failed');
                },
                complete: function() {
                    $analyzeBtn.prop('disabled', false).text('Analyze File');
                }
            });
        });
        
        // Override the existing import button handler for ZIP support
        $importBtn.off('click').on('click', function() {
            if (!$fileInput.val()) {
                showImportError('Please select a file to import');
                return;
            }
            
            const importMode = $('input[name="import_mode"]:checked').val();
            
            if (importMode === 'preview') {
                // Just analyze for preview mode
                $analyzeBtn.click();
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'pie_process_zip_import');
            formData.append('nonce', productIE.nonce);
            formData.append('import_file', $fileInput[0].files[0]);
            
            // Add import options
            formData.append('update_existing', $('#update_existing').is(':checked') ? '1' : '');
            formData.append('skip_images', $('#skip_images').is(':checked') ? '1' : '');
            formData.append('preserve_ids', $('#preserve_ids').is(':checked') ? '1' : '');
            formData.append('dedupe_images', $('#dedupe_images').is(':checked') ? '1' : '');
            formData.append('optimize_images', $('#optimize_images').is(':checked') ? '1' : '');
            formData.append('import_mode', importMode);
            formData.append('import_status', $('input[name="import_status"]').is(':checked') ? 'draft' : 'publish');
            
            console.log('ZIP Import - import_status:', $('input[name="import_status"]').is(':checked') ? 'draft' : 'publish');
            
            startEnhancedImport(formData);
        });
        
        // New import button
        $('#new-import-btn').on('click', function() {
            resetImportForm();
        });
    }
    
    function startEnhancedImport(formData) {
        $('#import-progress').show();
        $('#import-result').hide();
        $('#import-analysis').hide();
        
        const $importBtn = $('#import-btn');
        $importBtn.prop('disabled', true).text('Importing...');
        
        updateImportProgress(0, 'Starting import...');
        
        $.ajax({
            url: productIE.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showImportResults(response.data);
                } else {
                    showImportError('Import failed: ' + response.data);
                }
            },
            error: function() {
                showImportError('Import request failed');
            },
            complete: function() {
                $importBtn.prop('disabled', false).text('Start Import');
            }
        });
    }
    
    function showAnalysisResults(data) {
        const $analysis = $('#import-analysis');
        const $content = $('#analysis-content');
        
        let html = '<div class="pie-analysis-results">';
        html += '<h4>File Analysis Results</h4>';
        html += '<div class="pie-analysis-grid">';
        
        html += '<div class="pie-analysis-item">';
        html += '<span class="pie-analysis-label">File Name:</span>';
        html += '<span class="pie-analysis-value">' + data.file_name + '</span>';
        html += '</div>';
        
        html += '<div class="pie-analysis-item">';
        html += '<span class="pie-analysis-label">File Size:</span>';
        html += '<span class="pie-analysis-value">' + formatFileSize(data.file_size) + '</span>';
        html += '</div>';
        
        html += '<div class="pie-analysis-item">';
        html += '<span class="pie-analysis-label">File Type:</span>';
        html += '<span class="pie-analysis-value">' + data.file_type.toUpperCase() + '</span>';
        html += '</div>';
        
        if (data.estimated_products) {
            html += '<div class="pie-analysis-item">';
            html += '<span class="pie-analysis-label">Estimated Products:</span>';
            html += '<span class="pie-analysis-value">' + data.estimated_products + '</span>';
            html += '</div>';
        }
        
        if (data.contains_images) {
            html += '<div class="pie-analysis-item">';
            html += '<span class="pie-analysis-label">Contains Images:</span>';
            html += '<span class="pie-analysis-value">Yes (' + (data.image_files ? data.image_files.length : 0) + ' files)</span>';
            html += '</div>';
        }
        
        if (data.export_info) {
            html += '<div class="pie-analysis-item">';
            html += '<span class="pie-analysis-label">Export Version:</span>';
            html += '<span class="pie-analysis-value">' + (data.export_info.version || 'Unknown') + '</span>';
            html += '</div>';
            
            if (data.export_info.export_date) {
                html += '<div class="pie-analysis-item">';
                html += '<span class="pie-analysis-label">Export Date:</span>';
                html += '<span class="pie-analysis-value">' + data.export_info.export_date + '</span>';
                html += '</div>';
            }
            
            if (data.export_info.site_url) {
                html += '<div class="pie-analysis-item">';
                html += '<span class="pie-analysis-label">Source Site:</span>';
                html += '<span class="pie-analysis-value">' + data.export_info.site_url + '</span>';
                html += '</div>';
            }
        }
        
        html += '</div>';
        html += '</div>';
        
        $content.html(html);
        $analysis.show();
    }
    
    function showImportResults(data) {
        $('#import-progress').hide();
        
        const $result = $('#import-result');
        const $summary = $('#import-summary');
        const $details = $('#import-details');
        
        const result = data.result;
        
        let html = '<div class="pie-result-summary-grid">';
        html += '<div class="pie-result-stat pie-result-success">';
        html += '<span class="pie-result-number">' + result.imported + '</span>';
        html += '<span class="pie-result-label">Products Created</span>';
        html += '</div>';
        
        html += '<div class="pie-result-stat pie-result-update">';
        html += '<span class="pie-result-number">' + result.updated + '</span>';
        html += '<span class="pie-result-label">Products Updated</span>';
        html += '</div>';
        
        html += '<div class="pie-result-stat pie-result-images">';
        html += '<span class="pie-result-number">' + result.imported_images + '</span>';
        html += '<span class="pie-result-label">Images Imported</span>';
        html += '</div>';
        
        html += '<div class="pie-result-stat pie-result-dedup">';
        html += '<span class="pie-result-number">' + result.deduplicated_images + '</span>';
        html += '<span class="pie-result-label">Images Deduplicated</span>';
        html += '</div>';
        
        if (result.skipped > 0) {
            html += '<div class="pie-result-stat pie-result-warning">';
            html += '<span class="pie-result-number">' + result.skipped + '</span>';
            html += '<span class="pie-result-label">Products Skipped</span>';
            html += '</div>';
        }
        
        html += '</div>';
        
        $summary.html(html);
        
        // Show errors if any
        if (result.errors && result.errors.length > 0) {
            let errorHtml = '<div class="pie-import-errors">';
            errorHtml += '<h4>Import Errors</h4>';
            errorHtml += '<div class="pie-error-list">';
            
            result.errors.forEach(function(error) {
                errorHtml += '<div class="pie-error-item">';
                errorHtml += '<strong>' + error.product_name + '</strong> (' + error.product_sku + '): ';
                errorHtml += error.error;
                errorHtml += '</div>';
            });
            
            errorHtml += '</div>';
            errorHtml += '</div>';
            
            $details.html(errorHtml);
        }
        
        $result.show();
    }
    
    function updateImportProgress(percent, status) {
        const $progressFill = $('.pie-progress-fill');
        const $progressText = $('.pie-progress-text');
        const $status = $('#import-status');
        
        $progressFill.css('width', percent + '%');
        $progressText.text(percent + '%');
        $status.text(status);
    }
    
    function resetImportForm() {
        $('#import_file').val('');
        $('#file-info').hide();
        $('#import-btn').prop('disabled', true);
        $('#analyze-btn').prop('disabled', true);
        $('#import-progress').hide();
        $('#import-result').hide();
        $('#import-analysis').hide();
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function showImportError(message) {
        alert('Error: ' + message);
    }
});