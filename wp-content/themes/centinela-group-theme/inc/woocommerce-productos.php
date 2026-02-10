<?php
/**
 * URLs amigables para Productos (estilo Syscom) e integración con WooCommerce.
 * Rutas: /productos/, /productos/videovigilancia/, /productos/videovigilancia/proteccion-contra-descargas/redes/
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtener la URL amigable de productos para un nodo del árbol (categoría Syscom).
 *
 * @param array  $nodo        Nodo con 'nombre' (y opcionalmente 'id').
 * @param string $parent_path Ruta de slugs del padre (ej. 'videovigilancia' o 'videovigilancia/proteccion-contra-descargas').
 * @return string URL completa.
 */
function centinela_get_productos_url( $nodo, $parent_path = '' ) {
	$slug = sanitize_title( $nodo['nombre'] );
	$path = $parent_path ? $parent_path . '/' . $slug : $slug;
	return home_url( '/productos/' . $path . '/' );
}

/**
 * URL amigable de la tienda para una categoría (SEO: /tienda/videovigilancia/proteccion-contra-descargas/redes/).
 *
 * @param array  $nodo        Nodo con 'nombre' (y opcionalmente 'id').
 * @param string $parent_path Ruta de slugs del padre.
 * @return string URL completa.
 */
function centinela_get_tienda_cat_url( $nodo, $parent_path = '' ) {
	$slug = sanitize_title( $nodo['nombre'] );
	$path = $parent_path ? $parent_path . '/' . $slug : $slug;
	return home_url( '/tienda/' . $path . '/' );
}

/**
 * Registrar query vars para rutas de categoría y producto único.
 */
function centinela_productos_query_vars( $vars ) {
	$vars[] = 'centinela_cat_path';
	$vars[] = 'centinela_producto_id';
	$vars[] = 'centinela_tienda_cat_path';
	return $vars;
}
add_filter( 'query_vars', 'centinela_productos_query_vars' );

/**
 * Reglas de reescritura: /productos/, /productos/slug.../, /producto/123/, /tienda/producto/123-slug/, /tienda/, /tienda/slug.../
 */
function centinela_productos_rewrite_rules() {
	// Checkout: /finalizar-compra/ y /checkout/ (prioridad para que resuelvan como página)
	add_rewrite_rule( 'finalizar-compra/?$', 'index.php?pagename=finalizar-compra', 'top' );
	add_rewrite_rule( 'checkout/?$', 'index.php?pagename=checkout', 'top' );
	// Producto por ID solo (legacy): /producto/123/
	add_rewrite_rule(
		'producto/([0-9]+)/?$',
		'index.php?centinela_producto_id=$matches[1]',
		'top'
	);
	// URLs amigables producto: /tienda/producto/123-slug-del-producto/ (el ID es el primer segmento numérico)
	add_rewrite_rule(
		'tienda/producto/([0-9]+)(-[^/]*)?/?$',
		'index.php?centinela_producto_id=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'productos/(.+?)/?$',
		'index.php?pagename=productos&centinela_cat_path=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'productos/?$',
		'index.php?pagename=productos',
		'top'
	);
	// Tienda con URLs amigables: /tienda/videovigilancia/proteccion-contra-descargas/redes/
	add_rewrite_rule(
		'tienda/(.+?)/?$',
		'index.php?pagename=tienda&centinela_tienda_cat_path=$matches[1]',
		'top'
	);
	add_rewrite_rule(
		'tienda/?$',
		'index.php?pagename=tienda',
		'top'
	);
}
add_action( 'init', 'centinela_productos_rewrite_rules' );

/**
 * Asegurar que /tienda/categoria/sub/ llegue con pagename=tienda y centinela_tienda_cat_path (p. ej. desde menú secundario).
 * Si la URL es /tienda/producto/123-slug/, detectarla y fijar centinela_producto_id (funciona aunque las reglas no se hayan vaciado).
 */
