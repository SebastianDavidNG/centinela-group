<?php
/**
 * Template Name: API Data Display
 * Template for displaying external API data
 * 
 * @package Centinela_Theme
 */

get_header();
?>

<div class="main-content">
    <h2><?php _e('External API Data', 'centinela-theme'); ?></h2>
    
    <!-- Posts Section -->
    <section style="margin-bottom: 3rem;">
        <h3><?php _e('Latest Posts from API', 'centinela-theme'); ?></h3>
        <?php echo do_shortcode('[centinela_api endpoint="posts" limit="5"]'); ?>
    </section>
    
    <!-- Users Section -->
    <section style="margin-bottom: 3rem;">
        <h3><?php _e('Users from API', 'centinela-theme'); ?></h3>
        <?php echo do_shortcode('[centinela_api endpoint="users" limit="3"]'); ?>
    </section>
    
    <!-- Custom API Call Example -->
    <section>
        <h3><?php _e('Custom API Data', 'centinela-theme'); ?></h3>
        <?php
        $api_service = centinela_get_api_service();
        $custom_data = $api_service->fetch_data('posts/1');
        
        if (is_wp_error($custom_data)) {
            echo '<div class="api-error">' . esc_html($custom_data->get_error_message()) . '</div>';
        } else {
            ?>
            <div class="api-data-container">
                <div class="api-data-item">
                    <?php if (isset($custom_data['title'])): ?>
                        <h4 class="api-data-title"><?php echo esc_html($custom_data['title']); ?></h4>
                    <?php endif; ?>
                    
                    <?php if (isset($custom_data['body'])): ?>
                        <div class="api-data-content"><?php echo esc_html($custom_data['body']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($custom_data['id']) && isset($custom_data['userId'])): ?>
                        <small class="api-data-meta">
                            <?php printf(__('Post ID: %d | User ID: %d', 'centinela-theme'), $custom_data['id'], $custom_data['userId']); ?>
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            <?php
        }
        ?>
    </section>
</div>

<?php
get_footer();
