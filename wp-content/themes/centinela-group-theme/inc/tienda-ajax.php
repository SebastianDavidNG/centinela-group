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
 * Mapa de aliases de marcas (clave normalizada => marca canónica).
 *
 * @return array<string,string>
 */
function centinela_tienda_brand_alias_map() {
	return array(
		'hilook'             => 'HiLook by HIKVISION',
		'hilookbyhikvision'  => 'HiLook by HIKVISION',
		// Kenwood / JVC Kenwood.
		'kenwood'            => 'KENWOOD',
		'keenon'             => 'KENWOOD',
		'keenwood'           => 'KENWOOD',
		'jvckenwood'         => 'KENWOOD',
		'jvckenwoodinc'      => 'KENWOOD',
		// MIMOSA.
		'mimosa'             => 'MIMOSA NETWORKS',
		'mimosanetworks'     => 'MIMOSA NETWORKS',
		// ALLIED TELESIS.
		'alliedtelesis'      => 'ALLIED TELESIS',
		// ROSSLARE.
		'rosslare'                  => 'ROSSLARE SECURITY PRODUCTS',
		'rosslaresecurityproducts'  => 'ROSSLARE SECURITY PRODUCTS',
		// AXPRO (línea de HIKVISION, pero la mantenemos separada para el usuario).
		'axpro'              => 'AXPRO',
		// LINKEDPRO.
		'linkedpro'                => 'LINKEDPRO',
		'linkedprobyepcom'         => 'LINKEDPRO',
		'linkedprobyfiberhome'     => 'LINKEDPRO',
		// CAMBIUM NETWORKS.
		'cambiumnetworks'          => 'CAMBIUM NETWORKS',
		'cambiumnetworksinc'       => 'CAMBIUM NETWORKS',
		// STI (abreviatura habitual).
		'sti'                      => 'STI',
	);
}

/**
 * Normaliza texto para comparar marcas.
 *
 * @param string $value Texto de marca.
 * @return string
 */
function centinela_tienda_normalize_brand_text( $value ) {
	$value = trim( (string) $value );
	if ( $value === '' ) {
		return '';
	}
	if ( function_exists( 'centinela_normalize_for_match' ) ) {
		return centinela_normalize_for_match( $value );
	}
	return strtolower( preg_replace( '/[\s\-_]+/', '', $value ) );
}

/**
 * Devuelve marca canónica (aplica aliases conocidos).
 *
 * @param string $brand Marca original.
 * @return string
 */
function centinela_tienda_brand_canonical( $brand ) {
	$brand = trim( (string) $brand );
	if ( $brand === '' ) {
		return '';
	}
	$norm = centinela_tienda_normalize_brand_text( $brand );
	$map  = centinela_tienda_brand_alias_map();
	return isset( $map[ $norm ] ) ? $map[ $norm ] : $brand;
}

/**
 * Construye términos de búsqueda por marca para consultar API y cubrir aliases/submarcas.
 *
 * @param string $marca Marca solicitada por el usuario.
 * @return string[]
 */
function centinela_tienda_brand_query_terms( $marca ) {
	$marca      = trim( (string) $marca );
	$canonical  = centinela_tienda_brand_canonical( $marca );
	$norm       = centinela_tienda_normalize_brand_text( $canonical );
	$terms      = array( $marca, $canonical );

	// Aliases de alta prioridad con comportamiento esperado por negocio.
	if ( $norm === 'kenwood' ) {
		// Syscom mezcla TXPRO con KENWOOD en radios comerciales (p. ej. Portátiles UHF); la API por categoría a veces no trae TXPRO sin búsqueda explícita.
		$terms[] = 'TXPRO';
		$terms[] = 'TX PRO';
		$terms[] = 'KENWOOD';
		$terms[] = 'JVC KENWOOD';
		$terms[] = 'JVC KENWOOD INC';
		$terms[] = 'JVC KENWOOD CORPORATION';
		$terms[] = 'para KENWOOD';
		$terms[] = 'accesorios KENWOOD';
	} elseif ( $norm === 'hilookbyhikvision' ) {
		$terms[] = 'HiLook by HIKVISION';
		$terms[] = 'Hilook';
		$terms[] = 'HIKVISION';
	} elseif ( $norm === 'cambiumnetworks' ) {
		$terms[] = 'CAMBIUM NETWORKS';
		$terms[] = 'CAMBIUM NETWORKS INC';
	}

	$terms = array_values( array_unique( array_filter( array_map( 'trim', $terms ) ) ) );
	return $terms;
}

/**
 * Término listo para el parámetro busqueda de Syscom (alineado con centinela-search).
 *
 * @param string $term Término crudo o alias de marca.
 * @return string Vacío si no hay nada útil que enviar.
 */
function centinela_tienda_api_busqueda( $term ) {
	$term = trim( (string) $term );
	if ( $term === '' ) {
		return '';
	}
	if ( function_exists( 'centinela_normalize_search_term_for_api' ) ) {
		$n = centinela_normalize_search_term_for_api( $term );
		return $n !== '' ? $n : strtolower( $term );
	}
	return strtolower( $term );
}

/**
 * Clave de transient para la lista completa fusionada por marca (misma que get_productos_by_brand).
 *
 * @param string $marca        Marca canónica o solicitada.
 * @param string $categoria_id ID categoría Syscom.
 * @param string $ordenar      Orden API.
 * @return string
 */
function centinela_tienda_brand_merge_cache_key( $marca, $categoria_id = '', $ordenar = 'relevancia' ) {
	return 'centinela_brand_merge_v5_' . md5(
		wp_json_encode(
			array(
				'marca'     => (string) centinela_tienda_brand_canonical( $marca ),
				'categoria' => (string) $categoria_id,
				'orden'     => (string) sanitize_text_field( $ordenar ),
				'ken_cat'   => 'subtree_v1',
			)
		)
	);
}

/**
 * Vista previa rápida por marca (pocas llamadas a Syscom) para mostrar resultados antes del merge completo.
 *
 * @param string $marca        Marca.
 * @param string $categoria_id Categoría opcional.
 * @param string $ordenar      Orden API.
 * @param int    $limit        Máximo de productos a devolver.
 * @return array{productos: array, paginas: int}
 */
function centinela_tienda_get_productos_by_brand_quick( $marca, $categoria_id = '', $ordenar = 'relevancia', $limit = 12 ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array(
			'productos' => array(),
			'paginas'   => 0,
		);
	}
	$marca    = trim( (string) $marca );
	$ordenar  = sanitize_text_field( $ordenar );
	$limit    = max( 1, min( 24, (int) $limit ) );
	$terms    = centinela_tienda_brand_query_terms( $marca );
	$terms    = array_slice( $terms, 0, 2 );
	if ( empty( $terms ) ) {
		return array(
			'productos' => array(),
			'paginas'   => 0,
		);
	}
	$all_items = array();
	$seen      = array();
	$max_calls = 8;
	$api_calls = 0;
	foreach ( $terms as $term ) {
		$busqueda = centinela_tienda_api_busqueda( $term );
		if ( $busqueda === '' ) {
			continue;
		}
		for ( $page_num = 1; $page_num <= 4; $page_num++ ) {
			if ( $api_calls >= $max_calls ) {
				break 2;
			}
			$api_calls++;
			$args = array(
				'pagina'   => $page_num,
				'orden'    => $ordenar,
				'cop'      => true,
				'busqueda' => $busqueda,
			);
			if ( $categoria_id !== '' ) {
				$args['categoria'] = (string) $categoria_id;
			}
			$resp = Centinela_Syscom_API::get_productos( $args );
			if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
				break;
			}
			foreach ( $resp['productos'] as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				$key = $pid !== '' ? (string) $pid : md5( wp_json_encode( $p ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$all_items[]  = $p;
			}
			$api_pages = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			if ( $api_pages > 0 && $page_num >= $api_pages ) {
				break;
			}
		}
	}
	$all_items = centinela_tienda_filter_productos_por_marca( $all_items, $marca, array() );
	$slice     = array_slice( $all_items, 0, $limit );
	return array(
		'productos' => $slice,
		'paginas'   => 0,
	);
}

/**
 * Consulta productos por marca probando variantes de alias y combinando resultados sin duplicados.
 *
 * @param string $marca       Marca a buscar.
 * @param string $categoria_id Categoría opcional.
 * @param int    $pagina      Página principal solicitada.
 * @param string $ordenar     Orden API.
 * @return array{productos: array, paginas: int}
 */
