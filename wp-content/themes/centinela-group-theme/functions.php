<?php
/**
 * Centinela Group Theme - Functions and definitions
 *
 * @package Centinela_Group_Theme
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CENTINELA_THEME_VERSION', '1.0.0' );
define( 'CENTINELA_THEME_DIR', get_template_directory() );
define( 'CENTINELA_THEME_URI', get_template_directory_uri() );

/**
 * Forzar HTTP en localhost para evitar redirecciones a HTTPS (Docker/local sin SSL).
 * Si la URL contiene localhost o 127.0.0.1, devuelve la misma URL con esquema http.
 *
 * @param string $url URL completa.
 * @return string
 */
function centinela_force_http_on_localhost( $url ) {
	if ( ! is_string( $url ) || $url === '' ) {
		return $url;
	}
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( $host === 'localhost' || $host === '127.0.0.1' ) {
		return set_url_scheme( $url, 'http' );
	}
	return $url;
}

/**
 * Normaliza precio COP: si la API envía un valor con 2 decimales que en realidad son pesos enteros
 * (ej: 697,33 → 69.733 COP; 368194.46 → 36.819.446 COP), devuelve el entero en pesos.
 * Aplica a miles, cienmiles y millones: siempre que num * 100 sea entero, se interpreta como pesos.
 *
 * @param string|float|int $precio Precio tal como viene de la API.
 * @return int|float Valor numérico en pesos (entero cuando se normaliza, float cuando no).
 */
function centinela_normalizar_precio_cop( $precio ) {
	if ( $precio === '' || $precio === null ) {
		return 0;
	}
	if ( is_numeric( $precio ) ) {
		$num = (float) $precio;
	} else {
		$s = preg_replace( '/\s*COP\s*$/i', '', trim( (string) $precio ) );
		$s = preg_replace( '/[^\d.,\-]/', '', $s );
		if ( strpos( $s, ',' ) !== false ) {
			$s = str_replace( '.', '', $s );
			$s = str_replace( ',', '.', $s );
		}
		$num = (float) $s;
	}
	if ( is_nan( $num ) || $num !== $num || $num < 0 ) {
		return 0;
	}
	$entero = (int) floor( $num );
	$decimal = $num - $entero;
	// Si tiene 2 decimales (num * 100 es entero): interpretar como pesos enteros (miles, cienmiles, millones).
	if ( $decimal > 0 && abs( round( $num * 100 ) - $num * 100 ) < 0.001 ) {
		return (int) round( $num * 100 );
	}
	return $num >= 1 ? ( $decimal == 0 ? $entero : $num ) : $num;
}

/**
 * Formato de precio COP para Colombia: miles con punto, decimales con coma (ej: 36.819.446 COP).
 * Usa la normalización para que cienmiles, millones etc. se muestren con todas las cifras.
 *
 * @param string|float|int $precio Precio tal como viene de la API o número.
 * @return string Precio formateado con " COP" al final, o string vacío si no hay precio.
 */
function centinela_format_precio_cop( $precio ) {
	$num = centinela_normalizar_precio_cop( $precio );
	if ( $num === 0 && $precio !== 0 && $precio !== '0' ) {
		return '';
	}
	$formateado = number_format( $num, 2, ',', '.' );
	if ( substr( $formateado, -3 ) === ',00' ) {
		$formateado = substr( $formateado, 0, -3 );
	}
	return $formateado . ' COP';
}

/**
 * En localhost evitar que WooCommerce redirija checkout a HTTPS (no hay SSL en Docker/local).
 * WooCommerce usa la opción "Forzar SSL en el checkout" y hace template_redirect a https.
 */
function centinela_disable_wc_force_ssl_on_localhost() {
	$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( trim( $_SERVER['HTTP_HOST'] ) ) : '';
	if ( $host === 'localhost' || $host === 'localhost:8081' || strpos( $host, '127.0.0.1' ) === 0 ) {
		remove_action( 'template_redirect', array( 'WC_HTTPS', 'force_https_template_redirect' ) );
	}
}
add_action( 'template_redirect', 'centinela_disable_wc_force_ssl_on_localhost', 0 );

