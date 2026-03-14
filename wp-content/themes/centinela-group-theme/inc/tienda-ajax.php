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
 * Extrae marcas únicas de una lista de productos (claves: marca, brand, fabricante).
 *
 * @param array $productos Lista de productos de la API.
 * @return array Lista de marcas ordenadas.
 */
function centinela_tienda_extract_marcas( $productos ) {
	$seen = array();
	$marcas = array();
	$keys = array( 'marca', 'brand', 'fabricante' );
	foreach ( (array) $productos as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$m = '';
		foreach ( $keys as $key ) {
			if ( isset( $p[ $key ] ) ) {
				$val = $p[ $key ];
				if ( is_array( $val ) && isset( $val['nombre'] ) ) {
					$m = trim( (string) $val['nombre'] );
				} elseif ( trim( (string) $val ) !== '' ) {
					$m = trim( (string) $val );
				}
				if ( $m !== '' ) {
					break;
				}
			}
		}
		if ( $m !== '' && ! isset( $seen[ $m ] ) ) {
			$seen[ $m ] = true;
			$marcas[] = $m;
		}
	}
	sort( $marcas );
	return $marcas;
}

/**
 * Devuelve el precio numérico de un producto (lista con IVA) para filtrar por rango.
 *
 * @param array $prod Un producto de la API (con clave precios).
 * @return float Precio numérico en COP.
 */
function centinela_tienda_producto_precio_num( $prod ) {
	if ( ! is_array( $prod ) ) {
		return 0.0;
	}
	$precios = isset( $prod['precios'] ) && is_array( $prod['precios'] ) ? $prod['precios'] : array();
	$precio_raw = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
	if ( $precio_raw === '' ) {
		$precio_raw = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '' );
	}
	return function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_raw ) : 0.0;
}

/**
 * Devuelve el nombre de la marca de un producto (misma lógica que extract_marcas).
 *
 * @param array $prod Producto de la API.
 * @return string Nombre de la marca o vacío.
 */
function centinela_tienda_producto_marca( $prod ) {
	if ( ! is_array( $prod ) ) {
		return '';
	}
	$keys = array( 'marca', 'brand', 'fabricante' );
	foreach ( $keys as $key ) {
		if ( isset( $prod[ $key ] ) ) {
			$val = $prod[ $key ];
			if ( is_array( $val ) && isset( $val['nombre'] ) ) {
				return trim( (string) $val['nombre'] );
			}
			if ( trim( (string) $val ) !== '' ) {
				return trim( (string) $val );
			}
		}
	}
	return '';
}

/**
 * Normalizar valor de inventario a escalar para mostrar (entero, float o string).
 *
 * @param mixed $val Valor devuelto por la API.
 * @return string|int|float|null
 */
function centinela_syscom_inventario_value( $val ) {
	if ( is_int( $val ) || is_float( $val ) ) {
		return $val;
	}
	if ( is_numeric( $val ) ) {
		return (int) $val;
	}
	if ( is_string( $val ) ) {
		$trimmed = trim( $val );
		return $trimmed !== '' ? $trimmed : null;
	}
	return null;
}

/**
 * Obtener inventario del producto Syscom (API envía campo "inventario" en listado y en detalle).
 * Acepta inventario a nivel raíz o en claves alternativas por si el listado usa otra estructura.
 *
 * @param array $prod Producto de la API (listado o detalle).
 * @return string|int|float|null Valor de inventario o null si no está disponible.
 */
function centinela_syscom_producto_inventario( $prod ) {
	if ( ! is_array( $prod ) ) {
		return null;
	}
	// API Syscom Colombia: listado y detalle usan "total_existencia" para la cantidad en stock (ver docs).
	// Fallbacks: inventario, existencia, stock, etc.
	$keys = array( 'total_existencia', 'inventario', 'existencia', 'stock', 'cantidad_disponible', 'disponibilidad' );
	foreach ( $keys as $key ) {
		if ( ! isset( $prod[ $key ] ) ) {
			continue;
		}
		$val = $prod[ $key ];
		// Valor anidado (ej. ["inventario" => ["cantidad" => 5]])
		if ( is_array( $val ) ) {
			if ( isset( $val['cantidad'] ) ) {
				$out = centinela_syscom_inventario_value( $val['cantidad'] );
				if ( $out !== null ) {
					return $out;
				}
			}
			if ( isset( $val['disponible'] ) ) {
				$out = centinela_syscom_inventario_value( $val['disponible'] );
				if ( $out !== null ) {
					return $out;
				}
			}
			continue;
		}
		$out = centinela_syscom_inventario_value( $val );
		if ( $out !== null ) {
			return $out;
		}
	}
	return null;
}

/**
 * Filtra una lista de productos por marca (nombre exacto, sin depender de la API).
 *
 * @param array  $productos Lista de productos de la API.
 * @param string $marca     Nombre de la marca a filtrar (vacío = no filtrar).
 * @return array Lista filtrada.
 */
function centinela_tienda_filter_productos_por_marca( $productos, $marca = '' ) {
	if ( $marca === '' || empty( $productos ) ) {
		return $productos;
	}
	$marca_trim = trim( $marca );
	$out        = array();
	foreach ( $productos as $p ) {
		$prod_marca = centinela_tienda_producto_marca( $p );
		if ( $prod_marca !== '' && $prod_marca === $marca_trim ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Filtra una lista de productos por rango de precio (min/max en COP).
 *
 * @param array  $productos Lista de productos de la API.
 * @param string $min_price Precio mínimo (vacío = sin límite).
 * @param string $max_price Precio máximo (vacío = sin límite).
 * @return array Lista filtrada.
 */
function centinela_tienda_filter_productos_por_precio( $productos, $min_price = '', $max_price = '' ) {
	if ( empty( $productos ) ) {
		return array();
	}
	$min_num = ( $min_price !== '' && is_numeric( $min_price ) ) ? (float) $min_price : null;
	$max_num = ( $max_price !== '' && is_numeric( $max_price ) ) ? (float) $max_price : null;
	if ( $min_num === null && $max_num === null ) {
		return $productos;
	}
	$out = array();
	foreach ( $productos as $p ) {
		$precio = centinela_tienda_producto_precio_num( $p );
		if ( $min_num !== null && $precio < $min_num ) {
			continue;
		}
		if ( $max_num !== null && $precio > $max_num ) {
			continue;
		}
		$out[] = $p;
	}
	return $out;
}

/**
 * Obtiene productos de la API (misma lógica que render), filtra por precio y extrae marcas.
 * Usado en la carga inicial de la tienda para rellenar el sidebar de marcas sin depender de JS.
 *
 * @param string $categoria_id ID de categoría Syscom (vacío = todos).
 * @param int    $pagina      Página 1-based.
 * @param string $ordenar     Orden.
 * @param string $cat_path    Ruta amigable.
 * @param string $marca       Filtro marca.
 * @param string $min_price   Precio mínimo.
 * @param string $max_price   Precio máximo.
 * @return array { 'productos' => [], 'paginas' => int, 'marcas' => [] }
 */
function centinela_tienda_get_productos_data( $categoria_id = '', $pagina = 1, $ordenar = 'relevancia', $cat_path = '', $marca = '', $min_price = '', $max_price = '' ) {
	$productos_api     = array();
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
			$productos_api     = $resp['productos'];
			$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
		}
		if ( $categoria_id === '' && empty( $productos_api ) && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
			$arbol = Centinela_Syscom_API::get_categorias_arbol();
			if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
				$args['categoria'] = (string) $arbol[0]['id'];
				$resp = Centinela_Syscom_API::get_productos( $args );
				if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) && ! empty( $resp['productos'] ) ) {
					$productos_api     = $resp['productos'];
					$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
				}
			}
		}
	}

	// Filtrar por precio en PHP (no en API) para tener siempre una página completa y poder extraer marcas del rango.
	$productos_tras_precio = $productos_api;
	if ( ! empty( $productos_api ) && function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
		$productos_tras_precio = centinela_tienda_filter_productos_por_precio( $productos_api, $min_price, $max_price );
	}

	// Marcas del sidebar = las que tienen productos en el rango de precio actual (o todas si el rango está vacío).
	$marcas = function_exists( 'centinela_tienda_extract_marcas' ) ? centinela_tienda_extract_marcas( ! empty( $productos_tras_precio ) ? $productos_tras_precio : $productos_api ) : array();

	// Filtrar por marca en PHP.
	if ( $marca !== '' && ! empty( $productos_tras_precio ) && function_exists( 'centinela_tienda_filter_productos_por_marca' ) ) {
		$productos_tras_precio = centinela_tienda_filter_productos_por_marca( $productos_tras_precio, $marca );
	}

	$productos_api = $productos_tras_precio;

	return array(
		'productos' => $productos_api,
		'paginas'   => $productos_paginas,
		'marcas'    => $marcas,
	);
}

