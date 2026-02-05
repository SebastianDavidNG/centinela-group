<?php
/**
 * Tienda: filtrado por AJAX sin recargar (template + REST).
 * Helper para renderizar grid de productos y endpoint para el front.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtiene productos de la API Syscom y devuelve el HTML del grid + paginación.
 *
 * @param string $categoria_id ID de categoría Syscom (vacío = todos).
 * @param int    $pagina      Página 1-based.
 * @param string $ordenar     Orden: relevancia, precio_asc, precio_desc, etc.
 * @param string $cat_path    Ruta amigable (ej. videovigilancia/proteccion-contra-descargas/redes) para URLs SEO.
 * @return string HTML del grid y paginación.
 */
function centinela_tienda_render_productos_html( $categoria_id = '', $pagina = 1, $ordenar = 'relevancia', $cat_path = '' ) {
	$productos_api    = array();
	$productos_paginas = 0;

	if ( class_exists( 'Centinela_Syscom_API' ) ) {
		$args = array(
			'pagina' => max( 1, (int) $pagina ),
			'orden'  => sanitize_text_field( $ordenar ),
			'cop'    => true,
		);
		if ( $categoria_id !== '' ) {
			$args['categoria'] = $categoria_id;
		}
		$resp = Centinela_Syscom_API::get_productos( $args );
		if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) ) {
			$productos_api    = $resp['productos'];
			$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
		}
		// Si sin categoría no devuelve productos, usar la primera categoría del árbol como fallback para mostrar algo.
		if ( $categoria_id === '' && empty( $productos_api ) && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
			$arbol = Centinela_Syscom_API::get_categorias_arbol();
			if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
				$args['categoria'] = (string) $arbol[0]['id'];
				$resp = Centinela_Syscom_API::get_productos( $args );
				if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) && ! empty( $resp['productos'] ) ) {
					$productos_api    = $resp['productos'];
					$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
				}
			}
		}
	}

	ob_start();

	if ( ! empty( $productos_api ) ) {
		?>
		<div class="centinela-tienda__grid">
			<?php foreach ( $productos_api as $prod ) :
				$pid    = isset( $prod['producto_id'] ) ? $prod['producto_id'] : ( isset( $prod['id'] ) ? $prod['id'] : '' );
				$titulo = isset( $prod['titulo'] ) ? $prod['titulo'] : '';
				$img    = isset( $prod['img_portada'] ) ? $prod['img_portada'] : '';
				$precios = isset( $prod['precios'] ) && is_array( $prod['precios'] ) ? $prod['precios'] : array();
				$precio = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '' );
				$url    = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $pid ) : home_url( '/producto/' . $pid . '/' );
				?>
				<article class="centinela-tienda__card">
					<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__card-link">
						<div class="centinela-tienda__card-image">
							<?php if ( $img ) : ?>
								<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $titulo ); ?>" loading="lazy" />
							<?php else : ?>
								<span class="centinela-tienda__card-placeholder"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></span>
							<?php endif; ?>
						</div>
						<div class="centinela-tienda__card-body">
							<h2 class="centinela-tienda__card-title"><?php echo esc_html( $titulo ); ?></h2>
							<?php if ( $precio ) : ?>
								<p class="centinela-tienda__card-price"><?php echo esc_html( $precio ); ?> COP</p>
							<?php endif; ?>
						</div>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php if ( $productos_paginas > 1 ) : ?>
			<nav class="centinela-tienda__pagination" aria-label="<?php esc_attr_e( 'Paginación', 'centinela-group-theme' ); ?>">
				<?php
				$tienda_base = ( $cat_path !== '' ) ? home_url( '/tienda/' . trim( $cat_path ) . '/' ) : home_url( '/tienda/' );
				for ( $p = 1; $p <= min( $productos_paginas, 10 ); $p++ ) :
					$pag_url = $p > 1 ? add_query_arg( 'pag', $p, $tienda_base ) : $tienda_base;
					?>
					<a href="<?php echo esc_url( $pag_url ); ?>" class="centinela-tienda__page-link <?php echo (int) $p === (int) $pagina ? 'centinela-tienda__page-link--current' : ''; ?>" data-pagina="<?php echo (int) $p; ?>"><?php echo (int) $p; ?></a>
				<?php endfor; ?>
			</nav>
		<?php endif; ?>
		<?php
	} else {
		?>
		<p class="centinela-tienda__empty centinela-tienda__empty--main"><?php esc_html_e( 'No hay productos disponibles en esta categoría.', 'centinela-group-theme' ); ?></p>
		<?php
	}

	return ob_get_clean();
}

/**
 * Registrar REST API para productos de la tienda (AJAX).
 */
function centinela_tienda_rest_routes() {
	register_rest_route( 'centinela/v1', '/tienda-productos', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'categoria' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'cat_path' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'pagina' => array(
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'ordenar' => array(
				'type'              => 'string',
				'default'           => 'relevancia',
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
		'callback' => function ( $request ) {
			$categoria = $request->get_param( 'categoria' );
			$cat_path  = $request->get_param( 'cat_path' );
			$pagina    = $request->get_param( 'pagina' );
			$ordenar   = $request->get_param( 'ordenar' );
			if ( $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
				$resolved = centinela_resolve_cat_path_to_syscom_id( trim( $cat_path ) );
				if ( $resolved !== null ) {
					$categoria = $resolved;
				}
			}
			$html = centinela_tienda_render_productos_html( $categoria, $pagina, $ordenar, $cat_path );
			return new WP_REST_Response( array(
				'html'      => $html,
				'pagina'    => (int) $pagina,
				'categoria' => $categoria,
				'cat_path'  => $cat_path,
			), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_tienda_rest_routes' );
