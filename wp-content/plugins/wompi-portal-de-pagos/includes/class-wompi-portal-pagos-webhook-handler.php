<?php
defined('ABSPATH') || exit;

/**
 * Webhook Handler Class
 */
class Wompi_Portal_Pagos_Webhook_Handler {


	/**
	 * Constructor
	 */
	public function __construct() {
		add_action('woocommerce_api_wc_wompi', array($this, 'check_for_webhook'));
	}

	/**
	 * Check incoming requests for Wompi Webhook data and process them
	 */
	public function check_for_webhook() {

		// Verify if the request is from Wompi's webhook
		if ( ! Wompi_Portal_Pagos_Helper::is_webhook( true ) ) {
			return false;
		}

		// Read the input from php://input
		$input = sanitize_text_field(wp_unslash(file_get_contents('php://input')));

		// Validate if the input is not empty
		if ( empty( $input ) ) {
			WompiPortalPagos_Logger::log('Empty webhook payload');
			status_header(400); // Respond with 400 Bad Request status
			return;
		}

		// Try to decode the JSON input safely
		$response = json_decode( $input, false );

		// Validate that the JSON is correctly decoded and is an object
		if ( json_last_error() === JSON_ERROR_NONE && is_object( $response ) ) {

			// Process only the necessary fields from the webhook response
			if ( isset( $response->data->transaction ) && isset( $response->event ) ) {

				// Log the processed transaction and event
				WompiPortalPagos_Logger::log('Webhook transaction: ' . wp_json_encode( $response->data->transaction ));
				WompiPortalPagos_Logger::log('Webhook event: ' . wp_json_encode( $response->event ));

				// Process the webhook with the specific data
				$this->process_webhook( $response, $response->event );

			} else {
				// Log if the structure of the webhook is invalid
				WompiPortalPagos_Logger::log('Invalid webhook structure');
				status_header(400); // Respond with 400 Bad Request status
			}

		} else {
			// Log the error if JSON is invalid
			WompiPortalPagos_Logger::log('Invalid JSON webhook response: ' . json_last_error_msg());
			status_header(400); // Respond with 400 Bad Request status
		}
	}


	/**
	 * Processes the incoming webhook
	 */
	public function process_webhook($response, $event) {
		switch ($event) {
			case Wompi_Portal_Pagos_API::EVENT_TRANSACTION_UPDATED:
				$this->process_webhook_payment($response);
				break;
			default:
				WompiPortalPagos_Logger::log('TRANSACTION Event Not Found');
				status_header(400);
		}
	}

	/**
	 * Process the payment
	 */
	public function process_webhook_payment($response) {
		$data = $response->data;
		// Validate response checksum
		if ($this->is_valid_checksum($response)) {
			// Validate transaction response
			if (isset($data->transaction)) {
				$transaction = $data->transaction;
				$order       = new WC_Order($transaction->reference);
				if ($this->is_payment_valid($order, $transaction)) {
					// Update order data
					$this->update_order_data($order, $transaction);
					$this->apply_status($order, $transaction);
					status_header(200);
				} else {
					$this->update_transaction_status($order, __('Wompi payment validation is invalid. TRANSACTION ID: ', 'wompi-portal-de-pagos') . ' (' . $transaction->id . ')', 'failed');
					status_header(400);
				}
			} else {
				WompiPortalPagos_Logger::log('TRANSACTION Response Not Found');
				status_header(400);
			}
		} else {
			WompiPortalPagos_Logger::log('TRANSACTION Invalid checksum');
			status_header(500);
		}
	}

	/**
	 * Validate transaction response
	 */
	protected function is_payment_valid($order, $transaction) {
		if (false === $order) {
			WompiPortalPagos_Logger::log('Order Not Found TRANSACTION ID: ' . $transaction->id);
			return false;
		}

		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;

		if ('wompi' !== $order->get_payment_method()) {
			WompiPortalPagos_Logger::log('Payment method incorrect TRANSACTION ID: ' . $transaction->id . ' ORDER ID: ' . $order_id . ' PAYMENT METHOD: ' . $order->get_payment_method());
			return false;
		}

		$amount = Wompi_Portal_Pagos_Helper::get_amount_in_cents($order->get_total());
		if ($transaction->amount_in_cents !== $amount) {
			WompiPortalPagos_Logger::log('Amount incorrect  TRANSACTION ID: ' . $transaction->id . ' ORDER ID: ' . $order_id . ' AMOUNT: ' . $amount);
			return false;
		}

		return true;
	}