/**
 * Obtiene productos de la API Syscom y devuelve el HTML del grid + paginación.
 * Opcionalmente recibe datos pre-obtenidos para evitar doble llamada.
 *
 * @param string $categoria_id ID de categoría Syscom (vacío = todos).
 * @param int    $pagina      Página 1-based.
 * @param string $ordenar     Orden: relevancia, precio_asc, precio_desc, etc.
 * @param string $cat_path    Ruta amigable para URLs SEO.
 * @param string $marca       Filtro por marca (busqueda en API); vacío = sin filtrar.
 * @param string $min_price   Precio mínimo (número); vacío = sin filtro.
 * @param string $max_price   Precio máximo (número); vacío = sin filtro.
 * @param array  $prefetched  Opcional. [ 'productos' => [], 'paginas' => 0 ] para no volver a llamar a la API.
 * @return string HTML del grid y paginación.
 */
function centinela_tienda_render_productos_html( $categoria_id = '', $pagina = 1, $ordenar = 'relevancia', $cat_path = '', $marca = '', $min_price = '', $max_price = '', $prefetched = null ) {
	$productos_api    = array();
	$productos_paginas = 0;

	if ( $prefetched !== null && isset( $prefetched['productos'] ) ) {
		$productos_api    = $prefetched['productos'];
		$productos_paginas = isset( $prefetched['paginas'] ) ? (int) $prefetched['paginas'] : 0;
	} elseif ( class_exists( 'Centinela_Syscom_API' ) ) {
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

	// Filtrar por precio en PHP (siempre; no se envía a la API para tener página completa y marcas del rango).
	if ( ! empty( $productos_api ) && function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
		$productos_api = centinela_tienda_filter_productos_por_precio( $productos_api, $min_price, $max_price );
	}

	// Filtrar por marca en PHP.
	if ( $marca !== '' && ! empty( $productos_api ) && function_exists( 'centinela_tienda_filter_productos_por_marca' ) ) {
		$productos_api = centinela_tienda_filter_productos_por_marca( $productos_api, $marca );
	}

	ob_start();

	if ( ! empty( $productos_api ) ) {
		?>
		<div class="centinela-tienda__grid">
			<?php foreach ( $productos_api as $prod ) :
				$pid       = isset( $prod['producto_id'] ) ? $prod['producto_id'] : ( isset( $prod['id'] ) ? $prod['id'] : '' );
				$titulo    = isset( $prod['titulo'] ) ? $prod['titulo'] : '';
				$img       = isset( $prod['img_portada'] ) ? $prod['img_portada'] : '';
				$modelo    = isset( $prod['modelo'] ) ? trim( (string) $prod['modelo'] ) : '';
				$prod_marca = function_exists( 'centinela_tienda_producto_marca' ) ? centinela_tienda_producto_marca( $prod ) : ( isset( $prod['marca'] ) ? trim( (string) $prod['marca'] ) : '' );
				$precios = isset( $prod['precios'] ) && is_array( $prod['precios'] ) ? $prod['precios'] : array();
				$precio_especial = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_descuento'] ) ? $precios['precio_descuento'] : '' );
				$precio = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
				if ( $precio === '' ) {
					$precio = $precio_especial ? $precio_especial : ( isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '' );
				}
				$tiene_precio_especial = $precio_especial !== '' && $precio_especial !== null;
				// URL legacy /tienda/producto/ID-slug/ para que Ver producto y enlaces lleven al detalle sin recargar tienda.
				$url    = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $pid, $titulo, '' ) : home_url( '/tienda/producto/' . $pid . '/' );
				// URL para filtrar por marca: /tienda/?marca=X o /tienda/cat-path/?marca=X
				$marca_base = home_url( '/tienda/' . ( $cat_path !== '' ? trim( $cat_path ) . '/' : '' ) );
				$marca_url  = $prod_marca !== '' ? add_query_arg( 'marca', rawurlencode( $prod_marca ), $marca_base ) : $marca_base;
				?>
				<article class="centinela-tienda__card" data-product-id="<?php echo esc_attr( $pid ); ?>">
					<div class="centinela-tienda__card-image-wrap">
						<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__card-link centinela-tienda__card-image" aria-label="<?php echo esc_attr( $titulo ); ?>">
							<?php if ( $img ) : ?>
								<img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $titulo ); ?>" loading="lazy" />
							<?php else : ?>
								<span class="centinela-tienda__card-placeholder"><?php esc_html_e( 'Sin imagen', 'centinela-group-theme' ); ?></span>
							<?php endif; ?>
						</a>
						<button type="button" class="centinela-tienda__wishlist" aria-label="<?php esc_attr_e( 'Lista de deseos', 'centinela-group-theme' ); ?>">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
						</button>
						<div class="centinela-tienda__card-overlay">
							<button type="button" class="centinela-tienda__quickview-btn" data-product-id="<?php echo esc_attr( $pid ); ?>"><span class="centinela-tienda__overlay-btn-text"><?php esc_html_e( 'Vista rápida', 'centinela-group-theme' ); ?></span><svg class="centinela-header__cta-icon centinela-tienda__overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></button>
							<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__add-cart"><span class="centinela-tienda__overlay-btn-text"><?php esc_html_e( 'Ver producto', 'centinela-group-theme' ); ?></span><svg class="centinela-header__cta-icon centinela-tienda__overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
						</div>
					</div>
					<div class="centinela-tienda__card-body">
						<h2 class="centinela-tienda__card-title">
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titulo ); ?></a>
						</h2>
						<?php if ( $modelo !== '' || $prod_marca !== '' ) : ?>
							<div class="centinela-tienda__card-meta">
								<?php if ( $modelo !== '' ) : ?><p class="centinela-tienda__card-modelo"><?php echo esc_html( $modelo ); ?></p><?php endif; ?>
								<?php if ( $prod_marca !== '' ) : ?><p class="centinela-tienda__card-marca"><a href="<?php echo esc_url( $marca_url ); ?>" class="centinela-tienda__card-marca-link"><?php echo esc_html( $prod_marca ); ?></a></p><?php endif; ?>
							</div>
						<?php endif; ?>
						<?php
						$prod_inventario = null;
						if ( function_exists( 'centinela_syscom_producto_inventario' ) ) {
							$prod_inventario = centinela_syscom_producto_inventario( $prod );
						}
						if ( $prod_inventario !== null && $prod_inventario !== '' ) {
							?>
							<p class="centinela-tienda__card-inventario"><span class="centinela-tienda__card-inventario-label"><?php echo esc_html( __( 'Stock disponible:', 'centinela-group-theme' ) ); ?></span> <span class="centinela-tienda__card-inventario-value"><?php echo esc_html( (string) $prod_inventario ); ?></span></p>
							<?php
						}
						?>
						<?php if ( $precio ) : ?>
							<div class="centinela-tienda__card-price-wrap">
								<?php if ( $tiene_precio_especial ) : ?>
									<p class="centinela-tienda__card-price-label"><?php esc_html_e( 'Precio Especial:', 'centinela-group-theme' ); ?></p>
									<p class="centinela-tienda__card-price"><?php echo esc_html( function_exists( 'centinela_format_precio_cop' ) ? centinela_format_precio_cop( $precio_especial ) : $precio_especial . ' COP' ); ?></p>
									<p class="centinela-tienda__card-price-disclaimer"><?php esc_html_e( 'Precio válido hasta agotar existencias', 'centinela-group-theme' ); ?></p>
								<?php else : ?>
									<p class="centinela-tienda__card-price"><?php echo esc_html( function_exists( 'centinela_format_precio_cop' ) ? centinela_format_precio_cop( $precio ) : $precio . ' COP' ); ?></p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
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
			'marca' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'min_price' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'max_price' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
		'callback' => function ( $request ) {
			$categoria  = $request->get_param( 'categoria' );
			$cat_path   = $request->get_param( 'cat_path' );
			$pagina     = $request->get_param( 'pagina' );
			$ordenar    = $request->get_param( 'ordenar' );
			$marca      = $request->get_param( 'marca' );
			$min_price  = $request->get_param( 'min_price' );
			$max_price  = $request->get_param( 'max_price' );
			if ( $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
				$resolved = centinela_resolve_cat_path_to_syscom_id( trim( $cat_path ) );
				if ( $resolved !== null ) {
					$categoria = $resolved;
				}
			}
			// Obtener siempre una página completa de productos (sin precio en API); filtro precio y marca se aplican en PHP.
			$productos_data = null;
			if ( class_exists( 'Centinela_Syscom_API' ) ) {
				$args = array(
					'pagina' => max( 1, (int) $pagina ),
					'orden'  => sanitize_text_field( $ordenar ),
					'cop'    => true,
				);
				if ( $categoria !== '' ) {
					$args['categoria'] = $categoria;
				}
				$resp = Centinela_Syscom_API::get_productos( $args );
				if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) ) {
					$productos_data = array(
						'productos' => $resp['productos'],
						'paginas'   => isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0,
					);
					if ( $categoria === '' && empty( $resp['productos'] ) && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
						$arbol = Centinela_Syscom_API::get_categorias_arbol();
						if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
							$args['categoria'] = (string) $arbol[0]['id'];
							$resp = Centinela_Syscom_API::get_productos( $args );
							if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) ) {
								$productos_data = array(
									'productos' => $resp['productos'],
									'paginas'   => isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0,
								);
							}
						}
					}
				}
			}
			$marcas = array();
			if ( $productos_data !== null ) {
				$prods_raw = $productos_data['productos'];
				$prods     = $prods_raw;
				if ( function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
					$prods = centinela_tienda_filter_productos_por_precio( $prods, $min_price, $max_price );
				}
				// Marcas del sidebar: del conjunto tras filtro precio; si ese conjunto está vacío, usar el sin filtrar por precio para que la lista no desaparezca.
				if ( function_exists( 'centinela_tienda_extract_marcas' ) ) {
					$marcas = centinela_tienda_extract_marcas( ! empty( $prods ) ? $prods : $prods_raw );
				}
				// Filtrar por marca en PHP (la API no filtra bien por marca vía busqueda).
				if ( $marca !== '' && function_exists( 'centinela_tienda_filter_productos_por_marca' ) ) {
					$prods = centinela_tienda_filter_productos_por_marca( $prods, $marca );
				}
				$productos_data['productos'] = $prods;
			}
			$html = centinela_tienda_render_productos_html( $categoria, $pagina, $ordenar, $cat_path, $marca, $min_price, $max_price, $productos_data );
			return new WP_REST_Response( array(
				'html'       => $html,
				'pagina'     => (int) $pagina,
				'categoria'  => $categoria,
				'cat_path'   => $cat_path,
				'marca'      => $marca,
				'min_price'  => $min_price,
				'max_price'  => $max_price,
				'marcas'     => $marcas,
			), 200 );
		},
	) );

	// Endpoint para obtener marcas disponibles (por categoría o todas).
	register_rest_route( 'centinela/v1', '/tienda-marcas', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'categoria' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'cat_path'  => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
		'callback' => function ( $request ) {
			$categoria = $request->get_param( 'categoria' );
			$cat_path  = $request->get_param( 'cat_path' );
			if ( $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
				$resolved = centinela_resolve_cat_path_to_syscom_id( trim( $cat_path ) );
				if ( $resolved !== null ) {
					$categoria = $resolved;
				}
			}
			$cache_key = 'centinela_tienda_marcas_' . md5( $categoria );
			$marcas    = get_transient( $cache_key );
			if ( $marcas === false && class_exists( 'Centinela_Syscom_API' ) ) {
				$all_marcas = array();
				$args_base  = array(
					'categoria' => $categoria,
					'orden'     => 'relevancia',
					'cop'       => true,
				);
				// Fallback: si sin categoría no hay productos, usar primera categoría del árbol.
				if ( $categoria === '' && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
					$arbol = Centinela_Syscom_API::get_categorias_arbol();
					if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
						$args_base['categoria'] = (string) $arbol[0]['id'];
					}
				}
				for ( $p = 1; $p <= 3; $p++ ) {
					$args = array_merge( $args_base, array( 'pagina' => $p ) );
					$resp = Centinela_Syscom_API::get_productos( $args );
					if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) && function_exists( 'centinela_tienda_extract_marcas' ) ) {
						$page_marcas = centinela_tienda_extract_marcas( $resp['productos'] );
						$all_marcas = array_unique( array_merge( $all_marcas, $page_marcas ) );
					}
					$paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
					if ( $p >= $paginas || is_wp_error( $resp ) || ! isset( $resp['productos'] ) || empty( $resp['productos'] ) ) {
						if ( $p === 1 && empty( $all_marcas ) ) {
							break;
						}
						if ( $p > 1 ) {
							break;
						}
					}
				}
				$marcas = array_values( $all_marcas );
				sort( $marcas );
				set_transient( $cache_key, $marcas, 30 * MINUTE_IN_SECONDS );
			}
			return new WP_REST_Response( array( 'marcas' => is_array( $marcas ) ? $marcas : array() ), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_tienda_rest_routes' );

/**
 * Intentar obtener URL de imagen en mayor resolución (API Syscom suele devolver 400x400; en origen puede ser 1000x1000).
 * Si la URL contiene patrones de tamaño (400, 400x400, etc.), los reemplaza por 1000 para zoom.
 *
 * @param string $url URL de la imagen.
 * @return string URL para usar en zoom (o la misma si no se detecta patrón).
 */
function centinela_syscom_image_url_large( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return $url;
	}
	// Syscom: -p = pequeña, -l = grande (ej. DSKH6320WTE1-p.PNG → DSKH6320WTE1-l.PNG)
	$large = preg_replace( '/-p\.(png|jpg|jpeg|webp|gif)$/i', '-l.$1', $url );
	if ( $large !== $url ) {
		return $large;
	}
	// 400x400, 400X400, etc.
	$large = preg_replace( '/400\s*[x×]\s*400/i', '1000x1000', $url );
	if ( $large !== $url ) {
		return $large;
	}
	// Números solos: 400, 300, 200 (típicos de thumbnails)
	$large = preg_replace( '/\b400\b/', '1000', $url );
	if ( $large !== $url ) {
		return $large;
	}
	$large = preg_replace( '/\b300\b/', '1000', $url );
	if ( $large !== $url ) {
		return $large;
	}
	$large = preg_replace( '/\b200\b/', '1000', $url );
	if ( $large !== $url ) {
		return $large;
	}
	// Parámetros de consulta: width=400, w=400, size=400
	$large = preg_replace( '/([?&](?:width|w|size|dim)=)400\b/i', '${1}1000', $url );
	if ( $large !== $url ) {
		return $large;
	}
	return $url;
}