function centinela_tienda_get_productos_by_brand( $marca, $categoria_id = '', $pagina = 1, $ordenar = 'relevancia', $per_page = 12 ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array(
			'productos' => array(),
			'paginas'   => 0,
		);
	}

	$marca = trim( (string) $marca );

	$pagina   = max( 1, (int) $pagina );
	$ordenar  = sanitize_text_field( $ordenar );
	$per_page = max( 1, (int) $per_page );
	$terms    = centinela_tienda_brand_query_terms( $marca );
	if ( empty( $terms ) ) {
		return array(
			'productos' => array(),
			'paginas'   => 0,
		);
	}

	$canonical_marca = centinela_tienda_brand_canonical( $marca );
	$norm_marca      = centinela_tienda_normalize_brand_text( $canonical_marca );

	$cache_key  = centinela_tienda_brand_merge_cache_key( $marca, $categoria_id, $ordenar );
	$cached_all = get_transient( $cache_key );
	$all_items  = is_array( $cached_all ) ? $cached_all : array();

	// Caché antigua sin orden Kenwood: reordenar al leer.
	if ( ! empty( $all_items ) && $norm_marca === 'kenwood' && $categoria_id === '' && function_exists( 'centinela_search_kenwood_radio_boost_score' ) ) {
		usort(
			$all_items,
			static function ( $a, $b ) {
				if ( ! is_array( $a ) || ! is_array( $b ) ) {
					return 0;
				}
				$sa = centinela_search_kenwood_radio_boost_score( $a );
				$sb = centinela_search_kenwood_radio_boost_score( $b );
				return $sb <=> $sa;
			}
		);
	}

	if ( empty( $all_items ) ) {
		$all_items      = array();
		$seen           = array();
		$max_api_calls   = ( $norm_marca === 'kenwood' && $categoria_id === '' ) ? 100 : 60;
		// Reservar llamadas para listados por categoría (subárbol KENWOOD en Syscom).
		$term_budget     = ( $norm_marca === 'kenwood' && $categoria_id === '' ) ? 55 : $max_api_calls;
		$api_calls       = 0;

		foreach ( $terms as $term ) {
			$busqueda = centinela_tienda_api_busqueda( $term );
			if ( $busqueda === '' ) {
				continue;
			}
			for ( $page_num = 1; $page_num <= 30; $page_num++ ) {
				if ( $api_calls >= $term_budget ) {
					break 2;
				}
				$api_calls++;
				$args = array(
					'pagina'   => $page_num,
					'orden'    => $ordenar,
					'cop'      => true,
					'busqueda' => $busqueda,
				);
				if ( $categoria_id !== '' ) {
					$args['categoria'] = (string) $categoria_id;
				}
				$resp = Centinela_Syscom_API::get_productos( $args );
				if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
					break;
				}
				foreach ( $resp['productos'] as $p ) {
					if ( ! is_array( $p ) ) {
						continue;
					}
					$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
					$key = $pid !== '' ? (string) $pid : md5( wp_json_encode( $p ) );
					if ( isset( $seen[ $key ] ) ) {
						continue;
					}
					$seen[ $key ] = true;
					$all_items[]  = $p;
				}
				$api_pages = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
				if ( $api_pages > 0 && $page_num >= $api_pages ) {
					break;
				}
			}
		}

		$trusted_kenwood_cat_ids = array();
		if ( $norm_marca === 'kenwood' && $categoria_id === '' ) {
			$remaining = $max_api_calls - $api_calls;
			if ( $remaining > 0 ) {
				$trusted_kenwood_cat_ids = centinela_tienda_kenwood_category_ids_from_tree();
				$cat_extra               = centinela_tienda_fetch_productos_by_category_ids(
					$trusted_kenwood_cat_ids,
					$ordenar,
					min( 45, $remaining ),
					$seen,
					$api_calls
				);
				foreach ( $cat_extra as $p ) {
					$all_items[] = $p;
				}
			}
		}

		// Quedarnos solo con la marca canónica solicitada (ej. KENWOOD incluye JVC KENWOOD por alias).
		$all_items = centinela_tienda_filter_productos_por_marca( $all_items, $marca, $trusted_kenwood_cat_ids );
		// Kenwood: radios / transceptores primero, luego accesoríos y resto (también al reutilizar caché más abajo).
		if ( $norm_marca === 'kenwood' && $categoria_id === '' && function_exists( 'centinela_search_kenwood_radio_boost_score' ) ) {
			usort(
				$all_items,
				static function ( $a, $b ) {
					if ( ! is_array( $a ) || ! is_array( $b ) ) {
						return 0;
					}
					$sa = centinela_search_kenwood_radio_boost_score( $a );
					$sb = centinela_search_kenwood_radio_boost_score( $b );
					return $sb <=> $sa;
				}
			);
		}
		// Lista fusionada por marca: reutilizar ~1 h en caliente; filtro `centinela_brand_merge_cache_ttl` para ajustar.
		$merge_ttl = (int) apply_filters(
			'centinela_brand_merge_cache_ttl',
			HOUR_IN_SECONDS,
			$marca,
			$categoria_id,
			$ordenar
		);
		$merge_ttl = max( 5 * MINUTE_IN_SECONDS, min( DAY_IN_SECONDS, $merge_ttl ) );
		if ( ! empty( $all_items ) ) {
			set_transient( $cache_key, $all_items, $merge_ttl );
		} else {
			delete_transient( $cache_key );
		}
	}

	$total  = count( $all_items );
	$pages  = $total > 0 ? (int) ceil( $total / $per_page ) : 0;
	$offset = ( $pagina - 1 ) * $per_page;
	$slice  = $total > 0 ? array_slice( $all_items, $offset, $per_page ) : array();

	return array(
		'productos' => $slice,
		'paginas'   => $pages,
	);
}

/**
 * Marcas prioritarias para validar en catálogo.
 *
 * @return string[]
 */
function centinela_tienda_required_marcas() {
	return array(
		'HiLook by HIKVISION',
		'Kenwood',
		'STI',
		'MIMOSA',
		'ALLIED TELESIS',
		'ROSSLARE',
		'AXPRO',
		'LINKEDPRO',
		'CAMBIUM NETWORKS',
	);
}

/**
 * Extrae marcas únicas de una lista de productos (claves: marca, brand, fabricante).
 *
 * @param array $productos Lista de productos de la API.
 * @return array Lista de marcas ordenadas.
 */
function centinela_tienda_extract_marcas( $productos ) {
	$seen   = array();
	$marcas = array();
	foreach ( (array) $productos as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$m = function_exists( 'centinela_tienda_producto_marca' ) ? centinela_tienda_producto_marca( $p ) : '';
		$m = centinela_tienda_brand_canonical( $m );
		if ( $m === '' || isset( $seen[ $m ] ) ) {
			continue;
		}
		$seen[ $m ] = true;
		$marcas[]   = $m;
	}
	sort( $marcas );
	return $marcas;
}

/**
 * Recolecta marcas barriendo productos por categorías raíz (respaldo si GET /marcas falla).
 * Limita llamadas para no saturar la API Syscom (~60 req/min).
 *
 * @return array Nombres de marca únicos, ordenados.
 */
function centinela_tienda_collect_marcas_fallback() {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) || empty( $arbol ) ) {
		return array();
	}
	$all_marcas = array();
	$max_calls  = 50;
	$calls      = 0;
	foreach ( $arbol as $root ) {
		if ( $calls >= $max_calls ) {
			break;
		}
		$cat_id = isset( $root['id'] ) ? (string) $root['id'] : '';
		if ( $cat_id === '' ) {
			continue;
		}
		$paginas_max = 15;
		for ( $p = 1; $p <= $paginas_max; $p++ ) {
			if ( $calls >= $max_calls ) {
				break 2;
			}
			$calls++;
			$resp = Centinela_Syscom_API::get_productos(
				array(
					'categoria' => $cat_id,
					'pagina'    => $p,
					'orden'     => 'relevancia',
					'cop'       => true,
				)
			);
			if ( is_wp_error( $resp ) || empty( $resp['productos'] ) ) {
				break;
			}
			if ( function_exists( 'centinela_tienda_extract_marcas' ) ) {
				$page_marcas = centinela_tienda_extract_marcas( $resp['productos'] );
				$all_marcas  = array_merge( $all_marcas, $page_marcas );
			}
			$paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			if ( $p >= $paginas ) {
				break;
			}
		}
	}
	$all_marcas = array_values( array_unique( array_map( 'trim', $all_marcas ) ) );
	sort( $all_marcas );
	return $all_marcas;
}

/**
 * Extrae marcas únicas recorriendo páginas de productos de una categoría Syscom.
 * Solo incluye marcas que aparecen en al menos un producto devuelto por la API.
 *
 * @param string $categoria_id ID categoría Syscom.
 * @param int    $max_pages    Máximo de páginas de productos a consultar.
 * @return string[] Nombres de marca únicos, ordenados.
 */
function centinela_tienda_collect_marcas_for_categoria( $categoria_id, $max_pages = 20 ) {
	if ( $categoria_id === '' || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	$all_marcas = array();
	$calls      = 0;
	$max_calls  = 30;
	$paginas_cap = max( 1, (int) $max_pages );
	for ( $p = 1; $p <= $paginas_cap; $p++ ) {
		if ( $calls >= $max_calls ) {
			break;
		}
		$calls++;
		$resp = Centinela_Syscom_API::get_productos(
			array(
				'categoria' => (string) $categoria_id,
				'pagina'    => $p,
				'orden'     => 'relevancia',
				'cop'       => true,
			)
		);
		if ( is_wp_error( $resp ) || empty( $resp['productos'] ) ) {
			break;
		}
		if ( function_exists( 'centinela_tienda_extract_marcas' ) ) {
			$page_marcas = centinela_tienda_extract_marcas( $resp['productos'] );
			$all_marcas  = array_merge( $all_marcas, $page_marcas );
		}
		$paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
		if ( $p >= $paginas ) {
			break;
		}
	}
	$all_marcas = array_values( array_unique( array_map( 'trim', $all_marcas ) ) );
	sort( $all_marcas );
	return $all_marcas;
}

/**
 * Bundle de marcas para la tienda.
 *
 * - En /tienda global: usa primero listado oficial de marcas de Syscom (GET /marcas).
 * - En categoría: mantiene muestreo por productos de esa categoría.
 *
 * @param string $categoria_id ID categoría Syscom (vacío = todas / raíz).
 * @param string $cat_path     Ruta amigable (se resuelve a ID si hace falta).
 * @return array{ marcas: string[], source: string, total: int }
 */
function centinela_tienda_get_marcas_bundle( $categoria_id = '', $cat_path = '' ) {
	if ( $categoria_id === '' && $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
		$resolved = centinela_resolve_cat_path_to_syscom_id( trim( (string) $cat_path ) );
		if ( $resolved !== null && (string) $resolved !== '' ) {
			$categoria_id = (string) $resolved;
		}
	}

	if ( $categoria_id !== '' ) {
		$cache_key = 'centinela_sidebar_marcas_v5_cat_' . md5( (string) $categoria_id . '|' . ( centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path ) ? 'km4' : '0' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['marcas'] ) && is_array( $cached['marcas'] ) ) {
			return array(
				'marcas' => $cached['marcas'],
				'source' => isset( $cached['source'] ) ? (string) $cached['source'] : 'cache',
				'total'  => isset( $cached['total'] ) ? (int) $cached['total'] : count( $cached['marcas'] ),
			);
		}
		if ( centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path )
			&& (bool) apply_filters( 'centinela_tienda_kenwood_marcas_from_merged_list', true, $categoria_id, $cat_path ) ) {
			$merged = centinela_tienda_get_kenwood_merged_category_list_cached( (string) $categoria_id, 'relevancia', (string) $cat_path );
			$list   = function_exists( 'centinela_tienda_extract_marcas' ) ? centinela_tienda_extract_marcas( $merged ) : array();
			$source = 'productos_categoria_kenwood_merge';
		} else {
			$list   = centinela_tienda_collect_marcas_for_categoria( $categoria_id );
			$source = 'productos_categoria';
		}
		$bundle = array(
			'marcas' => $list,
			'source' => $source,
			'total'  => count( $list ),
		);
		set_transient( $cache_key, $bundle, 6 * HOUR_IN_SECONDS );
		return $bundle;
	}

	$cache_key = 'centinela_sidebar_marcas_v7_global_api';
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['marcas'] ) && is_array( $cached['marcas'] ) ) {
		return array(
			'marcas' => $cached['marcas'],
			'source' => isset( $cached['source'] ) ? (string) $cached['source'] : 'cache',
			'total'  => isset( $cached['total'] ) ? (int) $cached['total'] : count( $cached['marcas'] ),
		);
	}

	$list   = array();
	$source = 'empty';
	if ( class_exists( 'Centinela_Syscom_API' ) && method_exists( 'Centinela_Syscom_API', 'get_marcas_nombres' ) ) {
		$list = Centinela_Syscom_API::get_marcas_nombres();
		if ( is_array( $list ) && ! empty( $list ) ) {
			// Consolidar aliases (ej. CAMBIUM NETWORKS INC -> CAMBIUM NETWORKS)
			// para evitar marcas duplicadas en el filtro de /tienda.
			$list = array_map( 'centinela_tienda_brand_canonical', $list );
			$list = array_values( array_unique( array_filter( array_map( 'trim', $list ) ) ) );
			sort( $list );
			$source = 'api_marcas';
		}
	}
	if ( empty( $list ) ) {
		$list   = centinela_tienda_collect_marcas_fallback();
		$source = ! empty( $list ) ? 'productos_raiz' : 'empty';
	}
	// Si la API no devolvió marcas (token, red, endpoint), mostrar al menos las marcas prioritarias del sitio.
	if ( empty( $list ) && function_exists( 'centinela_tienda_required_marcas' ) ) {
		$list   = array_map( 'centinela_tienda_brand_canonical', centinela_tienda_required_marcas() );
		$list   = array_values( array_unique( array_filter( array_map( 'trim', $list ) ) ) );
		sort( $list );
		$source = 'required_defaults';
	}
	$bundle = array(
		'marcas' => $list,
		'source' => $source,
		'total'  => count( $list ),
	);
	$ttl = ( $source === 'required_defaults' ) ? 5 * MINUTE_IN_SECONDS : 12 * HOUR_IN_SECONDS;
	set_transient( $cache_key, $bundle, $ttl );
	return $bundle;
}

