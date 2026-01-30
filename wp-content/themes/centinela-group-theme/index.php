<?php
/**
 * Plantilla principal del blog
 *
 * @package Centinela_Group_Theme
 */

get_header();
?>

<div class="container mx-auto px-4 py-8 md:py-12">
	<div class="max-w-4xl mx-auto">
		<?php if ( have_posts() ) : ?>
			<div class="space-y-8 md:space-y-12">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', get_post_type() );
				endwhile;
				?>
			</div>
			<?php
			the_posts_pagination( array(
				'mid_size'  => 2,
				'prev_text' => __( '&larr; Anterior', 'centinela-group-theme' ),
				'next_text' => __( 'Siguiente &rarr;', 'centinela-group-theme' ),
			) );
			?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
