<?php
defined('ABSPATH') || exit;

/**
 * Wompi main class
 */
class Wompi_Portal_Pagos_Main {


	/**
	 * Define WP constants
	 */
	const FIELD_PAYMENT_METHOD_TYPE = '_wompi_payment_method_type';

	/**
	 * The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * Settings
	 */
	public static $settings = array();

	/**
	 * Instance
	 */
	public static function instance() {
		if (is_null(self::$_instance) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Cloning is forbidden
	 */
	public function __clone() {
	}

	/**
	 * Unserializing instances of this class is forbidden
	 */
	public function __wakeup() {
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// Get settings
		self::$settings = get_option('woocommerce_wompi_settings');

		// Includes
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-gateway-custom.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-gateway.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-helper.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-de-pagos-logger.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-api.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-webhook-handler.php';
		include_once WC_WOMPI_PLUGIN_PATH . '/includes/class-wompi-portal-pagos-order-statuses.php';

		if (is_admin() ) {
			include_once WC_WOMPI_PLUGIN_PATH . '/includes/admin/class-wc-wompi-admin-notices.php';
		}

		// Hooks
		add_action('admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ));

		if (isset(self::$settings['enabled']) && 'yes' === self::$settings['enabled'] ) {
			add_action('woocommerce_after_checkout_validation', array( 'Wompi_Portal_Pagos_Gateway_Custom', 'checkout_validation' ), 10, 2);
			add_action('woocommerce_thankyou_order_received_text', array( 'Wompi_Portal_Pagos_Gateway_Custom', 'thankyou_order_received_text' ));
			add_action('woocommerce_admin_order_data_after_order_details', array( 'Wompi_Portal_Pagos_Gateway_Custom', 'admin_order_data_after_order_details' ));
			add_filter('woocommerce_thankyou_order_key', array( 'Wompi_Portal_Pagos_Gateway_Custom', 'thankyou_order_key' ));
		}
	}

	/**
	 * Add plugin action links
	 */
	public static function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=wompi') . '">' . __('Settings', 'wompi-portal-de-pagos') . '</a>',
			'<a href="https://docs.wompi.co/">' . esc_html__('Docs', 'wompi-portal-de-pagos') . '</a>',
			'<a href="https://wompi.co/">' . esc_html__('Support', 'wompi-portal-de-pagos') . '</a>',
		);

		return array_merge($plugin_links, $links);
	}

	/**
	 * Admin enqueue scripts
	 */
	// Admin enqueue scripts
	public function admin_enqueue_scripts() {
		wp_enqueue_style('wc_wompi_admin_styles', WC_WOMPI_PLUGIN_URL . '/assets/css/admin.css', array(), '1.0.0');
	}
}
