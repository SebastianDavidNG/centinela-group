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
 * Diagnóstico básico de configuración Wompi para evitar links inválidos.
 *
 * @return array{ok:bool,message:string}
 */
function centinela_checkout_wompi_config_diagnostic() {
	$settings = get_option( 'woocommerce_wompi_settings', array() );
	$settings = is_array( $settings ) ? $settings : array();
	$testmode = isset( $settings['testmode'] ) && $settings['testmode'] === 'yes';
	$pub      = trim( (string) ( $testmode ? ( $settings['test_public_key'] ?? '' ) : ( $settings['public_key'] ?? '' ) ) );
	$priv     = trim( (string) ( $testmode ? ( $settings['test_private_key'] ?? '' ) : ( $settings['private_key'] ?? '' ) ) );
	$host     = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$is_local = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );

	if ( $pub === '' || $priv === '' ) {
		return array(
			'ok'      => false,
			'message' => __( 'Wompi no está configurado correctamente (faltan llaves pública/privada).', 'centinela-group-theme' ),
		);
	}
	if ( $is_local && ! $testmode ) {
		return array(
			'ok'      => false,
			'message' => __( 'En localhost debes usar Wompi en modo pruebas (sandbox). Activa Test mode y usa llaves pub_test/prv_test.', 'centinela-group-theme' ),
		);
	}
	if ( $testmode && strpos( $pub, 'pub_test_' ) !== 0 ) {
		return array(
			'ok'      => false,
			'message' => __( 'Wompi está en modo pruebas pero la llave pública no es de sandbox (pub_test_...).', 'centinela-group-theme' ),
		);
	}
	if ( ! $testmode && strpos( $pub, 'pub_prod_' ) !== 0 ) {
		return array(
			'ok'      => false,
			'message' => __( 'Wompi está en producción pero la llave pública no es de producción (pub_prod_...).', 'centinela-group-theme' ),
		);
	}

	return array( 'ok' => true, 'message' => '' );
}

/**
 * Agrega un fee no gravable a una orden WooCommerce.
 *
 * @param WC_Order $order  Orden destino.
 * @param string   $name   Etiqueta del fee.
 * @param float    $amount Valor (positivo o negativo).
 * @return void
 */
