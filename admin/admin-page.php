<?php
/**
 * USA Map Admin Page
 * 
 * Provides admin interface for plugin configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class USA_Map_Admin {
    
    /**
     * Settings manager instance
     */
    private $settings_manager;
    
    /**
     * Current settings
     */
    private $settings;
    
    /**
     * Constructor
     */
    public function __construct($settings_manager) {
        $this->settings_manager = $settings_manager;
        $this->settings = $settings_manager->get_settings();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('USA Interactive Map', 'usa-interactive-map'),
            __('USA Map', 'usa-interactive-map'),
            'manage_options',
            'usa-interactive-map',
            array($this, 'render_admin_page'),
            'dashicons-location-alt',
            30
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('usa_map_settings_group', 'usa_map_settings');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current tab
        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        
        // Available tabs
        $tabs = array(
            'general' => __('General Settings', 'usa-interactive-map'),
            'display' => __('Display Options', 'usa-interactive-map'),
            'fields' => __('Field Mapping', 'usa-interactive-map'),
            'styling' => __('Colors & Styling', 'usa-interactive-map'),
            'advanced' => __('Advanced', 'usa-interactive-map'),
            'help' => __('Help & Support', 'usa-interactive-map'),
        );
        ?>
        
        <div class="wrap usa-map-admin">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <!-- Success/Error Messages -->
            <div id="usa-map-notices"></div>
            
            <!-- Tabs -->
            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_key => $tab_name) : ?>
                    <a href="?page=usa-interactive-map&tab=<?php echo esc_attr($tab_key); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_name); ?>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <form method="post" action="options.php" id="usa-map-settings-form">
                <?php settings_fields('usa_map_settings_group'); ?>
                
                <div class="usa-map-settings-content">
                    <?php
                    switch ($active_tab) {
                        case 'general':
                            $this->render_general_settings();
                            break;
                        case 'display':
                            $this->render_display_settings();
                            break;
                        case 'fields':
                            $this->render_field_mapping();
                            break;
                        case 'styling':
                            $this->render_styling_settings();
                            break;
                        case 'advanced':
                            $this->render_advanced_settings();
                            break;
                        case 'help':
                            $this->render_help_section();
                            break;
                    }
                    ?>
                </div>
                
                <?php if ($active_tab !== 'help') : ?>
                    <p class="submit">
                        <?php submit_button(__('Save Settings', 'usa-interactive-map'), 'primary', 'submit', false); ?>
                        <button type="button" class="button button-secondary" id="reset-settings">
                            <?php _e('Reset to Defaults', 'usa-interactive-map'); ?>
                        </button>
                    </p>
                <?php endif; ?>
            </form>
            
            <?php if ($active_tab === 'help') : ?>
                <div class="usa-map-shortcode-info">
                    <h3><?php _e('Quick Start', 'usa-interactive-map'); ?></h3>
                    <p><?php _e('Use this shortcode to display the map:', 'usa-interactive-map'); ?></p>
                    <code class="usa-map-shortcode">[usa_map]</code>
                    <button type="button" class="button button-small copy-shortcode" data-clipboard-text="[usa_map]">
                        <?php _e('Copy', 'usa-interactive-map'); ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render General Settings Tab
     */
    private function render_general_settings() {
        $post_types = $this->settings_manager->get_available_post_types();
        $selected_post_types = $this->settings['post_types'];
        ?>
        <div class="usa-map-section">
            <h2><?php _e('General Settings', 'usa-interactive-map'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post_types"><?php _e('Post Types', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Select Post Types', 'usa-interactive-map'); ?></legend>
                            <?php foreach ($post_types as $post_type => $label) : ?>
                                <label>
                                    <input type="checkbox" 
                                           name="usa_map_settings[post_types][]" 
                                           value="<?php echo esc_attr($post_type); ?>"
                                           <?php checked(in_array($post_type, $selected_post_types)); ?> />
                                    <?php echo esc_html($label); ?>
                                </label><br>
                            <?php endforeach; ?>
                        </fieldset>
                        <p class="description">
                            <?php _e('Select which post types should be displayed on the map.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="state_field_type"><?php _e('State Field Type', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <select name="usa_map_settings[state_field][type]" id="state_field_type">
                            <option value="meta" <?php selected($this->settings['state_field']['type'], 'meta'); ?>>
                                <?php _e('Custom Field (Post Meta)', 'usa-interactive-map'); ?>
                            </option>
                            <option value="taxonomy" <?php selected($this->settings['state_field']['type'], 'taxonomy'); ?>>
                                <?php _e('Taxonomy', 'usa-interactive-map'); ?>
                            </option>
                            <?php
                            $field_plugins = $this->settings_manager->detect_field_plugins();
                            foreach ($field_plugins as $plugin_key => $plugin_name) :
                            ?>
                                <option value="<?php echo esc_attr($plugin_key); ?>" 
                                        <?php selected($this->settings['state_field']['type'], $plugin_key); ?>>
                                    <?php echo esc_html($plugin_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Choose how state information is stored in your posts.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr id="state_field_key_row">
                    <th scope="row">
                        <label for="state_field_key"><?php _e('State Field', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <div id="state_field_selector">
                            <!-- This will be populated dynamically based on field type -->
                            <select name="usa_map_settings[state_field][key]" id="state_field_key">
                                <option value=""><?php _e('Loading...', 'usa-interactive-map'); ?></option>
                            </select>
                        </div>
                        <p class="description">
                            <?php _e('Select the field that contains state information.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="state_format"><?php _e('State Format', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <select name="usa_map_settings[state_field][format]" id="state_format">
                            <option value="full_name" <?php selected($this->settings['state_field']['format'], 'full_name'); ?>>
                                <?php _e('Full Name (e.g., California)', 'usa-interactive-map'); ?>
                            </option>
                            <option value="abbreviation" <?php selected($this->settings['state_field']['format'], 'abbreviation'); ?>>
                                <?php _e('Abbreviation (e.g., CA)', 'usa-interactive-map'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('How are states stored in your database?', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Display Settings Tab
     */
    private function render_display_settings() {
        $display = $this->settings['display'];
        $fields = $this->settings['display_fields'];
        ?>
        <div class="usa-map-section">
            <h2><?php _e('Display Options', 'usa-interactive-map'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="display_mode"><?php _e('Display Mode', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <select name="usa_map_settings[display][mode]" id="display_mode">
                            <option value="tooltip" <?php selected($display['mode'], 'tooltip'); ?>>
                                <?php _e('Tooltip (Popup)', 'usa-interactive-map'); ?>
                            </option>
                            <option value="sidebar" <?php selected($display['mode'], 'sidebar'); ?>>
                                <?php _e('Sidebar', 'usa-interactive-map'); ?>
                            </option>
                            <option value="modal" <?php selected($display['mode'], 'modal'); ?>>
                                <?php _e('Modal Window', 'usa-interactive-map'); ?>
                            </option>
                            <option value="below" <?php selected($display['mode'], 'below'); ?>>
                                <?php _e('Below Map', 'usa-interactive-map'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('How should results be displayed when a state is clicked?', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="results_per_page"><?php _e('Results Per Page', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="usa_map_settings[display][results_per_page]" 
                               id="results_per_page"
                               value="<?php echo esc_attr($display['results_per_page']); ?>"
                               min="1" 
                               max="50" />
                        <p class="description">
                            <?php _e('Number of posts to show per state (1-50).', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Display Fields', 'usa-interactive-map'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php _e('Display Fields', 'usa-interactive-map'); ?></legend>
                            
                            <label>
                                <input type="checkbox" 
                                       name="usa_map_settings[display_fields][show_title]" 
                                       value="1"
                                       <?php checked($fields['show_title'], true); ?> />
                                <?php _e('Show Title', 'usa-interactive-map'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" 
                                       name="usa_map_settings[display_fields][show_excerpt]" 
                                       value="1"
                                       <?php checked($fields['show_excerpt'], true); ?> />
                                <?php _e('Show Excerpt', 'usa-interactive-map'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" 
                                       name="usa_map_settings[display_fields][show_featured_image]" 
                                       value="1"
                                       <?php checked($fields['show_featured_image'], true); ?> />
                                <?php _e('Show Featured Image', 'usa-interactive-map'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="excerpt_length"><?php _e('Excerpt Length', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="usa_map_settings[display_fields][excerpt_length]" 
                               id="excerpt_length"
                               value="<?php echo esc_attr($fields['excerpt_length']); ?>"
                               min="5" 
                               max="100" />
                        <span><?php _e('words', 'usa-interactive-map'); ?></span>
                        <p class="description">
                            <?php _e('Number of words to show in excerpt.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="image_size"><?php _e('Image Size', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <select name="usa_map_settings[display_fields][image_size]" id="image_size">
                            <?php
                            $sizes = get_intermediate_image_sizes();
                            foreach ($sizes as $size) :
                            ?>
                                <option value="<?php echo esc_attr($size); ?>" 
                                        <?php selected($fields['image_size'], $size); ?>>
                                    <?php echo esc_html($size); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select the image size to use for featured images.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Additional Options', 'usa-interactive-map'); ?></th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="usa_map_settings[display][show_count_badges]" 
                                       value="1"
                                       <?php checked($display['show_count_badges'], true); ?> />
                                <?php _e('Show Count Badges on States', 'usa-interactive-map'); ?>
                            </label><br>
                            
                            <label>
                                <input type="checkbox" 
                                       name="usa_map_settings[display][enable_search]" 
                                       value="1"
                                       <?php checked($display['enable_search'], true); ?> />
                                <?php _e('Enable Search Within Results', 'usa-interactive-map'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="animation_speed"><?php _e('Animation Speed', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="usa_map_settings[display][animation_speed]" 
                               id="animation_speed"
                               value="<?php echo esc_attr($display['animation_speed']); ?>"
                               min="0" 
                               max="2000" 
                               step="100" />
                        <span><?php _e('milliseconds', 'usa-interactive-map'); ?></span>
                        <p class="description">
                            <?php _e('Animation duration for tooltips and transitions (0 to disable).', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Field Mapping Tab
     */
    private function render_field_mapping() {
        $custom_fields = $this->settings['display_fields']['custom_fields'];
        ?>
        <div class="usa-map-section">
            <h2><?php _e('Field Mapping', 'usa-interactive-map'); ?></h2>
            <p><?php _e('Add custom fields to display in the map results.', 'usa-interactive-map'); ?></p>
            
            <div id="custom-fields-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Field Key', 'usa-interactive-map'); ?></th>
                            <th><?php _e('Display Label', 'usa-interactive-map'); ?></th>
                            <th><?php _e('Field Type', 'usa-interactive-map'); ?></th>
                            <th><?php _e('Actions', 'usa-interactive-map'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="custom-fields-list">
                        <?php if (empty($custom_fields)) : ?>
                            <tr class="no-fields">
                                <td colspan="4"><?php _e('No custom fields added yet.', 'usa-interactive-map'); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($custom_fields as $index => $field) : ?>
                                <tr class="custom-field-row">
                                    <td>
                                        <input type="text" 
                                               name="usa_map_settings[display_fields][custom_fields][<?php echo $index; ?>][key]" 
                                               value="<?php echo esc_attr($field['key']); ?>" />
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="usa_map_settings[display_fields][custom_fields][<?php echo $index; ?>][label]" 
                                               value="<?php echo esc_attr($field['label']); ?>" />
                                    </td>
                                    <td>
                                        <select name="usa_map_settings[display_fields][custom_fields][<?php echo $index; ?>][type]">
                                            <option value="text" <?php selected($field['type'], 'text'); ?>>
                                                <?php _e('Text', 'usa-interactive-map'); ?>
                                            </option>
                                            <option value="url" <?php selected($field['type'], 'url'); ?>>
                                                <?php _e('URL', 'usa-interactive-map'); ?>
                                            </option>
                                            <option value="email" <?php selected($field['type'], 'email'); ?>>
                                                <?php _e('Email', 'usa-interactive-map'); ?>
                                            </option>
                                            <option value="image" <?php selected($field['type'], 'image'); ?>>
                                                <?php _e('Image', 'usa-interactive-map'); ?>
                                            </option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small remove-field">
                                            <?php _e('Remove', 'usa-interactive-map'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <p>
                    <button type="button" class="button button-secondary" id="add-custom-field">
                        <?php _e('Add Custom Field', 'usa-interactive-map'); ?>
                    </button>
                </p>
            </div>
            
            <div class="available-fields">
                <h3><?php _e('Available Fields', 'usa-interactive-map'); ?></h3>
                <p><?php _e('These fields were detected in your selected post types:', 'usa-interactive-map'); ?></p>
                <?php
                $available_fields = $this->settings_manager->get_available_meta_fields();
                if (!empty($available_fields)) :
                ?>
                    <div class="field-list">
                        <?php foreach ($available_fields as $key => $label) : ?>
                            <code class="field-key" data-field-key="<?php echo esc_attr($key); ?>">
                                <?php echo esc_html($key); ?>
                            </code>
                        <?php endforeach; ?>
                    </div>
                    <p class="description">
                        <?php _e('Click on a field to add it to the custom fields list.', 'usa-interactive-map'); ?>
                    </p>
                <?php else : ?>
                    <p class="description">
                        <?php _e('No custom fields found. Select post types in General Settings first.', 'usa-interactive-map'); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Styling Settings Tab
     */
    private function render_styling_settings() {
        $colors = $this->settings['colors'];
        $typography = $this->settings['typography'];
        ?>
        <div class="usa-map-section">
            <h2><?php _e('Colors & Styling', 'usa-interactive-map'); ?></h2>
            
            <h3><?php _e('Map Colors', 'usa-interactive-map'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="state_default"><?php _e('Default State Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][state_default]" 
                               id="state_default"
                               value="<?php echo esc_attr($colors['state_default']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="state_hover"><?php _e('State Hover Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][state_hover]" 
                               id="state_hover"
                               value="<?php echo esc_attr($colors['state_hover']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="state_active"><?php _e('Active State Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][state_active]" 
                               id="state_active"
                               value="<?php echo esc_attr($colors['state_active']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="border_color"><?php _e('State Border Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][border_color]" 
                               id="border_color"
                               value="<?php echo esc_attr($colors['border_color']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="background_color"><?php _e('Map Background Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][background_color]" 
                               id="background_color"
                               value="<?php echo esc_attr($colors['background_color']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Tooltip/Results Colors', 'usa-interactive-map'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="tooltip_background"><?php _e('Background Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][tooltip_background]" 
                               id="tooltip_background"
                               value="<?php echo esc_attr($colors['tooltip_background']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="tooltip_text"><?php _e('Text Color', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][tooltip_text]" 
                               id="tooltip_text"
                               value="<?php echo esc_attr($colors['tooltip_text']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="count_badge_bg"><?php _e('Count Badge Background', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][count_badge_bg]" 
                               id="count_badge_bg"
                               value="<?php echo esc_attr($colors['count_badge_bg']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="count_badge_text"><?php _e('Count Badge Text', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               name="usa_map_settings[colors][count_badge_text]" 
                               id="count_badge_text"
                               value="<?php echo esc_attr($colors['count_badge_text']); ?>"
                               class="color-picker" />
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Typography', 'usa-interactive-map'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="enable_google_fonts"><?php _e('Enable Google Fonts', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="usa_map_settings[typography][enable_google_fonts]" 
                                   id="enable_google_fonts"
                                   value="1"
                                   <?php checked($typography['enable_google_fonts'], true); ?> />
                            <?php _e('Load Google Fonts for better typography', 'usa-interactive-map'); ?>
                        </label>
                    </td>
                </tr>
            </table>
            
            <div class="color-preview">
                <h3><?php _e('Preview', 'usa-interactive-map'); ?></h3>
                <div class="map-preview-container">
                    <div class="mini-map-preview">
                        <!-- Mini preview will be generated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Advanced Settings Tab
     */
    private function render_advanced_settings() {
        $advanced = $this->settings['advanced'];
        ?>
        <div class="usa-map-section">
            <h2><?php _e('Advanced Settings', 'usa-interactive-map'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="cache_enabled"><?php _e('Enable Caching', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="usa_map_settings[advanced][cache_enabled]" 
                                   id="cache_enabled"
                                   value="1"
                                   <?php checked($advanced['cache_enabled'], true); ?> />
                            <?php _e('Cache map data for better performance', 'usa-interactive-map'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Recommended for sites with many posts.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="cache_duration"><?php _e('Cache Duration', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               name="usa_map_settings[advanced][cache_duration]" 
                               id="cache_duration"
                               value="<?php echo esc_attr($advanced['cache_duration']); ?>"
                               min="300" 
                               max="86400" 
                               step="300" />
                        <span><?php _e('seconds', 'usa-interactive-map'); ?></span>
                        <p class="description">
                            <?php _e('How long to cache data (300 = 5 minutes, 3600 = 1 hour, 86400 = 1 day).', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="debug_mode"><?php _e('Debug Mode', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   name="usa_map_settings[advanced][debug_mode]" 
                                   id="debug_mode"
                                   value="1"
                                   <?php checked($advanced['debug_mode'], true); ?> />
                            <?php _e('Enable debug mode for troubleshooting', 'usa-interactive-map'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Shows additional information in the browser console.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="custom_css"><?php _e('Custom CSS', 'usa-interactive-map'); ?></label>
                    </th>
                    <td>
                        <textarea name="usa_map_settings[advanced][custom_css]" 
                                  id="custom_css"
                                  rows="10" 
                                  cols="50" 
                                  class="large-text code"><?php echo esc_textarea($advanced['custom_css']); ?></textarea>
                        <p class="description">
                            <?php _e('Add custom CSS to style the map. Do not include &lt;style&gt; tags.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <h3><?php _e('Tools', 'usa-interactive-map'); ?></h3>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Clear Cache', 'usa-interactive-map'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="clear-cache">
                            <?php _e('Clear All Cache', 'usa-interactive-map'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Clear all cached map data.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php _e('Export/Import', 'usa-interactive-map'); ?></th>
                    <td>
                        <button type="button" class="button button-secondary" id="export-settings">
                            <?php _e('Export Settings', 'usa-interactive-map'); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="import-settings">
                            <?php _e('Import Settings', 'usa-interactive-map'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Export or import plugin settings for backup or migration.', 'usa-interactive-map'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render Help Section
     */
    private function render_help_section() {
        ?>
        <div class="usa-map-section">
            <h2><?php _e('Help & Support', 'usa-interactive-map'); ?></h2>
            
            <div class="help-section">
                <h3><?php _e('Shortcode Usage', 'usa-interactive-map'); ?></h3>
                <p><?php _e('Use the following shortcode to display the map on any page or post:', 'usa-interactive-map'); ?></p>
                <code>[usa_map]</code>
                
                <h4><?php _e('Shortcode Parameters', 'usa-interactive-map'); ?></h4>
                <ul>
                    <li><code>post_types="post,page"</code> - <?php _e('Override post types', 'usa-interactive-map'); ?></li>
                    <li><code>state_field="custom_state"</code> - <?php _e('Override state field', 'usa-interactive-map'); ?></li>
                    <li><code>max_width="1200px"</code> - <?php _e('Set maximum width', 'usa-interactive-map'); ?></li>
                    <li><code>class="custom-class"</code> - <?php _e('Add custom CSS class', 'usa-interactive-map'); ?></li>
                </ul>
                
                <h4><?php _e('Example', 'usa-interactive-map'); ?></h4>
                <code>[usa_map post_types="location,office" state_field="location_state" max_width="800px"]</code>
            </div>
            
            <div class="help-section">
                <h3><?php _e('State Data Format', 'usa-interactive-map'); ?></h3>
                <p><?php _e('States can be stored in your posts as either:', 'usa-interactive-map'); ?></p>
                <ul>
                    <li><strong><?php _e('Full Names', 'usa-interactive-map'); ?>:</strong> California, Texas, New York</li>
                    <li><strong><?php _e('Abbreviations', 'usa-interactive-map'); ?>:</strong> CA, TX, NY</li>
                </ul>
                <p><?php _e('The plugin will automatically detect and handle both formats.', 'usa-interactive-map'); ?></p>
            </div>
            
            <div class="help-section">
                <h3><?php _e('Troubleshooting', 'usa-interactive-map'); ?></h3>
                <ul>
                    <li><?php _e('Enable Debug Mode in Advanced settings to see detailed information', 'usa-interactive-map'); ?></li>
                    <li><?php _e('Clear cache if changes are not appearing', 'usa-interactive-map'); ?></li>
                    <li><?php _e('Check that your posts have state data in the selected field', 'usa-interactive-map'); ?></li>
                    <li><?php _e('Ensure jQuery is not disabled by your theme', 'usa-interactive-map'); ?></li>
                </ul>
            </div>
            
            <div class="help-section">
                <h3><?php _e('Support', 'usa-interactive-map'); ?></h3>
                <p><?php _e('For additional help:', 'usa-interactive-map'); ?></p>
                <ul>
                    <li><a href="https://wordpress.org/support/plugin/usa-interactive-map" target="_blank">
                        <?php _e('WordPress Support Forum', 'usa-interactive-map'); ?>
                    </a></li>
                    <li><a href="https://github.com/yourrepo/usa-interactive-map" target="_blank">
                        <?php _e('GitHub Issues', 'usa-interactive-map'); ?>
                    </a></li>
                    <li><a href="https://yourwebsite.com/documentation" target="_blank">
                        <?php _e('Documentation', 'usa-interactive-map'); ?>
                    </a></li>
                </ul>
            </div>
        </div>
        <?php
    }
}
