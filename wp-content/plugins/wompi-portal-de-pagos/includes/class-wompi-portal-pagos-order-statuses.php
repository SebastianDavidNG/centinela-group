<?php
defined('ABSPATH') || exit;

/**
 * Add custom order statuses
 */
class Wompi_Portal_Pagos_Order_Statuses {

	/**
	 * Vars
	 */
	const VOIDED_EXPIRY = 3600; // 1 hour

	/**
	 * Init
	 */
	public function __construct() {
		add_action('init', array($this, 'register_voided_post_status'), 10);
		add_filter('wc_order_statuses', array($this, 'order_statuses'));
		add_action('woocommerce_process_shop_order_meta', array($this, 'process_shop_order_meta'));
	}

	/**
	 * Add custom status to order list
	 */
	public function register_voided_post_status() {
		register_post_status(
			'wc-voided',
			array(
				'label'                     => _x('Voided', 'Order status', 'wompi-portal-de-pagos'),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				// translators: %s: count
				'label_count'               => _n_noop('Voided <span class="count">(%s)</span>', 'Voided <span class="count">(%s)</span>', 'wompi-portal-de-pagos')
			)
		);
	}

	/**
	 * Add custom status to order page drop down
	 */
	public function order_statuses($order_statuses) {
		$add_status = false;
		if (Wompi_Portal_Pagos_Helper::is_webhook()) {
			$add_status = true;
		} else {
			global $pagenow, $post;
			if ('edit.php' === $pagenow && isset($_GET['post_type']) && 'shop_order' === sanitize_text_field(wp_unslash($_GET['post_type']))) {
				$add_status = true;
			} elseif ('post.php' == $pagenow && is_object($post) && 'shop_order' == $post->post_type) {
				$order  = new WC_Order($post->ID);
				$status = $order->get_status();
				if ('voided' == $status || self::check_voided_access($order, $status)) {
					$add_status = true;
				}
			}
		}
		if ($add_status) {
			$order_statuses['wc-voided'] = _x('Voided', 'Order status', 'wompi-portal-de-pagos');
		}

		return $order_statuses;
	}

	/**
	 * Check allowing to change order to Voided status
	 */
	public function check_voided_access($order, $status) {
		if ('completed' != $status) {
			return false;
		}

		$order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
		if (get_post_meta($order_id, Wompi_Portal_Pagos_Main::FIELD_PAYMENT_METHOD_TYPE, true) != Wompi_Portal_Pagos_API::PAYMENT_TYPE_CARD) {
			return false;
		}

		$time_diff = current_time('timestamp') - $order->get_date_completed()->getOffsetTimestamp();
		if ($time_diff > self::VOIDED_EXPIRY) {
			return false;
		}

		return true;
	}

	/**
	 * On order update
	 */
	public function process_shop_order_meta($order_id) {
		// Verify nonce
		if (isset($_SERVER['REQUEST_METHOD']) && 'POST' === $_SERVER['REQUEST_METHOD']) {
			// Sanitize POST data
			$save_order_nonce        = isset($_POST['woocommerce-save_order']) ? sanitize_text_field(wp_unslash($_POST['woocommerce-save_order'])) : '';
			$order_item_nonce        = isset($_POST['woocommerce_order_item_nonce']) ? sanitize_text_field(wp_unslash($_POST['woocommerce_order_item_nonce'])) : '';
			$add_order_note_nonce    = isset($_POST['add-order-note-nonce']) ? sanitize_text_field(wp_unslash($_POST['add-order-note-nonce'])) : '';
			$delete_order_item_nonce = isset($_POST['delete-order-item-nonce']) ? sanitize_text_field(wp_unslash($_POST['delete-order-item-nonce'])) : '';
			$order_status            = isset($_POST['order_status']) ? sanitize_text_field(wp_unslash($_POST['order_status'])) : '';

			// Verify nonce for saving the order
			if (!empty($save_order_nonce) && !wp_verify_nonce($save_order_nonce, 'woocommerce-save_order')) {
				wp_die(esc_html__('Nonce verification failed for saving order', 'wompi-portal-de-pagos'));
			}

			// Verify nonce for updating order item metadata
			if (!empty($order_item_nonce) && !wp_verify_nonce($order_item_nonce, 'woocommerce_order_item_nonce')) {
				wp_die(esc_html__('Nonce verification failed for updating order item', 'wompi-portal-de-pagos'));
			}

			// Verify nonce for adding an order note
			if (!empty($add_order_note_nonce) && !wp_verify_nonce($add_order_note_nonce, 'add-order-note')) {
				wp_die(esc_html__('Nonce verification failed for adding order note', 'wompi-portal-de-pagos'));
			}

			// Verify nonce for deleting an order item
			if (!empty($delete_order_item_nonce) && !wp_verify_nonce($delete_order_item_nonce, 'delete-order-item')) {
				wp_die(esc_html__('Nonce verification failed for deleting order item', 'wompi-portal-de-pagos'));
			}
		}

		if ('wc-voided' == $order_status) {
			$order  = new WC_Order($order_id);
			$status = $order->get_status();

			if ('completed' != $status || !self::check_voided_access($order, $status) || !$this->order_void($order)) {
				$order->add_order_note(__('Unable to change status to Voided.', 'wompi-portal-de-pagos'));
				WompiPortalPagos_Logger::log('Unable to change status to Voided.');
				$_POST['order_status'] = sanitize_text_field(wp_unslash('wc-' . $status));
			}
		}
	}

	/**
	 * On order void
	 */
	public function order_void($order) {
		// API Void
		if (Wompi_Portal_Pagos_API::instance()->transaction_void($order->get_transaction_id())) {
			// Gateway process
			WompiPortalPagos_Gateway::process_void($order);
			return true;
		}
		return false;
	}
}

new Wompi_Portal_Pagos_Order_Statuses();