/**
 * Obtener todas las imágenes de un producto (portada + galería) normalizadas.
 * Compatible con la respuesta de la API Syscom: img_portada, imagenes (array con orden, url).
 * Acepta también claves alternativas (galeria, fotos) y en cada ítem (url, imagen, src).
 *
 * @param array $producto Array del producto tal como lo devuelve Centinela_Syscom_API::get_producto().
 * @return array Lista de { 'url' => string, 'url_large' => string }.
 */
function centinela_get_producto_imagenes( $producto ) {
	if ( ! is_array( $producto ) ) {
		return array();
	}
	$img_portada = isset( $producto['img_portada'] ) ? trim( (string) $producto['img_portada'] ) : '';
	$api_imgs   = array();
	if ( isset( $producto['imagenes'] ) && is_array( $producto['imagenes'] ) ) {
		$api_imgs = $producto['imagenes'];
	} elseif ( isset( $producto['imágenes'] ) && is_array( $producto['imágenes'] ) ) {
		$api_imgs = $producto['imágenes'];
	} elseif ( isset( $producto['galeria'] ) && is_array( $producto['galeria'] ) ) {
		$api_imgs = $producto['galeria'];
	} elseif ( isset( $producto['fotos'] ) && is_array( $producto['fotos'] ) ) {
		$api_imgs = $producto['fotos'];
	}

	$imgs_list = array();
	if ( $img_portada !== '' ) {
		$imgs_list[] = array(
			'url'       => $img_portada,
			'url_large' => function_exists( 'centinela_syscom_image_url_large' ) ? centinela_syscom_image_url_large( $img_portada ) : $img_portada,
		);
	}
	foreach ( $api_imgs as $im ) {
		$u = '';
		if ( is_array( $im ) ) {
			if ( isset( $im['url'] ) ) {
				$u = trim( (string) $im['url'] );
			} elseif ( isset( $im['imagen'] ) ) {
				$u = trim( (string) $im['imagen'] );
			} elseif ( isset( $im['src'] ) ) {
				$u = trim( (string) $im['src'] );
			}
		} elseif ( is_string( $im ) ) {
			$u = trim( $im );
		}
		if ( $u === '' ) {
			continue;
		}
		$already = false;
		foreach ( $imgs_list as $existing ) {
			if ( $existing['url'] === $u ) {
				$already = true;
				break;
			}
		}
		if ( ! $already ) {
			$imgs_list[] = array(
				'url'       => $u,
				'url_large' => function_exists( 'centinela_syscom_image_url_large' ) ? centinela_syscom_image_url_large( $u ) : $u,
			);
		}
	}
	// Ordenar por "orden" si existe en la API
	if ( ! empty( $api_imgs ) && is_array( $api_imgs[0] ) && isset( $api_imgs[0]['orden'] ) ) {
		$with_order = array();
		$idx        = 0;
		foreach ( $imgs_list as $item ) {
			$orden = 999;
			foreach ( $api_imgs as $im ) {
				$ou = is_array( $im ) && isset( $im['url'] ) ? $im['url'] : ( is_array( $im ) && isset( $im['imagen'] ) ? $im['imagen'] : ( is_string( $im ) ? $im : '' ) );
				if ( $ou === $item['url'] ) {
					$orden = isset( $im['orden'] ) ? (int) $im['orden'] : $idx;
					break;
				}
			}
			$with_order[] = array( 'orden' => $orden, 'item' => $item );
			$idx++;
		}
		usort( $with_order, function ( $a, $b ) {
			return $a['orden'] - $b['orden'];
		} );
		$imgs_list = array_map( function ( $e ) {
			return $e['item'];
		}, $with_order );
	}
	return $imgs_list;
}

