<?php
/**
 * USA Map - Default Tooltip Template
 * 
 * This template can be overridden by copying it to your theme:
 * /your-theme/usa-map/tooltip-default.php
 * 
 * Available variables:
 * - $state_name: The name of the selected state
 * - $posts: Array of post objects for the state
 * - $settings: Plugin settings array
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="state-tooltip" style="display: none;">
    <div class="tooltip-header">
        <h3 class="state-name"><?php echo esc_html($state_name); ?></h3>
        <button class="close-tooltip" aria-label="<?php esc_attr_e('Close', 'usa-interactive-map'); ?>">&times;</button>
    </div>
    <div class="tooltip-content">
        <div class="loading-spinner"><?php _e('Loading...', 'usa-interactive-map'); ?></div>
        <div class="posts-container">
            <?php if (!empty($posts)) : ?>
                <div class="state-posts" data-state="<?php echo esc_attr($state_name); ?>">
                    <div class="results-header">
                        <?php
                        printf(
                            _n('%d result for %s', '%d results for %s', count($posts), 'usa-interactive-map'),
                            count($posts),
                            esc_html($state_name)
                        );
                        ?>
                    </div>
                    
                    <?php if ($settings['display']['enable_search']) : ?>
                        <div class="results-search">
                            <input type="text" 
                                   class="search-results" 
                                   placeholder="<?php esc_attr_e('Search results...', 'usa-interactive-map'); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="results-list">
                        <?php
                        foreach ($posts as $post) {
                            setup_postdata($post);
                            include USA_MAP_PLUGIN_DIR . 'templates/result-item.php';
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="no-results">
                    <?php printf(__('No content found for %s.', 'usa-interactive-map'), esc_html($state_name)); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
