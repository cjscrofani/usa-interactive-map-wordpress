<?php
/**
 * USA Map Settings Manager
 * 
 * Handles all plugin settings using WordPress Options API
 */

if (!defined('ABSPATH')) {
    exit;
}

class USA_Map_Settings {
    
    /**
     * Option name in database
     */
    const OPTION_NAME = 'usa_map_settings';
    
    /**
     * Settings version for migrations
     */
    const SETTINGS_VERSION = '2.0.0';
    
    /**
     * Current settings
     */
    private $settings;
    
    /**
     * Default settings
     */
    private $defaults;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->set_default_values();
        $this->load_settings();
    }
    
    /**
     * Set default values
     */
    private function set_default_values() {
        $this->defaults = array(
            'version' => self::SETTINGS_VERSION,
            
            // Post Type Settings
            'post_types' => array('post'),
            
            // Field Mapping
            'state_field' => array(
                'type' => 'meta', // meta, acf, taxonomy, toolset
                'key' => 'state',
                'format' => 'full_name', // full_name or abbreviation
            ),
            
            // Display Fields
            'display_fields' => array(
                'show_title' => true,
                'show_excerpt' => true,
                'show_featured_image' => true,
                'excerpt_length' => 20,
                'image_size' => 'thumbnail',
                'custom_fields' => array(),
            ),
            
            // Display Options
            'display' => array(
                'mode' => 'tooltip', // tooltip, sidebar, modal, below
                'results_per_page' => 10,
                'show_count_badges' => true,
                'enable_search' => false,
                'animation_speed' => 300,
                'tooltip_position' => 'auto', // auto, top, bottom, left, right
            ),
            
            // Colors
            'colors' => array(
                'state_default' => '#4c57a6',
                'state_hover' => '#af1b3c',
                'state_active' => '#ec2d51',
                'border_color' => '#ffffff',
                'background_color' => '#f1f2f9',
                'tooltip_background' => '#ffffff',
                'tooltip_text' => '#333333',
                'count_badge_bg' => '#ec2d51',
                'count_badge_text' => '#ffffff',
            ),
            
            // Typography
            'typography' => array(
                'enable_google_fonts' => true,
                'heading_font' => 'Montserrat',
                'body_font' => 'Open Sans',
            ),
            
            // Advanced
            'advanced' => array(
                'cache_enabled' => true,
                'cache_duration' => 3600, // 1 hour in seconds
                'custom_css' => '',
                'custom_js' => '',
                'debug_mode' => false,
            ),
            
            // Templates
            'templates' => array(
                'tooltip_template' => 'default',
                'result_template' => 'default',
                'custom_templates_path' => '',
            ),
        );
    }
    
    /**
     * Load settings from database
     */
    private function load_settings() {
        $saved_settings = get_option(self::OPTION_NAME, array());
        
        // Merge with defaults
        $this->settings = $this->parse_args_recursive($saved_settings, $this->defaults);
        
        // Check for version updates
        if (version_compare($this->settings['version'], self::SETTINGS_VERSION, '<')) {
            $this->migrate_settings();
        }
    }
    
    /**
     * Get all settings
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get specific setting
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->settings;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Update settings
     */
    public function update_settings($new_settings) {
        // Merge with existing settings
        $this->settings = $this->parse_args_recursive($new_settings, $this->settings);
        
        // Ensure version is current
        $this->settings['version'] = self::SETTINGS_VERSION;
        
        // Save to database
        $result = update_option(self::OPTION_NAME, $this->settings);
        
        // Clear cache if settings updated
        if ($result) {
            $this->clear_cache();
        }
        
        return $result;
    }
    
    /**
     * Reset to defaults
     */
    public function reset_settings() {
        $this->settings = $this->defaults;
        update_option(self::OPTION_NAME, $this->settings);
        $this->clear_cache();
    }
    
    /**
     * Set defaults (for activation)
     */
    public function set_defaults() {
        if (!get_option(self::OPTION_NAME)) {
            add_option(self::OPTION_NAME, $this->defaults);
        }
    }
    
    /**
     * Get available post types
     */
    public function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $excluded = array('attachment', 'revision', 'nav_menu_item');
        
        $available = array();
        foreach ($post_types as $post_type) {
            if (!in_array($post_type->name, $excluded)) {
                $available[$post_type->name] = $post_type->label;
            }
        }
        
        return $available;
    }
    
    /**
     * Get available meta fields for selected post types
     */
    public function get_available_meta_fields() {
        global $wpdb;
        
        $post_types = $this->settings['post_types'];
        if (empty($post_types)) {
            return array();
        }
        
        // Build placeholders for post types
        $placeholders = array_fill(0, count($post_types), '%s');
        $placeholder_string = implode(',', $placeholders);
        
        // Query to get distinct meta keys
        $query = $wpdb->prepare(
            "SELECT DISTINCT pm.meta_key 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE p.post_type IN ($placeholder_string)
             AND pm.meta_key NOT LIKE '\_%'
             ORDER BY pm.meta_key",
            $post_types
        );
        
        $meta_keys = $wpdb->get_col($query);
        
        $fields = array();
        foreach ($meta_keys as $key) {
            $fields[$key] = $this->humanize_field_name($key);
        }
        
        return $fields;
    }
    
    /**
     * Get available taxonomies for selected post types
     */
    public function get_available_taxonomies() {
        $post_types = $this->settings['post_types'];
        if (empty($post_types)) {
            return array();
        }
        
        $taxonomies = array();
        foreach ($post_types as $post_type) {
            $post_type_taxonomies = get_object_taxonomies($post_type, 'objects');
            foreach ($post_type_taxonomies as $tax) {
                if ($tax->public) {
                    $taxonomies[$tax->name] = $tax->label;
                }
            }
        }
        
        return $taxonomies;
    }
    
    /**
     * Detect field management plugins
     */
    public function detect_field_plugins() {
        $detected = array();
        
        // ACF
        if (class_exists('ACF')) {
            $detected['acf'] = 'Advanced Custom Fields';
        }
        
        // Toolset
        if (defined('TYPES_VERSION')) {
            $detected['toolset'] = 'Toolset Types';
        }
        
        // Pods
        if (function_exists('pods')) {
            $detected['pods'] = 'Pods';
        }
        
        // Custom Field Suite
        if (class_exists('CFS')) {
            $detected['cfs'] = 'Custom Field Suite';
        }
        
        // Meta Box
        if (defined('RWMB_VER')) {
            $detected['metabox'] = 'Meta Box';
        }
        
        return $detected;
    }
    
    /**
     * Get ACF fields if available
     */
    public function get_acf_fields() {
        if (!class_exists('ACF')) {
            return array();
        }
        
        $fields = array();
        $post_types = $this->settings['post_types'];
        
        // Get all field groups
        $field_groups = acf_get_field_groups();
        
        foreach ($field_groups as $group) {
            // Check if group applies to our post types
            $applicable = false;
            foreach ($group['location'] as $location_group) {
                foreach ($location_group as $rule) {
                    if ($rule['param'] == 'post_type' && in_array($rule['value'], $post_types)) {
                        $applicable = true;
                        break 2;
                    }
                }
            }
            
            if ($applicable) {
                $group_fields = acf_get_fields($group['key']);
                foreach ($group_fields as $field) {
                    $fields[$field['name']] = $field['label'] . ' (ACF)';
                }
            }
        }
        
        return $fields;
    }
    
    /**
     * Clear cache
     */
    private function clear_cache() {
        global $wpdb;
        
        // Clear transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_usa_map_%' 
             OR option_name LIKE '_transient_timeout_usa_map_%'"
        );
        
        // Trigger action for external cache plugins
        do_action('usa_map_cache_cleared');
    }
    
    /**
     * Migrate settings for version updates
     */
    private function migrate_settings() {
        // Example migration from 1.x to 2.0
        if (!isset($this->settings['version'])) {
            // Migrate old settings structure
            if (isset($this->settings['ml_state'])) {
                $this->settings['state_field']['key'] = 'ml_state';
                unset($this->settings['ml_state']);
            }
        }
        
        // Update version
        $this->settings['version'] = self::SETTINGS_VERSION;
        
        // Save migrated settings
        update_option(self::OPTION_NAME, $this->settings);
    }
    
    /**
     * Recursive array merge that preserves numeric keys
     */
    private function parse_args_recursive($args, $defaults) {
        $args = (array) $args;
        $defaults = (array) $defaults;
        $result = $defaults;
        
        foreach ($args as $k => &$v) {
            if (is_array($v) && isset($result[$k])) {
                $result[$k] = $this->parse_args_recursive($v, $result[$k]);
            } else {
                $result[$k] = $v;
            }
        }
        
        return $result;
    }
    
    /**
     * Convert field name to human readable
     */
    private function humanize_field_name($field_name) {
        $field_name = str_replace(array('_', '-'), ' ', $field_name);
        $field_name = ucwords($field_name);
        return $field_name;
    }
    
    /**
     * Export settings
     */
    public function export_settings() {
        return json_encode($this->settings, JSON_PRETTY_PRINT);
    }
    
    /**
     * Import settings
     */
    public function import_settings($json_settings) {
        $imported = json_decode($json_settings, true);
        
        if (!$imported || !is_array($imported)) {
            return false;
        }
        
        // Validate structure
        if (!isset($imported['version'])) {
            return false;
        }
        
        // Update settings
        return $this->update_settings($imported);
    }
}
