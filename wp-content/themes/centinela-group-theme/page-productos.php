<?php
/**
 * Plantilla: Página Productos (filtro por categoría vía URL, estilo Syscom / OpenHome).
 * Asignar esta plantilla a la página con slug "productos".
 * Layout: sidebar izquierda (categorías), derecha productos (API Syscom o WooCommerce).
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$cat_path   = get_query_var( 'centinela_cat_path' );
$wc_term    = function_exists( 'centinela_resolve_cat_path_to_wc_term' ) ? centinela_resolve_cat_path_to_wc_term( $cat_path ) : null;
$syscom_id  = function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ? centinela_resolve_cat_path_to_syscom_id( $cat_path ) : null;
$arbol      = ( class_exists( 'Centinela_Syscom_API' ) && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) ? Centinela_Syscom_API::get_categorias_arbol() : array();
if ( is_wp_error( $arbol ) ) {
	$arbol = array();
}

$pagina_actual = max( 1, (int) ( isset( $_GET['pag'] ) ? $_GET['pag'] : 1 ) );
$productos_api = array();
$productos_total = 0;
$productos_paginas = 0;
if ( $syscom_id && class_exists( 'Centinela_Syscom_API' ) ) {
	$resp = Centinela_Syscom_API::get_productos( array(
		'categoria' => $syscom_id,
		'pagina'    => $pagina_actual,
		'orden'     => isset( $_GET['ordenar'] ) ? sanitize_text_field( $_GET['ordenar'] ) : 'relevancia',
		'cop'       => true,
	) );
	if ( ! is_wp_error( $resp ) && ! empty( $resp['productos'] ) ) {
		$productos_api   = $resp['productos'];
		$productos_total = isset( $resp['cantidad'] ) ? (int) $resp['cantidad'] : count( $productos_api );
		$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 1;
	}
}
?>

<div class="centinela-productos centinela-productos--with-sidebar">
	<div class="centinela-productos__inner max-w-7xl mx-auto px-4 py-6 md:py-10 flex flex-col md:flex-row gap-8">
		<aside class="centinela-productos__sidebar w-full md:w-64 flex-shrink-0" role="navigation" aria-label="<?php esc_attr_e( 'Filtrar por categoría', 'centinela-group-theme' ); ?>">
			<div class="centinela-productos__filters sticky top-24">
				<h2 class="text-sm font-bold text-gray-700 uppercase tracking-wider mb-4"><?php esc_html_e( 'Categorías', 'centinela-group-theme' ); ?></h2>
				<?php if ( ! empty( $arbol ) && function_exists( 'centinela_get_productos_url' ) ) : ?>
					<nav class="space-y-1">
						<?php foreach ( $arbol as $cat ) : ?>
							<?php $url_cat = centinela_get_productos_url( $cat ); ?>
							<div class="centinela-productos__cat-group">
								<a href="<?php echo esc_url( $url_cat ); ?>" class="block py-2 px-3 text-sm rounded hover:bg-gray-100 <?php echo $cat_path === sanitize_title( $cat['nombre'] ) ? 'font-semibold text-green-600' : 'text-gray-700'; ?>"><?php echo esc_html( $cat['nombre'] ); ?></a>
								<?php if ( ! empty( $cat['hijos'] ) ) : ?>
									<ul class="ml-3 space-y-0.5 border-l border-gray-200 pl-3">
										<?php
										$parent_slug = sanitize_title( $cat['nombre'] );
										foreach ( $cat['hijos'] as $sub ) :
											$url_sub = centinela_get_productos_url( $sub, $parent_slug );
											$sub_path = $parent_slug . '/' . sanitize_title( $sub['nombre'] );
											?>
											<li>
												<a href="<?php echo esc_url( $url_sub ); ?>" class="block py-1.5 px-2 text-sm rounded hover:bg-gray-100 <?php echo ( $cat_path === $sub_path ) ? 'font-semibold text-green-600' : 'text-gray-600'; ?>"><?php echo esc_html( $sub['nombre'] ); ?></a>
												<?php if ( ! empty( $sub['hijos'] ) ) : ?>
													<ul class="ml-2 space-y-0.5">
														<?php foreach ( $sub['hijos'] as $nieto ) :
															$url_nieto = centinela_get_productos_url( $nieto, $sub_path );
															$nieto_path = $sub_path . '/' . sanitize_title( $nieto['nombre'] );
															?>
															<li><a href="<?php echo esc_url( $url_nieto ); ?>" class="block py-1 px-2 text-xs rounded hover:bg-gray-100 <?php echo ( $cat_path === $nieto_path ) ? 'font-semibold text-green-600' : 'text-gray-500'; ?>"><?php echo esc_html( $nieto['nombre'] ); ?></a></li>
														<?php endforeach; ?>
													</ul>
												<?php endif; ?>
											</li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</nav>
				<?php else : ?>
					<p class="text-sm text-gray-500"><?php esc_html_e( 'Categorías no disponibles.', 'centinela-group-theme' ); ?></p>
				<?php endif; ?>
			</div>
		</aside>

		<main class="centinela-productos__main flex-grow min-w-0">
			<?php
			$titulo_pagina = __( 'Todos los productos', 'centinela-group-theme' );
			if ( $cat_path && $arbol ) {
				$segments = array_filter( array_map( 'trim', explode( '/', $cat_path ) ) );
				$padre = $arbol;
				$nombre_cat = '';
				foreach ( $segments as $slug ) {
					foreach ( $padre as $item ) {
						if ( sanitize_title( $item['nombre'] ) === $slug ) {
							$nombre_cat = $item['nombre'];
							$padre = isset( $item['hijos'] ) ? $item['hijos'] : array();
							break;
						}
					}
				}
				if ( $nombre_cat ) {
					$titulo_pagina = $nombre_cat;
				}
			}
			?>
			<h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6"><?php echo esc_html( $titulo_pagina ); ?></h1>

			<?php if ( ! empty( $productos_api ) ) : ?>
				<div class="centinela-productos__grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
					<?php foreach ( $productos_api as $prod ) :
						$pid    = isset( $prod['producto_id'] ) ? $prod['producto_id'] : ( isset( $prod['id'] ) ? $prod['id'] : '' );
						$titulo = isset( $prod['titulo'] ) ? $prod['titulo'] : '';
						$img    = isset( $prod['img_portada'] ) ? $prod['img_portada'] : '';
						$precios = isset( $prod['precios'] ) && is_array( $prod['precios'] ) ? $prod['precios'] : array();
						$precio = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '' );
						$url    = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $pid, $titulo ) : home_url( '/tienda/producto/' . $pid . '/' );
						?>
						<article class="centinela-productos__card border border-gray-200 rounded-lg overflow-hidden hover:shadow-md transition">
							<a href="<?php echo esc_url( $url ); ?>" class="block">
								<?php if ( $img ) : ?>
									<div class="aspect-square bg-gray-100"><img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $titulo ); ?>" class="w-full h-full object-contain" loading="lazy" /></div>
								<?php else : ?>
									<div class="aspect-square bg-gray-100 flex items-center justify-center text-gray-400 text-sm"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></div>
								<?php endif; ?>
								<div class="p-4">
									<h2 class="font-medium text-gray-900 line-clamp-2"><?php echo esc_html( $titulo ); ?></h2>
									<?php if ( $precio ) : ?><p class="text-green-600 font-semibold mt-2"><?php echo esc_html( $precio ); ?> COP</p><?php endif; ?>
								</div>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
				<?php if ( $productos_paginas > 1 ) : ?>
					<nav class="centinela-productos__pagination flex justify-center gap-2 mt-8" aria-label="<?php esc_attr_e( 'Paginación', 'centinela-group-theme' ); ?>">
						<?php for ( $p = 1; $p <= min( $productos_paginas, 10 ); $p++ ) : ?>
							<a href="<?php echo esc_url( add_query_arg( 'pag', $p ) ); ?>" class="px-4 py-2 rounded border <?php echo (int) $p === $pagina_actual ? 'bg-green-600 text-white border-green-600' : 'border-gray-300 hover:bg-gray-50'; ?>"><?php echo (int) $p; ?></a>
						<?php endfor; ?>
					</nav>
				<?php endif; ?>
			<?php elseif ( function_exists( 'wc_get_loop_display_mode' ) && ( $wc_term || empty( $syscom_id ) ) ) : ?>
				<div class="centinela-productos__grid">
					<?php
					if ( $wc_term && ! is_wp_error( $wc_term ) ) {
						echo do_shortcode( '[products category="' . esc_attr( $wc_term->slug ) . '" limit="12" pagination="true" columns="3"]' );
					} else {
						echo do_shortcode( '[products limit="12" pagination="true" columns="3"]' );
					}
					?>
				</div>
			<?php else : ?>
				<?php if ( $syscom_id && empty( $productos_api ) ) : ?>
					<p class="text-gray-600"><?php esc_html_e( 'No hay productos en esta categoría.', 'centinela-group-theme' ); ?></p>
				<?php elseif ( ! $syscom_id && ! function_exists( 'wc_get_loop_display_mode' ) ) : ?>
					<p class="text-gray-600"><?php esc_html_e( 'Configura la API Syscom o activa WooCommerce para mostrar productos.', 'centinela-group-theme' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</main>
	</div>
</div>

<?php
get_footer();