/**
 * REST: detalle de producto para vista rápida (modal). Incluye todas las imágenes y URL en alta resolución para zoom.
 */
function centinela_tienda_quickview_route() {
	register_rest_route( 'centinela/v1', '/producto-quick-view', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'type'              => 'string',
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
		),
		'callback' => function ( $request ) {
			$id = $request->get_param( 'id' );
			if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
				return new WP_REST_Response( array( 'error' => 'API no disponible' ), 404 );
			}
			$producto = Centinela_Syscom_API::get_producto( $id, true );
			if ( is_wp_error( $producto ) || empty( $producto['titulo'] ) ) {
				return new WP_REST_Response( array( 'error' => 'Producto no encontrado' ), 404 );
			}
			$precios     = isset( $producto['precios'] ) && is_array( $producto['precios'] ) ? $producto['precios'] : array();
			$precio_esp  = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_descuento'] ) ? $precios['precio_descuento'] : '' );
			$precio_lista = isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '';
			$precio_mostrar = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
			if ( $precio_mostrar === '' ) {
				$precio_mostrar = $precio_esp ?: $precio_lista;
			}
			$precio_raw  = function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_mostrar ) : $precio_mostrar;
			// URL legacy /tienda/producto/ID-slug/ para que "Ver producto" en quickview lleve al detalle.
			$url = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $id, isset( $producto['titulo'] ) ? $producto['titulo'] : '', '' ) : home_url( '/tienda/producto/' . $id . '/' );
			$categorias  = isset( $producto['categorías'] ) ? $producto['categorías'] : ( isset( $producto['categorias'] ) ? $producto['categorias'] : array() );
			$categoria   = '';
			if ( is_array( $categorias ) && ! empty( $categorias ) ) {
				$primera = $categorias[0];
				$categoria = is_array( $primera ) && isset( $primera['nombre'] ) ? $primera['nombre'] : ( is_object( $primera ) && isset( $primera->nombre ) ? $primera->nombre : '' );
			}
			$modelo = isset( $producto['modelo'] ) ? trim( (string) $producto['modelo'] ) : '';
			$marca  = isset( $producto['marca'] ) ? trim( (string) $producto['marca'] ) : '';

			$imgs_list = function_exists( 'centinela_get_producto_imagenes' ) ? centinela_get_producto_imagenes( $producto ) : array();
			$imgs_urls = array_map( function ( $e ) {
				return $e['url'];
			}, $imgs_list );
			$imgs_urls_large = array_map( function ( $e ) {
				return $e['url_large'];
			}, $imgs_list );
			$img_portada = isset( $imgs_urls[0] ) ? $imgs_urls[0] : ( isset( $producto['img_portada'] ) ? trim( $producto['img_portada'] ) : '' );

			$precio_formateado = ( $precio_mostrar !== '' && function_exists( 'centinela_format_precio_cop' ) ) ? centinela_format_precio_cop( $precio_mostrar ) : '';
			$stock = function_exists( 'centinela_syscom_producto_inventario' ) ? centinela_syscom_producto_inventario( $producto ) : null;
			return new WP_REST_Response( array(
				'id'             => $id,
				'titulo'         => $producto['titulo'],
				'precio'         => $precio_raw,
				'precio_formateado' => $precio_formateado,
				'precio_lista'   => $precio_lista,
				'categoria'      => $categoria,
				'modelo'         => $modelo,
				'marca'          => $marca,
				'imagenes'       => $imgs_urls,
				'imagenes_large' => $imgs_urls_large,
				'img_portada'    => $img_portada,
				'url'            => $url,
				'stock'          => $stock !== null && $stock !== '' ? $stock : null,
			), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_tienda_quickview_route' );

/**
 * Extrae secciones (características por grupo) y videos de un producto para mostrarlos estilo Syscom (3 columnas + videos).
 *
 * @param array $producto Producto tal como lo devuelve Centinela_Syscom_API::get_producto().
 * @return array { 'videos' => [ ['url' => '', 'titulo' => ''], ... ], 'secciones' => [ ['titulo' => '', 'items' => [] o 'html' => ''], ... ] }
 */
function centinela_producto_secciones_y_videos( $producto ) {
	$videos    = array();
	$secciones = array();

	if ( ! is_array( $producto ) ) {
		return array( 'videos' => $videos, 'secciones' => $secciones );
	}

	// --- Videos: producto['videos'], producto['video'], producto['recursos'] ---
	if ( isset( $producto['videos'] ) && is_array( $producto['videos'] ) ) {
		foreach ( $producto['videos'] as $v ) {
			$url = is_array( $v ) ? ( isset( $v['url'] ) ? $v['url'] : ( isset( $v['src'] ) ? $v['src'] : '' ) ) : ( is_string( $v ) ? $v : '' );
			$url = trim( (string) $url );
			if ( $url !== '' ) {
				$titulo = is_array( $v ) && isset( $v['titulo'] ) ? $v['titulo'] : ( isset( $v['title'] ) ? $v['title'] : '' );
				$videos[] = array( 'url' => $url, 'titulo' => trim( (string) $titulo ) );
			}
		}
	}
	if ( empty( $videos ) && ! empty( $producto['video'] ) ) {
		$url = is_array( $producto['video'] ) ? ( $producto['video']['url'] ?? $producto['video']['src'] ?? '' ) : (string) $producto['video'];
		if ( trim( $url ) !== '' ) {
			$videos[] = array( 'url' => trim( $url ), 'titulo' => '' );
		}
	}
	if ( empty( $videos ) && isset( $producto['recursos'] ) && is_array( $producto['recursos'] ) ) {
		foreach ( $producto['recursos'] as $r ) {
			$tipo = is_array( $r ) ? ( $r['tipo'] ?? $r['type'] ?? '' ) : '';
			if ( stripos( (string) $tipo, 'video' ) !== false ) {
				$url = is_array( $r ) ? ( $r['url'] ?? $r['src'] ?? $r['enlace'] ?? '' ) : (string) $r;
				$url = trim( (string) $url );
				if ( $url !== '' ) {
					$titulo = is_array( $r ) ? ( $r['titulo'] ?? $r['title'] ?? '' ) : '';
					$videos[] = array( 'url' => $url, 'titulo' => trim( (string) $titulo ) );
				}
			}
		}
	}

	// --- Secciones: API puede devolver secciones, especificaciones o caracteristicas agrupadas ---
	if ( isset( $producto['secciones'] ) && is_array( $producto['secciones'] ) ) {
		foreach ( $producto['secciones'] as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$titulo = isset( $sec['titulo'] ) ? $sec['titulo'] : ( isset( $sec['nombre'] ) ? $sec['nombre'] : ( isset( $sec['title'] ) ? $sec['title'] : '' ) );
			$titulo = trim( (string) $titulo );
			if ( isset( $sec['items'] ) && is_array( $sec['items'] ) ) {
				$secciones[] = array( 'titulo' => $titulo, 'items' => $sec['items'] );
			} elseif ( isset( $sec['html'] ) && (string) $sec['html'] !== '' ) {
				$secciones[] = array( 'titulo' => $titulo, 'html' => $sec['html'] );
			} elseif ( isset( $sec['contenido'] ) ) {
				$secciones[] = array( 'titulo' => $titulo, 'html' => (string) $sec['contenido'] );
			}
		}
	}
	if ( empty( $secciones ) && isset( $producto['especificaciones'] ) && is_array( $producto['especificaciones'] ) ) {
		foreach ( $producto['especificaciones'] as $sec ) {
			if ( ! is_array( $sec ) ) {
				continue;
			}
			$titulo = isset( $sec['titulo'] ) ? $sec['titulo'] : ( isset( $sec['nombre'] ) ? $sec['nombre'] : '' );
			$titulo = trim( (string) $titulo );
			if ( isset( $sec['items'] ) && is_array( $sec['items'] ) ) {
				$secciones[] = array( 'titulo' => $titulo, 'items' => $sec['items'] );
			}
		}
	}
	// caracteristicas como objeto: { "Título sección": [ "item1", "item2" ], ... }
	if ( empty( $secciones ) && isset( $producto['caracteristicas'] ) && is_array( $producto['caracteristicas'] ) ) {
		$first_key = array_key_first( $producto['caracteristicas'] );
		if ( $first_key !== null && ! is_int( $first_key ) ) {
			foreach ( $producto['caracteristicas'] as $titulo => $items ) {
				$titulo = trim( (string) $titulo );
				$list   = is_array( $items ) ? $items : ( $items !== '' ? array( $items ) : array() );
				$secciones[] = array( 'titulo' => $titulo, 'items' => $list );
			}
		}
	}
	// Parsear descripcion HTML o texto con **Título:** y listas
	if ( empty( $secciones ) && ! empty( $producto['descripcion'] ) ) {
		$desc    = $producto['descripcion'];
		$is_html = preg_match( '/<(h[2-4]|ul|li|p)\b/i', $desc );
		if ( $is_html ) {
			// Dividir por inicio de <h2>, <h3> o <h4> y extraer título + contenido hasta el siguiente heading
			$chunks = preg_split( '/(?=<\s*h[2-4]\s[^>]*>)/i', $desc );
			foreach ( $chunks as $chunk ) {
				$chunk = trim( $chunk );
				if ( $chunk === '' ) {
					continue;
				}
				if ( preg_match( '/<\s*h[2-4]\s[^>]*>([^<]*)<\s*\/\s*h[2-4]\s*>/is', $chunk, $m ) ) {
					$titulo = trim( wp_strip_all_tags( $m[1] ) );
					$rest   = trim( substr( $chunk, strlen( $m[0] ) ) );
					$secciones[] = array( 'titulo' => $titulo, 'html' => $rest !== '' ? $rest : '' );
				} elseif ( trim( wp_strip_all_tags( $chunk ) ) !== '' ) {
					$secciones[] = array( 'titulo' => _x( 'Descripción', 'producto', 'centinela-group-theme' ), 'html' => $chunk );
				}
			}
			// Si no hubo headings h2/h3/h4, intentar dividir por <strong>...</strong> o <b>...</b> (estilo Syscom)
			if ( empty( $secciones ) && preg_match( '/<(?:strong|b)\b[^>]*>/i', $desc ) ) {
				$chunks = preg_split( '/<(?:strong|b)\s[^>]*>([^<]*)<\s*\/(?:strong|b)\s*>\s*:?\s*/i', $desc, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
				$i = 0;
				while ( $i < count( $chunks ) ) {
					$titulo = trim( wp_strip_all_tags( $chunks[ $i ] ) );
					$i++;
					$content = isset( $chunks[ $i ] ) ? trim( $chunks[ $i ] ) : '';
					$i++;
					if ( $titulo !== '' && $content !== '' ) {
						$secciones[] = array( 'titulo' => $titulo, 'html' => $content );
					}
				}
			}
			if ( empty( $secciones ) && trim( $desc ) !== '' ) {
				$secciones[] = array( 'titulo' => _x( 'Descripción', 'producto', 'centinela-group-theme' ), 'html' => $desc );
			}
		} else {
			// Texto plano: dividir por **Título:** o **Título**
			$blocks = preg_split( '/\*\*([^*]+)\*\*\s*:?\s*/', $desc, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
			$i = 0;
			while ( $i < count( $blocks ) ) {
				$titulo = trim( $blocks[ $i ] );
				$i++;
				$content = isset( $blocks[ $i ] ) ? trim( $blocks[ $i ] ) : '';
				$i++;
				$items = array();
				foreach ( preg_split( '/\r\n|\n|\r/', $content ) as $line ) {
					$line = trim( $line );
					if ( $line === '' ) {
						continue;
					}
					if ( preg_match( '/^[\*\-]\s*(.+)$/', $line, $m ) ) {
						$items[] = trim( $m[1] );
					} else {
						$items[] = $line;
					}
				}
				if ( $titulo !== '' ) {
					$secciones[] = array( 'titulo' => $titulo, 'items' => $items );
				}
			}
			if ( empty( $secciones ) && trim( $desc ) !== '' ) {
				$secciones[] = array( 'titulo' => _x( 'Descripción', 'producto', 'centinela-group-theme' ), 'html' => wp_kses_post( wpautop( $desc ) ) );
			}
		}
	}
	// Fallback: una sola sección con la lista plana de caracteristicas
	if ( empty( $secciones ) && ! empty( $caracteristicas_flat = isset( $producto['caracteristicas'] ) && is_array( $producto['caracteristicas'] ) ? $producto['caracteristicas'] : array() ) ) {
		$items = array();
		foreach ( $caracteristicas_flat as $c ) {
			if ( is_string( $c ) && $c !== '' ) {
				$items[] = $c;
			}
		}
		if ( ! empty( $items ) ) {
			$secciones[] = array( 'titulo' => __( 'Características', 'centinela-group-theme' ), 'items' => $items );
		}
	}

	return array( 'videos' => $videos, 'secciones' => $secciones );
}

/**
 * Extrae URLs de YouTube y Vimeo de un texto/HTML (para mostrar embeds sobre cada columna de características).
 *
 * @param string $html Contenido HTML o texto.
 * @return array Lista de URLs únicas (YouTube y Vimeo).
 */
function centinela_extract_video_urls_from_html( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return array();
	}
	$urls = array();
	// Enlaces en href
	if ( preg_match_all( '/href=["\']([^"\']*(?:youtube\.com|youtu\.be|vimeo\.com)[^"\']*)["\']/i', $html, $m ) ) {
		foreach ( $m[1] as $url ) {
			$url = trim( $url );
			if ( $url !== '' && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}
	}
	// URLs sueltas en el texto (ej. Syscom)
	if ( preg_match_all( '/(?:https?:)?\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/|vimeo\.com\/(?:video\/)?\d+)[a-zA-Z0-9?=&_\-\.\/]*/i', $html, $m ) ) {
		foreach ( $m[0] as $raw ) {
			$url = ( strpos( $raw, 'http' ) === 0 ) ? $raw : 'https:' . $raw;
			$url = trim( $url );
			if ( $url !== '' && ! in_array( $url, $urls, true ) ) {
				$urls[] = $url;
			}
		}
	}
	return array_values( array_unique( $urls ) );
}

/**
 * Elimina de la descripción el bloque completo .row que contiene .col-sm-4 (características),
 * para que ese contenido solo se muestre en el tab Características.
 *
 * @param string $html Descripción HTML del producto.
 * @return string HTML sin el bloque row de características.
 */
function centinela_remove_caracteristicas_block_from_description( $html ) {
	if ( ! is_string( $html ) || $html === '' || strpos( $html, 'col-sm-4' ) === false ) {
		return $html;
	}
	if ( ! preg_match( '/<div[^>]*\bclass\s*=\s*["\'][^"\']*\brow\b[^"\']*["\'][^>]*>/i', $html, $m ) ) {
		return $html;
	}
	$start = strpos( $html, $m[0] );
	$pos   = $start + strlen( $m[0] );
	$len   = strlen( $html );
	$depth = 1;
	while ( $depth > 0 && $pos < $len ) {
		$next_open  = stripos( $html, '<div', $pos );
		$next_close = stripos( $html, '</div>', $pos );
		if ( $next_close === false ) {
			break;
		}
		if ( $next_open !== false && $next_open < $next_close ) {
			$depth++;
			$pos = $next_open + 4;
		} else {
			$depth--;
			$pos = $next_close + 6;
		}
	}
	$end = $pos <= $len ? $pos : $len;
	$before = substr( $html, 0, $start );
	$after  = substr( $html, $end );
	return trim( $before . $after );
}

/**
 * Normaliza una URL de YouTube/Vimeo a una clave única (id de video) para comparar duplicados.
 *
 * @param string $url URL completa.
 * @return string|null Clave "yt:ID" o "vm:ID" o null.
 */
function centinela_video_url_to_key( $url ) {
	if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m ) ) {
		return 'yt:' . $m[1];
	}
	if ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m ) ) {
		return 'vm:' . $m[1];
	}
	return null;
}

