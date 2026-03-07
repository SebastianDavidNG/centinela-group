<?php
/**
 * Cotizador - Menú y página en el Admin de WordPress
 *
 * Campos: Título de la Cotización, Listado de Productos (buscar por Modelo/Título vía API Syscom),
 * filas editables: Modelo, Cantidad, Descuento, Precio, Importe; eliminar producto.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar CPT para cotizaciones guardadas
 */
function centinela_cotizador_register_cpt() {
	register_post_type( 'cotizacion', array(
		'labels'             => array(
			'name'               => __( 'Cotizaciones', 'centinela-group-theme' ),
			'singular_name'      => __( 'Cotización', 'centinela-group-theme' ),
			'menu_name'          => __( 'Cotizaciones', 'centinela-group-theme' ),
			'add_new'            => __( 'Añadir nueva', 'centinela-group-theme' ),
			'add_new_item'       => __( 'Añadir nueva cotización', 'centinela-group-theme' ),
			'edit_item'          => __( 'Editar cotización', 'centinela-group-theme' ),
			'new_item'           => __( 'Nueva cotización', 'centinela-group-theme' ),
			'view_item'          => __( 'Ver cotización', 'centinela-group-theme' ),
			'search_items'       => __( 'Buscar cotizaciones', 'centinela-group-theme' ),
			'not_found'          => __( 'No hay cotizaciones.', 'centinela-group-theme' ),
			'not_found_in_trash' => __( 'No hay cotizaciones en la papelera.', 'centinela-group-theme' ),
		),
		'public'             => false,
		'show_ui'            => false,
		'show_in_menu'       => false,
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'supports'            => array( 'title' ),
		'has_archive'        => false,
		'rewrite'            => false,
	) );
}
add_action( 'init', 'centinela_cotizador_register_cpt' );

/**
 * Generar cuerpo del correo en HTML optimizado para email (con datos de la cotización)
 *
 * @param array $datos Datos de la cotización (titulo, productos, cliente, moneda, etc.).
 * @return string HTML del cuerpo del correo.
 */
function centinela_cotizador_build_email_html( $datos ) {
	$titulo_cot  = isset( $datos['titulo'] ) ? esc_html( $datos['titulo'] ) : '';
	$productos   = isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array();
	$cliente     = isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$contacto    = isset( $datos['contacto'] ) && is_array( $datos['contacto'] ) ? $datos['contacto'] : array();
	$moneda      = isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP';
	$simbolo     = $moneda === 'USD' ? 'USD $' : 'CO $';
	$subtotal    = isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0;
	$iva_valor   = isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0;
	$total       = isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
	$iva_pct     = isset( $datos['iva_pct'] ) ? floatval( $datos['iva_pct'] ) : 19;
	$tipo_cambio = isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0;

	$nombre_cliente   = isset( $cliente['nombre'] ) ? esc_html( $cliente['nombre'] ) : '';
	$email_cliente    = isset( $cliente['email'] ) ? esc_html( $cliente['email'] ) : '';
	$vigencia         = isset( $cliente['vigencia'] ) ? esc_html( $cliente['vigencia'] ) : '';
	$comentarios      = isset( $cliente['comentarios'] ) ? esc_html( $cliente['comentarios'] ) : '';
	$nombre_asesor    = isset( $contacto['nombre'] ) ? esc_html( $contacto['nombre'] ) : '';
	$email_asesor     = isset( $contacto['email'] ) ? esc_html( $contacto['email'] ) : '';
	$telefono_asesor  = isset( $contacto['telefono'] ) ? esc_html( $contacto['telefono'] ) : '';
	$metodo_pago_key  = isset( $contacto['metodo_pago'] ) ? $contacto['metodo_pago'] : '';
	$metodo_pago_labels = array(
		'tarjeta_credito'           => __( 'Tarjeta de Crédito', 'centinela-group-theme' ),
		'tarjeta_debito'            => __( 'Tarjeta de Débito', 'centinela-group-theme' ),
		'contado'                   => __( 'Contado', 'centinela-group-theme' ),
		'cheque_nominativo'         => __( 'Cheque Nominativo', 'centinela-group-theme' ),
		'transferencia_electronica' => __( 'Transferencia electrónica', 'centinela-group-theme' ),
		'pago_a_credito'            => __( 'Pago a crédito', 'centinela-group-theme' ),
	);
	$forma_pago_texto = isset( $metodo_pago_labels[ $metodo_pago_key ] ) ? $metodo_pago_labels[ $metodo_pago_key ] : $metodo_pago_key;

	$rows = '';
	foreach ( $productos as $p ) {
		$modelo    = isset( $p['modelo'] ) ? esc_html( $p['modelo'] ) : '';
		$cantidad  = isset( $p['cantidad'] ) ? (int) $p['cantidad'] : 0;
		$descuento = isset( $p['descuento'] ) ? floatval( $p['descuento'] ) : 0;
		$precio    = isset( $p['precio'] ) ? floatval( $p['precio'] ) : 0;
		$importe   = isset( $p['importe'] ) ? floatval( $p['importe'] ) : ( $cantidad * $precio * ( 1 - $descuento / 100 ) );
		$rows .= '<tr>';
		$rows .= '<td style="padding:8px;border:1px solid #ddd;">' . $modelo . '</td>';
		$rows .= '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . $cantidad . '</td>';
		$rows .= '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' . number_format( $precio, 2, ',', '.' ) . '</td>';
		$rows .= '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' . number_format( $importe, 2, ',', '.' ) . '</td>';
		$rows .= '</tr>';
	}

	$logo_url = isset( $datos['logo_url'] ) ? trim( $datos['logo_url'] ) : '';
	if ( $logo_url !== '' && preg_match( '#^https?://#i', $logo_url ) ) {
		$logo_url = esc_url( $logo_url );
	} elseif ( $logo_url !== '' ) {
		$logo_url = esc_url( home_url( $logo_url ) );
	}

	$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#333;max-width:600px;margin:0 auto;padding:20px;">';

	// Logo centrado con separado
	if ( $logo_url !== '' ) {
		$html .= '<div style="text-align:center;margin-bottom:1.5em;"><img src="' . $logo_url . '" alt="Logo" style="max-width:220px;height:auto;" /></div>';
	}

	// Título "Cotización" centrado
	$html .= '<h1 style="font-size:1.5em;margin:0 0 0.5em;text-align:center;">' . esc_html__( 'Cotización', 'centinela-group-theme' ) . '</h1>';
	if ( $titulo_cot !== '' ) {
		$html .= '<p style="text-align:center;margin:0 0 0.5em;font-weight:600;">' . $titulo_cot . '</p>';
	}
	// Número de cotización (izquierda, encima de Datos del cliente)
	$numero_cot = isset( $datos['numero'] ) ? $datos['numero'] : '';
	if ( $numero_cot !== '' ) {
		$html .= '<p style="margin:0 0 1em;text-align:left;font-size:13px;"><strong>' . esc_html__( 'Cotización', 'centinela-group-theme' ) . ' #' . esc_html( $numero_cot ) . '</strong></p>';
	}

	// Dos columnas: Datos del cliente (izq) | Datos del Asesor (der)
	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:1.25em;" cellpadding="0" cellspacing="0">';
	$html .= '<tr><td style="width:50%;vertical-align:top;padding:12px;border:1px solid #ddd;background:#f9f9f9;">';
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;font-size:13px;">' . esc_html__( 'Datos del cliente', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . ( $nombre_cliente !== '' ? $nombre_cliente : '—' ) . '</p>';
	$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . ( $email_cliente !== '' ? $email_cliente : '—' ) . '</p>';
	if ( $vigencia !== '' ) {
		$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . esc_html__( 'Vigencia:', 'centinela-group-theme' ) . ' ' . $vigencia . '</p>';
	}
	if ( $comentarios !== '' ) {
		$html .= '<p style="margin:0.5em 0 0;font-size:12px;color:#555;">' . $comentarios . '</p>';
	}
	$html .= '</td><td style="width:50%;vertical-align:top;padding:12px;border:1px solid #ddd;border-left:0;background:#f9f9f9;">';
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;font-size:13px;">' . esc_html__( 'Datos del Asesor', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . ( $nombre_asesor !== '' ? $nombre_asesor : '—' ) . '</p>';
	$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . ( $email_asesor !== '' ? $email_asesor : '—' ) . '</p>';
	$html .= '<p style="margin:0 0 0.35em;font-size:13px;">' . ( $telefono_asesor !== '' ? $telefono_asesor : '—' ) . '</p>';
	$html .= '</td></tr></table>';

	// Forma de pago, Tipo de cambio, Vigencia
	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:1.5em;font-size:13px;" cellpadding="0" cellspacing="0">';
	$html .= '<tr style="background:#f0f0f0;"><td style="padding:8px 10px;border:1px solid #ddd;width:33%;"><strong>' . esc_html__( 'Forma de pago', 'centinela-group-theme' ) . '</strong></td><td style="padding:8px 10px;border:1px solid #ddd;width:33%;"><strong>' . esc_html__( 'Tipo de cambio', 'centinela-group-theme' ) . '</strong></td><td style="padding:8px 10px;border:1px solid #ddd;width:34%;"><strong>' . esc_html__( 'Vigencia', 'centinela-group-theme' ) . '</strong></td></tr>';
	$html .= '<tr><td style="padding:8px 10px;border:1px solid #ddd;">' . esc_html( $forma_pago_texto ) . '</td><td style="padding:8px 10px;border:1px solid #ddd;">' . ( $tipo_cambio > 0 ? '1 USD = ' . number_format( $tipo_cambio, 2, ',', '.' ) . ' COP' : '—' ) . '</td><td style="padding:8px 10px;border:1px solid #ddd;">' . ( $vigencia !== '' ? $vigencia : '—' ) . '</td></tr>';
	$html .= '</table>';

	// Tabla de productos
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;">' . esc_html__( 'Detalle de productos cotizados', 'centinela-group-theme' ) . '</p>';
	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:1.5em;">';
	$html .= '<thead><tr style="background:#f0f0f0;">';
	$html .= '<th style="padding:8px;border:1px solid #ddd;text-align:left;">' . esc_html__( 'Modelo', 'centinela-group-theme' ) . '</th>';
	$html .= '<th style="padding:8px;border:1px solid #ddd;text-align:center;">' . esc_html__( 'Cantidad', 'centinela-group-theme' ) . '</th>';
	$html .= '<th style="padding:8px;border:1px solid #ddd;text-align:right;">' . esc_html__( 'Precio', 'centinela-group-theme' ) . '</th>';
	$html .= '<th style="padding:8px;border:1px solid #ddd;text-align:right;">' . esc_html__( 'Importe', 'centinela-group-theme' ) . '</th>';
	$html .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

	$html .= '<p style="text-align:right;margin:0.25em 0;"><strong>' . esc_html__( 'Subtotal', 'centinela-group-theme' ) . ':</strong> ' . $simbolo . ' ' . number_format( $subtotal, 2, ',', '.' ) . '</p>';
	$html .= '<p style="text-align:right;margin:0.25em 0;">' . esc_html__( 'I.V.A.', 'centinela-group-theme' ) . ' (' . $iva_pct . '%): ' . $simbolo . ' ' . number_format( $iva_valor, 2, ',', '.' ) . '</p>';
	$html .= '<p style="text-align:right;margin:0.5em 0 1em;font-size:1.2em;"><strong>' . esc_html__( 'TOTAL', 'centinela-group-theme' ) . ': ' . $simbolo . ' ' . number_format( $total, 2, ',', '.' ) . '</strong></p>';
	$html .= '<p style="margin-top:1.5em;color:#666;font-size:12px;">' . esc_html__( 'Este correo está siendo enviado por CENTINELA GROUP SAS. Adjunto encontrará la cotización en formato (PDF o Excel).', 'centinela-group-theme' ) . '</p>';
	$html .= '</body></html>';

	return apply_filters( 'centinela_cotizador_email_html', $html, $datos );
}