	/**
	 * Apply transaction status
	 */
	public function apply_status($order, $transaction) {
		switch ($transaction->status) {
			case Wompi_Portal_Pagos_API::STATUS_APPROVED:
				$order->payment_complete($transaction->id);
				$this->update_transaction_status($order, __('Wompi payment APPROVED. TRANSACTION ID: ', 'wompi-portal-de-pagos') . ' (' . $transaction->id . ')', 'processing');
				break;
			case Wompi_Portal_Pagos_API::STATUS_VOIDED:
				WompiPortalPagos_Gateway::process_void($order);
				$this->update_transaction_status($order, __('Wompi payment VOIDED. TRANSACTION ID: ', 'wompi-portal-de-pagos') . ' (' . $transaction->id . ')', 'voided');
				break;
			case Wompi_Portal_Pagos_API::STATUS_DECLINED:
				$this->update_transaction_status($order, __('Wompi payment DECLINED. TRANSACTION ID: ', 'wompi-portal-de-pagos') . ' (' . $transaction->id . ')', 'cancelled');
				break;
			default: // ERROR
				$this->update_transaction_status($order, __('Wompi payment ERROR. TRANSACTION ID: ', 'wompi-portal-de-pagos') . ' (' . $transaction->id . ')', 'failed');
		}
	}

	/**
	 * Update order data
	 */
	public function update_order_data($order, $transaction) {
		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
		// Check if order data was set
		if (!$order->get_transaction_id()) {
			// Set transaction id
			update_post_meta($order_id, '_transaction_id', $transaction->id);
			// Set payment method type
			update_post_meta($order_id, Wompi_Portal_Pagos_Main::FIELD_PAYMENT_METHOD_TYPE, $transaction->payment_method_type);
			// Set customer email
			if (!$order->get_billing_email()) {
				update_post_meta($order_id, '_billing_email', $transaction->customer_email);
				update_post_meta($order_id, '_billing_address_index', $transaction->customer_email);
			}
			// Set first name
			if (!$order->get_billing_first_name() && property_exists($transaction, 'customer_data') && property_exists($transaction->customer_data, 'full_name')) {
				update_post_meta($order_id, '_billing_first_name', $transaction->customer_data->full_name);
			}
			// Set last name
			if (!$order->get_billing_last_name()) {
				update_post_meta($order_id, '_billing_last_name', '');
			}
			// Set phone number
			if (!$order->get_billing_phone() && property_exists($transaction, 'customer_data') && property_exists($transaction->customer_data, 'phone_number')) {
				update_post_meta($order_id, '_billing_phone', $transaction->customer_data->phone_number);
			}
		}
	}

	/**
	 * Update transaction status
	 */
	public function update_transaction_status($order, $note, $status) {
		$order->add_order_note($note);
		$status = apply_filters('wc_wompi_order_status', $status, $order);
		if ($status) {
			$order->update_status($status);
		}
	}

	/**
	 * Validate response checksum according with https://docs.wompi.co/docs/en/eventos#seguridad
	 *
	 * @param  $response
	 * @return bool
	 * @throws Exception
	 */
	private function is_valid_checksum($response) {
		try {
			$toHash = '';
			//concatenate properties
			$properties = $response->signature->properties;
			foreach ($properties as $property) {
				$keys = explode('.', $property);

				$result = $response->data;
				foreach ($keys as $key) {
					$result = $result->{$key};
				}
				$toHash .= $result;
			}
			//concatenate timestamp
			$toHash .= $response->timestamp;
			//concatenate event private key
			$options = Wompi_Portal_Pagos_Main::$settings;
			if ('yes' === $options['testmode']) {
				$toHash .= $options['test_event_secret_key'];
			} else {
				$toHash .= $options['event_secret_key'];
			}
			return hash('sha256', $toHash) === $response->signature->checksum;
		} catch (\Exception $e) {
			WompiPortalPagos_Logger::log('Exception while validating checksum: ' . $e->getMessage());
			throw $e;
		}
	}
}

new Wompi_Portal_Pagos_Webhook_Handler();
