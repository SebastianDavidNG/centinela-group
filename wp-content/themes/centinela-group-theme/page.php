<?php
/**
 * Plantilla para páginas
 * Compatible con contenido editado con Elementor.
 *
 * @package Centinela_Group_Theme
 */

get_header();
?>

<div class="container mx-auto px-4 py-8 md:py-12">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'max-w-4xl mx-auto' ); ?>>
			<?php if ( ! ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( get_the_ID() ) ) ) : ?>
				<header class="entry-header mb-6">
					<h1 class="entry-title text-3xl md:text-4xl font-bold text-gray-900"><?php the_title(); ?></h1>
				</header>
			<?php endif; ?>
			<div class="entry-content prose prose-gray max-w-none">
				<?php the_content(); ?>
			</div>
		</article>
		<?php
	endwhile;
	?>
</div>

<?php
get_footer();
