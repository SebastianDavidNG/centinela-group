<?php
/**
 * Template Name: Carrito (Centinela)
 * Plantilla: Página Carrito – lista de productos en localStorage (estilo CozyCorner).
 * El contenido se renderiza por JS desde centinela_cart_items.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

wp_enqueue_script(
	'centinela-cart-page',
	get_template_directory_uri() . '/assets/js/cart-page.js',
	array( 'centinela-theme-script' ),
	defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0',
	true
);

$carrito_title = get_the_title();
$carrito_hero_image = get_the_post_thumbnail_url( get_queried_object_id(), 'full' );
$tienda_url = home_url( '/tienda/' );
$checkout_url = $tienda_url;
$checkout_page = get_page_by_path( 'finalizar-compra', OBJECT, 'page' );
if ( $checkout_page ) {
	$checkout_url = get_permalink( $checkout_page );
}
if ( function_exists( 'wc_get_checkout_url' ) ) {
	$checkout_url = wc_get_checkout_url();
}
// Forzar HTTP en localhost para evitar redirección a https (Docker/local sin SSL).
$tienda_url   = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $tienda_url ) : $tienda_url;
$checkout_url = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $checkout_url ) : $checkout_url;

get_header();

get_template_part( 'template-parts/hero', 'page-inner', array(
	'title'      => $carrito_title,
	'breadcrumb' => true,
	'image_url'  => $carrito_hero_image ? $carrito_hero_image : '',
) );
?>

<main id="content" class="site-main flex-grow">
	<div class="centinela-cart centinela-container">
		<div id="centinela-cart-empty" class="centinela-cart__empty" style="display: none;">
			<p class="centinela-cart__empty-text"><?php esc_html_e( 'Tu carrito está vacío.', 'centinela-group-theme' ); ?></p>
			<a href="<?php echo esc_url( $tienda_url ); ?>" class="centinela-btn centinela-cart__empty-cta"><?php esc_html_e( 'Continuar comprando', 'centinela-group-theme' ); ?></a>
		</div>

		<div id="centinela-cart-content" class="centinela-cart__content" style="display: none;">
			<div class="centinela-cart__table-wrap">
				<table class="centinela-cart__table" aria-label="<?php esc_attr_e( 'Contenido del carrito', 'centinela-group-theme' ); ?>">
					<thead>
						<tr>
							<th scope="col" class="centinela-cart__th centinela-cart__th--product"><?php esc_html_e( 'Producto', 'centinela-group-theme' ); ?></th>
							<th scope="col" class="centinela-cart__th centinela-cart__th--price"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?></th>
							<th scope="col" class="centinela-cart__th centinela-cart__th--qty"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></th>
							<th scope="col" class="centinela-cart__th centinela-cart__th--subtotal"><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></th>
							<th scope="col" class="centinela-cart__th centinela-cart__th--remove"><span class="screen-reader-text"><?php esc_html_e( 'Quitar', 'centinela-group-theme' ); ?></span></th>
						</tr>
					</thead>
					<tbody id="centinela-cart-tbody">
					</tbody>
				</table>
			</div>
			<div class="centinela-cart__actions">
				<div class="centinela-cart__totals">
					<p class="centinela-cart__subtotal-row">
						<span class="centinela-cart__subtotal-label"><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></span>
						<span id="centinela-cart-subtotal" class="centinela-cart__subtotal-value">0 COP</span>
					</p>
				</div>
				<div class="centinela-cart__buttons">
					<a href="<?php echo esc_url( $checkout_url ); ?>" id="centinela-cart-checkout" class="centinela-btn centinela-btn--primary centinela-cart__btn"><?php esc_html_e( 'Finalizar compra', 'centinela-group-theme' ); ?></a>
					<a href="<?php echo esc_url( $tienda_url ); ?>" class="centinela-btn centinela-cart__btn centinela-cart__btn--secondary"><?php esc_html_e( 'Continuar comprando', 'centinela-group-theme' ); ?></a>
				</div>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
