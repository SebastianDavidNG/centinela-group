<?php
defined( 'ABSPATH' ) || exit;

/*
Plugin Name: Wompi Portal de Pagos
Plugin URI: https://docs.wompi.co/
Description: Wompi Portal de Pagos para WooCommerce.
Version: 2.0.0
Author: Wompi
Author URI: https://wompi.co/
Domain Path: /languages
Text Domain: wompi-portal-de-pagos
WC requires at least: 3.5.0
WC tested up to: 8.8.3
License: GPL-3.0
*/

/**
 * Constants
 */
define( 'WC_WOMPI_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_WOMPI_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_WOMPI_MIN_WC_VER', '3.5.0' );

/**
 * Notice if WooCommerce not activated
 */
function wompi_portal_pagos_wc_missing_notice() {
	$wc_link_url  = esc_url('https://woocommerce.com/');
	$wc_link_text = esc_html__('WooCommerce', 'wompi-portal-de-pagos');

	$wc_link = '<a href="' . $wc_link_url . '" target="_blank">' . $wc_link_text . '</a>';

	$message = sprintf(
	/* Translators: %s is the WooCommerce download link. */
		esc_html__('WooCommerce Wompi Gateway requires WooCommerce to be installed and active. You can download %s here.', 'wompi-portal-de-pagos'),
		wp_kses($wc_link, array('a' => array('href' => array(), 'target' => array()))) // Permitir etiquetas <a>
	);

	// Mostrar el mensaje escapado.
	echo '<div class="error"><p><strong>' . esc_html($message) . '</strong></p></div>';
}

/**
 * Notice if WooCommerce not supported
 */
function wompi_portal_pagos_wc_not_supported_notice() {
	$min_wc_version     = esc_html( WC_WOMPI_MIN_WC_VER );
	$current_wc_version = esc_html( WC_VERSION );

	echo '<div class="error"><p><strong>' . sprintf(
		/* Translators: %1$s is the minimum required version of WooCommerce, %2$s is the current version of WooCommerce. */
			esc_html__( 'WooCommerce Wompi Gateway requires WooCommerce %1$s or greater. Current version: %2$s', 'wompi-portal-de-pagos' ),
			esc_html( $min_wc_version ),   // Escapando $min_wc_version
			esc_html( $current_wc_version ) // Escapando $current_wc_version
		) . '</strong></p></div>';
}

/**
 * Initialize the gateway
 */
add_action('plugins_loaded', 'wompi_portal_pagos_init', 0);
function wompi_portal_pagos_init() {
	/**
	 * Load languages
	 */
	load_plugin_textdomain( 'wompi-portal-de-pagos', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

	/**
	 * Check if WooCommerce is activated
	 */
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wompi_portal_pagos_wc_missing_notice' );
		return;
	}

	/**
	 * Check if WooCommerce is supported
	 */
	if ( version_compare( WC_VERSION, WC_WOMPI_MIN_WC_VER, '<' ) ) {
		add_action( 'admin_notices', 'wompi_portal_pagos_wc_not_supported_notice' );
		return;
	}

	/**
	 * Returns the main instance of Wompi_Portal_Pagos_Main
	 */
	require_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-main.php';
	Wompi_Portal_Pagos_Main::instance();

	/**
	 * Add plugin action links
	 */
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( 'Wompi_Portal_Pagos_Main', 'plugin_action_links' ) );

}

/**
 *  Add woocommerce payment gateways
 */
add_filter('woocommerce_payment_gateways', 'wompi_portal_pagos_add_gateway');

/**
 * Adds a custom payment gateway to the list of WooCommerce payment gateways.
 *
 * @param array $gateways Existing list of gateways.
 * @return array Updated list of gateways including the custom gateway.
 */
function wompi_portal_pagos_add_gateway($gateways) {
	$gateways[] = 'WompiPortalPagos_Gateway'; // Add the Wompi custom gateway
	return $gateways; // Return the updated gateways list
}


/**
 * Declares compatibility with the cart checkout blocks feature and HPOS.
 */
function wompi_gateway_cart_checkout_blocks_compatibility() {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		// Declare compatibility for 'cart_checkout_blocks'
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
	}
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		// Declare compatibility for 'HPOS'
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
}
// Hook the custom function to the 'before_woocommerce_init' action
add_action('before_woocommerce_init', 'wompi_gateway_cart_checkout_blocks_compatibility');

// Hook the custom function to the 'woocommerce_blocks_loaded' action
add_action( 'woocommerce_blocks_loaded', 'wompi_register_order_approval_payment_method_type' );

/**
 * Custom function to register a payment method type
 */
function wompi_register_order_approval_payment_method_type() {
	// Check if the required class exists
	if ( ! class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		return;
	}

	// Include the custom Blocks Checkout class
	require_once plugin_dir_path(__FILE__) . '/includes/class-wompi-portal-pagos-gateway-blocks.php';

	// Hook the registration function to the 'woocommerce_blocks_payment_method_type_registration' action
	add_action(
		'woocommerce_blocks_payment_method_type_registration',
		function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
			// Register an instance of Wompi_Portal_Pagos_Gateway_Blocks
			$payment_method_registry->register( new Wompi_Portal_Pagos_Gateway_Blocks() );
		}
	);
}
