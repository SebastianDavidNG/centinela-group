<?php
/**
 * Plantilla: Producto único (API Syscom) – estilo CozyCorner
 * URL: /tienda/producto/123-slug/ o /producto/123/
 * Muestra: galería (como vista rápida), título, precio, descripción, categorías, características, relacionados.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$producto_id = get_query_var( 'centinela_producto_id' );
if ( ! $producto_id || ! class_exists( 'Centinela_Syscom_API' ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include( get_query_template( '404' ) );
	return;
}

$producto = Centinela_Syscom_API::get_producto( $producto_id, true );
if ( is_wp_error( $producto ) || empty( $producto['titulo'] ) ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include( get_query_template( '404' ) );
	return;
}

$relacionados = Centinela_Syscom_API::get_productos_relacionados( $producto_id );
if ( is_wp_error( $relacionados ) ) {
	$relacionados = array();
}

$precios   = isset( $producto['precios'] ) && is_array( $producto['precios'] ) ? $producto['precios'] : array();
$precio_esp = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_descuento'] ) ? $precios['precio_descuento'] : '' );
$precio_lista = isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '';
$producto_imagenes = function_exists( 'centinela_get_producto_imagenes' ) ? centinela_get_producto_imagenes( $producto ) : array();
$caracteristicas   = isset( $producto['caracteristicas'] ) && is_array( $producto['caracteristicas'] ) ? $producto['caracteristicas'] : array();
$categorias        = isset( $producto['categorías'] ) ? $producto['categorías'] : ( isset( $producto['categorias'] ) ? $producto['categorias'] : array() );
$secciones_videos  = function_exists( 'centinela_producto_secciones_y_videos' ) ? centinela_producto_secciones_y_videos( $producto ) : array( 'videos' => array(), 'secciones' => array() );
$producto_videos   = isset( $secciones_videos['videos'] ) ? $secciones_videos['videos'] : array();
$producto_secciones = isset( $secciones_videos['secciones'] ) ? $secciones_videos['secciones'] : array();
if ( ! is_array( $categorias ) ) {
	$categorias = array();
}

// Canonical URL con ruta de categoría: /tienda/cat/subcat/product-slug/
if ( function_exists( 'centinela_get_product_cat_path' ) && function_exists( 'centinela_get_producto_url' ) ) {
	$canonical_cat_path = centinela_get_product_cat_path( $producto );
	$canonical_url      = centinela_get_producto_url( (int) $producto_id, $producto['titulo'], $canonical_cat_path );
	add_action( 'wp_head', function () use ( $canonical_url ) {
		echo '<link rel="canonical" href="' . esc_url( $canonical_url ) . '" />' . "\n";
	}, 5 );
}

get_header();

// Hero automático: título del producto, breadcrumb y overlay (mismo estilo que páginas internas)
$hero_producto_imagen = ! empty( $producto_imagenes[0] ) ? ( ! empty( $producto_imagenes[0]['url_large'] ) ? $producto_imagenes[0]['url_large'] : $producto_imagenes[0]['url'] ) : '';
$hero_breadcrumb_parts = array(
	'<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Inicio', 'centinela-group-theme' ) . '</a>',
	'<span class="centinela-hero-page__sep" aria-hidden="true">/</span>',
	'<a href="' . esc_url( home_url( '/tienda/' ) ) . '">' . esc_html__( 'Tienda', 'centinela-group-theme' ) . '</a>',
);
$primera_cat = is_array( $categorias ) && ! empty( $categorias ) ? $categorias[0] : null;
$cat_nombre  = $primera_cat && is_array( $primera_cat ) && isset( $primera_cat['nombre'] ) ? $primera_cat['nombre'] : ( is_object( $primera_cat ) ? $primera_cat->nombre : '' );
if ( $cat_nombre && function_exists( 'centinela_get_tienda_cat_url' ) ) {
	$nodo_cat = is_array( $primera_cat ) ? $primera_cat : array( 'nombre' => $primera_cat->nombre, 'id' => $primera_cat->id );
	$url_cat  = centinela_get_tienda_cat_url( $nodo_cat, '' );
	$hero_breadcrumb_parts[] = '<span class="centinela-hero-page__sep" aria-hidden="true">/</span>';
	$hero_breadcrumb_parts[] = '<a href="' . esc_url( $url_cat ) . '">' . esc_html( $cat_nombre ) . '</a>';
}
$hero_breadcrumb_parts[] = '<span class="centinela-hero-page__sep" aria-hidden="true">/</span>';
$hero_breadcrumb_parts[] = '<span class="centinela-hero-page__current">' . esc_html( $producto['titulo'] ) . '</span>';
get_template_part( 'template-parts/hero', 'page-inner', array(
	'title'             => $producto['titulo'],
	'breadcrumb'        => true,
	'breadcrumb_custom'  => implode( '', $hero_breadcrumb_parts ),
	'image_url'         => $hero_producto_imagen,
	'hero_class'        => 'centinela-hero-page--producto',
) );
?>

<div class="centinela-single-producto">
	<div class="centinela-single-producto__inner">
		<div class="centinela-single-producto__grid">
			<!-- Galería: igual que vista rápida – miniaturas a la izquierda, imagen principal + zoom a la derecha -->
			<div class="centinela-single-producto__gallery">
				<?php if ( ! empty( $producto_imagenes ) ) : ?>
					<?php
					$first       = $producto_imagenes[0];
					$main_src    = ! empty( $first['url_large'] ) ? $first['url_large'] : $first['url'];
					$thumbs_list = array_slice( $producto_imagenes, 0, 12 );
					$imgs_js     = array_map( function ( $i ) { return $i['url']; }, $thumbs_list );
					$imgs_large_js = array_map( function ( $i ) { return ! empty( $i['url_large'] ) ? $i['url_large'] : $i['url']; }, $thumbs_list );
					?>
					<div class="centinela-single-producto__thumbs" id="centinela-single-producto-thumbs">
						<?php foreach ( $thumbs_list as $idx => $img_item ) :
							$thumb_url = $img_item['url'];
							$is_first  = ( $idx === 0 );
							?>
							<button type="button" class="centinela-single-producto__thumb <?php echo $is_first ? ' centinela-single-producto__thumb--active' : ''; ?>" data-index="<?php echo (int) $idx; ?>">
								<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
							</button>
						<?php endforeach; ?>
					</div>
					<div class="centinela-single-producto__main-wrap" id="centinela-single-producto-main-area">
						<div class="centinela-single-producto__main-image" id="centinela-single-producto-main-wrap">
							<img src="<?php echo esc_url( $main_src ); ?>" alt="<?php echo esc_attr( $producto['titulo'] ); ?>" class="centinela-single-producto__img" id="centinela-single-producto-img" loading="eager" data-index="0" />
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

			<!-- Resumen (estilo CozyCorner: título, precio, descripción corta, cantidad, agregar al carrito) -->
			<div class="centinela-single-producto__summary">
				<h2 class="centinela-single-producto__title"><?php echo esc_html( $producto['titulo'] ); ?></h2>

				<?php if ( $precio_esp || $precio_lista ) : ?>
					<div class="centinela-single-producto__price">
						<?php if ( $precio_esp ) : ?>
							<span class="centinela-single-producto__price-special"><?php echo esc_html( $precio_esp ); ?> <?php esc_html_e( 'COP', 'centinela-group-theme' ); ?></span>
							<?php if ( $precio_lista && (float) $precio_lista !== (float) $precio_esp ) : ?>
								<del class="centinela-single-producto__price-list"><?php echo esc_html( $precio_lista ); ?></del>
							<?php endif; ?>
						<?php else : ?>
							<span><?php echo esc_html( $precio_lista ); ?> <?php esc_html_e( 'COP', 'centinela-group-theme' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $producto['modelo'] ) || ! empty( $producto['marca'] ) ) : ?>
					<div class="centinela-single-producto__meta-row">
						<?php if ( ! empty( $producto['modelo'] ) ) : ?>
							<p class="centinela-single-producto__meta-item"><strong><?php esc_html_e( 'Modelo:', 'centinela-group-theme' ); ?></strong> <?php echo esc_html( $producto['modelo'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $producto['marca'] ) ) : ?>
							<p class="centinela-single-producto__meta-item"><strong><?php esc_html_e( 'Marca:', 'centinela-group-theme' ); ?></strong> <?php echo esc_html( $producto['marca'] ); ?></p>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $caracteristicas ) ) : ?>
					<ul class="centinela-single-producto__features">
						<?php foreach ( $caracteristicas as $car ) : ?>
							<li><?php echo esc_html( is_string( $car ) ? $car : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php
				$addcart_image = ! empty( $producto_imagenes[0] ) ? ( ! empty( $producto_imagenes[0]['url'] ) ? $producto_imagenes[0]['url'] : '' ) : '';
				$addcart_price  = $precio_esp ? $precio_esp : $precio_lista;
				?>
				<div class="centinela-single-producto__cart-row">
					<label for="centinela-single-producto-qty" class="centinela-single-producto__qty-label"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></label>
					<input type="number" id="centinela-single-producto-qty" class="centinela-single-producto__qty" min="1" value="1" />
					<button type="button" id="centinela-single-producto-addcart" class="centinela-btn centinela-single-producto__btn centinela-single-producto__btn--primary"
						data-product-id="<?php echo esc_attr( $producto_id ); ?>"
						data-product-title="<?php echo esc_attr( $producto['titulo'] ); ?>"
						data-product-image="<?php echo esc_url( $addcart_image ); ?>"
						data-product-price="<?php echo esc_attr( $addcart_price ); ?>"><?php esc_html_e( 'Agregar al carrito', 'centinela-group-theme' ); ?></button>
				</div>

				<?php if ( ! empty( $categorias ) ) : ?>
					<div class="centinela-single-producto__cats">
						<span class="centinela-single-producto__cats-label"><?php esc_html_e( 'Categorías:', 'centinela-group-theme' ); ?></span>
						<?php foreach ( $categorias as $cat ) : ?>
							<?php
							$nombre = is_array( $cat ) && isset( $cat['nombre'] ) ? $cat['nombre'] : ( is_object( $cat ) ? $cat->nombre : '' );
							$nodo_cat = is_array( $cat ) ? $cat : array( 'nombre' => $cat->nombre, 'id' => $cat->id );
							if ( $nombre && function_exists( 'centinela_get_tienda_cat_url' ) ) :
								$url_cat = centinela_get_tienda_cat_url( $nodo_cat, '' );
								?>
								<a href="<?php echo esc_url( $url_cat ); ?>" class="centinela-single-producto__cat-link"><?php echo esc_html( $nombre ); ?></a><?php echo $cat !== end( $categorias ) ? ', ' : ''; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Tab Especificaciones: misma estructura y organización que Syscom -->
		<div class="centinela-single-producto__tabs">
			<nav class="centinela-single-producto__tab-list" aria-label="<?php esc_attr_e( 'Especificaciones del producto', 'centinela-group-theme' ); ?>">
				<button type="button" class="centinela-single-producto__tab is-active" data-tab="descripcion"><?php esc_html_e( 'Especificaciones', 'centinela-group-theme' ); ?></button>
			</nav>
			<div id="tab-descripcion" class="centinela-single-producto__tab-panel">
				<?php
				$descripcion_html = isset( $producto['descripcion'] ) ? (string) $producto['descripcion'] : '';
				$descripcion_html = trim( $descripcion_html );
				if ( $descripcion_html !== '' ) :
					// Orden: 1) Características en columnas, 2) Imágenes, 3) Vídeos
					if ( function_exists( 'centinela_reorder_especificaciones' ) ) {
						$descripcion_html = centinela_reorder_especificaciones( $descripcion_html );
					} elseif ( function_exists( 'centinela_inject_video_embeds_above_cols' ) ) {
						$descripcion_html = centinela_inject_video_embeds_above_cols( $descripcion_html );
					}
					$allowed = wp_kses_allowed_html( 'post' );
					$allowed['iframe'] = array(
						'src'             => true,
						'title'           => true,
						'class'           => true,
						'allow'           => true,
						'allowfullscreen' => true,
						'loading'         => true,
						'width'           => true,
						'height'          => true,
						'style'           => true,
					);
					echo wp_kses( $descripcion_html, $allowed );
				else :
					?>
					<p class="centinela-single-producto__sin-desc"><?php esc_html_e( 'Sin descripción.', 'centinela-group-theme' ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<!-- Productos relacionados -->
		<?php if ( ! empty( $relacionados ) ) : ?>
			<section class="centinela-single-producto__related" aria-labelledby="related-heading">
				<h2 id="related-heading" class="centinela-single-producto__related-title"><?php esc_html_e( 'Productos relacionados', 'centinela-group-theme' ); ?></h2>
				<div class="centinela-single-producto__related-grid">
					<?php foreach ( array_slice( $relacionados, 0, 4 ) as $rel ) : ?>
						<?php
						$rid      = isset( $rel['producto_id'] ) ? $rel['producto_id'] : ( isset( $rel['id'] ) ? $rel['id'] : '' );
						$rtitle   = isset( $rel['titulo'] ) ? $rel['titulo'] : '';
						$rimg     = isset( $rel['img_portada'] ) ? $rel['img_portada'] : '';
						$rprecios = isset( $rel['precios'] ) && is_array( $rel['precios'] ) ? $rel['precios'] : array();
						$rprecio  = isset( $rprecios['precio_especial'] ) ? $rprecios['precio_especial'] : ( isset( $rprecios['precio_lista'] ) ? $rprecios['precio_lista'] : '' );
						$rel_cat_path = function_exists( 'centinela_get_product_cat_path' ) ? centinela_get_product_cat_path( $producto ) : '';
$rurl     = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $rid, $rtitle, $rel_cat_path ) : home_url( '/tienda/producto/' . $rid . '/' );
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
									<?php if ( $rprecio ) : ?><p class="centinela-single-producto__related-price"><?php echo esc_html( $rprecio ); ?> COP</p><?php endif; ?>
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

	var tabs = document.querySelectorAll('.centinela-single-producto__tab');
	var panels = document.querySelectorAll('.centinela-single-producto__tab-panel');
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var id = tab.getAttribute('data-tab');
			tabs.forEach(function(t){ t.classList.remove('is-active'); });
			tab.classList.add('is-active');
			panels.forEach(function(p){ p.classList.add('centinela-single-producto__tab-panel--hidden'); });
			var panel = document.getElementById('tab-' + id);
			if (panel) panel.classList.remove('centinela-single-producto__tab-panel--hidden');
		});
	});

	var addcart = document.getElementById('centinela-single-producto-addcart');
	if (addcart) {
		addcart.addEventListener('click', function() {
			var id = addcart.getAttribute('data-product-id');
			var qtyEl = document.getElementById('centinela-single-producto-qty');
			var qty = (qtyEl && parseInt(qtyEl.value, 10)) || 1;
			if (id && window.centinelaAddToCart) {
				window.centinelaAddToCart({
					id: id,
					qty: qty,
					title: addcart.getAttribute('data-product-title') || '',
					image: addcart.getAttribute('data-product-image') || '',
					price: addcart.getAttribute('data-product-price') || ''
				});
			}
		});
	}
})();
</script>

<?php
get_footer();