function centinela_tienda_parse_request( $wp ) {
	// Si ya viene el ID del producto por rewrite, no tocar.
	if ( ! empty( $wp->query_vars['centinela_producto_id'] ) ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( ! $req_path || ! preg_match( '#/tienda/(.+)$#', $req_path, $m ) ) {
		return;
	}
	$path_after = trim( $m[1], '/' );
	if ( $path_after === '' ) {
		return;
	}
	// Detectar /tienda/producto/123 o /tienda/producto/123-slug (página de detalle del producto).
	// Si la regla genérica tienda/(.+?) hizo match, centinela_tienda_cat_path será "producto/123-slug"; lo tratamos como producto.
	if ( preg_match( '#^producto/([0-9]+)(?:-[^/]*)?$#', $path_after, $prod ) ) {
		$wp->query_vars['centinela_producto_id'] = $prod[1];
		unset( $wp->query_vars['pagename'] );
		unset( $wp->query_vars['centinela_tienda_cat_path'] );
		return;
	}
	// Intentar como categoría primero: /tienda/videovigilancia/proteccion-contra-descargas/redes/
	if ( centinela_resolve_cat_path_to_syscom_id( $path_after ) !== null ) {
		$wp->query_vars['pagename']                  = 'tienda';
		$wp->query_vars['centinela_tienda_cat_path'] = $path_after;
		return;
	}
	// Intentar como producto: /tienda/cat/subcat/product-slug/ (último segmento = slug del producto)
	$segments = array_filter( array_map( 'trim', explode( '/', $path_after ) ) );
	if ( count( $segments ) >= 2 && function_exists( 'centinela_resolve_product_by_cat_path_and_slug' ) ) {
		$product_slug = (string) array_pop( $segments );
		$cat_path_str = implode( '/', $segments );
		$resolved_id  = centinela_resolve_product_by_cat_path_and_slug( $cat_path_str, $product_slug );
		if ( $resolved_id !== null ) {
			$wp->query_vars['centinela_producto_id'] = (string) $resolved_id;
			unset( $wp->query_vars['pagename'] );
			unset( $wp->query_vars['centinela_tienda_cat_path'] );
			return;
		}
	}
	$wp->query_vars['pagename']                  = 'tienda';
	$wp->query_vars['centinela_tienda_cat_path'] = $path_after;
}
add_action( 'parse_request', 'centinela_tienda_parse_request', 5 );

/**
 * Evitar 404 cuando la petición es para un producto único (centinela_producto_id) o tienda con ruta (centinela_tienda_cat_path).
 */
function centinela_single_producto_pre_404( $pre, $wp_query ) {
	if ( $wp_query->get( 'centinela_producto_id' ) ) {
		return true;
	}
	if ( $wp_query->get( 'centinela_tienda_cat_path' ) !== '' && $wp_query->get( 'centinela_tienda_cat_path' ) !== false ) {
		return true;
	}
	return $pre;
}
add_filter( 'pre_handle_404', 'centinela_single_producto_pre_404', 10, 2 );

/**
 * Si la URL es /tienda/{path}/ y la consulta principal no tiene posts (p. ej. no existe página "tienda"),
 * inyectar la página con slug "tienda" para que el template tenga have_posts() y the_content().
 */
function centinela_tienda_ensure_queried_object() {
	$cat_path = get_query_var( 'centinela_tienda_cat_path' );
	if ( $cat_path === '' || $cat_path === false ) {
		return;
	}
	global $wp_query;
	if ( $wp_query->get_queried_object() ) {
		return;
	}
	$tienda_page = get_page_by_path( 'tienda', OBJECT, 'page' );
	if ( ! $tienda_page ) {
		return;
	}
	$wp_query->posts          = array( $tienda_page );
	$wp_query->post_count     = 1;
	$wp_query->queried_object = $tienda_page;
	$wp_query->queried_object_id = (int) $tienda_page->ID;
	$wp_query->is_singular    = true;
	$wp_query->is_single      = false;
	$wp_query->is_page        = true;
	$wp_query->is_404         = false;
}
add_action( 'template_redirect', 'centinela_tienda_ensure_queried_object', 2 );

/**
 * Si la petición es /tienda/categoria/... y WordPress devolvió 404 (p. ej. menú secundario, reglas no coincidieron),
 * forzar consulta de la página tienda y cat_path para que cargue la plantilla correcta.
 */
function centinela_tienda_fix_404_on_cat_url() {
	if ( ! is_404() ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( ! $req_path || ! preg_match( '#/tienda/(.+)$#', $req_path, $m ) ) {
		return;
	}
	$path_after = trim( trim( $m[1], '/' ) );
	if ( $path_after === '' ) {
		return;
	}
	$tienda_page = get_page_by_path( 'tienda', OBJECT, 'page' );
	if ( ! $tienda_page ) {
		return;
	}
	global $wp_query;
	$wp_query->set( 'centinela_tienda_cat_path', $path_after );
	$wp_query->set( 'pagename', '' );
	$wp_query->posts          = array( $tienda_page );
	$wp_query->post_count     = 1;
	$wp_query->queried_object = $tienda_page;
	$wp_query->queried_object_id = (int) $tienda_page->ID;
	$wp_query->is_404         = false;
	$wp_query->is_singular    = true;
	$wp_query->is_single      = false;
	$wp_query->is_page        = true;
}
add_action( 'template_redirect', 'centinela_tienda_fix_404_on_cat_url', 1 );

/**
 * Evitar que redirect_canonical envíe /tienda/categoria/sub/ a /tienda/ (p. ej. desde menú secundario).
 */
function centinela_tienda_redirect_canonical( $redirect_url, $requested_url ) {
	if ( $redirect_url === $requested_url ) {
		return $redirect_url;
	}
	$path = parse_url( $requested_url, PHP_URL_PATH );
	if ( $path && preg_match( '#/tienda/.+#', $path ) ) {
		return false;
	}
	return $redirect_url;
}
add_filter( 'redirect_canonical', 'centinela_tienda_redirect_canonical', 10, 2 );

/**
 * Usar plantilla single producto cuando la URL es /producto/{id}/.
 */
function centinela_single_producto_template( $template ) {
	$producto_id = get_query_var( 'centinela_producto_id' );
	if ( $producto_id === '' || $producto_id === false ) {
		return $template;
	}
	$single = get_stylesheet_directory() . '/single-producto.php';
	if ( file_exists( $single ) ) {
		return $single;
	}
	$single_theme = get_template_directory() . '/single-producto.php';
	if ( file_exists( $single_theme ) ) {
		return $single_theme;
	}
	return $template;
}
add_filter( 'template_include', 'centinela_single_producto_template', 5 );

/**
 * Redirigir URLs legacy de producto (/tienda/producto/123-slug/) a la URL canónica con ruta de categoría.
 * Se ejecuta en template_redirect cuando ya tenemos el producto cargado (en single-producto).
 */
function centinela_producto_canonical_redirect() {
	$producto_id = get_query_var( 'centinela_producto_id' );
	if ( $producto_id === '' || $producto_id === false || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	if ( ! $req_path || ! preg_match( '#/tienda/producto/([0-9]+)#', $req_path ) ) {
		return;
	}
	$producto = Centinela_Syscom_API::get_producto( (int) $producto_id, true );
	if ( is_wp_error( $producto ) || empty( $producto['titulo'] ) || ! function_exists( 'centinela_get_product_cat_path' ) || ! function_exists( 'centinela_get_producto_url' ) ) {
		return;
	}
	$cat_path = centinela_get_product_cat_path( $producto );
	$canonical = centinela_get_producto_url( (int) $producto_id, $producto['titulo'], $cat_path );
	$current   = home_url( $req_path );
	if ( rtrim( $canonical, '/' ) !== rtrim( $current, '/' ) ) {
		wp_safe_redirect( $canonical, 301 );
		exit;
	}
}
add_action( 'template_redirect', 'centinela_producto_canonical_redirect', 2 );

/**
 * URL amigable para un producto (API Syscom).
 * Si se pasa $cat_path genera /tienda/{cat_path}/{slug}/ (ej. /tienda/videovigilancia/proteccion-contra-descargas/redes/producto-slug/).
 * Si no, genera /tienda/producto/123-slug/ (legacy).
 *
 * @param int|string $producto_id ID del producto.
 * @param string     $titulo      Opcional. Título del producto para el slug.
 * @param string     $cat_path    Opcional. Ruta de categoría (slugs con /). Si está vacía se usa URL legacy.
 * @return string
 */
function centinela_get_producto_url( $producto_id, $titulo = '', $cat_path = '' ) {
	$id   = (int) $producto_id;
	$slug = ( $titulo !== '' ) ? sanitize_title( $titulo ) : 'producto-' . $id;
	$cat_path = is_string( $cat_path ) ? trim( $cat_path, '/' ) : '';
	if ( $cat_path !== '' ) {
		return home_url( '/tienda/' . $cat_path . '/' . $slug . '/' );
	}
	return home_url( '/tienda/producto/' . $id . ( $slug !== 'producto-' . $id ? '-' . $slug : '' ) . '/' );
}

/**
 * Obtener la ruta de slugs de categoría (ej. videovigilancia/proteccion-contra-descargas/redes) desde el árbol,
 * dado el ID de una categoría. Usa el árbol de categorías de la API.
 *
 * @param string     $cat_id ID de la categoría Syscom.
 * @param array|null $arbol  Árbol de categorías (opcional; si no se pasa se obtiene de la API).
 * @return string|null Ruta con slugs separados por / o null si no se encuentra.
 */
function centinela_get_cat_path_from_id( $cat_id, $arbol = null ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return null;
	}
	if ( $arbol === null ) {
		$arbol = Centinela_Syscom_API::get_categorias_arbol();
	}
	if ( is_wp_error( $arbol ) || ! is_array( $arbol ) ) {
		return null;
	}
	$cat_id = (string) $cat_id;
	$path   = array();
	$found  = false;
	$search = function ( $nodes, $prefix ) use ( $cat_id, &$path, &$found, &$search ) {
		foreach ( $nodes as $nodo ) {
			$slug = sanitize_title( isset( $nodo['nombre'] ) ? $nodo['nombre'] : '' );
			$cur  = $prefix ? $prefix . '/' . $slug : $slug;
			if ( isset( $nodo['id'] ) && (string) $nodo['id'] === $cat_id ) {
				$path  = $cur;
				$found = true;
				return;
			}
			$hijos = isset( $nodo['hijos'] ) ? $nodo['hijos'] : array();
			if ( ! empty( $hijos ) ) {
				$search( $hijos, $cur );
				if ( $found ) {
					return;
				}
			}
		}
	};
	$search( $arbol, '' );
	return $found ? $path : null;
}

/**
 * Resolver ruta de categoría (ej. videovigilancia/proteccion-contra-descargas) al ID de categoría Syscom.
 * Usa el árbol de categorías de la API.
 *
 * @param string $path Ruta con slugs separados por /.
 * @return string|null ID de categoría o null.
 */
function centinela_resolve_cat_path_to_syscom_id( $path ) {
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return null;
	}
	$arbol = Centinela_Syscom_API::get_categorias_arbol();
	if ( is_wp_error( $arbol ) || empty( $arbol ) ) {
		return null;
	}
	$segments = array_filter( array_map( 'trim', explode( '/', $path ) ) );
	if ( empty( $segments ) ) {
		return null;
	}
	$nodo = null;
	$padre = $arbol;
	foreach ( $segments as $slug ) {
		$found = null;
		foreach ( $padre as $item ) {
			if ( sanitize_title( $item['nombre'] ) === $slug ) {
				$found = $item;
				break;
			}
		}
		if ( ! $found ) {
			return null;
		}
		$nodo  = $found;
		$padre = isset( $found['hijos'] ) ? $found['hijos'] : array();
	}
	return $nodo && isset( $nodo['id'] ) ? $nodo['id'] : null;
}

/**
 * Obtener la ruta de categoría (slugs) para usar en la URL de un producto.
 * Usa la primera categoría del producto y el árbol de categorías para construir la ruta completa.
 *
 * @param array $producto Producto con clave 'categorías' o 'categorias' (array de { id, nombre }).
 * @return string Ruta con slugs (ej. videovigilancia/proteccion-contra-descargas/redes) o ''.
 */
function centinela_get_product_cat_path( $producto ) {
	$cats = isset( $producto['categorías'] ) ? $producto['categorías'] : ( isset( $producto['categorias'] ) ? $producto['categorias'] : array() );
	if ( ! is_array( $cats ) || empty( $cats ) ) {
		return '';
	}
	$primera = $cats[0];
	$cat_id  = isset( $primera['id'] ) ? (string) $primera['id'] : ( is_object( $primera ) && isset( $primera->id ) ? (string) $primera->id : '' );
	if ( $cat_id === '' || ! function_exists( 'centinela_get_cat_path_from_id' ) ) {
		return '';
	}
	$path = centinela_get_cat_path_from_id( $cat_id );
	return is_string( $path ) ? $path : '';
}

/**
 * Resolver producto por ruta de categoría + slug (para URLs /tienda/cat/subcat/product-slug/).
 * Busca en la categoría los productos y devuelve el ID del que coincida el slug del título.
 *
 * @param string $cat_path   Ruta de categoría (slugs con /).
 * @param string $product_slug Slug del producto (sanitize_title del título).
 * @return int|null ID del producto o null.
 */
function centinela_resolve_product_by_cat_path_and_slug( $cat_path, $product_slug ) {
	$cache_key = 'centinela_ps_' . md5( $cat_path . '|' . $product_slug );
	$cached    = get_transient( $cache_key );
	if ( $cached !== false && is_numeric( $cached ) ) {
		return (int) $cached;
	}
	$cat_id = centinela_resolve_cat_path_to_syscom_id( $cat_path );
	if ( $cat_id === null || ! class_exists( 'Centinela_Syscom_API' ) ) {
		return null;
	}
	$pagina   = 1;
	$max_pag  = 10;
	$producto_id = null;
	while ( $pagina <= $max_pag ) {
		$res = Centinela_Syscom_API::get_productos( array( 'categoria' => $cat_id, 'pagina' => $pagina ) );
		if ( is_wp_error( $res ) ) {
			break;
		}
		$productos = isset( $res['productos'] ) ? $res['productos'] : array();
		if ( empty( $productos ) ) {
			break;
		}
		foreach ( $productos as $p ) {
			$titulo = isset( $p['titulo'] ) ? $p['titulo'] : ( isset( $p['nombre'] ) ? $p['nombre'] : '' );
			if ( $titulo !== '' && sanitize_title( $titulo ) === $product_slug ) {
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : null );
				if ( $pid !== null ) {
					$producto_id = (int) $pid;
					break 2;
				}
			}
		}
		$paginas = isset( $res['paginas'] ) ? (int) $res['paginas'] : 1;
		if ( $pagina >= $paginas ) {
			break;
		}
		$pagina++;
	}
	if ( $producto_id !== null ) {
		set_transient( $cache_key, $producto_id, 12 * HOUR_IN_SECONDS );
	}
	return $producto_id;
}

