<?php
/**
 * CPT Testimonios.
 *
 * Para usar con Elementor (Loop Carousel):
 * - Título del proyecto: título del post.
 * - Descripción del testimonio: contenido del post (editor).
 * - Imagen (logo / ícono): imagen destacada.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar tipo de contenido "Testimonios".
 */
function centinela_register_cpt_testimonios() {
	$labels = array(
		'name'                  => __( 'Testimonios', 'centinela-group-theme' ),
		'singular_name'         => __( 'Testimonio', 'centinela-group-theme' ),
		'menu_name'             => __( 'Testimonios', 'centinela-group-theme' ),
		'name_admin_bar'        => __( 'Testimonio', 'centinela-group-theme' ),
		'add_new'               => __( 'Añadir nuevo', 'centinela-group-theme' ),
		'add_new_item'          => __( 'Añadir nuevo testimonio', 'centinela-group-theme' ),
		'new_item'              => __( 'Nuevo testimonio', 'centinela-group-theme' ),
		'edit_item'             => __( 'Editar testimonio', 'centinela-group-theme' ),
		'view_item'             => __( 'Ver testimonio', 'centinela-group-theme' ),
		'all_items'             => __( 'Todos los testimonios', 'centinela-group-theme' ),
		'search_items'          => __( 'Buscar testimonios', 'centinela-group-theme' ),
		'not_found'             => __( 'No se encontraron testimonios.', 'centinela-group-theme' ),
		'not_found_in_trash'    => __( 'No hay testimonios en la papelera.', 'centinela-group-theme' ),
	);

	$args = array(
		'labels'             => $labels,
		'public'             => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'has_archive'        => false,
		'rewrite'             => array(
			'slug'       => 'testimonios',
			'with_front' => true,
		),
		'supports'            => array( 'title', 'editor', 'thumbnail', 'revisions' ),
		'menu_icon'           => 'dashicons-testimonial',
		'show_in_nav_menus'   => false,
	);

	register_post_type( 'testimonio', $args );
}
add_action( 'init', 'centinela_register_cpt_testimonios' );

/**
 * Al activar el tema, regenerar reglas de reescritura para que las URLs de testimonios
 * (y el editor de Elementor al cargar el preview) no devuelvan 404.
 */
function centinela_testimonios_flush_rewrite_rules() {
	centinela_register_cpt_testimonios();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'centinela_testimonios_flush_rewrite_rules' );

/**
 * Indicar a Elementor que puede usar el editor en el post type "testimonio".
 * Evita 404 al abrir "Editar con Elementor" si Elementor no lo tenía en su lista.
 */
function centinela_testimonios_elementor_support() {
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}
	add_post_type_support( 'testimonio', 'elementor' );
}
add_action( 'init', 'centinela_testimonios_elementor_support', 20 );

/**
 * Busca recursivamente el primer widget "Encabezado" (heading) en la estructura de Elementor.
 *
 * @param array $elements Elementos de Elementor (array de elementos con posible hijos en 'elements').
 * @return array|null Datos del elemento heading o null si no hay ninguno.
 */
function centinela_testimonio_find_first_heading_element( $elements ) {
	if ( ! is_array( $elements ) ) {
		return null;
	}
	foreach ( $elements as $element ) {
		if ( ! is_array( $element ) ) {
			continue;
		}
		if ( isset( $element['widgetType'] ) && $element['widgetType'] === 'heading' ) {
			return $element;
		}
		if ( ! empty( $element['elements'] ) ) {
			$found = centinela_testimonio_find_first_heading_element( $element['elements'] );
			if ( $found !== null ) {
				return $found;
			}
		}
	}
	return null;
}

/**
 * Imprime (una sola vez por post_id) el CSS del documento Elementor del testimonio,
 * para que los estilos del Encabezado se apliquen dentro del Loop Carousel.
 *
 * @param int $post_id ID del testimonio.
 */
function centinela_testimonio_ensure_css_printed( $post_id ) {
	static $printed = array();
	$post_id = (int) $post_id;
	if ( $post_id <= 0 || isset( $printed[ $post_id ] ) ) {
		return;
	}
	if ( ! did_action( 'elementor/loaded' ) || get_post_type( $post_id ) !== 'testimonio' ) {
		return;
	}
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	if ( ! $document || ! $document->is_built_with_elementor() ) {
		return;
	}
	$css_class = \Elementor\Core\Files\CSS\Post::class;
	if ( ! class_exists( $css_class ) ) {
		return;
	}
	$css_file = $css_class::create( $post_id );
	if ( $css_file ) {
		$css_file->print_css();
		$printed[ $post_id ] = true;
	}
}

/**
 * Obtiene el HTML del subtítulo del testimonio (primer widget Encabezado de Elementor).
 * Incluye el CSS del testimonio y envuelve el HTML en el contenedor que Elementor usa
 * (.elementor-{post_id}) para que se apliquen mayúsculas, tamaño, margin, etc.
 *
 * @param int $post_id ID del post tipo testimonio.
 * @return string HTML del encabezado o cadena vacía si no hay Encabezado o Elementor no está disponible.
 */
function centinela_testimonio_render_subtitle( $post_id ) {
	if ( ! did_action( 'elementor/loaded' ) || get_post_type( $post_id ) !== 'testimonio' ) {
		return '';
	}
	$document = \Elementor\Plugin::$instance->documents->get( $post_id );
	if ( ! $document || ! $document->is_built_with_elementor() ) {
		return '';
	}
	$elements_data = $document->get_elements_data();
	if ( empty( $elements_data ) ) {
		return '';
	}
	$heading_element = centinela_testimonio_find_first_heading_element( $elements_data );
	if ( $heading_element === null ) {
		return '';
	}

	// Imprimir el CSS del testimonio (una vez por post_id) para que los estilos del Encabezado se vean.
	centinela_testimonio_ensure_css_printed( $post_id );

	try {
		$html = $document->render_element( $heading_element );
		if ( $html === '' ) {
			return '';
		}
		// El CSS de Elementor usa selectores .elementor-{post_id} .elementor-element...
		// Envolvemos el widget en el mismo contenedor que usa la página del testimonio.
		return '<div class="elementor elementor-' . (int) $post_id . '">' . $html . '</div>';
	} catch ( \Exception $e ) {
		return '';
	}
}

