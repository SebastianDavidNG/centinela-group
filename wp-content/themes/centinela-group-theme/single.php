<?php
/**
 * Plantilla para entradas del blog
 *
 * @package Centinela_Group_Theme
 */

get_header();
?>

<div class="container mx-auto px-4 py-8 md:py-12">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'max-w-3xl mx-auto' ); ?>>
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content', 'single' );
		endwhile;
		?>
	</article>
</div>

<?php
get_footer();
