<?php
defined('ABSPATH') || exit;

/**
 * Class that represents admin notices.
 */
class WC_Wompi_Admin_Notices {

	/**
	 * Notices (array)
	 */
	public $notices = array();

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action('admin_notices', array( $this, 'admin_notices' ));
	}

	/**
	 * Allow this class and other classes to add slug keyed notices (to avoid duplication).
	 */
	public function add_admin_notice( $slug, $class, $message, $dismissible = false ) {
		$this->notices[ $slug ] = array(
		'class'       => $class,
		'message'     => $message,
		'dismissible' => $dismissible,
		);
	}

	/**
	 * Display any notices we've collected thus far.
	 */
	public function admin_notices() {
		if (! current_user_can('manage_woocommerce') ) {
			return;
		}

		$this->wompi_check_environment();

		foreach ( (array) $this->notices as $notice_key => $notice ) {
			echo '<div class="' . esc_attr($notice['class']) . '"><p>' . wp_kses($notice['message'], array( 'a' => array( 'href' => array(), 'target' => array() ) )) . '</p></div>';
		}
	}

	/**
	 * The backup sanity check, in case the plugin is activated in a weird way,
	 * or the environment changes after activation. Also handles upgrade routines.
	 */
	public function wompi_check_environment() {
		$options               = Wompi_Portal_Pagos_Main::$settings;
		$testmode              = ( isset($options['testmode']) && 'yes' === $options['testmode'] ) ? true : false;
		$test_pub_key          = isset($options['test_public_key']) ? $options['test_public_key'] : '';
		$test_secret_key       = isset($options['test_private_key']) ? $options['test_private_key'] : '';
		$test_event_secret_key = isset($options['test_event_secret_key']) ? $options['test_event_secret_key'] : '';
		$test_integrity_key    = isset($options['test_integrity_key']) ? $options['test_integrity_key'] : '';
		$live_pub_key          = isset($options['public_key']) ? $options['public_key'] : '';
		$live_secret_key       = isset($options['private_key']) ? $options['private_key'] : '';
		$event_secret_key      = isset($options['event_secret_key']) ? $options['event_secret_key'] : '';
		$integrity_key         = isset($options['integrity_key']) ? $options['integrity_key'] : '';

		if (isset($options['enabled']) && 'yes' === $options['enabled'] ) {

			$keys_valid = true;

			$supported_currency = WompiPortalPagos_Gateway::get_supported_currency();
			$setting_link       = $this->get_setting_link();

			// Check if keys are entered properly per live/test mode.
			if ($testmode ) {
				if (empty($test_pub_key) 
					|| empty($test_secret_key) 
					|| empty($test_event_secret_key) 
					|| empty($test_integrity_key) 
					|| empty($supported_currency) 
				) {
					// translators: %s: wompi settings link
					$this->add_admin_notice('wc_wompi', 'notice notice-error', sprintf(__('Wompi is in test mode however your test keys may not be valid. Please go to your settings and, <a href="%s">set your Wompi account keys</a>.', 'wompi-portal-de-pagos'), $setting_link));
					$keys_valid = false;
				}
			} else {
				if (empty($live_pub_key) 
					|| empty($live_secret_key) 
					|| empty($event_secret_key) 
					|| empty($integrity_key) 
					|| empty($supported_currency) 
				) {
					// translators: %s: wompi settings link
					$this->add_admin_notice('wc_wompi', 'notice notice-error', sprintf(__('Wompi is in live mode however your live keys may not be valid. Please go to your settings and, <a href="%s">set your Wompi account keys</a>.', 'wompi-portal-de-pagos'), $setting_link));
					$keys_valid = false;
				}
			}

			if ($keys_valid ) {

				// Supported currency notice
				if (! in_array(get_woocommerce_currency(), $supported_currency) ) {
					// translators: %1$s: WompiPortalPagos_Gateway, %2$s: supported currency
					$this->add_admin_notice('wc_wompi', 'notice notice-error', sprintf(__('%1$s is enabled - it requires store currency to be set to %2$s', 'wompi-portal-de-pagos'), 'WompiPortalPagos_Gateway', implode(', ', $supported_currency)));
				}
			}
		}
	}

	/**
	 * Get setting link.
	 */
	public function get_setting_link() {
		$use_id_as_section = function_exists('WC') ? version_compare(WC()->version, '2.6', '>=') : false;

		$section_slug = $use_id_as_section ? 'wompi' : strtolower('WompiPortalPagos_Gateway');

		return admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $section_slug);
	}
}

new WC_Wompi_Admin_Notices();
