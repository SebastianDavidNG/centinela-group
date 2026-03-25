<?php
/**
 * Plantilla de la página de inicio
 * Elementor puede usar esta página como "Home" y aplicar su contenido.
 * Si la portada está construida con Elementor, no se muestra el hero por shortcode
 * para evitar duplicar el hero y que el widget de Elementor quede bien en .site-content.
 *
 * @package Centinela_Group_Theme
 */

get_header();

$front_page_id    = (int) get_option( 'page_on_front' );
$is_elementor_front = $front_page_id && get_post_meta( $front_page_id, '_elementor_edit_mode', true ) === 'builder';

// Mostrar hero por shortcode solo si la portada NO está editada con Elementor.
if ( ! $is_elementor_front && apply_filters( 'centinela_show_hero_on_front', true ) ) {
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
