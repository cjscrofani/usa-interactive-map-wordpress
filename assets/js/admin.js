/**
 * USA Interactive Map Admin JavaScript
 */

(function($) {
    'use strict';
    
    var USAMapAdmin = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initColorPickers();
            this.loadStateFields();
            this.initCustomFields();
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Post type change
            $('input[name="usa_map_settings[post_types][]"]').on('change', this.handlePostTypeChange.bind(this));
            
            // Field type change
            $('#state_field_type').on('change', this.handleFieldTypeChange.bind(this));
            
            // Save settings (AJAX)
            $('#usa-map-settings-form').on('submit', this.saveSettings.bind(this));
            
            // Reset settings
            $('#reset-settings').on('click', this.resetSettings.bind(this));
            
            // Clear cache
            $('#clear-cache').on('click', this.clearCache.bind(this));
            
            // Export settings
            $('#export-settings').on('click', this.exportSettings.bind(this));
            
            // Import settings
            $('#import-settings').on('click', this.importSettings.bind(this));
            
            // Custom fields
            $('#add-custom-field').on('click', this.addCustomField.bind(this));
            $(document).on('click', '.remove-field', this.removeCustomField);
            $(document).on('click', '.field-key', this.quickAddField.bind(this));
            
            // Tab switching
            $('.nav-tab').on('click', this.handleTabSwitch.bind(this));
            
            // Copy shortcode
            $('.copy-shortcode').on('click', this.copyShortcode.bind(this));
        },
        
        /**
         * Initialize color pickers
         */
        initColorPickers: function() {
            $('.color-picker').wpColorPicker({
                change: function(event, ui) {
                    // Update preview if exists
                    USAMapAdmin.updateColorPreview();
                }
            });
        },
        
        /**
         * Load state fields based on current settings
         */
        loadStateFields: function() {
            var fieldType = $('#state_field_type').val();
            var postTypes = this.getSelectedPostTypes();
            var currentField = $('#state_field_key').data('current-value') || usa_map_admin.current_state_field;
            
            this.fetchFields(fieldType, postTypes, currentField);
        },
        
        /**
         * Handle post type change
         */
        handlePostTypeChange: function() {
            var postTypes = this.getSelectedPostTypes();
            
            if (postTypes.length === 0) {
                this.showNotice('warning', 'Please select at least one post type.');
                return;
            }
            
            // Reload fields
            this.loadStateFields();
        },
        
        /**
         * Handle field type change
         */
        handleFieldTypeChange: function() {
            this.loadStateFields();
        },
        
        /**
         * Get selected post types
         */
        getSelectedPostTypes: function() {
            var postTypes = [];
            $('input[name="usa_map_settings[post_types][]"]:checked').each(function() {
                postTypes.push($(this).val());
            });
            return postTypes;
        },
        
        /**
         * Fetch fields via AJAX
         */
        fetchFields: function(fieldType, postTypes, currentValue) {
            var $container = $('#state_field_selector');
            var $select = $('#state_field_key');
            
            // Show loading
            $select.html('<option value="">Loading...</option>').prop('disabled', true);
            
            $.ajax({
                url: usa_map_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_get_fields',
                    field_type: fieldType,
                    post_types: postTypes,
                    nonce: usa_map_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var fields = response.data;
                        var html = '<option value="">— Select Field —</option>';
                        
                        $.each(fields, function(key, label) {
                            var selected = (key === currentValue) ? ' selected' : '';
                            html += '<option value="' + key + '"' + selected + '>' + label + '</option>';
                        });
                        
                        $select.html(html).prop('disabled', false);
                        
                        // Store current value
                        $select.data('current-value', currentValue);
                    } else {
                        USAMapAdmin.showNotice('error', response.data || 'Failed to load fields.');
                    }
                },
                error: function() {
                    USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                    $select.html('<option value="">Error loading fields</option>').prop('disabled', false);
                }
            });
        },
        
        /**
         * Save settings via AJAX
         */
        saveSettings: function(e) {
            e.preventDefault();
            
            var $form = $(e.target);
            var $submitButton = $form.find('input[type="submit"]');
            var originalText = $submitButton.val();
            
            // Disable button and show loading
            $submitButton.val('Saving...').prop('disabled', true);
            
            $.ajax({
                url: usa_map_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_save_settings',
                    settings: $form.serialize(),
                    nonce: usa_map_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        USAMapAdmin.showNotice('success', response.data.message);
                    } else {
                        USAMapAdmin.showNotice('error', response.data || 'Failed to save settings.');
                    }
                },
                error: function() {
                    USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                },
                complete: function() {
                    $submitButton.val(originalText).prop('disabled', false);
                }
            });
        },
        
        /**
         * Reset settings
         */
        resetSettings: function(e) {
            e.preventDefault();
            
            if (!confirm(usa_map_admin.strings.confirm_reset)) {
                return;
            }
            
            var $button = $(e.target);
            $button.text('Resetting...').prop('disabled', true);
            
            $.ajax({
                url: usa_map_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_reset_settings',
                    nonce: usa_map_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        USAMapAdmin.showNotice('success', response.data.message);
                        if (response.data.redirect) {
                            setTimeout(function() {
                                window.location.href = response.data.redirect;
                            }, 1500);
                        }
                    } else {
                        USAMapAdmin.showNotice('error', response.data || 'Failed to reset settings.');
                    }
                },
                error: function() {
                    USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                },
                complete: function() {
                    $button.text('Reset to Defaults').prop('disabled', false);
                }
            });
        },
        
        /**
         * Clear cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            $button.text('Clearing...').prop('disabled', true);
            
            $.ajax({
                url: usa_map_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_clear_cache',
                    nonce: usa_map_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        USAMapAdmin.showNotice('success', response.data.message);
                    } else {
                        USAMapAdmin.showNotice('error', response.data || 'Failed to clear cache.');
                    }
                },
                error: function() {
                    USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                },
                complete: function() {
                    $button.text('Clear All Cache').prop('disabled', false);
                }
            });
        },
        
        /**
         * Export settings
         */
        exportSettings: function(e) {
            e.preventDefault();
            
            $.ajax({
                url: usa_map_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'usa_map_export_settings',
                    nonce: usa_map_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link
                        var blob = new Blob([response.data.content], {type: 'application/json'});
                        var url = window.URL.createObjectURL(blob);
                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                        
                        USAMapAdmin.showNotice('success', 'Settings exported successfully!');
                    } else {
                        USAMapAdmin.showNotice('error', response.data || 'Failed to export settings.');
                    }
                },
                error: function() {
                    USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                }
            });
        },
        
        /**
         * Import settings
         */
        importSettings: function(e) {
            e.preventDefault();
            
            // Create file input
            var fileInput = $('<input type="file" accept=".json" style="display: none;">');
            
            fileInput.on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var content = e.target.result;
                    
                    $.ajax({
                        url: usa_map_admin.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'usa_map_import_settings',
                            settings_data: content,
                            nonce: usa_map_admin.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                USAMapAdmin.showNotice('success', response.data.message);
                                if (response.data.redirect) {
                                    setTimeout(function() {
                                        window.location.href = response.data.redirect;
                                    }, 1500);
                                }
                            } else {
                                USAMapAdmin.showNotice('error', response.data || 'Failed to import settings.');
                            }
                        },
                        error: function() {
                            USAMapAdmin.showNotice('error', 'Network error. Please try again.');
                        }
                    });
                };
                
                reader.readAsText(file);
            });
            
            fileInput.click();
        },
        
        /**
         * Initialize custom fields
         */
        initCustomFields: function() {
            this.customFieldIndex = $('#custom-fields-list tr.custom-field-row').length;
        },
        
        /**
         * Add custom field row
         */
        addCustomField: function(e) {
            e.preventDefault();
            
            var index = this.customFieldIndex++;
            var html = `
                <tr class="custom-field-row">
                    <td>
                        <input type="text" 
                               name="usa_map_settings[display_fields][custom_fields][${index}][key]" 
                               value="" 
                               placeholder="field_key" />
                    </td>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[display_fields][custom_fields][${index}][label]" 
                               value="" 
                               placeholder="Field Label" />
                    </td>
                    <td>
                        <select name="usa_map_settings[display_fields][custom_fields][${index}][type]">
                            <option value="text">Text</option>
                            <option value="url">URL</option>
                            <option value="email">Email</option>
                            <option value="image">Image</option>
                        </select>
                    </td>
                    <td>
                        <button type="button" class="button button-small remove-field">Remove</button>
                    </td>
                </tr>
            `;
            
            // Remove "no fields" message if exists
            $('#custom-fields-list .no-fields').remove();
            
            // Add new row
            $('#custom-fields-list').append(html);
        },
        
        /**
         * Remove custom field row
         */
        removeCustomField: function(e) {
            e.preventDefault();
            $(this).closest('tr').fadeOut(300, function() {
                $(this).remove();
                
                // Show "no fields" message if empty
                if ($('#custom-fields-list tr.custom-field-row').length === 0) {
                    $('#custom-fields-list').html('<tr class="no-fields"><td colspan="4">No custom fields added yet.</td></tr>');
                }
            });
        },
        
        /**
         * Quick add field from available fields list
         */
        quickAddField: function(e) {
            e.preventDefault();
            
            var fieldKey = $(e.target).data('field-key');
            var fieldLabel = $(e.target).text();
            
            // Add the field
            this.addCustomField(e);
            
            // Fill in the values
            var $lastRow = $('#custom-fields-list tr.custom-field-row').last();
            $lastRow.find('input[name*="[key]"]').val(fieldKey);
            $lastRow.find('input[name*="[label]"]').val(fieldLabel);
            
            // Highlight the row
            $lastRow.css('background-color', '#ffffcc').animate({
                backgroundColor: 'transparent'
            }, 1000);
        },
        
        /**
         * Handle tab switching
         */
        handleTabSwitch: function(e) {
            // Allow default behavior for tab links
            // Just add any additional handling here if needed
        },
        
        /**
         * Copy shortcode to clipboard
         */
        copyShortcode: function(e) {
            e.preventDefault();
            
            var $button = $(e.target);
            var text = $button.data('clipboard-text');
            
            // Create temporary textarea
            var $temp = $('<textarea>');
            $('body').append($temp);
            $temp.val(text).select();
            
            try {
                document.execCommand('copy');
                $button.text('Copied!');
                setTimeout(function() {
                    $button.text('Copy');
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
            }
            
            $temp.remove();
        },
        
        /**
         * Update color preview
         */
        updateColorPreview: function() {
            // This could update a live preview of the map colors
            // For now, just a placeholder
        },
        
        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var $notices = $('#usa-map-notices');
            
            var noticeClass = 'notice-' + type;
            if (type === 'error') {
                noticeClass = 'notice-error';
            } else if (type === 'success') {
                noticeClass = 'notice-success';
            }
            
            var html = `
                <div class="notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">Dismiss this notice.</span>
                    </button>
                </div>
            `;
            
            var $notice = $(html);
            $notices.html($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut();
            }, 5000);
            
            // Handle dismiss button
            $notice.find('.notice-dismiss').on('click', function() {
                $notice.fadeOut();
            });
            
            // Scroll to top to see notice
            $('html, body').animate({
                scrollTop: 0
            }, 300);
        }
    };
    
    // Initialize when ready
    $(document).ready(function() {
        USAMapAdmin.init();
    });
    
})(jQuery);
