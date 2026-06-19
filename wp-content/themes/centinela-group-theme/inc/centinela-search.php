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
	$normalized = strtolower( remove_accents( $term ) );
	// Syscom responde mejor si enviamos solo tokens alfanuméricos separados por "+" (evita problemas con paréntesis, barras, etc.).
	$normalized = trim( preg_replace( '/[^a-z0-9]+/i', '+', $normalized ), '+' );
	$normalized = $normalized !== '' ? $normalized : $term;
	return strtolower( $normalized );
}

/**
 * Singulariza de forma conservadora un token (ES) para mejorar búsquedas plural/singular.
 * Ej: camaras -> camara, torniquetes -> torniquete, paneles -> panel.
 *
 * @param string $token Palabra.
 * @return string
 */
function centinela_search_singularize_token_es( $token ) {
	$token = trim( (string) $token );
	$len   = strlen( $token );
	if ( $len < 4 ) {
		return $token;
	}
	$lower = strtolower( remove_accents( $token ) );
	// Evitar tocar "ss" / formas cortas.
	if ( substr( $lower, -2 ) === 'ss' ) {
		return $token;
	}
	// Si termina en "...es", decidir entre quitar "s" o "es".
	if ( substr( $lower, -2 ) === 'es' && $len >= 5 ) {
		$before_e = substr( $lower, -3, 1 );
		// Vocal + es -> normalmente basta quitar solo "s" (torniquetes -> torniquete).
		if ( preg_match( '/[aeiou]/', $before_e ) ) {
			return substr( $token, 0, -1 );
		}
		// Consonante + es -> quitar "es" (paneles -> panel).
		return substr( $token, 0, -2 );
	}
	// Vocal/consonante + s -> quitar "s" (camaras -> camara).
	if ( substr( $lower, -1 ) === 's' && $len >= 5 ) {
		return substr( $token, 0, -1 );
	}
	return $token;
}

/**
 * Convierte una frase a una variante más singular (token por token), sin perder el original.
 *
 * @param string $query Consulta de búsqueda.
 * @return string
 */
