<?php
/**
 * Integración checkout Centinela (carrito API / localStorage) con Wompi.
 * Crea un pedido WooCommerce desde el formulario de finalizar-compra y redirige a la página de pago Wompi.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Obtener o crear el producto placeholder de WooCommerce para líneas de la tienda API.
 * Se usa un único producto "Producto tienda (API)" para todas las líneas; el nombre y total se sobrescriben por línea.
 *
 * @return WC_Product|null Producto placeholder o null si WooCommerce no está activo.
 */
function centinela_get_or_create_tienda_placeholder_product() {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
		return null;
	}
	$option_key = 'centinela_tienda_placeholder_product_id';
	$product_id = (int) get_option( $option_key, 0 );
	if ( $product_id > 0 ) {
		$product = wc_get_product( $product_id );
		if ( $product && $product->exists() ) {
			return $product;
		}
	}
	// Crear producto placeholder: simple, virtual, no visible en tienda, precio 0.
	$product = new WC_Product_Simple();
	$product->set_name( __( 'Producto tienda (API)', 'centinela-group-theme' ) );
	$product->set_status( 'private' );
	$product->set_catalog_visibility( 'hidden' );
	$product->set_virtual( true );
	$product->set_price( 0 );
	$product->set_regular_price( 0 );
	$product->save();
	$new_id = $product->get_id();
	if ( $new_id > 0 ) {
		update_option( $option_key, $new_id );
		return $product;
	}
	return null;
}

/**
 * Crear pedido WooCommerce desde datos del checkout custom (ítems + formulario) y devolver URL de pago.
 *
 * @param array $items   Ítems del carrito: cada uno con id, qty, title, price (y opcional image).
 * @param array $form    Datos del formulario: centinela_nombre, centinela_email, centinela_telefono, dirección, etc.
 * @return array { 'success' => bool, 'redirect' => string|null, 'message' => string }
 */
function centinela_checkout_create_wc_order( $items, $form ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_order' ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'WooCommerce no está disponible.', 'centinela-group-theme' ) );
	}

	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( empty( $gateways['wompi'] ) || ! $gateways['wompi']->is_available() ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'El método de pago Wompi no está disponible.', 'centinela-group-theme' ) );
	}

	$placeholder = centinela_get_or_create_tienda_placeholder_product();
	if ( ! $placeholder ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'No se pudo crear el pedido. Contacte al administrador.', 'centinela-group-theme' ) );
	}

	$items = is_array( $items ) ? $items : array();
	if ( empty( $items ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'El carrito está vacío.', 'centinela-group-theme' ) );
	}

	$nombre   = isset( $form['centinela_nombre'] ) ? sanitize_text_field( $form['centinela_nombre'] ) : '';
	$email    = isset( $form['centinela_email'] ) ? sanitize_email( $form['centinela_email'] ) : '';
	$telefono = isset( $form['centinela_telefono'] ) ? sanitize_text_field( $form['centinela_telefono'] ) : '';
	$direccion = isset( $form['centinela_direccion'] ) ? sanitize_text_field( $form['centinela_direccion'] ) : '';
	$complemento = isset( $form['centinela_complemento'] ) ? sanitize_text_field( $form['centinela_complemento'] ) : '';
	$ciudad   = isset( $form['centinela_ciudad'] ) ? sanitize_text_field( $form['centinela_ciudad'] ) : '';
	$departamento = isset( $form['centinela_departamento'] ) ? sanitize_text_field( $form['centinela_departamento'] ) : '';
	$codigo_postal = isset( $form['centinela_codigo_postal'] ) ? sanitize_text_field( $form['centinela_codigo_postal'] ) : '';
	$pais     = isset( $form['centinela_pais'] ) ? sanitize_text_field( $form['centinela_pais'] ) : 'Colombia';
	$notas    = isset( $form['centinela_notas'] ) ? sanitize_textarea_field( $form['centinela_notas'] ) : '';

	if ( $nombre === '' || $email === '' || $direccion === '' || $ciudad === '' || $departamento === '' ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'Faltan datos obligatorios de contacto o dirección.', 'centinela-group-theme' ) );
	}

	if ( ! is_email( $email ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'Correo electrónico no válido.', 'centinela-group-theme' ) );
	}

	try {
		$order = wc_create_order( array( 'status' => 'pending' ) );
		if ( ! $order || ! ( $order instanceof WC_Order ) ) {
			return array( 'success' => false, 'redirect' => null, 'message' => __( 'No se pudo crear el pedido.', 'centinela-group-theme' ) );
		}

		$order->set_payment_method( 'wompi' );
		$order->set_billing_first_name( $nombre );
		$order->set_billing_last_name( '' );
		$order->set_billing_email( $email );
		$order->set_billing_phone( $telefono );
		$order->set_billing_country( 'CO' );
		$order->set_billing_address_1( $direccion );
		$order->set_billing_address_2( $complemento );
		$order->set_billing_city( $ciudad );
		$order->set_billing_state( $departamento );
		$order->set_billing_postcode( $codigo_postal );

		$order->set_shipping_first_name( $nombre );
		$order->set_shipping_last_name( '' );
		$order->set_shipping_country( 'CO' );
		$order->set_shipping_address_1( $direccion );
		$order->set_shipping_address_2( $complemento );
		$order->set_shipping_city( $ciudad );
		$order->set_shipping_state( $departamento );
		$order->set_shipping_postcode( $codigo_postal );

		if ( $notas !== '' ) {
			$order->set_customer_note( $notas );
		}

		$order_total = 0.0;
		foreach ( $items as $item ) {
			$qty   = max( 1, (int) ( isset( $item['qty'] ) ? $item['qty'] : 1 ) );
			$price = isset( $item['price'] ) ? centinela_parse_precio_api( $item['price'] ) : 0.0;
			$total = $price * $qty;
			$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : ( 'Producto #' . ( isset( $item['id'] ) ? $item['id'] : '' ) );
			$product_id_api = isset( $item['id'] ) ? $item['id'] : '';

			$order->add_product( $placeholder, $qty, array(
				'subtotal' => $total,
				'total'    => $total,
			) );
			$order_items = $order->get_items();
			$last        = end( $order_items );
			if ( $last ) {
				$last->set_name( $title );
				if ( $product_id_api !== '' ) {
					$last->add_meta_data( '_centinela_producto_id', $product_id_api, true );
				}
				$last->save();
			}
			$order_total += $total;
		}

		$order->calculate_totals();
		$order->save();

		$redirect = $order->get_checkout_payment_url( true );
		if ( function_exists( 'centinela_force_http_on_localhost' ) ) {
			$redirect = centinela_force_http_on_localhost( $redirect );
		}

		return array( 'success' => true, 'redirect' => $redirect, 'message' => '' );
	} catch ( Exception $e ) {
		return array(
			'success'  => false,
			'redirect' => null,
			'message'  => __( 'Error al crear el pedido. Intente de nuevo.', 'centinela-group-theme' ),
		);
	}
}