function centinela_checkout_add_order_fee( WC_Order $order, $name, $amount ) {
	$amount = (float) $amount;
	if ( abs( $amount ) < 0.00001 ) {
		return;
	}
	$fee = new WC_Order_Item_Fee();
	$fee->set_name( (string) $name );
	$fee->set_amount( $amount );
	$fee->set_total( $amount );
	$fee->set_tax_status( 'none' );
	$order->add_item( $fee );
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
 * @return array { 'success' => bool, 'redirect' => string|null, 'message' => string, 'order_id' => int }
 */
function centinela_checkout_create_wc_order( $items, $form ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_create_order' ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'WooCommerce no está disponible.', 'centinela-group-theme' ), 'order_id' => 0 );
	}
	$diag = centinela_checkout_wompi_config_diagnostic();
	if ( empty( $diag['ok'] ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => (string) $diag['message'], 'order_id' => 0 );
	}

	$gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( empty( $gateways['wompi'] ) || ! $gateways['wompi']->is_available() ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'El método de pago Wompi no está disponible.', 'centinela-group-theme' ), 'order_id' => 0 );
	}

	$placeholder = centinela_get_or_create_tienda_placeholder_product();
	if ( ! $placeholder ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'No se pudo crear el pedido. Contacte al administrador.', 'centinela-group-theme' ), 'order_id' => 0 );
	}

	$items = is_array( $items ) ? $items : array();
	if ( empty( $items ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'El carrito está vacío.', 'centinela-group-theme' ), 'order_id' => 0 );
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
	$cot_moneda   = isset( $form['centinela_cot_moneda'] ) ? strtoupper( trim( (string) $form['centinela_cot_moneda'] ) ) : 'COP';
	$cot_subtotal = isset( $form['centinela_cot_subtotal'] ) ? (float) $form['centinela_cot_subtotal'] : 0.0;
	$cot_iva      = isset( $form['centinela_cot_iva_valor'] ) ? (float) $form['centinela_cot_iva_valor'] : 0.0;
	$cot_total    = isset( $form['centinela_cot_total'] ) ? (float) $form['centinela_cot_total'] : 0.0;
	$cot_iva_pct  = isset( $form['centinela_cot_iva_pct'] ) ? (float) $form['centinela_cot_iva_pct'] : 0.0;

	if ( $nombre === '' || $email === '' || $direccion === '' || $ciudad === '' || $departamento === '' ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'Faltan datos obligatorios de contacto o dirección.', 'centinela-group-theme' ), 'order_id' => 0 );
	}

	if ( ! is_email( $email ) ) {
		return array( 'success' => false, 'redirect' => null, 'message' => __( 'Correo electrónico no válido.', 'centinela-group-theme' ), 'order_id' => 0 );
	}

	try {
		$order = wc_create_order( array( 'status' => 'pending' ) );
		if ( ! $order || ! ( $order instanceof WC_Order ) ) {
			return array( 'success' => false, 'redirect' => null, 'message' => __( 'No se pudo crear el pedido.', 'centinela-group-theme' ), 'order_id' => 0 );
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
			$qty     = max( 1, (int) ( isset( $item['qty'] ) ? $item['qty'] : 1 ) );
			$is_wc   = isset( $item['source'] ) && $item['source'] === 'wc';
			$item_id = isset( $item['id'] ) ? $item['id'] : '';

			if ( $is_wc && $item_id !== '' && function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( (int) $item_id );
				if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
					$order->add_product( $product, $qty );
					$order_total += (float) $product->get_price() * $qty;
					continue;
				}
			}

			// Syscom (API): línea con producto placeholder.
			$price         = isset( $item['price'] ) ? centinela_parse_precio_api( $item['price'] ) : 0.0;
			$total         = $price * $qty;
			$title         = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : ( 'Producto #' . $item_id );

			$order->add_product( $placeholder, $qty, array(
				'subtotal' => $total,
				'total'    => $total,
			) );
			$order_items = $order->get_items();
			$last        = end( $order_items );
			if ( $last ) {
				$last->set_name( $title );
				if ( $item_id !== '' ) {
					$last->add_meta_data( '_centinela_producto_id', $item_id, true );
				}
				$last->save();
			}
			$order_total += $total;
		}
		// Para links de pago desde cotizador en COP: forzar total de orden = total cotizado.
		$fees_total = 0.0;
		if ( $cot_moneda === 'COP' && $cot_total > 0 ) {
			if ( $cot_iva > 0 ) {
				$iva_label = $cot_iva_pct > 0 ? sprintf( 'I.V.A. (%.2f%%)', $cot_iva_pct ) : 'I.V.A.';
				centinela_checkout_add_order_fee( $order, $iva_label, $cot_iva );
				$fees_total += $cot_iva;
			}
			$expected_total = $cot_total;
			$current_total  = $order_total + $fees_total;
			$delta          = $expected_total - $current_total;
			if ( abs( $delta ) >= 0.01 ) {
				centinela_checkout_add_order_fee(
					$order,
					$delta > 0 ? 'Ajuste cotización' : 'Descuento cotización',
					$delta
				);
				$fees_total += $delta;
			}
			$order->update_meta_data( '_centinela_cot_subtotal', $cot_subtotal );
			$order->update_meta_data( '_centinela_cot_iva_valor', $cot_iva );
			$order->update_meta_data( '_centinela_cot_total', $cot_total );
		}
		// Validación equivalente al plugin de Wompi: mínimo COP 1.500 (150.000 centavos).
		$currency = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : get_woocommerce_currency();
		$amount_in_cents = (int) round( ( $order_total + $fees_total ) * 100 );
		if ( strtoupper( $currency ) === 'COP' && $amount_in_cents < 150000 ) {
			$order_id_to_delete = (int) $order->get_id();
			if ( $order_id_to_delete > 0 ) {
				wp_delete_post( $order_id_to_delete, true );
			}
			return array(
				'success'  => false,
				'redirect' => null,
				'message'  => __( 'El total mínimo para pagar con Wompi es COP 1.500.', 'centinela-group-theme' ),
				'order_id' => 0,
			);
		}

		$order->calculate_totals();
		$order->save();

		$redirect = '';
		// Preferir la URL que devuelve la pasarela para evitar caer en pantallas de checkout custom vacías.
		$gateway = isset( $gateways['wompi'] ) ? $gateways['wompi'] : null;
		if ( $gateway && is_object( $gateway ) && method_exists( $gateway, 'process_payment' ) ) {
			try {
				$payment_result = $gateway->process_payment( $order->get_id() );
				if ( is_array( $payment_result ) && isset( $payment_result['result'] ) && $payment_result['result'] === 'success' && ! empty( $payment_result['redirect'] ) ) {
					$redirect = (string) $payment_result['redirect'];
				}
			} catch ( Exception $e ) {
				$redirect = '';
			}
		}
		if ( $redirect === '' ) {
			$redirect = $order->get_checkout_payment_url( true );
		}
		if ( function_exists( 'centinela_force_http_on_localhost' ) ) {
			$redirect = centinela_force_http_on_localhost( $redirect );
		}

		return array( 'success' => true, 'redirect' => $redirect, 'message' => '', 'order_id' => (int) $order->get_id() );
	} catch ( Exception $e ) {
		return array(
			'success'  => false,
			'redirect' => null,
			'message'  => __( 'Error al crear el pedido. Intente de nuevo.', 'centinela-group-theme' ),
			'order_id' => 0,
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
						'id'          => array( 'type' => array( 'string', 'integer' ) ),
						'qty'         => array( 'type' => array( 'string', 'integer' ) ),
						'title'       => array( 'type' => 'string' ),
						'price'       => array( 'type' => array( 'string', 'number' ) ),
						'source'      => array( 'type' => 'string' ),
						'product_url' => array( 'type' => 'string' ),
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
			'centinela_cot_moneda'   => array( 'type' => 'string' ),
			'centinela_cot_subtotal' => array( 'type' => array( 'string', 'number' ) ),
			'centinela_cot_iva_valor'=> array( 'type' => array( 'string', 'number' ) ),
			'centinela_cot_total'    => array( 'type' => array( 'string', 'number' ) ),
			'centinela_cot_iva_pct'  => array( 'type' => array( 'string', 'number' ) ),
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
				'centinela_cot_moneda'    => $request->get_param( 'centinela_cot_moneda' ),
				'centinela_cot_subtotal'  => $request->get_param( 'centinela_cot_subtotal' ),
				'centinela_cot_iva_valor' => $request->get_param( 'centinela_cot_iva_valor' ),
				'centinela_cot_total'     => $request->get_param( 'centinela_cot_total' ),
				'centinela_cot_iva_pct'   => $request->get_param( 'centinela_cot_iva_pct' ),
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