/**
 * No redirigir al carrito WC cuando estamos en "finalizar-compra".
 * El tema usa carrito en localStorage; WooCommerce redirige si WC()->cart está vacío.
 */
function centinela_allow_checkout_with_empty_wc_cart( $redirect ) {
	if ( is_page( 'finalizar-compra' ) ) {
		return false;
	}
	return $redirect;
}
add_filter( 'woocommerce_checkout_redirect_empty_cart', 'centinela_allow_checkout_with_empty_wc_cart' );

/**
 * Configuración del tema
 */
function centinela_theme_setup() {
	load_theme_textdomain( 'centinela-group-theme', CENTINELA_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array(
		'height'      => 80,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
		'header-text' => array( 'site-title', 'site-description' ),
	) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'automatic-feed-links' );

	register_nav_menus( array(
		'primary'   => __( 'Menú principal', 'centinela-group-theme' ),
		'footer'    => __( 'Menú pie de página', 'centinela-group-theme' ),
	) );
}
add_action( 'after_setup_theme', 'centinela_theme_setup' );

/**
 * Soporte para Elementor (compatible con versión gratuita)
 * Si en el futuro usas Elementor Pro, puedes activar header/footer desde el Theme Builder.
 */
function centinela_theme_elementor_support() {
	add_theme_support( 'elementor' );
	add_theme_support( 'elementor-header-footer' );
}
add_action( 'after_setup_theme', 'centinela_theme_elementor_support' );

/**
 * Asegurar que la plantilla Tienda aparezca en el desplegable de Páginas (editor clásico y bloques).
 */
function centinela_theme_page_templates( $templates, $theme = null, $post = null ) {
	$our = get_template_directory() . '/page-tienda.php';
	if ( file_exists( $our ) ) {
		$templates['page-tienda.php'] = __( 'Tienda (Centinela)', 'centinela-group-theme' );
	}
	$carrito = get_template_directory() . '/page-carrito.php';
	if ( file_exists( $carrito ) ) {
		$templates['page-carrito.php'] = __( 'Carrito (Centinela)', 'centinela-group-theme' );
	}
	$checkout = get_template_directory() . '/page-finalizar-compra.php';
	if ( file_exists( $checkout ) ) {
		$templates['page-finalizar-compra.php'] = __( 'Finalizar compra (Checkout)', 'centinela-group-theme' );
	}
	return $templates;
}
add_filter( 'theme_page_templates', 'centinela_theme_page_templates', 10, 3 );

/**
 * Si la página tiene slug "carrito", usar siempre la plantilla page-carrito.php.
 */
function centinela_force_carrito_template( $template ) {
	if ( ! is_singular( 'page' ) ) {
		return $template;
	}
	$page = get_queried_object();
	if ( ! $page || ! isset( $page->post_name ) || $page->post_name !== 'carrito' ) {
		return $template;
	}
	$carrito_file = get_template_directory() . '/page-carrito.php';
	if ( file_exists( $carrito_file ) ) {
		return $carrito_file;
	}
	return $template;
}
add_filter( 'template_include', 'centinela_force_carrito_template', 98 );

/**
 * Si la página tiene slug "finalizar-compra" o "checkout", usar la plantilla page-finalizar-compra.php.
 */
function centinela_force_checkout_template( $template ) {
	if ( ! is_singular( 'page' ) ) {
		return $template;
	}
	$page = get_queried_object();
	if ( ! $page || ! isset( $page->post_name ) ) {
		return $template;
	}
	$slug = $page->post_name;
	if ( $slug !== 'finalizar-compra' && $slug !== 'checkout' ) {
		return $template;
	}
	$checkout_file = get_template_directory() . '/page-finalizar-compra.php';
	if ( file_exists( $checkout_file ) ) {
		return $checkout_file;
	}
	return $template;
}
add_filter( 'template_include', 'centinela_force_checkout_template', 98 );

/**
 * Si la URL es /finalizar-compra/ o /checkout/ y WordPress devolvió 404 (p. ej. slug distinto o reglas no actualizadas),
 * forzar la carga de la página de checkout si existe (por slug o por plantilla).
 */