/**
 * Guardar o actualizar cotización como post tipo cotizacion
 *
 * @param array    $datos     Datos de la cotización (titulo, productos, cliente, contacto, moneda, etc.).
 * @param int|null $editar_id ID del post a actualizar; null para crear uno nuevo.
 * @return int|WP_Error ID del post o error.
 */
function centinela_cotizador_save_cotizacion( $datos, $editar_id = null ) {
	$titulo = isset( $datos['titulo'] ) ? sanitize_text_field( $datos['titulo'] ) : '';
	if ( $titulo === '' ) {
		$titulo = __( 'Cotización sin título', 'centinela-group-theme' ) . ' ' . current_time( 'Y-m-d H:i' );
	}
	$post_id = null;
	if ( $editar_id > 0 ) {
		$post = get_post( $editar_id );
		if ( $post && $post->post_type === 'cotizacion' && $post->post_status === 'publish' ) {
			$post_id = (int) $editar_id;
			wp_update_post( array(
				'ID'         => $post_id,
				'post_title' => $titulo,
			) );
			// Si la cotización no tenía número (creada antes de esta función), asignar uno.
			if ( get_post_meta( $post_id, '_cotizacion_numero', true ) === '' ) {
				$numero = (int) get_option( 'centinela_cotizador_ultimo_numero', 0 ) + 1;
				update_option( 'centinela_cotizador_ultimo_numero', $numero );
				update_post_meta( $post_id, '_cotizacion_numero', $numero );
			}
		}
	}
	if ( $post_id === null ) {
		$post_data = array(
			'post_type'   => 'cotizacion',
			'post_title'  => $titulo,
			'post_status' => 'publish',
			'post_author' => get_current_user_id(),
		);
		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}
		// Asignar número único de cotización (secuencial).
		$numero = (int) get_option( 'centinela_cotizador_ultimo_numero', 0 ) + 1;
		update_option( 'centinela_cotizador_ultimo_numero', $numero );
		update_post_meta( $post_id, '_cotizacion_numero', $numero );
	}
	$datos_safe = array(
		'titulo'        => $titulo,
		'productos'     => isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array(),
		'cliente'       => isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array(),
		'contacto'      => isset( $datos['contacto'] ) && is_array( $datos['contacto'] ) ? $datos['contacto'] : array(),
		'moneda'        => isset( $datos['moneda'] ) ? sanitize_text_field( $datos['moneda'] ) : 'COP',
		'tipo_cambio'   => isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0,
		'tipo_precio'   => isset( $datos['tipo_precio'] ) ? sanitize_text_field( $datos['tipo_precio'] ) : 'lista',
		'iva_pct'       => isset( $datos['iva_pct'] ) ? floatval( $datos['iva_pct'] ) : 19,
		'subtotal'      => isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0,
		'iva_valor'     => isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0,
		'total'         => isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0,
		'logo_url'      => isset( $datos['logo_url'] ) ? esc_url_raw( trim( $datos['logo_url'] ) ) : '',
	);
	update_post_meta( $post_id, '_cotizacion_datos', $datos_safe );
	return $post_id;
}

/**
 * Registrar el menú "Cotizador" y submenú "Mis Cotizaciones" en el admin
 */
function centinela_cotizador_register_menu() {
	add_menu_page(
		__( 'Cotizador', 'centinela-group-theme' ),
		__( 'Cotizador', 'centinela-group-theme' ),
		'manage_options',
		'centinela-cotizador',
		'centinela_cotizador_render_page',
		'dashicons-calculator',
		56
	);
	add_submenu_page(
		'centinela-cotizador',
		__( 'Mis Cotizaciones', 'centinela-group-theme' ),
		__( 'Mis Cotizaciones', 'centinela-group-theme' ),
		'manage_options',
		'centinela-cotizador-mis-cotizaciones',
		'centinela_cotizador_render_mis_cotizaciones'
	);
}
add_action( 'admin_menu', 'centinela_cotizador_register_menu' );

