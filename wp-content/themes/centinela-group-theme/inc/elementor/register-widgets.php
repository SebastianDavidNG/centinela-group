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
	$widgets_manager->register( new \Centinela_Hero_Slider_Widget() );
	$widgets_manager->register( new \Centinela_Hero_Page_Inner_Widget() );
}
add_action( 'elementor/widgets/register', 'centinela_elementor_register_widgets' );
