<?php
/**
 * Búsqueda unificada: contenido del tema + productos Syscom.
 * Coincidencias flexibles para referencias (ej. DS-KIS203-T = DS KIS203 T).
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normaliza un término para enviarlo a la API Syscom (palabras separadas por +, minúsculas).
 * Ej: "DS KIS203 T" o "Monitor" → "ds+kis203+t" / "monitor" (búsqueda sin importar mayúsculas/minúsculas).
 *
 * @param string $term Término de búsqueda.
 * @return string Término listo para el parámetro busqueda de la API.
 */
function centinela_normalize_search_term_for_api( $term ) {
	$term = trim( (string) $term );
	if ( $term === '' ) {
		return '';
	}
	$normalized = trim( preg_replace( '/[\s\-_]+/', '+', $term ), '+' );
	$normalized = $normalized !== '' ? $normalized : $term;
	return strtolower( $normalized );
}

/**
 * Normaliza una cadena para comparación flexible (quitar espacios, guiones, guiones bajos; minúsculas).
 * Así "DS-KIS203-T" y "DS KIS203 T" dan la misma cadena y podemos matchear.
 *
 * @param string $str Cadena (modelo, título, referencia).
 * @return string Cadena normalizada.
 */
function centinela_normalize_for_match( $str ) {
	if ( $str === '' || $str === null ) {
		return '';
	}
	$s = preg_replace( '/[\s\-_]+/', '', (string) $str );
	return strtolower( $s );
}

/**
 * Obtiene la marca de un producto (string) para búsqueda.
 *
 * @param array $producto Producto de la API.
 * @return string Marca o vacío.
 */