/**
 * Reordena el HTML del tab Especificaciones: 1) Columnas de características, 2) Imágenes, 3) Vídeos.
 * Si no hay estructura de columnas, devuelve el HTML procesado por centinela_inject_video_embeds_above_cols.
 *
 * @param string $html Descripción HTML del producto.
 * @return string HTML reordenado.
 */
function centinela_reorder_especificaciones( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}
	$html = str_replace( array( 'col-sm-6 col-md-4', 'col-sm-6  col-md-4' ), 'col-sm-4 col-md-4', $html );
	$enc  = mb_detect_encoding( $html, array( 'UTF-8', 'ISO-8859-1' ), true );
	if ( $enc ) {
		$html = mb_convert_encoding( $html, 'HTML-ENTITIES', $enc );
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<div id="centinela-desc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	$root  = $doc->getElementById( 'centinela-desc-root' );
	if ( ! $root ) {
		return $html;
	}

	// 1) Filas que contienen columnas (.row con .col-sm-4 o .col-md-4)
	$rows = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' row ')]" );
	if ( ! $rows || $rows->length === 0 ) {
		$rows = $xpath->query( "//*[contains(@class, 'row')]" );
	}
	$column_rows = array();
	foreach ( $rows as $row ) {
		$cols = $xpath->query( ".//*[contains(@class, 'col-sm-4') or contains(@class, 'col-md-4')]", $row );
		if ( $cols && $cols->length > 0 ) {
			$column_rows[] = $row;
		}
	}

	// Evitar duplicar: si un .row está dentro de otro .row de la lista, solo conservar el ancestro (nivel superior).
	$top_level_rows = array();
	foreach ( $column_rows as $row ) {
		$has_ancestor_in_list = false;
		$parent = $row->parentNode;
		while ( $parent && $parent->nodeType === XML_ELEMENT_NODE ) {
			if ( in_array( $parent, $column_rows, true ) ) {
				$has_ancestor_in_list = true;
				break;
			}
			$parent = $parent->parentNode;
		}
		if ( ! $has_ancestor_in_list ) {
			$top_level_rows[] = $row;
		}
	}
	$column_rows = $top_level_rows;

	$skip_video_ids = array( '9u9JDJ7tAuc' );
	$column_html    = '';
	$image_html     = '';
	$video_html     = '';

	if ( ! empty( $column_rows ) ) {
		foreach ( $column_rows as $row ) {
			$clone = $row->cloneNode( true );
			// Quitar imágenes del clon (se mostrarán en la sección de imágenes)
			$imgs = $xpath->query( './/img', $clone );
			$to_remove = array();
			foreach ( $imgs as $img ) {
				$to_remove[] = $img;
			}
			foreach ( $to_remove as $el ) {
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
			}
			// Quitar iframes y enlaces a video del clon (se mostrarán en la sección de vídeos)
			$media = $xpath->query( ".//iframe[contains(@src,'youtube') or contains(@src,'vimeo')] | .//a[contains(@href,'youtube') or contains(@href,'youtu.be') or contains(@href,'vimeo')]", $clone );
			$to_remove = array();
			foreach ( $media as $el ) {
				$to_remove[] = $el;
			}
			foreach ( $to_remove as $el ) {
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
			}
			// Quitar contenedores vacíos embed-responsive / embed-responsive-16by9 (dejan espacio sin contenido)
			$embed_wrappers = $xpath->query( ".//*[contains(@class, 'embed-responsive')]", $clone );
			$to_remove = array();
			foreach ( $embed_wrappers as $el ) {
				$to_remove[] = $el;
			}
			foreach ( $to_remove as $el ) {
				if ( $el->parentNode ) {
					$el->parentNode->removeChild( $el );
				}
			}
			$column_html .= $doc->saveHTML( $clone );
		}
	}

	// 2) Bloques que contienen imágenes (padre de cada img, sin duplicar)
	$all_imgs = $xpath->query( '//img' );
	$seen_parents = array();
	foreach ( $all_imgs as $img ) {
		$parent = $img->parentNode;
		if ( ! $parent || $parent->nodeType !== XML_ELEMENT_NODE ) {
			continue;
		}
		// Si el padre es el root, guardar solo la img
		if ( $parent instanceof DOMElement && $parent->getAttribute( 'id' ) === 'centinela-desc-root' ) {
			$image_html .= $doc->saveHTML( $img );
			continue;
		}
		$key = spl_object_id( $parent );
		if ( isset( $seen_parents[ $key ] ) ) {
			continue;
		}
		$seen_parents[ $key ] = true;
		$image_html .= $doc->saveHTML( $parent );
	}

	// 3) URLs de vídeo únicas y generar embeds
	$video_urls = array();
	$video_keys = array();
	$media_nodes = $xpath->query( "//iframe[contains(@src,'youtube') or contains(@src,'vimeo')] | //a[contains(@href,'youtube') or contains(@href,'youtu.be') or contains(@href,'vimeo')]" );
	foreach ( $media_nodes as $el ) {
		$href = $el->getAttribute( 'href' );
		$src  = $el->getAttribute( 'src' );
		$url  = $href ? $href : $src;
		if ( ! $url ) {
			continue;
		}
		$key = centinela_video_url_to_key( $url );
		if ( ! $key || in_array( $key, $video_keys, true ) ) {
			continue;
		}
		$yt_id = null;
		$vm_id = null;
		if ( preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $url, $m ) ) {
			$yt_id = $m[1];
		} elseif ( preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m ) ) {
			$vm_id = $m[1];
		}
		if ( $yt_id && in_array( $yt_id, $skip_video_ids, true ) ) {
			continue;
		}
		$video_keys[] = $key;
		$video_urls[] = array( 'url' => $url, 'yt_id' => $yt_id, 'vm_id' => $vm_id );
	}
	foreach ( $video_urls as $v ) {
		$src = '';
		if ( $v['yt_id'] ) {
			$src = 'https://www.youtube.com/embed/' . $v['yt_id'] . '?rel=0';
		} elseif ( $v['vm_id'] ) {
			$src = 'https://player.vimeo.com/video/' . $v['vm_id'];
		}
		if ( $src ) {
			$video_html .= '<div class="centinela-single-producto__video-embed"><iframe class="centinela-video-iframe" src="' . esc_url( $src ) . '" title="Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" width="100%" height="315"></iframe></div>';
		}
	}

	// Montar salida: 1) columnas (sin vídeos ni imágenes), 2) imágenes, 3) vídeos
	$out = '';
	if ( $column_html !== '' ) {
		$out .= '<div class="centinela-espec-seccion centinela-espec-columnas">' . $column_html . '</div>';
	}
	if ( $image_html !== '' ) {
		$out .= '<div class="centinela-espec-seccion centinela-espec-imagenes">' . $image_html . '</div>';
	}
	if ( $video_html !== '' ) {
		$out .= '<div class="centinela-espec-seccion centinela-espec-videos">' . $video_html . '</div>';
	}

	if ( $out === '' ) {
		// Sin columnas detectadas: devolver HTML original con inyección de vídeos en columnas si aplica
		if ( function_exists( 'centinela_inject_video_embeds_above_cols' ) ) {
			return centinela_inject_video_embeds_above_cols( $html );
		}
		return $html;
	}
	return $out;
}