/**
 * Lista de marcas para sidebar /tienda (misma fuente que REST tienda-marcas).
 * Solo marcas presentes en productos de la API (categoría actual o muestreo global).
 *
 * @param string $categoria_id ID categoría Syscom (vacío = vista global).
 * @param string $cat_path     Ruta de categoría (alternativa a ID).
 * @return string[]
 */
function centinela_tienda_get_marcas_for_sidebar( $categoria_id = '', $cat_path = '' ) {
	$bundle = centinela_tienda_get_marcas_bundle( $categoria_id, $cat_path );
	return isset( $bundle['marcas'] ) ? $bundle['marcas'] : array();
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
 * Precio a mostrar en el listado de tienda (mismo que en detalle y vista rápida).
 * Siempre usa el detalle del producto (get_producto) con caché para que el precio coincida exactamente.
 *
 * @param array  $prod Producto del listado (con precios).
 * @param string $pid  ID del producto (puede venir de producto_id, id o product_id).
 * @return array { 'precio' => string, 'precio_especial' => string, 'tiene_precio_especial' => bool }
 */
function centinela_tienda_precio_para_listado( $prod, $pid ) {
	// Asegurar ID: listado API puede devolver id, producto_id, product_id o ID (mayúscula).
	if ( ( $pid === '' || $pid === null ) && is_array( $prod ) ) {
		$pid = isset( $prod['producto_id'] ) ? $prod['producto_id'] : ( isset( $prod['id'] ) ? $prod['id'] : ( isset( $prod['ID'] ) ? $prod['ID'] : ( isset( $prod['product_id'] ) ? $prod['product_id'] : '' ) ) );
	}
	$pid = trim( (string) $pid );
	// ID numérico para la API (evitar slug tipo "123-slug").
	$pid_api = preg_replace( '/[^0-9]/', '', $pid );
	if ( $pid_api !== '' ) {
		$pid = $pid_api;
	}

	$precios = isset( $prod['precios'] ) && is_array( $prod['precios'] ) ? $prod['precios'] : array();
	$precio_especial = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_descuento'] ) ? $precios['precio_descuento'] : '' );
	$precio_lista = isset( $precios['precio_lista'] ) ? $precios['precio_lista'] : '';
	$precio = '';

	// Usar siempre el detalle del producto (misma fuente que vista rápida y single) para que el precio sea idéntico.
	if ( $pid !== '' && class_exists( 'Centinela_Syscom_API' ) ) {
		$cache_key = 'centinela_precios_detalle_' . $pid;
		$cached = get_transient( $cache_key );
		// Caché guarda array con 'precio', 'precio_especial', 'tiene_precio_especial' ya resueltos.
		if ( is_array( $cached ) && isset( $cached['precio'] ) && $cached['precio'] !== '' ) {
			return array(
				'precio'                  => $cached['precio'],
				'precio_especial'         => isset( $cached['precio_especial'] ) ? $cached['precio_especial'] : '',
				'tiene_precio_especial'   => ! empty( $cached['tiene_precio_especial'] ),
			);
		}

		$producto_full = Centinela_Syscom_API::get_producto( $pid, true );
		if ( ! is_wp_error( $producto_full ) && is_array( $producto_full ) ) {
			// Buscar precio con IVA en toda la respuesta (recursivo) por si la API anida precios.
			$precio_iva = function_exists( 'centinela_find_precio_lista_iva_in_array' ) ? centinela_find_precio_lista_iva_in_array( $producto_full ) : '';
			if ( $precio_iva !== '' ) {
				$precio = $precio_iva;
			} else {
				$full_precios = isset( $producto_full['precios'] ) && is_array( $producto_full['precios'] ) ? $producto_full['precios'] : array();
				$root_keys = array( 'precio_lista_iva', 'precio_lista_con_iva', 'precio_con_iva', 'precio_iva', 'precio_lista_cop', 'precio_cop', 'precio_lista', 'precio_especial', 'precio_descuento' );
				foreach ( $root_keys as $key ) {
					if ( isset( $producto_full[ $key ] ) && ( $producto_full[ $key ] !== '' && $producto_full[ $key ] !== null ) && ! isset( $full_precios[ $key ] ) ) {
						$full_precios[ $key ] = $producto_full[ $key ];
					}
				}
				$precio = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $full_precios ) : ( isset( $full_precios['precio_lista'] ) ? $full_precios['precio_lista'] : '' );
			}
			$precio_especial = isset( $producto_full['precio_especial'] ) ? $producto_full['precio_especial'] : ( isset( $producto_full['precios']['precio_especial'] ) ? $producto_full['precios']['precio_especial'] : ( isset( $producto_full['precios']['precio_descuento'] ) ? $producto_full['precios']['precio_descuento'] : '' ) );
			$tiene_precio_especial = $precio_especial !== '' && $precio_especial !== null;
			if ( $precio === '' ) {
				$precio = $precio_especial ? $precio_especial : $precio_lista;
			}
			set_transient( $cache_key, array(
				'precio'                  => $precio,
				'precio_especial'         => $precio_especial,
				'tiene_precio_especial'   => $tiene_precio_especial,
			), 1 * HOUR_IN_SECONDS );
			return array(
				'precio'                  => $precio,
				'precio_especial'         => $precio_especial,
				'tiene_precio_especial'   => $tiene_precio_especial,
			);
		}
	}
	if ( $precio === '' ) {
		$precio = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
	}
	if ( $precio === '' ) {
		$precio = $precio_especial ? $precio_especial : $precio_lista;
	}
	$tiene_precio_especial = $precio_especial !== '' && $precio_especial !== null;
	return array(
		'precio'                  => $precio,
		'precio_especial'          => $precio_especial,
		'tiene_precio_especial'    => $tiene_precio_especial,
	);
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
				return centinela_tienda_brand_canonical( (string) $val['nombre'] );
			}
			if ( trim( (string) $val ) !== '' ) {
				return centinela_tienda_brand_canonical( (string) $val );
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
 * Texto crudo de marca/fabricante para coincidencias (sin solo canónico).
 *
 * @param array $prod Producto API.
 * @return string
 */
function centinela_tienda_producto_marca_raw_for_haystack( $prod ) {
	if ( ! is_array( $prod ) ) {
		return '';
	}
	$parts = array();
	foreach ( array( 'marca', 'brand', 'fabricante' ) as $key ) {
		if ( ! isset( $prod[ $key ] ) ) {
			continue;
		}
		$val = $prod[ $key ];
		if ( is_array( $val ) && isset( $val['nombre'] ) ) {
			$parts[] = (string) $val['nombre'];
		} elseif ( is_string( $val ) && trim( $val ) !== '' ) {
			$parts[] = $val;
		}
	}
	return trim( implode( ' ', $parts ) );
}

/**
 * ¿El nombre de categoría (Syscom) pertenece al árbol KENWOOD / JVC KENWOOD?
 *
 * @param string $nombre Nombre visible de la categoría.
 * @return bool
 */
function centinela_tienda_categoria_nombre_matches_kenwood( $nombre ) {
	$n = centinela_tienda_normalize_brand_text( (string) $nombre );
	if ( $n === '' ) {
		return false;
	}
	if ( strpos( $n, 'kenwood' ) !== false ) {
		return true;
	}
	if ( strpos( $n, 'jvckenwood' ) !== false ) {
		return true;
	}
	return false;
}

/**
 * IDs de categoría en un subárbol (nodo + descendientes).
 *
 * @param array $nodo Nodo del árbol de categorías.
 * @return string[]
 */
function centinela_tienda_collect_subtree_ids_from_node( $nodo ) {
	$out   = array();
	$stack = array( $nodo );
	while ( ! empty( $stack ) ) {
		$n = array_pop( $stack );
		if ( ! is_array( $n ) ) {
			continue;
		}
		if ( isset( $n['id'] ) && (string) $n['id'] !== '' ) {
			$out[] = (string) $n['id'];
		}
		if ( ! empty( $n['hijos'] ) && is_array( $n['hijos'] ) ) {
			foreach ( $n['hijos'] as $h ) {
				$stack[] = $h;
			}
		}
	}
	return $out;
}

/**
 * IDs de categoría bajo nodos cuyo nombre indica KENWOOD (como listadopormarca en Syscom).
 *
 * @return string[]
 */
function centinela_tienda_kenwood_category_ids_from_tree() {
	if ( ! class_exists( 'Centinela_Syscom_API' ) || ! method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
		return array();
	}
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) || ! is_array( $arbol ) ) {
		return array();
	}
	$ids = array();
	$walk = function( $nodo ) use ( &$ids, &$walk ) {
		if ( ! is_array( $nodo ) ) {
			return;
		}
		$nombre = isset( $nodo['nombre'] ) ? (string) $nodo['nombre'] : '';
		if ( $nombre !== '' && centinela_tienda_categoria_nombre_matches_kenwood( $nombre ) ) {
			$ids = array_merge( $ids, centinela_tienda_collect_subtree_ids_from_node( $nodo ) );
			return;
		}
		if ( ! empty( $nodo['hijos'] ) && is_array( $nodo['hijos'] ) ) {
			foreach ( $nodo['hijos'] as $h ) {
				$walk( $h );
			}
		}
	};
	foreach ( $arbol as $root ) {
		$walk( $root );
	}
	return array_values( array_unique( array_filter( array_map( 'strval', $ids ) ) ) );
}

