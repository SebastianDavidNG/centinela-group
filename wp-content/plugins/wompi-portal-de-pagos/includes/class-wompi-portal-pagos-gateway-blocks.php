<?php
defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Wompi_Portal_Pagos_Gateway_Blocks extends AbstractPaymentMethodType {


	// your payment gateway name
	protected $name = 'wompi';
	/**
	 * Get payment method scripthandles
	 */
	public function get_payment_method_script_handles() {
		wp_register_script(
			'my_custom_gateway-blocks-integration',
			plugin_dir_url(__DIR__) . 'build/checkout.js',
			[
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
			],
			'1.0.0',
			true
		);
		if (function_exists('wp_set_script_translations') ) {            
			wp_set_script_translations('my_custom_gateway-blocks-integration');
		}
		return [ 'my_custom_gateway-blocks-integration' ];
	}
	/**
	 * Get payment method data
	 */
	public function get_payment_method_data() {
		return [
			'title' => 'Wompi',
		];
	}
	/**
	 * Get payment method supports
	 */
	public function initialize() {

	}
}
