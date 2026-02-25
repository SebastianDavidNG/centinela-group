<?php
/**
 * Single product WooCommerce – mismo diseño que single-producto.php (Syscom).
 * URL producto: /tienda-centinela/nombre-producto/
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $product;
if ( ! $product && function_exists( 'wc_get_product' ) ) {
	$product = wc_get_product( get_queried_object_id() );
}
if ( ! $product || ! is_a( $product, 'WC_Product' ) || ! $product->is_visible() ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	return;
}

$tienda_wc_url = function_exists( 'wc_get_page_id' ) ? get_permalink( wc_get_page_id( 'shop' ) ) : home_url( '/tienda-centinela/' );
$product_id    = $product->get_id();
$title         = $product->get_name();
$gallery_ids   = $product->get_gallery_image_ids();
$thumb_id      = $product->get_image_id();
$image_ids     = $thumb_id ? array_merge( array( $thumb_id ), $gallery_ids ) : $gallery_ids;
$image_ids     = array_unique( array_filter( $image_ids ) );

// Breadcrumb: Inicio > Productos propios > Título producto
$hero_breadcrumb_parts = array(
	'<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Inicio', 'centinela-group-theme' ) . '</a>',
	'<span class="centinela-hero-page__sep" aria-hidden="true">/</span>',
	'<a href="' . esc_url( $tienda_wc_url ) . '">' . esc_html__( 'Productos propios', 'centinela-group-theme' ) . '</a>',
	'<span class="centinela-hero-page__sep" aria-hidden="true">/</span>',
	'<span class="centinela-hero-page__current">' . esc_html( $title ) . '</span>',
);

$hero_image = '';
if ( ! empty( $image_ids ) ) {
	$hero_image = wp_get_attachment_image_url( $image_ids[0], 'full' );
}

get_header();

get_template_part( 'template-parts/hero', 'page-inner', array(
	'title'             => $title,
	'breadcrumb'        => true,
	'breadcrumb_custom' => implode( '', $hero_breadcrumb_parts ),
	'image_url'         => $hero_image ? $hero_image : '',
	'hero_class'        => 'centinela-hero-page--producto',
) );
?>

<div class="centinela-single-producto">
	<div class="centinela-single-producto__inner">
		<div class="centinela-single-producto__grid">
			<div class="centinela-single-producto__gallery">
				<?php if ( ! empty( $image_ids ) ) : ?>
					<?php
					$thumbs_list = array_slice( $image_ids, 0, 12 );
					$imgs_js     = array_map( function ( $id ) { return wp_get_attachment_image_url( $id, 'medium' ); }, $thumbs_list );
					$imgs_large_js = array_map( function ( $id ) { return wp_get_attachment_image_url( $id, 'full' ); }, $thumbs_list );
					$main_src = wp_get_attachment_image_url( $image_ids[0], 'full' );
					if ( ! $main_src ) {
						$main_src = wp_get_attachment_image_url( $image_ids[0], 'large' );
					}
					?>
					<div class="centinela-single-producto__thumbs" id="centinela-single-producto-thumbs">
						<?php foreach ( $thumbs_list as $idx => $img_id ) :
							$thumb_url = wp_get_attachment_image_url( $img_id, 'medium' );
							$is_first  = ( $idx === 0 );
							?>
							<button type="button" class="centinela-single-producto__thumb <?php echo $is_first ? ' centinela-single-producto__thumb--active' : ''; ?>" data-index="<?php echo (int) $idx; ?>">
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
							</button>
						<?php endforeach; ?>
					</div>
					<div class="centinela-single-producto__main-wrap" id="centinela-single-producto-main-area">
						<div class="centinela-single-producto__main-image" id="centinela-single-producto-main-wrap">
							<img src="<?php echo esc_url( $main_src ); ?>" alt="<?php echo esc_attr( $title ); ?>" class="centinela-single-producto__img" id="centinela-single-producto-img" loading="eager" data-index="0" />
						</div>
						<div class="centinela-single-producto__zoom-panel" id="centinela-single-producto-zoom" aria-hidden="true"></div>
					</div>
					<script>
					window.centinelaSingleProductoImagenes = <?php echo wp_json_encode( $imgs_js ); ?>;
					window.centinelaSingleProductoImagenesLarge = <?php echo wp_json_encode( $imgs_large_js ); ?>;
					</script>
				<?php else : ?>
					<div class="centinela-single-producto__main-image centinela-single-producto__main-image--placeholder">
						<?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="centinela-single-producto__summary">
				<h2 class="centinela-single-producto__title"><?php echo esc_html( $title ); ?></h2>

				<?php if ( $product->get_price_html() ) : ?>
					<div class="centinela-single-producto__price">
						<span class="centinela-single-producto__price-special"><?php echo $product->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</div>
				<?php endif; ?>

				<?php if ( $product->get_short_description() ) : ?>
					<div class="centinela-single-producto__short-desc">
						<?php echo apply_filters( 'woocommerce_short_description', $product->get_short_description() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>
				<?php endif; ?>

				<?php
				$wc_addcart_price = $product->get_price();
				$wc_addcart_image = '';
				if ( ! empty( $image_ids ) ) {
					$wc_addcart_image = wp_get_attachment_image_url( $image_ids[0], 'full' );
					if ( ! $wc_addcart_image ) {
						$wc_addcart_image = wp_get_attachment_image_url( $image_ids[0], 'medium' );
					}
				}
				$carrito_url = function_exists( 'centinela_get_wc_shop_url' ) ? centinela_get_wc_shop_url() : home_url( '/tienda-centinela/' );
				$carrito_page = get_page_by_path( 'carrito', OBJECT, 'page' );
				if ( $carrito_page ) {
					$carrito_url = get_permalink( $carrito_page );
				}
				$carrito_url = function_exists( 'centinela_force_http_on_localhost' ) ? centinela_force_http_on_localhost( $carrito_url ) : $carrito_url;
				?>
				<div class="centinela-single-producto__cart-row centinela-single-producto__cart-row--wc" id="centinela-wc-addcart-wrapper"
					data-product-id="<?php echo esc_attr( (string) $product_id ); ?>"
					data-product-title="<?php echo esc_attr( $title ); ?>"
					data-product-image="<?php echo esc_attr( $wc_addcart_image ); ?>"
					data-product-price="<?php echo esc_attr( $wc_addcart_price !== '' ? $wc_addcart_price : '0' ); ?>"
					data-carrito-url="<?php echo esc_url( $carrito_url ); ?>">
					<?php woocommerce_template_single_add_to_cart(); ?>
				</div>

				<?php
				$terms = get_the_terms( $product_id, 'product_cat' );
				if ( $terms && ! is_wp_error( $terms ) ) :
					?>
					<div class="centinela-single-producto__cats">
						<span class="centinela-single-producto__cats-label"><?php esc_html_e( 'Categorías:', 'centinela-group-theme' ); ?></span>
						<?php
						$cat_links = array();
						foreach ( $terms as $term ) {
							$cat_links[] = '<a href="' . esc_url( get_term_link( $term ) ) . '" class="centinela-single-producto__cat-link">' . esc_html( $term->name ) . '</a>';
						}
						echo implode( ', ', $cat_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<div class="centinela-single-producto__tabs">
			<nav class="centinela-single-producto__tab-list" aria-label="<?php esc_attr_e( 'Descripción del producto', 'centinela-group-theme' ); ?>">
				<button type="button" class="centinela-single-producto__tab is-active" data-tab="descripcion"><?php esc_html_e( 'Descripción', 'centinela-group-theme' ); ?></button>
			</nav>
			<div id="tab-descripcion" class="centinela-single-producto__tab-panel">
				<?php if ( $product->get_description() ) : ?>
					<?php echo apply_filters( 'the_content', $product->get_description() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php else : ?>
					<p class="centinela-single-producto__sin-desc"><?php esc_html_e( 'Sin descripción.', 'centinela-group-theme' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php
		$related_ids = wc_get_related_products( $product_id, 4 );
		if ( ! empty( $related_ids ) ) :
			?>
			<section class="centinela-single-producto__related" aria-labelledby="related-heading">
				<h2 id="related-heading" class="centinela-single-producto__related-title"><?php esc_html_e( 'Productos relacionados', 'centinela-group-theme' ); ?></h2>
				<div class="centinela-single-producto__related-grid">
					<?php foreach ( $related_ids as $rid ) :
						$rproduct = wc_get_product( $rid );
						if ( ! $rproduct || ! $rproduct->is_visible() ) {
							continue;
						}
						$rurl   = $rproduct->get_permalink();
						$rtitle = $rproduct->get_name();
						$rimg  = wp_get_attachment_image_url( $rproduct->get_image_id(), 'medium' );
						?>
						<article class="centinela-single-producto__related-item">
							<a href="<?php echo esc_url( $rurl ); ?>">
								<?php if ( $rimg ) : ?>
									<div class="centinela-single-producto__related-image"><img src="<?php echo esc_url( $rimg ); ?>" alt="<?php echo esc_attr( $rtitle ); ?>" loading="lazy" /></div>
								<?php else : ?>
									<div class="centinela-single-producto__related-image centinela-single-producto__related-image--placeholder"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></div>
								<?php endif; ?>
								<div class="centinela-single-producto__related-body">
									<h3 class="centinela-single-producto__related-item-title"><?php echo esc_html( $rtitle ); ?></h3>
									<?php if ( $rproduct->get_price_html() ) : ?>
										<p class="centinela-single-producto__related-price"><?php echo $rproduct->get_price_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
									<?php endif; ?>
								</div>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	</div>
</div>

<script>
(function() {
	var imgs = window.centinelaSingleProductoImagenes || [];
	var imgsLarge = window.centinelaSingleProductoImagenesLarge || imgs.slice();
	var mainImg = document.getElementById('centinela-single-producto-img');
	var mainWrap = document.getElementById('centinela-single-producto-main-wrap');
	var zoomEl = document.getElementById('centinela-single-producto-zoom');
	var mainArea = document.getElementById('centinela-single-producto-main-area');
	var thumbs = document.querySelectorAll('.centinela-single-producto__thumb[data-index]');
	var transitionDuration = 280;

	function setMainImage(index) {
		if (!mainImg || !imgs.length) return;
		var i = Math.max(0, Math.min(index, imgs.length - 1));
		var newSrc = imgs[i] || '';
		var newLarge = (imgsLarge[i] || imgs[i]) || '';
		if (mainWrap) mainWrap.classList.add('centinela-single-producto__main-image--fade');
		var onTransition = function() {
			if (mainWrap) mainWrap.removeEventListener('transitionend', onTransition);
			mainImg.src = newSrc;
			mainImg.setAttribute('data-index', i);
			if (zoomEl) zoomEl.style.backgroundImage = newLarge ? 'url("' + newLarge + '")' : 'none';
			if (mainWrap) mainWrap.classList.remove('centinela-single-producto__main-image--fade');
		};
		if (mainWrap) {
			mainWrap.addEventListener('transitionend', onTransition);
			setTimeout(function() {
				if (mainWrap && mainWrap.classList.contains('centinela-single-producto__main-image--fade')) onTransition();
			}, transitionDuration + 20);
		} else {
			mainImg.src = newSrc;
			mainImg.setAttribute('data-index', i);
			if (zoomEl) zoomEl.style.backgroundImage = newLarge ? 'url("' + newLarge + '")' : 'none';
		}
		thumbs.forEach(function(t) { t.classList.remove('centinela-single-producto__thumb--active'); });
		var activeThumb = document.querySelector('.centinela-single-producto__thumb[data-index="' + i + '"]');
		if (activeThumb) activeThumb.classList.add('centinela-single-producto__thumb--active');
	}

	function updateZoom(ev) {
		if (!zoomEl || !mainArea || !imgs.length) return;
		var rect = mainArea.getBoundingClientRect();
		var x = ev.clientX - rect.left;
		var y = ev.clientY - rect.top;
		if (x < 0 || x > rect.width || y < 0 || y > rect.height) {
			zoomEl.classList.remove('centinela-single-producto__zoom-panel--visible');
			return;
		}
		var index = (mainImg && mainImg.getAttribute('data-index')) ? parseInt(mainImg.getAttribute('data-index'), 10) : 0;
		var largeUrl = imgsLarge[index] || imgs[index];
		if (!largeUrl) {
			zoomEl.classList.remove('centinela-single-producto__zoom-panel--visible');
			return;
		}
		var zoomRect = zoomEl.getBoundingClientRect();
		var zoomW = zoomRect.width;
		var zoomH = zoomRect.height;
		var largeSize = 1000;
		var scaleX = largeSize / Math.max(rect.width, 1);
		var scaleY = largeSize / Math.max(rect.height, 1);
		var bx = (zoomW / 2) - (x * scaleX);
		var by = (zoomH / 2) - (y * scaleY);
		zoomEl.style.backgroundImage = largeUrl ? 'url("' + largeUrl + '")' : 'none';
		zoomEl.style.backgroundPosition = bx + 'px ' + by + 'px';
		zoomEl.classList.add('centinela-single-producto__zoom-panel--visible');
	}

	thumbs.forEach(function(btn) {
		btn.addEventListener('click', function() {
			var idx = parseInt(btn.getAttribute('data-index'), 10);
			if (!isNaN(idx)) setMainImage(idx);
		});
	});

	if (mainArea && zoomEl && imgs.length) {
		mainArea.addEventListener('mouseenter', function(ev) {
			if (imgsLarge[0] || imgs[0]) {
				mainArea.addEventListener('mousemove', updateZoom);
				updateZoom(ev);
			}
		});
		mainArea.addEventListener('mouseleave', function() {
			mainArea.removeEventListener('mousemove', updateZoom);
			zoomEl.classList.remove('centinela-single-producto__zoom-panel--visible');
		});
	}
	if (mainArea && (imgsLarge.length || imgs.length)) {
		mainArea.style.cursor = 'pointer';
	}
})();
</script>

<script>
(function() {
	var wrapper = document.getElementById('centinela-wc-addcart-wrapper');
	if (!wrapper) return;
	var form = wrapper.querySelector('form.cart');
	if (!form) return;
	form.addEventListener('submit', function(e) {
		e.preventDefault();
		var id = wrapper.getAttribute('data-product-id');
		var title = wrapper.getAttribute('data-product-title') || '';
		var image = wrapper.getAttribute('data-product-image') || '';
		var price = wrapper.getAttribute('data-product-price') || '0';
		var qtyInput = form.querySelector('input.qty');
		var qty = (qtyInput && parseInt(qtyInput.value, 10)) || 1;
		if (qty < 1) qty = 1;
		if (id && typeof window.centinelaAddToCart === 'function') {
			window.centinelaAddToCart({
				id: id,
				qty: qty,
				title: title,
				image: image,
				price: price,
				source: 'wc',
				product_url: window.location.href
			});
		}
		// Misma interacción que Syscom: no recargar ni redirigir; el ícono del carrito se actualiza solo.
	});
})();
</script>

<?php
get_footer();