/**
 * Registrar ruta REST para crear pedido y redirigir a Wompi.
 */
function centinela_checkout_wompi_rest_routes() {
	register_rest_route( 'centinela/v1', '/checkout-create-order', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'args'                => array(
			'items'         => array(
				'required' => true,
				'type'     => 'array',
				'items'    => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array( 'type' => array( 'string', 'integer' ) ),
						'qty'   => array( 'type' => array( 'string', 'integer' ) ),
						'title' => array( 'type' => 'string' ),
						'price' => array( 'type' => array( 'string', 'number' ) ),
					),
				),
			),
			'centinela_nombre'       => array( 'type' => 'string' ),
			'centinela_email'        => array( 'type' => 'string' ),
			'centinela_telefono'     => array( 'type' => 'string' ),
			'centinela_direccion'    => array( 'type' => 'string' ),
			'centinela_complemento'  => array( 'type' => 'string' ),
			'centinela_ciudad'       => array( 'type' => 'string' ),
			'centinela_departamento' => array( 'type' => 'string' ),
			'centinela_codigo_postal'=> array( 'type' => 'string' ),
			'centinela_pais'         => array( 'type' => 'string' ),
			'centinela_notas'        => array( 'type' => 'string' ),
		),
		'callback' => function ( WP_REST_Request $request ) {
			$items = $request->get_param( 'items' );
			$form  = array(
				'centinela_nombre'        => $request->get_param( 'centinela_nombre' ),
				'centinela_email'         => $request->get_param( 'centinela_email' ),
				'centinela_telefono'      => $request->get_param( 'centinela_telefono' ),
				'centinela_direccion'     => $request->get_param( 'centinela_direccion' ),
				'centinela_complemento'   => $request->get_param( 'centinela_complemento' ),
				'centinela_ciudad'        => $request->get_param( 'centinela_ciudad' ),
				'centinela_departamento'  => $request->get_param( 'centinela_departamento' ),
				'centinela_codigo_postal' => $request->get_param( 'centinela_codigo_postal' ),
				'centinela_pais'          => $request->get_param( 'centinela_pais' ),
				'centinela_notas'         => $request->get_param( 'centinela_notas' ),
			);

			$result = centinela_checkout_create_wc_order( $items, $form );

			if ( $result['success'] ) {
				return new WP_REST_Response( array(
					'success'  => true,
					'redirect' => $result['redirect'],
				), 200 );
			}

			return new WP_REST_Response( array(
				'success' => false,
				'message' => $result['message'],
			), 400 );
		},
	) );
}
add_action( 'rest_api_init', 'centinela_checkout_wompi_rest_routes' );

/**
 * Comprobar si Wompi está disponible como pasarela para el checkout del tema.
 *
 * @return bool
 */
function centinela_checkout_wompi_available() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return false;
	}
	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	return ! empty( $gateways['wompi'] ) && $gateways['wompi']->is_available();
}
