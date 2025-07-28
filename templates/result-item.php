<?php
/**
 * USA Map - Result Item Template
 * 
 * This template can be overridden by copying it to your theme:
 * /your-theme/usa-map/result-item.php
 * 
 * Available variables:
 * - $post: The current post object
 * - $settings: Plugin settings array
 * - $display_fields: Display fields configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

$display_fields = $settings['display_fields'];
?>

<article class="state-post-item post-type-<?php echo esc_attr($post->post_type); ?>" data-post-id="<?php echo esc_attr($post->ID); ?>">
    
    <?php if ($display_fields['show_featured_image'] && has_post_thumbnail($post->ID)) : ?>
        <div class="post-thumbnail">
            <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" rel="noopener">
                <?php echo get_the_post_thumbnail($post->ID, $display_fields['image_size']); ?>
            </a>
        </div>
    <?php endif; ?>
    
    <div class="post-content">
        <?php if ($display_fields['show_title']) : ?>
            <h4 class="post-title">
                <a href="<?php echo get_permalink($post->ID); ?>" target="_blank" rel="noopener">
                    <?php echo get_the_title($post->ID); ?>
                </a>
            </h4>
        <?php endif; ?>
        
        <?php if ($display_fields['show_excerpt']) : ?>
            <div class="post-excerpt">
                <?php 
                $excerpt = has_excerpt($post->ID) ? get_the_excerpt($post->ID) : get_the_content($post->ID);
                echo wp_trim_words($excerpt, $display_fields['excerpt_length']); 
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($display_fields['custom_fields'])) : ?>
            <div class="post-custom-fields">
                <?php foreach ($display_fields['custom_fields'] as $field) : ?>
                    <?php 
                    $value = get_post_meta($post->ID, $field['key'], true);
                    if ($value) : 
                    ?>
                        <div class="custom-field field-<?php echo esc_attr($field['key']); ?>">
                            <span class="field-label"><?php echo esc_html($field['label']); ?>:</span>
                            <span class="field-value">
                                <?php 
                                // Format value based on field type
                                switch ($field['type']) {
                                    case 'url':
                                        echo '<a href="' . esc_url($value) . '" target="_blank" rel="noopener">' . esc_html($value) . '</a>';
                                        break;
                                        
                                    case 'email':
                                        echo '<a href="mailto:' . esc_attr($value) . '">' . esc_html($value) . '</a>';
                                        break;
                                        
                                    case 'image':
                                        if (is_numeric($value)) {
                                            echo wp_get_attachment_image($value, 'thumbnail', false, array('class' => 'custom-field-image'));
                                        } else {
                                            echo '<img src="' . esc_url($value) . '" alt="" class="custom-field-image" />';
                                        }
                                        break;
                                        
                                    default:
                                        echo esc_html($value);
                                        break;
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php
        // Allow themes/plugins to add custom content
        do_action('usa_map_after_post_fields', $post, $settings);
        ?>
        
        <div class="post-actions">
            <a href="<?php echo get_permalink($post->ID); ?>" class="read-more" target="_blank" rel="noopener">
                <?php _e('Read More', 'usa-interactive-map'); ?>
            </a>
            
            <?php
            // Allow themes/plugins to add custom actions
            do_action('usa_map_post_actions', $post, $settings);
            ?>
        </div>
    </div>
</article>
