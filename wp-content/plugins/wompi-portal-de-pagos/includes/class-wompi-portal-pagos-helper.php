<?php
defined('ABSPATH') || exit;

/**
 * Provides static methods as helpers
 */
class Wompi_Portal_Pagos_Helper {

	/**
	 * Check if current request is webhook
	 */
	public static function is_webhook( $log = false ) {
		if ( isset( $_SERVER['REQUEST_METHOD'] )
			&& 'POST' === $_SERVER['REQUEST_METHOD']
			&& isset( $_GET['wc-api'] )
			&& 'wc_wompi' === sanitize_text_field(wp_unslash( $_GET['wc-api'] )) ) {
			return true;
		} else {
			if ( $log ) {
				WompiPortalPagos_Logger::log( 'Webhook checking error' );
			}
		  return false;
		}
	}

	/**
	 * Get amount in cents
	 */
	public static function get_amount_in_cents( $amount ) {
		// Asegurarse de que el monto es un número float válido
		$amount = floatval( $amount );
		return (int) ( $amount * 100 );
	}
}