function centinela_search_producto_marca( $producto ) {
	if ( ! is_array( $producto ) ) {
		return '';
	}
	if ( isset( $producto['marca'] ) ) {
		$m = $producto['marca'];
		if ( is_array( $m ) && isset( $m['nombre'] ) ) {
			return trim( (string) $m['nombre'] );
		}
		if ( trim( (string) $m ) !== '' ) {
			return trim( (string) $m );
		}
	}
	foreach ( array( 'brand', 'fabricante' ) as $key ) {
		if ( isset( $producto[ $key ] ) ) {
			$val = $producto[ $key ];
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
 * Indica si el término de búsqueda coincide con el producto (título, modelo, sku, codigo, marca).
 * Usa comparación normalizada para que "DS KIS203 T" coincida con modelo "DS-KIS203-T".
 *
 * @param string $term     Término de búsqueda.
 * @param array  $producto  Producto con titulo, modelo, sku, codigo, marca.
 * @return bool True si hay coincidencia.
 */
function centinela_producto_matches_search( $term, $producto ) {
	if ( ! is_array( $producto ) || $term === '' ) {
		return false;
	}
	$q = centinela_normalize_for_match( $term );
	if ( $q === '' ) {
		return false;
	}
	// Título/nombre en varias claves (API puede devolver titulo, nombre, name, title).
	$campos = array( 'titulo', 'nombre', 'name', 'title', 'modelo', 'sku', 'codigo', 'descripcion', 'description' );
	foreach ( $campos as $key ) {
		if ( ! isset( $producto[ $key ] ) || $producto[ $key ] === '' ) {
			continue;
		}
		$val = is_string( $producto[ $key ] ) ? $producto[ $key ] : (string) $producto[ $key ];
		if ( $val === '' ) {
			continue;
		}
		$norm = centinela_normalize_for_match( $val );
		// Coincidencia exacta normalizada o el término está contenido en el valor normalizado.
		if ( $norm === $q || strpos( $norm, $q ) !== false || strpos( $q, $norm ) !== false ) {
			return true;
		}
	}
	// Búsqueda por marca (nombre o referencia de marca).
	$marca = centinela_search_producto_marca( $producto );
	if ( $marca !== '' ) {
		$norm = centinela_normalize_for_match( $marca );
		if ( $norm === $q || strpos( $norm, $q ) !== false || strpos( $q, $norm ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Intenta extraer una URL de imagen de un producto Syscom desde distintas variantes de campos.
 *
 * @param array $producto Datos de producto (listado o detalle).
 * @return string URL de imagen o vacío.
 */
function centinela_search_extract_producto_image( $producto ) {
	if ( ! is_array( $producto ) ) {
		return '';
	}
	$imagen = '';
	foreach ( array( 'img_portada', 'imagen', 'image', 'imagen_url', 'image_url', 'url_imagen', 'thumb', 'thumbnail', 'foto', 'portada' ) as $img_key ) {
		if ( isset( $producto[ $img_key ] ) && is_string( $producto[ $img_key ] ) && trim( $producto[ $img_key ] ) !== '' ) {
			$imagen = trim( (string) $producto[ $img_key ] );
			break;
		}
	}
	if ( $imagen === '' && function_exists( 'centinela_get_producto_imagenes' ) ) {
		$imgs_list = centinela_get_producto_imagenes( $producto );
		$imagen    = ! empty( $imgs_list[0]['url'] ) ? trim( (string) $imgs_list[0]['url'] ) : '';
	}
	if ( $imagen === '' && ! empty( $producto['imagenes'] ) && is_array( $producto['imagenes'] ) ) {
		$first = reset( $producto['imagenes'] );
		$imagen = is_string( $first ) ? $first : (
			isset( $first['url'] ) ? $first['url'] : (
				isset( $first['imagen'] ) ? $first['imagen'] : (
					isset( $first['src'] ) ? $first['src'] : (
						isset( $first['image'] ) ? $first['image'] : (
							isset( $first['thumb'] ) ? $first['thumb'] : ''
						)
					)
				)
			)
		);
	}
	if ( $imagen === '' && ! empty( $producto['gallery'] ) && is_array( $producto['gallery'] ) ) {
		$first_gallery = reset( $producto['gallery'] );
		$imagen = is_string( $first_gallery ) ? $first_gallery : ( isset( $first_gallery['url'] ) ? $first_gallery['url'] : '' );
	}
	return is_string( $imagen ) ? trim( $imagen ) : '';
}

/**
 * Construye candidatos de imagen "tipo Syscom" usando patrón -g.
 * Ejemplo: /ACCESSPRO/AP1000HD/AP1000HD-g.PNG
 *
 * @param string $marca  Marca del producto.
 * @param string $modelo Modelo/referencia del producto.
 * @return array Lista de URLs candidatas.
 */
function centinela_search_build_syscom_g_image_candidates( $marca, $modelo ) {
	$marca  = trim( (string) $marca );
	$modelo = trim( (string) $modelo );
	if ( $marca === '' || $modelo === '' ) {
		return array();
	}
	$base = 'https://ftp3.syscom.mx/usuarios/fotos/BancoFotografiasSyscom/'
		. rawurlencode( $marca ) . '/'
		. rawurlencode( $modelo ) . '/'
		. rawurlencode( $modelo );
	$candidates = array(
		$base . '-g.PNG',
		$base . '-g.png',
		$base . '-g.JPG',
		$base . '-g.jpg',
		$base . '-g.WEBP',
		$base . '-g.webp',
		$base . '.PNG',
		$base . '.png',
		$base . '.JPG',
		$base . '.jpg',
	);
	return array_values( array_unique( $candidates ) );
}

/**
 * Busca productos en la API Syscom con coincidencias flexibles.
 * Prueba: término normalizado (DS+KIS203+T), primer segmento, y término original; combina y deduplica;
 * luego filtra en PHP por coincidencia en título/modelo/sku/codigo (normalizado).
 *
 * @param string $query Término de búsqueda.
 * @param int    $limit Número máximo de productos a devolver (por defecto 12).
 * @return array Lista de productos (cada uno con id, titulo, modelo, url, precio_lista, etc.).
 */
/**
 * Extrae el array de productos de una respuesta (productos, data o items).
 *
 * @param array $resp Respuesta de Centinela_Syscom_API::get_productos().
 * @return array Lista de productos.
 */
function centinela_search_get_productos_from_response( $resp ) {
	if ( ! is_array( $resp ) ) {
		return array();
	}
	$lista = array();
	if ( ! empty( $resp['productos'] ) && is_array( $resp['productos'] ) ) {
		$lista = $resp['productos'];
	} elseif ( ! empty( $resp['data']['productos'] ) && is_array( $resp['data']['productos'] ) ) {
		$lista = $resp['data']['productos'];
	} elseif ( ! empty( $resp['data'] ) && is_array( $resp['data'] ) ) {
		$lista = $resp['data'];
	} elseif ( ! empty( $resp['items'] ) && is_array( $resp['items'] ) ) {
		$lista = $resp['items'];
	}
	// Si cada ítem viene anidado en clave 'producto', extraerlo.
	$out = array();
	foreach ( $lista as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		if ( isset( $item['producto'] ) && is_array( $item['producto'] ) ) {
			$out[] = $item['producto'];
		} else {
			$out[] = $item;
		}
	}
	return $out;
}

/**
 * @param string $query Término de búsqueda.
 * @param int    $limit Número máximo de productos.
 * @param bool   $fast  Si true, solo 1–2 páginas y busqueda API (para sugerencias en el overlay).
 */
function centinela_search_productos_syscom( $query, $limit = 12, $fast = false ) {
	$query = trim( (string) $query );
	if ( $query === '' || strlen( $query ) < 2 ) {
		return array();
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	if ( $fast ) {
		$cache_key = 'centinela_search_fast_' . md5( strtolower( $query ) . '|' . (int) $limit );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}

	$ids_vistos = array();
	$productos_raw = array();
	$busqueda_api = centinela_normalize_search_term_for_api( $query );
	$get_lista = function ( $resp ) {
		return function_exists( 'centinela_search_get_productos_from_response' ) ? centinela_search_get_productos_from_response( $resp ) : ( isset( $resp['productos'] ) ? $resp['productos'] : array() );
	};

	// Siempre intentar primero la API con busqueda (menos llamadas y resultados más relevantes).
	// Variantes extra para que referencias tipo "AP-1000" / "AP 1000" / "AP1000" coincidan mejor.
	$var_con_espacios = strtolower( preg_replace( '/-+/', ' ', (string) $query ) );
	$var_sin_sep      = strtolower( preg_replace( '/[-_\\s]+/', '', (string) $query ) );
	$variantes_busqueda = array_unique( array(
		$busqueda_api,
		strtolower( $query ),
		$query,
		$var_con_espacios,
		$var_sin_sep,
	) );
	$query_is_likely_brand = preg_match( '/^[a-zA-Z\s]{4,}$/', $query ) === 1;
	if ( $fast && ! $query_is_likely_brand ) {
		// Referencia/modelo en sugerencias: pocas variantes de alto valor para responder rápido.
		$variantes_busqueda = array_values( array_unique( array(
			$busqueda_api,
			$var_sin_sep,
			$var_con_espacios,
		) ) );
	}
	// En sugerencias, limitar páginas para evitar demoras largas en búsquedas por marca.
	$api_max_paginas = $fast ? ( $query_is_likely_brand ? 10 : 5 ) : 8;
	foreach ( $variantes_busqueda as $term_api ) {
		if ( $term_api === '' ) {
			continue;
		}
		for ( $api_pag = 1; $api_pag <= $api_max_paginas; $api_pag++ ) {
			$resp = Centinela_Syscom_API::get_productos( array(
				'busqueda' => $term_api,
				'pagina'   => $api_pag,
				'orden'    => 'relevancia',
				'cop'      => true,
			) );
			$lista = $get_lista( $resp );
			if ( empty( $lista ) ) {
				break;
			}
			foreach ( $lista as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				// Filtrar localmente para coincidencias flexibles (referencias con guiones/espacios).
				if ( ! centinela_producto_matches_search( $query, $p ) ) {
					continue;
				}
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				if ( $pid === '' ) {
					continue;
				}
				$pid = (string) $pid;
				if ( ! empty( $ids_vistos[ $pid ] ) ) {
					continue;
				}
				$productos_raw[] = $p;
				$ids_vistos[ $pid ] = true;
			}
			if ( count( $productos_raw ) >= $limit ) {
				break;
			}
		}
		if ( $fast ) {
			// Marca: no recorrer variantes extra cuando ya hay volumen suficiente para sugerencias.
			if ( $query_is_likely_brand && count( $productos_raw ) >= min( $limit, 40 ) ) {
				return centinela_search_build_productos_output( $productos_raw, $query, $limit, $fast );
			}
			// Referencia/modelo: reunir un bloque suficiente para no truncar resultados en desktop.
			if ( ! $query_is_likely_brand && count( $productos_raw ) >= min( $limit, 20 ) ) {
				return centinela_search_build_productos_output( $productos_raw, $query, $limit, $fast );
			}
		}
	}

	// Barrido por páginas del catálogo (con primera categoría si hace falta). En modo rápido solo 2 páginas.
	$max_paginas = $fast ? ( $query_is_likely_brand ? 2 : 1 ) : 15;
	$categoria_id = '';
	for ( $pag = 1; $pag <= $max_paginas; $pag++ ) {
		$args = array(
			'pagina' => $pag,
			'orden'  => 'relevancia',
			'cop'    => true,
		);
		if ( $categoria_id !== '' ) {
			$args['categoria'] = $categoria_id;
		}
		$resp = Centinela_Syscom_API::get_productos( $args );
		$lista = $get_lista( $resp );
		if ( empty( $lista ) && $pag === 1 && $categoria_id === '' && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
			$arbol = Centinela_Syscom_API::get_categorias_arbol();
			if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
				$categoria_id = (string) $arbol[0]['id'];
				$args['categoria'] = $categoria_id;
				$args['pagina']    = 1;
				$resp = Centinela_Syscom_API::get_productos( $args );
				$lista = $get_lista( $resp );
			}
		}
		if ( empty( $lista ) ) {
			continue;
		}
		foreach ( $lista as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			if ( ! centinela_producto_matches_search( $query, $p ) ) {
				continue;
			}
			$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
			if ( $pid === '' ) {
				continue;
			}
			$pid = (string) $pid;
			if ( ! empty( $ids_vistos[ $pid ] ) ) {
				continue;
			}
			$productos_raw[] = $p;
			$ids_vistos[ $pid ] = true;
		}
		if ( count( $productos_raw ) >= $limit * ( $fast ? 1 : 3 ) ) {
			break;
		}
	}

	$result = centinela_search_build_productos_output( $productos_raw, $query, $limit, $fast );
	if ( $fast ) {
		// TTL corto para sugerencias: acelera tecleo repetido sin estancar resultados.
		set_transient( $cache_key, $result, 8 * MINUTE_IN_SECONDS );
	}
	return $result;
}

/**
 * Ordena y formatea el array de productos raw para la búsqueda.
 *
 * @param array  $productos_raw Productos crudos de la API.
 * @param string $query        Término de búsqueda.
 * @param int    $limit        Máximo a devolver.
 * @param bool   $fast         Si true, modo sugerencias.
 * @return array Lista con id, titulo, modelo, url, precio_lista, imagen.
 */
function centinela_search_build_productos_output( $productos_raw, $query, $limit, $fast = false ) {
	$query_norm = centinela_normalize_for_match( $query );
	usort( $productos_raw, function ( $a, $b ) use ( $query, $query_norm ) {
		$ma = centinela_producto_matches_search( $query, $a ) ? 1 : 0;
		$mb = centinela_producto_matches_search( $query, $b ) ? 1 : 0;
		if ( $mb !== $ma ) {
			return $mb - $ma;
		}
		$ta = centinela_normalize_for_match( isset( $a['titulo'] ) ? $a['titulo'] : ( isset( $a['nombre'] ) ? $a['nombre'] : '' ) );
		$tb = centinela_normalize_for_match( isset( $b['titulo'] ) ? $b['titulo'] : ( isset( $b['nombre'] ) ? $b['nombre'] : '' ) );
		$modela = centinela_normalize_for_match(
			isset( $a['modelo'] ) ? $a['modelo'] : ( isset( $a['sku'] ) ? $a['sku'] : ( isset( $a['codigo'] ) ? $a['codigo'] : '' ) )
		);
		$modelb = centinela_normalize_for_match(
			isset( $b['modelo'] ) ? $b['modelo'] : ( isset( $b['sku'] ) ? $b['sku'] : ( isset( $b['codigo'] ) ? $b['codigo'] : '' ) )
		);
		$marka = centinela_normalize_for_match( centinela_search_producto_marca( $a ) );
		$markb = centinela_normalize_for_match( centinela_search_producto_marca( $b ) );

		$pa = 0;
		$pb = 0;
		$is_ap_turnstile_query = in_array( $query_norm, array( 'ap1000', 'ap2000', 'ap5000' ), true );
		if ( $is_ap_turnstile_query ) {
			// Prioridad comercial acordada: torniquete industrial AccessPRO arriba para AP1000/2000/5000.
			$torniquete_a = strpos( $ta, 'torniqueteindustrialenaceroinoxidable' ) !== false
				&& strpos( $ta, 'bidireccional' ) !== false;
			$torniquete_b = strpos( $tb, 'torniqueteindustrialenaceroinoxidable' ) !== false
				&& strpos( $tb, 'bidireccional' ) !== false;
			if ( $torniquete_a ) {
				$pa += 220;
			}
			if ( $torniquete_b ) {
				$pb += 220;
			}
		}
		// Prioridad fuerte para coincidencia en referencia/modelo.
		if ( $modela === $query_norm ) {
			$pa += 120;
		} elseif ( strpos( $modela, $query_norm ) !== false ) {
			$pa += 70;
		}
		if ( $modelb === $query_norm ) {
			$pb += 120;
		} elseif ( strpos( $modelb, $query_norm ) !== false ) {
			$pb += 70;
		}
		// Marca y título.
		if ( $marka === $query_norm ) {
			$pa += 40;
		} elseif ( strpos( $marka, $query_norm ) !== false ) {
			$pa += 20;
		}
		if ( $markb === $query_norm ) {
			$pb += 40;
		} elseif ( strpos( $markb, $query_norm ) !== false ) {
			$pb += 20;
		}
		if ( $ta === $query_norm ) {
			$pa += 15;
		} elseif ( strpos( $ta, $query_norm ) !== false ) {
			$pa += 8;
		}
		if ( $tb === $query_norm ) {
			$pb += 15;
		} elseif ( strpos( $tb, $query_norm ) !== false ) {
			$pb += 8;
		}
		return $pb - $pa;
	} );

	$productos = array();
	foreach ( $productos_raw as $p ) {
		$id     = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
		$titulo = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : ( isset( $p['nombre'] ) ? trim( (string) $p['nombre'] ) : '' );
		$modelo = isset( $p['modelo'] ) ? trim( (string) $p['modelo'] ) : '';
		if ( $modelo === '' && isset( $p['sku'] ) ) {
			$modelo = trim( (string) $p['sku'] );
		}
		if ( $modelo === '' && isset( $p['codigo'] ) ) {
			$modelo = trim( (string) $p['codigo'] );
		}
		$url = '';
		if ( function_exists( 'centinela_get_producto_url' ) ) {
			$url = centinela_get_producto_url( (int) $id, $titulo );
		} else {
			$url = home_url( '/tienda/producto/' . $id . '/' );
		}
		$precios = isset( $p['precios'] ) && is_array( $p['precios'] ) ? $p['precios'] : array();
		$precio_lista_raw = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
		if ( $precio_lista_raw === '' && isset( $precios['precio_lista'] ) ) {
			$precio_lista_raw = $precios['precio_lista'];
		}
		$precio_lista = function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_lista_raw ) : 0.0;
		$imagen = centinela_search_extract_producto_image( $p );
		$marca = centinela_search_producto_marca( $p );
		$imagen_fallbacks = centinela_search_build_syscom_g_image_candidates( $marca, $modelo );
		if ( $imagen === '' && ! empty( $imagen_fallbacks ) ) {
			$imagen = $imagen_fallbacks[0];
		}
		$productos[] = array(
			'id'           => (string) $id,
			'titulo'       => $titulo,
			'modelo'       => $modelo,
			'marca'        => $marca,
			'url'          => $url,
			'precio_lista' => $precio_lista,
			'imagen'       => $imagen,
			'imagen_fallbacks' => $imagen_fallbacks,
		);
		if ( count( $productos ) >= $limit ) {
			break;
		}
	}
	return $productos;
}

/**
 * Obtiene términos sugeridos (marcas/modelos/títulos) para animación del buscador desktop.
 * Usa caché para evitar llamadas frecuentes a la API Syscom.
 *
 * @param int $limit Máximo de términos.
 * @return array Lista de términos.
 */
function centinela_search_get_prompts_syscom( $limit = 10 ) {
	$limit = max( 3, min( 20, (int) $limit ) );
	$cache_key = 'centinela_search_prompts_v1_' . $limit;
	$cached = get_transient( $cache_key );
	if ( is_array( $cached ) && ! empty( $cached ) ) {
		return $cached;
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}

	$candidatos = array();
	$seen = array();
	$paginas = array( 1, 2 );
	foreach ( $paginas as $pagina ) {
		$resp = Centinela_Syscom_API::get_productos( array(
			'pagina' => $pagina,
			'orden'  => 'relevancia',
			'cop'    => true,
		) );
		$lista = centinela_search_get_productos_from_response( $resp );
		if ( empty( $lista ) ) {
			continue;
		}
		foreach ( $lista as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$titulo = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : ( isset( $p['nombre'] ) ? trim( (string) $p['nombre'] ) : '' );
			$modelo = isset( $p['modelo'] ) ? trim( (string) $p['modelo'] ) : '';
			$marca  = centinela_search_producto_marca( $p );
			$items = array( $marca, $modelo, $titulo );
			foreach ( $items as $term ) {
				$term = trim( (string) $term );
				if ( $term === '' || strlen( $term ) < 3 ) {
					continue;
				}
				if ( strlen( $term ) > 42 ) {
					$term = function_exists( 'mb_substr' ) ? mb_substr( $term, 0, 42 ) : substr( $term, 0, 42 );
					$term = rtrim( $term ) . '...';
				}
				$key = strtolower( $term );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$candidatos[] = $term;
				if ( count( $candidatos ) >= ( $limit * 3 ) ) {
					break 2;
				}
			}
		}
	}
	if ( empty( $candidatos ) ) {
		return array();
	}
	shuffle( $candidatos );
	$result = array_slice( $candidatos, 0, $limit );
	set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
	return $result;
}

/**
 * Registra el endpoint REST para búsqueda unificada (contenido + productos).
 * Útil para búsqueda en vivo en el header.
 */
function centinela_register_search_rest_route() {
	register_rest_route( 'centinela/v1', '/search', array(
		'methods'             => 'GET',
		'permission_callback'  => '__return_true',
		'args'                => array(
			'q' => array(
				'required'          => false,
				'type'              => 'string',
				'sanitize_callback'  => 'sanitize_text_field',
			),
			'limit_content' => array(
				'required' => false,
				'type'     => 'integer',
				'default'  => 5,
			),
			'limit_productos' => array(
				'required' => false,
				'type'     => 'integer',
				'default'  => 8,
			),
			'suggestions' => array(
				'required' => false,
				'type'     => 'boolean',
				'default'  => false,
			),
		),
		'callback' => function ( $request ) {
			$q = trim( (string) $request->get_param( 'q' ) );
			if ( $q === '' || strlen( $q ) < 2 ) {
				return new WP_REST_Response( array( 'contenido' => array(), 'productos' => array() ), 200 );
			}
			$limit_c = (int) $request->get_param( 'limit_content' );
			$limit_p = (int) $request->get_param( 'limit_productos' );
			$limit_c = max( 1, min( 20, $limit_c ) );
			$limit_p = max( 1, min( 1500, $limit_p ) );
			$fast = (bool) $request->get_param( 'suggestions' );
			if ( $fast && (int) $request->get_param( 'limit_content' ) <= 0 ) {
				$limit_c = 0;
			}

			$contenido = array();
			if ( $limit_c > 0 ) {
				$wp_query = new WP_Query( array(
					's'              => $q,
					'post_type'      => array( 'post', 'page', 'testimonio' ),
					'post_status'    => 'publish',
					'posts_per_page' => $limit_c,
					'orderby'        => 'relevance',
				) );
				if ( $wp_query->have_posts() ) {
					foreach ( $wp_query->posts as $post ) {
						$contenido[] = array(
							'id'    => (string) $post->ID,
							'title' => get_the_title( $post ),
							'url'   => get_permalink( $post ),
							'type'  => $post->post_type,
						);
					}
				}
				wp_reset_postdata();
			}

			$productos = function_exists( 'centinela_search_productos_syscom' ) ? centinela_search_productos_syscom( $q, $limit_p, $fast ) : array();
			return new WP_REST_Response( array( 'contenido' => $contenido, 'productos' => $productos ), 200 );
		},
	) );

	register_rest_route( 'centinela/v1', '/search-prompts', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'limit' => array(
				'required' => false,
				'type'     => 'integer',
				'default'  => 8,
			),
		),
		'callback'            => function ( $request ) {
			$limit = (int) $request->get_param( 'limit' );
			$terms = centinela_search_get_prompts_syscom( $limit );
			return new WP_REST_Response( array( 'terms' => array_values( $terms ) ), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_register_search_rest_route' );

/**
 * Incluir en la búsqueda WordPress: entradas, páginas y testimonios.
 * Así la búsqueda por contenido del sitio devuelve páginas internas y testimonios.
 */
function centinela_search_pre_get_posts( $query ) {
	if ( ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}
	$query->set( 'post_type', array( 'post', 'page', 'testimonio' ) );
}
add_action( 'pre_get_posts', 'centinela_search_pre_get_posts', 5 );
