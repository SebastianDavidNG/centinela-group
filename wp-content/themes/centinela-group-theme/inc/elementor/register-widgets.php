<?php
/**
 * Registrar widgets y categoría de Elementor (Centinela Group)
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar categoría "Centinela"
 */
function centinela_elementor_register_categories( $elements_manager ) {
	$elements_manager->add_category(
		'centinela',
		array(
			'title' => __( 'Centinela Group', 'centinela-group-theme' ),
			'icon'  => 'eicon-folder',
		)
	);
}
add_action( 'elementor/elements/categories_registered', 'centinela_elementor_register_categories' );

/**
 * Registrar widgets
 */
function centinela_elementor_register_widgets( $widgets_manager ) {
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-hero-slider-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-hero-page-inner-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-servicios-slider-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-content-block-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-video-button-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-whatsapp-float-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-loop-carousel-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-image-box-widget.php';
	require_once CENTINELA_THEME_DIR . '/inc/elementor/class-cotizacion-web-form-widget.php';
	$widgets_manager->register( new \Centinela_Hero_Slider_Widget() );
	$widgets_manager->register( new \Centinela_Hero_Page_Inner_Widget() );
	$widgets_manager->register( new \Centinela_Servicios_Slider_Widget() );
	$widgets_manager->register( new \Centinela_Content_Block_Widget() );
	$widgets_manager->register( new \Centinela_Video_Button_Widget() );
	$widgets_manager->register( new \Centinela_WhatsApp_Float_Widget() );
	$widgets_manager->register( new \Centinela_Loop_Carousel_Widget() );
	$widgets_manager->register( new \Centinela_Image_Box_Widget() );
	$widgets_manager->register( new \Centinela_Cotizacion_Web_Form_Widget() );
}
add_action( 'elementor/widgets/register', 'centinela_elementor_register_widgets' );

/**
 * Título del formulario Cotización Web: renderizar antes de .elementor-widget-container
 * para que la estructura coincida con Figma (título fuera del contenedor, sobre el formulario).
 */
add_action( 'elementor/widget/before_render_content', 'centinela_cwf_render_title_before_container', 10, 1 );
