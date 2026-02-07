<?php
/**
 * Hero para páginas internas (estilo WiseGuard).
 * Imagen de fondo (imagen destacada de la página), breadcrumb y título.
 * Solo agregar la imagen destacada en la página para el fondo del hero.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero_title = isset( $args['title'] ) ? $args['title'] : get_the_title();
$hero_breadcrumb = isset( $args['breadcrumb'] ) ? $args['breadcrumb'] : true;
$hero_breadcrumb_custom = isset( $args['breadcrumb_custom'] ) ? $args['breadcrumb_custom'] : '';
$hero_image_url = '';
if ( isset( $args['image_url'] ) && $args['image_url'] ) {
	$hero_image_url = $args['image_url'];
} elseif ( has_post_thumbnail() ) {
	$hero_image_url = get_the_post_thumbnail_url( null, 'full' );
}
$inline_style = $hero_image_url ? ' style="background-image: url(' . esc_url( $hero_image_url ) . ');"' : '';
$hero_class = isset( $args['hero_class'] ) ? ' ' . esc_attr( $args['hero_class'] ) : '';
?>

<header class="centinela-hero-page<?php echo $hero_class; ?>"<?php echo $inline_style; ?> aria-label="<?php esc_attr_e( 'Cabecera de la página', 'centinela-group-theme' ); ?>">
	<div class="centinela-hero-page__overlay" aria-hidden="true"></div>
	<div class="centinela-hero-page__inner">
		<h1 class="centinela-hero-page__title"><?php echo esc_html( $hero_title ); ?></h1>
		<?php if ( $hero_breadcrumb ) : ?>
			<nav class="centinela-hero-page__breadcrumb" aria-label="<?php esc_attr_e( 'Miga de pan', 'centinela-group-theme' ); ?>">
				<?php if ( $hero_breadcrumb_custom !== '' ) : ?>
					<?php echo $hero_breadcrumb_custom; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — HTML construido con esc_url/esc_html en el llamador ?>
				<?php else : ?>
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Inicio', 'centinela-group-theme' ); ?></a>
					<span class="centinela-hero-page__sep" aria-hidden="true">/</span>
					<span class="centinela-hero-page__current"><?php echo esc_html( $hero_title ); ?></span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</div>
</header>
