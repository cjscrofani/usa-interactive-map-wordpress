<?php
/**
 * USA Map Admin AJAX Handler
 * 
 * Handles AJAX requests in the admin area
 */

if (!defined('ABSPATH')) {
    exit;
}

class USA_Map_Admin_Ajax {
    
    /**
     * Settings manager instance
     */
    private $settings_manager;
    
    /**
     * Constructor
     */
    public function __construct($settings_manager) {
        $this->settings_manager = $settings_manager;
        
        // Register AJAX handlers
        add_action('wp_ajax_usa_map_get_fields', array($this, 'ajax_get_fields'));
        add_action('wp_ajax_usa_map_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_usa_map_reset_settings', array($this, 'ajax_reset_settings'));
        add_action('wp_ajax_usa_map_clear_cache', array($this, 'ajax_clear_cache'));
        add_action('wp_ajax_usa_map_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_usa_map_import_settings', array($this, 'ajax_import_settings'));
    }
    
    /**
     * Get available fields based on selected post types and field type
     */
    public function ajax_get_fields() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $field_type = sanitize_text_field($_POST['field_type']);
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', $_POST['post_types']) : array();
        
        // Update settings temporarily to get correct fields
        $current_settings = $this->settings_manager->get_settings();
        $current_settings['post_types'] = $post_types;
        $this->settings_manager->update_settings($current_settings);
        
        $fields = array();
        
        switch ($field_type) {
            case 'meta':
                $fields = $this->settings_manager->get_available_meta_fields();
                break;
                
            case 'taxonomy':
                $fields = $this->settings_manager->get_available_taxonomies();
                break;
                
            case 'acf':
                if (function_exists('acf')) {
                    $fields = $this->settings_manager->get_acf_fields();
                }
                break;
                
            case 'toolset':
                if (defined('TYPES_VERSION')) {
                    $fields = $this->get_toolset_fields($post_types);
                }
                break;
                
            case 'pods':
                if (function_exists('pods')) {
                    $fields = $this->get_pods_fields($post_types);
                }
                break;
        }
        
        // Include common state field names if not already present
        $common_state_fields = array(
            'state' => 'State',
            'location_state' => 'Location State',
            'address_state' => 'Address State',
            'us_state' => 'US State',
            '_state' => 'State (Hidden)',
        );
        
        foreach ($common_state_fields as $key => $label) {
            if (!isset($fields[$key])) {
                $fields[$key] = $label . ' (Common)';
            }
        }
        
        wp_send_json_success($fields);
    }
    
    /**
     * Save settings via AJAX
     */
    public function ajax_save_settings() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Parse settings from form data
        parse_str($_POST['settings'], $form_data);
        
        if (isset($form_data['usa_map_settings'])) {
            $new_settings = $form_data['usa_map_settings'];
            
            // Handle checkboxes that might not be present
            if (!isset($new_settings['post_types'])) {
                $new_settings['post_types'] = array();
            }
            
            if (!isset($new_settings['display_fields']['show_title'])) {
                $new_settings['display_fields']['show_title'] = false;
            }
            
            if (!isset($new_settings['display_fields']['show_excerpt'])) {
                $new_settings['display_fields']['show_excerpt'] = false;
            }
            
            if (!isset($new_settings['display_fields']['show_featured_image'])) {
                $new_settings['display_fields']['show_featured_image'] = false;
            }
            
            if (!isset($new_settings['display']['show_count_badges'])) {
                $new_settings['display']['show_count_badges'] = false;
            }
            
            if (!isset($new_settings['display']['enable_search'])) {
                $new_settings['display']['enable_search'] = false;
            }
            
            if (!isset($new_settings['typography']['enable_google_fonts'])) {
                $new_settings['typography']['enable_google_fonts'] = false;
            }
            
            if (!isset($new_settings['advanced']['cache_enabled'])) {
                $new_settings['advanced']['cache_enabled'] = false;
            }
            
            if (!isset($new_settings['advanced']['debug_mode'])) {
                $new_settings['advanced']['debug_mode'] = false;
            }
            
            // Update settings
            $result = $this->settings_manager->update_settings($new_settings);
            
            if ($result) {
                wp_send_json_success(array(
                    'message' => __('Settings saved successfully!', 'usa-interactive-map')
                ));
            } else {
                wp_send_json_error(__('Failed to save settings.', 'usa-interactive-map'));
            }
        } else {
            wp_send_json_error(__('Invalid settings data.', 'usa-interactive-map'));
        }
    }
    
    /**
     * Reset settings to defaults
     */
    public function ajax_reset_settings() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $this->settings_manager->reset_settings();
        
        wp_send_json_success(array(
            'message' => __('Settings reset to defaults successfully!', 'usa-interactive-map'),
            'redirect' => admin_url('admin.php?page=usa-interactive-map')
        ));
    }
    
    /**
     * Clear all cache
     */
    public function ajax_clear_cache() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        global $wpdb;
        
        // Clear transients
        $cleared = $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_usa_map_%' 
             OR option_name LIKE '_transient_timeout_usa_map_%'"
        );
        
        wp_send_json_success(array(
            'message' => sprintf(__('Cache cleared successfully! %d items removed.', 'usa-interactive-map'), $cleared / 2)
        ));
    }
    
    /**
     * Export settings
     */
    public function ajax_export_settings() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $settings_json = $this->settings_manager->export_settings();
        
        wp_send_json_success(array(
            'filename' => 'usa-map-settings-' . date('Y-m-d-His') . '.json',
            'content' => $settings_json
        ));
    }
    
    /**
     * Import settings
     */
    public function ajax_import_settings() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_admin_nonce')) {
            wp_send_json_error('Invalid security token');
        }
        
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!isset($_POST['settings_data'])) {
            wp_send_json_error(__('No settings data provided.', 'usa-interactive-map'));
        }
        
        $result = $this->settings_manager->import_settings($_POST['settings_data']);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('Settings imported successfully!', 'usa-interactive-map'),
                'redirect' => admin_url('admin.php?page=usa-interactive-map')
            ));
        } else {
            wp_send_json_error(__('Invalid settings file or format.', 'usa-interactive-map'));
        }
    }
    
    /**
     * Get Toolset fields
     */
    private function get_toolset_fields($post_types) {
        $fields = array();
        
        if (!function_exists('wpcf_admin_fields_get_groups')) {
            return $fields;
        }
        
        $groups = wpcf_admin_fields_get_groups();
        
        foreach ($groups as $group) {
            // Check if group applies to our post types
            $group_post_types = isset($group['post_types']) ? $group['post_types'] : array();
            
            if (array_intersect($post_types, $group_post_types)) {
                $group_fields = wpcf_admin_fields_get_fields_by_group($group['id']);
                
                foreach ($group_fields as $field) {
                    $fields['wpcf-' . $field['slug']] = $field['name'] . ' (Toolset)';
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Get Pods fields
     */
    private function get_pods_fields($post_types) {
        $fields = array();
        
        foreach ($post_types as $post_type) {
            $pod = pods($post_type);
            
            if ($pod) {
                $pod_fields = $pod->fields();
                
                foreach ($pod_fields as $field_name => $field_data) {
                    $fields[$field_name] = $field_data['label'] . ' (Pods)';
                }
            }
        }
        
        return $fields;
    }
}
