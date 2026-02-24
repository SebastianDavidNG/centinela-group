<?php
defined('ABSPATH') || exit;

/**
 * Communicates with Wompi API
 */
class Wompi_Portal_Pagos_API {


	/**
	 * Define API constants
	 */
	// We make a call to the Wompi production endpoint to process a payment.
	// API Documentation: https://app.swaggerhub.com/apis-docs/waybox/wompi/1.2.0
	const ENDPOINT = 'https://production.wompi.co/v1';
	// We make a call to the Wompi test endpoint to process a payment.
	// API Documentation: https://app.swaggerhub.com/apis-docs/waybox/wompi/1.2.0
	const ENDPOINT_TEST             = 'https://sandbox.wompi.co/v1';
	const EVENT_TRANSACTION_UPDATED = 'transaction.updated';
	const STATUS_APPROVED           = 'APPROVED';
	const STATUS_DECLINED           = 'DECLINED';
	const STATUS_VOIDED             = 'VOIDED';
	const PAYMENT_TYPE_CARD         = 'CARD';

	/**
	 * The single instance of the class
	 */
	protected static $_instance = null;

	/**
	 * API endpoint
	 */
	private $endpoint = '';

	/**
	 * Public API Key
	 */
	private $public_key = '';

	/**
	 * Private API Key
	 */
	private $private_key = '';

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
	 * Constructor
	 */
	public function __construct() {

		$options = Wompi_Portal_Pagos_Main::$settings;

		if (isset($options['testmode']) && 'yes' === $options['testmode']) {
			$this->endpoint    = self::ENDPOINT_TEST;
			$this->public_key  = $options['test_public_key'];
			$this->private_key = $options['test_private_key'];
		} else {
			$this->endpoint    = self::ENDPOINT;
			$this->public_key  = $options['public_key'] ?? '';
			$this->private_key = $options['private_key'] ?? '';
		}
	}

	/**
	 * Getter
	 */
	public function __get( $name ) {
		if (property_exists($this, $name) ) {
			return $this->$name;
		}
	}

	/**
	 * Generates the headers to pass to API request
	 */
	private function get_headers( $use_secret ) {
		$headers = array();

		if ($use_secret ) {
			$headers['Authorization'] = 'Bearer ' . $this->private_key;
		}

		return $headers;
	}

	/**
	 * Send the request to Wompi API
	 */
	public function request( $method, $request, $data = null, $use_secret = false ) {
		WompiPortalPagos_Logger::log("==== REQUEST ============================== Start Log ==== \n REQUEST URL: " . $method . ' ' . $this->endpoint . $request . "\n", false);
		if (! is_null($data) ) {
			WompiPortalPagos_Logger::log('REQUEST DATA: ' . wp_json_encode($data), false);
		}

		$headers = $this->get_headers($use_secret);

		$params = array(
			'method'  => $method,
			'headers' => $headers,
			'body'    => $data,
		);

		// Exclude private key from logs
		if ('yes' === Wompi_Portal_Pagos_Main::$settings['logging'] && ! empty($headers) ) {
			$strlen                   = strlen($this->private_key);
			$headers['Authorization'] = 'Bearer ' . ( ! empty($strlen) ? str_repeat('X', $strlen) : '' );
			WompiPortalPagos_Logger::log('REQUEST HEADERS: ' . wp_json_encode($headers), false);
		}

		$response = wp_safe_remote_post($this->endpoint . $request, $params);
		WompiPortalPagos_Logger::log('REQUEST RESPONSE: ' . wp_json_encode($response), false);

		if (is_wp_error($response) ) {
			return false;
		}

		return json_decode($response['body']);
	}

	/**
	 * Transaction void
	 */
	public function transaction_void( $transaction_id ) {
		$response = $this->request('POST', '/transactions/' . $transaction_id . '/void', null, true);
		return self::STATUS_APPROVED == $response->data->status;
	}

	/**
	 * Get merchant data
	 */
	public function get_merchant_data( $type ) {
		$response = $this->request('GET', '/merchants/' . $this->public_key);
		if (isset($response->data) && is_object($response->data) ) {
			$data = $response->data;
			switch ( $type ) {
				case 'accepted_currencies':
					return ( isset($data->accepted_currencies) && is_array($data->accepted_currencies) ) ? $data->accepted_currencies : array();
				default:
					return $data;
			}
		} else {
			return array();
		}
	}
}

Wompi_Portal_Pagos_API::instance();