/**
 * Encolar estilos y script solo en la página del Cotizador
 */
function centinela_cotizador_enqueue_assets( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_centinela-cotizador' ) {
		return;
	}
	wp_enqueue_style(
		'centinela-cotizador-admin',
		get_template_directory_uri() . '/assets/css/cotizador-admin.css',
		array(),
		defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0'
	);
	wp_enqueue_media();
	wp_enqueue_script(
		'centinela-cotizador-admin',
		get_template_directory_uri() . '/assets/js/cotizador-admin.js',
		array( 'jquery' ),
		defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0',
		true
	);
	$logo_default_url = '';
	if ( has_custom_logo() ) {
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$logo_default_url = wp_get_attachment_image_url( $logo_id, 'full' );
		}
	}
	$cotizacion_editar = null;
	$editar_id = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : 0;
	if ( $editar_id > 0 ) {
		$post = get_post( $editar_id );
		if ( $post && $post->post_type === 'cotizacion' && $post->post_status === 'publish' ) {
			$datos = get_post_meta( $editar_id, '_cotizacion_datos', true );
			if ( is_array( $datos ) ) {
				$cotizacion_editar = array( 'id' => $editar_id, 'datos' => $datos );
			}
		}
	}
	wp_localize_script( 'centinela-cotizador-admin', 'centinelaCotizador', array(
		'ajax_url'           => admin_url( 'admin-ajax.php' ),
		'nonce'              => wp_create_nonce( 'centinela_cotizador' ),
		'iva_default'        => 19,
		'logo_default_url'   => $logo_default_url ? $logo_default_url : '',
		'cotizacion_editar'  => $cotizacion_editar,
		'i18n'              => array(
			'buscar_placeholder_modelo' => __( 'Buscar por modelo…', 'centinela-group-theme' ),
			'buscar_placeholder_titulo'  => __( 'Buscar por título…', 'centinela-group-theme' ),
			'sin_resultados'            => __( 'Sin resultados.', 'centinela-group-theme' ),
			'error_busqueda'             => __( 'Error al buscar. Revisa la API Syscom.', 'centinela-group-theme' ),
			'eliminar'                  => __( 'Eliminar', 'centinela-group-theme' ),
			'importe'                   => __( 'Importe', 'centinela-group-theme' ),
			'actualizar_tc'              => __( 'Actualizar tipo de cambio', 'centinela-group-theme' ),
			'enviar_guardar'             => __( 'Enviar y Guardar', 'centinela-group-theme' ),
			'guardar_cotizacion'         => __( 'Guardar Cotización', 'centinela-group-theme' ),
			'enviar_carrito'             => __( 'Enviar al Carrito', 'centinela-group-theme' ),
			'enviar'                      => __( 'Enviar', 'centinela-group-theme' ),
			'guardar_en_moneda'           => __( '¿Desea guardar la cotización en la moneda seleccionada?', 'centinela-group-theme' ),
			'cancelar'                    => __( 'Cancelar', 'centinela-group-theme' ),
			'guardar'                     => __( 'Guardar', 'centinela-group-theme' ),
			'datos_cliente_pago'          => __( 'Datos para el link de pago', 'centinela-group-theme' ),
			'direccion'                   => __( 'Dirección', 'centinela-group-theme' ),
			'ciudad'                      => __( 'Ciudad', 'centinela-group-theme' ),
			'departamento'                => __( 'Departamento', 'centinela-group-theme' ),
			'generar_link'                => __( 'Generar link de pago', 'centinela-group-theme' ),
			'link_copiado'                => __( 'Link copiado al portapapeles.', 'centinela-group-theme' ),
			'agregue_productos'           => __( 'Agregue al menos un producto a la cotización.', 'centinela-group-theme' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'centinela_cotizador_enqueue_assets' );

/**
 * AJAX: buscar productos en API Syscom (por modelo o título)
 */
function centinela_cotizador_ajax_buscar_productos() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$busqueda = isset( $_REQUEST['busqueda'] ) ? trim( sanitize_text_field( wp_unslash( $_REQUEST['busqueda'] ) ) ) : '';
	$tipo     = isset( $_REQUEST['tipo'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['tipo'] ) ) : 'titulo';
	if ( $busqueda === '' || strlen( $busqueda ) < 2 ) {
		wp_send_json_success( array( 'productos' => array() ) );
	}
	if ( ! in_array( $tipo, array( 'modelo', 'titulo' ), true ) ) {
		$tipo = 'titulo';
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		wp_send_json_error( array( 'message' => 'API no disponible' ) );
	}
	// Para la API Syscom: palabras clave separadas por "+" (doc). Si buscan por modelo, enviamos términos separados para ampliar resultados.
	$busqueda_api = $busqueda;
	if ( $tipo === 'modelo' ) {
		$busqueda_api = trim( preg_replace( '/[\s\-_]+/', '+', $busqueda ), '+' );
		if ( $busqueda_api === '' ) {
			$busqueda_api = $busqueda;
		}
	}
	$resp = Centinela_Syscom_API::get_productos( array(
		'busqueda' => $busqueda_api,
		'pagina'   => 1,
		'orden'    => 'relevancia',
		'cop'      => true,
	) );
	if ( is_wp_error( $resp ) ) {
		wp_send_json_error( array( 'message' => $resp->get_error_message() ) );
	}
	$productos_raw = isset( $resp['productos'] ) && is_array( $resp['productos'] ) ? $resp['productos'] : array();
	// Búsqueda por modelo: combinar resultados de varias variantes para que siempre haya sugerencias (ej. "XB" y "XB-ARM-4M").
	if ( $tipo === 'modelo' ) {
		$ids_vistos = array();
		foreach ( $productos_raw as $p ) {
			$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
			if ( $pid !== '' ) {
				$ids_vistos[ (string) $pid ] = true;
			}
		}
		$segmentos = preg_split( '/[\s\-_]+/', $busqueda, 2 );
		$primer    = isset( $segmentos[0] ) ? trim( $segmentos[0] ) : '';
		if ( $primer !== '' ) {
			$resp2 = Centinela_Syscom_API::get_productos( array(
				'busqueda' => $primer,
				'pagina'   => 1,
				'orden'    => 'relevancia',
				'cop'      => true,
			) );
			if ( ! is_wp_error( $resp2 ) && isset( $resp2['productos'] ) && is_array( $resp2['productos'] ) ) {
				foreach ( $resp2['productos'] as $p ) {
					$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
					if ( $pid !== '' && empty( $ids_vistos[ (string) $pid ] ) ) {
						$productos_raw[] = $p;
						$ids_vistos[ (string) $pid ] = true;
					}
				}
			}
		}
		// Si aún no hay nada, intentar con el término completo tal cual (por si la API lo matchea exacto).
		if ( empty( $productos_raw ) && $busqueda !== $busqueda_api ) {
			$resp3 = Centinela_Syscom_API::get_productos( array(
				'busqueda' => $busqueda,
				'pagina'   => 1,
				'orden'    => 'relevancia',
				'cop'      => true,
			) );
			if ( ! is_wp_error( $resp3 ) && isset( $resp3['productos'] ) && is_array( $resp3['productos'] ) ) {
				$productos_raw = $resp3['productos'];
			}
		}
	}
	$productos     = array();
	foreach ( $productos_raw as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$id      = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
		$titulo  = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : '';
		$modelo  = isset( $p['modelo'] ) ? trim( (string) $p['modelo'] ) : '';
		if ( $modelo === '' && isset( $p['sku'] ) ) {
			$modelo = trim( (string) $p['sku'] );
		}
		if ( $modelo === '' && isset( $p['codigo'] ) ) {
			$modelo = trim( (string) $p['codigo'] );
		}
		$precios = isset( $p['precios'] ) && is_array( $p['precios'] ) ? $p['precios'] : array();
		// Precio lista (con IVA): según API Syscom
		$precio_lista_raw = function_exists( 'centinela_get_precio_lista_con_iva' ) ? centinela_get_precio_lista_con_iva( $precios ) : '';
		if ( $precio_lista_raw === '' && isset( $precios['precio_lista'] ) ) {
			$precio_lista_raw = $precios['precio_lista'];
		}
		$precio_lista = function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_lista_raw ) : 0.0;
		// Precio oferta: precio_especial o precio_descuento si existe y es menor que lista
		$precio_oferta_raw = isset( $precios['precio_especial'] ) ? $precios['precio_especial'] : ( isset( $precios['precio_descuento'] ) ? $precios['precio_descuento'] : '' );
		$precio_oferta     = 0.0;
		if ( $precio_oferta_raw !== '' && $precio_oferta_raw !== null ) {
			$precio_oferta = function_exists( 'centinela_parse_precio_api' ) ? centinela_parse_precio_api( $precio_oferta_raw ) : 0.0;
			// Solo considerar oferta si es menor que lista (y mayor que 0)
			if ( $precio_oferta <= 0 || $precio_oferta >= $precio_lista ) {
				$precio_oferta = 0.0;
			}
		}
		// Filtrar por tipo: solo resultados que coincidan en el campo elegido (modelo o título)
		$q = $busqueda;
		if ( $tipo === 'modelo' ) {
			if ( $modelo === '' || stripos( $modelo, $q ) === false ) {
				continue;
			}
		}
		if ( $tipo === 'titulo' ) {
			if ( $titulo === '' || stripos( $titulo, $q ) === false ) {
				continue;
			}
		}
		$productos[] = array(
			'id'           => (string) $id,
			'titulo'       => $titulo,
			'modelo'       => $modelo,
			'precio_lista' => $precio_lista,
			'precio_oferta' => $precio_oferta > 0 ? $precio_oferta : $precio_lista,
			'tiene_oferta' => $precio_oferta > 0,
		);
	}
	// Limitar a 20 para autocompletado
	$productos = array_slice( $productos, 0, 20 );
	wp_send_json_success( array( 'productos' => $productos ) );
}
add_action( 'wp_ajax_centinela_cotizador_buscar_productos', 'centinela_cotizador_ajax_buscar_productos' );

/**
 * AJAX: obtener tipo de cambio USD/COP (precio del dólar hoy). Caché 24h.
 */
function centinela_cotizador_ajax_tipo_cambio() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$tipo_cambio = 0;
	// Intentar primero con el tipo de cambio oficial de Syscom (TRM que ves en su panel).
	if ( class_exists( 'Centinela_Syscom_API' ) && method_exists( 'Centinela_Syscom_API', 'get_tipo_cambio_usd_cop' ) ) {
		$tc_syscom = Centinela_Syscom_API::get_tipo_cambio_usd_cop();
		if ( ! is_wp_error( $tc_syscom ) ) {
			$tipo_cambio = (float) $tc_syscom;
		}
	}
	// Si falla Syscom, usar API gratuita (USD base: 1 USD = X COP) como respaldo.
	if ( $tipo_cambio <= 0 ) {
		$url      = 'https://api.exchangerate-api.com/v4/latest/USD';
		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );
		if ( ! is_wp_error( $response ) ) {
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( $code === 200 && is_array( $data ) && isset( $data['rates']['COP'] ) ) {
				$tipo_cambio = (float) $data['rates']['COP'];
			}
		}
	}
	if ( $tipo_cambio <= 0 ) {
		$tipo_cambio = (float) get_option( 'centinela_cotizador_tipo_cambio_default', 4000 );
	}
	wp_send_json_success( array( 'tipo_cambio' => $tipo_cambio ) );
}
add_action( 'wp_ajax_centinela_cotizador_tipo_cambio', 'centinela_cotizador_ajax_tipo_cambio' );

