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
 * Reglas de reescritura: /productos/, /productos/slug.../, /producto/123/, /tienda/, /tienda/slug.../
 */
function centinela_productos_rewrite_rules() {
	add_rewrite_rule(
		'producto/([0-9]+)/?$',
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
 * Si la URL contiene /tienda/xxx y la variable no viene por rewrite, se rellena desde REQUEST_URI.
 */
function centinela_tienda_parse_request( $wp ) {
	$cat_path = isset( $wp->query_vars['centinela_tienda_cat_path'] ) ? $wp->query_vars['centinela_tienda_cat_path'] : '';
	if ( $cat_path !== '' && $cat_path !== false ) {
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
	$wp->query_vars['pagename']              = 'tienda';
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
 * URL amigable para un producto (API Syscom).
 *
 * @param int|string $producto_id ID del producto.
 * @return string
 */
function centinela_get_producto_url( $producto_id ) {
	return home_url( '/producto/' . (int) $producto_id . '/' );
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
