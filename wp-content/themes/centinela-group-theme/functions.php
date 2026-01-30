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
 * Incluir partes del tema (header/footer por defecto)
 */
require_once CENTINELA_THEME_DIR . '/inc/class-syscom-api.php';
require_once CENTINELA_THEME_DIR . '/inc/syscom-settings.php';
require_once CENTINELA_THEME_DIR . '/inc/template-header.php';
require_once CENTINELA_THEME_DIR . '/inc/template-footer.php';

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
		CENTINELA_THEME_URI . '/assets/js/theme.min.js',
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