/**
 * AJAX: guardar cotización (moneda elegida por el usuario)
 */
function centinela_cotizador_ajax_guardar() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	if ( is_string( $raw ) ) {
		$datos = json_decode( $raw, true );
	} else {
		$datos = is_array( $raw ) ? $raw : array();
	}
	if ( empty( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'Datos de cotización no válidos.', 'centinela-group-theme' ) ) );
	}
	$editar_id = isset( $_POST['editar_id'] ) ? absint( $_POST['editar_id'] ) : 0;
	$post_id = centinela_cotizador_save_cotizacion( $datos, $editar_id > 0 ? $editar_id : null );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}
	wp_send_json_success( array( 'id' => $post_id, 'message' => __( 'Cotización guardada.', 'centinela-group-theme' ) ) );
}
add_action( 'wp_ajax_centinela_cotizador_guardar', 'centinela_cotizador_ajax_guardar' );

/**
 * AJAX: vista previa del cuerpo del correo (HTML) para el modal Enviar y Guardar
 */
function centinela_cotizador_ajax_preview_email() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw  = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( empty( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'Datos de cotización no válidos.', 'centinela-group-theme' ) ) );
	}
	$html = centinela_cotizador_build_email_html( $datos );
	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_centinela_cotizador_preview_email', 'centinela_cotizador_ajax_preview_email' );

/**
 * AJAX: enviar cotización por email (HTML + adjunto PDF o Excel) y guardar
 */
function centinela_cotizador_ajax_enviar_guardar() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw   = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( empty( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'Datos de cotización no válidos.', 'centinela-group-theme' ) ) );
	}
	$formato_adjunto = isset( $_POST['formato_adjunto'] ) ? sanitize_text_field( wp_unslash( $_POST['formato_adjunto'] ) ) : 'pdf';
	if ( ! in_array( $formato_adjunto, array( 'pdf', 'excel' ), true ) ) {
		$formato_adjunto = 'pdf';
	}

	$editar_id = isset( $_POST['editar_id'] ) ? absint( $_POST['editar_id'] ) : 0;
	$post_id   = centinela_cotizador_save_cotizacion( $datos, $editar_id > 0 ? $editar_id : null );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}

	$email_cliente = '';
	if ( ! empty( $datos['cliente']['email'] ) ) {
		$email_cliente = sanitize_email( $datos['cliente']['email'] );
	}
	if ( ! is_email( $email_cliente ) ) {
		wp_send_json_error( array( 'message' => __( 'El email del cliente no es válido.', 'centinela-group-theme' ) ) );
	}

	$datos['numero'] = get_post_meta( $post_id, '_cotizacion_numero', true );
	$asunto = sprintf( __( 'Cotización: %s', 'centinela-group-theme' ), isset( $datos['titulo'] ) ? $datos['titulo'] : '' );
	$cuerpo_html = centinela_cotizador_build_email_html( $datos );

	$attachments = array();
	if ( $formato_adjunto === 'pdf' ) {
		$pdf_path = apply_filters( 'centinela_cotizador_generar_pdf', '', $post_id, 'default', $datos );
		if ( $pdf_path !== '' && file_exists( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		}
	} elseif ( $formato_adjunto === 'excel' ) {
		$excel_path = apply_filters( 'centinela_cotizador_generar_excel', '', $post_id, $datos );
		if ( $excel_path === '' ) {
			$excel_path = centinela_cotizador_generar_excel_fallback( $post_id, $datos );
		}
		if ( $excel_path !== '' && file_exists( $excel_path ) ) {
			$attachments[] = $excel_path;
		}
	}

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$envio   = wp_mail( $email_cliente, $asunto, $cuerpo_html, $headers, $attachments );

	foreach ( $attachments as $path ) {
		if ( file_exists( $path ) ) {
			@unlink( $path );
		}
	}

	wp_send_json_success( array(
		'id'      => $post_id,
		'message' => $envio ? __( 'Cotización guardada y enviada por email.', 'centinela-group-theme' ) : __( 'Cotización guardada. No se pudo enviar el email.', 'centinela-group-theme' ),
		'enviado' => $envio,
	) );
}
add_action( 'wp_ajax_centinela_cotizador_enviar_guardar', 'centinela_cotizador_ajax_enviar_guardar' );

/**
 * AJAX: generar y guardar una copia del correo (cuerpo HTML + adjunto) para revisar en local sin enviar
 */
