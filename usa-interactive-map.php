<?php
/**
 * Plugin Name: USA Interactive Map
 * Plugin URI: https://yourwebsite.com/
 * Description: Display any post type data on an interactive USA map. Fully customizable through admin settings.
 * Version: 2.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: usa-interactive-map
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('USA_MAP_VERSION', '2.0.0');
define('USA_MAP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USA_MAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('USA_MAP_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class USA_Interactive_Map {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * Settings class instance
     */
    private $settings_manager;
    
    /**
     * Display class instance
     */
    private $display_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once USA_MAP_PLUGIN_DIR . 'includes/class-usa-map-settings.php';
        require_once USA_MAP_PLUGIN_DIR . 'includes/class-usa-map-display.php';
        
        if (is_admin()) {
            require_once USA_MAP_PLUGIN_DIR . 'admin/admin-page.php';
            require_once USA_MAP_PLUGIN_DIR . 'admin/admin-ajax.php';
        }
        
        $this->settings_manager = new USA_Map_Settings();
        $this->settings = $this->settings_manager->get_settings();
        $this->display_manager = new USA_Map_Display($this->settings);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(USA_MAP_PLUGIN_BASENAME, array($this, 'activate'));
        register_deactivation_hook(USA_MAP_PLUGIN_BASENAME, array($this, 'deactivate'));
        
        // Init
        add_action('init', array($this, 'init'));
        
        // Scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Shortcodes
        add_shortcode('usa_map', array($this, 'render_map_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_usa_map_get_state_posts', array($this->display_manager, 'ajax_get_state_posts'));
        add_action('wp_ajax_nopriv_usa_map_get_state_posts', array($this->display_manager, 'ajax_get_state_posts'));
        
        // Admin
        if (is_admin()) {
            $admin = new USA_Map_Admin($this->settings_manager);
            $admin_ajax = new USA_Map_Admin_Ajax($this->settings_manager);
        }
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default settings if not exists
        if (!get_option('usa_map_settings')) {
            $this->settings_manager->set_defaults();
        }
        
        // Create upload directory for custom templates
        $upload_dir = wp_upload_dir();
        $template_dir = $upload_dir['basedir'] . '/usa-map-templates';
        if (!file_exists($template_dir)) {
            wp_mkdir_p($template_dir);
        }
        
        // Clear any transients
        $this->clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear transients
        $this->clear_cache();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('usa-interactive-map', false, dirname(USA_MAP_PLUGIN_BASENAME) . '/languages');
        
        // Register custom post statuses or taxonomies if needed
        do_action('usa_map_init');
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        global $post;
        
        // Check if shortcode is present
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'usa_map')) {
            
            // Google Fonts (if enabled in settings)
            if ($this->settings['typography']['enable_google_fonts']) {
                wp_enqueue_style(
                    'usa-map-google-fonts', 
                    'https://fonts.googleapis.com/css2?family=Montserrat:wght@900&family=Open+Sans:wght@400;600&display=swap'
                );
            }
            
            // Plugin styles
            wp_enqueue_style(
                'usa-map-styles', 
                USA_MAP_PLUGIN_URL . 'assets/css/usa-map.css', 
                array(), 
                USA_MAP_VERSION
            );
            
            // Dynamic styles from settings
            $custom_css = $this->generate_dynamic_css();
            wp_add_inline_style('usa-map-styles', $custom_css);
            
            // Plugin scripts
            wp_enqueue_script(
                'usa-map-script', 
                USA_MAP_PLUGIN_URL . 'assets/js/usa-map.js', 
                array('jquery'), 
                USA_MAP_VERSION, 
                true
            );
            
            // Localize script
            wp_localize_script('usa-map-script', 'usa_map_vars', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('usa_map_nonce'),
                'settings' => array(
                    'colors' => $this->settings['colors'],
                    'display_mode' => $this->settings['display']['mode'],
                    'animation_speed' => $this->settings['display']['animation_speed'],
                ),
                'states' => $this->get_states_data(),
                'debug' => defined('WP_DEBUG') && WP_DEBUG,
            ));
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only on our admin page
        if ($hook !== 'toplevel_page_usa-interactive-map') {
            return;
        }
        
        // Admin styles
        wp_enqueue_style(
            'usa-map-admin-styles', 
            USA_MAP_PLUGIN_URL . 'assets/css/admin.css', 
            array('wp-color-picker'), 
            USA_MAP_VERSION
        );
        
        // Admin scripts
        wp_enqueue_script(
            'usa-map-admin-script', 
            USA_MAP_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery', 'wp-color-picker'), 
            USA_MAP_VERSION, 
            true
        );
        
        // Localize admin script
        wp_localize_script('usa-map-admin-script', 'usa_map_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('usa_map_admin_nonce'),
            'strings' => array(
                'save_success' => __('Settings saved successfully!', 'usa-interactive-map'),
                'save_error' => __('Error saving settings. Please try again.', 'usa-interactive-map'),
                'confirm_reset' => __('Are you sure you want to reset all settings to defaults?', 'usa-interactive-map'),
            ),
        ));
    }
    
    /**
     * Render map shortcode
     */
    public function render_map_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'id' => 'usa-map-' . uniqid(),
            'post_types' => '', // Override settings
            'state_field' => '', // Override settings
            'max_width' => '1200px',
            'height' => 'auto',
            'class' => '',
        ), $atts, 'usa_map');
        
        // Allow settings override via shortcode
        $settings = $this->settings;
        
        if (!empty($atts['post_types'])) {
            $settings['post_types'] = array_map('trim', explode(',', $atts['post_types']));
        }
        
        if (!empty($atts['state_field'])) {
            $settings['state_field']['key'] = $atts['state_field'];
        }
        
        // Generate map HTML
        return $this->display_manager->render_map($atts, $settings);
    }
    
    /**
     * Generate dynamic CSS from settings
     */
    private function generate_dynamic_css() {
        $colors = $this->settings['colors'];
        $custom_css = $this->settings['advanced']['custom_css'];
        
        $css = "
            :root {
                --usa-map-state-default: {$colors['state_default']};
                --usa-map-state-hover: {$colors['state_hover']};
                --usa-map-state-active: {$colors['state_active']};
                --usa-map-border: {$colors['border_color']};
                --usa-map-background: {$colors['background_color']};
                --usa-map-tooltip-bg: {$colors['tooltip_background']};
                --usa-map-tooltip-text: {$colors['tooltip_text']};
            }
            
            .usa-map-svg path.state {
                fill: var(--usa-map-state-default) !important;
                stroke: var(--usa-map-border) !important;
            }
            
            .usa-map-svg path.state:hover {
                fill: var(--usa-map-state-hover) !important;
            }
            
            .usa-map-svg path.state.active {
                fill: var(--usa-map-state-active) !important;
            }
            
            .usa-map-wrapper {
                background-color: var(--usa-map-background);
            }
            
            .state-tooltip {
                background-color: var(--usa-map-tooltip-bg);
                color: var(--usa-map-tooltip-text);
            }
        ";
        
        // Add custom CSS if provided
        if (!empty($custom_css)) {
            $css .= "\n/* Custom CSS */\n" . $custom_css;
        }
        
        return $css;
    }
    
    /**
     * Get states data
     */
    private function get_states_data() {
        return array(
            'AL' => array('name' => 'Alabama', 'x' => 690, 'y' => 405),
            'AK' => array('name' => 'Alaska', 'x' => 113, 'y' => 495),
            'AZ' => array('name' => 'Arizona', 'x' => 235, 'y' => 365),
            'AR' => array('name' => 'Arkansas', 'x' => 590, 'y' => 368),
            'CA' => array('name' => 'California', 'x' => 69, 'y' => 285),
            'CO' => array('name' => 'Colorado', 'x' => 365, 'y' => 285),
            'CT' => array('name' => 'Connecticut', 'x' => 875, 'y' => 173),
            'DE' => array('name' => 'Delaware', 'x' => 848, 'y' => 243),
            'DC' => array('name' => 'District of Columbia', 'x' => 820, 'y' => 262),
            'FL' => array('name' => 'Florida', 'x' => 760, 'y' => 485),
            'GA' => array('name' => 'Georgia', 'x' => 720, 'y' => 405),
            'HI' => array('name' => 'Hawaii', 'x' => 305, 'y' => 565),
            'ID' => array('name' => 'Idaho', 'x' => 195, 'y' => 155),
            'IL' => array('name' => 'Illinois', 'x' => 620, 'y' => 280),
            'IN' => array('name' => 'Indiana', 'x' => 665, 'y' => 280),
            'IA' => array('name' => 'Iowa', 'x' => 560, 'y' => 250),
            'KS' => array('name' => 'Kansas', 'x' => 480, 'y' => 315),
            'KY' => array('name' => 'Kentucky', 'x' => 695, 'y' => 330),
            'LA' => array('name' => 'Louisiana', 'x' => 585, 'y' => 460),
            'ME' => array('name' => 'Maine', 'x' => 900, 'y' => 90),
            'MD' => array('name' => 'Maryland', 'x' => 830, 'y' => 260),
            'MA' => array('name' => 'Massachusetts', 'x' => 885, 'y' => 165),
            'MI' => array('name' => 'Michigan', 'x' => 675, 'y' => 200),
            'MN' => array('name' => 'Minnesota', 'x' => 545, 'y' => 150),
            'MS' => array('name' => 'Mississippi', 'x' => 625, 'y' => 425),
            'MO' => array('name' => 'Missouri', 'x' => 575, 'y' => 320),
            'MT' => array('name' => 'Montana', 'x' => 300, 'y' => 105),
            'NE' => array('name' => 'Nebraska', 'x' => 465, 'y' => 255),
            'NV' => array('name' => 'Nevada', 'x' => 145, 'y' => 260),
            'NH' => array('name' => 'New Hampshire', 'x' => 885, 'y' => 135),
            'NJ' => array('name' => 'New Jersey', 'x' => 860, 'y' => 225),
            'NM' => array('name' => 'New Mexico', 'x' => 350, 'y' => 385),
            'NY' => array('name' => 'New York', 'x' => 830, 'y' => 165),
            'NC' => array('name' => 'North Carolina', 'x' => 785, 'y' => 345),
            'ND' => array('name' => 'North Dakota', 'x' => 465, 'y' => 120),
            'OH' => array('name' => 'Ohio', 'x' => 710, 'y' => 270),
            'OK' => array('name' => 'Oklahoma', 'x' => 505, 'y' => 370),
            'OR' => array('name' => 'Oregon', 'x' => 95, 'y' => 125),
            'PA' => array('name' => 'Pennsylvania', 'x' => 795, 'y' => 235),
            'RI' => array('name' => 'Rhode Island', 'x' => 895, 'y' => 173),
            'SC' => array('name' => 'South Carolina', 'x' => 765, 'y' => 380),
            'SD' => array('name' => 'South Dakota', 'x' => 465, 'y' => 185),
            'TN' => array('name' => 'Tennessee', 'x' => 675, 'y' => 355),
            'TX' => array('name' => 'Texas', 'x' => 480, 'y' => 450),
            'UT' => array('name' => 'Utah', 'x' => 265, 'y' => 295),
            'VT' => array('name' => 'Vermont', 'x' => 870, 'y' => 125),
            'VA' => array('name' => 'Virginia', 'x' => 795, 'y' => 315),
            'WA' => array('name' => 'Washington', 'x' => 115, 'y' => 65),
            'WV' => array('name' => 'West Virginia', 'x' => 760, 'y' => 295),
            'WI' => array('name' => 'Wisconsin', 'x' => 600, 'y' => 175),
            'WY' => array('name' => 'Wyoming', 'x' => 355, 'y' => 205),
        );
    }
    
    /**
     * Clear plugin cache
     */
    private function clear_cache() {
        global $wpdb;
        
        // Clear all plugin transients
        $wpdb->query(
            "DELETE FROM {$wpdb->options} 
             WHERE option_name LIKE '_transient_usa_map_%' 
             OR option_name LIKE '_transient_timeout_usa_map_%'"
        );
    }
}

// Initialize plugin
function usa_interactive_map_init() {
    $GLOBALS['usa_interactive_map'] = new USA_Interactive_Map();
}
add_action('plugins_loaded', 'usa_interactive_map_init');
