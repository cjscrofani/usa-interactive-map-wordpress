<?php
/**
 * USA Map Display Manager
 * 
 * Handles map rendering and AJAX requests
 */

if (!defined('ABSPATH')) {
    exit;
}

class USA_Map_Display {
    
    /**
     * Plugin settings
     */
    private $settings;
    
    /**
     * State data
     */
    private $states_data;
    
    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_states_data();
    }
    
    /**
     * Initialize states data
     */
    private function init_states_data() {
        $this->states_data = array(
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'DC' => 'District of Columbia',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming'
        );
    }
    
    /**
     * Render the map
     */
    public function render_map($atts, $custom_settings = null) {
        $settings = $custom_settings ?: $this->settings;
        
        // Generate unique ID if not provided
        $map_id = !empty($atts['id']) ? $atts['id'] : 'usa-map-' . uniqid();
        
        ob_start();
        ?>
        <div class="usa-map-container <?php echo esc_attr($atts['class']); ?>" 
             style="max-width: <?php echo esc_attr($atts['max_width']); ?>;">
            <div class="usa-map-wrapper" 
                 id="<?php echo esc_attr($map_id); ?>"
                 data-map-id="<?php echo esc_attr($map_id); ?>"
                 data-post-types="<?php echo esc_attr(implode(',', $settings['post_types'])); ?>"
                 data-display-mode="<?php echo esc_attr($settings['display']['mode']); ?>">
                
                <?php echo $this->get_svg_map($settings); ?>
                
                <?php if ($settings['display']['mode'] === 'tooltip') : ?>
                    <div class="state-tooltip" style="display: none;">
                        <div class="tooltip-header">
                            <h3 class="state-name"></h3>
                            <button class="close-tooltip" aria-label="Close">&times;</button>
                        </div>
                        <div class="tooltip-content">
                            <div class="loading-spinner"><?php _e('Loading...', 'usa-interactive-map'); ?></div>
                            <div class="posts-container"></div>
                        </div>
                    </div>
                <?php elseif ($settings['display']['mode'] === 'sidebar') : ?>
                    <div class="map-sidebar">
                        <div class="sidebar-header">
                            <h3 class="sidebar-title"><?php _e('Select a State', 'usa-interactive-map'); ?></h3>
                        </div>
                        <div class="sidebar-content">
                            <p class="sidebar-instruction">
                                <?php _e('Click on any state to view content.', 'usa-interactive-map'); ?>
                            </p>
                        </div>
                    </div>
                <?php elseif ($settings['display']['mode'] === 'below') : ?>
                    <div class="map-results-below">
                        <div class="results-header" style="display: none;">
                            <h3 class="results-title"></h3>
                        </div>
                        <div class="results-content"></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($settings['display']['show_count_badges']) : ?>
                <?php $this->render_count_badges(); ?>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Get SVG map with proper processing
     */
    private function get_svg_map($settings) {
        // Try to load external SVG file first
        $svg_file = USA_MAP_PLUGIN_DIR . 'assets/images/usa-map.svg';
        
        if (file_exists($svg_file)) {
            $svg_content = file_get_contents($svg_file);
            return $this->process_svg_map($svg_content, $settings);
        }
        
        // Fallback to inline SVG
        return $this->generate_inline_svg($settings);
    }
    
    /**
     * Process SVG content
     */
    private function process_svg_map($svg_content, $settings) {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        // Suppress warnings for HTML5 tags
        libxml_use_internal_errors(true);
        
        // Load SVG
        $dom->loadHTML('<?xml encoding="UTF-8">' . $svg_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        // Add class to SVG element
        $svgs = $xpath->query('//svg');
        if ($svgs->length > 0) {
            $svg = $svgs->item(0);
            $svg->setAttribute('class', 'usa-map-svg');
            $svg->setAttribute('data-map-ready', 'true');
        }
        
        // Process state paths
        $paths = $xpath->query('//path[@id]');
        
        foreach ($paths as $path) {
            $id = strtoupper($path->getAttribute('id'));
            
            // Check if this is a valid state
            if (isset($this->states_data[$id])) {
                $state_name = $this->states_data[$id];
                
                // Add required attributes
                $path->setAttribute('class', 'state');
                $path->setAttribute('data-state', $state_name);
                $path->setAttribute('data-state-abbr', $id);
                $path->setAttribute('tabindex', '0');
                $path->setAttribute('role', 'button');
                $path->setAttribute('aria-label', sprintf(__('View content for %s', 'usa-interactive-map'), $state_name));
                
                // Add post count if enabled
                if ($settings['display']['show_count_badges']) {
                    $count = $this->get_state_post_count($state_name, $settings);
                    if ($count > 0) {
                        $path->setAttribute('data-post-count', $count);
                    }
                }
            }
        }
        
        // Get processed SVG
        $processed = $dom->saveHTML();
        
        // Clean up
        $processed = str_replace('<?xml encoding="UTF-8">', '', $processed);
        
        return $processed;
    }
    
    /**
     * Generate inline SVG fallback
     */
    private function generate_inline_svg($settings) {
        ob_start();
        ?>
        <svg viewBox="0 0 959 593" class="usa-map-svg" data-map-ready="true">
            <defs>
                <style>
                    .state { 
                        cursor: pointer; 
                        transition: all 0.3s ease;
                    }
                    .state-label { 
                        pointer-events: none;
                        font-family: Arial, sans-serif;
                        font-size: 12px;
                        text-anchor: middle;
                    }
                </style>
            </defs>
            <rect width="959" height="593" fill="transparent" />
            <g class="states-group">
                <?php
                // This is a simplified representation
                // In production, you'd want to include actual state paths
                foreach ($this->states_data as $abbr => $name) :
                    // Simple rectangle placeholders for demonstration
                    $x = mt_rand(50, 900);
                    $y = mt_rand(50, 500);
                ?>
                    <g class="state-group">
                        <rect class="state"
                              id="<?php echo esc_attr($abbr); ?>"
                              data-state="<?php echo esc_attr($name); ?>"
                              data-state-abbr="<?php echo esc_attr($abbr); ?>"
                              x="<?php echo $x - 25; ?>"
                              y="<?php echo $y - 20; ?>"
                              width="50"
                              height="40"
                              rx="5" />
                        <text class="state-label"
                              x="<?php echo $x; ?>"
                              y="<?php echo $y; ?>">
                            <?php echo esc_html($abbr); ?>
                        </text>
                    </g>
                <?php endforeach; ?>
            </g>
        </svg>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for getting state posts
     */
    public function ajax_get_state_posts() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'usa_map_nonce')) {
            wp_die(__('Security check failed', 'usa-interactive-map'));
        }
        
        $state = sanitize_text_field($_POST['state']);
        $post_types = isset($_POST['post_types']) ? array_map('sanitize_text_field', explode(',', $_POST['post_types'])) : $this->settings['post_types'];
        
        // Use cache if enabled
        $cache_key = 'usa_map_state_' . md5($state . implode('_', $post_types));
        
        if ($this->settings['advanced']['cache_enabled']) {
            $cached_results = get_transient($cache_key);
            if ($cached_results !== false) {
                echo $cached_results;
                wp_die();
            }
        }
        
        // Get posts for the state
        $posts = $this->get_state_posts($state, $post_types);
        
        ob_start();
        
        if (empty($posts)) {
            echo '<div class="no-results">';
            printf(__('No content found for %s.', 'usa-interactive-map'), esc_html($state));
            echo '</div>';
        } else {
            $this->render_posts_list($posts, $state);
        }
        
        $output = ob_get_clean();
        
        // Cache results if enabled
        if ($this->settings['advanced']['cache_enabled']) {
            set_transient($cache_key, $output, $this->settings['advanced']['cache_duration']);
        }
        
        echo $output;
        wp_die();
    }
    
    /**
     * Get posts for a specific state
     */
    private function get_state_posts($state, $post_types = null) {
        if (!$post_types) {
            $post_types = $this->settings['post_types'];
        }
        
        // Build meta query based on state field settings
        $meta_query = $this->build_state_meta_query($state);
        
        $args = array(
            'post_type' => $post_types,
            'posts_per_page' => $this->settings['display']['results_per_page'],
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC',
        );
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        // Allow filtering of query args
        $args = apply_filters('usa_map_state_query_args', $args, $state, $post_types);
        
        $query = new WP_Query($args);
        
        return $query->posts;
    }
    
    /**
     * Build meta query for state
     */
    private function build_state_meta_query($state) {
        $field_type = $this->settings['state_field']['type'];
        $field_key = $this->settings['state_field']['key'];
        $field_format = $this->settings['state_field']['format'];
        
        // Build possible state values (full name and abbreviation)
        $state_values = array($state);
        
        // Add abbreviation or full name based on format
        if ($field_format === 'full_name') {
            // If we have full name, also check abbreviation
            $abbr = array_search($state, $this->states_data);
            if ($abbr !== false) {
                $state_values[] = $abbr;
            }
        } else {
            // If we have abbreviation, also check full name
            if (isset($this->states_data[$state])) {
                $state_values[] = $this->states_data[$state];
            }
        }
        
        $meta_query = array();
        
        switch ($field_type) {
            case 'meta':
                $meta_query = array(
                    array(
                        'key' => $field_key,
                        'value' => $state_values,
                        'compare' => 'IN'
                    )
                );
                break;
                
            case 'taxonomy':
                // Handle taxonomy-based state storage
                // This would require tax_query instead of meta_query
                add_filter('posts_where', function($where) use ($state_values, $field_key) {
                    global $wpdb;
                    $terms = get_terms(array(
                        'taxonomy' => $field_key,
                        'name' => $state_values,
                        'hide_empty' => false
                    ));
                    
                    if (!empty($terms)) {
                        $term_ids = wp_list_pluck($terms, 'term_id');
                        $where .= " AND {$wpdb->posts}.ID IN (
                            SELECT object_id FROM {$wpdb->term_relationships} 
                            WHERE term_taxonomy_id IN (" . implode(',', $term_ids) . ")
                        )";
                    }
                    
                    return $where;
                });
                break;
                
            case 'acf':
                if (function_exists('get_field_object')) {
                    $meta_query = array(
                        array(
                            'key' => $field_key,
                            'value' => $state_values,
                            'compare' => 'IN'
                        )
                    );
                }
                break;
        }
        
        return $meta_query;
    }
    
    /**
     * Render posts list
     */
    private function render_posts_list($posts, $state) {
        $template_file = $this->get_template_file('result-item');
        $display_fields = $this->settings['display_fields'];
        
        echo '<div class="state-posts" data-state="' . esc_attr($state) . '">';
        
        // Show count header
        echo '<div class="results-header">';
        printf(
            _n('%d result for %s', '%d results for %s', count($posts), 'usa-interactive-map'),
            count($posts),
            esc_html($state)
        );
        echo '</div>';
        
        // Search box if enabled
        if ($this->settings['display']['enable_search']) {
            echo '<div class="results-search">';
            echo '<input type="text" class="search-results" placeholder="' . esc_attr__('Search results...', 'usa-interactive-map') . '" />';
            echo '</div>';
        }
        
        echo '<div class="results-list">';
        
        foreach ($posts as $post) {
            setup_postdata($post);
            
            // Use template if exists, otherwise use default
            if ($template_file) {
                include $template_file;
            } else {
                $this->render_default_post_item($post, $display_fields);
            }
        }
        
        wp_reset_postdata();
        
        echo '</div>'; // .results-list
        echo '</div>'; // .state-posts
    }
    
    /**
     * Render default post item
     */
    private function render_default_post_item($post, $display_fields) {
        ?>
        <article class="state-post-item post-type-<?php echo esc_attr($post->post_type); ?>">
            <?php if ($display_fields['show_featured_image'] && has_post_thumbnail($post->ID)) : ?>
                <div class="post-thumbnail">
                    <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                        <?php echo get_the_post_thumbnail($post->ID, $display_fields['image_size']); ?>
                    </a>
                </div>
            <?php endif; ?>
            
            <div class="post-content">
                <?php if ($display_fields['show_title']) : ?>
                    <h4 class="post-title">
                        <a href="<?php echo get_permalink($post->ID); ?>" target="_blank">
                            <?php echo get_the_title($post->ID); ?>
                        </a>
                    </h4>
                <?php endif; ?>
                
                <?php if ($display_fields['show_excerpt']) : ?>
                    <div class="post-excerpt">
                        <?php echo wp_trim_words(get_the_excerpt($post->ID), $display_fields['excerpt_length']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($display_fields['custom_fields'])) : ?>
                    <div class="post-custom-fields">
                        <?php foreach ($display_fields['custom_fields'] as $field) : ?>
                            <?php $value = get_post_meta($post->ID, $field['key'], true); ?>
                            <?php if ($value) : ?>
                                <div class="custom-field field-<?php echo esc_attr($field['key']); ?>">
                                    <span class="field-label"><?php echo esc_html($field['label']); ?>:</span>
                                    <span class="field-value">
                                        <?php echo $this->format_field_value($value, $field['type']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="post-actions">
                    <a href="<?php echo get_permalink($post->ID); ?>" class="read-more" target="_blank">
                        <?php _e('Read More', 'usa-interactive-map'); ?>
                    </a>
                </div>
            </div>
        </article>
        <?php
    }
    
    /**
     * Format custom field value based on type
     */
    private function format_field_value($value, $type) {
        switch ($type) {
            case 'url':
                return '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
                
            case 'email':
                return '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                
            case 'image':
                if (is_numeric($value)) {
                    return wp_get_attachment_image($value, 'thumbnail');
                } else {
                    return '<img src="' . esc_url($value) . '" alt="" style="max-width: 100px; height: auto;" />';
                }
                
            default:
                return esc_html($value);
        }
    }
    
    /**
     * Get template file
     */
    private function get_template_file($template_name) {
        $template_paths = array(
            get_stylesheet_directory() . '/usa-map/' . $template_name . '.php',
            get_template_directory() . '/usa-map/' . $template_name . '.php',
            USA_MAP_PLUGIN_DIR . 'templates/' . $template_name . '.php'
        );
        
        foreach ($template_paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Get state post count
     */
    private function get_state_post_count($state, $settings = null) {
        if (!$settings) {
            $settings = $this->settings;
        }
        
        // Use cache if enabled
        $cache_key = 'usa_map_count_' . md5($state . implode('_', $settings['post_types']));
        
        if ($settings['advanced']['cache_enabled']) {
            $count = get_transient($cache_key);
            if ($count !== false) {
                return $count;
            }
        }
        
        // Build query
        $meta_query = $this->build_state_meta_query($state);
        
        $args = array(
            'post_type' => $settings['post_types'],
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        );
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        $query = new WP_Query($args);
        $count = $query->found_posts;
        
        // Cache count if enabled
        if ($settings['advanced']['cache_enabled']) {
            set_transient($cache_key, $count, $settings['advanced']['cache_duration']);
        }
        
        return $count;
    }
    
    /**
     * Render count badges
     */
    private function render_count_badges() {
        $counts = array();
        
        foreach ($this->states_data as $abbr => $name) {
            $count = $this->get_state_post_count($name);
            if ($count > 0) {
                $counts[$abbr] = $count;
            }
        }
        
        if (!empty($counts)) {
            echo '<script>window.usaMapCounts = ' . json_encode($counts) . ';</script>';
        }
    }
}