/**
 * Inyecta el iframe del video correspondiente encima de cada .col-sm-4 (como en Syscom).
 * Evita duplicados: quita el enlace/iframe del contenido de la columna y del resto del HTML.
 * No embebe vídeos que Syscom no muestra (ej. 9u9JDJ7tAuc).
 *
 * @param string $html Descripción HTML del producto (con .row y .col-sm-4).
 * @return string HTML con video arriba de cada columna y sin duplicados.
 */
function centinela_inject_video_embeds_above_cols( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}
	// Normalizar clases de columna: col-sm-6 col-md-4 → col-sm-4 col-md-4 (como Syscom).
	$html = str_replace( array( 'col-sm-6 col-md-4', 'col-sm-6  col-md-4' ), 'col-sm-4 col-md-4', $html );
	if ( strpos( $html, 'col-sm-4' ) === false && strpos( $html, 'col-md-4' ) === false ) {
		return $html;
	}
	// IDs de video que no se muestran como embed en Syscom (solo enlace o no se muestran).
	$skip_video_ids = array( '9u9JDJ7tAuc' );
	$enc = mb_detect_encoding( $html, array( 'UTF-8', 'ISO-8859-1' ), true );
	if ( $enc ) {
		$html = mb_convert_encoding( $html, 'HTML-ENTITIES', $enc );
	}
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<div id="centinela-desc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	$xpath = new DOMXPath( $doc );
	// Columnas: col-sm-4 o col-md-4 (tras normalizar, suelen ser col-sm-4 col-md-4).
	$cols = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' col-sm-4 ') or contains(concat(' ', normalize-space(@class), ' '), ' col-md-4 ')]" );
	if ( ! $cols || $cols->length === 0 ) {
		$cols = $xpath->query( "//*[contains(@class, 'col-sm-4') or contains(@class, 'col-md-4')]" );
	}
	if ( ! $cols || $cols->length === 0 ) {
		return $html;
	}
	$embedded_keys = array();
	foreach ( $cols as $col ) {
		$inner_html = '';
		foreach ( $col->childNodes as $child ) {
			$inner_html .= $doc->saveHTML( $child );
		}
		$urls = centinela_extract_video_urls_from_html( $inner_html );
		if ( empty( $urls ) ) {
			continue;
		}
		$first_url  = trim( $urls[0] );
		$yt_id      = null;
		$vm_id      = null;
		$is_youtube = ( strpos( $first_url, 'youtube.com' ) !== false || strpos( $first_url, 'youtu.be' ) !== false );
		$is_vimeo   = ( strpos( $first_url, 'vimeo.com' ) !== false );
		if ( $is_youtube && preg_match( '/(?:youtube\.com\/(?:watch\?v=|embed\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/', $first_url, $yt ) ) {
			$yt_id = $yt[1];
		} elseif ( $is_vimeo && preg_match( '/vimeo\.com\/(?:video\/)?(\d+)/', $first_url, $vm ) ) {
			$vm_id = $vm[1];
		}
		if ( $yt_id === null && $vm_id === null ) {
			continue;
		}
		if ( $yt_id && in_array( $yt_id, $skip_video_ids, true ) ) {
			continue;
		}
		$wrap = $doc->createElement( 'div' );
		$wrap->setAttribute( 'class', 'centinela-col-video' );
		$inner = $doc->createElement( 'div' );
		$inner->setAttribute( 'class', 'centinela-single-producto__video-embed' );
		$iframe = $doc->createElement( 'iframe' );
		if ( $is_youtube && isset( $yt_id ) ) {
			$iframe->setAttribute( 'src', 'https://www.youtube.com/embed/' . $yt_id . '?rel=0' );
		} elseif ( $is_vimeo && isset( $vm_id ) ) {
			$iframe->setAttribute( 'src', 'https://player.vimeo.com/video/' . $vm_id );
		} else {
			continue;
		}
		$iframe->setAttribute( 'title', 'Video' );
		$iframe->setAttribute( 'class', 'centinela-video-iframe' );
		$iframe->setAttribute( 'allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' );
		$iframe->setAttribute( 'allowfullscreen', '' );
		$iframe->setAttribute( 'loading', 'lazy' );
		$iframe->setAttribute( 'width', '100%' );
		$iframe->setAttribute( 'height', '315' );
		$inner->appendChild( $iframe );
		$wrap->appendChild( $inner );
		$col->insertBefore( $wrap, $col->firstChild );
		$key = centinela_video_url_to_key( $first_url );
		if ( $key ) {
			$embedded_keys[] = $key;
		}
		// Quitar de esta columna el primer <a> o <iframe> que tenga este mismo video para no duplicar dentro de la columna.
		foreach ( $col->childNodes as $child ) {
			if ( ! $child instanceof DOMElement ) {
				continue;
			}
			if ( $child->getAttribute( 'class' ) === 'centinela-col-video' ) {
				continue;
			}
			$to_remove = null;
			foreach ( $xpath->query( './/a[contains(@href,"youtube") or contains(@href,"youtu.be") or contains(@href,"vimeo")] | .//iframe[contains(@src,"youtube") or contains(@src,"vimeo")]', $child ) as $el ) {
				$href = $el->getAttribute( 'href' );
				$src  = $el->getAttribute( 'src' );
				$k    = $href ? centinela_video_url_to_key( $href ) : ( $src ? centinela_video_url_to_key( $src ) : null );
				if ( $k && $k === $key ) {
					$to_remove = $el;
					break;
				}
			}
			if ( $to_remove && $to_remove->parentNode ) {
				$to_remove->parentNode->removeChild( $to_remove );
				break;
			}
		}
	}
	// Quitar del resto del documento cualquier otro iframe o enlace que repita un video ya embebido (ej. debajo de una imagen).
	if ( ! empty( $embedded_keys ) ) {
		$all_media = $xpath->query( "//iframe[contains(@src,'youtube') or contains(@src,'vimeo')] | //a[contains(@href,'youtube') or contains(@href,'youtu.be') or contains(@href,'vimeo')]" );
		$remove = array();
		foreach ( $all_media as $el ) {
			$href = $el->getAttribute( 'href' );
			$src  = $el->getAttribute( 'src' );
			$k    = $href ? centinela_video_url_to_key( $href ) : ( $src ? centinela_video_url_to_key( $src ) : null );
			if ( $k && in_array( $k, $embedded_keys, true ) ) {
				$remove[] = $el;
			}
		}
		foreach ( $remove as $el ) {
			if ( $el->parentNode ) {
				$el->parentNode->removeChild( $el );
			}
		}
	}
	$root = $doc->getElementById( 'centinela-desc-root' );
	if ( ! $root ) {
		return $html;
	}
	$out = '';
	foreach ( $root->childNodes as $child ) {
		$out .= $doc->saveHTML( $child );
	}
	return $out;
}