function centinela_cotizador_ajax_preview_envio() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw   = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( empty( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'Datos de cotización no válidos.', 'centinela-group-theme' ) ) );
	}
	$formato_adjunto = isset( $_POST['formato_adjunto'] ) ? sanitize_text_field( wp_unslash( $_POST['formato_adjunto'] ) ) : 'pdf';
	if ( ! in_array( $formato_adjunto, array( 'pdf', 'excel' ), true ) ) {
		$formato_adjunto = 'pdf';
	}

	$editar_id = isset( $_POST['editar_id'] ) ? absint( $_POST['editar_id'] ) : 0;
	$post_id   = centinela_cotizador_save_cotizacion( $datos, $editar_id > 0 ? $editar_id : null );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}

	$upload_dir = wp_upload_dir();
	if ( ! empty( $upload_dir['error'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No se pudo acceder a la carpeta de subidas.', 'centinela-group-theme' ) ) );
	}
	$preview_dir = $upload_dir['basedir'] . '/cotizador-preview';
	if ( ! file_exists( $preview_dir ) ) {
		wp_mkdir_p( $preview_dir );
	}
	if ( ! is_writable( $preview_dir ) ) {
		wp_send_json_error( array( 'message' => __( 'La carpeta cotizador-preview no es escribible.', 'centinela-group-theme' ) ) );
	}
	$base_url = $upload_dir['baseurl'] . '/cotizador-preview';
	$prefijo  = 'cotizacion-' . gmdate( 'Y-m-d_H-i-s' ) . '-';

	$datos['numero'] = get_post_meta( $post_id, '_cotizacion_numero', true );
	$cuerpo_html = centinela_cotizador_build_email_html( $datos );
	$html_file   = $preview_dir . '/' . $prefijo . 'cuerpo.html';
	if ( file_put_contents( $html_file, $cuerpo_html ) === false ) {
		wp_send_json_error( array( 'message' => __( 'No se pudo guardar el archivo HTML.', 'centinela-group-theme' ) ) );
	}
	$html_url = $base_url . '/' . $prefijo . 'cuerpo.html';

	$adjunto_url     = '';
	$adjunto_nombre  = '';
	$archivo_adjunto = '';

	if ( $formato_adjunto === 'pdf' ) {
		$archivo_adjunto = apply_filters( 'centinela_cotizador_generar_pdf', '', $post_id, 'default', $datos );
		if ( $archivo_adjunto !== '' && file_exists( $archivo_adjunto ) ) {
			$dest = $preview_dir . '/' . $prefijo . 'cotizacion.pdf';
			if ( copy( $archivo_adjunto, $dest ) ) {
				$adjunto_url    = $base_url . '/' . $prefijo . 'cotizacion.pdf';
				$adjunto_nombre = 'cotizacion.pdf';
			}
			@unlink( $archivo_adjunto );
		}
	} elseif ( $formato_adjunto === 'excel' ) {
		$archivo_adjunto = apply_filters( 'centinela_cotizador_generar_excel', '', $post_id, $datos );
		if ( $archivo_adjunto === '' ) {
			$archivo_adjunto = centinela_cotizador_generar_excel_fallback( $post_id, $datos );
		}
		if ( $archivo_adjunto !== '' && file_exists( $archivo_adjunto ) ) {
			$ext  = ( $formato_adjunto === 'excel' && strpos( $archivo_adjunto, '.xlsx' ) !== false ) ? 'xlsx' : 'csv';
			$dest = $preview_dir . '/' . $prefijo . 'cotizacion.' . $ext;
			if ( copy( $archivo_adjunto, $dest ) ) {
				$adjunto_url    = $base_url . '/' . $prefijo . 'cotizacion.' . $ext;
				$adjunto_nombre = 'cotizacion.' . $ext;
			}
			@unlink( $archivo_adjunto );
		}
	}

	wp_send_json_success( array(
		'html_url'        => $html_url,
		'adjunto_url'     => $adjunto_url,
		'adjunto_nombre'  => $adjunto_nombre,
		'message'         => __( 'Copia generada. Se abrirá el correo en una nueva pestaña.', 'centinela-group-theme' ),
	) );
}
add_action( 'wp_ajax_centinela_cotizador_preview_envio', 'centinela_cotizador_ajax_preview_envio' );

/**
 * Generar archivo Excel/CSV de la cotización (fallback si no hay filtro)
 *
 * @param int   $post_id ID del post cotización.
 * @param array $datos   Datos de la cotización.
 * @return string Ruta al archivo temporal o vacío.
 */
function centinela_cotizador_generar_excel_fallback( $post_id, $datos ) {
	$productos = isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array();
	$titulo    = isset( $datos['titulo'] ) ? $datos['titulo'] : __( 'Cotización', 'centinela-group-theme' );
	$moneda    = isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP';
	$simbolo   = $moneda === 'USD' ? 'USD $' : 'CO $';
	$subtotal  = isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0;
	$iva_valor = isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0;
	$total     = isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
	$numero    = isset( $datos['numero'] ) ? $datos['numero'] : get_post_meta( $post_id, '_cotizacion_numero', true );

	$tmp = wp_tempnam( 'cotizacion-' . $post_id );
	if ( ! $tmp ) {
		return '';
	}
	$ext = '.csv';
	$out = $tmp . $ext;
	if ( rename( $tmp, $out ) !== true ) {
		@unlink( $tmp );
		return '';
	}
	$fp = fopen( $out, 'w' );
	if ( ! $fp ) {
		@unlink( $out );
		return '';
	}
	// Cotización # visible en el archivo Excel/CSV (igual que en el correo y PDF).
	if ( $numero !== '' ) {
		fputcsv( $fp, array( __( 'Cotización', 'centinela-group-theme' ) . ' #' . $numero ), ';' );
		fputcsv( $fp, array( '' ), ';' );
	}
	fputcsv( $fp, array( $titulo ), ';' );
	fputcsv( $fp, array( '' ), ';' );
	fputcsv( $fp, array( __( 'Modelo', 'centinela-group-theme' ), __( 'Cantidad', 'centinela-group-theme' ), __( 'Descuento %', 'centinela-group-theme' ), __( 'Precio', 'centinela-group-theme' ), __( 'Importe', 'centinela-group-theme' ) ), ';' );
	foreach ( $productos as $p ) {
		$modelo   = isset( $p['modelo'] ) ? $p['modelo'] : '';
		$cantidad = isset( $p['cantidad'] ) ? (int) $p['cantidad'] : 0;
		$descuento = isset( $p['descuento'] ) ? floatval( $p['descuento'] ) : 0;
		$precio   = isset( $p['precio'] ) ? floatval( $p['precio'] ) : 0;
		$importe  = isset( $p['importe'] ) ? floatval( $p['importe'] ) : 0;
		fputcsv( $fp, array( $modelo, $cantidad, $descuento, $precio, $importe ), ';' );
	}
	fputcsv( $fp, array( '' ), ';' );
	fputcsv( $fp, array( __( 'Subtotal', 'centinela-group-theme' ), '', '', '', $simbolo . ' ' . number_format( $subtotal, 2, ',', '.' ) ), ';' );
	fputcsv( $fp, array( __( 'I.V.A.', 'centinela-group-theme' ), '', '', '', $simbolo . ' ' . number_format( $iva_valor, 2, ',', '.' ) ), ';' );
	fputcsv( $fp, array( __( 'TOTAL', 'centinela-group-theme' ), '', '', '', $simbolo . ' ' . number_format( $total, 2, ',', '.' ) ), ';' );
	fclose( $fp );
	return $out;
}

/**
 * Fallback: generar PDF desde el mismo HTML del correo (incluye Cotización #) si Dompdf está disponible.
 * Quien implemente el filtro centinela_cotizador_generar_pdf debe incluir $datos['numero'] en el PDF.
 *
 * @param string $path     Ruta devuelta por otro callback (si no está vacía, se devuelve tal cual).
 * @param int    $post_id  ID del post cotización.
 * @param string $plantilla Plantilla (no usado).
 * @param array  $datos    Datos de la cotización (incluye 'numero' para Cotización #).
 * @return string Ruta al PDF temporal o valor recibido en $path.
 */
function centinela_cotizador_generar_pdf_fallback( $path, $post_id, $plantilla, $datos ) {
	if ( $path !== '' ) {
		return $path;
	}
	if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
		return '';
	}
	$datos['numero'] = isset( $datos['numero'] ) ? $datos['numero'] : get_post_meta( $post_id, '_cotizacion_numero', true );
	$html = centinela_cotizador_build_email_html( $datos );
	$tmp  = wp_tempnam( 'cotizacion-' . $post_id );
	if ( ! $tmp ) {
		return '';
	}
	@unlink( $tmp );
	$out = $tmp . '.pdf';
	try {
		$dompdf = new \Dompdf\Dompdf( array( 'isRemoteEnabled' => true ) );
		$dompdf->loadHtml( $html );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();
		file_put_contents( $out, $dompdf->output() );
		return $out;
	} catch ( Exception $e ) {
		if ( file_exists( $out ) ) {
			@unlink( $out );
		}
		return '';
	}
}
add_filter( 'centinela_cotizador_generar_pdf', 'centinela_cotizador_generar_pdf_fallback', 999, 4 );