function centinela_fix_404_checkout_url() {
	if ( ! is_404() ) {
		return;
	}
	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}
	$req_path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	$req_path = $req_path ? trim( $req_path, '/' ) : '';
	if ( $req_path !== 'finalizar-compra' && $req_path !== 'checkout' ) {
		return;
	}
	$checkout_page = get_page_by_path( 'finalizar-compra', OBJECT, 'page' );
	if ( ! $checkout_page ) {
		$checkout_page = get_page_by_path( 'checkout', OBJECT, 'page' );
	}
	if ( ! $checkout_page || $checkout_page->post_status !== 'publish' ) {
		return;
	}
	global $wp_query;
	$wp_query->posts          = array( $checkout_page );
	$wp_query->post_count     = 1;
	$wp_query->queried_object = $checkout_page;
	$wp_query->queried_object_id = (int) $checkout_page->ID;
	$wp_query->is_404         = false;
	$wp_query->is_singular    = true;
	$wp_query->is_single      = false;
	$wp_query->is_page        = true;
	$wp_query->set( 'pagename', '' );
	$wp_query->set( 'page_id', $checkout_page->ID );
}
add_action( 'template_redirect', 'centinela_fix_404_checkout_url', 0 );

/**
 * Si la página tiene slug "tienda", usar siempre la plantilla page-tienda.php
 * (así la tienda funciona aunque en el editor no se guarde bien la plantilla).
 */
function centinela_force_tienda_template( $template ) {
	global $wp_query;
	if ( ! is_singular( 'page' ) ) {
		return $template;
	}
	$page = get_queried_object();
	if ( ! $page || ! isset( $page->post_name ) || $page->post_name !== 'tienda' ) {
		return $template;
	}
	$tienda_file = get_template_directory() . '/page-tienda.php';
	if ( file_exists( $tienda_file ) ) {
		return $tienda_file;
	}
	return $template;
}
add_filter( 'template_include', 'centinela_force_tienda_template', 99 );

/**
 * Incluir partes del tema (header/footer por defecto)
 */
require_once CENTINELA_THEME_DIR . '/inc/class-syscom-api.php';
require_once CENTINELA_THEME_DIR . '/inc/syscom-settings.php';
require_once CENTINELA_THEME_DIR . '/inc/hero-slider.php';
require_once CENTINELA_THEME_DIR . '/inc/template-header.php';
require_once CENTINELA_THEME_DIR . '/inc/template-footer.php';
require_once CENTINELA_THEME_DIR . '/inc/woocommerce-productos.php';
require_once CENTINELA_THEME_DIR . '/inc/tienda-ajax.php';

/**
 * Registrar Swiper y hero-slider para que Elementor los encole cuando use el widget Hero Slider
 */
function centinela_register_hero_slider_assets() {
	wp_register_style(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
		array(),
		'11'
	);
	wp_register_script(
		'swiper',
		'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
		array(),
		'11',
		true
	);
	wp_register_script(
		'centinela-hero-slider',
		CENTINELA_THEME_URI . '/assets/js/hero-slider.js',
		array( 'swiper' ),
		CENTINELA_THEME_VERSION,
		true
	);
	wp_register_script(
		'centinela-video-modal',
		CENTINELA_THEME_URI . '/assets/js/video-modal.js',
		array(),
		CENTINELA_THEME_VERSION,
		true
	);
	wp_register_script(
		'centinela-servicios-slider',
		CENTINELA_THEME_URI . '/assets/js/servicios-slider.js',
		array( 'swiper' ),
		CENTINELA_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'centinela_register_hero_slider_assets', 5 );

/**
 * Cargar widgets de Elementor (Hero Slider, etc.) cuando Elementor esté activo
 */
function centinela_load_elementor_widgets() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'elementor/loaded', 'centinela_load_elementor_widgets' );
		return;
	}
	require_once CENTINELA_THEME_DIR . '/inc/elementor/register-widgets.php';
}
add_action( 'after_setup_theme', 'centinela_load_elementor_widgets', 20 );

/**
 * Encolar estilos y scripts con foco en performance
 * Se cargan también en el preview de Elementor para que el editor muestre los mismos estilos que el frontend.
 */
