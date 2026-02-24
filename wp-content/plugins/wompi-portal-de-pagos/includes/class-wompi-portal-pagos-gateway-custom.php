<?php
defined('ABSPATH') || exit;

/**
 * Custom Wompi payment gateway
 */
class Wompi_Portal_Pagos_Gateway_Custom extends WC_Payment_Gateway {

	/**
	 * Vars
	 */
	const MINIMUM_ORDER_AMOUNT = 150000;
	public $testmode;
	public $public_key;
	public $private_key;
	public $event_secret_key;
	public $integrity_key;
	public static $supported_currency = false;
	// This endpoint opens the widget for creating payments through Wompi
	// Wompi documentation: https://docs.wompi.co/docs/colombia/widget-checkout-web/
	private $checkout_url = 'https://checkout.wompi.co';

	/**
	 * Init hooks
	 */
	public function init_hooks() {
		// Hook to enqueue script when generating the Wompi widget
		add_action('woocommerce_receipt_wompi', array($this, 'generate_wompi_widget'));
	}

	/**
	 *
	 * Returns all supported currencies for this payment method
	 */
	public static function get_supported_currency() {
		if (false === self::$supported_currency) {
			self::$supported_currency = apply_filters('wc_wompi_supported_currencies', ( new Wompi_Portal_Pagos_API() )->get_merchant_data('accepted_currencies'));
		}
		return self::$supported_currency;
	}

	/**
	 * Enqueue the Wompi widget script and localize data for the script.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function enqueue_wompi_script($order_id) {

		// Get the order object
		$order = wc_get_order($order_id);

		// Variables needed for the script
		$amount_in_cents = $order->get_total() * 100; // WooCommerce stores amounts as float, convert to cents
		$public_key      = 'yes' === Wompi_Portal_Pagos_Main::$settings['testmode'] ? Wompi_Portal_Pagos_Main::$settings['test_public_key'] : Wompi_Portal_Pagos_Main::$settings['public_key'];
		$integrity_key   = 'yes' === Wompi_Portal_Pagos_Main::$settings['testmode'] ? Wompi_Portal_Pagos_Main::$settings['test_integrity_key'] : Wompi_Portal_Pagos_Main::$settings['integrity_key'];
		$currency        = $order->get_currency();
		$signature       = hash('sha256', "{$order_id}{$amount_in_cents}{$currency}{$integrity_key}");
		$checkout_url    = $this->checkout_url;

		// Register and enqueue the widget script
		wp_enqueue_script('wompi-widget', esc_url($checkout_url . '/widget.js'), array(), '1.0.0', true);

		// Pass data to JavaScript
		$wompi_data = array(
			'checkoutUrl' => esc_url($checkout_url),
			'signature' => esc_attr($signature),
			'publicKey' => esc_attr($public_key),
			'currency' => esc_attr($currency),
			'amountInCents' => esc_attr($amount_in_cents),
			'reference' => esc_attr($order_id),
			'redirectUrl' => esc_url($order->get_checkout_order_received_url()),
		);

		// Locate script
		wp_localize_script('wompi-widget', 'wompiData', $wompi_data);

		// Inyectar script personalizado para configurar el widget
		$inline_script = "
            document.addEventListener('DOMContentLoaded', function() {
                var script = document.createElement('script');
                script.src = wompiData.checkoutUrl + '/widget.js';
                script.setAttribute('data-render', 'button');
                script.setAttribute('data-signature:integrity', wompiData.signature);
                script.setAttribute('data-public-key', wompiData.publicKey);
                script.setAttribute('data-currency', wompiData.currency);
                script.setAttribute('data-amount-in-cents', wompiData.amountInCents);
                script.setAttribute('data-reference', wompiData.reference);
                script.setAttribute('data-redirect-url', wompiData.redirectUrl);
                document.querySelector('.wompi-button-holder').appendChild(script);
                
				var checkButtonInterval = setInterval(function() {
					var button = document.querySelector('.wompi-button-holder button');
					if (button) {
						button.click();  // Disparar el evento de clic en el botón para abrir el widget
						clearInterval(checkButtonInterval); // Detener la verificación
					}
				}, 100);
            });
        ";

		wp_add_inline_script('wompi-widget', $inline_script);
	}

	/**
	 * Generate the Wompi widget.
	 *
	 * @param int $order_id The ID of the order.
	 */
	public function generate_wompi_widget($order_id) {
		$this->enqueue_wompi_script($order_id);
		$this->render_wompi_button_holder();
	}

	/**
	 * Render the Wompi button holder.
	 */
	public function render_wompi_button_holder() {
		$style_value = 'display: flex; justify-content: space-evenly;';
		echo "<div id='wompi-button' class='wompi-button-holder' style='" . esc_attr($style_value) . "'></div>";
	}

	/**
	 * Generate order key on thank you page
	 */
	public static function thankyou_order_key($order_key) {
		if (empty(sanitize_text_field(wp_unslash($_GET['key'])))) {
			global $wp;
			$order     = wc_get_order(sanitize_text_field($wp->query_vars['order-received']));
			$order_key = $order->get_order_key();
		}
		return $order_key;
	}

	/**
	 * Inform user if status of received order is failed on the thank you page
	 */
	public static function thankyou_order_received_text($text) {
		global $wp;
		$order  = wc_get_order($wp->query_vars['order-received']);
		$status = $order->get_status();
		if (in_array($status, array('cancelled', 'failed', 'refunded', 'voided'))) {
			// translators: %s: order status
			return '<div class="woocommerce-error">' . sprintf(__('This order changed to status &ldquo;%s&rdquo;. Please contact us if you need assistance.', 'wompi-portal-de-pagos'), $status) . '</div>';
		} else {
			return $text;
		}
	}

	/**
	 * Validation on checkout page
	 */
	public static function checkout_validation($fields, $errors) {
		$amount = floatval(WC()->cart->total);
		if (!self::validate_minimum_order_amount($amount)) {
			// translators: %s: minimum order amount
			$errors->add('validation', sprintf(__('Sorry, the minimum allowed order total is %1$s to use this payment method.', 'wompi-portal-de-pagos'), wc_remove_number_precision(self::MINIMUM_ORDER_AMOUNT)));
		}
	}

	/**
	 * Validates that the order meets the minimum order amount
	 */
	public static function validate_minimum_order_amount($amount) {
		if (Wompi_Portal_Pagos_Helper::get_amount_in_cents($amount) < self::MINIMUM_ORDER_AMOUNT) {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Output payment method type on order admin page
	 */
	public static function admin_order_data_after_order_details($order) {
		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
		// translators: %s: payment method type
		echo '<p class="form-field form-field-wide wompi-payment-method-type"><strong>' . esc_html(__('Payment method type', 'wompi-portal-de-pagos')) . ':</strong> ' . esc_html(get_post_meta($order_id, Wompi_Portal_Pagos_Main::FIELD_PAYMENT_METHOD_TYPE, true)) . '</p>';
	}
}