/**
 * Redirigir /productos/categoria/ a /tienda/categoria/ para unificar en la tienda (evitar 404 y misma experiencia).
 */
function centinela_redirect_productos_to_tienda() {
	$path = get_query_var( 'centinela_cat_path' );
	if ( $path === '' || $path === false ) {
		return;
	}
	$tienda_url = home_url( '/tienda/' . trim( $path ) . '/' );
	wp_safe_redirect( $tienda_url, 301 );
	exit;
}
add_action( 'template_redirect', 'centinela_redirect_productos_to_tienda', 1 );

/**
 * Flush rewrite rules al activar el tema.
 */
function centinela_productos_flush_rewrites() {
	centinela_productos_rewrite_rules();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'centinela_productos_flush_rewrites' );

/**
 * Resolver ruta de categoría (ej. videovigilancia/proteccion-contra-descargas/redes) a término de product_cat.
 * Requiere WooCommerce. Crea o usa categorías con el mismo slug que el menú.
 *
 * @param string $path Ruta con slugs separados por /.
 * @return WP_Term|null Término de product_cat o null.
 */
function centinela_resolve_cat_path_to_wc_term( $path ) {
	if ( ! taxonomy_exists( 'product_cat' ) || empty( trim( $path ) ) ) {
		return null;
	}
	$segments = array_filter( array_map( 'trim', explode( '/', $path ) ) );
	if ( empty( $segments ) ) {
		return null;
	}
	$parent_id = 0;
	$term      = null;
	foreach ( $segments as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}
		if ( (int) $term->parent !== $parent_id ) {
			return null;
		}
		$parent_id = (int) $term->term_id;
	}
	return $term;
}