/**
 * AJAX: generar link de pago (crear pedido WC y devolver URL Wompi)
 */
function centinela_cotizador_ajax_enviar_carrito() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( empty( $datos ) || empty( $datos['productos'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No hay productos en la cotización.', 'centinela-group-theme' ) ) );
	}
	$direccion  = isset( $_POST['centinela_direccion'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_direccion'] ) ) : '';
	$ciudad     = isset( $_POST['centinela_ciudad'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_ciudad'] ) ) : '';
	$departamento = isset( $_POST['centinela_departamento'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_departamento'] ) ) : '';
	$nombre     = isset( $datos['cliente']['nombre'] ) ? sanitize_text_field( $datos['cliente']['nombre'] ) : '';
	$email      = isset( $datos['cliente']['email'] ) ? sanitize_email( $datos['cliente']['email'] ) : '';
	$telefono   = isset( $datos['contacto']['telefono'] ) ? sanitize_text_field( $datos['contacto']['telefono'] ) : '';
	if ( $nombre === '' || $email === '' || $direccion === '' || $ciudad === '' || $departamento === '' ) {
		wp_send_json_error( array( 'message' => __( 'Complete los datos del cliente: nombre, email, dirección, ciudad y departamento.', 'centinela-group-theme' ) ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Email del cliente no válido.', 'centinela-group-theme' ) ) );
	}

	$items = array();
	foreach ( $datos['productos'] as $p ) {
		$items[] = array(
			'id'    => isset( $p['id'] ) ? $p['id'] : '',
			'qty'   => isset( $p['cantidad'] ) ? max( 1, (int) $p['cantidad'] ) : 1,
			'title' => isset( $p['modelo'] ) ? $p['modelo'] : '',
			'price' => isset( $p['precio'] ) ? floatval( $p['precio'] ) : 0,
		);
	}
	$form = array(
		'centinela_nombre'       => $nombre,
		'centinela_email'        => $email,
		'centinela_telefono'     => $telefono,
		'centinela_direccion'    => $direccion,
		'centinela_complemento'  => '',
		'centinela_ciudad'       => $ciudad,
		'centinela_departamento' => $departamento,
		'centinela_codigo_postal'=> '',
		'centinela_pais'         => 'Colombia',
		'centinela_notas'        => isset( $datos['titulo'] ) ? $datos['titulo'] : '',
	);

	if ( ! function_exists( 'centinela_checkout_create_wc_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Checkout no disponible.', 'centinela-group-theme' ) ) );
	}
	$result = centinela_checkout_create_wc_order( $items, $form );
	if ( ! $result['success'] ) {
		wp_send_json_error( array( 'message' => $result['message'] ) );
	}
	wp_send_json_success( array( 'redirect' => $result['redirect'], 'message' => __( 'Link de pago generado.', 'centinela-group-theme' ) ) );
}
add_action( 'wp_ajax_centinela_cotizador_enviar_carrito', 'centinela_cotizador_ajax_enviar_carrito' );

/**
 * Renderizar la página del Cotizador
 */
function centinela_cotizador_render_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap centinela-cotizador-wrap">
		<h1><?php esc_html_e( 'Cotizador', 'centinela-group-theme' ); ?></h1>

		<div class="centinela-cotizador-layout">
			<div class="centinela-cotizador-contenido">
		<div class="centinela-cotizador-form">
			<input type="hidden" id="centinela-cotizador-editar-id" value="" />
			<div class="centinela-cotizador-field">
				<label for="centinela-cotizador-titulo"><?php esc_html_e( 'Título de la Cotización', 'centinela-group-theme' ); ?></label>
				<input type="text" id="centinela-cotizador-titulo" class="regular-text" placeholder="<?php esc_attr_e( 'Ej: Cotización proyecto videovigilancia', 'centinela-group-theme' ); ?>" />
			</div>

			<div class="centinela-cotizador-section">
				<h2 class="centinela-cotizador-section-title"><?php esc_html_e( 'Listado de Productos', 'centinela-group-theme' ); ?></h2>
				<div class="centinela-cotizador-busqueda">
					<select id="centinela-cotizador-tipo-busqueda" aria-label="<?php esc_attr_e( 'Buscar por', 'centinela-group-theme' ); ?>">
						<option value="modelo"><?php esc_html_e( 'Modelo', 'centinela-group-theme' ); ?></option>
						<option value="titulo"><?php esc_html_e( 'Título', 'centinela-group-theme' ); ?></option>
					</select>
					<div class="centinela-cotizador-autocomplete-wrap">
						<input type="text" id="centinela-cotizador-busqueda" class="regular-text" autocomplete="off" placeholder="" />
						<span class="centinela-cotizador-spinner" aria-hidden="true"></span>
						<ul id="centinela-cotizador-sugerencias" class="centinela-cotizador-sugerencias" role="listbox" hidden></ul>
					</div>
				</div>

				<div class="centinela-cotizador-tabla-wrap">
					<table class="widefat striped centinela-cotizador-tabla" id="centinela-cotizador-tabla">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Modelo', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-cantidad"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-descuento"><?php esc_html_e( 'Descuento %', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-precio"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-importe"><?php esc_html_e( 'Importe', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-acciones"><?php esc_html_e( 'Acciones', 'centinela-group-theme' ); ?></th>
							</tr>
						</thead>
						<tbody id="centinela-cotizador-filas">
							<tr class="centinela-cotizador-tabla-vacia" id="centinela-cotizador-fila-vacia">
								<td colspan="6"><?php esc_html_e( 'Agrega productos usando el buscador de arriba.', 'centinela-group-theme' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>

			<div class="centinela-cotizador-section centinela-cotizador-datos-columnas">
				<div class="centinela-cotizador-col centinela-cotizador-col-cliente">
					<h2 class="centinela-cotizador-section-title"><?php esc_html_e( 'Datos del Cliente', 'centinela-group-theme' ); ?></h2>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-nombre"><?php esc_html_e( 'Nombre', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-nombre" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-email"><?php esc_html_e( 'Email', 'centinela-group-theme' ); ?></label>
						<input type="email" id="centinela-cotizador-cliente-email" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-vigencia"><?php esc_html_e( 'Vigencia', 'centinela-group-theme' ); ?></label>
						<input type="date" id="centinela-cotizador-cliente-vigencia" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-comentarios"><?php esc_html_e( 'Comentarios', 'centinela-group-theme' ); ?></label>
						<textarea id="centinela-cotizador-cliente-comentarios" class="large-text" rows="4"></textarea>
					</div>
				</div>
				<div class="centinela-cotizador-col centinela-cotizador-col-contacto">
					<h2 class="centinela-cotizador-section-title"><?php esc_html_e( 'Mis Datos de Contacto', 'centinela-group-theme' ); ?></h2>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-mi-nombre"><?php esc_html_e( 'Nombre', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-mi-nombre" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-mi-email"><?php esc_html_e( 'Mi Email', 'centinela-group-theme' ); ?></label>
						<input type="email" id="centinela-cotizador-mi-email" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-mi-telefono"><?php esc_html_e( 'Mi Teléfono', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-mi-telefono" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-metodo-pago"><?php esc_html_e( 'Método de Pago', 'centinela-group-theme' ); ?></label>
						<select id="centinela-cotizador-metodo-pago" class="regular-text">
							<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
							<option value="tarjeta_credito"><?php esc_html_e( 'Tarjeta de Crédito', 'centinela-group-theme' ); ?></option>
							<option value="tarjeta_debito"><?php esc_html_e( 'Tarjeta de Débito', 'centinela-group-theme' ); ?></option>
							<option value="contado"><?php esc_html_e( 'Contado', 'centinela-group-theme' ); ?></option>
							<option value="cheque_nominativo"><?php esc_html_e( 'Cheque Nominativo', 'centinela-group-theme' ); ?></option>
							<option value="transferencia_electronica"><?php esc_html_e( 'Transferencia electrónica', 'centinela-group-theme' ); ?></option>
							<option value="pago_a_credito"><?php esc_html_e( 'Pago a crédito', 'centinela-group-theme' ); ?></option>
						</select>
					</div>
				</div>
			</div>
		</div>

			<div class="centinela-cotizador-sidebar">
				<section class="centinela-cotizador-logo-section" aria-labelledby="centinela-cotizador-logo-title">
					<h2 id="centinela-cotizador-logo-title" class="centinela-cotizador-logo-title"><?php esc_html_e( 'Logo de la cotización', 'centinela-group-theme' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Logo que se mostrará en el correo y en el PDF/Excel enviado al cliente.', 'centinela-group-theme' ); ?></p>
					<?php
					$logo_default_id   = get_theme_mod( 'custom_logo' );
					$logo_default_src   = $logo_default_id ? wp_get_attachment_image_url( $logo_default_id, 'medium' ) : '';
					$logo_default_full  = $logo_default_id ? wp_get_attachment_image_url( $logo_default_id, 'full' ) : '';
					?>
					<div class="centinela-cotizador-logo-preview-wrap">
						<div class="centinela-cotizador-logo-preview" id="centinela-cotizador-logo-preview">
							<img id="centinela-cotizador-logo-img" src="<?php echo esc_url( $logo_default_src ); ?>" alt="" style="max-width:100%;height:auto;<?php echo $logo_default_src ? '' : 'display:none;'; ?>" />
							<span class="centinela-cotizador-logo-placeholder" id="centinela-cotizador-logo-placeholder" style="<?php echo $logo_default_src ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Logo del tema', 'centinela-group-theme' ); ?></span>
						</div>
						<p class="centinela-cotizador-logo-formats"><?php esc_html_e( 'Formatos aceptados:', 'centinela-group-theme' ); ?> .png, .jpg, .ai</p>
					</div>
					<input type="hidden" id="centinela-cotizador-logo-url" value="<?php echo esc_url( $logo_default_full ); ?>" />
					<div class="centinela-cotizador-logo-actions">
						<button type="button" class="button" id="centinela-cotizador-logo-select"><?php esc_html_e( 'Subir / Cambiar logo', 'centinela-group-theme' ); ?></button>
						<button type="button" class="button button-link-delete" id="centinela-cotizador-logo-reset"><?php esc_html_e( 'Usar logo del tema', 'centinela-group-theme' ); ?></button>
					</div>
				</section>

			<aside class="centinela-cotizador-resumen">
				<h2 class="centinela-cotizador-resumen-title"><?php esc_html_e( 'Resumen', 'centinela-group-theme' ); ?></h2>

				<div class="centinela-cotizador-field">
					<label for="centinela-cotizador-tipo-precio"><?php esc_html_e( 'Tipo de Precio', 'centinela-group-theme' ); ?></label>
					<select id="centinela-cotizador-tipo-precio" class="regular-text">
						<option value="lista"><?php esc_html_e( 'Lista', 'centinela-group-theme' ); ?></option>
						<option value="oferta"><?php esc_html_e( 'Oferta', 'centinela-group-theme' ); ?></option>
					</select>
				</div>

				<div class="centinela-cotizador-field">
					<label for="centinela-cotizador-moneda"><?php esc_html_e( 'Moneda', 'centinela-group-theme' ); ?></label>
					<select id="centinela-cotizador-moneda" class="regular-text">
						<option value="COP"><?php esc_html_e( 'COP', 'centinela-group-theme' ); ?></option>
						<option value="USD"><?php esc_html_e( 'USD', 'centinela-group-theme' ); ?></option>
					</select>
				</div>

				<div class="centinela-cotizador-field centinela-cotizador-field-tc">
					<label for="centinela-cotizador-tipo-cambio"><?php esc_html_e( 'Tipo de Cambio (1 USD = X COP)', 'centinela-group-theme' ); ?></label>
					<div class="centinela-cotizador-tc-wrap">
						<input type="number" id="centinela-cotizador-tipo-cambio" class="regular-text" min="0" step="0.01" value="" placeholder="<?php esc_attr_e( 'Cargando…', 'centinela-group-theme' ); ?>" />
						<button type="button" class="button" id="centinela-cotizador-actualizar-tc"><?php esc_html_e( 'Actualizar', 'centinela-group-theme' ); ?></button>
					</div>
				</div>

				<div class="centinela-cotizador-resumen-totales">
					<div class="centinela-cotizador-resumen-fila">
						<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></span>
						<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-subtotal">0</span>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-iva">
						<div class="centinela-cotizador-resumen-iva-pct">
							<label for="centinela-cotizador-iva-pct"><?php esc_html_e( 'I.V.A. %', 'centinela-group-theme' ); ?></label>
							<input type="number" id="centinela-cotizador-iva-pct" min="0" max="100" step="0.01" value="19" />
						</div>
						<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-iva-valor">0</span>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-total">
						<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'TOTAL', 'centinela-group-theme' ); ?></span>
						<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-total">0</span>
					</div>
				</div>

				<div class="centinela-cotizador-resumen-botones">
					<button type="button" class="button button-primary button-large" id="centinela-cotizador-enviar-guardar"><?php esc_html_e( 'Enviar y Guardar', 'centinela-group-theme' ); ?></button>
					<button type="button" class="button button-large" id="centinela-cotizador-guardar"><?php esc_html_e( 'Guardar Cotización', 'centinela-group-theme' ); ?></button>
					<button type="button" class="button button-large" id="centinela-cotizador-enviar-carrito"><?php esc_html_e( 'Enviar al Carrito', 'centinela-group-theme' ); ?></button>
				</div>
			</aside>
			</div>
		</div>
	</div>
	<!-- Modal Enviar y Guardar -->
	<div id="centinela-cotizador-modal-enviar-guardar" class="centinela-cotizador-modal" role="dialog" aria-labelledby="centinela-modal-enviar-title" aria-modal="true" hidden>
		<div class="centinela-cotizador-modal-backdrop"></div>
		<div class="centinela-cotizador-modal-content centinela-cotizador-modal-enviar-content">
			<h2 id="centinela-modal-enviar-title" class="centinela-cotizador-modal-title"><?php esc_html_e( 'Enviar cotización por email', 'centinela-group-theme' ); ?></h2>
			<div class="centinela-cotizador-field">
				<label><?php esc_html_e( 'Vista previa del correo', 'centinela-group-theme' ); ?></label>
				<div class="centinela-cotizador-email-preview-wrap">
					<iframe id="centinela-cotizador-email-preview" class="centinela-cotizador-email-preview" title="<?php esc_attr_e( 'Vista previa del correo', 'centinela-group-theme' ); ?>"></iframe>
					<span class="centinela-cotizador-email-preview-loading" id="centinela-cotizador-email-preview-loading"><?php esc_html_e( 'Cargando vista previa…', 'centinela-group-theme' ); ?></span>
				</div>
			</div>
			<div class="centinela-cotizador-field">
				<label for="centinela-modal-formato-adjunto"><?php esc_html_e( 'Seleccione formato de la cotización:', 'centinela-group-theme' ); ?></label>
				<select id="centinela-modal-formato-adjunto" class="regular-text">
					<option value="pdf"><?php esc_html_e( 'PDF', 'centinela-group-theme' ); ?></option>
					<option value="excel"><?php esc_html_e( 'Excel', 'centinela-group-theme' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'El archivo se adjuntará al correo que recibirá el cliente en el email indicado en Datos del cliente.', 'centinela-group-theme' ); ?></p>
			</div>
			<p class="centinela-cotizador-modal-msg" id="centinela-modal-enviar-msg"></p>
			<div class="centinela-cotizador-modal-actions">
				<button type="button" class="button" id="centinela-cotizador-modal-ver-envio-btn"><?php esc_html_e( 'Ver cómo llegaría el correo', 'centinela-group-theme' ); ?></button>
				<button type="button" class="button" data-close-modal="centinela-cotizador-modal-enviar-guardar"><?php esc_html_e( 'Cerrar', 'centinela-group-theme' ); ?></button>
				<button type="button" class="button button-primary" id="centinela-cotizador-modal-enviar-btn"><?php esc_html_e( 'Enviar', 'centinela-group-theme' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Modal Guardar Cotización -->
	<div id="centinela-cotizador-modal-guardar" class="centinela-cotizador-modal" role="dialog" aria-labelledby="centinela-modal-guardar-title" aria-modal="true" hidden>
		<div class="centinela-cotizador-modal-backdrop"></div>
		<div class="centinela-cotizador-modal-content">
			<h2 id="centinela-modal-guardar-title" class="centinela-cotizador-modal-title"><?php esc_html_e( 'Guardar cotización', 'centinela-group-theme' ); ?></h2>
			<p class="centinela-cotizador-modal-confirm" id="centinela-modal-guardar-text"></p>
			<p class="centinela-cotizador-modal-msg" id="centinela-modal-guardar-msg"></p>
			<div class="centinela-cotizador-modal-actions">
				<button type="button" class="button" data-close-modal="centinela-cotizador-modal-guardar"><?php esc_html_e( 'Cancelar', 'centinela-group-theme' ); ?></button>
				<button type="button" class="button button-primary" id="centinela-cotizador-modal-guardar-btn"><?php esc_html_e( 'Guardar', 'centinela-group-theme' ); ?></button>
			</div>
		</div>
	</div>

	<!-- Modal Enviar al Carrito (link de pago) -->
	<div id="centinela-cotizador-modal-carrito" class="centinela-cotizador-modal" role="dialog" aria-labelledby="centinela-modal-carrito-title" aria-modal="true" hidden>
		<div class="centinela-cotizador-modal-backdrop"></div>
		<div class="centinela-cotizador-modal-content centinela-cotizador-modal-carrito-content">
			<h2 id="centinela-modal-carrito-title" class="centinela-cotizador-modal-title"><?php esc_html_e( 'Generar link de pago', 'centinela-group-theme' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Complete los datos del cliente para generar el link de pago con Wompi.', 'centinela-group-theme' ); ?></p>
			<div class="centinela-cotizador-field">
				<label for="centinela-modal-direccion"><?php esc_html_e( 'Dirección', 'centinela-group-theme' ); ?></label>
				<input type="text" id="centinela-modal-direccion" class="regular-text" />
			</div>
			<div class="centinela-cotizador-field">
				<label for="centinela-modal-ciudad"><?php esc_html_e( 'Ciudad', 'centinela-group-theme' ); ?></label>
				<input type="text" id="centinela-modal-ciudad" class="regular-text" />
			</div>
			<div class="centinela-cotizador-field">
				<label for="centinela-modal-departamento"><?php esc_html_e( 'Departamento', 'centinela-group-theme' ); ?></label>
				<input type="text" id="centinela-modal-departamento" class="regular-text" />
			</div>
			<p class="centinela-cotizador-modal-msg" id="centinela-modal-carrito-msg"></p>
			<div class="centinela-cotizador-modal-actions">
				<button type="button" class="button" data-close-modal="centinela-cotizador-modal-carrito"><?php esc_html_e( 'Cerrar', 'centinela-group-theme' ); ?></button>
				<button type="button" class="button button-primary" id="centinela-cotizador-modal-carrito-btn"><?php esc_html_e( 'Generar link de pago', 'centinela-group-theme' ); ?></button>
			</div>
			<div class="centinela-cotizador-modal-link-wrap" id="centinela-cotizador-link-wrap" style="display:none;">
				<label><?php esc_html_e( 'Link de pago:', 'centinela-group-theme' ); ?></label>
				<div class="centinela-cotizador-link-row">
					<input type="text" id="centinela-cotizador-pago-link" class="large-text" readonly />
					<button type="button" class="button" id="centinela-cotizador-copiar-link"><?php esc_html_e( 'Copiar', 'centinela-group-theme' ); ?></button>
				</div>
			</div>
		</div>
	</div>

	<?php
}

/**
 * Renderizar la página Mis Cotizaciones (listado de cotizaciones guardadas)
 */
function centinela_cotizador_render_mis_cotizaciones() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// Procesar eliminación (mover a papelera)
	$eliminar_id = isset( $_GET['eliminar'] ) ? absint( $_GET['eliminar'] ) : 0;
	if ( $eliminar_id > 0 && isset( $_GET['_wpnonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( wp_verify_nonce( $nonce, 'centinela_eliminar_cotizacion_' . $eliminar_id ) ) {
			$post = get_post( $eliminar_id );
			if ( $post && $post->post_type === 'cotizacion' ) {
				wp_trash_post( $eliminar_id );
				wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&eliminado=1' ) );
				exit;
			}
		}
	}
	$query = new WP_Query( array(
		'post_type'      => 'cotizacion',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Mis Cotizaciones', 'centinela-group-theme' ); ?></h1>
		<?php if ( isset( $_GET['eliminado'] ) && $_GET['eliminado'] === '1' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cotización eliminada.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Cotizaciones guardadas. Podrás consultarlas, editarlas o enviarlas por email en PDF.', 'centinela-group-theme' ); ?></p>
		<?php if ( $query->have_posts() ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Título', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Moneda', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Total', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'centinela-group-theme' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php
						$datos = get_post_meta( get_the_ID(), '_cotizacion_datos', true );
						$moneda = is_array( $datos ) && isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP';
						$total  = is_array( $datos ) && isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
						$total_fmt = $moneda === 'USD' ? 'USD $ ' . number_format( $total, 2 ) : 'CO $ ' . number_format( $total, 0 );
						$editar_url = add_query_arg( 'editar', get_the_ID(), admin_url( 'admin.php?page=centinela-cotizador' ) );
						$eliminar_url = add_query_arg( array(
							'eliminar' => get_the_ID(),
							'_wpnonce' => wp_create_nonce( 'centinela_eliminar_cotizacion_' . get_the_ID() ),
						), admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' ) );
						$eliminar_confirm = esc_attr__( '¿Eliminar esta cotización? No se podrá deshacer.', 'centinela-group-theme' );
						?>
						<tr>
							<td><strong><?php the_title(); ?></strong></td>
							<td><?php echo esc_html( $moneda ); ?></td>
							<td><?php echo esc_html( $total_fmt ); ?></td>
							<td><?php echo esc_html( get_the_date() ); ?></td>
							<td>
								<a href="<?php echo esc_url( $editar_url ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'centinela-group-theme' ); ?></a>
								<a href="<?php echo esc_url( $eliminar_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( $eliminar_confirm ); ?>');"><?php esc_html_e( 'Eliminar', 'centinela-group-theme' ); ?></a>
							</td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
			<?php wp_reset_postdata(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay cotizaciones guardadas. Crea una desde el Cotizador y guárdala.', 'centinela-group-theme' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}
