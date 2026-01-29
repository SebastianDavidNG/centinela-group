<?php
/**
 * Template for displaying single posts
 * 
 * @package Centinela_Theme
 */

get_header();
?>

<div class="main-content">
    <?php
    while (have_posts()) : the_post();
        ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class('api-data-container'); ?>>
            <h2 class="api-data-title"><?php the_title(); ?></h2>
            
            <div class="api-data-meta" style="margin-bottom: 1.5rem; color: #7f8c8d;">
                <?php printf(__('Posted on %s by %s', 'centinela-theme'), get_the_date(), get_the_author()); ?>
            </div>
            
            <div class="api-data-content">
                <?php the_content(); ?>
            </div>
            
            <?php
            if (comments_open() || get_comments_number()) :
                comments_template();
            endif;
            ?>
        </article>
        <?php
    endwhile;
    ?>
</div>

<?php
get_footer();
