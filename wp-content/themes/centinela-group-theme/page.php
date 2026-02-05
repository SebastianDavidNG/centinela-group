<?php
/**
 * Plantilla para páginas
 * Compatible con contenido editado con Elementor.
 * Hero estilo WiseGuard en páginas no construidas con Elementor (solo contenido).
 *
 * @package Centinela_Group_Theme
 */

get_header();

$is_elementor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( get_the_ID() );

while ( have_posts() ) :
	the_post();
	if ( ! $is_elementor ) {
		get_template_part( 'template-parts/hero', 'page-inner' );
	}
	?>
	<div class="container mx-auto px-4 py-8 md:py-12">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'max-w-4xl mx-auto' ); ?>>
			<?php if ( ! $is_elementor ) : ?>
				<?php /* Título ya va en el hero */ ?>
			<?php endif; ?>
			<div class="entry-content prose prose-gray max-w-none">
				<?php the_content(); ?>
			</div>
		</article>
	</div>
	<?php
endwhile;

get_footer();
