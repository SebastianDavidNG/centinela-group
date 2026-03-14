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
 * Normaliza un término para enviarlo a la API Syscom (palabras separadas por +).
 * Ej: "DS KIS203 T" o "DS-KIS203-T" → "DS+KIS203+T"
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
	return $normalized !== '' ? $normalized : $term;
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
 * Indica si el término de búsqueda coincide con el producto (título, modelo, sku, codigo).
 * Usa comparación normalizada para que "DS KIS203 T" coincida con modelo "DS-KIS203-T".
 *
 * @param string $term     Término de búsqueda.
 * @param array  $producto  Producto con titulo, modelo, sku, codigo.
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
	$campos = array( 'titulo', 'modelo', 'sku', 'codigo' );
	foreach ( $campos as $key ) {
		if ( empty( $producto[ $key ] ) ) {
			continue;
		}
		$val = is_string( $producto[ $key ] ) ? $producto[ $key ] : (string) $producto[ $key ];
		$norm = centinela_normalize_for_match( $val );
		// Coincidencia exacta normalizada o el término está contenido en el valor normalizado.
		if ( $norm === $q || strpos( $norm, $q ) !== false || strpos( $q, $norm ) !== false ) {
			return true;
		}
	}
	return false;
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
function centinela_search_productos_syscom( $query, $limit = 12 ) {
	$query = trim( (string) $query );
	if ( $query === '' || strlen( $query ) < 2 ) {
		return array();
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}

	$ids_vistos = array();
	$productos_raw = array();

	// 1) Búsqueda con término normalizado para API (espacios/guiones → +).
	$busqueda_api = centinela_normalize_search_term_for_api( $query );
	if ( $busqueda_api !== '' ) {
		$resp = Centinela_Syscom_API::get_productos( array(
			'busqueda' => $busqueda_api,
			'pagina'   => 1,
			'orden'    => 'relevancia',
			'cop'      => true,
		) );
		if ( ! is_wp_error( $resp ) && ! empty( $resp['productos'] ) ) {
			foreach ( $resp['productos'] as $p ) {
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				if ( $pid !== '' && empty( $ids_vistos[ (string) $pid ] ) ) {
					$productos_raw[] = $p;
					$ids_vistos[ (string) $pid ] = true;
				}
			}
		}
	}

	// 2) Si el término tiene varios segmentos, buscar también por el primero (más resultados tipo "DS").
	$segmentos = preg_split( '/[\s\-_]+/', $query, 2 );
	$primer = isset( $segmentos[0] ) ? trim( $segmentos[0] ) : '';
	if ( $primer !== '' && strlen( $primer ) >= 2 ) {
		$resp2 = Centinela_Syscom_API::get_productos( array(
			'busqueda' => $primer,
			'pagina'   => 1,
			'orden'    => 'relevancia',
			'cop'      => true,
		) );
		if ( ! is_wp_error( $resp2 ) && ! empty( $resp2['productos'] ) ) {
			foreach ( $resp2['productos'] as $p ) {
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				if ( $pid !== '' && empty( $ids_vistos[ (string) $pid ] ) ) {
					$productos_raw[] = $p;
					$ids_vistos[ (string) $pid ] = true;
				}
			}
		}
	}

	// 3) Término original (por si la API matchea exacto y los anteriores no).
	if ( $query !== $busqueda_api ) {
		$resp3 = Centinela_Syscom_API::get_productos( array(
			'busqueda' => $query,
			'pagina'   => 1,
			'orden'    => 'relevancia',
			'cop'      => true,
		) );
		if ( ! is_wp_error( $resp3 ) && ! empty( $resp3['productos'] ) ) {
			foreach ( $resp3['productos'] as $p ) {
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				if ( $pid !== '' && empty( $ids_vistos[ (string) $pid ] ) ) {
					$productos_raw[] = $p;
					$ids_vistos[ (string) $pid ] = true;
				}
			}
		}
	}

	// Filtrar en PHP por coincidencia flexible (título, modelo, sku, codigo) y armar salida.
	$productos = array();
	foreach ( $productos_raw as $p ) {
		if ( ! centinela_producto_matches_search( $query, $p ) ) {
			continue;
		}
		$id     = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
		$titulo = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : '';
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
		// Misma lógica que la tienda: img_portada o primera imagen de centinela_get_producto_imagenes (imagenes/galeria/fotos con url/imagen/src).
		$imagen = isset( $p['img_portada'] ) ? trim( (string) $p['img_portada'] ) : '';
		if ( $imagen === '' && function_exists( 'centinela_get_producto_imagenes' ) ) {
			$imgs_list = centinela_get_producto_imagenes( $p );
			$imagen    = ! empty( $imgs_list[0]['url'] ) ? $imgs_list[0]['url'] : '';
		}
		if ( $imagen === '' && ! empty( $p['imagen'] ) && is_string( $p['imagen'] ) ) {
			$imagen = trim( $p['imagen'] );
		}
		if ( $imagen === '' && ! empty( $p['imagenes'] ) && is_array( $p['imagenes'] ) ) {
			$first = reset( $p['imagenes'] );
			$imagen = is_string( $first ) ? $first : ( isset( $first['url'] ) ? $first['url'] : ( isset( $first['imagen'] ) ? $first['imagen'] : ( isset( $first['src'] ) ? $first['src'] : '' ) ) );
		}
		$productos[] = array(
			'id'           => (string) $id,
			'titulo'       => $titulo,
			'modelo'       => $modelo,
			'url'          => $url,
			'precio_lista' => $precio_lista,
			'imagen'       => $imagen,
		);
		if ( count( $productos ) >= $limit ) {
			break;
		}
	}
	return $productos;
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
		),
		'callback' => function ( $request ) {
			$q = trim( (string) $request->get_param( 'q' ) );
			if ( $q === '' || strlen( $q ) < 2 ) {
				return new WP_REST_Response( array( 'contenido' => array(), 'productos' => array() ), 200 );
			}
			$limit_c = (int) $request->get_param( 'limit_content' );
			$limit_p = (int) $request->get_param( 'limit_productos' );
			$limit_c = max( 1, min( 20, $limit_c ) );
			$limit_p = max( 1, min( 20, $limit_p ) );

			$contenido = array();
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

			$productos = function_exists( 'centinela_search_productos_syscom' ) ? centinela_search_productos_syscom( $q, $limit_p ) : array();
			return new WP_REST_Response( array( 'contenido' => $contenido, 'productos' => $productos ), 200 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_register_search_rest_route' );