function centinela_search_plural_to_singular_query( $query ) {
	$query = trim( (string) $query );
	if ( $query === '' ) {
		return '';
	}
	$parts = preg_split( '/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY );
	if ( empty( $parts ) ) {
		return $query;
	}
	$mapped = array();
	foreach ( $parts as $part ) {
		$mapped[] = centinela_search_singularize_token_es( $part );
	}
	$out = trim( implode( ' ', $mapped ) );
	return $out !== '' ? $out : $query;
}

/**
 * Normaliza una cadena para comparación flexible (quitar espacios, guiones, puntos; minúsculas sin acentos).
 * Así "DS-KIS203-T", "DS KIS203 T" y "TK-3000-KV2" / "TK3000KV2" alinean mejor con la API Syscom.
 *
 * @param string $str Cadena (modelo, título, referencia).
 * @return string Cadena normalizada.
 */
function centinela_normalize_for_match( $str ) {
	if ( $str === '' || $str === null ) {
		return '';
	}
	$s = remove_accents( (string) $str );
	$s = strtolower( $s );
	// Igualar referencias aunque traigan símbolos: DS-KV8413-WME1(C) === DS KV8413 WME1 C.
	$s = preg_replace( '/[^a-z0-9]+/i', '', $s );
	return $s;
}

/**
 * Indica si la consulta parece una referencia de modelo (letras y dígitos), no solo una marca genérica.
 *
 * @param string $query Texto de búsqueda.
 * @return bool
 */
function centinela_search_query_looks_like_product_reference( $query ) {
	$query = trim( (string) $query );
	if ( strlen( $query ) < 5 ) {
		return false;
	}
	if ( ! preg_match( '/\d/', $query ) || ! preg_match( '/[a-zA-Z]/', $query ) ) {
		return false;
	}
	return true;
}

/**
 * Aísla la referencia de modelo cuando el usuario pega título + especificaciones (varias líneas o MHz, Watts…).
 *
 * @param string $query Texto completo del buscador.
 * @return string Referencia corta o el texto original si no se detecta patrón.
 */
function centinela_search_extract_primary_model_query( $query ) {
	$query = trim( (string) $query );
	if ( $query === '' ) {
		return $query;
	}
	$normalized_breaks = str_replace( array( "\r\n", "\r" ), "\n", $query );
	$first             = $query;
	if ( strpos( $normalized_breaks, "\n" ) !== false ) {
		$lines = explode( "\n", $normalized_breaks, 2 );
		$first = trim( $lines[0] );
	}
	if ( $first !== '' && strlen( $first ) <= 56 && centinela_search_query_looks_like_product_reference( $first ) ) {
		return $first;
	}
	if ( preg_match( '/\b(TK-?\d{4}[A-Z0-9-]*)\b/i', $query, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/\b(NX-?\d{4}[A-Z0-9-]*)\b/i', $query, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/(?<![A-Z0-9])(NX\d{4}[A-Z0-9]+)\b/i', $query, $m ) ) {
		return $m[1];
	}
	if ( preg_match( '/\b(PKT-?300[A-Z0-9]*)\b/i', $query, $m ) ) {
		return strtoupper( $m[1] );
	}
	if ( preg_match( '/\b(DS-?[0-9][A-Z0-9-]{4,})\b/i', $query, $m ) ) {
		return strtoupper( $m[1] );
	}
	return $query;
}

/**
 * Añade variantes por prefijos con guión (XBS-CAN-AC-800 → XBS-CAN-AC, XBS-CAN, …).
 *
 * @param array  $variantes_busqueda    Variantes API (por referencia).
 * @param array  $trust_api_extra_terms Términos en los que confiar en relevancia API.
 * @param string $ref_query             Referencia/modelo.
 */
function centinela_search_append_hyphen_family_variants( array &$variantes_busqueda, array &$trust_api_extra_terms, $ref_query ) {
	if ( ! centinela_search_query_looks_like_product_reference( $ref_query ) ) {
		return;
	}
	$parts = preg_split( '/-+/', strtoupper( trim( (string) $ref_query ) ), -1, PREG_SPLIT_NO_EMPTY );
	if ( count( $parts ) < 2 ) {
		return;
	}
	$prefix = $parts[0];
	for ( $i = 1; $i < count( $parts ); $i++ ) {
		$prefix .= '-' . $parts[ $i ];
		if ( strlen( $prefix ) < 5 ) {
			continue;
		}
		$variantes_busqueda[]    = $prefix;
		$variantes_busqueda[]    = centinela_normalize_search_term_for_api( $prefix );
		$trust_api_extra_terms[] = strtolower( centinela_normalize_for_match( $prefix ) );
	}
}

/**
 * Limita variantes de API en cotizador (prioriza término normalizado y referencia exacta).
 *
 * @param array  $variantes    Lista de términos.
 * @param string $busqueda_api Término normalizado para API.
 * @param string $ref_query    Referencia original.
 * @param int    $max          Máximo de variantes.
 * @return array
 */
function centinela_search_limit_variantes_for_cotizador( $variantes, $busqueda_api, $ref_query, $max = 8 ) {
	$variantes = array_values( array_unique( array_filter( array_map( 'trim', (array) $variantes ) ) ) );
	$max       = max( 3, (int) $max );
	if ( count( $variantes ) <= $max ) {
		return $variantes;
	}
	$var_sin_sep = strtolower( preg_replace( '/[-_\s]+/', '', (string) $ref_query ) );
	$prio        = array();
	foreach ( array( $busqueda_api, strtolower( (string) $ref_query ), (string) $ref_query, $var_sin_sep ) as $p ) {
		if ( $p !== '' && in_array( $p, $variantes, true ) ) {
			$prio[] = $p;
		}
	}
	foreach ( $variantes as $v ) {
		if ( ! in_array( $v, $prio, true ) ) {
			$prio[] = $v;
		}
		if ( count( $prio ) >= $max ) {
			break;
		}
	}
	return array_slice( $prio, 0, $max );
}

/**
 * Coincidencia flexible para referencias NX/TK/PKT cuando modelo y título no repiten el SKU exacto.
 *
 * @param string $term     Referencia (p. ej. NX-1300-AK4).
 * @param array  $producto Fila API.
 * @return bool
 */
function centinela_producto_reference_fuzzy_matches_query( $term, $producto ) {
	if ( ! is_array( $producto ) || $term === '' ) {
		return false;
	}
	$q = centinela_normalize_for_match( $term );
	if ( strlen( $q ) < 4 ) {
		return false;
	}
	$parts = array();
	foreach ( array( 'titulo', 'nombre', 'name', 'title', 'modelo', 'sku', 'codigo', 'referencia', 'mpn', 'codigo_producto' ) as $k ) {
		if ( ! empty( $producto[ $k ] ) && is_scalar( $producto[ $k ] ) ) {
			$parts[] = (string) $producto[ $k ];
		}
	}
	$blob = centinela_normalize_for_match( implode( ' ', $parts ) );
	if ( $blob === '' ) {
		return false;
	}
	if ( strpos( $blob, $q ) !== false || strpos( $q, $blob ) !== false ) {
		return true;
	}
	if ( ! preg_match( '/^(nx|tk|pkt)(\d{2,4})([a-z0-9]*)$/', $q, $m ) ) {
		return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', false, $term, $producto, $blob, $q );
	}
	$pref = $m[1] . $m[2];
	if ( strpos( $blob, $pref ) === false ) {
		return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', false, $term, $producto, $blob, $q );
	}
	$suf = $m[3];
	if ( $suf === '' ) {
		return true;
	}
	if ( strlen( $suf ) <= 2 ) {
		$ok = strpos( $blob, $suf ) !== false;
		return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', $ok, $term, $producto, $blob, $q );
	}
	preg_match_all( '/[a-z]{1,4}\d{0,3}|\d+[a-z]{1,4}/', $suf, $mt );
	$toks = array();
	foreach ( $mt[0] as $tk ) {
		$tn = centinela_normalize_for_match( $tk );
		if ( strlen( $tn ) >= 2 ) {
			$toks[] = $tn;
		}
	}
	if ( empty( $toks ) ) {
		$ok = strpos( $blob, $suf ) !== false;
		return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', $ok, $term, $producto, $blob, $q );
	}
	$from = 0;
	foreach ( $toks as $tn ) {
		$p = strpos( $blob, $tn, $from );
		if ( $p === false ) {
			return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', false, $term, $producto, $blob, $q );
		}
		$from = $p + strlen( $tn );
	}
	return (bool) apply_filters( 'centinela_producto_reference_fuzzy_matches_query', true, $term, $producto, $blob, $q );
}

/**
 * Extrae IDs de producto Syscom desde URL o texto (p. ej. /producto/...-136674.html o solo 30002136674).
 * No usa fragmentos numéricos cortos del modelo (p. ej. 3000 en TK-3000) para evitar falsos positivos.
 *
 * @param string $query Texto pegado o término.
 * @return string[] IDs únicos.
 */
function centinela_search_extract_syscom_product_ids_from_query( $query ) {
	$query = trim( (string) $query );
	if ( $query === '' ) {
		return array();
	}
	$ids = array();
	if ( preg_match_all( '#/(?:producto|product)/[^/\s#?]+-(\d{4,16})(?:\.html)?#i', $query, $m ) ) {
		foreach ( $m[1] as $id ) {
			$ids[] = (string) $id;
		}
	}
	if ( preg_match_all( '/-(\d{4,16})\.html\b/i', $query, $m2 ) ) {
		foreach ( $m2[1] as $id ) {
			$ids[] = (string) $id;
		}
	}
	if ( preg_match( '/^\s*(\d{4,16})\s*$/', $query, $m3 ) ) {
		$ids[] = (string) $m3[1];
	}
	return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Candidatos de ID para GET /productos/{id}: la API puede usar 11 dígitos (p. ej. 30002136674)
 * mientras el sitio regional muestra un sufijo corto en la URL (p. ej. …-136674.html → 30002+136674).
 *
 * @param string $raw_id ID extraído de URL o escrito por el usuario.
 * @return string[] IDs a probar en orden.
 */
function centinela_search_syscom_api_id_candidates( $raw_id ) {
	$d = preg_replace( '/\D/', '', (string) $raw_id );
	if ( $d === '' ) {
		return array();
	}
	$out = array( $d );
	// Slugs regionales con 6 dígitos finales suelen mapear a prefijo API (configurable).
	if ( strlen( $d ) === 6 ) {
		$prefix = (string) apply_filters( 'centinela_syscom_regional_slug_id_prefix', '30002' );
		if ( $prefix !== '' && preg_match( '/^\d+$/', $prefix ) ) {
			$out[] = $prefix . $d;
		}
	}
	return array_values( array_unique( $out ) );
}

/**
 * Título típico de accesorio (funda, micrófono, etc.): no debe imponerse al equipo cuando se busca por modelo.
 *
 * @param array $producto Ítem API.
 * @return bool
 */
function centinela_search_producto_title_suggests_accessory( $producto ) {
	if ( ! is_array( $producto ) ) {
		return false;
	}
	$titulo = isset( $producto['titulo'] ) ? $producto['titulo'] : ( isset( $producto['nombre'] ) ? $producto['nombre'] : '' );
	$titulo = strtolower( remove_accents( (string) $titulo ) );
	if ( $titulo === '' ) {
		return false;
	}
	$keywords = (array) apply_filters(
		'centinela_search_accessory_title_keywords',
		array(
			'funda', 'estuche', 'forro',
			'cubierta de plastico', 'cubierta de plástico',
			'diadema', 'micrófono', 'microfono', 'audifono', 'audífono', 'auricular',
			'bocina de solapa',
			'cable de programacion', 'cable de programación',
			'software de programacion', 'software de programación',
			'kit de microfono', 'kit de micrófono',
		)
	);
	foreach ( $keywords as $kw ) {
		$kw = strtolower( remove_accents( trim( (string) $kw ) ) );
		if ( $kw !== '' && strpos( $titulo, $kw ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Coincidencia del término solo en campos de referencia (modelo/sku/código), no por título comercial.
 *
 * @param string $term     Búsqueda.
 * @param array  $producto Producto API.
 * @return bool
 */
function centinela_producto_reference_fields_match_query( $term, $producto ) {
	if ( ! is_array( $producto ) || $term === '' ) {
		return false;
	}
	$q = centinela_normalize_for_match( $term );
	if ( $q === '' ) {
		return false;
	}
	$keys = array( 'modelo', 'sku', 'codigo', 'codigo_sat', 'codigo_producto', 'referencia', 'mpn', 'part_number', 'clave' );
	foreach ( $keys as $key ) {
		if ( ! isset( $producto[ $key ] ) || $producto[ $key ] === '' ) {
			continue;
		}
		$val  = is_string( $producto[ $key ] ) ? $producto[ $key ] : (string) $producto[ $key ];
		$norm = centinela_normalize_for_match( $val );
		if ( $norm === '' ) {
			continue;
		}
		if ( $norm === $q || strpos( $norm, $q ) !== false || strpos( $q, $norm ) !== false ) {
			return true;
		}
	}
	return false;
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
			$val = trim( (string) $m['nombre'] );
			if ( function_exists( 'centinela_tienda_brand_canonical' ) ) {
				$val = centinela_tienda_brand_canonical( $val );
			}
			return $val;
		}
		if ( trim( (string) $m ) !== '' ) {
			$val = trim( (string) $m );
			if ( function_exists( 'centinela_tienda_brand_canonical' ) ) {
				$val = centinela_tienda_brand_canonical( $val );
			}
			return $val;
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
	// Variante para búsqueda por marca: aplica alias de marca si existe helper de tienda.
	$q_brand = $q;
	if ( function_exists( 'centinela_tienda_brand_canonical' ) && function_exists( 'centinela_tienda_normalize_brand_text' ) ) {
		$term_brand_canonical = centinela_tienda_brand_canonical( $term );
		$q_brand              = centinela_tienda_normalize_brand_text( $term_brand_canonical );
	}
	if ( $q === '' ) {
		return false;
	}
	$strict_ref = centinela_search_query_looks_like_product_reference( $term );
	$accessory  = $strict_ref && centinela_search_producto_title_suggests_accessory( $producto );
	if ( $accessory ) {
		return centinela_producto_reference_fields_match_query( $term, $producto );
	}
	// Título/nombre en varias claves (API puede devolver titulo, nombre, name, title).
	$campos = array(
		'titulo', 'nombre', 'name', 'title', 'modelo', 'sku', 'codigo',
		'descripcion', 'description',
		'codigo_sat', 'codigo_producto', 'referencia', 'mpn', 'part_number', 'clave',
	);
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
		// Comparación usando normalización general y específica de marca.
		if ( $norm === $q || strpos( $norm, $q ) !== false || strpos( $q, $norm ) !== false ) {
			return true;
		}
		$norm_brand = function_exists( 'centinela_tienda_normalize_brand_text' ) ? centinela_tienda_normalize_brand_text( $marca ) : $norm;
		if ( $norm_brand === $q_brand || strpos( $norm_brand, $q_brand ) !== false || strpos( $q_brand, $norm_brand ) !== false ) {
			return true;
		}
	}
	if ( $strict_ref && function_exists( 'centinela_producto_reference_fuzzy_matches_query' )
		&& centinela_producto_reference_fuzzy_matches_query( $term, $producto ) ) {
		return true;
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
 * Puntuación para priorizar equipos de radiocomunicación Kenwood frente a accesorios u otras líneas.
 * Usada en ordenación de búsqueda y listado por marca Kenwood en tienda.
 *
 * @param array $producto Ítem API producto.
 * @return int Mayor = más “radio”.
 */
function centinela_search_kenwood_radio_boost_score( $producto ) {
	if ( ! is_array( $producto ) ) {
		return 0;
	}
	$parts = array();
	foreach ( array( 'titulo', 'nombre', 'name', 'title', 'modelo', 'descripcion', 'description', 'categoria', 'nombre_categoria' ) as $k ) {
		if ( ! empty( $producto[ $k ] ) && is_string( $producto[ $k ] ) ) {
			$parts[] = $producto[ $k ];
		}
	}
	if ( ! empty( $producto['categorias'] ) && is_array( $producto['categorias'] ) ) {
		foreach ( $producto['categorias'] as $c ) {
			if ( is_array( $c ) ) {
				if ( isset( $c['nombre'] ) ) {
					$parts[] = (string) $c['nombre'];
				}
				if ( isset( $c['titulo'] ) ) {
					$parts[] = (string) $c['titulo'];
				}
			} elseif ( is_string( $c ) ) {
				$parts[] = $c;
			}
		}
	}
	$blob = centinela_normalize_for_match( implode( ' ', $parts ) );
	if ( $blob === '' ) {
		return 0;
	}
	$score = 0;
	$boost_terms = array(
		'radio', 'radiocom', 'radiocomunic', 'portatil', 'transceptor', 'walkie', 'talkie',
		'uhf', 'vhf', 'hf', 'dmr', 'p25', 'analog', 'digital', 'movil', 'handheld', 'hand',
		'repetidor', 'repeater', 'nxdn', 'dstar',
	);
	foreach ( $boost_terms as $t ) {
		$nt = centinela_normalize_for_match( $t );
		if ( $nt !== '' && strpos( $blob, $nt ) !== false ) {
			$score += 12;
		}
	}
	if ( strpos( $blob, 'txpro' ) !== false ) {
		$score += 10;
	}
	$penalty_terms = array(
		'coaxial', 'coax', 'torniquete', 'cctv', 'camara', 'videoporter', 'controldeacceso',
		'patchpanel', 'cableado',
	);
	foreach ( $penalty_terms as $t ) {
		$nt = centinela_normalize_for_match( $t );
		if ( $nt !== '' && strpos( $blob, $nt ) !== false ) {
			$score -= 18;
		}
	}
	return $score;
}

/**
 * @param string $query   Término de búsqueda.
 * @param int    $limit   Número máximo de productos.
 * @param bool   $fast    Si true, solo 1–2 páginas y busqueda API (para sugerencias en el overlay).
 * @param string $context 'site' (tienda/buscador) o 'cotizador' (admin: menos llamadas API).
 */
function centinela_search_productos_syscom( $query, $limit = 12, $fast = false, $context = 'site' ) {
	$query = trim( (string) $query );
	if ( $query === '' || strlen( $query ) < 2 ) {
		return array();
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	$context      = is_string( $context ) ? strtolower( trim( $context ) ) : 'site';
	$is_cotizador = ( $context === 'cotizador' );
	if ( $fast ) {
		$cache_key = 'centinela_search_fast_v2_' . md5( strtolower( $query ) . '|' . (int) $limit );
		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
	}
	if ( $is_cotizador ) {
		$cache_key_cot = 'centinela_cotizador_search_v1_' . md5( strtolower( $query ) . '|' . (int) $limit );
		$cached_cot    = get_transient( $cache_key_cot );
		if ( is_array( $cached_cot ) ) {
			return $cached_cot;
		}
	}

	$ref_query = centinela_search_extract_primary_model_query( $query );
	if ( $ref_query === '' ) {
		$ref_query = $query;
	}

	$ids_vistos = array();
	$productos_raw = array();
	$busqueda_api = centinela_normalize_search_term_for_api( $ref_query );

	// Resolución directa por ID (URL Syscom o número de catálogo).
	$direct_ids = centinela_search_extract_syscom_product_ids_from_query( $query );
	foreach ( $direct_ids as $did ) {
		foreach ( centinela_search_syscom_api_id_candidates( $did ) as $try_id ) {
			$full = Centinela_Syscom_API::get_producto( $try_id, true );
			if ( is_wp_error( $full ) || ! is_array( $full ) ) {
				continue;
			}
			$pid = isset( $full['producto_id'] ) ? (string) $full['producto_id'] : ( isset( $full['id'] ) ? (string) $full['id'] : (string) $try_id );
			if ( $pid === '' || ! empty( $ids_vistos[ $pid ] ) ) {
				continue;
			}
			$productos_raw[] = $full;
			$ids_vistos[ $pid ] = true;
			break;
		}
	}
	$get_lista = function ( $resp ) {
		return function_exists( 'centinela_search_get_productos_from_response' ) ? centinela_search_get_productos_from_response( $resp ) : ( isset( $resp['productos'] ) ? $resp['productos'] : array() );
	};

	// Siempre intentar primero la API con busqueda (menos llamadas y resultados más relevantes).
	// Variantes extra para que referencias tipo "AP-1000" / "AP 1000" / "AP1000" coincidan mejor.
	$var_con_espacios = strtolower( preg_replace( '/-+/', ' ', (string) $ref_query ) );
	$var_sin_sep      = strtolower( preg_replace( '/[-_\\s]+/', '', (string) $ref_query ) );
	// Términos de API "amplios" (p. ej. NX+1300) donde confiamos en relevancia pero filtramos accesorios.
	$trust_api_extra_terms = array();
	$variantes_busqueda = array_unique( array(
		$busqueda_api,
		strtolower( $ref_query ),
		$ref_query,
		$var_con_espacios,
		$var_sin_sep,
	) );
	// Búsqueda por marca: incluir aliases canónicos para ampliar cobertura (Kenwood/JVC Kenwood).
	if ( function_exists( 'centinela_tienda_brand_canonical' ) && function_exists( 'centinela_tienda_normalize_brand_text' ) ) {
		$canonical = centinela_tienda_brand_canonical( $query );
		$norm_brand = centinela_tienda_normalize_brand_text( $canonical );
		if ( $canonical !== '' ) {
			$variantes_busqueda[] = $canonical;
		}
		if ( $norm_brand === 'kenwood' ) {
			$variantes_busqueda[] = 'TXPRO';
			$variantes_busqueda[] = 'TX PRO';
			$variantes_busqueda[] = 'KENWOOD';
			$variantes_busqueda[] = 'JVC KENWOOD';
			$variantes_busqueda[] = 'JVC KENWOOD INC';
		}
	}
	$variantes_busqueda = array_values( array_unique( array_filter( array_map( 'trim', $variantes_busqueda ) ) ) );
	$query_is_likely_brand = preg_match( '/^[a-zA-Z\s]{4,}$/', $ref_query ) === 1;
	if ( $fast && ! $query_is_likely_brand ) {
		// Referencia/modelo en sugerencias: pocas variantes de alto valor para responder rápido.
		$variantes_busqueda = array_values( array_unique( array(
			$busqueda_api,
			$var_sin_sep,
			$var_con_espacios,
		) ) );
	}
	// Base de modelo (p. ej. TK-3000-KV2 → TK-3000): la API a veces indexa por familia; debe quedar también en modo sugerencias (fast).
	if ( centinela_search_query_looks_like_product_reference( $ref_query ) && preg_match( '/\bTK-?(\d{4})\b/i', $ref_query, $m_base ) ) {
		$digits = $m_base[1];
		$base   = strtoupper( 'TK-' . $digits );
		if ( strlen( $base ) >= 6 ) {
			$variantes_busqueda[] = $base;
			$variantes_busqueda[] = strtoupper( 'TK' . $digits );
			$variantes_busqueda[] = centinela_normalize_search_term_for_api( $base );
			$tk_plus = 'TK+' . $digits;
			$variantes_busqueda[] = $tk_plus;
			$trust_api_extra_terms[] = strtolower( $tk_plus );
			$tk_c = strtolower( centinela_normalize_for_match( $ref_query ) );
			if ( strlen( $tk_c ) >= 6 ) {
				$variantes_busqueda[]    = $tk_c;
				$trust_api_extra_terms[] = $tk_c;
			}
		}
	}
	// Familia NX: la API suele indexar NX+1300 / NX 1300, no el sufijo completo NX-1300-AK4.
	if ( centinela_search_query_looks_like_product_reference( $ref_query ) ) {
		$base_nx   = '';
		$nx_series = '';
		if ( preg_match( '/\b(NX-?(\d{4}))\b/i', $ref_query, $m_nx ) ) {
			$base_nx   = strtoupper( preg_replace( '/\s+/', '', $m_nx[1] ) );
			$nx_series = $m_nx[2];
		} elseif ( preg_match( '/(?<![A-Z0-9])(NX(\d{4}))(?![0-9])/i', $ref_query, $m_nx ) ) {
			$base_nx   = strtoupper( $m_nx[1] );
			$nx_series = $m_nx[2];
		}
		if ( $nx_series !== '' && strlen( $base_nx ) >= 6 ) {
			$variantes_busqueda[] = $base_nx;
			$variantes_busqueda[] = centinela_normalize_search_term_for_api( $base_nx );
			if ( strpos( $base_nx, '-' ) === false && preg_match( '/^NX\d{4}$/', $base_nx ) ) {
				$h = 'NX-' . substr( $base_nx, 2 );
				$variantes_busqueda[] = $h;
				$variantes_busqueda[] = centinela_normalize_search_term_for_api( $h );
			}
			$nx_plus = 'NX+' . $nx_series;
			$nx_sp   = 'NX ' . $nx_series;
			$nx_lc   = 'nx' . $nx_series;
			$variantes_busqueda[] = $nx_plus;
			$variantes_busqueda[] = $nx_sp;
			$variantes_busqueda[] = $nx_lc;
			$variantes_busqueda[] = 'ICOM+NX-' . $nx_series;
			$nx_compact            = strtolower( centinela_normalize_for_match( $ref_query ) );
			$variantes_busqueda[] = $nx_compact;
			$trust_api_extra_terms[] = strtolower( $nx_plus );
			$trust_api_extra_terms[] = strtolower( $nx_sp );
			$trust_api_extra_terms[] = strtolower( $nx_lc );
			$trust_api_extra_terms[] = strtolower( 'KENWOOD+NX-' . $nx_series );
			$trust_api_extra_terms[] = strtolower( 'ICOM+NX-' . $nx_series );
			if ( $nx_compact !== '' ) {
				$trust_api_extra_terms[] = $nx_compact;
			}
		}
	}
	// PKT-300K: la API responde mejor a PKT+300 / PKT 300K que al SKU completo.
	if ( centinela_search_query_looks_like_product_reference( $ref_query ) && preg_match( '/\bPKT-?300/i', $ref_query ) ) {
		foreach ( array( 'PKT+300', 'PKT 300K', 'PKT 300' ) as $pkt_term ) {
			$variantes_busqueda[]    = $pkt_term;
			$trust_api_extra_terms[] = strtolower( $pkt_term );
		}
		$variantes_busqueda[] = strtolower( centinela_normalize_for_match( $ref_query ) );
	}
	// Hikvision DS-2CD…: la API suele indexar DS+2CD2143 o la familia DS-2CD2143, no siempre el sufijo G2-I completo.
	if ( centinela_search_query_looks_like_product_reference( $ref_query ) && preg_match( '/\b(DS-?2CD(\d{4}))[A-Z0-9-]*/i', $ref_query, $m_ds ) ) {
		$base_ds   = strtoupper( preg_replace( '/\s+/', '', $m_ds[1] ) );
		$series_ds = $m_ds[2];
		if ( strlen( $base_ds ) >= 8 ) {
			$variantes_busqueda[] = $base_ds;
			$variantes_busqueda[] = centinela_normalize_search_term_for_api( $base_ds );
			if ( strpos( $base_ds, '-' ) === false && preg_match( '/^DS2CD\d{4}$/', $base_ds ) ) {
				$h = 'DS-2CD' . $series_ds;
				$variantes_busqueda[] = $h;
				$variantes_busqueda[] = centinela_normalize_search_term_for_api( $h );
			}
			$ds_plus = 'DS+2CD' . $series_ds;
			$ds_sp   = 'DS 2CD' . $series_ds;
			$variantes_busqueda[]    = $ds_plus;
			$variantes_busqueda[]    = $ds_sp;
			$trust_api_extra_terms[] = strtolower( $ds_plus );
			$trust_api_extra_terms[] = strtolower( $ds_sp );
			$ds_compact = strtolower( centinela_normalize_for_match( $ref_query ) );
			if ( $ds_compact !== '' ) {
				$variantes_busqueda[]    = $ds_compact;
				$trust_api_extra_terms[] = $ds_compact;
			}
		}
	}
	centinela_search_append_hyphen_family_variants( $variantes_busqueda, $trust_api_extra_terms, $ref_query );
	if ( centinela_search_query_looks_like_product_reference( $ref_query ) && preg_match( '/\b(XBS-?CAN-?AC(?:-\d+)?)/i', $ref_query, $m_xbs ) ) {
		$base_xbs = strtoupper( preg_replace( '/\s+/', '', $m_xbs[1] ) );
		if ( strlen( $base_xbs ) >= 6 ) {
			$variantes_busqueda[]    = $base_xbs;
			$variantes_busqueda[]    = centinela_normalize_search_term_for_api( $base_xbs );
			$variantes_busqueda[]    = 'XBS+CAN+AC';
			$variantes_busqueda[]    = 'XBS CAN AC';
			$trust_api_extra_terms[] = 'xbs+can+ac';
			$trust_api_extra_terms[] = 'xbscanac';
		}
	}
	$variantes_busqueda = array_values( array_unique( array_filter( array_map( 'trim', $variantes_busqueda ) ) ) );
	$trust_api_extra_terms = array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'trim', $trust_api_extra_terms ) ) ) ) );
	if ( $is_cotizador ) {
		$variantes_busqueda = centinela_search_limit_variantes_for_cotizador( $variantes_busqueda, $busqueda_api, $ref_query, 8 );
	}
	// En sugerencias / cotizador, limitar páginas para priorizar respuesta inmediata.
	if ( $is_cotizador ) {
		$api_max_paginas = centinela_search_query_looks_like_product_reference( $ref_query ) ? 3 : 2;
	} elseif ( $fast ) {
		$api_max_paginas = $query_is_likely_brand ? 3 : 2;
	} else {
		$api_max_paginas = centinela_search_query_looks_like_product_reference( $ref_query ) ? 12 : 8;
	}
	$busqueda_sin_plus = str_replace( '+', '', $busqueda_api );
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
				// La lista a veces trae título comercial sin el modelo; si la API ya filtró por término de referencia, confiar en esa página.
				$term_l = strtolower( (string) $term_api );
				$trust_api_relevance = centinela_search_query_looks_like_product_reference( $ref_query )
					&& (
						$term_l === $var_sin_sep
						|| $term_l === $busqueda_api
						|| $term_l === $busqueda_sin_plus
						|| in_array( $term_l, $trust_api_extra_terms, true )
					);
				// No incluir accesorios solo por relevancia de la API si el modelo/código no coincide con la referencia.
				if ( $trust_api_relevance && centinela_search_producto_title_suggests_accessory( $p )
					&& ! centinela_producto_reference_fields_match_query( $ref_query, $p )
					&& ! ( function_exists( 'centinela_producto_reference_fuzzy_matches_query' ) && centinela_producto_reference_fuzzy_matches_query( $ref_query, $p ) ) ) {
					continue;
				}
				if ( ! $trust_api_relevance && ! centinela_producto_matches_search( $ref_query, $p ) ) {
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
		if ( $is_cotizador && count( $productos_raw ) >= $limit ) {
			break;
		}
		if ( $is_cotizador && centinela_search_query_looks_like_product_reference( $ref_query ) && ! empty( $productos_raw ) ) {
			foreach ( $productos_raw as $pr ) {
				if ( centinela_producto_matches_search( $ref_query, $pr )
					|| centinela_producto_reference_fields_match_query( $ref_query, $pr )
					|| ( function_exists( 'centinela_producto_reference_fuzzy_matches_query' ) && centinela_producto_reference_fuzzy_matches_query( $ref_query, $pr ) ) ) {
					break 2;
				}
			}
		}
		if ( $fast ) {
			// Marca: no recorrer variantes extra cuando ya hay volumen suficiente para sugerencias.
			if ( $query_is_likely_brand && count( $productos_raw ) >= $limit ) {
				return centinela_search_build_productos_output( $productos_raw, $ref_query, $limit, $fast );
			}
			// Referencia/modelo: reunir un bloque suficiente para no truncar resultados en desktop.
			if ( ! $query_is_likely_brand && count( $productos_raw ) >= $limit ) {
				return centinela_search_build_productos_output( $productos_raw, $ref_query, $limit, $fast );
			}
		}
	}

	if ( $is_cotizador ) {
		$result = centinela_search_build_productos_output( $productos_raw, $ref_query, $limit, false );
		set_transient( $cache_key_cot, $result, 20 * MINUTE_IN_SECONDS );
		return $result;
	}

	// En modo sugerencias no hacemos barrido de catálogo completo: prioriza latencia baja.
	if ( $fast ) {
		$result = centinela_search_build_productos_output( $productos_raw, $ref_query, $limit, true );
		set_transient( $cache_key, $result, 8 * MINUTE_IN_SECONDS );
		return $result;
	}

	// Barrido por páginas del catálogo (con primera categoría si hace falta). Solo sitio web, no cotizador.
	if ( ! $is_cotizador ) {
		$max_paginas  = $fast ? ( $query_is_likely_brand ? 2 : 1 ) : 15;
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
			$resp  = Centinela_Syscom_API::get_productos( $args );
			$lista = $get_lista( $resp );
			if ( empty( $lista ) && $pag === 1 && $categoria_id === '' && method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
				$arbol = Centinela_Syscom_API::get_categorias_arbol();
				if ( ! is_wp_error( $arbol ) && ! empty( $arbol ) && isset( $arbol[0]['id'] ) ) {
					$categoria_id      = (string) $arbol[0]['id'];
					$args['categoria'] = $categoria_id;
					$args['pagina']    = 1;
					$resp              = Centinela_Syscom_API::get_productos( $args );
					$lista             = $get_lista( $resp );
				}
			}
			if ( empty( $lista ) ) {
				continue;
			}
			foreach ( $lista as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				if ( ! centinela_producto_matches_search( $ref_query, $p ) ) {
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
				$productos_raw[]    = $p;
				$ids_vistos[ $pid ] = true;
			}
			if ( count( $productos_raw ) >= $limit * ( $fast ? 1 : 3 ) ) {
				break;
			}
		}
	}

	if ( ! $fast && ! $is_cotizador && centinela_search_query_looks_like_product_reference( $ref_query ) ) {
		$max_detail = (int) apply_filters( 'centinela_search_enrich_reference_max_detail_fetches', 28, $ref_query, $productos_raw );
		$max_detail = max( 0, min( 40, $max_detail ) );
		$n_detail   = 0;
		foreach ( $productos_raw as $i => $row ) {
			if ( $n_detail >= $max_detail ) {
				break;
			}
			if ( centinela_producto_matches_search( $ref_query, $row ) ) {
				continue;
			}
			if ( function_exists( 'centinela_producto_reference_fuzzy_matches_query' ) && centinela_producto_reference_fuzzy_matches_query( $ref_query, $row ) ) {
				continue;
			}
			if ( centinela_producto_reference_fields_match_query( $ref_query, $row ) ) {
				continue;
			}
			++$n_detail;
			$productos_raw[ $i ] = centinela_search_merge_syscom_list_row_with_detail( $row );
		}
		$filtered_raw = array();
		foreach ( $productos_raw as $row ) {
			if ( centinela_producto_matches_search( $ref_query, $row )
				|| centinela_producto_reference_fields_match_query( $ref_query, $row )
				|| ( function_exists( 'centinela_producto_reference_fuzzy_matches_query' ) && centinela_producto_reference_fuzzy_matches_query( $ref_query, $row ) ) ) {
				$filtered_raw[] = $row;
			}
		}
		if ( ! empty( $filtered_raw ) ) {
			$productos_raw = $filtered_raw;
		}
	}

	$result = centinela_search_build_productos_output( $productos_raw, $ref_query, $limit, $fast );
	if ( $fast ) {
		// TTL corto para sugerencias: acelera tecleo repetido sin estancar resultados.
		set_transient( $cache_key, $result, 8 * MINUTE_IN_SECONDS );
	}
	if ( $is_cotizador ) {
		set_transient( $cache_key_cot, $result, 20 * MINUTE_IN_SECONDS );
	}
	return $result;
}

/**
 * Completa una fila de listado con GET /productos/{id} (modelo/referencia a veces solo vienen en detalle).
 *
 * @param array $row Fila API listado.
 * @return array
 */
function centinela_search_merge_syscom_list_row_with_detail( $row ) {
	if ( ! is_array( $row ) || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return $row;
	}
	$id = isset( $row['producto_id'] ) ? $row['producto_id'] : ( isset( $row['id'] ) ? $row['id'] : '' );
	if ( $id === '' ) {
		return $row;
	}
	$full = Centinela_Syscom_API::get_producto( $id, true );
	if ( is_wp_error( $full ) || ! is_array( $full ) ) {
		return $row;
	}
	foreach ( array( 'modelo', 'sku', 'codigo', 'titulo', 'nombre', 'descripcion', 'description', 'referencia', 'mpn', 'codigo_producto' ) as $k ) {
		if ( ! isset( $full[ $k ] ) || ! is_string( $full[ $k ] ) || trim( $full[ $k ] ) === '' ) {
			continue;
		}
		$cur = isset( $row[ $k ] ) ? trim( (string) $row[ $k ] ) : '';
		if ( $cur === '' || strlen( trim( $full[ $k ] ) ) > strlen( $cur ) ) {
			$row[ $k ] = $full[ $k ];
		}
	}
	return $row;
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
	$query_is_kenwood_brand = false;
	if ( function_exists( 'centinela_tienda_normalize_brand_text' ) && function_exists( 'centinela_tienda_brand_canonical' ) ) {
		$query_is_kenwood_brand = ( centinela_tienda_normalize_brand_text( centinela_tienda_brand_canonical( $query ) ) === 'kenwood' );
	}
	$query_is_kenwood_radioish = $query_is_kenwood_brand
		|| ( centinela_search_query_looks_like_product_reference( $query ) && preg_match( '/^(tk|nx|pkt)/', $query_norm ) === 1 );
	usort( $productos_raw, function ( $a, $b ) use ( $query, $query_norm, $query_is_kenwood_radioish ) {
		$query_ref = centinela_search_query_looks_like_product_reference( $query );
		$modela    = centinela_normalize_for_match(
			isset( $a['modelo'] ) ? $a['modelo'] : ( isset( $a['sku'] ) ? $a['sku'] : ( isset( $a['codigo'] ) ? $a['codigo'] : '' ) )
		);
		$modelb = centinela_normalize_for_match(
			isset( $b['modelo'] ) ? $b['modelo'] : ( isset( $b['sku'] ) ? $b['sku'] : ( isset( $b['codigo'] ) ? $b['codigo'] : '' ) )
		);
		if ( $query_ref ) {
			$acc_a = centinela_search_producto_title_suggests_accessory( $a );
			$acc_b = centinela_search_producto_title_suggests_accessory( $b );
			if ( $acc_a !== $acc_b ) {
				return ( $acc_a ? 1 : 0 ) - ( $acc_b ? 1 : 0 );
			}
			$exa = ( $modela === $query_norm ) ? 1 : 0;
			$exb = ( $modelb === $query_norm ) ? 1 : 0;
			if ( $exa !== $exb ) {
				return $exb - $exa;
			}
		}
		$ma = centinela_producto_matches_search( $query, $a ) ? 1 : 0;
		$mb = centinela_producto_matches_search( $query, $b ) ? 1 : 0;
		if ( $mb !== $ma ) {
			return $mb - $ma;
		}
		$ta = centinela_normalize_for_match( isset( $a['titulo'] ) ? $a['titulo'] : ( isset( $a['nombre'] ) ? $a['nombre'] : '' ) );
		$tb = centinela_normalize_for_match( isset( $b['titulo'] ) ? $b['titulo'] : ( isset( $b['nombre'] ) ? $b['nombre'] : '' ) );
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
		if ( $query_is_kenwood_radioish ) {
			$pa += centinela_search_kenwood_radio_boost_score( $a );
			$pb += centinela_search_kenwood_radio_boost_score( $b );
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
		$precio_lista   = 0.0;
		$precio_oferta  = 0.0;
		$tiene_oferta   = false;
		if ( function_exists( 'centinela_syscom_producto_precios_lista_oferta' ) ) {
			$bundle = centinela_syscom_producto_precios_lista_oferta( $p, true );
			if ( is_array( $bundle ) && isset( $bundle['precio_lista'] ) ) {
				$precio_lista  = (float) $bundle['precio_lista'];
				$precio_oferta = isset( $bundle['precio_oferta'] ) ? (float) $bundle['precio_oferta'] : 0.0;
				$tiene_oferta  = ! empty( $bundle['tiene_oferta'] );
			}
		}
		if ( $precio_lista <= 0 ) {
			$precios = isset( $p['precios'] ) && is_array( $p['precios'] ) ? $p['precios'] : array();
			$precio_lista_raw = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
			if ( $precio_lista_raw === '' && isset( $precios['precio_lista'] ) ) {
				$precio_lista_raw = $precios['precio_lista'];
			}
			$precio_lista = function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_lista_raw ) : 0.0;
		}
		if ( $precio_oferta <= 0 && $precio_lista > 0 ) {
			$precio_oferta = $precio_lista;
		}
		$imagen = centinela_search_extract_producto_image( $p );
		$marca = centinela_search_producto_marca( $p );
		// Candidatos -g solo para thumbnails en JS (onerror); no usarlos como src inicial:
		// muchas referencias no tienen archivo en BancoFotografiasSyscom y el listado queda roto.
		$imagen_fallbacks = centinela_search_build_syscom_g_image_candidates( $marca, $modelo );
		if ( $imagen === '' && function_exists( 'centinela_syscom_imagen_no_disponible_url' ) ) {
			$imagen = centinela_syscom_imagen_no_disponible_url();
		}
		$productos[] = array(
			'id'             => (string) $id,
			'titulo'         => $titulo,
			'modelo'         => $modelo,
			'marca'          => $marca,
			'url'            => $url,
			'precio_lista'   => $precio_lista,
			'precio_oferta'  => $precio_oferta > 0 ? $precio_oferta : $precio_lista,
			'tiene_oferta'   => $tiene_oferta,
			'imagen'         => $imagen,
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
 * ¿Algún ítem de la lista de búsqueda coincide con la referencia consultada?
 *
 * @param array  $productos Filas formateadas (id, titulo, modelo, …) o crudas API.
 * @param string $query     Referencia o término.
 * @return bool
 */
function centinela_search_list_has_reference_match( $productos, $query ) {
	if ( empty( $productos ) || ! is_array( $productos ) ) {
		return false;
	}
	$query = trim( (string) $query );
	if ( $query === '' ) {
		return false;
	}
	$q_norm = centinela_normalize_for_match( $query );
	if ( $q_norm === '' ) {
		return false;
	}
	foreach ( $productos as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		if ( function_exists( 'centinela_producto_matches_search' ) && centinela_producto_matches_search( $query, $p ) ) {
			return true;
		}
		if ( function_exists( 'centinela_producto_reference_fields_match_query' ) && centinela_producto_reference_fields_match_query( $query, $p ) ) {
			return true;
		}
		$modelo = isset( $p['modelo'] ) ? centinela_normalize_for_match( $p['modelo'] ) : '';
		if ( $modelo !== '' && ( $modelo === $q_norm || strpos( $modelo, $q_norm ) !== false ) ) {
			return true;
		}
	}
	return false;
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
			$limit_p = max( 1, min( 80, $limit_p ) );
			$use_suggestions = (bool) $request->get_param( 'suggestions' );
			$is_ref          = function_exists( 'centinela_search_query_looks_like_product_reference' )
				&& centinela_search_query_looks_like_product_reference( $q );
			// Referencias (DS-2CD2143G2-I, TK-3000, …): búsqueda completa como cotizador y search.php con Enter.
			$fast = $use_suggestions && ! $is_ref;
			if ( $is_ref ) {
				$limit_p = min( 80, max( $limit_p, 48 ) );
			}
			if ( $use_suggestions && (int) $request->get_param( 'limit_content' ) <= 0 ) {
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
			if ( $use_suggestions && $is_ref && ! empty( $productos )
				&& ! centinela_search_list_has_reference_match( $productos, $q ) ) {
				$productos = centinela_search_productos_syscom( $q, $limit_p, false );
			} elseif ( $fast && empty( $productos ) && $is_ref ) {
				$productos = centinela_search_productos_syscom( $q, $limit_p, false );
			}
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
