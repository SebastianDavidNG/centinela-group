<?php
/**
 * Plantilla de la página de inicio
 * Elementor puede usar esta página como "Home" y aplicar su contenido.
 *
 * @package Centinela_Group_Theme
 */

get_header();

if ( apply_filters( 'centinela_show_hero_on_front', true ) ) {
	echo do_shortcode( '[centinela_hero_slider]' );
}
?>

<div class="site-content">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>

<?php
get_footer();