/**
 * Productos paginados por lista de categorías (dedupe con $seen).
 *
 * @param string[] $ids        IDs Syscom.
 * @param string   $ordenar    Orden API.
 * @param int      $max_calls  Límite de llamadas get_productos.
 * @param array    $seen       Mapa producto_id => true (por referencia).
 * @param int      $api_calls  Contador (por referencia).
 * @return array
 */
function centinela_tienda_fetch_productos_by_category_ids( $ids, $ordenar, $max_calls, array &$seen, &$api_calls ) {
	$out = array();
	if ( empty( $ids ) || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return $out;
	}
	$ordenar = sanitize_text_field( (string) $ordenar );
	foreach ( $ids as $cat_id ) {
		if ( $api_calls >= $max_calls ) {
			break;
		}
		$cat_id = trim( (string) $cat_id );
		if ( $cat_id === '' ) {
			continue;
		}
		for ( $page_num = 1; $page_num <= 30; $page_num++ ) {
			if ( $api_calls >= $max_calls ) {
				break 2;
			}
			$api_calls++;
			$resp = Centinela_Syscom_API::get_productos(
				array(
					'categoria' => $cat_id,
					'pagina'    => $page_num,
					'orden'     => $ordenar,
					'cop'       => true,
				)
			);
			if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
				break;
			}
			foreach ( $resp['productos'] as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				$key = $pid !== '' ? (string) $pid : md5( wp_json_encode( $p ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$out[]        = $p;
			}
			$api_pages = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			if ( $api_pages > 0 && $page_num >= $api_pages ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * ¿El producto pertenece a alguna de las categorías Syscom dadas?
 *
 * @param array    $p    Producto API.
 * @param string[] $ids IDs de categoría.
 * @return bool
 */
function centinela_tienda_producto_in_category_ids( $p, $ids ) {
	if ( ! is_array( $p ) || empty( $ids ) ) {
		return false;
	}
	$set = array_flip( array_map( 'strval', $ids ) );
	foreach ( array( 'categoria_id', 'id_categoria', 'categoria', 'id_categoria_principal' ) as $key ) {
		if ( ! isset( $p[ $key ] ) ) {
			continue;
		}
		$v = $p[ $key ];
		if ( is_array( $v ) && isset( $v['id'] ) ) {
			$v = $v['id'];
		}
		$id = (string) $v;
		if ( $id !== '' && isset( $set[ $id ] ) ) {
			return true;
		}
	}
	foreach ( array( 'categorias', 'categorías' ) as $ck ) {
		if ( empty( $p[ $ck ] ) || ! is_array( $p[ $ck ] ) ) {
			continue;
		}
		foreach ( $p[ $ck ] as $c ) {
			if ( is_array( $c ) && isset( $c['id'] ) ) {
				$id = (string) $c['id'];
				if ( $id !== '' && isset( $set[ $id ] ) ) {
					return true;
				}
			}
		}
	}
	return false;
}

/**
 * Coincidencia de respaldo cuando el listado /marcas y el campo marca del producto no alinean,
 * pero la API ya devolvió el ítem por búsqueda (título, modelo, fabricante, descripción).
 *
 * @param array  $p          Producto API.
 * @param string $marca_trim Marca canónica.
 * @param string $marca_norm Marca normalizada para strpos.
 * @return bool
 */
function centinela_tienda_producto_matches_brand_search_fallback( $p, $marca_trim, $marca_norm ) {
	if ( ! is_array( $p ) || $marca_norm === '' ) {
		return false;
	}
	$raw    = centinela_tienda_producto_marca_raw_for_haystack( $p );
	$pm     = centinela_tienda_producto_marca( $p );
	$titulo = isset( $p['titulo'] ) ? (string) $p['titulo'] : ( isset( $p['nombre'] ) ? (string) $p['nombre'] : '' );
	$modelo = isset( $p['modelo'] ) ? (string) $p['modelo'] : '';
	$desc   = isset( $p['descripcion'] ) ? (string) $p['descripcion'] : ( isset( $p['description'] ) ? (string) $p['description'] : '' );
	$haystack = centinela_tienda_normalize_brand_text( $titulo . ' ' . $modelo . ' ' . $desc . ' ' . $raw . ' ' . $pm );
	if ( $haystack === '' ) {
		return false;
	}
	// Marcas de 3 caracteres (p. ej. STI): solo si están en la lista permitida (evita ruido en texto).
	$short_norms = (array) apply_filters( 'centinela_tienda_brand_short_norms_for_fallback', array( 'sti' ) );
	$short_norms = array_map( 'centinela_tienda_normalize_brand_text', $short_norms );
	if ( strlen( $marca_norm ) === 3 && in_array( $marca_norm, $short_norms, true ) && strpos( $haystack, $marca_norm ) !== false ) {
		return true;
	}
	if ( strlen( $marca_norm ) >= 4 && strpos( $haystack, $marca_norm ) !== false ) {
		return true;
	}
	// Marcas compuestas (ej. "HiLook by HIKVISION"): fragmentos ≥5 caracteres para limitar falsos positivos.
	foreach ( preg_split( '/[\s,&\/]+/', $marca_trim, -1, PREG_SPLIT_NO_EMPTY ) as $tok ) {
		$tn = centinela_tienda_normalize_brand_text( $tok );
		if ( strlen( $tn ) < 5 ) {
			continue;
		}
		if ( strpos( $haystack, $tn ) !== false ) {
			return true;
		}
	}
	return false;
}

/**
 * Filtra una lista de productos por marca (nombre exacto, sin depender de la API).
 *
 * @param array    $productos             Lista de productos de la API.
 * @param string   $marca                 Nombre de la marca a filtrar (vacío = no filtrar).
 * @param string[] $trusted_category_ids  IDs de categoría Syscom donde confiamos el listado (ej. subárbol KENWOOD).
 * @return array Lista filtrada.
 */
function centinela_tienda_filter_productos_por_marca( $productos, $marca = '', $trusted_category_ids = array() ) {
	if ( $marca === '' || empty( $productos ) ) {
		return $productos;
	}
	// Normalización para evitar que el filtro falle por mayúsculas/espacios.
	$marca_trim = centinela_tienda_brand_canonical( trim( (string) $marca ) );
	$marca_norm = centinela_tienda_normalize_brand_text( $marca_trim );
	$out        = array();
	foreach ( $productos as $p ) {
		$prod_marca = centinela_tienda_brand_canonical( centinela_tienda_producto_marca( $p ) );
		if ( $prod_marca !== '' ) {
			$prod_norm = centinela_tienda_normalize_brand_text( $prod_marca );
			if ( $prod_norm === $marca_norm ) {
				$out[] = $p;
				continue;
			}
		}
		if ( $marca_norm === 'kenwood' && is_array( $p ) ) {
			if ( centinela_tienda_producto_matches_brand_family( $p, $marca ) ) {
				$out[] = $p;
				continue;
			}
			if ( ! empty( $trusted_category_ids ) && centinela_tienda_producto_in_category_ids( $p, $trusted_category_ids ) ) {
				$out[] = $p;
				continue;
			}
		}
		if ( is_array( $p ) && centinela_tienda_producto_matches_brand_search_fallback( $p, $marca_trim, $marca_norm ) ) {
			$out[] = $p;
			continue;
		}
		// Referencia de modelo en ?marca= (p. ej. TK-3000-KV2): el producto es KENWOOD, no una “marca” con ese nombre.
		if ( is_array( $p ) && function_exists( 'centinela_search_query_looks_like_product_reference' ) && centinela_search_query_looks_like_product_reference( $marca )
			&& function_exists( 'centinela_producto_matches_search' ) && centinela_producto_matches_search( $marca, $p ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/**
 * Coincidencia extendida por familia de marca (Kenwood/JVC Kenwood).
 *
 * @param array  $producto Producto API.
 * @param string $marca    Marca objetivo.
 * @return bool
 */
function centinela_tienda_producto_matches_brand_family( $producto, $marca ) {
	if ( ! is_array( $producto ) || trim( (string) $marca ) === '' ) {
		return false;
	}
	$target_canonical = centinela_tienda_brand_canonical( (string) $marca );
	$target_norm      = centinela_tienda_normalize_brand_text( $target_canonical );
	$prod_marca       = centinela_tienda_producto_marca( $producto );
	$prod_norm        = centinela_tienda_normalize_brand_text( $prod_marca );
	if ( $prod_norm !== '' && $prod_norm === $target_norm ) {
		return true;
	}
	if ( $target_norm !== 'kenwood' ) {
		return false;
	}
	$titulo = isset( $producto['titulo'] ) ? (string) $producto['titulo'] : ( isset( $producto['nombre'] ) ? (string) $producto['nombre'] : '' );
	$modelo = isset( $producto['modelo'] ) ? (string) $producto['modelo'] : ( isset( $producto['sku'] ) ? (string) $producto['sku'] : '' );
	$desc   = isset( $producto['descripcion'] ) ? (string) $producto['descripcion'] : ( isset( $producto['description'] ) ? (string) $producto['description'] : '' );
	$cat_text = '';
	if ( isset( $producto['categorías'] ) && is_array( $producto['categorías'] ) ) {
		foreach ( $producto['categorías'] as $c ) {
			if ( is_array( $c ) && isset( $c['nombre'] ) ) {
				$cat_text .= ' ' . (string) $c['nombre'];
			} elseif ( is_string( $c ) ) {
				$cat_text .= ' ' . $c;
			}
		}
	}
	if ( isset( $producto['categorias'] ) && is_array( $producto['categorias'] ) ) {
		foreach ( $producto['categorias'] as $c ) {
			if ( is_array( $c ) && isset( $c['nombre'] ) ) {
				$cat_text .= ' ' . (string) $c['nombre'];
			} elseif ( is_string( $c ) ) {
				$cat_text .= ' ' . $c;
			}
		}
	}
	$raw_marca = centinela_tienda_producto_marca_raw_for_haystack( $producto );
	$haystack  = centinela_tienda_normalize_brand_text( $titulo . ' ' . $modelo . ' ' . $desc . ' ' . $cat_text . ' ' . $prod_marca . ' ' . $raw_marca );
	if ( $haystack === '' ) {
		return false;
	}
	if ( strpos( $haystack, 'kenwood' ) !== false ) {
		return true;
	}
	// Línea TXPRO listada bajo radios comerciales KENWOOD en Syscom.
	return strpos( $haystack, 'txpro' ) !== false;
}

/**
 * Barrido de categorías para completar familia de marca cuando busqueda API no devuelve todo.
 *
 * @param string $marca       Marca objetivo.
 * @param string $categoria_id Categoría opcional.
 * @return array
 */
function centinela_tienda_collect_productos_brand_family( $marca, $categoria_id = '' ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	$targets = array();
	if ( $categoria_id !== '' ) {
		$targets[] = (string) $categoria_id;
	} else {
		$arbol = Centinela_Syscom_API::get_categorias_arbol();
		if ( ! is_wp_error( $arbol ) && is_array( $arbol ) ) {
			foreach ( $arbol as $root ) {
				if ( isset( $root['id'] ) && (string) $root['id'] !== '' ) {
					$targets[] = (string) $root['id'];
				}
			}
		}
	}
	$targets = array_values( array_unique( array_filter( array_map( 'trim', $targets ) ) ) );
	if ( empty( $targets ) ) {
		return array();
	}
	// Control de costo: limitar categorías raíz a un subconjunto para mantener respuesta del filtro ágil.
	if ( $categoria_id === '' && count( $targets ) > 10 ) {
		$targets = array_slice( $targets, 0, 10 );
	}
	$out       = array();
	$seen      = array();
	$calls     = 0;
	$max_calls = 24;
	foreach ( $targets as $cat_id ) {
		for ( $page = 1; $page <= 3; $page++ ) {
			if ( $calls >= $max_calls ) {
				break 2;
			}
			$calls++;
			$resp = Centinela_Syscom_API::get_productos(
				array(
					'categoria' => (string) $cat_id,
					'pagina'    => $page,
					'orden'     => 'relevancia',
					'cop'       => true,
				)
			);
			if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
				break;
			}
			foreach ( $resp['productos'] as $p ) {
				if ( ! is_array( $p ) || ! centinela_tienda_producto_matches_brand_family( $p, $marca ) ) {
					continue;
				}
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				$key = $pid !== '' ? (string) $pid : md5( wp_json_encode( $p ) );
				if ( isset( $seen[ $key ] ) ) {
					continue;
				}
				$seen[ $key ] = true;
				$out[]        = $p;
			}
			if ( count( $out ) >= 120 ) {
				break 2;
			}
			$api_pages = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			if ( $api_pages > 0 && $page >= $api_pages ) {
				break;
			}
		}
	}
	return $out;
}

/**
 * Verifica si una marca tiene productos visibles (global o por categoría).
 *
 * @param string $marca        Marca objetivo.
 * @param string $categoria_id Categoría Syscom opcional.
 * @return bool
 */
function centinela_tienda_marca_has_productos( $marca, $categoria_id = '' ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return false;
	}
	$marca = centinela_tienda_brand_canonical( $marca );
	if ( $marca === '' ) {
		return false;
	}
	$queries = array( $marca );
	$norm    = centinela_tienda_normalize_brand_text( $marca );
	if ( $norm === 'hilookbyhikvision' ) {
		$queries[] = 'Hilook';
		$queries[] = 'HiLook';
		$queries[] = 'HIKVISION';
	} elseif ( $norm === 'kenwood' ) {
		$queries[] = 'JVC KENWOOD';
		$queries[] = 'JVC KENWOOD INC';
	}
	$queries = array_values( array_unique( array_filter( array_map( 'trim', $queries ) ) ) );
	foreach ( $queries as $query ) {
		$bq = centinela_tienda_api_busqueda( $query );
		if ( $bq === '' ) {
			continue;
		}
		$args = array(
			'busqueda' => $bq,
			'pagina'   => 1,
			'orden'    => 'relevancia',
			'cop'      => true,
		);
		if ( $categoria_id !== '' ) {
			$args['categoria'] = (string) $categoria_id;
		}
		$resp = Centinela_Syscom_API::get_productos( $args );
		if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
			continue;
		}
		$matches = centinela_tienda_filter_productos_por_marca( $resp['productos'], $marca );
		if ( ! empty( $matches ) ) {
			return true;
		}
	}
	return false;
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
		// El listado API a veces no trae precio COP (0); el detalle/quickview sí. No ocultar esos ítems por rango.
		if ( (bool) apply_filters( 'centinela_tienda_price_filter_include_unknown', true, $min_num, $max_num, $p ) && $precio <= 0 ) {
			$out[] = $p;
			continue;
		}
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
 * HTML de “esqueleto” mientras el navegador pide el listado por marca (SSR diferido).
 *
 * @return string
 */
function centinela_tienda_render_brand_loading_shell() {
	ob_start();
	?>
	<div class="centinela-tienda__grid centinela-tienda__grid--skeleton" aria-busy="true" role="status">
		<?php
		for ( $i = 0; $i < 8; $i++ ) :
			?>
			<div class="centinela-tienda__card centinela-tienda__card--skeleton" aria-hidden="true"></div>
			<?php
		endfor;
		?>
	</div>
	<p class="centinela-tienda__defer-brand-hint"><?php esc_html_e( 'Cargando productos de la marca…', 'centinela-group-theme' ); ?></p>
	<?php
	return trim( ob_get_clean() );
}

/**
 * ¿Servir HTML ligero en /tienda/?marca=… (pág. 1) y rellenar grid vía REST con vista previa rápida?
 * Mejora TTFB; desactivar con: add_filter( 'centinela_tienda_defer_brand_ssr', '__return_false' ); si priorizas SEO del HTML inicial.
 *
 * @param string $marca        Marca (query).
 * @param string $categoria_id ID categoría Syscom.
 * @param int    $pagina       Página 1-based.
 * @param string $min_price    Filtro precio.
 * @param string $max_price    Filtro precio.
 * @return bool
 */
function centinela_tienda_should_defer_brand_ssr( $marca, $categoria_id, $pagina, $min_price, $max_price ) {
	$marca = trim( (string) $marca );
	if ( $marca === '' || (int) $pagina !== 1 ) {
		return false;
	}
	if ( trim( (string) $min_price ) !== '' || trim( (string) $max_price ) !== '' ) {
		return false;
	}
	return (bool) apply_filters( 'centinela_tienda_defer_brand_ssr', true, $marca, $categoria_id, $pagina, $min_price, $max_price );
}

/**
 * Imprime el mensaje cuando el grid de la tienda queda vacío (marca, categoría, precio, API Syscom).
 *
 * @param string $marca     Marca activa (query).
 * @param string $cat_path  Ruta de categoría amigable (vacío = toda la tienda).
 * @param string $min_price Precio mínimo.
 * @param string $max_price Precio máximo.
 */
function centinela_tienda_print_empty_grid_message( $marca = '', $cat_path = '', $min_price = '', $max_price = '' ) {
	$marca_t  = trim( (string) $marca );
	$in_cat   = ( trim( (string) $cat_path ) !== '' );
	$price_on = ( trim( (string) $min_price ) !== '' || trim( (string) $max_price ) !== '' );

	if ( $marca_t !== '' ) {
		if ( $in_cat ) {
			echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
			echo esc_html(
				sprintf(
					/* translators: %s: brand name */
					__( 'No hay productos que coincidan con la marca «%s» en esta categoría.', 'centinela-group-theme' ),
					$marca_t
				)
			);
			echo '</p>';
		} else {
			echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
			echo esc_html(
				sprintf(
					/* translators: %s: brand name */
					__( 'No hay productos que coincidan con la marca «%s».', 'centinela-group-theme' ),
					$marca_t
				)
			);
			echo '</p>';
		}
		if ( $in_cat ) {
			$url_marca_solo = add_query_arg( 'marca', $marca_t, home_url( '/tienda/' ) );
			printf(
				'<p class="centinela-tienda__empty centinela-tienda__empty--hint"><a href="%s">%s</a></p>',
				esc_url( $url_marca_solo ),
				esc_html__( 'Probar la misma marca sin filtrar por categoría (toda la tienda)', 'centinela-group-theme' )
			);
		}
		if ( $price_on ) {
			echo '<p class="centinela-tienda__empty centinela-tienda__empty--hint">';
			esc_html_e( 'Si aplicaste un rango de precio, puede que ningún producto de esa marca entre en ese rango.', 'centinela-group-theme' );
			echo '</p>';
		}
		return;
	}

	if ( $price_on && $in_cat ) {
		echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
		esc_html_e( 'No hay productos en esta categoría que coincidan con el rango de precio seleccionado.', 'centinela-group-theme' );
		echo '</p>';
		return;
	}
	if ( $price_on && ! $in_cat ) {
		echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
		esc_html_e( 'No hay productos que coincidan con el rango de precio seleccionado.', 'centinela-group-theme' );
		echo '</p>';
		return;
	}

	if ( $in_cat ) {
		echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
		esc_html_e( 'No hay productos disponibles en esta categoría.', 'centinela-group-theme' );
		echo '</p>';
	} else {
		echo '<p class="centinela-tienda__empty centinela-tienda__empty--main">';
		esc_html_e( 'No hay productos disponibles.', 'centinela-group-theme' );
		echo '</p>';
	}
}

/**
 * Si get_productos sin categoría devuelve vacío, prueba varias raíces del árbol Syscom (la primera puede no tener listado).
 *
 * @param array $args_base pagina, orden, cop (sin categoria).
 * @return array{productos: array, paginas: int}|null
 */
function centinela_tienda_fallback_productos_by_arbol_roots( $args_base ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) || ! method_exists( 'Centinela_Syscom_API', 'get_categorias_arbol' ) ) {
		return null;
	}
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) || empty( $arbol ) || ! is_array( $arbol ) ) {
		return null;
	}
	foreach ( array_slice( $arbol, 0, 15 ) as $root ) {
		if ( empty( $root['id'] ) ) {
			continue;
		}
		$args              = $args_base;
		$args['categoria'] = (string) $root['id'];
		$resp              = Centinela_Syscom_API::get_productos( $args );
		if ( ! is_wp_error( $resp ) && ! empty( $resp['productos'] ) && is_array( $resp['productos'] ) ) {
			return array(
				'productos' => $resp['productos'],
				'paginas'   => isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0,
			);
		}
	}
	return null;
}

/**
 * Adapta una fila del buscador unificado (centinela_search_productos_syscom) al shape que espera el grid de /tienda/.
 *
 * @param array $row Ítem con id, titulo, modelo, marca, imagen, etc.
 * @return array Producto tipo listado API.
 */
function centinela_tienda_map_search_row_to_listado_prod( $row ) {
	if ( ! is_array( $row ) ) {
		return array();
	}
	$id = isset( $row['id'] ) ? trim( (string) $row['id'] ) : '';
	return array(
		'producto_id' => $id,
		'id'          => $id,
		'titulo'      => isset( $row['titulo'] ) ? (string) $row['titulo'] : '',
		'modelo'      => isset( $row['modelo'] ) ? (string) $row['modelo'] : '',
		'img_portada' => isset( $row['imagen'] ) ? (string) $row['imagen'] : '',
		'marca'       => isset( $row['marca'] ) ? (string) $row['marca'] : '',
	);
}

/**
 * ¿La ruta de tienda está bajo "Radios comerciales KENWOOD" en Syscom?
 *
 * En la API, GET /productos?categoria= a veces no mezcla TXPRO y KENWOOD como en la web pública;
 * fusionamos con búsqueda por marca en la misma categoría.
 *
 * @param string $cat_path Ruta slug (ej. radiocomunicacion/radios-comerciales-kenwood/portatiles-uhf).
 * @return bool
 */
function centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path ) {
	$cat_path = trim( (string) $cat_path, '/' );
	if ( $cat_path === '' ) {
		return false;
	}
	return (bool) preg_match( '#(^|/)radios-comerciales-kenwood(/|$)#i', $cat_path );
}

/**
 * Ruta tipo …/radios-comerciales-kenwood/…/portatiles-uhf (último segmento).
 *
 * @param string $cat_path Ruta slug.
 * @return bool
 */
function centinela_tienda_cat_path_is_kenwood_portatiles_uhf( $cat_path ) {
	return (bool) preg_match( '#/portatiles-uhf$#i', trim( (string) $cat_path, '/' ) );
}

/**
 * Filas de búsqueda global a incluir en el merge portátiles UHF: excluye cables/programadores obvios.
 *
 * @param array $p Producto API (listado).
 * @return bool
 */
function centinela_tienda_portatiles_uhf_global_busqueda_row_keep( $p ) {
	if ( ! is_array( $p ) ) {
		return false;
	}
	$t = isset( $p['titulo'] ) ? (string) $p['titulo'] : '';
	$m = isset( $p['modelo'] ) ? (string) $p['modelo'] : '';
	$tl = strtolower( remove_accents( $t . ' ' . $m ) );
	if ( $tl === '' ) {
		return false;
	}
	$cable_hints = array(
		'programador',
		'programacion',
		'programación',
		'cable de program',
		'cable program',
		'software de program',
		'software de programación',
	);
	foreach ( $cable_hints as $h ) {
		if ( strpos( $tl, $h ) !== false ) {
			return false;
		}
	}
	if ( ! function_exists( 'centinela_normalize_for_match' ) ) {
		return true;
	}
	$blob = centinela_normalize_for_match( $t . ' ' . $m );
	if ( $blob === '' ) {
		return false;
	}
	$hints = array(
		'radio', 'portatil', 'uhf', 'vhf', 'transceptor', 'walkie', 'talkie', 'handheld',
		'watts', 'mhz', 'kenwood', 'icom', 'nexedge', 'dmr', 'digital', 'analog',
		'nx', 'pkt', 'tk',
	);
	foreach ( $hints as $h ) {
		$nh = centinela_normalize_for_match( $h );
		if ( $nh !== '' && strpos( $blob, $nh ) !== false ) {
			return (bool) apply_filters( 'centinela_tienda_portatiles_uhf_global_busqueda_row_keep', true, $p, $tl );
		}
	}
	return (bool) apply_filters( 'centinela_tienda_portatiles_uhf_global_busqueda_row_keep', false, $p, $tl );
}

/**
 * Une varias listas de productos API sin duplicar por producto_id / id.
 *
 * @param array $lists Lista de arrays de productos.
 * @return array
 */
function centinela_tienda_merge_productos_unique( $lists ) {
	$seen = array();
	$out  = array();
	if ( ! is_array( $lists ) ) {
		return $out;
	}
	foreach ( $lists as $list ) {
		if ( ! is_array( $list ) ) {
			continue;
		}
		foreach ( $list as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$pid = isset( $p['producto_id'] ) ? (string) $p['producto_id'] : ( isset( $p['id'] ) ? (string) $p['id'] : '' );
			$key = $pid !== '' ? $pid : md5( wp_json_encode( $p ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $p;
		}
	}
	return $out;
}

/**
 * Listado fusionado: categoría + búsqueda por marca (TXPRO, KENWOOD / JVC KENWOOD, etc.) en la misma categoría.
 *
 * @param string $categoria_id ID categoría Syscom.
 * @param string $ordenar      Orden API.
 * @param string $cat_path     Ruta tienda (para merge extra portátiles UHF vía busqueda global).
 * @return array Productos sin paginar.
 */
function centinela_tienda_fetch_kenwood_comercial_category_merged( $categoria_id, $ordenar = 'relevancia', $cat_path = '' ) {
	$categoria_id = trim( (string) $categoria_id );
	if ( $categoria_id === '' || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return array();
	}
	$ordenar         = sanitize_text_field( $ordenar );
	$blocks          = array();
	$max_cat_pages   = max( 1, min( 40, (int) apply_filters( 'centinela_tienda_kenwood_merge_max_cat_pages', 20, $categoria_id ) ) );
	$max_brand_pages = max( 1, min( 15, (int) apply_filters( 'centinela_tienda_kenwood_merge_max_brand_pages', 6, $categoria_id ) ) );
	$max_terms       = max( 1, min( 8, (int) apply_filters( 'centinela_tienda_kenwood_merge_brand_terms_max', 6, $categoria_id ) ) );

	for ( $p = 1; $p <= $max_cat_pages; $p++ ) {
		$resp = Centinela_Syscom_API::get_productos(
			array(
				'categoria' => $categoria_id,
				'pagina'    => $p,
				'orden'     => $ordenar,
				'cop'       => true,
			)
		);
		if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
			break;
		}
		$blocks[] = $resp['productos'];
		$paginas  = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
		if ( $paginas > 0 && $p >= $paginas ) {
			break;
		}
	}

	$terms = function_exists( 'centinela_tienda_brand_query_terms' ) ? centinela_tienda_brand_query_terms( 'KENWOOD' ) : array( 'KENWOOD' );
	$terms = array_slice( array_values( array_unique( array_filter( array_map( 'trim', $terms ) ) ) ), 0, $max_terms );
	foreach ( $terms as $term ) {
		$bq = function_exists( 'centinela_tienda_api_busqueda' ) ? centinela_tienda_api_busqueda( $term ) : $term;
		if ( $bq === '' ) {
			continue;
		}
		for ( $p = 1; $p <= $max_brand_pages; $p++ ) {
			$resp = Centinela_Syscom_API::get_productos(
				array(
					'categoria' => $categoria_id,
					'busqueda'  => $bq,
					'pagina'    => $p,
					'orden'     => $ordenar,
					'cop'       => true,
				)
			);
			if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
				break;
			}
			$blocks[] = $resp['productos'];
			$paginas  = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			if ( $paginas > 0 && $p >= $paginas ) {
				break;
			}
		}
	}

	$merge_global_uhf =
		trim( (string) $cat_path ) !== ''
		&& centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path )
		&& centinela_tienda_cat_path_is_kenwood_portatiles_uhf( $cat_path )
		&& (bool) apply_filters( 'centinela_tienda_kenwood_portatiles_uhf_merge_global_busqueda', true, $categoria_id, $cat_path );

	if ( $merge_global_uhf ) {
		$g_terms = apply_filters(
			'centinela_tienda_kenwood_portatiles_uhf_global_busqueda_terms',
			array( 'NX+1300', 'PKT+300', 'KENWOOD+NX-1300' ),
			$categoria_id,
			$cat_path
		);
		$g_terms     = array_slice( array_values( array_unique( array_filter( array_map( 'trim', $g_terms ) ) ) ), 0, 8 );
		$max_g_pages = max( 1, min( 4, (int) apply_filters( 'centinela_tienda_kenwood_portatiles_uhf_global_max_pages', 2, $categoria_id, $cat_path ) ) );
		foreach ( $g_terms as $gterm ) {
			if ( $gterm === '' ) {
				continue;
			}
			for ( $gp = 1; $gp <= $max_g_pages; $gp++ ) {
				$resp = Centinela_Syscom_API::get_productos(
					array(
						'busqueda' => $gterm,
						'pagina'   => $gp,
						'orden'    => $ordenar,
						'cop'      => true,
					)
				);
				if ( is_wp_error( $resp ) || empty( $resp['productos'] ) || ! is_array( $resp['productos'] ) ) {
					break;
				}
				$keep_rows = array();
				foreach ( $resp['productos'] as $row ) {
					if ( centinela_tienda_portatiles_uhf_global_busqueda_row_keep( $row ) ) {
						$keep_rows[] = $row;
					}
				}
				if ( ! empty( $keep_rows ) ) {
					$blocks[] = $keep_rows;
				}
				$gpags = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
				if ( $gpags > 0 && $gp >= $gpags ) {
					break;
				}
			}
		}
	}

	$merged = centinela_tienda_merge_productos_unique( $blocks );
	if ( function_exists( 'centinela_search_kenwood_radio_boost_score' ) ) {
		usort(
			$merged,
			static function ( $a, $b ) {
				if ( ! is_array( $a ) || ! is_array( $b ) ) {
					return 0;
				}
				$sa = centinela_search_kenwood_radio_boost_score( $a );
				$sb = centinela_search_kenwood_radio_boost_score( $b );
				if ( $sb !== $sa ) {
					return $sb <=> $sa;
				}
				$ta = isset( $a['titulo'] ) ? (string) $a['titulo'] : '';
				$tb = isset( $b['titulo'] ) ? (string) $b['titulo'] : '';
				return strcasecmp( $ta, $tb );
			}
		);
	}
	return $merged;
}

/**
 * Lista fusionada KENWOOD (categoría + búsqueda) con transient corto para compartir entre grid y sidebar.
 *
 * @param string $categoria_id ID categoría.
 * @param string $ordenar      Orden API.
 * @param string $cat_path     Ruta tienda (merge global portátiles UHF).
 * @return array
 */
function centinela_tienda_get_kenwood_merged_category_list_cached( $categoria_id, $ordenar = 'relevancia', $cat_path = '' ) {
	$categoria_id = trim( (string) $categoria_id );
	if ( $categoria_id === '' ) {
		return array();
	}
	$ordenar = sanitize_text_field( $ordenar );
	$guf     = '';
	if ( centinela_tienda_cat_path_is_kenwood_portatiles_uhf( $cat_path )
		&& centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path )
		&& (bool) apply_filters( 'centinela_tienda_kenwood_portatiles_uhf_merge_global_busqueda', true, $categoria_id, $cat_path ) ) {
		$guf = '|guf1';
	}
	$cache_key = 'centinela_kmcat_v4_' . md5( $categoria_id . '|' . $ordenar . $guf );
	$cached   = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$list = centinela_tienda_fetch_kenwood_comercial_category_merged( $categoria_id, $ordenar, $cat_path );
	$ttl  = (int) apply_filters( 'centinela_tienda_kenwood_merged_list_ttl', 3 * MINUTE_IN_SECONDS, $categoria_id, $ordenar );
	$ttl  = max( 60, min( HOUR_IN_SECONDS, $ttl ) );
	set_transient( $cache_key, $list, $ttl );
	return $list;
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
	$marca             = trim( (string) $marca );
	$tienda_marcas_prefill = null;

	// Cache de resultados de productos para reducir llamadas a la API Syscom y mejorar TTFB.
	$cache_key = 'centinela_tienda_data_v9_' . md5( wp_json_encode( array(
		'cat'   => (string) $categoria_id,
		'page'  => (int) $pagina,
		'order' => (string) $ordenar,
		'marca' => (string) $marca,
		'min'   => (string) $min_price,
		'max'   => (string) $max_price,
		// Bump cuando cambie la lógica de fusión KENWOOD (radios-comerciales-kenwood).
		'kbr'   => ( $marca === '' && $categoria_id !== '' && centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path ) ) ? 'km4' : '',
	) ) );

	// Por marca: TTL alto (el merge Syscom es costoso). Sin marca: listado general, un poco más vivo.
	$cache_ttl = ( trim( (string) $marca ) !== '' ) ? 30 * MINUTE_IN_SECONDS : 3 * MINUTE_IN_SECONDS;
	$cache_ttl = (int) apply_filters( 'centinela_tienda_productos_cache_ttl', $cache_ttl, $marca, $categoria_id, $pagina, $ordenar, $min_price, $max_price );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) && isset( $cached['productos'], $cached['paginas'], $cached['marcas'] ) ) {
		$cp = (int) $cached['paginas'];
		// No reutilizar transients de listados “fallidos” (vacío + 0 páginas) que bloqueaban la tienda horas.
		if ( ! empty( $cached['productos'] ) || $cp > 0 ) {
			return $cached;
		}
	}

	if ( class_exists( 'Centinela_Syscom_API' ) ) {
		$args = array(
			'pagina' => max( 1, (int) $pagina ),
			'orden'  => sanitize_text_field( $ordenar ),
			'cop'    => true,
		);
		if ( $categoria_id !== '' ) {
			$args['categoria'] = $categoria_id;
		}

		$is_ref_marca = $marca !== ''
			&& function_exists( 'centinela_search_query_looks_like_product_reference' )
			&& centinela_search_query_looks_like_product_reference( $marca );

		if ( $is_ref_marca && function_exists( 'centinela_search_productos_syscom' ) ) {
			$per_page  = max( 1, min( 48, (int) apply_filters( 'centinela_tienda_per_page', 12 ) ) );
			$max_fetch = max( $per_page, (int) apply_filters( 'centinela_tienda_search_reference_max', 180 ) );
			$all_rows  = centinela_search_productos_syscom( $marca, $max_fetch, false );
			$mapped_all = array();
			foreach ( $all_rows as $row ) {
				if ( is_array( $row ) ) {
					$mapped = centinela_tienda_map_search_row_to_listado_prod( $row );
					if ( ( isset( $mapped['producto_id'] ) && $mapped['producto_id'] !== '' ) || ( isset( $mapped['titulo'] ) && trim( (string) $mapped['titulo'] ) !== '' ) ) {
						$mapped_all[] = $mapped;
					}
				}
			}
			$mapped_for_marcas = $mapped_all;
			if ( function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
				$mapped_for_marcas = centinela_tienda_filter_productos_por_precio( $mapped_all, $min_price, $max_price );
			}
			$tienda_marcas_prefill = function_exists( 'centinela_tienda_extract_marcas' ) ? centinela_tienda_extract_marcas( $mapped_for_marcas ) : array();
			$total                 = count( $mapped_for_marcas );
			$productos_paginas     = $total > 0 ? max( 1, (int) ceil( $total / $per_page ) ) : 0;
			$offset                = ( max( 1, (int) $pagina ) - 1 ) * $per_page;
			$productos_api         = $total > 0 ? array_slice( $mapped_for_marcas, $offset, $per_page ) : array();
			if ( $total === 0 ) {
				$productos_paginas = 0;
			}
		} elseif ( $marca !== '' && function_exists( 'centinela_tienda_get_productos_by_brand' ) ) {
			$brand_data        = centinela_tienda_get_productos_by_brand( (string) $marca, (string) $categoria_id, (int) $pagina, 'relevancia' );
			$productos_api     = isset( $brand_data['productos'] ) && is_array( $brand_data['productos'] ) ? $brand_data['productos'] : array();
			$productos_paginas = isset( $brand_data['paginas'] ) ? (int) $brand_data['paginas'] : 0;
		} else {
			$use_kenwood_merge =
				$categoria_id !== ''
				&& $marca === ''
				&& centinela_tienda_cat_path_is_kenwood_commercial_branch( $cat_path )
				&& (bool) apply_filters( 'centinela_tienda_kenwood_category_search_merge', true, $categoria_id, $cat_path );

			if ( $use_kenwood_merge ) {
				$per_page          = max( 1, min( 48, (int) apply_filters( 'centinela_tienda_per_page', 12 ) ) );
				$full_list         = centinela_tienda_get_kenwood_merged_category_list_cached( (string) $categoria_id, (string) $ordenar, (string) $cat_path );
				$total             = count( $full_list );
				$productos_paginas = $total > 0 ? max( 1, (int) ceil( $total / $per_page ) ) : 0;
				$offset            = ( max( 1, (int) $pagina ) - 1 ) * $per_page;
				$productos_api     = $total > 0 ? array_slice( $full_list, $offset, $per_page ) : array();
				if ( $total === 0 ) {
					$productos_paginas = 0;
				}
			} else {
				$resp = Centinela_Syscom_API::get_productos( $args );
				if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) ) {
					$productos_api     = $resp['productos'];
					$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
				}
			}
		}
		if ( $categoria_id === '' && empty( $productos_api ) && $marca === '' ) {
			$fb = centinela_tienda_fallback_productos_by_arbol_roots( $args );
			if ( is_array( $fb ) ) {
				$productos_api     = $fb['productos'];
				$productos_paginas = $fb['paginas'];
			}
		}
	}

	// Filtrar por precio en PHP (no en API) para tener siempre una página completa y poder extraer marcas del rango.
	$productos_tras_precio = $productos_api;
	if ( ! empty( $productos_api ) && function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
		$productos_tras_precio = centinela_tienda_filter_productos_por_precio( $productos_api, $min_price, $max_price );
	}

	// Marcas: en categoría sin filtro de marca, usar muestreo de la categoría (varias páginas), no solo la página actual.
	if ( $tienda_marcas_prefill !== null ) {
		$marcas = $tienda_marcas_prefill;
	} elseif ( $marca === '' && $categoria_id !== '' && function_exists( 'centinela_tienda_get_marcas_for_sidebar' ) ) {
		$marcas = centinela_tienda_get_marcas_for_sidebar( (string) $categoria_id, (string) $cat_path );
		if ( empty( $marcas ) ) {
			$marcas = function_exists( 'centinela_tienda_extract_marcas' ) ? centinela_tienda_extract_marcas( ! empty( $productos_tras_precio ) ? $productos_tras_precio : $productos_api ) : array();
		}
	} else {
		$marcas = function_exists( 'centinela_tienda_extract_marcas' ) ? centinela_tienda_extract_marcas( ! empty( $productos_tras_precio ) ? $productos_tras_precio : $productos_api ) : array();
	}

	// No volver a filtrar por marca aquí: con marca activa los productos ya vienen filtrados de centinela_tienda_get_productos_by_brand()
	// (incl. Kenwood con categorías de confianza). Un segundo filtro sin $trusted_category_ids vaciaba el listado.

	$productos_api = $productos_tras_precio;

	$result = array(
		'productos' => $productos_api,
		'paginas'   => $productos_paginas,
		'marcas'    => $marcas,
	);

	if ( ! empty( $result['productos'] ) || (int) $result['paginas'] > 0 ) {
		set_transient( $cache_key, $result, $cache_ttl );
	}

	return $result;
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
		$is_ref_marca_render = trim( (string) $marca ) !== ''
			&& function_exists( 'centinela_search_query_looks_like_product_reference' )
			&& centinela_search_query_looks_like_product_reference( $marca );

		if ( $is_ref_marca_render && function_exists( 'centinela_tienda_get_productos_data' ) ) {
			$data              = centinela_tienda_get_productos_data( $categoria_id, $pagina, $ordenar, $cat_path, $marca, $min_price, $max_price );
			$productos_api     = isset( $data['productos'] ) && is_array( $data['productos'] ) ? $data['productos'] : array();
			$productos_paginas = isset( $data['paginas'] ) ? (int) $data['paginas'] : 0;
		} elseif ( $marca !== '' && function_exists( 'centinela_tienda_get_productos_by_brand' ) ) {
			$brand_data        = centinela_tienda_get_productos_by_brand( (string) $marca, (string) $categoria_id, (int) $pagina, 'relevancia' );
			$productos_api     = isset( $brand_data['productos'] ) && is_array( $brand_data['productos'] ) ? $brand_data['productos'] : array();
			$productos_paginas = isset( $brand_data['paginas'] ) ? (int) $brand_data['paginas'] : 0;
		} else {
			$resp = Centinela_Syscom_API::get_productos( $args );
			if ( ! is_wp_error( $resp ) && isset( $resp['productos'] ) ) {
				$productos_api    = $resp['productos'];
				$productos_paginas = isset( $resp['paginas'] ) ? (int) $resp['paginas'] : 0;
			}
		}
		if ( $categoria_id === '' && empty( $productos_api ) && trim( (string) $marca ) === '' ) {
			$fb = centinela_tienda_fallback_productos_by_arbol_roots( $args );
			if ( is_array( $fb ) ) {
				$productos_api     = $fb['productos'];
				$productos_paginas = $fb['paginas'];
			}
		}
	}

	// Filtrar por precio en PHP (siempre; no se envía a la API para tener página completa y marcas del rango).
	if ( ! empty( $productos_api ) && function_exists( 'centinela_tienda_filter_productos_por_precio' ) ) {
		$productos_api = centinela_tienda_filter_productos_por_precio( $productos_api, $min_price, $max_price );
	}

	// Con marca activa el listado ya pasó por get_productos_by_brand (no duplicar filter_productos_por_marca).

	ob_start();

	if ( ! empty( $productos_api ) ) {
		?>
		<div class="centinela-tienda__grid">
			<?php
			$seen_pids = array();
			foreach ( $productos_api as $prod ) :
				$pid = isset( $prod['producto_id'] ) ? $prod['producto_id'] : ( isset( $prod['id'] ) ? $prod['id'] : ( isset( $prod['ID'] ) ? $prod['ID'] : ( isset( $prod['product_id'] ) ? $prod['product_id'] : '' ) ) );
				$pid = trim( (string) $pid );
				if ( preg_replace( '/[^0-9]/', '', $pid ) !== '' ) {
					$pid = preg_replace( '/[^0-9]/', '', $pid );
				}
				if ( $pid === '' ) {
					continue;
				}
				if ( isset( $seen_pids[ $pid ] ) ) {
					continue;
				}
				$seen_pids[ $pid ] = true;
				$titulo    = isset( $prod['titulo'] ) ? $prod['titulo'] : '';
				$img       = isset( $prod['img_portada'] ) ? trim( (string) $prod['img_portada'] ) : '';
				if ( $img === '' && function_exists( 'centinela_syscom_imagen_no_disponible_url' ) ) {
					$img = centinela_syscom_imagen_no_disponible_url();
				}
				$modelo    = isset( $prod['modelo'] ) ? trim( (string) $prod['modelo'] ) : '';
				$prod_marca = function_exists( 'centinela_tienda_producto_marca' ) ? centinela_tienda_producto_marca( $prod ) : ( isset( $prod['marca'] ) ? trim( (string) $prod['marca'] ) : '' );
				$precio_data = function_exists( 'centinela_tienda_precio_para_listado' ) ? centinela_tienda_precio_para_listado( $prod, $pid ) : array( 'precio' => '', 'precio_especial' => '', 'tiene_precio_especial' => false );
				$precio = isset( $precio_data['precio'] ) ? $precio_data['precio'] : '';
				$precio_especial = isset( $precio_data['precio_especial'] ) ? $precio_data['precio_especial'] : '';
				$tiene_precio_especial = ! empty( $precio_data['tiene_precio_especial'] );
				// URL legacy /tienda/producto/ID-slug/ para que Ver producto y enlaces lleven al detalle sin recargar tienda.
				$url    = function_exists( 'centinela_get_producto_url' ) ? centinela_get_producto_url( $pid, $titulo, '' ) : home_url( '/tienda/producto/' . $pid . '/' );
				// URL para filtrar por marca: /tienda/?marca=X o /tienda/cat-path/?marca=X
				$marca_base = home_url( '/tienda/' . ( $cat_path !== '' ? trim( $cat_path ) . '/' : '' ) );
				$marca_url  = $prod_marca !== '' ? add_query_arg( 'marca', rawurlencode( $prod_marca ), $marca_base ) : $marca_base;
				?>
				<article class="centinela-tienda__card" data-product-id="<?php echo esc_attr( $pid ); ?>">
					<div class="centinela-tienda__card-image-wrap">
						<a href="<?php echo esc_url( $url ); ?>" class="centinela-tienda__card-link centinela-tienda__card-image" aria-label="<?php echo esc_attr( $titulo ); ?>">
							<?php if ( $img !== '' ) : ?>
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
		if ( function_exists( 'centinela_tienda_print_empty_grid_message' ) ) {
			centinela_tienda_print_empty_grid_message( $marca, $cat_path, $min_price, $max_price );
		} else {
			?>
			<p class="centinela-tienda__empty centinela-tienda__empty--main"><?php esc_html_e( 'No hay productos disponibles en esta categoría.', 'centinela-group-theme' ); ?></p>
			<?php
		}
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
			'quick' => array(
				'type'    => 'boolean',
				'default' => false,
			),
		),
		'callback' => function ( $request ) {
			$categoria  = $request->get_param( 'categoria' );
			$cat_path   = $request->get_param( 'cat_path' );
			$pagina     = $request->get_param( 'pagina' );
			$ordenar    = $request->get_param( 'ordenar' );
			$marca      = trim( (string) $request->get_param( 'marca' ) );
			$min_price  = $request->get_param( 'min_price' );
			$max_price  = $request->get_param( 'max_price' );
			$quick      = (bool) $request->get_param( 'quick' );
			if ( $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
				$resolved = centinela_resolve_cat_path_to_syscom_id( trim( $cat_path ) );
				if ( $resolved !== null ) {
					$categoria = $resolved;
				}
			}
			$no_precio = trim( (string) $min_price ) === '' && trim( (string) $max_price ) === '';

			// Vista previa rápida (solo marca, página 1, sin filtro de precio): pocas llamadas Syscom.
			if ( $quick && $marca !== '' && (int) $pagina === 1 && $no_precio && function_exists( 'centinela_tienda_get_productos_by_brand_quick' ) ) {
				$quick_data = centinela_tienda_get_productos_by_brand_quick( $marca, (string) $categoria, (string) $ordenar, 12 );
				$html       = centinela_tienda_render_productos_html( $categoria, 1, $ordenar, $cat_path, $marca, $min_price, $max_price, $quick_data );
				return new WP_REST_Response(
					array(
						'html'       => $html,
						'pagina'     => 1,
						'paginas'    => 0,
						'partial'    => true,
						'categoria'  => $categoria,
						'cat_path'   => $cat_path,
						'marca'      => $marca,
						'min_price'  => $min_price,
						'max_price'  => $max_price,
						'marcas'     => array(),
					),
					200
				);
			}

			// Misma lógica y caché transitoria que la carga inicial (get_productos_data): evita doble llamada a Syscom en cada petición AJAX.
			$productos_data = null;
			$marcas           = array();
			if ( function_exists( 'centinela_tienda_get_productos_data' ) ) {
				$full = centinela_tienda_get_productos_data(
					(string) $categoria,
					(int) $pagina,
					(string) $ordenar,
					(string) $cat_path,
					(string) $marca,
					(string) $min_price,
					(string) $max_price
				);
				$productos_data = array(
					'productos' => isset( $full['productos'] ) ? $full['productos'] : array(),
					'paginas'   => isset( $full['paginas'] ) ? (int) $full['paginas'] : 0,
				);
				$marcas = ( isset( $full['marcas'] ) && is_array( $full['marcas'] ) ) ? $full['marcas'] : array();
			}
			$html = centinela_tienda_render_productos_html( $categoria, $pagina, $ordenar, $cat_path, $marca, $min_price, $max_price, $productos_data );
			return new WP_REST_Response(
				array(
					'html'       => $html,
					'pagina'     => (int) $pagina,
					'paginas'    => isset( $productos_data['paginas'] ) ? (int) $productos_data['paginas'] : 0,
					'partial'    => false,
					'categoria'  => $categoria,
					'cat_path'   => $cat_path,
					'marca'      => $marca,
					'min_price'  => $min_price,
					'max_price'  => $max_price,
					'marcas'     => $marcas,
				),
				200
			);
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
			if ( ! $request instanceof WP_REST_Request ) {
				return new WP_REST_Response( array( 'marcas' => array(), 'total' => 0, 'source' => 'invalid' ), 500 );
			}
			$categoria = $request->get_param( 'categoria' );
			$cat_path  = $request->get_param( 'cat_path' );
			if ( $cat_path !== '' && function_exists( 'centinela_resolve_cat_path_to_syscom_id' ) ) {
				$resolved = centinela_resolve_cat_path_to_syscom_id( trim( (string) $cat_path ) );
				if ( $resolved !== null && (string) $resolved !== '' ) {
					$categoria = (string) $resolved;
				}
			}
			if ( function_exists( 'centinela_tienda_get_marcas_bundle' ) ) {
				$bundle = centinela_tienda_get_marcas_bundle( (string) $categoria, (string) $cat_path );
				$marcas = isset( $bundle['marcas'] ) ? $bundle['marcas'] : array();
				// Fallback defensivo: si la fuente global/categoría vino vacía por caché o respuesta parcial,
				// reconstruir marcas desde el primer bloque de productos para no dejar el select vacío.
				if ( empty( $marcas ) && function_exists( 'centinela_tienda_get_productos_data' ) ) {
					$fallback_data = centinela_tienda_get_productos_data(
						(string) $categoria,
						1,
						'relevancia',
						(string) $cat_path,
						'',
						'',
						''
					);
					if ( isset( $fallback_data['marcas'] ) && is_array( $fallback_data['marcas'] ) && ! empty( $fallback_data['marcas'] ) ) {
						$marcas = $fallback_data['marcas'];
					}
				}
				return new WP_REST_Response(
					array(
						'marcas' => $marcas,
						'total'  => isset( $bundle['total'] ) ? (int) $bundle['total'] : 0,
						'source' => isset( $bundle['source'] ) ? $bundle['source'] : 'unknown',
					),
					200
				);
			}
			return new WP_REST_Response( array( 'marcas' => array(), 'total' => 0, 'source' => 'none' ), 200 );
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
