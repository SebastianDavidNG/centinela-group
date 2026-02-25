<?php
/**
 * Archivo de tienda WooCommerce con el mismo aspecto que la tienda Syscom (/tienda/).
 * Usar para la página "tienda-centinela" (productos creados en WooCommerce).
 *
 * @package Centinela_Group_Theme
 * @see page-tienda.php (tienda Syscom)
 */

defined( 'ABSPATH' ) || exit;

get_header();

$tienda_wc_page_id = get_queried_object_id();
$tienda_wc_base   = get_permalink( wc_get_page_id( 'shop' ) );
$tienda_syscom_url = home_url( '/tienda/' );
$centinela_chevron_svg = '<svg class="centinela-header__cta-icon centinela-tienda__cat-summary-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>';

// Hero (mismo que tienda Syscom)
$tienda_built_with_elementor = class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->db->is_built_with_elementor( $tienda_wc_page_id );
if ( ! $tienda_built_with_elementor ) {
	$hero_image = $tienda_wc_page_id ? get_the_post_thumbnail_url( $tienda_wc_page_id, 'full' ) : '';
	get_template_part( 'template-parts/hero', 'page-inner', array(
		'title'     => _x( 'Productos propios', 'shop page title', 'centinela-group-theme' ),
		'image_url' => $hero_image ? $hero_image : '',
	) );
}

$product_cats = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'parent'     => 0,
) );
if ( is_wp_error( $product_cats ) ) {
	$product_cats = array();
}
$current_cat = is_product_category() ? get_queried_object() : null;
?>

<div class="centinela-tienda centinela-tienda--wc">
	<div class="centinela-tienda__inner max-w-7xl mx-auto px-4 py-8 md:py-12 flex flex-col md:flex-row gap-8">
		<aside class="centinela-tienda__sidebar w-full md:w-64 flex-shrink-0" role="navigation" aria-label="<?php esc_attr_e( 'Categorías de la tienda', 'centinela-group-theme' ); ?>">
			<div class="centinela-tienda__filters">
				<h2 class="centinela-tienda__sidebar-title"><?php esc_html_e( 'Categorías', 'centinela-group-theme' ); ?></h2>
				<nav class="centinela-tienda__nav" role="navigation" aria-label="<?php esc_attr_e( 'Filtrar por categoría', 'centinela-group-theme' ); ?>">
					<div class="centinela-tienda__cat-group">
						<a href="<?php echo esc_url( $tienda_wc_base ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--all <?php echo ! $current_cat ? 'centinela-tienda__cat-link--active' : ''; ?>"><?php esc_html_e( 'Todos los productos', 'centinela-group-theme' ); ?></a>
					</div>
					<?php foreach ( $product_cats as $cat ) : ?>
						<?php
						$cat_link = get_term_link( $cat );
						$is_active = $current_cat && (int) $current_cat->term_id === (int) $cat->term_id;
						?>
						<div class="centinela-tienda__cat-group">
							<a href="<?php echo esc_url( $cat_link ); ?>" class="centinela-tienda__cat-link <?php echo $is_active ? 'centinela-tienda__cat-link--active' : ''; ?>"><?php echo esc_html( $cat->name ); ?></a>
						</div>
					<?php endforeach; ?>
				</nav>

				<div class="centinela-tienda__filter-block centinela-tienda__wc-shop-link">
					<h3 class="centinela-tienda__filter-title"><?php esc_html_e( 'Más productos', 'centinela-group-theme' ); ?></h3>
					<a href="<?php echo esc_url( $tienda_syscom_url ); ?>" class="centinela-tienda__cat-link centinela-tienda__cat-link--wc"><?php esc_html_e( 'Productos Syscom', 'centinela-group-theme' ); ?></a>
				</div>
			</div>
		</aside>

		<main class="centinela-tienda__main flex-grow min-w-0">
			<h1 class="centinela-tienda__main-title screen-reader-text"><?php esc_html_e( 'Productos propios', 'centinela-group-theme' ); ?></h1>
			<div class="centinela-tienda__content centinela-tienda__content--wc">

				<?php do_action( 'woocommerce_before_main_content' ); ?>

				<?php do_action( 'woocommerce_shop_loop_header' ); ?>

				<?php if ( woocommerce_product_loop() ) : ?>

					<?php do_action( 'woocommerce_before_shop_loop' ); ?>

					<div class="centinela-tienda__grid centinela-tienda__grid--wc">
						<?php
						woocommerce_product_loop_start();
						if ( wc_get_loop_prop( 'total' ) ) {
							while ( have_posts() ) {
								the_post();
								do_action( 'woocommerce_shop_loop' );
								wc_get_template_part( 'content', 'product' );
							}
						}
						woocommerce_product_loop_end();
						?>
					</div>

					<?php do_action( 'woocommerce_after_shop_loop' ); ?>

				<?php else : ?>
					<?php do_action( 'woocommerce_no_products_found' ); ?>
				<?php endif; ?>

				<?php do_action( 'woocommerce_after_main_content' ); ?>

			</div>
		</main>
	</div>
</div>

<?php
get_footer();
