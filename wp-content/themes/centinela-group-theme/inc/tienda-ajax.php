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
							<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__add-cart"><span class="centinela-tienda__overlay-btn-text"><?php esc_html_e( 'Agregar al carrito', 'centinela-group-theme' ); ?></span><svg class="centinela-header__cta-icon centinela-tienda__overlay-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
						</div>
					</div>
					<div class="centinela-tienda__card-body">
						<h2 class="centinela-tienda__card-title">
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $titulo ); ?></a>
						</h2>
						<?php if ( $precio ) : ?>
							<p class="centinela-tienda__card-price"><?php echo esc_html( $precio ); ?> COP</p>
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
			$precio_raw  = $precio_esp ?: $precio_lista;
			$precio_raw  = is_string( $precio_raw ) ? preg_replace( '/\s*COP\s*$/i', '', trim( $precio_raw ) ) : $precio_raw;
			$url         = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $id ) : home_url( '/producto/' . $id . '/' );
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

			return new WP_REST_Response( array(
				'id'             => $id,
				'titulo'         => $producto['titulo'],
				'precio'         => $precio_raw,
				'precio_lista'   => $precio_lista,
				'categoria'      => $categoria,
				'modelo'         => $modelo,
				'marca'          => $marca,
				'imagenes'       => $imgs_urls,
				'imagenes_large' => $imgs_urls_large,
				'img_portada'    => $img_portada,
				'url'            => $url,
			), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_tienda_quickview_route' );
