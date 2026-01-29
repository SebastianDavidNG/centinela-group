<?php
/**
 * The main template file
 * 
 * @package Centinela_Theme
 */

get_header();
?>

<div class="main-content">
    <h2><?php _e('Welcome to Centinela Theme', 'centinela-theme'); ?></h2>
    
    <p><?php _e('This theme demonstrates external API integration capabilities.', 'centinela-theme'); ?></p>
    
    <h3><?php _e('Latest Posts from External API', 'centinela-theme'); ?></h3>
    
    <?php
    // Display API data
    $api_service = centinela_get_api_service();
    $posts = $api_service->get_posts(5);
    
    if (is_wp_error($posts)) {
        echo '<div class="api-error">' . esc_html($posts->get_error_message()) . '</div>';
    } else {
        echo '<div class="api-data-container">';
        
        if (empty($posts)) {
            echo '<p>' . __('No posts available.', 'centinela-theme') . '</p>';
        } else {
            foreach ($posts as $post) {
                ?>
                <div class="api-data-item">
                    <h3 class="api-data-title"><?php echo esc_html($post['title']); ?></h3>
                    <div class="api-data-content"><?php echo esc_html($post['body']); ?></div>
                    <small class="api-data-meta">
                        <?php printf(__('Post ID: %d | User ID: %d', 'centinela-theme'), $post['id'], $post['userId']); ?>
                    </small>
                </div>
                <?php
            }
        }
        
        echo '</div>';
    }
    ?>
    
    <div style="margin-top: 3rem; padding: 1.5rem; background: #fff; border-radius: 8px;">
        <h3><?php _e('Using the API Shortcode', 'centinela-theme'); ?></h3>
        <p><?php _e('You can use the following shortcode to display API data in your posts and pages:', 'centinela-theme'); ?></p>
        <pre style="background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto;">
[centinela_api endpoint="posts" limit="5"]
[centinela_api endpoint="users" limit="3"]
        </pre>
    </div>
    
    <?php
    // Display WordPress posts if any exist
    if (have_posts()) :
        echo '<h3 style="margin-top: 3rem;">' . __('WordPress Posts', 'centinela-theme') . '</h3>';
        
        while (have_posts()) : the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('api-data-container'); ?>>
                <h3 class="api-data-title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                <div class="api-data-content">
                    <?php the_excerpt(); ?>
                </div>
                <small class="api-data-meta">
                    <?php printf(__('Posted on %s by %s', 'centinela-theme'), get_the_date(), get_the_author()); ?>
                </small>
            </article>
            <?php
        endwhile;
    endif;
    ?>
</div>

<?php
get_footer();