function centinela_theme_scripts() {
	$style_deps = array();

	// Roboto: todos los pesos (100, 300, 400, 500, 700, 900)
	wp_enqueue_style(
		'centinela-roboto',
		'https://fonts.googleapis.com/css2?family=Roboto:wght@100;300;400;500;700;900&display=swap',
		array(),
		null
	);
	$style_deps[] = 'centinela-roboto';

	// Tailwind (siempre en front y en preview de Elementor)
	wp_enqueue_style(
		'centinela-tailwind',
		CENTINELA_THEME_URI . '/assets/css/tailwind.min.css',
		array(),
		CENTINELA_THEME_VERSION
	);
	$style_deps[] = 'centinela-tailwind';

	// SCSS compilado (BEM: header, CTA, menú, etc.) – siempre para que coincida con el frontend
	wp_enqueue_style(
		'centinela-theme-scss',
		CENTINELA_THEME_URI . '/assets/css/theme.min.css',
		array( 'centinela-roboto' ),
		CENTINELA_THEME_VERSION
	);
	$style_deps[] = 'centinela-theme-scss';

	wp_enqueue_style(
		'centinela-theme-style',
		get_stylesheet_uri(),
		$style_deps,
		CENTINELA_THEME_VERSION
	);

	// JS del tema (menú móvil, buscador) – siempre para que el header funcione igual en el editor
	wp_enqueue_script(
		'centinela-theme-script',
		CENTINELA_THEME_URI . '/assets/js/theme.js',
		array(),
		CENTINELA_THEME_VERSION,
		true
	);
	// Lightbox de imagen (quickview + detalle producto)
	wp_enqueue_script(
		'centinela-image-lightbox',
		CENTINELA_THEME_URI . '/assets/js/image-lightbox.js',
		array(),
		CENTINELA_THEME_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'centinela_theme_scripts', 10 );

/**
 * Performance: precargar fuentes críticas, defer/async
 */
function centinela_theme_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array(
			'href' => 'https://fonts.googleapis.com',
			'crossorigin' => '',
		);
	}
	return $urls;
}
add_filter( 'wp_resource_hints', 'centinela_theme_resource_hints', 10, 2 );

/**
 * Performance: atributo loading="lazy" en iframes del contenido
 */
function centinela_theme_lazy_iframes( $content ) {
	if ( ! is_singular() ) {
		return $content;
	}
	return preg_replace( '/<iframe /i', '<iframe loading="lazy" ', $content );
}
add_filter( 'the_content', 'centinela_theme_lazy_iframes', 99 );

/**
 * Ancho de contenido para alineación (compatible con Elementor)
 */
function centinela_theme_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'centinela_theme_content_width', 1200 );
}
add_action( 'after_setup_theme', 'centinela_theme_content_width', 0 );

/**
 * Registrar ubicaciones de Elementor (útil si usas Elementor Pro)
 */
function centinela_theme_register_elementor_locations( $elementor_theme_manager ) {
	$elementor_theme_manager->register_all_core_location();
}
add_action( 'elementor/theme/register_locations', 'centinela_theme_register_elementor_locations' );

/**
 * Clases CSS para menús (BEM: centinela-header)
 */
function centinela_theme_nav_menu_css_class( $classes, $item, $args ) {
	if ( isset( $args->theme_location ) && 'primary' === $args->theme_location ) {
		$classes[] = 'centinela-header__item';
	}
	if ( isset( $args->theme_location ) && 'footer' === $args->theme_location ) {
		$classes[] = 'block';
	}
	return $classes;
}
add_filter( 'nav_menu_css_class', 'centinela_theme_nav_menu_css_class', 10, 3 );

function centinela_theme_nav_menu_link_attributes( $atts, $item, $args ) {
	if ( isset( $args->theme_location ) && 'primary' === $args->theme_location ) {
		$atts['class'] = isset( $atts['class'] ) ? $atts['class'] . ' centinela-header__link' : 'centinela-header__link';
	}
	if ( isset( $args->theme_location ) && 'footer' === $args->theme_location ) {
		$atts['class'] = isset( $atts['class'] ) ? $atts['class'] . ' text-gray-400 hover:text-white no-underline' : 'text-gray-400 hover:text-white no-underline';
	}
	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'centinela_theme_nav_menu_link_attributes', 10, 3 );
