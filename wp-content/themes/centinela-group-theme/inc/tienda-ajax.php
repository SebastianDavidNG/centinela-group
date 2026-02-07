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
				$url    = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $pid, $titulo ) : home_url( '/tienda/producto/' . $pid . '/' );
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
			$url         = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $id, isset( $producto['titulo'] ) ? $producto['titulo'] : '' ) : home_url( '/tienda/producto/' . $id . '/' );
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
