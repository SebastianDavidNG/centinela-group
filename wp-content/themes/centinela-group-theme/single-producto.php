<?php
/**
 * Plantilla: Producto único (API Syscom) – estilo Hotlock
 * URL: /producto/{id}/
 * Muestra: galería, título, precio, descripción, categorías, características, relacionados.
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
$imagenes  = isset( $producto['imagenes'] ) && is_array( $producto['imagenes'] ) ? $producto['imagenes'] : array();
$img_portada = isset( $producto['img_portada'] ) ? trim( $producto['img_portada'] ) : '';
$caracteristicas = isset( $producto['caracteristicas'] ) && is_array( $producto['caracteristicas'] ) ? $producto['caracteristicas'] : array();
$categorias = isset( $producto['categorías'] ) ? $producto['categorías'] : ( isset( $producto['categorias'] ) ? $producto['categorias'] : array() );
if ( ! is_array( $categorias ) ) {
	$categorias = array();
}

get_header();
?>

<div class="centinela-single-producto">
	<!-- Breadcrumb -->
	<nav class="centinela-single-producto__breadcrumb" aria-label="<?php esc_attr_e( 'Miga de pan', 'centinela-group-theme' ); ?>">
		<div class="centinela-single-producto__inner max-w-7xl mx-auto px-4 py-3">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Inicio', 'centinela-group-theme' ); ?></a>
			<span class="centinela-single-producto__sep">/</span>
			<a href="<?php echo esc_url( home_url( '/productos/' ) ); ?>"><?php esc_html_e( 'Productos', 'centinela-group-theme' ); ?></a>
			<span class="centinela-single-producto__sep">/</span>
			<span class="centinela-single-producto__current"><?php echo esc_html( $producto['titulo'] ); ?></span>
		</div>
	</nav>

	<div class="centinela-single-producto__inner max-w-7xl mx-auto px-4 py-6 md:py-10">
		<div class="centinela-single-producto__grid grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12">
			<!-- Galería -->
			<div class="centinela-single-producto__gallery">
				<?php if ( $img_portada || ! empty( $imagenes ) ) : ?>
					<div class="centinela-single-producto__main-image mb-4 rounded-lg overflow-hidden bg-gray-100">
						<?php
						$src = $img_portada;
						if ( ! $src && ! empty( $imagenes ) ) {
							$first = isset( $imagenes[0]['url'] ) ? $imagenes[0]['url'] : ( is_string( $imagenes[0] ) ? $imagenes[0] : '' );
							$src  = $first;
						}
						if ( $src ) :
							?>
							<img src="<?php echo esc_url( $src ); ?>" alt="<?php echo esc_attr( $producto['titulo'] ); ?>" class="w-full h-auto object-contain centinela-single-producto__img" loading="eager" />
						<?php else : ?>
							<div class="aspect-square flex items-center justify-center text-gray-400"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></div>
						<?php endif; ?>
					</div>
					<?php if ( count( $imagenes ) > 1 ) : ?>
						<div class="centinela-single-producto__thumbs flex flex-wrap gap-2">
							<?php foreach ( array_slice( $imagenes, 0, 6 ) as $img ) :
								$url = isset( $img['url'] ) ? $img['url'] : ( is_string( $img ) ? $img : '' );
								if ( ! $url ) continue;
								?>
								<button type="button" class="centinela-single-producto__thumb w-16 h-16 rounded border-2 border-gray-200 overflow-hidden focus:border-green-500 focus:outline-none" data-src="<?php echo esc_url( $url ); ?>">
									<img src="<?php echo esc_url( $url ); ?>" alt="" class="w-full h-full object-cover" loading="lazy" />
								</button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				<?php else : ?>
					<div class="centinela-single-producto__main-image aspect-square rounded-lg bg-gray-100 flex items-center justify-center text-gray-400">
						<?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Resumen -->
			<div class="centinela-single-producto__summary">
				<h1 class="centinela-single-producto__title text-2xl md:text-3xl font-bold text-gray-900 mb-4"><?php echo esc_html( $producto['titulo'] ); ?></h1>

				<?php if ( $precio_esp || $precio_lista ) : ?>
					<div class="centinela-single-producto__price text-xl font-semibold text-gray-900 mb-4">
						<?php if ( $precio_esp ) : ?>
							<span class="centinela-single-producto__price-special"><?php echo esc_html( $precio_esp ); ?> <?php esc_html_e( 'COP', 'centinela-group-theme' ); ?></span>
							<?php if ( $precio_lista && (float) $precio_lista !== (float) $precio_esp ) : ?>
								<del class="text-gray-500 text-base ml-2"><?php echo esc_html( $precio_lista ); ?></del>
							<?php endif; ?>
						<?php else : ?>
							<span><?php echo esc_html( $precio_lista ); ?> <?php esc_html_e( 'COP', 'centinela-group-theme' ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $producto['modelo'] ) ) : ?>
					<p class="text-sm text-gray-600 mb-2"><strong><?php esc_html_e( 'Modelo:', 'centinela-group-theme' ); ?></strong> <?php echo esc_html( $producto['modelo'] ); ?></p>
				<?php endif; ?>
				<?php if ( ! empty( $producto['marca'] ) ) : ?>
					<p class="text-sm text-gray-600 mb-4"><strong><?php esc_html_e( 'Marca:', 'centinela-group-theme' ); ?></strong> <?php echo esc_html( $producto['marca'] ); ?></p>
				<?php endif; ?>

				<?php if ( ! empty( $producto['descripcion'] ) ) : ?>
					<div class="centinela-single-producto__short-desc prose prose-sm text-gray-600 mb-6 max-w-none">
						<?php echo wp_kses_post( wpautop( $producto['descripcion'] ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $caracteristicas ) ) : ?>
					<ul class="centinela-single-producto__features list-disc list-inside text-sm text-gray-600 mb-6 space-y-1">
						<?php foreach ( $caracteristicas as $car ) : ?>
							<li><?php echo esc_html( is_string( $car ) ? $car : '' ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<div class="centinela-single-producto__actions flex flex-wrap items-center gap-4">
					<a href="#pedir-cotizacion" class="inline-flex items-center px-6 py-3 bg-green-600 text-white font-medium rounded hover:bg-green-700 transition"><?php esc_html_e( 'Pedir cotización', 'centinela-group-theme' ); ?></a>
				</div>

				<?php if ( ! empty( $categorias ) ) : ?>
					<div class="centinela-single-producto__meta mt-6 pt-6 border-t border-gray-200">
						<span class="text-sm text-gray-500"><?php esc_html_e( 'Categorías:', 'centinela-group-theme' ); ?></span>
						<?php foreach ( $categorias as $cat ) : ?>
							<?php
							$nombre = is_array( $cat ) && isset( $cat['nombre'] ) ? $cat['nombre'] : ( is_object( $cat ) ? $cat->nombre : '' );
							$id    = is_array( $cat ) && isset( $cat['id'] ) ? $cat['id'] : ( is_object( $cat ) ? $cat->id : '' );
							if ( $nombre && $id && function_exists( 'centinela_get_productos_url' ) ) :
								$url_cat = home_url( '/productos/' . sanitize_title( $nombre ) . '/' );
								?>
								<a href="<?php echo esc_url( $url_cat ); ?>" class="text-sm text-green-600 hover:underline ml-1"><?php echo esc_html( $nombre ); ?></a><?php echo $cat !== end( $categorias ) ? ', ' : ''; ?>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Tabs: Descripción / Información adicional -->
		<div class="centinela-single-producto__tabs mt-12 border-t border-gray-200 pt-8">
			<nav class="centinela-single-producto__tab-list flex gap-6 border-b border-gray-200 mb-6" aria-label="<?php esc_attr_e( 'Detalles del producto', 'centinela-group-theme' ); ?>">
				<button type="button" class="centinela-single-producto__tab is-active py-3 border-b-2 border-green-600 font-medium text-gray-900" data-tab="descripcion"><?php esc_html_e( 'Descripción', 'centinela-group-theme' ); ?></button>
				<?php if ( ! empty( $caracteristicas ) ) : ?>
					<button type="button" class="centinela-single-producto__tab py-3 border-b-2 border-transparent text-gray-600 hover:text-gray-900" data-tab="caracteristicas"><?php esc_html_e( 'Características', 'centinela-group-theme' ); ?></button>
				<?php endif; ?>
			</nav>
			<div id="tab-descripcion" class="centinela-single-producto__tab-panel prose max-w-none text-gray-600">
				<?php echo ! empty( $producto['descripcion'] ) ? wp_kses_post( wpautop( $producto['descripcion'] ) ) : '<p>' . esc_html__( 'Sin descripción.', 'centinela-group-theme' ) . '</p>'; ?>
			</div>
			<?php if ( ! empty( $caracteristicas ) ) : ?>
				<div id="tab-caracteristicas" class="centinela-single-producto__tab-panel hidden">
					<table class="w-full text-sm">
						<tbody>
							<?php foreach ( $caracteristicas as $car ) : ?>
								<tr class="border-b border-gray-100"><td class="py-2"><?php echo esc_html( is_string( $car ) ? $car : '' ); ?></td></tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>

		<!-- Productos relacionados -->
		<?php if ( ! empty( $relacionados ) ) : ?>
			<section class="centinela-single-producto__related mt-16" aria-labelledby="related-heading">
				<h2 id="related-heading" class="text-2xl font-bold text-gray-900 mb-6"><?php esc_html_e( 'Productos relacionados', 'centinela-group-theme' ); ?></h2>
				<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
					<?php foreach ( array_slice( $relacionados, 0, 4 ) as $rel ) : ?>
						<?php
						$rid    = isset( $rel['producto_id'] ) ? $rel['producto_id'] : ( isset( $rel['id'] ) ? $rel['id'] : '' );
						$rtitle = isset( $rel['titulo'] ) ? $rel['titulo'] : '';
						$rimg   = isset( $rel['img_portada'] ) ? $rel['img_portada'] : '';
						$rprecios = isset( $rel['precios'] ) && is_array( $rel['precios'] ) ? $rel['precios'] : array();
						$rprecio = isset( $rprecios['precio_especial'] ) ? $rprecios['precio_especial'] : ( isset( $rprecios['precio_lista'] ) ? $rprecios['precio_lista'] : '' );
						$rurl = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $rid ) : home_url( '/producto/' . $rid . '/' );
						?>
						<article class="centinela-single-producto__related-item border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition">
							<a href="<?php echo esc_url( $rurl ); ?>" class="block">
								<?php if ( $rimg ) : ?>
									<div class="aspect-square bg-gray-100"><img src="<?php echo esc_url( $rimg ); ?>" alt="<?php echo esc_attr( $rtitle ); ?>" class="w-full h-full object-contain" loading="lazy" /></div>
								<?php else : ?>
									<div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-400 text-sm"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></div>
								<?php endif; ?>
								<div class="p-4">
									<h3 class="font-medium text-gray-900 line-clamp-2"><?php echo esc_html( $rtitle ); ?></h3>
									<?php if ( $rprecio ) : ?><p class="text-green-600 font-semibold mt-1"><?php echo esc_html( $rprecio ); ?> COP</p><?php endif; ?>
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
	var mainImg = document.querySelector('.centinela-single-producto__img');
	var thumbs = document.querySelectorAll('.centinela-single-producto__thumb[data-src]');
	thumbs.forEach(function(btn) {
		btn.addEventListener('click', function() {
			var src = btn.getAttribute('data-src');
			if (src && mainImg) mainImg.src = src;
		});
	});
	var tabs = document.querySelectorAll('.centinela-single-producto__tab');
	var panels = document.querySelectorAll('.centinela-single-producto__tab-panel');
	tabs.forEach(function(tab) {
		tab.addEventListener('click', function() {
			var id = tab.getAttribute('data-tab');
			tabs.forEach(function(t){ t.classList.remove('is-active'); t.classList.add('border-transparent'); t.classList.remove('border-green-600'); });
			tab.classList.add('is-active'); tab.classList.remove('border-transparent'); tab.classList.add('border-green-600');
			panels.forEach(function(p){ p.classList.add('hidden'); });
			var panel = document.getElementById('tab-' + id);
			if (panel) panel.classList.remove('hidden');
		});
	});
})();
</script>

<?php
get_footer();
