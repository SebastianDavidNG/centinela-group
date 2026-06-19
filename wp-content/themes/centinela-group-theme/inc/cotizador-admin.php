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
 * Detecta envíos del cotizador (cabecera X-Centinela-Cotizacion-Trace o asunto típico).
 *
 * @param array $wp_mail_args Argumentos de wp_mail (headers, subject, …).
 * @return bool
 */
function centinela_cotizador_wp_mail_args_is_cotizacion( $wp_mail_args ) {
	if ( ! is_array( $wp_mail_args ) ) {
		return false;
	}
	$headers = isset( $wp_mail_args['headers'] ) ? $wp_mail_args['headers'] : '';
	if ( is_array( $headers ) ) {
		$list = $headers;
	} else {
		$list = array_filter( explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) ) );
	}
	foreach ( $list as $header_name => $header_val ) {
		if ( is_string( $header_name ) && preg_match( '/^X-Centinela-Cotizacion-Trace$/i', trim( $header_name ) ) ) {
			return true;
		}
		if ( is_string( $header_val ) && preg_match( '/^\s*X-Centinela-Cotizacion-Trace\s*:/i', $header_val ) ) {
			return true;
		}
	}
	$subject = isset( $wp_mail_args['subject'] ) ? (string) $wp_mail_args['subject'] : '';
	if ( $subject !== '' && ( stripos( $subject, 'Cotización Web de Centinela Group' ) !== false || stripos( $subject, 'Link de pago de tu cotización' ) !== false ) ) {
		return true;
	}
	return false;
}

/**
 * WP Mail SMTP (desde ~4.0): la opción «Optimizar envío de correos» encola wp_mail en peticiones normales
 * (p. ej. admin-ajax del cotizador). El correo de prueba del plugin no se encola porque lleva
 * X-Mailer-Type: WPMailSMTP/Admin/Test. Si el cron/Action Scheduler no procesa la cola, el correo nunca sale
 * aunque wp_mail devuelva true.
 *
 * Los envíos del cotizador llevan X-Centinela-Cotizacion-Trace: forzamos el mismo comportamiento que un envío inmediato.
 */
function centinela_cotizador_wp_mail_smtp_skip_queue_when_trace_header( $enqueue, $wp_mail_args ) {
	if ( true !== $enqueue || ! is_array( $wp_mail_args ) ) {
		return $enqueue;
	}
	if ( centinela_cotizador_wp_mail_args_is_cotizacion( $wp_mail_args ) ) {
		return false;
	}
	return $enqueue;
}
add_filter( 'wp_mail_smtp_mail_catcher_send_enqueue_email', 'centinela_cotizador_wp_mail_smtp_skip_queue_when_trace_header', 100, 2 );

/**
 * Site Mailer (Elementor) engancha pre_wp_mail y envía por su API, ignorando WP Mail SMTP.
 * Si ambos plugins están activos, los correos del cotizador deben ir por WP Mail SMTP.
 *
 * @return void
 */
function centinela_cotizador_unhook_site_mailer_pre_wp_mail() {
	global $wp_filter;
	if ( empty( $wp_filter['pre_wp_mail'] ) ) {
		return;
	}
	$hook = $wp_filter['pre_wp_mail'];
	if ( ! is_object( $hook ) || ! isset( $hook->callbacks ) || ! is_array( $hook->callbacks ) ) {
		return;
	}
	foreach ( $hook->callbacks as $priority => $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( empty( $callback['function'] ) || ! is_array( $callback['function'] ) ) {
				continue;
			}
			$object = $callback['function'][0];
			$method = isset( $callback['function'][1] ) ? $callback['function'][1] : '';
			if ( is_object( $object ) && $method === 'send' && is_a( $object, 'SiteMailer\Modules\Mailer\Module' ) ) {
				remove_filter( 'pre_wp_mail', $callback['function'], (int) $priority );
			}
		}
	}
}

/**
 * Deja pasar correos del cotizador a wp_mail + WP Mail SMTP (no Site Mailer pre_wp_mail).
 *
 * @param null|bool $sent Valor devuelto por filtros previos de pre_wp_mail.
 * @param array     $atts Argumentos de wp_mail.
 * @return null|bool
 */
function centinela_cotizador_pre_wp_mail_bypass_site_mailer( $sent, $atts ) {
	if ( null !== $sent ) {
		return $sent;
	}
	if ( ! centinela_cotizador_wp_mail_args_is_cotizacion( $atts ) ) {
		return null;
	}
	centinela_cotizador_unhook_site_mailer_pre_wp_mail();
	return null;
}
add_filter( 'pre_wp_mail', 'centinela_cotizador_pre_wp_mail_bypass_site_mailer', 9, 2 );

/**
 * Tras un envío exitoso desde el cotizador, ejecutar Action Scheduler en shutdown para procesar
 * acciones pendientes como wp_mail_smtp_send_enqueued_email (si el cron del sitio no corre bien).
 *
 * @return void
 */
function centinela_cotizador_schedule_action_scheduler_run_on_shutdown() {
	if ( ! apply_filters( 'centinela_cotizador_run_action_scheduler_after_mail', true ) ) {
		return;
	}
	if ( defined( 'CENTINELA_COTIZADOR_DISABLE_ACTION_SCHEDULER_FLUSH' ) && CENTINELA_COTIZADOR_DISABLE_ACTION_SCHEDULER_FLUSH ) {
		return;
	}
	add_action( 'shutdown', 'centinela_cotizador_action_scheduler_run_queue_on_shutdown', 999 );
}

/**
 * Ejecuta la cola de Action Scheduler una vez al final de la petición.
 *
 * @return void
 */
function centinela_cotizador_action_scheduler_run_queue_on_shutdown() {
	static $did = false;
	if ( $did ) {
		return;
	}
	$did = true;
	if ( ! class_exists( 'ActionScheduler_QueueRunner', false ) ) {
		return;
	}
	if ( doing_action( 'action_scheduler_run_queue' ) ) {
		return;
	}
	ActionScheduler_QueueRunner::instance()->run( 'Centinela cotizador' );
}

/**
 * Direcciones de correo para enviar la cotización: campo Email (principal) + opcionales (coma o punto y coma).
 * El PDF y el bloque fiscal siguen usando solo el email principal.
 *
 * @param array $cliente Bloque cliente (email, email_adicionales).
 * @return array{ primary: string, to: string[], error: string } Si error no está vacío, no enviar.
 */
function centinela_cotizador_cliente_emails_para_envio( $cliente ) {
	$out = array(
		'primary' => '',
		'to'      => array(),
		'error'   => '',
	);
	if ( ! is_array( $cliente ) ) {
		$out['error'] = __( 'El email del cliente no es válido.', 'centinela-group-theme' );
		return $out;
	}
	$primary = isset( $cliente['email'] ) ? sanitize_email( trim( (string) $cliente['email'] ) ) : '';
	if ( ! is_email( $primary ) ) {
		$out['error'] = __( 'El email del cliente no es válido.', 'centinela-group-theme' );
		return $out;
	}
	$out['primary'] = $primary;
	$list           = array( strtolower( $primary ) => $primary );
	$extras_raw     = isset( $cliente['email_adicionales'] ) ? trim( (string) $cliente['email_adicionales'] ) : '';
	if ( $extras_raw !== '' ) {
		$tokens = preg_split( '/[\s,;]+/', $extras_raw, -1, PREG_SPLIT_NO_EMPTY );
		$bad = array();
		foreach ( $tokens as $tok ) {
			$e = sanitize_email( trim( (string) $tok ) );
			if ( $e === '' || ! is_email( $e ) ) {
				$bad[] = sanitize_text_field( (string) $tok );
				continue;
			}
			$list[ strtolower( $e ) ] = $e;
		}
		if ( ! empty( $bad ) ) {
			$out['error'] = sprintf(
				/* translators: %s: list of invalid email tokens. */
				__( 'Hay direcciones de correo adicionales no válidas: %s', 'centinela-group-theme' ),
				implode( ', ', $bad )
			);
			return $out;
		}
	}
	$out['to'] = array_values( $list );
	return $out;
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
 * Asegura rol y capacidades para usuarios del Cotizador.
 * - centinela_manage_cotizador: acceso total al módulo (sin borrar cotizaciones).
 * - La eliminación queda reservada al administrador (manage_options).
 */
function centinela_cotizador_setup_role_caps() {
	$cap = 'centinela_manage_cotizador';

	$admin_role = get_role( 'administrator' );
	if ( $admin_role && ! $admin_role->has_cap( $cap ) ) {
		$admin_role->add_cap( $cap );
	}

	$cotizador_role = get_role( 'centinela_cotizador' );
	if ( ! $cotizador_role ) {
		$cotizador_role = add_role(
			'centinela_cotizador',
			__( 'Cotizador Centinela', 'centinela-group-theme' ),
			array(
				'read'                    => true,
				$cap                      => true,
			)
		);
	} elseif ( ! $cotizador_role->has_cap( $cap ) ) {
		$cotizador_role->add_cap( $cap );
	}
}
add_action( 'init', 'centinela_cotizador_setup_role_caps', 20 );

/**
 * Asegura que el usuario operativo del cotizador tenga el rol/cap correctos.
 * Fallback defensivo para instalaciones donde el usuario se crea manualmente con otro rol.
 */
function centinela_cotizador_ensure_operator_user_caps() {
	$user = get_user_by( 'login', 'CotizadorCentinelaG' );
	if ( ! $user || ! $user instanceof WP_User ) {
		// Compatibilidad por si el login fue creado en minúsculas.
		$user = get_user_by( 'login', 'cotizadorcentinelag' );
	}
	if ( ! $user || ! $user instanceof WP_User ) {
		return;
	}
	$needs_role = ! in_array( 'centinela_cotizador', (array) $user->roles, true );
	if ( $needs_role ) {
		$user->set_role( 'centinela_cotizador' );
	}
	if ( ! $user->has_cap( 'centinela_manage_cotizador' ) ) {
		$user->add_cap( 'centinela_manage_cotizador', true );
	}
}
add_action( 'init', 'centinela_cotizador_ensure_operator_user_caps', 25 );

/**
 * Permiso central del módulo cotizador.
 *
 * @return bool
 */
function centinela_cotizador_can_manage() {
	return current_user_can( 'centinela_manage_cotizador' );
}

/**
 * Obtiene el logo por defecto del cotizador.
 * Prioridad:
 * 1) Imagen con texto alternativo exacto: "Logo Centinela Group"
 * 2) Imagen con título exacto: "Logo Cotizador"
 * 3) Fallback al logo principal del tema.
 *
 * @return array{ id:int, src_full:string, src_medium:string }
 */
function centinela_cotizador_get_default_logo_data() {
	static $cached = null;
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$logo_id = 0;

	// 1) Buscar por texto alternativo (meta de adjunto).
	$ids_by_alt = get_posts(
		array(
			'post_type'              => 'attachment',
			'post_status'            => 'inherit',
			'post_mime_type'         => 'image',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'meta_key'               => '_wp_attachment_image_alt',
			'meta_value'             => 'Logo Centinela Group',
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	if ( ! empty( $ids_by_alt ) ) {
		$logo_id = (int) $ids_by_alt[0];
	}

	// 2) Si no existe por alt, buscar por título de adjunto.
	if ( $logo_id <= 0 ) {
		$ids_by_title = get_posts(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'title'                  => 'Logo Cotizador',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $ids_by_title ) ) {
			$logo_id = (int) $ids_by_title[0];
		}
	}

	// 3) Fallback final: logo principal del tema.
	if ( $logo_id <= 0 && has_custom_logo() ) {
		$theme_logo_id = (int) get_theme_mod( 'custom_logo' );
		if ( $theme_logo_id > 0 ) {
			$logo_id = $theme_logo_id;
		}
	}

	$src_full   = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';
	$src_medium = $logo_id > 0 ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	if ( $src_medium === '' ) {
		$src_medium = $src_full;
	}

	$cached = array(
		'id'         => $logo_id,
		'src_full'   => $src_full,
		'src_medium' => $src_medium,
	);
	return $cached;
}

/**
 * Formatea una fecha Y-m-d a texto legible en español: "Febrero 28 del 2026".
 *
 * @param string $fecha_ymd Fecha en formato Y-m-d (ej. 2026-02-28).
 * @return string Fecha formateada o cadena vacía si no hay fecha válida.
 */
function centinela_cotizador_format_vigencia_fecha( $fecha_ymd ) {
	$fecha_ymd = is_string( $fecha_ymd ) ? trim( $fecha_ymd ) : '';
	if ( $fecha_ymd === '' ) {
		return '';
	}
	$t = strtotime( $fecha_ymd );
	if ( $t === false ) {
		return $fecha_ymd;
	}
	$meses = array(
		1  => __( 'Enero', 'centinela-group-theme' ),
		2  => __( 'Febrero', 'centinela-group-theme' ),
		3  => __( 'Marzo', 'centinela-group-theme' ),
		4  => __( 'Abril', 'centinela-group-theme' ),
		5  => __( 'Mayo', 'centinela-group-theme' ),
		6  => __( 'Junio', 'centinela-group-theme' ),
		7  => __( 'Julio', 'centinela-group-theme' ),
		8  => __( 'Agosto', 'centinela-group-theme' ),
		9  => __( 'Septiembre', 'centinela-group-theme' ),
		10 => __( 'Octubre', 'centinela-group-theme' ),
		11 => __( 'Noviembre', 'centinela-group-theme' ),
		12 => __( 'Diciembre', 'centinela-group-theme' ),
	);
	$d = (int) gmdate( 'j', $t );
	$m = (int) gmdate( 'n', $t );
	$y = gmdate( 'Y', $t );
	$mes = isset( $meses[ $m ] ) ? $meses[ $m ] : $m;
	return $mes . ' ' . $d . ' del ' . $y;
}

/**
 * Normaliza fecha de cotización a Y-m-d (entrada type="date" o d/m/Y).
 *
 * @param string $raw Valor del campo.
 * @return string Y-m-d o cadena vacía si no es válida.
 */
function centinela_cotizador_sanitize_fecha_cotizacion( $raw ) {
	$raw = is_string( $raw ) ? trim( $raw ) : '';
	if ( $raw === '' ) {
		return '';
	}
	if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ) {
		return $raw;
	}
	if ( preg_match( '/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $raw, $m ) ) {
		$d  = (int) $m[1];
		$mo = (int) $m[2];
		$y  = (int) $m[3];
		if ( checkdate( $mo, $d, $y ) ) {
			return sprintf( '%04d-%02d-%02d', $y, $mo, $d );
		}
	}
	return '';
}

/**
 * Fecha Y-m-d de la cotización (meta, post o hoy).
 *
 * @param array $datos   Datos de la cotización.
 * @param int   $post_id ID del post (opcional).
 * @return string Y-m-d
 */
function centinela_cotizador_fecha_cotizacion_ymd_from_datos( $datos, $post_id = 0 ) {
	if ( is_array( $datos ) && ! empty( $datos['fecha_cotizacion'] ) ) {
		$ymd = centinela_cotizador_sanitize_fecha_cotizacion( (string) $datos['fecha_cotizacion'] );
		if ( $ymd !== '' ) {
			return $ymd;
		}
	}
	$pid = $post_id > 0 ? $post_id : ( is_array( $datos ) && ! empty( $datos['cotizacion_post_id'] ) ? absint( $datos['cotizacion_post_id'] ) : 0 );
	if ( $pid > 0 ) {
		$p = get_post( $pid );
		if ( $p && $p->post_type === 'cotizacion' ) {
			$t = get_post_time( 'Y-m-d', false, $p );
			if ( is_string( $t ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $t ) ) {
				return $t;
			}
		}
	}
	return wp_date( 'Y-m-d' );
}

/**
 * Fecha de cotización para mostrar en PDF/correo (d/m/Y).
 *
 * @param string $ymd Fecha Y-m-d.
 * @return string
 */
function centinela_cotizador_fecha_cotizacion_display_dmY( $ymd ) {
	$ymd = centinela_cotizador_sanitize_fecha_cotizacion( $ymd );
	if ( $ymd === '' ) {
		return wp_date( 'd/m/Y' );
	}
	try {
		$dt = new DateTimeImmutable( $ymd . ' 00:00:00', wp_timezone() );
		return $dt->format( 'd/m/Y' );
	} catch ( Exception $e ) {
		return wp_date( 'd/m/Y' );
	}
}

/**
 * Fecha de emisión mostrada en cotización (PDF/correo).
 *
 * @param array $datos Puede incluir fecha_cotizacion (string), cotizacion_post_id (int).
 * @return string Formato d/m/Y.
 */
function centinela_cotizador_email_fecha_emision( $datos ) {
	$ymd = centinela_cotizador_fecha_cotizacion_ymd_from_datos( is_array( $datos ) ? $datos : array() );
	return centinela_cotizador_fecha_cotizacion_display_dmY( $ymd );
}

/**
 * Diferencia en días entre dos fechas Y-m-d (zona del sitio).
 *
 * @param string $from_ymd Inicio.
 * @param string $to_ymd   Fin.
 * @return int|null Días (to - from) o null si no son válidas.
 */
function centinela_cotizador_diff_days_ymd( $from_ymd, $to_ymd ) {
	$from_ymd = is_string( $from_ymd ) ? trim( $from_ymd ) : '';
	$to_ymd   = is_string( $to_ymd ) ? trim( $to_ymd ) : '';
	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $from_ymd ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $to_ymd ) ) {
		return null;
	}
	try {
		$tz = wp_timezone();
		$a  = new DateTimeImmutable( $from_ymd . ' 00:00:00', $tz );
		$b  = new DateTimeImmutable( $to_ymd . ' 00:00:00', $tz );
		$secs = $b->getTimestamp() - $a->getTimestamp();
		return (int) round( $secs / DAY_IN_SECONDS );
	} catch ( Exception $e ) {
		return null;
	}
}

/**
 * Fecha Y-m-d de creación de la cotización (post) o hoy.
 *
 * @param array $datos Debe incluir cotizacion_post_id si existe post.
 * @return string Y-m-d
 */
function centinela_cotizador_email_fecha_cotizacion_ymd( $datos ) {
	return centinela_cotizador_fecha_cotizacion_ymd_from_datos( is_array( $datos ) ? $datos : array() );
}

/**
 * Mapa ciudad => lista de departamentos/municipios comunes (cotizador admin).
 *
 * @return array<string,string[]>
 */
function centinela_cotizador_ciudad_departamento_map() {
	$map = array(
		'Aguachica'             => array( 'Cesar', 'Ocaña' ),
		'Arauca'                => array( 'Arauca', 'Yopal' ),
		'Armenia'               => array( 'Quindío', 'Pereira' ),
		'Barrancabermeja'       => array( 'Santander', 'Bucaramanga' ),
		'Barranquilla'          => array( 'Atlántico', 'Soledad' ),
		'Bello'                 => array( 'Antioquia', 'Medellín' ),
		'Bogotá D.C.'           => array( 'Bogotá D.C.', 'Cundinamarca', 'Soacha', 'Chía', 'Zipaquirá' ),
		'Bucaramanga'           => array( 'Santander', 'Floridablanca', 'Girón', 'Piedecuesta' ),
		'Buenaventura'          => array( 'Valle del Cauca', 'Cali' ),
		'Cali'                  => array( 'Valle del Cauca', 'Palmira', 'Yumbo' ),
		'Cartagena de Indias'   => array( 'Bolívar', 'Turbaco' ),
		'Cartago'               => array( 'Valle del Cauca', 'Pereira' ),
		'Chía'                  => array( 'Cundinamarca', 'Bogotá D.C.' ),
		'Cúcuta'                => array( 'Norte de Santander', 'Villa del Rosario' ),
		'Duitama'               => array( 'Boyacá', 'Sogamoso' ),
		'Envigado'              => array( 'Antioquia', 'Medellín' ),
		'Facatativá'            => array( 'Cundinamarca', 'Bogotá D.C.' ),
		'Florencia'             => array( 'Caquetá', 'Neiva' ),
		'Floridablanca'         => array( 'Santander', 'Bucaramanga' ),
		'Girardot'              => array( 'Cundinamarca', 'Tolima' ),
		'Girón'                 => array( 'Santander', 'Bucaramanga' ),
		'Ibagué'                => array( 'Tolima', 'Girardot' ),
		'Inírida'               => array( 'Guainía' ),
		'Itagüí'                => array( 'Antioquia', 'Medellín' ),
		'Leticia'               => array( 'Amazonas' ),
		'Manizales'             => array( 'Caldas', 'Pereira' ),
		'Medellín'              => array( 'Antioquia', 'Bello', 'Envigado', 'Itagüí', 'Sabaneta' ),
		'Mitú'                  => array( 'Vaupés' ),
		'Mocoa'                 => array( 'Putumayo' ),
		'Montería'              => array( 'Córdoba', 'Sincelejo' ),
		'Neiva'                 => array( 'Huila', 'Florencia' ),
		'Ocaña'                 => array( 'Norte de Santander', 'Cesar' ),
		'Palmira'               => array( 'Valle del Cauca', 'Cali' ),
		'Pasto'                 => array( 'Nariño', 'Ipiales' ),
		'Pereira'               => array( 'Risaralda', 'Dosquebradas', 'Armenia', 'Manizales' ),
		'Piedecuesta'           => array( 'Santander', 'Bucaramanga' ),
		'Popayán'               => array( 'Cauca', 'Cali' ),
		'Puerto Carreño'        => array( 'Vichada' ),
		'Quibdó'                => array( 'Chocó' ),
		'Riohacha'              => array( 'La Guajira', 'Maicao' ),
		'San Andrés'            => array( 'San Andrés y Providencia' ),
		'San José del Guaviare' => array( 'Guaviare' ),
		'Santa Marta'           => array( 'Magdalena', 'Barranquilla' ),
		'Sincelejo'             => array( 'Sucre', 'Montería' ),
		'Soacha'                => array( 'Cundinamarca', 'Bogotá D.C.' ),
		'Sogamoso'              => array( 'Boyacá', 'Duitama' ),
		'Tuluá'                 => array( 'Valle del Cauca', 'Cali' ),
		'Tunja'                 => array( 'Boyacá', 'Duitama', 'Sogamoso' ),
		'Valledupar'            => array( 'Cesar', 'La Guajira' ),
		'Villavicencio'         => array( 'Meta', 'Bogotá D.C.' ),
		'Yopal'                 => array( 'Casanare', 'Arauca' ),
		'Zipaquirá'             => array( 'Cundinamarca', 'Bogotá D.C.' ),
	);
	ksort( $map, SORT_NATURAL | SORT_FLAG_CASE );
	return apply_filters( 'centinela_cotizador_ciudad_departamento_map', $map );
}

/**
 * Ciudades de Colombia para el selector de envío (ampliable con filtro).
 *
 * @return string[] Lista ordenada alfabéticamente.
 */
function centinela_cotizador_ciudades_colombia() {
	$ciudades = array_keys( centinela_cotizador_ciudad_departamento_map() );
	sort( $ciudades, SORT_NATURAL | SORT_FLAG_CASE );
	return apply_filters( 'centinela_cotizador_lista_ciudades_colombia', $ciudades );
}

/**
 * Sanitiza el bloque envío de la cotización.
 *
 * @param array $raw Datos envío desde el formulario.
 * @return array
 */
function centinela_cotizador_sanitize_envio( $raw ) {
	$raw = is_array( $raw ) ? $raw : array();
	$emb_allowed = apply_filters( 'centinela_cotizador_embarcar_a_allowed_values', array( 'envio_nacional', 'envio_local', 'recoger_en_tienda' ) );
	$emb_allowed = is_array( $emb_allowed ) ? $emb_allowed : array( 'envio_nacional', 'envio_local', 'recoger_en_tienda' );
	$embarcar    = isset( $raw['embarcar_a'] ) ? sanitize_text_field( (string) $raw['embarcar_a'] ) : '';
	$embarcar    = in_array( $embarcar, $emb_allowed, true ) ? $embarcar : '';

	$via_allowed = apply_filters( 'centinela_cotizador_via_allowed_values', array( 'transportadora_logistica', 'entrega_personalizada' ) );
	$via_allowed = is_array( $via_allowed ) ? $via_allowed : array( 'transportadora_logistica', 'entrega_personalizada' );
	$via         = isset( $raw['via'] ) ? sanitize_text_field( (string) $raw['via'] ) : '';
	$via         = in_array( $via, $via_allowed, true ) ? $via : '';

	$ciudad = isset( $raw['ciudad'] ) ? sanitize_text_field( (string) $raw['ciudad'] ) : '';
	if ( $ciudad !== '' && ! in_array( $ciudad, centinela_cotizador_ciudades_colombia(), true ) ) {
		$ciudad = '';
	}

	return array(
		'embarcar_a'   => $embarcar,
		'via'          => $via,
		'quien_recibe' => isset( $raw['quien_recibe'] ) ? sanitize_text_field( (string) $raw['quien_recibe'] ) : '',
		'direccion'    => isset( $raw['direccion'] ) ? sanitize_text_field( (string) $raw['direccion'] ) : '',
		'ciudad'       => $ciudad,
		'cel'          => isset( $raw['cel'] ) ? sanitize_text_field( (string) $raw['cel'] ) : '',
	);
}

/**
 * URL de la imagen de firma del director comercial (columna izquierda del bloque de firmas).
 *
 * Orden recomendado:
 * 1) Subir PNG/JPG a: wp-content/themes/centinela-group-theme/assets/images/cotizador-firma-director-comercial.png
 *    (o .jpg, .jpeg, .webp con el mismo nombre base).
 * 2) O devolver la URL del adjunto en la biblioteca con el filtro `centinela_cotizador_firma_director_comercial_img_url`.
 *
 * @param array $datos Datos de la cotización (para el filtro).
 * @return string URL absoluta o cadena vacía.
 */
function centinela_cotizador_get_firma_director_comercial_img_url( $datos = array() ) {
	$datos   = is_array( $datos ) ? $datos : array();
	$default = '';
	$dir     = trailingslashit( get_template_directory() ) . 'assets/images/';
	$uri     = trailingslashit( get_template_directory_uri() ) . 'assets/images/';
	foreach ( array( 'cotizador-firma-director-comercial.png', 'cotizador-firma-director-comercial.jpg', 'cotizador-firma-director-comercial.jpeg', 'cotizador-firma-director-comercial.webp' ) as $fn ) {
		if ( is_readable( $dir . $fn ) ) {
			$default = $uri . $fn;
			break;
		}
	}
	return apply_filters( 'centinela_cotizador_firma_director_comercial_img_url', $default, $datos );
}

/**
 * Bloque HTML de firmas (Centinela / cliente) bajo el texto legal de la cotización.
 *
 * @param array $datos Datos de la cotización.
 * @return string HTML (sin wp_kses; lo aplica el fragmento de condiciones).
 */
function centinela_cotizador_email_firmas_cotizacion_fragment( $datos ) {
	$datos = is_array( $datos ) ? $datos : array();

	$url_firma = centinela_cotizador_get_firma_director_comercial_img_url( $datos );
	$nombre    = apply_filters( 'centinela_cotizador_firma_director_nombre', 'Harley Pérez Martín', $datos );
	$cargo     = apply_filters( 'centinela_cotizador_firma_director_cargo', __( 'Director Comercial', 'centinela-group-theme' ), $datos );
	$nombre    = is_string( $nombre ) ? esc_html( $nombre ) : '';
	$cargo     = is_string( $cargo ) ? esc_html( $cargo ) : esc_html( __( 'Director Comercial', 'centinela-group-theme' ) );

	$tbl = 'width:100%;margin-top:1.15em;border-collapse:collapse;table-layout:fixed;font-size:8px;line-height:1.45;color:#111;';
	$tdL = 'width:50%;vertical-align:top;padding:10px 12px 6px 0;text-align:center;border:0;';
	$tdR = 'width:50%;vertical-align:top;padding:10px 0 6px 12px;text-align:center;border:0;border-left:1px solid #ddd;';
	$line = 'border-bottom:1px solid #111;height:0;margin:6px 10px 10px;';
	$box  = 'min-height:78px;margin:0 auto 8px;max-width:260px;border:1px dashed #999;background:#fafafa;text-align:center;';

	if ( $url_firma !== '' ) {
		$img_centinela = '<img src="' . esc_url( $url_firma ) . '" alt="FirmaCentinelaDirector" width="220" height="80" style="max-width:220px;max-height:80px;height:auto;width:auto;display:block;margin:0 auto 8px;" />';
	} else {
		$img_centinela = '<div style="min-height:80px;margin:0 auto 8px;max-width:260px;" aria-hidden="true"></div>';
	}

	$hint_cliente = esc_html__( 'Espacio para su firma o imagen (puede completarlo en Adobe Acrobat u otro editor de PDF).', 'centinela-group-theme' );

	$html  = '<table style="' . esc_attr( $tbl ) . '" role="presentation"><tr>';
	$html .= '<td style="' . esc_attr( $tdL ) . '">';
	$html .= $img_centinela;
	$html .= '<div style="' . esc_attr( $line ) . '"></div>';
	$html .= '<p style="margin:8px 0 2px;padding:0;font-size:9px;font-weight:700;">' . $nombre . '</p>';
	$html .= '<p style="margin:0;padding:0;font-size:8px;">' . $cargo . '</p>';
	$html .= '</td>';
	$html .= '<td style="' . esc_attr( $tdR ) . '">';
	$html .= '<div style="' . esc_attr( $box ) . '"><span style="display:block;padding:10px 8px 6px;font-size:6.5px;line-height:1.35;color:#666;">' . $hint_cliente . '</span></div>';
	$html .= '<div style="' . esc_attr( $line ) . '"></div>';
	$html .= '<table style="width:100%;margin-top:8px;border-collapse:collapse;font-size:9px;" role="presentation"><tr>';
	$html .= '<td style="text-align:left;vertical-align:bottom;padding:2px 6px 0 0;width:32%;font-weight:700;">' . esc_html__( 'Aprobado', 'centinela-group-theme' ) . '</td>';
	$html .= '<td style="text-align:right;vertical-align:bottom;padding:2px 0 0;font-size:10px;white-space:nowrap;">&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;/&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>';
	$html .= '</tr></table>';
	$html .= '<p style="margin:5px 0 0;padding:0;font-size:6.5px;color:#555;text-align:right;">' . esc_html__( 'Escriba la fecha de firma entre las barras (día / mes / año).', 'centinela-group-theme' ) . '</p>';
	$html .= '</td></tr></table>';

	return apply_filters( 'centinela_cotizador_email_firmas_html', $html, $datos );
}

/**
 * Bloque HTML de condiciones comerciales (correo y PDF de cotización).
 *
 * @param array $datos Datos de la cotización.
 * @return string Fragmento HTML (pasará por wp_kses_post al integrarse).
 */
function centinela_cotizador_email_condiciones_comerciales_fragment( $datos ) {
	$datos = is_array( $datos ) ? $datos : array();

	$politicas_url = apply_filters( 'centinela_cotizador_email_politicas_ventas_url', 'https://www.centinelagroup.com/principal/politicas-ventas', $datos );
	$politicas_url = esc_url( $politicas_url );

	$wrap   = 'margin-top:1.35em;padding-top:1.1em;border-top:1px solid #ddd;font-size:7px;line-height:1.55;color:#222;';
	$p_item = 'margin:0.35em 0;padding:0;';
	$p_body = 'margin:0.85em 0;padding:0;';
	$p_caps = 'margin:1em 0 0;padding:0;font-size:7px;line-height:1.5;letter-spacing:0.02em;font-weight:600;color:#111;text-transform:uppercase;';

	$html  = '<div style="' . esc_attr( $wrap ) . '">';
	$html .= '<p style="margin:0 0 0.55em;font-weight:700;text-transform:uppercase;">' . esc_html__( 'Condiciones comerciales:', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '1- Forma de pago :TRANSFERENCIA BANCARIA', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '2- Garantía : 12 meses por defectos de fábrica o de fallas eléctricas y electrónicas en equipos y/o en infraestructura de sistema (hace referencia a los equipos y accesorios instalados por CENTINELA GROUP S.A.S.).', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '3- Soporte: Se agenda por teléfono de 8:30 am a 5:00 pm de lunes a viernes. Aplica solo para apoyo documental.', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '4- Por ningún concepto se realizará devolución del dinero', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '5- Validez de la oferta : 10 Días. El costo final será actualizado a la TRM del día de orden de compra.', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_item ) . '">' . esc_html__( '6- Una vez entregado a satisfacción todo el sistema, las partes firmarán acta de entrega certificando el cumplimiento de las condiciones.', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_body ) . '">' . esc_html__( 'Es de todo Nuestro interés llegar a un acuerdo comercial y realizar este proyecto, como una muestra más de nuestro compromiso con la calidad y satisfacción de nuestros clientes.', 'centinela-group-theme' ) . '</p>';
	$html .= '<p style="' . esc_attr( $p_body ) . '">' . esc_html__( 'Permítanos continuar ampliando nuestra experiencia de nueve (15) años en el mercado con un trabajo confiable y beneficioso para su compañía.', 'centinela-group-theme' ) . '</p>';

	$caps_legal = __(
		'PRECIOS CALCULADOS CON BASE EN LA TRM DIARIA DEL BANCO DE LA REPÚBLICA. LA VIGENCIA DE LA COTIZACIÓN SE INDICA EN EL DOCUMENTO Y, UNA VEZ VENCIDA, DEBERÁ SOLICITARSE ACTUALIZACIÓN CON SU EJECUTIVO DE VENTAS. EL FLETE SERÁ ASUMIDO POR EL CLIENTE CUANDO EL PEDIDO INCLUYA CARGA EXTRA PESADA O SOBREDIMENSIONADA, QUE SUPERE LOS 100 KG DE PESO Y LOS 55 KG DE PESO VOLUMÉTRICO, TENGA UN VALOR INFERIOR A $1.200.000 COP ANTES DE IVA (NO ACUMULABLE). SI EL VALOR DE LA FACTURA NO CUMPLE EL MÍNIMO, EL CLIENTE PUEDE ACCEDER A LAS TARIFAS DE FLETE: $25.000 + IVA (CIUDADES PRINCIPALES) Y $35.000 + IVA (CIUDADES NO PRINCIPALES O FUERA DE PERÍMETRO) SIN SUPERAR LOS 20 KG DE PESO. CENTINELA GROUP DESPACHA A ELECCIÓN INTERNA CON TCC, SERVIENTREGA; PARA OTRAS TRANSPORTADORAS, EL CLIENTE ASUMIRÁ EL COSTO Y/O DEBERÁ COORDINAR LA RECOLECCIÓN EN NUESTRAS SUCURSALES. PARA AMPLIAR INFORMACIÓN DE NUESTRO REGLAMENTO DE ACUERDOS COMERCIALES, DIRÍJASE AQUÍ:',
		'centinela-group-theme'
	);
	$html      .= '<p style="' . esc_attr( $p_caps ) . '">' . esc_html( is_string( $caps_legal ) ? $caps_legal : '' ) . ' ';
	$html      .= '<a style="color:#1a5fb4;text-decoration:underline;word-break:break-all;font-weight:600;" href="' . $politicas_url . '">' . esc_html( $politicas_url ) . '</a></p>';

	$html .= centinela_cotizador_email_firmas_cotizacion_fragment( $datos );

	$html .= '</div>';

	$html = apply_filters( 'centinela_cotizador_email_condiciones_comerciales_html', $html, $datos );
	$html = is_string( $html ) ? $html : '';

	$allowed = array(
		'div'    => array( 'style' => true, 'aria-hidden' => true ),
		'p'      => array( 'style' => true ),
		'a'      => array(
			'href'   => true,
			'style'  => true,
			'target' => true,
			'rel'    => true,
		),
		'strong' => array(),
		'br'     => array(),
		'span'   => array( 'style' => true ),
		'table'  => array( 'style' => true, 'role' => true ),
		'tbody'  => array( 'style' => true ),
		'tr'     => array( 'style' => true ),
		'td'     => array( 'style' => true, 'colspan' => true, 'rowspan' => true ),
		'img'    => array(
			'src'    => true,
			'alt'    => true,
			'style'  => true,
			'width'  => true,
			'height' => true,
		),
	);

	return wp_kses( $html, $allowed );
}

/**
 * Generar cuerpo del correo en HTML optimizado para email (con datos de la cotización)
 *
 * @param array $datos   Datos de la cotización (titulo, productos, cliente, moneda, etc.).
 * @param bool  $for_pdf Si true, pie de página para PDF (no el texto de aviso de correo).
 * @return string HTML del cuerpo del correo.
 */
function centinela_cotizador_build_email_html( $datos, $for_pdf = false ) {
	if ( function_exists( 'centinela_cotizador_recalc_totales_desde_lineas' ) ) {
		$datos = centinela_cotizador_recalc_totales_desde_lineas( $datos );
	}
	$productos   = isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array();
	$cliente     = isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$contacto    = isset( $datos['contacto'] ) && is_array( $datos['contacto'] ) ? $datos['contacto'] : array();
	$moneda      = centinela_cotizador_normalize_moneda( isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' );
	$simbolo     = $moneda === 'USD' ? 'USD $' : 'CO $';
	$subtotal        = isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0;
	$descuento_pct   = isset( $datos['descuento_pct'] ) ? floatval( $datos['descuento_pct'] ) : 0;
	$descuento_valor = isset( $datos['descuento_valor'] ) ? floatval( $datos['descuento_valor'] ) : 0;
	if ( $descuento_pct <= 0 ) {
		$descuento_valor = 0.0;
	}
	$iva_valor       = isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0;
	$total           = isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
	$iva_pct         = isset( $datos['iva_pct'] ) ? floatval( $datos['iva_pct'] ) : 19;
	$tipo_cambio_tabla = isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0;
	$lineas_precio_usd = ( $moneda === 'USD' && $tipo_cambio_tabla > 0 );

	$nombre_cliente   = isset( $cliente['nombre'] ) ? esc_html( $cliente['nombre'] ) : '';
	$telefono_cliente = isset( $cliente['telefono'] ) ? esc_html( $cliente['telefono'] ) : '';
	$nit_cliente      = isset( $cliente['nit_cc'] ) ? esc_html( $cliente['nit_cc'] ) : '';
	$direccion_cliente = isset( $cliente['direccion'] ) ? esc_html( $cliente['direccion'] ) : '';
	$direccion_fisica_cliente = isset( $cliente['direccion_fisica'] ) ? esc_html( $cliente['direccion_fisica'] ) : '';
	$email_cliente    = '';
	if ( isset( $cliente['email'] ) && (string) $cliente['email'] !== '' ) {
		$email_cliente = esc_html( sanitize_email( trim( (string) $cliente['email'] ) ) );
	}
	$ciudad_cli_raw = isset( $cliente['ciudad'] ) ? trim( (string) $cliente['ciudad'] ) : '';
	$ciudad_cliente = ( $ciudad_cli_raw !== '' && in_array( $ciudad_cli_raw, centinela_cotizador_ciudades_colombia(), true ) ) ? esc_html( $ciudad_cli_raw ) : '';
	$vigencia_raw     = isset( $cliente['vigencia'] ) ? trim( (string) $cliente['vigencia'] ) : '';
	$vigencia         = $vigencia_raw !== '' ? esc_html( centinela_cotizador_format_vigencia_fecha( $vigencia_raw ) ) : '';
	$comentarios      = isset( $cliente['comentarios'] ) ? esc_html( $cliente['comentarios'] ) : '';
	$nombre_asesor    = isset( $contacto['nombre'] ) ? esc_html( $contacto['nombre'] ) : '';
	$email_asesor     = isset( $contacto['email'] ) ? esc_html( $contacto['email'] ) : '';
	$telefono_asesor  = isset( $contacto['telefono'] ) ? esc_html( $contacto['telefono'] ) : '';

	$metodo_pago_key = isset( $contacto['metodo_pago'] ) ? sanitize_text_field( (string) $contacto['metodo_pago'] ) : '';
	$metodo_pago_labels = apply_filters(
		'centinela_cotizador_email_metodo_pago_labels',
		array(
			'tarjeta_credito'           => __( 'Tarjeta de Crédito', 'centinela-group-theme' ),
			'tarjeta_debito'            => __( 'Tarjeta de Débito', 'centinela-group-theme' ),
			'contado'                   => __( 'Contado', 'centinela-group-theme' ),
			'cheque_nominativo'         => __( 'Cheque Nominativo', 'centinela-group-theme' ),
			'transferencia_electronica' => __( 'Transferencia electrónica', 'centinela-group-theme' ),
			'pago_a_credito'            => __( 'Pago a crédito', 'centinela-group-theme' ),
		),
		$datos
	);
	$metodo_pago_labels = is_array( $metodo_pago_labels ) ? $metodo_pago_labels : array();
	$metodo_pago_display = '';
	if ( $metodo_pago_key !== '' ) {
		if ( isset( $metodo_pago_labels[ $metodo_pago_key ] ) && is_string( $metodo_pago_labels[ $metodo_pago_key ] ) ) {
			$metodo_pago_display = esc_html( $metodo_pago_labels[ $metodo_pago_key ] );
		} else {
			$metodo_pago_display = esc_html( $metodo_pago_key );
		}
	}
	if ( $metodo_pago_display === '' ) {
		$metodo_pago_display = '—';
	}

	$rows     = '';
	$item_num = 0;
	// table-layout:auto: columnas compactas al texto (nowrap + width:1%); Descripción con min-width para ser siempre la más ancha.
	$td_item  = 'padding:6px 4px;border:1px solid #ddd;text-align:center;vertical-align:middle;white-space:nowrap;font-size:12px;font-weight:700;width:1%;';
	$td_mod   = 'padding:6px 4px;border:1px solid #ddd;text-align:left;vertical-align:middle;font-size:12px;width:1%;max-width:32%;word-wrap:break-word;overflow-wrap:break-word;word-break:break-word;background:#f4f4f4;';
	$td_desc  = 'padding:8px 6px;border:1px solid #ddd;text-align:left;vertical-align:middle;font-size:12px;min-width:48%;width:auto;word-wrap:break-word;overflow-wrap:break-word;';
	$td_num   = 'padding:6px 4px;border:1px solid #ddd;text-align:center;vertical-align:middle;font-size:12px;white-space:nowrap;width:1%;background:#f4f4f4;';
	$td_money = 'padding:6px 4px;border:1px solid #ddd;text-align:center;vertical-align:middle;font-size:12px;white-space:nowrap;width:1%;';
	$td_importe = 'padding:6px 4px;border:1px solid #ddd;text-align:center;vertical-align:middle;font-size:12px;white-space:nowrap;width:1%;background:#f4f4f4;';
	foreach ( $productos as $p ) {
		++$item_num;
		$item_label = (string) $item_num;
		$ref_raw   = isset( $p['referencia'] ) ? trim( (string) $p['referencia'] ) : '';
		$ref_line  = ( $ref_raw !== '' ) ? ( '<strong>' . esc_html( $ref_raw ) . '</strong><br />' ) : '';
		$modelo    = isset( $p['modelo'] ) ? esc_html( (string) $p['modelo'] ) : '';
		$titulo_p  = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : '';
		$desc_html = $titulo_p !== '' ? esc_html( $titulo_p ) : '';
		$cantidad  = isset( $p['cantidad'] ) ? (int) $p['cantidad'] : 0;
		$descuento = isset( $p['descuento'] ) ? floatval( $p['descuento'] ) : 0;
		$precio    = isset( $p['precio'] ) ? floatval( $p['precio'] ) : 0;
		$importe   = isset( $p['importe'] ) ? floatval( $p['importe'] ) : ( $cantidad * $precio * ( 1 - $descuento / 100 ) );
		if ( $lineas_precio_usd ) {
			$precio  = $precio / $tipo_cambio_tabla;
			$importe = $importe / $tipo_cambio_tabla;
		}
		$rows     .= '<tr>';
		$rows     .= '<td style="' . $td_item . '">' . esc_html( $item_label ) . '</td>';
		$rows     .= '<td style="' . $td_mod . '">' . $ref_line . $modelo . '</td>';
		$rows     .= '<td style="' . $td_desc . '">' . $desc_html . '</td>';
		$rows     .= '<td style="' . $td_num . '">' . $cantidad . '</td>';
		$rows     .= '<td style="' . $td_money . '">' . number_format( $precio, 2, ',', '.' ) . '</td>';
		$rows     .= '<td style="' . $td_importe . '">' . number_format( $importe, 2, ',', '.' ) . '</td>';
		$rows     .= '</tr>';
	}

	$logo_url = isset( $datos['logo_url'] ) ? trim( $datos['logo_url'] ) : '';
	if ( $logo_url !== '' && preg_match( '#^https?://#i', $logo_url ) ) {
		$logo_url = esc_url( $logo_url );
	} elseif ( $logo_url !== '' ) {
		$logo_url = esc_url( home_url( $logo_url ) );
	}

	$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#333;max-width:600px;margin:0 auto;padding:20px;">';

	// Cabecera: logo a la izquierda; a la derecha, nombre de empresa centrado en altura respecto al logo (correo + PDF).
	$empresa_cabecera = apply_filters( 'centinela_cotizador_empresa_cabecera', __( 'CENTINELA GROUP S.A.S.', 'centinela-group-theme' ) );
	$empresa_cabecera = is_string( $empresa_cabecera ) ? trim( $empresa_cabecera ) : '';
	if ( $empresa_cabecera === '' ) {
		$empresa_cabecera = 'CENTINELA GROUP S.A.S.';
	}
	$empresa_html = '<span style="font-size:1.15em;font-weight:700;letter-spacing:0.02em;">' . esc_html( $empresa_cabecera ) . '</span>';
	$html        .= '<table style="width:100%;border-collapse:collapse;margin-bottom:1.5em;border:none;border-bottom:1px solid #ddd;" cellpadding="0" cellspacing="0">';
	if ( $logo_url !== '' ) {
		$html .= '<tr>';
		$html .= '<td style="width:42%;vertical-align:middle;text-align:left;padding:0 12px 12px 0;">';
		$html .= '<img src="' . esc_url( $logo_url ) . '" alt="Logo" style="max-width:200px;max-height:65px;width:auto;height:auto;display:block;" />';
		$html .= '</td>';
		$html .= '<td style="vertical-align:middle;text-align:center;padding:0;">' . $empresa_html . '</td>';
		$html .= '</tr>';
	} else {
		$html .= '<tr><td style="vertical-align:middle;text-align:center;padding:0 0 0.5em;">' . $empresa_html . '</td></tr>';
	}
	$html .= '</table>';

	$numero_cot = isset( $datos['numero'] ) ? trim( (string) $datos['numero'] ) : '';
	$numero_txt = $numero_cot !== '' ? esc_html( $numero_cot ) : '—';
	$fecha_emision_txt = esc_html( centinela_cotizador_email_fecha_emision( $datos ) );

	$orden_compra_raw = isset( $datos['orden_compra'] ) ? trim( (string) $datos['orden_compra'] ) : '';
	$orden_compra_raw = strlen( $orden_compra_raw ) > 120 ? substr( $orden_compra_raw, 0, 120 ) : $orden_compra_raw;
	$orden_compra_txt = $orden_compra_raw !== '' ? esc_html( $orden_compra_raw ) : '';

	$empresa_lineas = apply_filters(
		'centinela_cotizador_email_datos_empresa',
		array(
			__( 'CENTINELA GROUP S.A.S.', 'centinela-group-theme' ),
			__( 'NIT: 900619815-9', 'centinela-group-theme' ),
			__( 'AV. CALLE 1 25-38 SANTA ISABEL P2', 'centinela-group-theme' ),
			__( 'Tel: 3043424718 - 3008440523', 'centinela-group-theme' ),
			__( 'www.centinelagroup.com', 'centinela-group-theme' ),
		),
		$datos
	);
	$empresa_lineas = is_array( $empresa_lineas ) ? $empresa_lineas : array();

	$legal_lineas = apply_filters(
		'centinela_cotizador_email_texto_legal',
		array(
			__( 'NO SOMOS AUTORRETENEDORES | IVA RÉGIMEN COMÚN', 'centinela-group-theme' ),
			__( 'ACTIVIDAD ECONÓMICA 4652 | NÚMERO DE AUTORIZACIÓN 18764096196435', 'centinela-group-theme' ),
			__( 'RANGO DESDE: 501 RANGO HASTA: 1000', 'centinela-group-theme' ),
			__( 'FECHA DE VENCIMIENTO 28/07/2027', 'centinela-group-theme' ),
			__( 'ELABORADA E IMPRESA POR CENTINELA GROUP S.A.S.', 'centinela-group-theme' ),
		),
		$datos
	);
	$legal_lineas = is_array( $legal_lineas ) ? $legal_lineas : array();

	// Sin borde lateral/superior: solo línea inferior (#ddd como antes) y sin margen inferior.
	$td_cot_header = 'padding:10px 8px;border:none;border-bottom:1px solid #ddd;vertical-align:middle;font-size:12px;line-height:1.45;';
	$td_cot_header_left = 'padding:10px 8px 10px 0;border:none;border-bottom:1px solid #ddd;vertical-align:middle;font-size:12px;line-height:1.45;';
	// Texto legal: sin padding derecho (más ancho útil; borde inferior igual).
	$td_cot_header_legal = 'padding:10px 0 10px 8px;border:none;border-bottom:1px solid #ddd;vertical-align:middle;font-size:12px;line-height:1.45;';
	$p_cot_meta = 'margin:0 auto;font-size:12px;line-height:1.45;';
	$cot_accent = 'color:#229379;font-weight:700;';
	$html   .= '<table style="width:100%;border-collapse:collapse;margin-bottom:0;table-layout:fixed;border:none;" cellpadding="0" cellspacing="0">';
	$html   .= '<tr>';
	// Columna izquierda: número y orden de compra (el rótulo COTIZACIÓN va encima de toda la sección).
	$html .= '<td style="width:38%;' . $td_cot_header_left . '">';
	$html .= '<p style="' . esc_attr( $p_cot_meta ) . '"><span style="' . esc_attr( $cot_accent ) . '">' . esc_html__( 'COTIZACIÓN', 'centinela-group-theme' ) . ' # ' . $numero_txt . '</span></p>';
	if ( $orden_compra_txt !== '' ) {
		$html .= '<p style="' . esc_attr( $p_cot_meta ) . '"><strong>' . esc_html__( 'ORDEN DE COMPRA:', 'centinela-group-theme' ) . '</strong> ' . $orden_compra_txt . '</p>';
	} else {
		$html .= '<p style="' . esc_attr( $p_cot_meta ) . '"><strong>' . esc_html__( 'ORDEN DE COMPRA', 'centinela-group-theme' ) . '</strong></p>';
	}
	$html .= '<p style="' . esc_attr( $p_cot_meta ) . '"><span style="' . esc_attr( $cot_accent ) . '">' . esc_html__( 'FECHA:', 'centinela-group-theme' ) . '</span> <span style="' . esc_attr( $cot_accent ) . '">' . $fecha_emision_txt . '</span></p>';
	$html .= '<p style="' . esc_attr( $p_cot_meta ) . '"><strong>' . esc_html__( 'MÉTODO DE PAGO:', 'centinela-group-theme' ) . '</strong> ' . $metodo_pago_display . '</p>';
	$html .= '</td>';
	// Columna central: datos empresa (más estrecha para dar espacio a COTIZACIÓN / fecha / pago).
	$html .= '<td style="width:28%;' . $td_cot_header . 'text-align:left;font-size:7px;">';
	$emp_i = 0;
	foreach ( $empresa_lineas as $line ) {
		if ( ! is_string( $line ) || trim( $line ) === '' ) {
			continue;
		}
		$line_t = trim( $line );
		$fw     = ( 0 === $emp_i ) ? 'font-weight:700;' : '';
		$html  .= '<p style="margin:0 0 0.3em;text-align:left;font-size:7px;' . $fw . '">' . esc_html( $line_t ) . '</p>';
		++$emp_i;
	}
	$html .= '</td>';
	// Columna derecha: texto legal en un solo <p> (todas las líneas del filtro, unidas por espacio).
	$html .= '<td style="width:34%;' . $td_cot_header_legal . 'font-size:7px;text-align:left;">';
	$legal_parts = array();
	foreach ( $legal_lineas as $line ) {
		if ( is_string( $line ) && trim( $line ) !== '' ) {
			$legal_parts[] = trim( $line );
		}
	}
	if ( ! empty( $legal_parts ) ) {
		$html .= '<p style="margin:0;font-size:7px;text-align:left;">' . esc_html( implode( ' ', $legal_parts ) ) . '</p>';
	}
	$html .= '</td>';
	$html .= '</tr></table>';

	$envio_raw = isset( $datos['envio'] ) && is_array( $datos['envio'] ) ? $datos['envio'] : array();
	$embarcar_key = isset( $envio_raw['embarcar_a'] ) ? sanitize_text_field( (string) $envio_raw['embarcar_a'] ) : '';
	if ( $embarcar_key === '' && isset( $contacto['embarcar_a'] ) ) {
		$leg = sanitize_text_field( (string) $contacto['embarcar_a'] );
		if ( $leg === 'entrega_local' ) {
			$embarcar_key = 'envio_local';
		} elseif ( in_array( $leg, array( 'envio_nacional', 'envio_local', 'recoger_en_tienda' ), true ) ) {
			$embarcar_key = $leg;
		}
	}
	$embarcar_map = apply_filters(
		'centinela_cotizador_embarcar_a_labels',
		array(
			'entrega_local'      => __( 'Envío local', 'centinela-group-theme' ),
			'envio_nacional'     => __( 'Envío nacional', 'centinela-group-theme' ),
			'envio_local'        => __( 'Envío local', 'centinela-group-theme' ),
			'recoger_en_tienda'  => __( 'Recoger en tienda', 'centinela-group-theme' ),
		),
		$datos
	);
	$embarcar_txt = ( isset( $embarcar_map[ $embarcar_key ] ) && is_string( $embarcar_map[ $embarcar_key ] ) ) ? $embarcar_map[ $embarcar_key ] : '';

	$via_key = isset( $envio_raw['via'] ) ? sanitize_text_field( (string) $envio_raw['via'] ) : '';
	$via_map = apply_filters(
		'centinela_cotizador_via_labels',
		array(
			'transportadora_logistica' => __( 'Transportadora logística', 'centinela-group-theme' ),
			'entrega_personalizada'      => __( 'Entrega personalizada', 'centinela-group-theme' ),
		),
		$datos
	);
	$via_txt = ( isset( $via_map[ $via_key ] ) && is_string( $via_map[ $via_key ] ) ) ? $via_map[ $via_key ] : '';

	$quien_recibe_txt = isset( $envio_raw['quien_recibe'] ) ? trim( (string) $envio_raw['quien_recibe'] ) : '';
	$direccion_env_txt = isset( $envio_raw['direccion'] ) ? trim( (string) $envio_raw['direccion'] ) : '';
	$ciudad_env_txt    = isset( $envio_raw['ciudad'] ) ? trim( (string) $envio_raw['ciudad'] ) : '';
	$cel_env_txt       = isset( $envio_raw['cel'] ) ? trim( (string) $envio_raw['cel'] ) : '';

	// Dos columnas (fiscal/envío y asesor/observaciones): solo línea inferior #ddd, sin fondo.
	$sec_td_dos_cols = 'width:50%;vertical-align:top;padding:12px;border:none;border-bottom:1px solid #ddd;font-size:7px;line-height:1.45;';
	$sec_td_dos_cols_left = 'width:50%;vertical-align:top;padding:12px 12px 12px 0;border:none;border-bottom:1px solid #ddd;font-size:7px;line-height:1.45;';
	$p_fiscal = 'margin:0;font-size:7px;';

	// Fila 1: DATOS FISCALES | DATOS DE ENVÍO (sin guiones "—" si falta información).
	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:0;border:none;" cellpadding="0" cellspacing="0">';
	$html .= '<tr>';
	$html .= '<td style="' . $sec_td_dos_cols_left . '">';
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;font-size:12px;letter-spacing:0.02em;">' . esc_html__( 'DATOS FISCALES', 'centinela-group-theme' ) . '</p>';
	$fiscal_rows = array(
		array( 'label' => __( 'NOMBRE:', 'centinela-group-theme' ), 'val' => $nombre_cliente ),
		array( 'label' => __( 'NIT / C.C.:', 'centinela-group-theme' ), 'val' => $nit_cliente ),
		array( 'label' => __( 'EMAIL:', 'centinela-group-theme' ), 'val' => $email_cliente ),
		array( 'label' => __( 'CEL:', 'centinela-group-theme' ), 'val' => $telefono_cliente ),
		array( 'label' => __( 'DIRECCIÓN:', 'centinela-group-theme' ), 'val' => $direccion_cliente ),
		array( 'label' => __( 'DIRECCIÓN FÍSICA:', 'centinela-group-theme' ), 'val' => $direccion_fisica_cliente ),
		array( 'label' => __( 'CIUDAD:', 'centinela-group-theme' ), 'val' => $ciudad_cliente ),
	);
	foreach ( $fiscal_rows as $fr ) {
		if ( ! isset( $fr['val'] ) || $fr['val'] === '' ) {
			continue;
		}
		$lab = isset( $fr['label'] ) ? (string) $fr['label'] : '';
		$html .= '<p style="' . $p_fiscal . '"><strong>' . esc_html( $lab ) . '</strong> ' . $fr['val'] . '</p>';
	}
	$html .= '</td>';
	$html .= '<td style="' . $sec_td_dos_cols . '">';
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;font-size:12px;letter-spacing:0.02em;">' . esc_html__( 'DATOS DE ENVÍO', 'centinela-group-theme' ) . '</p>';
	$envio_rows = array(
		array(
			'label' => __( 'QUIEN RECIBE:', 'centinela-group-theme' ),
			'val'   => $quien_recibe_txt !== '' ? esc_html( $quien_recibe_txt ) : '',
		),
		array(
			'label' => __( 'DIRECCIÓN:', 'centinela-group-theme' ),
			'val'   => $direccion_env_txt !== '' ? esc_html( $direccion_env_txt ) : '',
		),
		array(
			'label' => __( 'CIUDAD:', 'centinela-group-theme' ),
			'val'   => $ciudad_env_txt !== '' ? esc_html( $ciudad_env_txt ) : '',
		),
		array(
			'label' => __( 'CEL:', 'centinela-group-theme' ),
			'val'   => $cel_env_txt !== '' ? esc_html( $cel_env_txt ) : '',
		),
		array(
			'label' => __( 'EMBARCAR A:', 'centinela-group-theme' ),
			'val'   => $embarcar_txt !== '' ? esc_html( $embarcar_txt ) : '',
		),
		array(
			'label' => __( 'VÍA:', 'centinela-group-theme' ),
			'val'   => $via_txt !== '' ? esc_html( $via_txt ) : '',
		),
	);
	foreach ( $envio_rows as $er ) {
		if ( ! isset( $er['val'] ) || $er['val'] === '' ) {
			continue;
		}
		$elab = isset( $er['label'] ) ? (string) $er['label'] : '';
		$html .= '<p style="' . $p_fiscal . '"><strong>' . esc_html( $elab ) . '</strong> ' . $er['val'] . '</p>';
	}
	$html .= '</td></tr></table>';

	// Fila 2: DATOS DEL ASESOR (izq) | OBSERVACIONES (der).
	$cot_ymd_vig = centinela_cotizador_email_fecha_cotizacion_ymd( $datos );
	$dias_total  = null;
	$dias_plazo  = null;
	if ( $vigencia_raw !== '' && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $vigencia_raw ) ) {
		$dias_total = centinela_cotizador_diff_days_ymd( $cot_ymd_vig, $vigencia_raw );
		$hoy_ymd    = wp_date( 'Y-m-d' );
		$dias_plazo = centinela_cotizador_diff_days_ymd( $hoy_ymd, $vigencia_raw );
		if ( $dias_plazo !== null && $dias_plazo < 0 ) {
			$dias_plazo = 0;
		}
	}

	$p_ase = 'margin:0;font-size:7px;';
	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:0;border:none;" cellpadding="0" cellspacing="0">';
	$html .= '<tr>';
	$html .= '<td style="' . $sec_td_dos_cols_left . '">';
	$html .= '<p style="margin:0 0 0.5em;font-weight:700;font-size:12px;letter-spacing:0.02em;">' . esc_html__( 'DATOS DEL ASESOR', 'centinela-group-theme' ) . '</p>';
	if ( $nombre_asesor !== '' ) {
		$html .= '<p style="' . $p_ase . '"><strong>' . esc_html__( 'EJECUTIVO VENTAS:', 'centinela-group-theme' ) . '</strong> ' . $nombre_asesor . '</p>';
	}
	if ( $email_asesor !== '' ) {
		$html .= '<p style="' . $p_ase . '"><strong>' . esc_html__( 'EMAIL:', 'centinela-group-theme' ) . '</strong> ' . $email_asesor . '</p>';
	}
	if ( $telefono_asesor !== '' ) {
		$html .= '<p style="' . $p_ase . '"><strong>' . esc_html__( 'CEL:', 'centinela-group-theme' ) . '</strong> ' . $telefono_asesor . '</p>';
	}
	if ( $vigencia !== '' ) {
		$html .= '<p style="' . $p_ase . '"><strong>' . esc_html__( 'FECHA DE VENCIMIENTO:', 'centinela-group-theme' ) . '</strong> ' . $vigencia . '</p>';
	}
	if ( $dias_total !== null && $dias_plazo !== null ) {
		$html .= '<p style="' . $p_ase . '"><strong>' . esc_html__( 'VIGENCIA COT:', 'centinela-group-theme' ) . '</strong> ' . (int) $dias_total . ' ' . esc_html__( 'días', 'centinela-group-theme' ) . ' — <strong>' . esc_html__( 'PLAZO:', 'centinela-group-theme' ) . '</strong> ' . (int) $dias_plazo . ' ' . esc_html__( 'días', 'centinela-group-theme' ) . '</p>';
	}
	$html .= '</td>';
	$html .= '<td style="' . $sec_td_dos_cols . '">';
	$html .= '<p style="margin:0 0 0.35em;font-weight:700;font-size:12px;">' . esc_html__( 'OBSERVACIONES:', 'centinela-group-theme' ) . '</p>';
	if ( $comentarios !== '' ) {
		$html .= '<p style="margin:0;font-size:7px;white-space:pre-wrap;">' . $comentarios . '</p>';
	}
	$html .= '</td></tr></table>';

	// Título de la cotización en tabla propia (separada de la tabla de ítems para que los % de columnas se respeten en clientes de correo/PDF).
	$titulo_tabla_cot = isset( $datos['titulo'] ) ? trim( wp_strip_all_tags( (string) $datos['titulo'] ) ) : '';
	if ( $titulo_tabla_cot === '' ) {
		$titulo_tabla_cot = __( 'Cotización', 'centinela-group-theme' );
	}
	$titulo_tabla_cot = apply_filters( 'centinela_cotizador_email_titulo_tabla_productos', $titulo_tabla_cot, $datos );
	$titulo_tabla_cot = is_string( $titulo_tabla_cot ) ? trim( $titulo_tabla_cot ) : '';
	if ( $titulo_tabla_cot === '' ) {
		$titulo_tabla_cot = __( 'Cotización', 'centinela-group-theme' );
	}
	$titulo_tabla_html = esc_html( $titulo_tabla_cot );
	$titulo_bar_td = 'padding:10px 12px;border:1px solid #229379;text-align:center;font-size:16px;font-weight:700;line-height:1.35;text-transform:uppercase;background:#229379;color:#ffffff;';

	$html .= '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:0;">';
	$html .= '<tr><td style="' . $titulo_bar_td . '">' . $titulo_tabla_html . '</td></tr>';
	$html .= '</table>';

	$th_prod = 'padding:8px 6px;border:1px solid #ddd;font-size:12px;line-height:1.35;text-transform:uppercase;vertical-align:middle;';
	$html   .= '<table style="width:100%;border-collapse:collapse;margin:0 auto;table-layout:auto;">';
	$html   .= '<thead>';
	$html   .= '<tr style="background:#f4f4f4;">';
	$html   .= '<th style="' . $th_prod . 'text-align:center;white-space:nowrap;width:1%;">' . esc_html__( 'Item', 'centinela-group-theme' ) . '</th>';
	$html   .= '<th style="' . $th_prod . 'text-align:center;white-space:nowrap;width:1%;">' . esc_html__( 'Modelo', 'centinela-group-theme' ) . '</th>';
	$html   .= '<th style="' . $th_prod . 'text-align:center;min-width:48%;width:auto;word-wrap:break-word;">' . esc_html__( 'Descripción', 'centinela-group-theme' ) . '</th>';
	$html   .= '<th style="' . $th_prod . 'text-align:center;white-space:nowrap;width:1%;">' . esc_html__( 'CANT', 'centinela-group-theme' ) . '</th>';
	$html   .= '<th style="' . $th_prod . 'text-align:center;white-space:nowrap;width:1%;">' . esc_html__( 'Subtotal', 'centinela-group-theme' ) . '</th>';
	$html   .= '<th style="' . $th_prod . 'text-align:center;white-space:nowrap;width:1%;">' . esc_html__( 'Importe', 'centinela-group-theme' ) . '</th>';
	$html   .= '</tr></thead><tbody>' . $rows . '</tbody></table>';

	$cero_fmt = $simbolo . ' ' . number_format( 0, 2, ',', '.' );
	// Sin borde superior: evita doble línea con la tabla de ítems (misma tonalidad #ddd en el resto de lados).
	$tot_td_l = 'width:64%;vertical-align:middle;padding:16px 14px;border-top:none;border-left:1px solid #ddd;border-right:1px solid #ddd;border-bottom:1px solid #ddd;font-size:7px;line-height:1.5;text-align:left;';
	$tot_td_r = 'width:36%;vertical-align:top;padding:0 14px;border-top:none;border-left:none;border-right:1px solid #ddd;border-bottom:1px solid #ddd;';
	/* translators: Preserve the <strong> tags so the account line and company name render bold. */
	$texto_anticipo_default = __( 'Por favor depositar por anticipado en Nuestra cuenta de ahorros <strong>Número 23326193640 BANCOLOMBIA</strong> a nombre de <strong>CENTINELA GROUP S.A.S.</strong> La seguridad del anticipo y la inversión estará respaldada con póliza de Seguro de Suramericana o Seguros del Estado. Costo de póliza adicional.', 'centinela-group-theme' );
	$texto_anticipo         = apply_filters( 'centinela_cotizador_email_texto_anticipo', $texto_anticipo_default, $datos );
	$texto_anticipo         = is_string( $texto_anticipo ) ? $texto_anticipo : $texto_anticipo_default;
	$texto_anticipo_html    = wp_kses_post( $texto_anticipo );

	$fs_r     = '12px';
	$p_tot_r    = 'margin:0;padding:4px 0 0 0;text-align:left;font-size:' . $fs_r . ';line-height:1.45;border-bottom:1px solid #ddd;';
	$p_tot_last = 'margin:0;padding:4px 0 0 0;text-align:left;font-size:' . $fs_r . ';line-height:1.45;border-bottom:none;';

	$html .= '<table style="width:100%;border-collapse:collapse;margin-bottom:1.25em;table-layout:fixed;" cellpadding="0" cellspacing="0">';
	$html .= '<tr>';
	$html .= '<td style="' . $tot_td_l . '">' . $texto_anticipo_html . '</td>';
	$html .= '<td style="' . $tot_td_r . '">';
	$html .= '<p style="' . $p_tot_r . '"><strong style="text-transform:uppercase;">' . esc_html__( 'Subtotal:', 'centinela-group-theme' ) . '</strong> ' . $simbolo . ' ' . number_format( $subtotal, 2, ',', '.' ) . '</p>';
	if ( $descuento_valor > 0 && $descuento_pct > 0 ) {
		$desc_pct_fmt = rtrim( rtrim( number_format( $descuento_pct, 2, ',', '.' ), '0' ), ',' );
		$desc_label   = sprintf(
			/* translators: %s: discount percentage (abbreviated label for PDF totals). */
			__( 'DTO. %s%%:', 'centinela-group-theme' ),
			$desc_pct_fmt
		);
		$html .= '<p style="' . $p_tot_r . '"><strong style="text-transform:uppercase;">' . esc_html( $desc_label ) . '</strong> ' . $simbolo . ' ' . number_format( $descuento_valor, 2, ',', '.' ) . '</p>';
	}
	$html .= '<p style="' . $p_tot_r . '"><strong>' . esc_html__( 'RET.FTE.:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $cero_fmt ) . '</p>';
	$html .= '<p style="' . $p_tot_r . '"><strong>' . esc_html__( 'RET.ICA.:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $cero_fmt ) . '</p>';
	$html .= '<p style="' . $p_tot_r . '"><strong>' . esc_html__( 'I.V.A.', 'centinela-group-theme' ) . ' (' . esc_html( (string) $iva_pct ) . '%):</strong> ' . $simbolo . ' ' . number_format( $iva_valor, 2, ',', '.' ) . '</p>';
	$html .= '<p style="' . $p_tot_r . '"><strong>' . esc_html__( 'RET.IVA:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $cero_fmt ) . '</p>';
	$html .= '<p style="' . $p_tot_last . '"><strong>' . esc_html__( 'TOTAL:', 'centinela-group-theme' ) . ' ' . $simbolo . ' ' . number_format( $total, 2, ',', '.' ) . '</strong></p>';
	$html .= '</td>';
	$html .= '</tr></table>';
	$html .= centinela_cotizador_email_condiciones_comerciales_fragment( $datos );
	$footer_style = 'margin-top:1.5em;color:#666;font-size:12px;';
	if ( $for_pdf ) {
		$html .= '<p style="' . esc_attr( $footer_style ) . '">' . esc_html__( 'Este archivo ha sido generado por CENTINELA GROUP S.A.S.', 'centinela-group-theme' ) . '</p>';
	} else {
		$html .= '<p style="' . esc_attr( $footer_style ) . '">' . esc_html__( 'Este correo está siendo enviado por CENTINELA GROUP S.A.S. Adjunto encontrará la cotización en formato (PDF o Excel).', 'centinela-group-theme' ) . '</p>';
	}
	$html .= '</body></html>';

	return apply_filters( 'centinela_cotizador_email_html', $html, $datos, (bool) $for_pdf );
}

/**
 * Guardar o actualizar cotización como post tipo cotizacion
 *
 * Número visible «COTIZACIÓN # N»: se guarda en meta `_cotizacion_numero` por post y no se reasigna al
 * editar. Al eliminar otras cotizaciones (papelera) los demás posts conservan su N; pueden quedar huecos
 * en la serie. El option `centinela_cotizador_ultimo_numero` solo aumenta al crear posts nuevos; no se
 * decrementa al borrar, así la siguiente cotización nueva sigue después del mayor asignado.
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
	$contacto_safe = isset( $datos['contacto'] ) && is_array( $datos['contacto'] ) ? $datos['contacto'] : array();
	unset( $contacto_safe['embarcar_a'] );

	$envio_in = isset( $datos['envio'] ) && is_array( $datos['envio'] ) ? $datos['envio'] : array();
	$emb_in   = isset( $envio_in['embarcar_a'] ) ? (string) $envio_in['embarcar_a'] : '';
	if ( $emb_in === '' && isset( $datos['contacto']['embarcar_a'] ) ) {
		$leg = sanitize_text_field( (string) $datos['contacto']['embarcar_a'] );
		if ( $leg === 'entrega_local' ) {
			$envio_in['embarcar_a'] = 'envio_local';
		} elseif ( in_array( $leg, array( 'envio_nacional', 'envio_local', 'recoger_en_tienda' ), true ) ) {
			$envio_in['embarcar_a'] = $leg;
		}
	}
	$envio_safe = centinela_cotizador_sanitize_envio( $envio_in );

	$cliente_safe = isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$ciudad_cli   = isset( $cliente_safe['ciudad'] ) ? sanitize_text_field( (string) $cliente_safe['ciudad'] ) : '';
	if ( $ciudad_cli !== '' && ! in_array( $ciudad_cli, centinela_cotizador_ciudades_colombia(), true ) ) {
		$ciudad_cli = '';
	}
	$cliente_safe['ciudad'] = $ciudad_cli;

	if ( isset( $cliente_safe['email'] ) ) {
		$cliente_safe['email'] = sanitize_email( trim( (string) $cliente_safe['email'] ) );
	}
	$ea_extra = isset( $cliente_safe['email_adicionales'] ) ? wp_strip_all_tags( (string) $cliente_safe['email_adicionales'] ) : '';
	$ea_extra = sanitize_text_field( $ea_extra );
	if ( strlen( $ea_extra ) > 500 ) {
		$ea_extra = substr( $ea_extra, 0, 500 );
	}
	$cliente_safe['email_adicionales'] = $ea_extra;

	$orden_compra_save = isset( $datos['orden_compra'] ) ? sanitize_text_field( (string) $datos['orden_compra'] ) : '';
	if ( strlen( $orden_compra_save ) > 120 ) {
		$orden_compra_save = substr( $orden_compra_save, 0, 120 );
	}

	if ( function_exists( 'centinela_cotizador_recalc_totales_desde_lineas' ) ) {
		$datos = centinela_cotizador_recalc_totales_desde_lineas( $datos );
	}

	$fecha_cotizacion_save = isset( $datos['fecha_cotizacion'] ) ? centinela_cotizador_sanitize_fecha_cotizacion( (string) $datos['fecha_cotizacion'] ) : '';
	if ( $fecha_cotizacion_save === '' ) {
		$fecha_cotizacion_save = centinela_cotizador_fecha_cotizacion_ymd_from_datos( $datos, $post_id ? (int) $post_id : 0 );
	}

	$datos_safe = array(
		'titulo'           => $titulo,
		'fecha_cotizacion' => $fecha_cotizacion_save,
		'orden_compra'     => $orden_compra_save,
		'productos'     => isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array(),
		'cliente'       => $cliente_safe,
		'contacto'      => $contacto_safe,
		'envio'         => $envio_safe,
		'moneda'        => centinela_cotizador_normalize_moneda( isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' ),
		'tipo_cambio'   => isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0,
		'tipo_precio'   => isset( $datos['tipo_precio'] ) ? sanitize_text_field( $datos['tipo_precio'] ) : 'lista',
		'iva_pct'         => isset( $datos['iva_pct'] ) ? floatval( $datos['iva_pct'] ) : 19,
		'descuento_pct'   => isset( $datos['descuento_pct'] ) ? floatval( $datos['descuento_pct'] ) : 0,
		'subtotal'        => isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0,
		'descuento_valor' => isset( $datos['descuento_valor'] ) ? floatval( $datos['descuento_valor'] ) : 0,
		'iva_valor'       => isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0,
		'total'           => isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0,
		'logo_url'      => isset( $datos['logo_url'] ) ? esc_url_raw( trim( $datos['logo_url'] ) ) : '',
	);
	update_post_meta( $post_id, '_cotizacion_datos', $datos_safe );

	if ( $post_id > 0 && $fecha_cotizacion_save !== '' ) {
		$hora_actual = wp_date( 'H:i:s' );
		$post_local  = $fecha_cotizacion_save . ' ' . $hora_actual;
		wp_update_post(
			array(
				'ID'            => (int) $post_id,
				'post_date'     => $post_local,
				'post_date_gmt' => get_gmt_from_date( $post_local ),
			)
		);
	}

	return $post_id;
}

/**
 * Crea un nuevo post cotización copiando los datos de uno existente; el título incluye «Copia».
 *
 * @param int $source_id ID del post cotización publicado a duplicar.
 * @return int|WP_Error ID del nuevo post o error.
 */
function centinela_cotizador_duplicate_cotizacion_post( $source_id ) {
	if ( ! centinela_cotizador_can_manage() ) {
		return new WP_Error( 'forbidden', __( 'No autorizado.', 'centinela-group-theme' ) );
	}
	$source_id = absint( $source_id );
	$post      = get_post( $source_id );
	if ( ! $post || $post->post_type !== 'cotizacion' || $post->post_status !== 'publish' ) {
		return new WP_Error( 'invalid', __( 'Cotización no válida.', 'centinela-group-theme' ) );
	}
	$datos = get_post_meta( $source_id, '_cotizacion_datos', true );
	if ( ! is_array( $datos ) ) {
		return new WP_Error( 'nodatos', __( 'No hay datos para duplicar.', 'centinela-group-theme' ) );
	}
	$datos_copy = json_decode( wp_json_encode( $datos ), true );
	if ( ! is_array( $datos_copy ) ) {
		return new WP_Error( 'nodatos', __( 'No hay datos para duplicar.', 'centinela-group-theme' ) );
	}
	$orig_title = isset( $datos_copy['titulo'] ) ? trim( (string) $datos_copy['titulo'] ) : '';
	if ( $orig_title === '' ) {
		$t = get_the_title( $source_id );
		$orig_title = is_string( $t ) ? trim( $t ) : '';
	}
	if ( $orig_title === '' ) {
		$orig_title = __( 'Cotización', 'centinela-group-theme' );
	}
	$copia   = __( 'Copia', 'centinela-group-theme' );
	$suffix  = ' — ' . $copia;
	$max_len = 250;
	if ( strlen( $orig_title . $suffix ) > $max_len ) {
		$ellipsis = '…';
		$keep     = max( 1, $max_len - strlen( $suffix ) - strlen( $ellipsis ) );
		$orig_title = function_exists( 'mb_substr' )
			? mb_substr( $orig_title, 0, $keep, 'UTF-8' )
			: substr( $orig_title, 0, $keep );
		$orig_title .= $ellipsis;
	}
	$datos_copy['titulo'] = $orig_title . $suffix;
	unset( $datos_copy['fecha_cotizacion'] );
	return centinela_cotizador_save_cotizacion( $datos_copy, null );
}

/**
 * Normaliza el código de moneda del cotizador a COP o USD.
 *
 * @param mixed $moneda Valor guardado o recibido por AJAX.
 * @return string 'USD' o 'COP'.
 */
function centinela_cotizador_normalize_moneda( $moneda ) {
	$m = strtoupper( trim( (string) $moneda ) );
	return ( $m === 'USD' ) ? 'USD' : 'COP';
}

/**
 * Base en COP para el descuento global: suma cantidad × precio de líneas marcadas.
 *
 * @param array $productos Líneas de la cotización.
 * @return float
 */
function centinela_cotizador_compute_descuento_base_cop( $productos ) {
	$base = 0.0;
	if ( ! is_array( $productos ) ) {
		return $base;
	}
	foreach ( $productos as $line ) {
		if ( ! is_array( $line ) || empty( $line['descuento_global'] ) ) {
			continue;
		}
		$cant   = isset( $line['cantidad'] ) ? max( 0.0, (float) $line['cantidad'] ) : 0.0;
		$precio = isset( $line['precio'] ) ? max( 0.0, (float) $line['precio'] ) : 0.0;
		$base  += $cant * $precio;
	}
	return $base;
}

/**
 * Base del descuento global: por líneas marcadas, o subtotal completo en cotizaciones antiguas.
 *
 * @param array $productos Líneas de la cotización.
 * @param float $sub_cop   Suma de importes en COP.
 * @return float
 */
function centinela_cotizador_resolve_descuento_base_cop( $productos, $sub_cop ) {
	$base_marcada = centinela_cotizador_compute_descuento_base_cop( $productos );
	if ( $base_marcada > 0 ) {
		return $base_marcada;
	}
	// Sin líneas marcadas: el % aplica sobre el subtotal completo.
	return max( 0.0, (float) $sub_cop );
}

/**
 * Calcula subtotal, descuento global, IVA y total (importes de línea en COP).
 *
 * @param float      $sub_cop            Suma de importes de línea en COP.
 * @param float      $iva_pct            Porcentaje IVA.
 * @param float      $descuento_pct      Porcentaje de descuento global.
 * @param string     $moneda             COP o USD.
 * @param float      $tc                 Tipo de cambio (1 USD = X COP).
 * @param float|null $descuento_base_cop Base del % (líneas marcadas). Null = subtotal completo.
 * @return array{ subtotal: float, descuento_pct: float, descuento_valor: float, iva_valor: float, total: float }
 */
function centinela_cotizador_compute_totales( $sub_cop, $iva_pct, $descuento_pct, $moneda = 'COP', $tc = 0, $descuento_base_cop = null ) {
	$sub_cop       = max( 0.0, (float) $sub_cop );
	$iva_pct       = max( 0.0, min( 100.0, (float) $iva_pct ) );
	$descuento_pct = max( 0.0, min( 100.0, (float) $descuento_pct ) );
	if ( $descuento_base_cop === null ) {
		$descuento_base_cop = $sub_cop;
	} else {
		$descuento_base_cop = max( 0.0, (float) $descuento_base_cop );
	}
	$desc_cop = $descuento_pct > 0 ? $descuento_base_cop * ( $descuento_pct / 100 ) : 0.0;
	$net_cop       = $sub_cop - $desc_cop;
	$iva_cop       = $net_cop * ( $iva_pct / 100 );
	$total_cop     = $net_cop + $iva_cop;
	$moneda        = centinela_cotizador_normalize_moneda( $moneda );
	$tc            = (float) $tc;
	$divisor       = ( $moneda === 'USD' && $tc > 0 ) ? $tc : 1.0;
	return array(
		'subtotal'        => $sub_cop / $divisor,
		'descuento_pct'   => $descuento_pct,
		'descuento_valor' => $desc_cop / $divisor,
		'iva_valor'       => $iva_cop / $divisor,
		'total'           => $total_cop / $divisor,
	);
}

/**
 * Recalcula subtotal / descuento / IVA / total a partir de las líneas (importes en COP del catálogo).
 * Si moneda es USD y hay tipo de cambio > 0, los agregados se guardan en USD (división por TRM).
 *
 * @param array $datos Estructura _cotizacion_datos.
 * @return array Datos con subtotal, descuento_valor, iva_valor, total actualizados.
 */
function centinela_cotizador_recalc_totales_desde_lineas( $datos ) {
	if ( ! is_array( $datos ) ) {
		return $datos;
	}
	$sub = 0.0;
	if ( ! empty( $datos['productos'] ) && is_array( $datos['productos'] ) ) {
		foreach ( $datos['productos'] as $line ) {
			if ( is_array( $line ) && isset( $line['importe'] ) ) {
				$sub += floatval( $line['importe'] );
			}
		}
	}
	$iva_pct       = isset( $datos['iva_pct'] ) ? floatval( $datos['iva_pct'] ) : 19;
	$descuento_pct = isset( $datos['descuento_pct'] ) ? floatval( $datos['descuento_pct'] ) : 0;
	$moneda        = centinela_cotizador_normalize_moneda( isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' );
	$tc            = isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0;
	$base_desc     = centinela_cotizador_resolve_descuento_base_cop( isset( $datos['productos'] ) ? $datos['productos'] : array(), $sub );
	$totales       = centinela_cotizador_compute_totales( $sub, $iva_pct, $descuento_pct, $moneda, $tc, $base_desc );
	$datos['subtotal']        = $totales['subtotal'];
	$datos['descuento_pct']   = $totales['descuento_pct'];
	$datos['descuento_valor'] = $totales['descuento_valor'];
	$datos['iva_valor']       = $totales['iva_valor'];
	$datos['total']           = $totales['total'];
	return $datos;
}

/**
 * Aplica precios desde caché API a líneas con precio ~0 (misma lógica tipo lista/oferta que el cotizador).
 *
 * @param array                $datos         _cotizacion_datos.
 * @param array<string,array> $precios_por_id producto_id => retorno de centinela_syscom_producto_precios_lista_oferta.
 * @return array{ datos: array, changed: bool, lines: int }
 */
function centinela_cotizador_sync_datos_zero_prices( $datos, array $precios_por_id ) {
	if ( ! is_array( $datos ) || empty( $datos['productos'] ) || ! is_array( $datos['productos'] ) ) {
		return array( 'datos' => $datos, 'changed' => false, 'lines' => 0 );
	}
	$tipo_precio = isset( $datos['tipo_precio'] ) ? sanitize_text_field( $datos['tipo_precio'] ) : 'lista';
	$lines       = 0;
	$changed     = false;
	foreach ( $datos['productos'] as $i => $line ) {
		if ( ! is_array( $line ) ) {
			continue;
		}
		if ( ! empty( $line['manual'] ) ) {
			continue;
		}
		$precio = isset( $line['precio'] ) ? floatval( $line['precio'] ) : 0;
		$id     = isset( $line['id'] ) ? trim( (string) $line['id'] ) : '';
		if ( $precio > 0.0001 || $id === '' || ! isset( $precios_por_id[ $id ] ) ) {
			continue;
		}
		$p          = $precios_por_id[ $id ];
		$lista      = isset( $p['precio_lista'] ) ? (float) $p['precio_lista'] : 0.0;
		$oferta     = isset( $p['precio_oferta'] ) ? (float) $p['precio_oferta'] : 0.0;
		$tiene      = ! empty( $p['tiene_oferta'] );
		$new_precio = ( $tipo_precio === 'oferta' && $tiene && $oferta > 0 ) ? $oferta : $lista;
		if ( $new_precio <= 0 ) {
			continue;
		}
		$cantidad = isset( $line['cantidad'] ) ? (int) $line['cantidad'] : 0;
		$desc     = isset( $line['descuento'] ) ? floatval( $line['descuento'] ) : 0;
		$datos['productos'][ $i ]['precio']   = $new_precio;
		$datos['productos'][ $i ]['importe'] = $cantidad * $new_precio * ( 1 - $desc / 100 );
		$lines++;
		$changed = true;
	}
	if ( ! $changed ) {
		return array( 'datos' => $datos, 'changed' => false, 'lines' => 0 );
	}
	$datos = centinela_cotizador_recalc_totales_desde_lineas( $datos );
	return array( 'datos' => $datos, 'changed' => true, 'lines' => $lines );
}

/**
 * Una pasada: todas las cotizaciones publicadas; líneas con precio 0 e ID Syscom numérico se actualizan desde la API.
 *
 * @return array{ posts_updated: int, lines_fixed: int, productos_consultados: int }
 */
function centinela_cotizador_sync_all_zero_prices_from_api() {
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 300 );
	}
	$posts_updated         = 0;
	$lines_fixed           = 0;
	$productos_consultados = 0;
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		return compact( 'posts_updated', 'lines_fixed', 'productos_consultados' );
	}
	$post_ids = get_posts( array(
		'post_type'      => 'cotizacion',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	) );
	if ( empty( $post_ids ) ) {
		return compact( 'posts_updated', 'lines_fixed', 'productos_consultados' );
	}
	$need_ids = array();
	foreach ( $post_ids as $post_id ) {
		$datos = get_post_meta( $post_id, '_cotizacion_datos', true );
		if ( ! is_array( $datos ) || empty( $datos['productos'] ) || ! is_array( $datos['productos'] ) ) {
			continue;
		}
		foreach ( $datos['productos'] as $line ) {
			if ( ! is_array( $line ) ) {
				continue;
			}
			$precio = isset( $line['precio'] ) ? floatval( $line['precio'] ) : 0;
			$id     = isset( $line['id'] ) ? trim( (string) $line['id'] ) : '';
			if ( $precio <= 0.0001 && $id !== '' && preg_match( '/^\d+$/', $id ) ) {
				$need_ids[] = $id;
			}
		}
	}
	$need_ids = array_values( array_unique( $need_ids ) );
	if ( empty( $need_ids ) ) {
		return compact( 'posts_updated', 'lines_fixed', 'productos_consultados' );
	}
	$cache = array();
	foreach ( $need_ids as $pid ) {
		$full = Centinela_Syscom_API::get_producto( $pid, true );
		$productos_consultados++;
		if ( is_wp_error( $full ) || ! is_array( $full ) ) {
			continue;
		}
		if ( function_exists( 'centinela_syscom_producto_precios_lista_oferta' ) ) {
			$cache[ $pid ] = centinela_syscom_producto_precios_lista_oferta( $full, false );
		}
	}
	foreach ( $post_ids as $post_id ) {
		$datos = get_post_meta( $post_id, '_cotizacion_datos', true );
		if ( ! is_array( $datos ) ) {
			continue;
		}
		$r = centinela_cotizador_sync_datos_zero_prices( $datos, $cache );
		if ( $r['changed'] ) {
			update_post_meta( $post_id, '_cotizacion_datos', $r['datos'] );
			$posts_updated++;
			$lines_fixed += (int) $r['lines'];
		}
	}
	return compact( 'posts_updated', 'lines_fixed', 'productos_consultados' );
}

/**
 * Acción admin: sincronizar precios en $0 (Syscom) en cotizaciones guardadas.
 */
function centinela_cotizador_handle_admin_sync_zero_prices() {
	if ( ! centinela_cotizador_can_manage() ) {
		wp_die( esc_html__( 'No autorizado.', 'centinela-group-theme' ) );
	}
	check_admin_referer( 'centinela_cotizador_sync_zero_prices' );
	$stats = centinela_cotizador_sync_all_zero_prices_from_api();
	wp_safe_redirect(
		add_query_arg(
			array(
				'page'                => 'centinela-cotizador-mis-cotizaciones',
				'precios_sync'        => '1',
				'cotiz_actualizadas'  => (int) $stats['posts_updated'],
				'lineas_corregidas'   => (int) $stats['lines_fixed'],
				'productos_api_calls' => (int) $stats['productos_consultados'],
			),
			admin_url( 'admin.php' )
		)
	);
	exit;
}
add_action( 'admin_action_centinela_cotizador_sync_zero_prices', 'centinela_cotizador_handle_admin_sync_zero_prices' );

/**
 * Registrar el menú "Cotizador" y submenú "Mis Cotizaciones" en el admin
 */
function centinela_cotizador_register_menu() {
	add_menu_page(
		__( 'Cotizador', 'centinela-group-theme' ),
		__( 'Cotizador', 'centinela-group-theme' ),
		'centinela_manage_cotizador',
		'centinela-cotizador',
		'centinela_cotizador_render_page',
		'dashicons-calculator',
		56
	);
	add_submenu_page(
		'centinela-cotizador',
		__( 'Mis Cotizaciones', 'centinela-group-theme' ),
		__( 'Mis Cotizaciones', 'centinela-group-theme' ),
		'centinela_manage_cotizador',
		'centinela-cotizador-mis-cotizaciones',
		'centinela_cotizador_render_mis_cotizaciones'
	);
}
add_action( 'admin_menu', 'centinela_cotizador_register_menu' );

/**
 * Restringe el admin para el rol de cotizador: solo páginas del módulo.
 */
function centinela_cotizador_restrict_admin_access() {
	if ( ! is_admin() || ! is_user_logged_in() ) {
		return;
	}
	if ( wp_doing_ajax() ) {
		return;
	}
	if ( ! centinela_cotizador_can_manage() || current_user_can( 'manage_options' ) ) {
		return;
	}
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$allowed_pages = array( 'centinela-cotizador', 'centinela-cotizador-mis-cotizaciones' );
	if ( in_array( $page, $allowed_pages, true ) ) {
		return;
	}
	$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
	if ( $action === 'centinela_cotizador_sync_zero_prices' ) {
		return;
	}
	wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador' ) );
	exit;
}
add_action( 'admin_init', 'centinela_cotizador_restrict_admin_access', 1 );

/**
 * Oculta menús no permitidos para el rol cotizador.
 */
function centinela_cotizador_limit_admin_menu() {
	if ( ! centinela_cotizador_can_manage() || current_user_can( 'manage_options' ) ) {
		return;
	}
	global $menu;
	$allowed_top = array( 'centinela-cotizador' );
	foreach ( (array) $menu as $item ) {
		$slug = isset( $item[2] ) ? (string) $item[2] : '';
		if ( $slug === '' ) {
			continue;
		}
		if ( ! in_array( $slug, $allowed_top, true ) ) {
			remove_menu_page( $slug );
		}
	}
}
add_action( 'admin_menu', 'centinela_cotizador_limit_admin_menu', 999 );

/**
 * WooCommerce puede bloquear /wp-admin para usuarios "no admin" y redirigir a My Account.
 * Permitir explícitamente acceso al rol/cap del cotizador.
 *
 * @param bool $prevent Valor actual de WooCommerce.
 * @return bool
 */
function centinela_cotizador_allow_wp_admin_woocommerce( $prevent ) {
	if ( is_user_logged_in() && centinela_cotizador_can_manage() ) {
		return false;
	}
	return $prevent;
}
add_filter( 'woocommerce_prevent_admin_access', 'centinela_cotizador_allow_wp_admin_woocommerce', 20 );

/**
 * Mostrar admin bar para usuario cotizador en admin (evita comportamientos de plugins que la ocultan por rol).
 *
 * @param bool $disable Valor actual de WooCommerce.
 * @return bool
 */
function centinela_cotizador_keep_admin_bar_woocommerce( $disable ) {
	if ( is_user_logged_in() && centinela_cotizador_can_manage() ) {
		return false;
	}
	return $disable;
}
add_filter( 'woocommerce_disable_admin_bar', 'centinela_cotizador_keep_admin_bar_woocommerce', 20 );

/**
 * Encolar estilos y script solo en la página del Cotizador
 */
function centinela_cotizador_enqueue_assets( $hook_suffix ) {
	if ( $hook_suffix !== 'toplevel_page_centinela-cotizador' ) {
		return;
	}
	wp_enqueue_style(
		'centinela-cotizador-fonts',
		'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	$cotizador_css = get_template_directory() . '/assets/css/cotizador-admin.css';
	wp_enqueue_style(
		'centinela-cotizador-admin',
		get_template_directory_uri() . '/assets/css/cotizador-admin.css',
		array( 'centinela-cotizador-fonts' ),
		file_exists( $cotizador_css ) ? (string) filemtime( $cotizador_css ) : ( defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0' )
	);
	wp_enqueue_media();
	$cotizador_js = get_template_directory() . '/assets/js/cotizador-admin.js';
	wp_enqueue_script(
		'centinela-cotizador-admin',
		get_template_directory_uri() . '/assets/js/cotizador-admin.js',
		array( 'jquery', 'jquery-ui-sortable' ),
		file_exists( $cotizador_js ) ? (string) filemtime( $cotizador_js ) : ( defined( 'CENTINELA_THEME_VERSION' ) ? CENTINELA_THEME_VERSION : '1.0.0' ),
		true
	);
	$logo_default_data = function_exists( 'centinela_cotizador_get_default_logo_data' ) ? centinela_cotizador_get_default_logo_data() : array();
	$logo_default_url  = isset( $logo_default_data['src_full'] ) ? (string) $logo_default_data['src_full'] : '';
	$cotizacion_editar = null;
	$editar_id = isset( $_GET['editar'] ) ? absint( $_GET['editar'] ) : 0;
	if ( $editar_id > 0 ) {
		$post = get_post( $editar_id );
		if ( $post && $post->post_type === 'cotizacion' && $post->post_status === 'publish' ) {
			$datos = get_post_meta( $editar_id, '_cotizacion_datos', true );
			if ( is_array( $datos ) ) {
				if ( empty( $datos['fecha_cotizacion'] ) ) {
					$datos['fecha_cotizacion'] = centinela_cotizador_fecha_cotizacion_ymd_from_datos( $datos, $editar_id );
				}
				$cotizacion_editar = array( 'id' => $editar_id, 'datos' => $datos );
			}
		}
	}
	$show_mail_meta = (bool) (
		( defined( 'CENTINELA_COTIZADOR_DEV_MAIL_META' ) && CENTINELA_COTIZADOR_DEV_MAIL_META )
		|| ( defined( 'CENTINELA_MAIL_DEBUG' ) && CENTINELA_MAIL_DEBUG )
	);
	wp_localize_script( 'centinela-cotizador-admin', 'centinelaCotizador', array(
		'ajax_url'               => admin_url( 'admin-ajax.php' ),
		'mis_cotizaciones_url'   => admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' ),
		'nonce'                  => wp_create_nonce( 'centinela_cotizador' ),
		'iva_default'            => 19,
		'logo_default_url'       => $logo_default_url ? $logo_default_url : '',
		'cotizacion_editar'      => $cotizacion_editar,
		'fecha_hoy'              => wp_date( 'Y-m-d' ),
		'ciudad_departamento_map' => centinela_cotizador_ciudad_departamento_map(),
		'debug_precios_admin' => current_user_can( 'manage_options' ),
		/** wp-config: define( 'CENTINELA_COTIZADOR_DEV_MAIL_META', true ); — consola + bloque JSON en el modal al enviar cotización. */
		'show_mail_meta'     => $show_mail_meta,
		'i18n'              => array(
			'buscar_placeholder_modelo' => __( 'Buscar por modelo…', 'centinela-group-theme' ),
			'buscar_placeholder_titulo'  => __( 'Buscar por título…', 'centinela-group-theme' ),
			'sin_resultados'            => __( 'Sin resultados.', 'centinela-group-theme' ),
			'error_busqueda'             => __( 'Error al buscar. Revisa la API Syscom.', 'centinela-group-theme' ),
			'busqueda_timeout'           => __( 'La búsqueda tardó demasiado. Intenta de nuevo o con un término más corto.', 'centinela-group-theme' ),
			'eliminar'                  => __( 'Eliminar', 'centinela-group-theme' ),
			'importe'                   => __( 'Importe', 'centinela-group-theme' ),
			'actualizar_tc'              => __( 'Actualizar tipo de cambio', 'centinela-group-theme' ),
			'tc_recalculado'             => __( 'Totales actualizados con el tipo de cambio del campo.', 'centinela-group-theme' ),
			'tc_invalido'                => __( 'Indica un tipo de cambio mayor que 0.', 'centinela-group-theme' ),
			'trm_syscom_ok'              => __( 'TRM Syscom cargado. Totales actualizados.', 'centinela-group-theme' ),
			'tc_pending_hint'            => __( 'Has modificado el tipo de cambio. Pulsa «Actualizar» para confirmarlo y alinear referencias en USD y el valor que se guardará al enviar o guardar la cotización.', 'centinela-group-theme' ),
			'tc_confirm_save_prompt'     => __( 'El tipo de cambio cambió y no pulsaste «Actualizar». Los totales en pantalla ya usan el valor del campo; al guardar o enviar se usará ese mismo valor. ¿Quieres continuar? (Recomendado: cancelar, pulsar «Actualizar» y luego guardar.)', 'centinela-group-theme' ),
			'debug_id_required'          => __( 'Introduce un ID numérico de producto Syscom.', 'centinela-group-theme' ),
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
			'departamento'                => __( 'Departamento / Municipio', 'centinela-group-theme' ),
			'seleccione_ciudad'           => __( 'Seleccione una ciudad', 'centinela-group-theme' ),
			'seleccione_departamento'     => __( 'Seleccione un departamento o municipio', 'centinela-group-theme' ),
			'generar_link'                => __( 'Generar link de pago', 'centinela-group-theme' ),
			'link_copiado'                => __( 'Link copiado al portapapeles.', 'centinela-group-theme' ),
			'reenviar_link'               => __( 'Reenviar link por correo', 'centinela-group-theme' ),
			'reenviando_link'             => __( 'Reenviando link…', 'centinela-group-theme' ),
			'link_reenviado_ok'           => __( 'Link de pago reenviado al correo del cliente.', 'centinela-group-theme' ),
			'agregue_productos'           => __( 'Agregue al menos un producto a la cotización.', 'centinela-group-theme' ),
			'descargar_pdf'               => __( 'Descargar PDF', 'centinela-group-theme' ),
			'generando_pdf'               => __( 'Generando PDF…', 'centinela-group-theme' ),
			'pdf_generado_ok'             => __( 'PDF generado. Si no se descargó automáticamente, use el enlace del mensaje.', 'centinela-group-theme' ),
			'error_pdf_adjunto'           => __( 'No se pudo generar el PDF de vista previa.', 'centinela-group-theme' ),
			'aprox_usd_prefix'            => __( '≈ USD $ ', 'centinela-group-theme' ),
			'manual_section_title'        => __( 'Producto manual (sin catálogo API)', 'centinela-group-theme' ),
			'manual_section_help'         => __( 'Añada líneas que no existan en Syscom. El precio se ingresa en COP (como en catálogo): se suma al subtotal y al total en pesos igual que el resto, y en moneda COP también entra en la referencia en USD cuando cambia el TRM.', 'centinela-group-theme' ),
			'manual_ref'                  => __( 'Referencia', 'centinela-group-theme' ),
			'manual_modelo'               => __( 'Modelo / nombre', 'centinela-group-theme' ),
			'manual_descripcion'          => __( 'Descripción (opcional)', 'centinela-group-theme' ),
			'manual_add'                  => __( 'Agregar a la tabla', 'centinela-group-theme' ),
			'manual_edit'                 => __( 'Editar', 'centinela-group-theme' ),
			'manual_update'               => __( 'Guardar', 'centinela-group-theme' ),
			'manual_cancel'               => __( 'Cancelar', 'centinela-group-theme' ),
			'manual_update_tooltip'       => __( 'Guardar cambios del producto manual', 'centinela-group-theme' ),
			'manual_cancel_tooltip'       => __( 'Cancelar edición del producto manual', 'centinela-group-theme' ),
			'manual_error_modelo'         => __( 'Indique el modelo o nombre del producto.', 'centinela-group-theme' ),
			'manual_error_precio'         => __( 'Indique un precio mayor que cero.', 'centinela-group-theme' ),
			'tabla_vacia'                 => __( 'Agrega productos con el buscador o con «Producto manual».', 'centinela-group-theme' ),
			'orden_arrastrar'             => __( 'Arrastrar para cambiar la posición de la línea en la cotización', 'centinela-group-theme' ),
			'descuento_global_col'        => __( 'Dto. cot.', 'centinela-group-theme' ),
			'descuento_global_col_title'  => __( 'Incluir esta línea en la base del descuento global (%)', 'centinela-group-theme' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'centinela_cotizador_enqueue_assets' );

/**
 * AJAX: buscar productos en API Syscom (por modelo o título)
 */
function centinela_cotizador_ajax_buscar_productos() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
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
	if ( function_exists( 'set_time_limit' ) ) {
		@set_time_limit( 50 );
	}

	// Misma lógica que el buscador del sitio, con contexto cotizador (menos llamadas API, sin barrido de catálogo).
	if ( function_exists( 'centinela_search_productos_syscom' ) ) {
		// Referencias/modelo: búsqueda completa (como /tienda/?marca= y search.php con Enter).
		// Modo rápido solo para títulos genéricos; evita perder SKUs tipo DS-2CD2143G2-I.
		$use_fast = ( $tipo !== 'modelo' )
			&& ! ( function_exists( 'centinela_search_query_looks_like_product_reference' )
				&& centinela_search_query_looks_like_product_reference( $busqueda ) );
		$rows      = centinela_search_productos_syscom( $busqueda, 50, $use_fast, 'cotizador' );
		$productos = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$id = isset( $row['id'] ) ? (string) $row['id'] : '';
			if ( $id === '' ) {
				continue;
			}
			$titulo        = isset( $row['titulo'] ) ? trim( (string) $row['titulo'] ) : '';
			$modelo        = isset( $row['modelo'] ) ? trim( (string) $row['modelo'] ) : '';
			$precio_lista  = isset( $row['precio_lista'] ) ? (float) $row['precio_lista'] : 0.0;
			$precio_oferta = isset( $row['precio_oferta'] ) ? (float) $row['precio_oferta'] : 0.0;
			$tiene_oferta  = ! empty( $row['tiene_oferta'] );
			if ( $precio_oferta <= 0 && $precio_lista > 0 ) {
				$precio_oferta = $precio_lista;
			}
			$productos[] = array(
				'id'            => $id,
				'titulo'        => $titulo,
				'modelo'        => $modelo,
				'precio_lista'  => $precio_lista,
				'precio_oferta' => $precio_oferta,
				'tiene_oferta'  => $tiene_oferta,
			);
		}
		$productos = array_slice( $productos, 0, 20 );
		wp_send_json_success( array( 'productos' => $productos ) );
		return;
	}

	// Fallback mínimo si no está cargada inc/centinela-search.php.
	$normalize_for_match = static function ( $value ) {
		$value = strtolower( remove_accents( (string) $value ) );
		return preg_replace( '/[\s\-_\.]+/', '', $value );
	};
	$to_api_busqueda = static function ( $value ) {
		return trim( preg_replace( '/[\s\-_]+/', '+', trim( (string) $value ) ), '+' );
	};
	$variants   = array();
	$compact_alnum = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', remove_accents( $busqueda ) ) );
	if ( $compact_alnum !== '' ) {
		$variants[] = $compact_alnum;
	}
	$variants[] = $to_api_busqueda( $busqueda );
	$variants[] = strtolower( $busqueda );
	$variants[] = $busqueda;
	$variants   = array_values( array_unique( array_filter( array_map( 'trim', $variants ) ) ) );
	$productos_raw = array();
	$ids_vistos    = array();
	foreach ( $variants as $term_api ) {
		for ( $api_pag = 1; $api_pag <= 2; $api_pag++ ) {
			$resp = Centinela_Syscom_API::get_productos( array(
				'busqueda' => $term_api,
				'pagina'   => $api_pag,
				'orden'    => 'relevancia',
				'cop'      => true,
			) );
			if ( is_wp_error( $resp ) ) {
				continue;
			}
			$lista = isset( $resp['productos'] ) && is_array( $resp['productos'] ) ? $resp['productos'] : array();
			if ( empty( $lista ) ) {
				break;
			}
			foreach ( $lista as $p ) {
				if ( ! is_array( $p ) ) {
					continue;
				}
				$pid = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
				$key = $pid !== '' ? (string) $pid : md5( wp_json_encode( $p ) );
				if ( isset( $ids_vistos[ $key ] ) ) {
					continue;
				}
				$productos_raw[]    = $p;
				$ids_vistos[ $key ] = true;
			}
		}
	}
	$tc_syscom = 0.0;
	if ( class_exists( 'Centinela_Syscom_API' ) && method_exists( 'Centinela_Syscom_API', 'get_tipo_cambio_usd_cop' ) ) {
		$tc_x = Centinela_Syscom_API::get_tipo_cambio_usd_cop();
		if ( ! is_wp_error( $tc_x ) ) {
			$tc_syscom = (float) $tc_x;
		}
	}

	$productos = array();
	foreach ( $productos_raw as $p ) {
		if ( ! is_array( $p ) ) {
			continue;
		}
		$id     = isset( $p['producto_id'] ) ? $p['producto_id'] : ( isset( $p['id'] ) ? $p['id'] : '' );
		$titulo = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : '';
		$modelo = isset( $p['modelo'] ) ? trim( (string) $p['modelo'] ) : '';
		if ( $modelo === '' && isset( $p['sku'] ) ) {
			$modelo = trim( (string) $p['sku'] );
		}
		if ( $modelo === '' && isset( $p['codigo'] ) ) {
			$modelo = trim( (string) $p['codigo'] );
		}
		$prices        = function_exists( 'centinela_syscom_producto_precios_lista_oferta' ) ? centinela_syscom_producto_precios_lista_oferta( $p, true ) : array( 'precio_lista' => 0.0, 'precio_oferta' => 0.0, 'tiene_oferta' => false );
		$precio_lista  = isset( $prices['precio_lista'] ) ? (float) $prices['precio_lista'] : 0.0;
		$precio_oferta = isset( $prices['precio_oferta'] ) ? (float) $prices['precio_oferta'] : 0.0;
		$q_norm        = $normalize_for_match( $busqueda );
		$sku_raw       = isset( $p['sku'] ) ? trim( (string) $p['sku'] ) : '';
		$cod_raw       = isset( $p['codigo'] ) ? trim( (string) $p['codigo'] ) : '';
		$haystack      = $normalize_for_match( $titulo . ' ' . $modelo . ' ' . $sku_raw . ' ' . $cod_raw );
		if ( $haystack === '' || strpos( $haystack, $q_norm ) === false ) {
			continue;
		}

		$lista_origen_usd  = false;
		$precio_lista_usd  = 0.0;
		$precio_oferta_usd = 0.0;
		if ( function_exists( 'centinela_syscom_compute_precios_lista_oferta_internal' ) && $tc_syscom > 0 && $precio_lista > 0 ) {
			$internal   = centinela_syscom_compute_precios_lista_oferta_internal( $p );
			$raw_lista  = isset( $internal['precio_lista'] ) ? (float) $internal['precio_lista'] : 0.0;
			$raw_oferta = isset( $internal['precio_oferta'] ) ? (float) $internal['precio_oferta'] : 0.0;
			if ( $raw_lista > 0 && $raw_lista <= 25000 ) {
				$esp_lista_cop = round( $raw_lista * $tc_syscom, 2 );
				$tolerance     = max( 0.02, $esp_lista_cop * 0.02 );
				if ( abs( $precio_lista - $esp_lista_cop ) <= $tolerance ) {
					$lista_origen_usd = true;
					$precio_lista_usd = $raw_lista;
					if ( $raw_oferta > 0 && $raw_oferta < $raw_lista ) {
						$precio_oferta_usd = $raw_oferta;
					}
				}
			}
		}

		$productos[] = array(
			'id'                 => (string) $id,
			'titulo'             => $titulo,
			'modelo'             => $modelo,
			'precio_lista'       => $precio_lista,
			'precio_oferta'      => $precio_oferta > 0 ? $precio_oferta : $precio_lista,
			'tiene_oferta'       => $precio_oferta > 0,
			'lista_origen_usd'   => $lista_origen_usd,
			'precio_lista_usd'   => $precio_lista_usd,
			'precio_oferta_usd'  => $precio_oferta_usd,
		);
	}
	$productos = array_slice( $productos, 0, 20 );
	wp_send_json_success( array( 'productos' => $productos ) );
}
add_action( 'wp_ajax_centinela_cotizador_buscar_productos', 'centinela_cotizador_ajax_buscar_productos' );

/**
 * AJAX: obtener tipo de cambio USD/COP (precio del dólar hoy). Caché 24h.
 */
function centinela_cotizador_ajax_tipo_cambio() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
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
 * Reduce un array de la API a un árbol seguro para depuración (profundidad y tamaño limitados).
 *
 * @param mixed $data   Dato API.
 * @param int   $depth  Profundidad actual.
 * @param int   $max_d  Máx. profundidad.
 * @return mixed
 */
function centinela_cotizador_debug_trim_api_tree( $data, $depth = 0, $max_d = 5 ) {
	if ( $depth >= $max_d ) {
		return '…';
	}
	if ( is_array( $data ) ) {
		$count = 0;
		$out   = array();
		foreach ( $data as $k => $v ) {
			if ( $count >= 40 ) {
				$out['…'] = 'más claves omitidas';
				break;
			}
			$key        = is_string( $k ) || is_int( $k ) ? (string) $k : 'key';
			$out[ $key ] = centinela_cotizador_debug_trim_api_tree( $v, $depth + 1, $max_d );
			$count++;
		}
		return $out;
	}
	if ( is_string( $data ) ) {
		return strlen( $data ) > 160 ? substr( $data, 0, 160 ) . '…' : $data;
	}
	if ( is_bool( $data ) || is_int( $data ) || is_float( $data ) || $data === null ) {
		return $data;
	}
	return gettype( $data );
}

/**
 * AJAX (solo administrador): inspeccionar precios de producto Syscom para depuración.
 */
function centinela_cotizador_ajax_debug_producto_precios() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( 'No autorizado.', 'centinela-group-theme' ) ) );
	}
	$pid = isset( $_POST['producto_id'] ) ? preg_replace( '/[^\d]/', '', (string) wp_unslash( $_POST['producto_id'] ) ) : '';
	if ( $pid === '' ) {
		wp_send_json_error( array( 'message' => __( 'ID de producto no válido.', 'centinela-group-theme' ) ) );
	}
	if ( ! class_exists( 'Centinela_Syscom_API' ) ) {
		wp_send_json_error( array( 'message' => __( 'API Syscom no disponible.', 'centinela-group-theme' ) ) );
	}
	$report = array(
		'producto_id' => $pid,
		'con_cop_1'   => null,
		'con_cop_0'   => null,
	);
	foreach ( array( true, false ) as $cop ) {
		$key = $cop ? 'con_cop_1' : 'con_cop_0';
		$res = Centinela_Syscom_API::get_producto( $pid, $cop );
		if ( is_wp_error( $res ) ) {
			$report[ $key ] = array( 'error' => $res->get_error_message() );
			continue;
		}
		if ( ! is_array( $res ) ) {
			$report[ $key ] = array( 'error' => 'invalid_response' );
			continue;
		}
		$precios = isset( $res['precios'] ) && is_array( $res['precios'] ) ? $res['precios'] : array();
		$parsed  = function_exists( 'centinela_syscom_producto_precios_lista_oferta' )
			? centinela_syscom_producto_precios_lista_oferta( $res, false )
			: array();
		$report[ $key ] = array(
			'claves_raiz'     => array_values( array_slice( array_keys( $res ), 0, 60 ) ),
			'claves_precios'  => is_array( $precios ) ? array_keys( $precios ) : array(),
			'precios_arbol'   => centinela_cotizador_debug_trim_api_tree( $precios, 0, 6 ),
			'precio_parseado' => $parsed,
		);
	}
	wp_send_json_success( array( 'report' => $report ) );
}
add_action( 'wp_ajax_centinela_cotizador_debug_producto_precios', 'centinela_cotizador_ajax_debug_producto_precios' );

/**
 * AJAX: guardar cotización (moneda elegida por el usuario)
 */
function centinela_cotizador_ajax_guardar() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
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
	if ( ! centinela_cotizador_can_manage() ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw  = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	if ( empty( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'Datos de cotización no válidos.', 'centinela-group-theme' ) ) );
	}
	$editar_id = isset( $_POST['editar_id'] ) ? absint( wp_unslash( $_POST['editar_id'] ) ) : 0;
	if ( $editar_id > 0 ) {
		$post_prev = get_post( $editar_id );
		if ( $post_prev && $post_prev->post_type === 'cotizacion' ) {
			$datos['cotizacion_post_id'] = $editar_id;
			$num_meta                    = get_post_meta( $editar_id, '_cotizacion_numero', true );
			if ( $num_meta !== '' && $num_meta !== false ) {
				$datos['numero'] = $num_meta;
			}
		}
	}
	$html = centinela_cotizador_build_email_html( $datos );
	wp_send_json_success( array( 'html' => $html ) );
}
add_action( 'wp_ajax_centinela_cotizador_preview_email', 'centinela_cotizador_ajax_preview_email' );

/**
 * AJAX: enviar cotización por email (HTML + adjunto PDF o Excel) y guardar
 */
/**
 * Adjuntos para wp_mail: nombre limpio en el correo => ruta en disco.
 * Evita nombres tipo cotizacion-1901-xxxxx.tmp.pdf (wp_tempnam) que algunos filtros marcan como sospechosos.
 *
 * @param int    $post_id    ID del post cotización.
 * @param string $abs_path   Ruta absoluta legible.
 * @param string $extension  Extensión sin punto: pdf, html, xlsx, csv.
 * @return array<string,string>
 */
function centinela_cotizador_wp_mail_attachments_map( $post_id, $abs_path, $extension ) {
	$post_id   = (int) $post_id;
	$abs_path  = (string) $abs_path;
	$extension = strtolower( preg_replace( '/[^a-z0-9]/', '', (string) $extension ) );
	if ( $post_id < 1 || $abs_path === '' || ! is_readable( $abs_path ) ) {
		return array();
	}
	if ( $extension === '' ) {
		$extension = 'pdf';
	}
	$num = get_post_meta( $post_id, '_cotizacion_numero', true );
	$num = is_scalar( $num ) ? (string) $num : '';
	$num = preg_replace( '/[^0-9A-Za-z\-_]/', '-', $num );
	$num = trim( $num, '-' );
	if ( $num === '' ) {
		$num = (string) $post_id;
	}
	$name = 'Cotizacion-' . $num . '.' . $extension;
	$name = apply_filters( 'centinela_cotizador_attachment_filename', $name, $post_id, $extension, $abs_path );
	return array( $name => $abs_path );
}

/**
 * Sustituye o añade la cabecera X-Centinela-Cotizacion-Trace (segundo envío en modo split).
 *
 * @param array  $headers Cabeceras como en wp_mail.
 * @param string $trace   Nuevo valor del trace.
 * @return array
 */
function centinela_cotizador_mail_headers_replace_trace( $headers, $trace ) {
	$trace = trim( (string) $trace );
	$out   = array();
	$done  = false;
	foreach ( (array) $headers as $h ) {
		if ( ! is_string( $h ) ) {
			continue;
		}
		if ( preg_match( '/^\s*X-Centinela-Cotizacion-Trace\s*:/i', $h ) ) {
			$out[] = 'X-Centinela-Cotizacion-Trace: ' . $trace;
			$done  = true;
		} else {
			$out[] = $h;
		}
	}
	if ( ! $done ) {
		$out[] = 'X-Centinela-Cotizacion-Trace: ' . $trace;
	}
	return $out;
}

function centinela_cotizador_ajax_enviar_guardar() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
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

	$mail_parse = centinela_cotizador_cliente_emails_para_envio( isset( $datos['cliente'] ) ? $datos['cliente'] : array() );
	if ( $mail_parse['error'] !== '' ) {
		wp_send_json_error( array( 'message' => $mail_parse['error'] ) );
	}
	$email_cliente_primary = $mail_parse['primary'];
	$email_cliente_to      = $mail_parse['to'];

	$datos_guardados = get_post_meta( $post_id, '_cotizacion_datos', true );
	if ( is_array( $datos_guardados ) && ! empty( $datos_guardados ) ) {
		$datos = $datos_guardados;
	}
	$datos['numero']             = get_post_meta( $post_id, '_cotizacion_numero', true );
	$datos['cotizacion_post_id'] = $post_id;
	$titulo_cot = isset( $datos['titulo'] ) && $datos['titulo'] !== '' ? $datos['titulo'] : '';
	$asunto     = $titulo_cot !== ''
		? sprintf( __( 'Cotización Web de Centinela Group - %s', 'centinela-group-theme' ), $titulo_cot )
		: __( 'Cotización Web de Centinela Group', 'centinela-group-theme' );
	$cuerpo_html = centinela_cotizador_build_email_html( $datos );

	$attachments = array();
	if ( $formato_adjunto === 'pdf' ) {
		$pdf_path = apply_filters( 'centinela_cotizador_generar_pdf', '', $post_id, 'default', $datos );
		if ( $pdf_path !== '' ) {
			$abs = realpath( $pdf_path );
			if ( $abs && is_readable( $abs ) ) {
				$attachments = centinela_cotizador_wp_mail_attachments_map( $post_id, $abs, 'pdf' );
			} elseif ( file_exists( $pdf_path ) ) {
				@unlink( $pdf_path );
			}
		}
		if ( empty( $attachments ) ) {
			// Fallback: adjuntar HTML si Dompdf no está disponible para que el cliente reciba la cotización.
			$html_path = centinela_cotizador_adjunto_html_fallback( $post_id, $datos );
			if ( $html_path !== '' ) {
				$abs = realpath( $html_path );
				if ( $abs && is_readable( $abs ) ) {
					$attachments = centinela_cotizador_wp_mail_attachments_map( $post_id, $abs, 'html' );
				} else {
					@unlink( $html_path );
				}
			}
		}
	} elseif ( $formato_adjunto === 'excel' ) {
		$excel_path   = apply_filters( 'centinela_cotizador_generar_excel', '', $post_id, $datos );
		$from_fallback = false;
		if ( $excel_path === '' ) {
			$excel_path    = centinela_cotizador_generar_excel_fallback( $post_id, $datos );
			$from_fallback = true;
		}
		if ( $excel_path !== '' ) {
			$abs = realpath( $excel_path );
			if ( $abs && is_readable( $abs ) ) {
				$ext = ( stripos( $excel_path, '.xlsx' ) !== false ) ? 'xlsx' : 'csv';
				$attachments = centinela_cotizador_wp_mail_attachments_map( $post_id, $abs, $ext );
			} elseif ( $from_fallback && file_exists( $excel_path ) ) {
				@unlink( $excel_path );
			}
		}
	}

	$from_email = apply_filters( 'centinela_cotizador_from_email', 'noreply@centinelagroup.com' );
	$from_name  = apply_filters( 'centinela_cotizador_from_name', 'Centinela Group' );
	$headers    = array(
		'Content-Type: text/html; charset=UTF-8',
		'From: ' . $from_name . ' <' . $from_email . '>',
	);
	// Reply-To: asesor o admin — no el mismo buzón que To (cliente); varios filtros antispam penalizan Reply-To = destinatario.
	$reply_to_default = '';
	$asesor_email_rt  = isset( $datos['contacto']['email'] ) ? sanitize_email( $datos['contacto']['email'] ) : '';
	if ( is_email( $asesor_email_rt ) ) {
		$reply_to_default = $asesor_email_rt;
	} else {
		$admin_rt = sanitize_email( get_option( 'admin_email' ) );
		$reply_to_default = is_email( $admin_rt ) ? $admin_rt : $from_email;
	}
	/**
	 * Email para cabecera Reply-To del envío al cliente.
	 *
	 * @param string $reply_to_default Asesor, admin_email o from_email.
	 * @param string $email_cliente    Email principal del cliente (campo Email; Reply-To no debe coincidir con To).
	 * @param int    $post_id          ID cotización.
	 * @param array  $datos            Payload.
	 * @param string $from_email       Remitente From.
	 */
	$reply_to = apply_filters( 'centinela_cotizador_reply_to_email', $reply_to_default, $email_cliente_primary, $post_id, $datos, $from_email );
	if ( ! is_email( $reply_to ) ) {
		$reply_to = $reply_to_default;
	}
	if ( ! is_email( $reply_to ) ) {
		$reply_to = $from_email;
	}
	$headers[] = 'Reply-To: ' . $reply_to;
	/**
	 * Cabeceras extra para wp_mail del cotizador (Bcc interno, etc.).
	 *
	 * Ejemplo en functions.php del child theme:
	 * add_filter( 'centinela_cotizador_wp_mail_headers', function ( $headers, $ctx ) {
	 *     $headers[] = 'Bcc: copia-interna@tudominio.com';
	 *     return $headers;
	 * }, 10, 2 );
	 *
	 * @param array $headers Cabeceras actuales.
	 * @param array $ctx     cliente_email, post_id, datos, asunto, from_email.
	 */
	$headers = apply_filters(
		'centinela_cotizador_wp_mail_headers',
		$headers,
		array(
			'cliente_email' => implode( ', ', $email_cliente_to ),
			'post_id'       => $post_id,
			'datos'         => $datos,
			'asunto'        => $asunto,
			'from_email'    => $from_email,
		)
	);
	if ( ! is_array( $headers ) ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . $from_name . ' <' . $from_email . '>',
			'Reply-To: ' . $reply_to,
		);
	}

	/**
	 * Sin adjuntos (solo HTML): diagnóstico si proveedores bloquean el PDF o el multipart.
	 *
	 * - wp-config.php (sin tocar el child theme):
	 *   define( 'CENTINELA_COTIZADOR_MAIL_BODY_ONLY', true );
	 * - O filtro:
	 *   add_filter( 'centinela_cotizador_mail_skip_attachments', '__return_true' );
	 *
	 * @param bool  $skip       Por defecto false.
	 * @param int   $post_id    ID cotización.
	 * @param array $datos      Payload.
	 * @param array $attachments Adjuntos tal como se armaron (antes de vaciar).
	 */
	$body_only_mail = ( defined( 'CENTINELA_COTIZADOR_MAIL_BODY_ONLY' ) && CENTINELA_COTIZADOR_MAIL_BODY_ONLY );
	if ( $body_only_mail || (bool) apply_filters( 'centinela_cotizador_mail_skip_attachments', false, $post_id, $datos, $attachments ) ) {
		$attachments = array();
	}

	/**
	 * Dos correos: primero solo HTML, segundo solo adjunto. A veces el filtro saliente bloquea multipart pero acepta mensajes simples.
	 * wp-config: define( 'CENTINELA_COTIZADOR_MAIL_SPLIT_HTML_AND_PDF', true );
	 */
	$split_send = ( defined( 'CENTINELA_COTIZADOR_MAIL_SPLIT_HTML_AND_PDF' ) && CENTINELA_COTIZADOR_MAIL_SPLIT_HTML_AND_PDF )
		&& ! empty( $attachments )
		&& ! $body_only_mail;

	$mail_config_warnings = array();
	if ( $body_only_mail && defined( 'CENTINELA_COTIZADOR_MAIL_SPLIT_HTML_AND_PDF' ) && CENTINELA_COTIZADOR_MAIL_SPLIT_HTML_AND_PDF ) {
		$mail_config_warnings[] = __( 'CENTINELA_COTIZADOR_MAIL_BODY_ONLY elimina el adjunto: el modo SPLIT no puede ejecutarse (no hay PDF). Quite BODY_ONLY para probar dos correos, o quite SPLIT.', 'centinela-group-theme' );
	}

	// Copia oculta al correo de administración de WordPress (wp-config: define( 'CENTINELA_COTIZADOR_BCC_ADMIN', true ); ).
	if ( defined( 'CENTINELA_COTIZADOR_BCC_ADMIN' ) && CENTINELA_COTIZADOR_BCC_ADMIN ) {
		$bcc_admin = sanitize_email( get_option( 'admin_email' ) );
		if ( is_email( $bcc_admin ) ) {
			$bcc_dup = false;
			foreach ( $email_cliente_to as $addr ) {
				if ( strcasecmp( $bcc_admin, $addr ) === 0 ) {
					$bcc_dup = true;
					break;
				}
			}
			if ( ! $bcc_dup ) {
				$headers[] = 'Bcc: ' . $bcc_admin;
			}
		}
	}

	// Rastreo en logs Exim / antispamcloud: pedir al hosting que busquen esta cabecera si el correo no llega.
	$mail_trace = sprintf( '%d-%s-%06d', (int) $post_id, gmdate( 'Ymd\THis\Z' ), wp_rand( 0, 999999 ) );
	$headers[]  = 'X-Centinela-Cotizacion-Trace: ' . $mail_trace;

	$asunto_envio = apply_filters( 'centinela_cotizador_mail_subject_for_send', $asunto, $post_id, $datos );

	/**
	 * Cuerpo HTML muy simple (sin logo, tablas de productos ni totales). Mismo asunto que en producción.
	 * Útil para ver si el filtro antispam reacciona al HTML largo del cotizador.
	 *
	 * wp-config.php: define( 'CENTINELA_COTIZADOR_MAIL_SIMPLE_BODY', true );
	 * Opcional: junto con CENTINELA_COTIZADOR_MAIL_BODY_ONLY para probar sin PDF.
	 */
	if ( defined( 'CENTINELA_COTIZADOR_MAIL_SIMPLE_BODY' ) && CENTINELA_COTIZADOR_MAIL_SIMPLE_BODY ) {
		$num_display = isset( $datos['numero'] ) && (string) $datos['numero'] !== '' ? (string) $datos['numero'] : (string) (int) $post_id;
		$num_safe    = esc_html( $num_display );
		$cuerpo_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;">';
		$cuerpo_html .= '<p>' . esc_html__( 'Hola,', 'centinela-group-theme' ) . '</p>';
		if ( $split_send ) {
			$cuerpo_html .= '<p>' . sprintf(
				/* translators: %s: quotation number. */
				esc_html__( 'Le enviamos la cotización #%s. Recibirá un segundo correo con el archivo adjunto (PDF o Excel).', 'centinela-group-theme' ),
				$num_safe
			) . '</p>';
		} elseif ( $body_only_mail ) {
			$cuerpo_html .= '<p>' . sprintf(
				/* translators: %s: quotation number. */
				esc_html__( 'Le enviamos la cotización #%s por correo (mensaje de prueba sin archivo adjunto).', 'centinela-group-theme' ),
				$num_safe
			) . '</p>';
		} else {
			$cuerpo_html .= '<p>' . sprintf(
				/* translators: %s: quotation number. */
				esc_html__( 'Le enviamos la cotización #%s en el archivo adjunto.', 'centinela-group-theme' ),
				$num_safe
			) . '</p>';
		}
		$cuerpo_html .= '<p>' . esc_html__( 'Saludos cordiales,', 'centinela-group-theme' ) . '<br />' . esc_html__( 'Centinela Group', 'centinela-group-theme' ) . '</p>';
		$cuerpo_html .= '</body></html>';
	}

	// wp-config: define( 'CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST', true ); — asunto genérico + una sola línea (prioridad sobre SIMPLE_BODY).
	if ( defined( 'CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST' ) && CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST ) {
		$cuerpo_html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><p>Prueba envío Centinela (cuerpo mínimo).</p></body></html>';
		$asunto_envio = 'Prueba Centinela cotizador';
	}

	// Forzar asunto (diagnóstico: imitar el correo de prueba de WP Mail SMTP). wp-config: define( 'CENTINELA_COTIZADOR_FORCE_SUBJECT', 'Nota Centinela cotizador' );
	if ( defined( 'CENTINELA_COTIZADOR_FORCE_SUBJECT' ) && is_string( CENTINELA_COTIZADOR_FORCE_SUBJECT ) && CENTINELA_COTIZADOR_FORCE_SUBJECT !== '' ) {
		$asunto_envio = CENTINELA_COTIZADOR_FORCE_SUBJECT;
	}

	if ( $split_send && ! ( defined( 'CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST' ) && CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST ) ) {
		$nota_split = '<p style="color:#555;font-size:13px;margin-top:1em;">' . esc_html__( 'Recibirá un segundo correo con el archivo de la cotización adjunto.', 'centinela-group-theme' ) . '</p>';
		$replaced    = 0;
		$cuerpo_html = str_replace( '</body>', $nota_split . '</body>', $cuerpo_html, $replaced );
		if ( ! $replaced ) {
			$cuerpo_html .= $nota_split;
		}
	}

	$mail_template = 'full';
	if ( defined( 'CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST' ) && CENTINELA_COTIZADOR_MAIL_MINIMAL_TEST ) {
		$mail_template = 'minimal';
	} elseif ( defined( 'CENTINELA_COTIZADOR_MAIL_SIMPLE_BODY' ) && CENTINELA_COTIZADOR_MAIL_SIMPLE_BODY ) {
		$mail_template = 'simple';
	}

	global $centinela_cotizador_last_mail_error;
	$centinela_cotizador_last_mail_error = '';
	add_action( 'wp_mail_failed', 'centinela_cotizador_capture_wp_mail_failed', 10, 1 );

	$mail_trace_pdf = '';
	$envio2         = true;
	$mail_error     = '';
	$mail_error2    = '';

	if ( $split_send ) {
		$envio = wp_mail( $email_cliente_to, $asunto_envio, $cuerpo_html, $headers, array() );
		$mail_error = is_string( $centinela_cotizador_last_mail_error ) ? trim( $centinela_cotizador_last_mail_error ) : '';
		if ( $envio ) {
			$num_subj = isset( $datos['numero'] ) && (string) $datos['numero'] !== '' ? (string) $datos['numero'] : (string) (int) $post_id;
			$asunto_pdf = apply_filters(
				'centinela_cotizador_mail_subject_attachment_mail',
				sprintf(
					/* translators: %s: quotation number. */
					__( 'Cotización #%s - Archivo adjunto', 'centinela-group-theme' ),
					$num_subj
				),
				$post_id,
				$datos,
				$formato_adjunto
			);
			$cuerpo_pdf  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#222;">';
			$cuerpo_pdf .= '<p>' . sprintf(
				/* translators: %s: quotation number. */
				esc_html__( 'Adjunto: archivo de la cotización #%s.', 'centinela-group-theme' ),
				esc_html( $num_subj )
			) . '</p>';
			$cuerpo_pdf .= '<p>' . esc_html__( 'Centinela Group', 'centinela-group-theme' ) . '</p></body></html>';
			$cuerpo_pdf  = apply_filters( 'centinela_cotizador_mail_body_attachment_mail', $cuerpo_pdf, $post_id, $datos, $formato_adjunto );
			$mail_trace_pdf = sprintf( '%d-%s-%06d-a', (int) $post_id, gmdate( 'Ymd\THis\Z' ), wp_rand( 0, 999999 ) );
			$headers_pdf    = centinela_cotizador_mail_headers_replace_trace( $headers, $mail_trace_pdf );
			$centinela_cotizador_last_mail_error = '';
			$envio2 = wp_mail( $email_cliente_to, $asunto_pdf, $cuerpo_pdf, $headers_pdf, $attachments );
			$mail_error2 = is_string( $centinela_cotizador_last_mail_error ) ? trim( $centinela_cotizador_last_mail_error ) : '';
		} else {
			$envio2 = false;
		}
		$envio = $envio && $envio2;
		if ( $mail_error2 !== '' ) {
			$mail_error = trim( $mail_error . ' ' . __( '[Adjunto]', 'centinela-group-theme' ) . ' ' . $mail_error2 );
		}
	} else {
		$envio = wp_mail( $email_cliente_to, $asunto_envio, $cuerpo_html, $headers, $attachments );
		$mail_error = is_string( $centinela_cotizador_last_mail_error ) ? trim( $centinela_cotizador_last_mail_error ) : '';
	}

	remove_action( 'wp_mail_failed', 'centinela_cotizador_capture_wp_mail_failed', 10 );

	if ( $envio ) {
		centinela_cotizador_schedule_action_scheduler_run_on_shutdown();
	}

	if ( ! $envio && $mail_error === '' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Centinela cotizador: wp_mail devolvió false sin wp_mail_failed (revisar SMTP / servidor).' );
	}
	if ( ! $envio && $mail_error !== '' && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( 'Centinela cotizador wp_mail_failed: ' . $mail_error );
	}

	// Borrado diferido: WP Mail SMTP u otros pueden poner el envío en cola; unlink en shutdown rompía el adjunto.
	centinela_cotizador_schedule_attachment_cleanup( $attachments );

	$n_mail_dest = count( $email_cliente_to );
	if ( $split_send && $envio ) {
		if ( $n_mail_dest > 1 ) {
			$msg_ok = sprintf(
				/* translators: %d: number of email recipients. */
				__( 'Cotización guardada. Se enviaron dos correos a %d destinatarios (mensaje y archivo adjunto).', 'centinela-group-theme' ),
				$n_mail_dest
			);
		} else {
			$msg_ok = __( 'Cotización guardada. Se enviaron dos correos al destinatario (mensaje y archivo adjunto).', 'centinela-group-theme' );
		}
	} elseif ( $n_mail_dest > 1 ) {
		$msg_ok = sprintf(
			/* translators: %d: number of email recipients. */
			__( 'Cotización guardada y enviada por email a %d destinatarios.', 'centinela-group-theme' ),
			$n_mail_dest
		);
	} else {
		$msg_ok = __( 'Cotización guardada y enviada por email.', 'centinela-group-theme' );
	}
	$msg_fail = __( 'Cotización guardada. No se pudo enviar el email.', 'centinela-group-theme' );
	if ( ! $envio && $mail_error !== '' ) {
		$msg_fail .= ' ' . __( 'Detalle:', 'centinela-group-theme' ) . ' ' . $mail_error;
	}
	if ( ! empty( $mail_config_warnings ) ) {
		$note = ' ' . implode( ' ', $mail_config_warnings );
		$msg_ok  .= $note;
		$msg_fail .= $note;
	}

	wp_send_json_success( array(
		'id'                 => $post_id,
		'message'            => $envio ? $msg_ok : $msg_fail,
		'enviado'            => (bool) $envio,
		'mail_error'         => $mail_error,
		// Ayuda en Network > Response (admin): confirmar To/From/adjuntos sin depender del plugin SMTP.
		'mail_to'            => implode( ', ', $email_cliente_to ),
		'mail_from'          => $from_email,
		'mail_reply_to'      => $reply_to,
		'mail_trace'         => $mail_trace,
		'mail_trace_pdf'     => $mail_trace_pdf,
		'mail_split_send'    => (bool) $split_send,
		'mail_body_only'     => (bool) $body_only_mail,
		'mail_config_warnings' => $mail_config_warnings,
		'mail_template'      => $mail_template,
		'mail_attachments_n' => count( $attachments ),
	) );
}
add_action( 'wp_ajax_centinela_cotizador_enviar_guardar', 'centinela_cotizador_ajax_enviar_guardar' );

/**
 * AJAX: generar y guardar una copia del correo (cuerpo HTML + adjunto) para revisar en local sin enviar
 */
function centinela_cotizador_ajax_preview_envio() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
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

	$datos_guardados = get_post_meta( $post_id, '_cotizacion_datos', true );
	if ( is_array( $datos_guardados ) && ! empty( $datos_guardados ) ) {
		$datos = $datos_guardados;
	}
	$datos['numero']             = get_post_meta( $post_id, '_cotizacion_numero', true );
	$datos['cotizacion_post_id'] = $post_id;
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
		'id'              => $post_id,
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
	if ( function_exists( 'centinela_cotizador_recalc_totales_desde_lineas' ) ) {
		$datos = centinela_cotizador_recalc_totales_desde_lineas( $datos );
	}
	$productos = isset( $datos['productos'] ) && is_array( $datos['productos'] ) ? $datos['productos'] : array();
	$titulo    = isset( $datos['titulo'] ) ? $datos['titulo'] : __( 'Cotización', 'centinela-group-theme' );
	$moneda    = centinela_cotizador_normalize_moneda( isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' );
	$simbolo   = $moneda === 'USD' ? 'USD $' : 'CO $';
	$subtotal  = isset( $datos['subtotal'] ) ? floatval( $datos['subtotal'] ) : 0;
	$iva_valor = isset( $datos['iva_valor'] ) ? floatval( $datos['iva_valor'] ) : 0;
	$total     = isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
	$tipo_cambio_csv   = isset( $datos['tipo_cambio'] ) ? floatval( $datos['tipo_cambio'] ) : 0;
	$tabla_lineas_usd  = ( $moneda === 'USD' && $tipo_cambio_csv > 0 );
	$numero    = isset( $datos['numero'] ) ? $datos['numero'] : get_post_meta( $post_id, '_cotizacion_numero', true );
	$cliente   = isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$telefono_cliente = isset( $cliente['telefono'] ) ? (string) $cliente['telefono'] : '';
	$nit_cliente      = isset( $cliente['nit_cc'] ) ? (string) $cliente['nit_cc'] : '';
	$sitio_web_cliente = isset( $cliente['sitio_web'] ) ? (string) $cliente['sitio_web'] : '';
	$direccion_cliente = isset( $cliente['direccion'] ) ? (string) $cliente['direccion'] : '';
	$direccion_fisica_cliente = isset( $cliente['direccion_fisica'] ) ? (string) $cliente['direccion_fisica'] : '';
	$ciudad_cliente_csv       = isset( $cliente['ciudad'] ) ? (string) $cliente['ciudad'] : '';
	$vigencia_raw = isset( $cliente['vigencia'] ) ? trim( (string) $cliente['vigencia'] ) : '';
	$vigencia_display = $vigencia_raw !== '' ? centinela_cotizador_format_vigencia_fecha( $vigencia_raw ) : '';

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
	if ( $vigencia_display !== '' ) {
		fputcsv( $fp, array( __( 'Vigencia', 'centinela-group-theme' ), $vigencia_display ), ';' );
		fputcsv( $fp, array( '' ), ';' );
	}
	fputcsv( $fp, array( __( 'Datos del cliente', 'centinela-group-theme' ) ), ';' );
	fputcsv( $fp, array( __( 'Teléfono', 'centinela-group-theme' ), $telefono_cliente ), ';' );
	fputcsv( $fp, array( __( 'NIT / C.C.', 'centinela-group-theme' ), $nit_cliente ), ';' );
	fputcsv( $fp, array( __( 'Sitio web', 'centinela-group-theme' ), $sitio_web_cliente ), ';' );
	fputcsv( $fp, array( __( 'Dirección', 'centinela-group-theme' ), $direccion_cliente ), ';' );
	fputcsv( $fp, array( __( 'Dirección física', 'centinela-group-theme' ), $direccion_fisica_cliente ), ';' );
	fputcsv( $fp, array( __( 'Ciudad', 'centinela-group-theme' ), $ciudad_cliente_csv ), ';' );
	fputcsv( $fp, array( '' ), ';' );
	fputcsv( $fp, array( $titulo ), ';' );
	$orden_csv = isset( $datos['orden_compra'] ) ? trim( (string) $datos['orden_compra'] ) : '';
	if ( strlen( $orden_csv ) > 120 ) {
		$orden_csv = substr( $orden_csv, 0, 120 );
	}
	fputcsv( $fp, array( __( 'Orden de compra', 'centinela-group-theme' ), $orden_csv ), ';' );
	$fecha_csv_ymd = centinela_cotizador_fecha_cotizacion_ymd_from_datos( $datos, $post_id );
	fputcsv( $fp, array( __( 'Fecha', 'centinela-group-theme' ), centinela_cotizador_fecha_cotizacion_display_dmY( $fecha_csv_ymd ) ), ';' );
	fputcsv( $fp, array( '' ), ';' );
	fputcsv( $fp, array( __( 'Item', 'centinela-group-theme' ), __( 'Modelo', 'centinela-group-theme' ), __( 'Descripción', 'centinela-group-theme' ), __( 'Cantidad', 'centinela-group-theme' ), __( 'Subtotal', 'centinela-group-theme' ), __( 'Importe', 'centinela-group-theme' ) ), ';' );
	$item_csv = 0;
	foreach ( $productos as $p ) {
		++$item_csv;
		$item_label = (string) $item_csv;
		$modelo     = isset( $p['modelo'] ) ? $p['modelo'] : '';
		$desc_csv   = isset( $p['titulo'] ) ? trim( (string) $p['titulo'] ) : '';
		$cantidad   = isset( $p['cantidad'] ) ? (int) $p['cantidad'] : 0;
		$descuento  = isset( $p['descuento'] ) ? floatval( $p['descuento'] ) : 0;
		$precio     = isset( $p['precio'] ) ? floatval( $p['precio'] ) : 0;
		$importe    = isset( $p['importe'] ) ? floatval( $p['importe'] ) : ( $cantidad * $precio * ( 1 - $descuento / 100 ) );
		if ( $tabla_lineas_usd ) {
			$precio  = $precio / $tipo_cambio_csv;
			$importe = $importe / $tipo_cambio_csv;
		}
		fputcsv( $fp, array( $item_label, $modelo, $desc_csv, $cantidad, $precio, $importe ), ';' );
	}
	fputcsv( $fp, array( '' ), ';' );
	$pad4 = array( '', '', '', '' );
	$descuento_pct_excel   = isset( $datos['descuento_pct'] ) ? floatval( $datos['descuento_pct'] ) : 0;
	$descuento_valor_excel = isset( $datos['descuento_valor'] ) ? floatval( $datos['descuento_valor'] ) : 0;
	fputcsv( $fp, array_merge( array( __( 'Subtotal', 'centinela-group-theme' ) ), $pad4, array( $simbolo . ' ' . number_format( $subtotal, 2, ',', '.' ) ) ), ';' );
	if ( $descuento_valor_excel > 0 && $descuento_pct_excel > 0 ) {
		$desc_row_label = $descuento_pct_excel > 0
			? sprintf( __( 'DTO. %s%%', 'centinela-group-theme' ), rtrim( rtrim( number_format( $descuento_pct_excel, 2, ',', '.' ), '0' ), ',' ) )
			: __( 'DTO.', 'centinela-group-theme' );
		fputcsv( $fp, array_merge( array( $desc_row_label ), $pad4, array( $simbolo . ' ' . number_format( $descuento_valor_excel, 2, ',', '.' ) ) ), ';' );
	}
	fputcsv( $fp, array_merge( array( __( 'I.V.A.', 'centinela-group-theme' ) ), $pad4, array( $simbolo . ' ' . number_format( $iva_valor, 2, ',', '.' ) ) ), ';' );
	fputcsv( $fp, array_merge( array( __( 'TOTAL', 'centinela-group-theme' ) ), $pad4, array( $simbolo . ' ' . number_format( $total, 2, ',', '.' ) ) ), ';' );
	fclose( $fp );
	return $out;
}

/**
 * Lee bytes del logo (ruta local o HTTP) y devuelve data URI para incrustar en HTML de Dompdf.
 *
 * @param string $url URL absoluta o ruta relativa al sitio.
 * @return string data:image/...;base64,... o vacío.
 */
function centinela_cotizador_logo_url_to_data_uri( $url ) {
	$url = trim( (string) $url );
	if ( $url === '' ) {
		return '';
	}
	if ( ! preg_match( '#^https?://#i', $url ) ) {
		$url = home_url( '/' . ltrim( str_replace( '\\', '/', $url ), '/' ) );
	}
	$url = esc_url_raw( $url );

	$binary    = null;
	$file_read = '';

	$uploads = wp_upload_dir();
	if ( empty( $uploads['error'] ) && ! empty( $uploads['baseurl'] ) && ! empty( $uploads['basedir'] ) && strpos( $url, $uploads['baseurl'] ) === 0 ) {
		$candidate = wp_normalize_path( str_replace( $uploads['baseurl'], $uploads['basedir'], $url ) );
		if ( is_readable( $candidate ) ) {
			$binary    = @file_get_contents( $candidate );
			$file_read = $candidate;
		}
	}
	if ( ( $binary === null || $binary === false || $binary === '' ) && strpos( $url, content_url() ) === 0 ) {
		$candidate = wp_normalize_path( str_replace( content_url(), WP_CONTENT_DIR, $url ) );
		if ( is_readable( $candidate ) ) {
			$binary    = @file_get_contents( $candidate );
			$file_read = $candidate;
		}
	}
	$site = site_url( '/' );
	if ( ( $binary === null || $binary === false || $binary === '' ) && strpos( $url, $site ) === 0 ) {
		$rel       = (string) substr( $url, strlen( $site ) );
		$candidate = wp_normalize_path( ABSPATH . ltrim( $rel, '/' ) );
		if ( is_readable( $candidate ) ) {
			$binary    = @file_get_contents( $candidate );
			$file_read = $candidate;
		}
	}
	$home = home_url( '/' );
	if ( ( $binary === null || $binary === false || $binary === '' ) && $home !== $site && strpos( $url, $home ) === 0 ) {
		$rel       = (string) substr( $url, strlen( $home ) );
		$candidate = wp_normalize_path( ABSPATH . ltrim( $rel, '/' ) );
		if ( is_readable( $candidate ) ) {
			$binary    = @file_get_contents( $candidate );
			$file_read = $candidate;
		}
	}

	$mime = 'image/png';
	if ( $binary === null || $binary === false || $binary === '' ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 25,
				'redirection' => 3,
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', true ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return '';
		}
		$binary = wp_remote_retrieve_body( $response );
		if ( $binary === null || $binary === false || $binary === '' ) {
			return '';
		}
		$ct = wp_remote_retrieve_header( $response, 'content-type' );
		if ( is_string( $ct ) && preg_match( '#\bimage/(png|jpe?g|gif|webp)\b#i', $ct, $m ) ) {
			$ext = strtolower( $m[1] );
			$mime = ( $ext === 'jpg' || $ext === 'jpeg' ) ? 'image/jpeg' : 'image/' . $ext;
		} else {
			$path_part = (string) parse_url( $url, PHP_URL_PATH );
			$ft        = wp_check_filetype( $path_part );
			if ( ! empty( $ft['type'] ) ) {
				$mime = $ft['type'];
			}
		}
	} elseif ( $file_read !== '' ) {
		$ft = wp_check_filetype( $file_read );
		if ( ! empty( $ft['type'] ) ) {
			$mime = $ft['type'];
		}
	} else {
		$path_part = (string) parse_url( $url, PHP_URL_PATH );
		$ft        = wp_check_filetype( $path_part );
		if ( ! empty( $ft['type'] ) ) {
			$mime = $ft['type'];
		}
	}

	if ( ! preg_match( '#^image/(png|jpeg|gif|webp)$#i', $mime ) ) {
		$mime = 'image/png';
	}

	return 'data:' . $mime . ';base64,' . base64_encode( (string) $binary );
}

/**
 * Sustituye la etiqueta img del logo en el HTML del correo por una con src en data URI (Dompdf).
 *
 * @param string $html  HTML completo.
 * @param array  $datos Debe incluir logo_url si hay logo.
 * @return string HTML modificado o el mismo si no aplica.
 */
function centinela_cotizador_pdf_html_embed_logo_data_uri( $html, $datos ) {
	if ( ! is_string( $html ) || $html === '' || strpos( $html, 'alt="Logo"' ) === false ) {
		return $html;
	}
	$logo_url = isset( $datos['logo_url'] ) ? trim( (string) $datos['logo_url'] ) : '';
	if ( $logo_url === '' ) {
		return $html;
	}
	$data_uri = centinela_cotizador_logo_url_to_data_uri( $logo_url );
	if ( $data_uri === '' ) {
		return $html;
	}
	$replaced = preg_replace( '/<img\s+src="[^"]*"\s+alt="Logo"/i', '<img src="' . esc_attr( $data_uri ) . '" alt="Logo"', $html, 1 );

	return is_string( $replaced ) ? $replaced : $html;
}

/**
 * Sustituye la imagen de firma del director por data URI para Dompdf (misma lógica que el logo).
 *
 * @param string $html  HTML completo.
 * @param array  $datos Datos de la cotización.
 * @return string
 */
function centinela_cotizador_pdf_html_embed_firma_director_data_uri( $html, $datos ) {
	if ( ! is_string( $html ) || $html === '' || stripos( $html, 'FirmaCentinelaDirector' ) === false ) {
		return $html;
	}
	$url = centinela_cotizador_get_firma_director_comercial_img_url( is_array( $datos ) ? $datos : array() );
	if ( $url === '' ) {
		return $html;
	}
	$data_uri = centinela_cotizador_logo_url_to_data_uri( $url );
	if ( $data_uri === '' ) {
		return $html;
	}
	$replaced = preg_replace(
		'/<img\b[^>]*\balt\s*=\s*([\'"])FirmaCentinelaDirector\1[^>]*>/i',
		'<img src="' . esc_attr( $data_uri ) . '" alt="FirmaCentinelaDirector" width="220" height="80" style="max-width:220px;max-height:80px;height:auto;width:auto;display:block;margin:0 auto 8px;" />',
		$html,
		1
	);
	return is_string( $replaced ) ? $replaced : $html;
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
	// Cargar Dompdf desde el tema si existe vendor (composer install en el tema).
	$autoload = get_template_directory() . '/vendor/autoload.php';
	if ( ! class_exists( '\Dompdf\Dompdf' ) && file_exists( $autoload ) ) {
		require_once $autoload;
	}
	if ( ! class_exists( '\Dompdf\Dompdf' ) ) {
		return '';
	}
	$datos['numero']             = isset( $datos['numero'] ) ? $datos['numero'] : get_post_meta( $post_id, '_cotizacion_numero', true );
	$datos['cotizacion_post_id'] = (int) $post_id;
	$html = centinela_cotizador_build_email_html( $datos, true );
	$html = centinela_cotizador_pdf_html_embed_logo_data_uri( $html, $datos );
	$html = centinela_cotizador_pdf_html_embed_firma_director_data_uri( $html, $datos );
	$tmp  = wp_tempnam( 'cotizacion-' . $post_id );
	if ( ! $tmp ) {
		return '';
	}
	@unlink( $tmp );
	$out = $tmp . '.pdf';
	try {
		$ud              = wp_upload_dir();
		$upload_basedir  = ( empty( $ud['error'] ) && ! empty( $ud['basedir'] ) ) ? wp_normalize_path( $ud['basedir'] ) : '';
		$chroot_dirs     = array_unique(
			array_filter(
				array(
					ABSPATH,
					WP_CONTENT_DIR,
					$upload_basedir,
				)
			)
		);
		$dompdf = new \Dompdf\Dompdf(
			array(
				'isRemoteEnabled' => true,
				'chroot'          => $chroot_dirs,
			)
		);
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
 * Elimina archivos temporales de cotización (callback de WP-Cron).
 *
 * @param array $paths Rutas absolutas.
 */
function centinela_cotizador_run_attachment_cleanup( $paths ) {
	if ( empty( $paths ) || ! is_array( $paths ) ) {
		return;
	}
	foreach ( $paths as $path ) {
		if ( is_string( $path ) && $path !== '' && file_exists( $path ) && is_writable( $path ) ) {
			@unlink( $path );
		}
	}
}
add_action( 'centinela_cotizador_cleanup_attachments', 'centinela_cotizador_run_attachment_cleanup', 10, 1 );

/**
 * Guarda el último error de PHPMailer cuando falla wp_mail (para mostrarlo en el admin).
 *
 * @param WP_Error $wp_error Error de wp_mail.
 */
function centinela_cotizador_capture_wp_mail_failed( $wp_error ) {
	global $centinela_cotizador_last_mail_error;
	if ( $wp_error instanceof WP_Error ) {
		$centinela_cotizador_last_mail_error = $wp_error->get_error_message();
	}
}

/**
 * Programa la eliminación de adjuntos con retraso (WP-Cron).
 * Evita borrar PDF/HTML antes de que plugins SMTP (cola / segundo pase) lean el archivo.
 *
 * @param array $paths Lista de rutas absolutas a borrar.
 */
function centinela_cotizador_schedule_attachment_cleanup( $paths ) {
	if ( empty( $paths ) || ! is_array( $paths ) ) {
		return;
	}
	$paths = array_values( array_filter( array_map( 'realpath', $paths ) ) );
	if ( empty( $paths ) ) {
		return;
	}
	$delay = (int) apply_filters( 'centinela_cotizador_attachment_cleanup_delay_seconds', 300 );
	$delay = max( 60, min( 3600, $delay ) );
	wp_schedule_single_event( time() + $delay, 'centinela_cotizador_cleanup_attachments', array( $paths ) );
}

/**
 * Fallback cuando no hay PDF: genera un archivo HTML con el cuerpo del correo para adjuntar.
 *
 * @param int   $post_id ID del post cotización.
 * @param array $datos   Datos de la cotización.
 * @return string Ruta al archivo .html temporal o vacío.
 */
function centinela_cotizador_adjunto_html_fallback( $post_id, $datos ) {
	$datos['numero']             = isset( $datos['numero'] ) ? $datos['numero'] : get_post_meta( $post_id, '_cotizacion_numero', true );
	$datos['cotizacion_post_id'] = (int) $post_id;
	$html = centinela_cotizador_build_email_html( $datos );
	$tmp  = wp_tempnam( 'cotizacion-html-' . $post_id );
	if ( ! $tmp ) {
		return '';
	}
	@unlink( $tmp );
	$out = $tmp . '.html';
	$full_html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Cotización</title></head><body>' . $html . '</body></html>';
	if ( file_put_contents( $out, $full_html ) === false ) {
		return '';
	}
	return $out;
}

/**
 * AJAX: generar link de pago (crear pedido WC y devolver URL Wompi)
 */
function centinela_cotizador_send_payment_link_email( $datos, $pay_url, $order_id = 0 ) {
	$datos   = is_array( $datos ) ? $datos : array();
	$pay_url = esc_url_raw( trim( (string) $pay_url ) );
	if ( $pay_url === '' ) {
		return false;
	}
	$cliente = isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$email_pack = centinela_cotizador_cliente_emails_para_envio( $cliente );
	if ( ! empty( $email_pack['error'] ) || empty( $email_pack['to'] ) ) {
		return false;
	}
	$titulo = isset( $datos['titulo'] ) ? sanitize_text_field( (string) $datos['titulo'] ) : '';
	if ( $titulo === '' ) {
		$titulo = __( 'Cotización', 'centinela-group-theme' );
	}
	$nombre = isset( $cliente['nombre'] ) ? sanitize_text_field( (string) $cliente['nombre'] ) : '';
	$saludo = $nombre !== '' ? $nombre : __( 'Cliente', 'centinela-group-theme' );
	$subject = sprintf(
		/* translators: %s: quote title */
		__( 'Link de pago de tu cotización: %s', 'centinela-group-theme' ),
		$titulo
	);
	$order_txt = $order_id > 0 ? sprintf( __( 'Pedido #%d', 'centinela-group-theme' ), (int) $order_id ) : '';
	$body  = '<p>' . esc_html( sprintf( __( 'Hola %s,', 'centinela-group-theme' ), $saludo ) ) . '</p>';
	$body .= '<p>' . esc_html__( 'Tu link de pago ya está listo para completar la compra de tu cotización aprobada.', 'centinela-group-theme' ) . '</p>';
	$body .= '<p><strong>' . esc_html__( 'Cotización:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $titulo ) . '</p>';
	if ( $order_txt !== '' ) {
		$body .= '<p><strong>' . esc_html__( 'Referencia:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $order_txt ) . '</p>';
	}
	$body .= '<p><a href="' . esc_url( $pay_url ) . '" style="display:inline-block;padding:10px 14px;background:#229379;color:#fff;text-decoration:none;border-radius:4px;">' . esc_html__( 'Pagar ahora', 'centinela-group-theme' ) . '</a></p>';
	$body .= '<p>' . esc_html__( 'Si el botón no abre, copia y pega este enlace en tu navegador:', 'centinela-group-theme' ) . '<br />' . esc_html( $pay_url ) . '</p>';
	$body .= '<p style="color:#666;font-size:12px;">' . esc_html__( 'Este mensaje fue enviado automáticamente por Centinela Group.', 'centinela-group-theme' ) . '</p>';

	$trace = 'cart-link-' . wp_generate_password( 10, false, false );
	$headers = array(
		'Content-Type: text/html; charset=UTF-8',
		'X-Centinela-Cotizacion-Trace: ' . $trace,
	);
	$headers = apply_filters( 'centinela_cotizador_wp_mail_headers', $headers, array(
		'type'       => 'payment_link',
		'trace'      => $trace,
		'order_id'   => (int) $order_id,
		'cotizacion' => $datos,
	) );

	return (bool) wp_mail( $email_pack['to'], $subject, $body, $headers, array() );
}

/**
 * Guarda rastro del último link de pago generado en la cotización (si existe post ID).
 *
 * @param int    $post_id   ID de la cotización.
 * @param int    $order_id  ID del pedido WC.
 * @param string $pay_url   Link de pago.
 * @param bool   $mail_sent Si el correo fue enviado.
 * @return void
 */
function centinela_cotizador_store_payment_link_trace( $post_id, $order_id, $pay_url, $mail_sent ) {
	$post_id = absint( $post_id );
	if ( $post_id < 1 ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'cotizacion' ) {
		return;
	}
	$datos = get_post_meta( $post_id, '_cotizacion_datos', true );
	if ( ! is_array( $datos ) ) {
		$datos = array();
	}
	$datos['ultimo_link_pago'] = esc_url_raw( (string) $pay_url );
	$datos['ultimo_order_id_wc'] = absint( $order_id );
	$datos['ultimo_link_pago_fecha'] = current_time( 'mysql' );
	$datos['ultimo_link_pago_mail_sent'] = $mail_sent ? 1 : 0;
	update_post_meta( $post_id, '_cotizacion_datos', $datos );
}

function centinela_cotizador_ajax_enviar_carrito() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	$editar_id = isset( $_POST['editar_id'] ) ? absint( $_POST['editar_id'] ) : 0;
	if ( empty( $datos ) || empty( $datos['productos'] ) ) {
		wp_send_json_error( array( 'message' => __( 'No hay productos en la cotización.', 'centinela-group-theme' ) ) );
	}
	$direccion  = isset( $_POST['centinela_direccion'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_direccion'] ) ) : '';
	$ciudad     = isset( $_POST['centinela_ciudad'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_ciudad'] ) ) : '';
	$departamento = isset( $_POST['centinela_departamento'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_departamento'] ) ) : '';
	$city_dept_map = centinela_cotizador_ciudad_departamento_map();
	$nombre     = isset( $datos['cliente']['nombre'] ) ? sanitize_text_field( $datos['cliente']['nombre'] ) : '';
	$email      = isset( $datos['cliente']['email'] ) ? sanitize_email( $datos['cliente']['email'] ) : '';
	$telefono   = isset( $datos['contacto']['telefono'] ) ? sanitize_text_field( $datos['contacto']['telefono'] ) : '';
	if ( $nombre === '' || $email === '' || $direccion === '' || $ciudad === '' || $departamento === '' ) {
		wp_send_json_error( array( 'message' => __( 'Complete los datos del cliente: nombre, email, dirección, ciudad y departamento.', 'centinela-group-theme' ) ) );
	}
	$zonas_validas = isset( $city_dept_map[ $ciudad ] ) && is_array( $city_dept_map[ $ciudad ] ) ? $city_dept_map[ $ciudad ] : array();
	$zonas_validas = array_map( 'strval', $zonas_validas );
	if ( empty( $zonas_validas ) || ! in_array( $departamento, $zonas_validas, true ) ) {
		wp_send_json_error( array( 'message' => __( 'Seleccione una ciudad de Colombia y un departamento/municipio válido para esa ciudad.', 'centinela-group-theme' ) ) );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Email del cliente no válido.', 'centinela-group-theme' ) ) );
	}

	$items = array();
	foreach ( $datos['productos'] as $p ) {
		$title = isset( $p['modelo'] ) ? sanitize_text_field( $p['modelo'] ) : '';
		if ( isset( $p['titulo'] ) && $p['titulo'] !== '' ) {
			$title .= ( $title !== '' ? ' — ' : '' ) . sanitize_text_field( $p['titulo'] );
		}
		$ref = isset( $p['referencia'] ) ? trim( (string) $p['referencia'] ) : '';
		if ( $ref !== '' ) {
			$title = $title !== '' ? ( $ref . ': ' . $title ) : $ref;
		}
		if ( $title === '' ) {
			$title = __( 'Producto manual', 'centinela-group-theme' );
		}
		$items[] = array(
			'id'    => isset( $p['id'] ) ? $p['id'] : '',
			'qty'   => isset( $p['cantidad'] ) ? max( 1, (int) $p['cantidad'] ) : 1,
			'title' => $title,
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
		'centinela_cot_moneda'   => isset( $datos['moneda'] ) ? (string) $datos['moneda'] : 'COP',
		'centinela_cot_subtotal' => isset( $datos['subtotal'] ) ? (float) $datos['subtotal'] : 0.0,
		'centinela_cot_iva_valor'=> isset( $datos['iva_valor'] ) ? (float) $datos['iva_valor'] : 0.0,
		'centinela_cot_total'    => isset( $datos['total'] ) ? (float) $datos['total'] : 0.0,
		'centinela_cot_iva_pct'  => isset( $datos['iva_pct'] ) ? (float) $datos['iva_pct'] : 0.0,
	);

	if ( ! function_exists( 'centinela_checkout_create_wc_order' ) ) {
		wp_send_json_error( array( 'message' => __( 'Checkout no disponible.', 'centinela-group-theme' ) ) );
	}
	$result = centinela_checkout_create_wc_order( $items, $form );
	if ( ! $result['success'] ) {
		wp_send_json_error( array( 'message' => $result['message'] ) );
	}
	$mail_sent = centinela_cotizador_send_payment_link_email(
		$datos,
		isset( $result['redirect'] ) ? $result['redirect'] : '',
		isset( $result['order_id'] ) ? (int) $result['order_id'] : 0
	);
	centinela_cotizador_store_payment_link_trace(
		$editar_id,
		isset( $result['order_id'] ) ? (int) $result['order_id'] : 0,
		isset( $result['redirect'] ) ? (string) $result['redirect'] : '',
		$mail_sent
	);
	$msg = $mail_sent
		? __( 'Link de pago generado y enviado al correo del cliente.', 'centinela-group-theme' )
		: __( 'Link de pago generado. No se pudo enviar por correo automáticamente.', 'centinela-group-theme' );
	wp_send_json_success( array(
		'redirect' => $result['redirect'],
		'message'  => $msg,
		'order_id' => isset( $result['order_id'] ) ? (int) $result['order_id'] : 0,
		'mail_sent'=> $mail_sent ? 1 : 0,
	) );
}
add_action( 'wp_ajax_centinela_cotizador_enviar_carrito', 'centinela_cotizador_ajax_enviar_carrito' );

/**
 * AJAX: reenviar por email un link de pago ya generado.
 */
function centinela_cotizador_ajax_reenviar_link_carrito() {
	check_ajax_referer( 'centinela_cotizador', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
		wp_send_json_error( array( 'message' => 'Unauthorized' ) );
	}
	$raw = isset( $_POST['datos'] ) ? wp_unslash( $_POST['datos'] ) : '';
	$datos = is_string( $raw ) ? json_decode( $raw, true ) : ( is_array( $raw ) ? $raw : array() );
	$pay_url = isset( $_POST['pay_url'] ) ? esc_url_raw( wp_unslash( $_POST['pay_url'] ) ) : '';
	$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	$editar_id = isset( $_POST['editar_id'] ) ? absint( $_POST['editar_id'] ) : 0;
	if ( empty( $datos ) || $pay_url === '' ) {
		wp_send_json_error( array( 'message' => __( 'No hay información suficiente para reenviar el link.', 'centinela-group-theme' ) ) );
	}
	$ok = centinela_cotizador_send_payment_link_email( $datos, $pay_url, $order_id );
	centinela_cotizador_store_payment_link_trace( $editar_id, $order_id, $pay_url, $ok );
	if ( ! $ok ) {
		wp_send_json_error( array( 'message' => __( 'No se pudo reenviar el link por correo.', 'centinela-group-theme' ) ) );
	}
	wp_send_json_success( array( 'message' => __( 'Link de pago reenviado al correo del cliente.', 'centinela-group-theme' ) ) );
}
add_action( 'wp_ajax_centinela_cotizador_reenviar_link_carrito', 'centinela_cotizador_ajax_reenviar_link_carrito' );

/**
 * Renderizar la página del Cotizador
 */
function centinela_cotizador_render_page() {
	if ( ! centinela_cotizador_can_manage() ) {
		return;
	}
	?>
	<div class="wrap centinela-cotizador-wrap">
		<h1><?php esc_html_e( 'Cotizador', 'centinela-group-theme' ); ?></h1>

		<div class="centinela-cotizador-layout">
			<div class="centinela-cotizador-contenido">
		<div class="centinela-cotizador-form">
			<input type="hidden" id="centinela-cotizador-editar-id" value="" />
			<div class="centinela-cotizador-field centinela-cotizador-field-titulo">
				<label for="centinela-cotizador-titulo"><?php esc_html_e( 'Título de la Cotización', 'centinela-group-theme' ); ?></label>
				<input type="text" id="centinela-cotizador-titulo" class="regular-text" placeholder="<?php esc_attr_e( 'Ej: Cotización proyecto videovigilancia', 'centinela-group-theme' ); ?>" />
			</div>
			<div class="centinela-cotizador-fila-fecha-orden">
				<div class="centinela-cotizador-field centinela-cotizador-field-fecha">
					<label for="centinela-cotizador-fecha-cotizacion"><?php esc_html_e( 'Fecha', 'centinela-group-theme' ); ?></label>
					<input type="date" id="centinela-cotizador-fecha-cotizacion" class="regular-text" value="<?php echo esc_attr( wp_date( 'Y-m-d' ) ); ?>" />
					<p class="description"><?php esc_html_e( 'Fecha de la cotización en el PDF y correo (FECHA). Puede cambiarla antes de enviar.', 'centinela-group-theme' ); ?></p>
				</div>
				<div class="centinela-cotizador-field centinela-cotizador-field-orden-compra">
					<label for="centinela-cotizador-orden-compra"><?php esc_html_e( 'Orden de compra', 'centinela-group-theme' ); ?></label>
					<input type="text" id="centinela-cotizador-orden-compra" class="regular-text" maxlength="120" placeholder="<?php esc_attr_e( 'Número u orden emitida por el cliente (opcional)', 'centinela-group-theme' ); ?>" />
					<p class="description"><?php esc_html_e( 'Si la completa, aparecerá en el PDF y el correo junto a «ORDEN DE COMPRA». Si la deja vacía, solo se mostrará el rótulo.', 'centinela-group-theme' ); ?></p>
				</div>
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
								<th scope="col" class="centinela-cotizador-col-orden" title="<?php esc_attr_e( 'Arrastre las líneas por el asa para reordenarlas.', 'centinela-group-theme' ); ?>">
									<span class="screen-reader-text"><?php esc_html_e( 'Orden', 'centinela-group-theme' ); ?></span>
									<span class="centinela-cotizador-drag-head" aria-hidden="true">⋮⋮</span>
								</th>
								<th><?php esc_html_e( 'Modelo', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-cantidad"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-descuento"><?php esc_html_e( 'Descuento %', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-descuento-global" title="<?php esc_attr_e( 'Incluir esta línea en la base del descuento global (%)', 'centinela-group-theme' ); ?>"><?php esc_html_e( 'Dto. cot.', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-precio"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-importe"><?php esc_html_e( 'Importe', 'centinela-group-theme' ); ?></th>
								<th class="centinela-cotizador-col-acciones"><?php esc_html_e( 'Acciones', 'centinela-group-theme' ); ?></th>
							</tr>
						</thead>
						<tbody id="centinela-cotizador-filas">
							<tr class="centinela-cotizador-tabla-vacia" id="centinela-cotizador-fila-vacia">
								<td colspan="8"><?php esc_html_e( 'Agrega productos con el buscador o con «Producto manual».', 'centinela-group-theme' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>

				<div class="centinela-cotizador-manual-wrap">
					<h3 class="centinela-cotizador-manual-title"><?php esc_html_e( 'Producto manual (sin catálogo API)', 'centinela-group-theme' ); ?></h3>
					<p class="description centinela-cotizador-manual-help"><?php esc_html_e( 'Añada líneas que no existan en Syscom; se suman al subtotal y totales igual que los demás.', 'centinela-group-theme' ); ?></p>
					<div class="centinela-cotizador-manual-grid">
						<div class="centinela-cotizador-field centinela-cotizador-manual-ref-wrap">
							<label for="centinela-cotizador-manual-ref"><?php esc_html_e( 'Referencia', 'centinela-group-theme' ); ?></label>
							<input type="text" id="centinela-cotizador-manual-ref" class="regular-text" autocomplete="off" />
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-modelo-wrap">
							<label for="centinela-cotizador-manual-modelo"><?php esc_html_e( 'Modelo / nombre', 'centinela-group-theme' ); ?> <span class="required">*</span></label>
							<input type="text" id="centinela-cotizador-manual-modelo" class="regular-text" autocomplete="off" />
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-cantidad-wrap">
							<label for="centinela-cotizador-manual-cantidad"><?php esc_html_e( 'Cantidad', 'centinela-group-theme' ); ?></label>
							<input type="number" id="centinela-cotizador-manual-cantidad" class="regular-text" min="1" step="1" value="1" />
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-descuento-wrap">
							<label for="centinela-cotizador-manual-descuento"><?php esc_html_e( 'Descuento %', 'centinela-group-theme' ); ?></label>
							<input type="number" id="centinela-cotizador-manual-descuento" class="regular-text" min="0" max="100" step="0.01" value="0" />
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-precio-wrap">
							<label for="centinela-cotizador-manual-precio"><?php esc_html_e( 'Precio', 'centinela-group-theme' ); ?> <span class="required">*</span></label>
							<input type="number" id="centinela-cotizador-manual-precio" class="regular-text" min="0" step="0.01" value="" placeholder="0" />
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-descripcion-wrap">
							<label for="centinela-cotizador-manual-descripcion"><?php esc_html_e( 'Descripción (opcional)', 'centinela-group-theme' ); ?></label>
							<textarea id="centinela-cotizador-manual-descripcion" class="regular-text" rows="4" autocomplete="off"></textarea>
						</div>
						<div class="centinela-cotizador-field centinela-cotizador-manual-actions">
							<label class="screen-reader-text" for="centinela-cotizador-manual-add"><?php esc_html_e( 'Agregar a la tabla', 'centinela-group-theme' ); ?></label>
							<button type="button" id="centinela-cotizador-manual-add" class="button button-secondary">
								<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
								<span class="button-label"><?php esc_html_e( 'Agregar a la tabla', 'centinela-group-theme' ); ?></span>
							</button>
							<button type="button" id="centinela-cotizador-manual-cancel" class="button" hidden>
								<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
								<span class="button-label"><?php esc_html_e( 'Cancelar', 'centinela-group-theme' ); ?></span>
							</button>
						</div>
					</div>
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
						<p class="description"><?php esc_html_e( 'Este es el correo que aparece en la cotización impresa y en el PDF.', 'centinela-group-theme' ); ?></p>
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-email-adicionales"><?php esc_html_e( 'Emails adicionales (opcional)', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-email-adicionales" class="large-text" autocomplete="off" placeholder="correo@ejemplo.com, otro@ejemplo.com" />
						<p class="description"><?php esc_html_e( 'Separe varias direcciones con coma o punto y coma. Recibirán la misma cotización por correo; no se muestran en el PDF.', 'centinela-group-theme' ); ?></p>
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-telefono"><?php esc_html_e( 'Teléfono', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-telefono" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-nitcc"><?php esc_html_e( 'NIT / C.C.', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-nitcc" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-sitio-web"><?php esc_html_e( 'Sitio web', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-sitio-web" class="regular-text" placeholder="https://" autocomplete="url" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-direccion"><?php esc_html_e( 'Dirección', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-direccion" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-direccion-fisica"><?php esc_html_e( 'Dirección física', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-cliente-direccion-fisica" class="regular-text" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-cliente-ciudad"><?php esc_html_e( 'Ciudad', 'centinela-group-theme' ); ?></label>
						<select id="centinela-cotizador-cliente-ciudad" class="regular-text">
							<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
							<?php
							foreach ( centinela_cotizador_ciudades_colombia() as $ciudad_opt ) {
								echo '<option value="' . esc_attr( $ciudad_opt ) . '">' . esc_html( $ciudad_opt ) . '</option>';
							}
							?>
						</select>
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
						<label for="centinela-cotizador-embarcar-a"><?php esc_html_e( 'Embarcar a', 'centinela-group-theme' ); ?></label>
						<select id="centinela-cotizador-embarcar-a" class="regular-text">
							<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
							<option value="envio_nacional"><?php esc_html_e( 'Envío nacional', 'centinela-group-theme' ); ?></option>
							<option value="envio_local"><?php esc_html_e( 'Envío local', 'centinela-group-theme' ); ?></option>
							<option value="recoger_en_tienda"><?php esc_html_e( 'Recoger en tienda', 'centinela-group-theme' ); ?></option>
						</select>
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-envio-via"><?php esc_html_e( 'Vía', 'centinela-group-theme' ); ?></label>
						<select id="centinela-cotizador-envio-via" class="regular-text">
							<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
							<option value="transportadora_logistica"><?php esc_html_e( 'Transportadora logística', 'centinela-group-theme' ); ?></option>
							<option value="entrega_personalizada"><?php esc_html_e( 'Entrega personalizada', 'centinela-group-theme' ); ?></option>
						</select>
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-envio-quien-recibe"><?php esc_html_e( 'Quien recibe', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-envio-quien-recibe" class="regular-text" autocomplete="name" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-envio-direccion"><?php esc_html_e( 'Dirección (envío)', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-envio-direccion" class="regular-text" autocomplete="street-address" />
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-envio-ciudad"><?php esc_html_e( 'Ciudad', 'centinela-group-theme' ); ?></label>
						<select id="centinela-cotizador-envio-ciudad" class="regular-text">
							<option value=""><?php esc_html_e( '— Seleccionar —', 'centinela-group-theme' ); ?></option>
							<?php
							foreach ( centinela_cotizador_ciudades_colombia() as $ciudad_opt ) {
								echo '<option value="' . esc_attr( $ciudad_opt ) . '">' . esc_html( $ciudad_opt ) . '</option>';
							}
							?>
						</select>
					</div>
					<div class="centinela-cotizador-field">
						<label for="centinela-cotizador-envio-cel"><?php esc_html_e( 'Celular quien recibe', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-envio-cel" class="regular-text" inputmode="tel" autocomplete="tel" />
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
					$logo_default_data = function_exists( 'centinela_cotizador_get_default_logo_data' ) ? centinela_cotizador_get_default_logo_data() : array();
					$logo_default_src  = isset( $logo_default_data['src_medium'] ) ? (string) $logo_default_data['src_medium'] : '';
					$logo_default_full = isset( $logo_default_data['src_full'] ) ? (string) $logo_default_data['src_full'] : '';
					?>
					<div class="centinela-cotizador-logo-preview-wrap">
						<div class="centinela-cotizador-logo-preview" id="centinela-cotizador-logo-preview">
							<img id="centinela-cotizador-logo-img" src="<?php echo esc_url( $logo_default_src ); ?>" alt="" style="max-width:100%;height:auto;<?php echo $logo_default_src ? '' : 'display:none;'; ?>" />
							<span class="centinela-cotizador-logo-placeholder" id="centinela-cotizador-logo-placeholder" style="<?php echo $logo_default_src ? 'display:none;' : ''; ?>"><?php esc_html_e( 'Logo por defecto', 'centinela-group-theme' ); ?></span>
						</div>
						<p class="centinela-cotizador-logo-formats"><?php esc_html_e( 'Formatos aceptados:', 'centinela-group-theme' ); ?> .png, .jpg, .ai</p>
					</div>
					<input type="hidden" id="centinela-cotizador-logo-url" value="<?php echo esc_url( $logo_default_full ); ?>" />
					<div class="centinela-cotizador-logo-actions">
						<button type="button" class="button" id="centinela-cotizador-logo-select"><?php esc_html_e( 'Subir / Cambiar logo', 'centinela-group-theme' ); ?></button>
						<button type="button" class="button button-link-delete" id="centinela-cotizador-logo-reset"><?php esc_html_e( 'Usar logo por defecto', 'centinela-group-theme' ); ?></button>
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
						<input type="number" id="centinela-cotizador-tipo-cambio" class="regular-text" min="0" step="0.01" value="" placeholder="<?php esc_attr_e( 'Cargando…', 'centinela-group-theme' ); ?>" title="<?php esc_attr_e( 'Puedes editar el valor manualmente; al guardar la cotización se guardará el TRM mostrado aquí.', 'centinela-group-theme' ); ?>" />
					</div>
					<div class="centinela-cotizador-tc-actions">
						<button type="button" class="button button-primary" id="centinela-cotizador-actualizar-tc"><?php esc_html_e( 'Actualizar', 'centinela-group-theme' ); ?></button>
						<button type="button" class="button" id="centinela-cotizador-sync-tc-syscom"><?php esc_html_e( 'Cargar TRM Syscom', 'centinela-group-theme' ); ?></button>
					</div>
					<p class="description centinela-cotizador-tc-hint"><?php esc_html_e( 'El TRM es «1 USD = X COP». «Actualizar» confirma el valor; «Cargar TRM Syscom» trae el tipo oficial del día.', 'centinela-group-theme' ); ?></p>
					<p class="description centinela-cotizador-tc-hint centinela-cotizador-tc-hint-cop-usd"><?php esc_html_e( 'Importante: los precios de catálogo Syscom ya vienen en pesos colombianos (COP). Por eso, con moneda COP, al cambiar solo el TRM no cambian el precio de cada línea ni el total en pesos: el TRM sirve para la referencia en dólares (texto «≈ USD» junto a cada total y el bloque «Referencia USD» más abajo). Si sube el TRM, con el mismo total en pesos verá menos dólares en esa referencia. Si necesita que el subtotal y el total principal del resumen se muevan al cambiar el TRM, elija la moneda USD en «Moneda».', 'centinela-group-theme' ); ?></p>
					<p class="centinela-cotizador-tc-msg" id="centinela-cotizador-tc-msg" aria-live="polite"></p>
					<p class="centinela-cotizador-tc-pending notice notice-warning" id="centinela-cotizador-tc-pending" role="status" hidden></p>
				</div>

				<div class="centinela-cotizador-resumen-totales">
					<div class="centinela-cotizador-resumen-fila">
						<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></span>
						<span class="centinela-cotizador-resumen-valor centinela-cotizador-resumen-valor-apilado">
							<span id="centinela-cotizador-subtotal" class="centinela-cotizador-monto-principal">0</span>
							<span id="centinela-cotizador-subtotal-usd-ref" class="centinela-cotizador-monto-usd-ref" hidden></span>
						</span>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-descuento" id="centinela-cotizador-resumen-descuento-fila" hidden>
						<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'Descuento', 'centinela-group-theme' ); ?></span>
						<span class="centinela-cotizador-resumen-valor centinela-cotizador-resumen-valor-apilado">
							<span id="centinela-cotizador-descuento-valor" class="centinela-cotizador-monto-principal">0</span>
							<span id="centinela-cotizador-descuento-valor-usd-ref" class="centinela-cotizador-monto-usd-ref" hidden></span>
						</span>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-iva">
						<div class="centinela-cotizador-resumen-iva-pct">
							<label for="centinela-cotizador-iva-pct"><?php esc_html_e( 'I.V.A. %', 'centinela-group-theme' ); ?></label>
							<input type="number" id="centinela-cotizador-iva-pct" min="0" max="100" step="0.01" value="19" />
						</div>
						<span class="centinela-cotizador-resumen-valor centinela-cotizador-resumen-valor-apilado">
							<span id="centinela-cotizador-iva-valor" class="centinela-cotizador-monto-principal">0</span>
							<span id="centinela-cotizador-iva-valor-usd-ref" class="centinela-cotizador-monto-usd-ref" hidden></span>
						</span>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-descuento-pct">
						<div class="centinela-cotizador-resumen-iva-pct">
							<label for="centinela-cotizador-descuento-pct"><?php esc_html_e( 'Descuento %', 'centinela-group-theme' ); ?></label>
							<input type="number" id="centinela-cotizador-descuento-pct" min="0" max="100" step="0.01" value="0" />
						</div>
					</div>
					<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-total">
						<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'TOTAL', 'centinela-group-theme' ); ?></span>
						<span class="centinela-cotizador-resumen-valor centinela-cotizador-resumen-valor-apilado">
							<span id="centinela-cotizador-total" class="centinela-cotizador-monto-principal">0</span>
							<span id="centinela-cotizador-total-usd-ref" class="centinela-cotizador-monto-usd-ref centinela-cotizador-monto-usd-ref-total" hidden></span>
						</span>
					</div>
					<div id="centinela-cotizador-ref-usd-bloque" class="centinela-cotizador-ref-usd-bloque" hidden>
						<p class="centinela-cotizador-ref-usd-titulo"><?php esc_html_e( 'Referencia USD (según TRM)', 'centinela-group-theme' ); ?></p>
						<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-ref-usd">
							<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'Subtotal', 'centinela-group-theme' ); ?></span>
							<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-ref-usd-subtotal">—</span>
						</div>
						<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-ref-usd">
							<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'I.V.A.', 'centinela-group-theme' ); ?></span>
							<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-ref-usd-iva">—</span>
						</div>
						<div class="centinela-cotizador-resumen-fila centinela-cotizador-resumen-ref-usd centinela-cotizador-resumen-ref-usd-total">
							<span class="centinela-cotizador-resumen-label"><?php esc_html_e( 'TOTAL', 'centinela-group-theme' ); ?></span>
							<span class="centinela-cotizador-resumen-valor" id="centinela-cotizador-ref-usd-total">—</span>
						</div>
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
				<p class="description"><?php esc_html_e( 'El archivo se adjuntará al correo enviado al email principal y, si los indicó, a los emails adicionales en Datos del cliente.', 'centinela-group-theme' ); ?></p>
			</div>
			<p class="centinela-cotizador-modal-msg" id="centinela-modal-enviar-msg"></p>
			<div class="centinela-cotizador-modal-actions">
				<button type="button" class="button button-primary" id="centinela-cotizador-modal-descargar-pdf-btn"><?php esc_html_e( 'Descargar PDF', 'centinela-group-theme' ); ?></button>
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
				<select id="centinela-modal-ciudad" class="regular-text">
					<option value=""><?php esc_html_e( 'Seleccione una ciudad', 'centinela-group-theme' ); ?></option>
					<?php foreach ( centinela_cotizador_ciudades_colombia() as $ciudad_opt ) : ?>
						<option value="<?php echo esc_attr( $ciudad_opt ); ?>"><?php echo esc_html( $ciudad_opt ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="centinela-cotizador-field">
				<label for="centinela-modal-departamento"><?php esc_html_e( 'Departamento / Municipio', 'centinela-group-theme' ); ?></label>
				<select id="centinela-modal-departamento" class="regular-text">
					<option value=""><?php esc_html_e( 'Seleccione un departamento o municipio', 'centinela-group-theme' ); ?></option>
				</select>
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
					<button type="button" class="button" id="centinela-cotizador-reenviar-link" disabled><?php esc_html_e( 'Reenviar link por correo', 'centinela-group-theme' ); ?></button>
				</div>
				<input type="hidden" id="centinela-cotizador-link-order-id" value="" />
			</div>
		</div>
	</div>

	<?php if ( current_user_can( 'manage_options' ) ) : ?>
		<div id="centinela-cotizador-debug-dock" class="centinela-cotizador-debug-dock">
			<details class="centinela-cotizador-debug-precios">
				<summary class="centinela-cotizador-debug-dock-summary"><?php esc_html_e( 'Depuración precios API', 'centinela-group-theme' ); ?></summary>
				<div class="centinela-cotizador-debug-dock-body">
					<p class="description"><?php esc_html_e( 'ID numérico Syscom (ej. URL …-218889.html). Respuesta con cop=1 y sin cop + parser del tema.', 'centinela-group-theme' ); ?></p>
					<p class="centinela-cotizador-debug-precios-row">
						<label for="centinela-cotizador-debug-producto-id" class="screen-reader-text"><?php esc_html_e( 'ID producto Syscom', 'centinela-group-theme' ); ?></label>
						<input type="text" id="centinela-cotizador-debug-producto-id" class="regular-text" inputmode="numeric" pattern="[0-9]*" placeholder="<?php esc_attr_e( 'ID', 'centinela-group-theme' ); ?>" />
						<button type="button" class="button button-small" id="centinela-cotizador-debug-precios-btn"><?php esc_html_e( 'Inspeccionar', 'centinela-group-theme' ); ?></button>
					</p>
					<pre class="centinela-cotizador-debug-precios-out" id="centinela-cotizador-debug-precios-out" hidden></pre>
				</div>
			</details>
		</div>
	<?php endif; ?>

	<?php
}

/**
 * Paginación de Mis Cotizaciones: IDs de posts y totales (búsqueda en meta serializada vía SQL LIKE).
 *
 * @param string $buscar Texto libre (nombre, NIT, etc. dentro de _cotizacion_datos).
 * @param int    $paged  Página (1-based).
 * @return array{ ids: int[], found_posts: int, max_num_pages: int }
 */
function centinela_cotizador_mis_cotizaciones_get_page( $buscar, $paged = 1 ) {
	global $wpdb;
	$per_page = 50;
	$buscar   = trim( (string) $buscar );
	if ( strlen( $buscar ) > 160 ) {
		$buscar = substr( $buscar, 0, 160 );
	}
	$paged  = max( 1, (int) $paged );
	$offset = ( $paged - 1 ) * $per_page;

	if ( $buscar === '' ) {
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY post_date DESC LIMIT %d OFFSET %d",
				'cotizacion',
				'publish',
				$per_page,
				$offset
			)
		);
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'cotizacion',
				'publish'
			)
		);
	} else {
		$like = '%' . $wpdb->esc_like( $buscar ) . '%';
		$ids  = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = %s AND pm.meta_value LIKE %s
				ORDER BY p.post_date DESC
				LIMIT %d OFFSET %d",
				'_cotizacion_datos',
				'cotizacion',
				'publish',
				$like,
				$per_page,
				$offset
			)
		);
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = %s AND pm.meta_value LIKE %s",
				'_cotizacion_datos',
				'cotizacion',
				'publish',
				$like
			)
		);
	}
	$ids = array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
	$max = $found > 0 ? (int) ceil( $found / $per_page ) : 0;

	return array(
		'ids'           => $ids,
		'found_posts'   => $found,
		'max_num_pages' => $max,
	);
}

/**
 * Fila de Mis Cotizaciones para JSON (búsqueda en vivo).
 *
 * @param int $post_id ID del post cotización.
 * @return array<string,mixed>|null
 */
function centinela_cotizador_mis_cotizaciones_row_payload( $post_id ) {
	$post_id = absint( $post_id );
	$post    = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'cotizacion' || $post->post_status !== 'publish' ) {
		return null;
	}
	$datos         = get_post_meta( $post_id, '_cotizacion_datos', true );
	$cliente_arr   = is_array( $datos ) && isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
	$cli_nombre    = isset( $cliente_arr['nombre'] ) ? trim( (string) $cliente_arr['nombre'] ) : '';
	$cli_nit       = isset( $cliente_arr['nit_cc'] ) ? trim( (string) $cliente_arr['nit_cc'] ) : '';
	$cli_nombre_d  = $cli_nombre !== '' ? $cli_nombre : '—';
	$cli_nit_d     = $cli_nit !== '' ? $cli_nit : '—';
	$moneda        = centinela_cotizador_normalize_moneda( is_array( $datos ) && isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' );
	$total         = is_array( $datos ) && isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
	$total_fmt     = $moneda === 'USD' ? 'USD $ ' . number_format( $total, 2, '.', ',' ) : 'CO $ ' . number_format( $total, 0, ',', '.' );
	$editar_url    = add_query_arg( 'editar', $post_id, admin_url( 'admin.php?page=centinela-cotizador' ) );
	$eliminar_url  = add_query_arg(
		array(
			'eliminar' => $post_id,
			'_wpnonce' => wp_create_nonce( 'centinela_eliminar_cotizacion_' . $post_id ),
		),
		admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' )
	);
	$duplicar_url = add_query_arg(
		array(
			'duplicar' => $post_id,
			'_wpnonce' => wp_create_nonce( 'centinela_duplicar_cotizacion_' . $post_id ),
		),
		admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' )
	);

	return array(
		'id'               => $post_id,
		'title'            => get_the_title( $post_id ),
		'cliente'          => $cli_nombre_d,
		'nit'              => $cli_nit_d,
		'moneda'           => $moneda,
		'total_fmt'        => $total_fmt,
		'fecha'            => centinela_cotizador_fecha_cotizacion_display_dmY(
			centinela_cotizador_fecha_cotizacion_ymd_from_datos( is_array( $datos ) ? $datos : array(), $post_id )
		),
		'editar_url'       => $editar_url,
		'duplicar_url'     => $duplicar_url,
		'eliminar_url'     => $eliminar_url,
		'can_delete'       => current_user_can( 'manage_options' ),
		'confirm_duplicar' => __( '¿Duplicar esta cotización? Se creará una nueva con el mismo contenido y «Copia» en el título.', 'centinela-group-theme' ),
		'confirm_eliminar' => __( '¿Eliminar esta cotización? No se podrá deshacer.', 'centinela-group-theme' ),
	);
}

/**
 * AJAX: listado / búsqueda Mis Cotizaciones (tiempo real).
 */
function centinela_cotizador_ajax_mis_cotizaciones_list() {
	check_ajax_referer( 'centinela_mis_cotizaciones', 'nonce' );
	if ( ! centinela_cotizador_can_manage() ) {
		wp_send_json_error( array( 'message' => __( 'No autorizado.', 'centinela-group-theme' ) ) );
	}
	$q     = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
	$paged = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
	$page  = centinela_cotizador_mis_cotizaciones_get_page( $q, $paged );
	$rows  = array();
	foreach ( $page['ids'] as $pid ) {
		$row = centinela_cotizador_mis_cotizaciones_row_payload( $pid );
		if ( $row ) {
			$rows[] = $row;
		}
	}
	wp_send_json_success(
		array(
			'rows'          => $rows,
			'found_posts'   => (int) $page['found_posts'],
			'max_num_pages' => (int) $page['max_num_pages'],
			'paged'         => $paged,
		)
	);
}
add_action( 'wp_ajax_centinela_mis_cotizaciones_list', 'centinela_cotizador_ajax_mis_cotizaciones_list' );

/**
 * Scripts Mis Cotizaciones (búsqueda en vivo).
 *
 * @param string $hook_suffix Hook de pantalla admin.
 */
function centinela_cotizador_enqueue_mis_cotizaciones( $hook_suffix ) {
	if ( strpos( (string) $hook_suffix, 'centinela-cotizador-mis-cotizaciones' ) === false ) {
		return;
	}
	$path = get_template_directory() . '/assets/js/mis-cotizaciones-admin.js';
	wp_enqueue_script(
		'centinela-mis-cotizaciones-admin',
		get_template_directory_uri() . '/assets/js/mis-cotizaciones-admin.js',
		array( 'jquery' ),
		file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0',
		true
	);
	wp_localize_script(
		'centinela-mis-cotizaciones-admin',
		'centinelaMisCotizaciones',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'centinela_mis_cotizaciones' ),
			'i18n'     => array(
				'no_results' => __( 'No hay cotizaciones que coincidan. Pruebe con otra palabra o con parte del NIT.', 'centinela-group-theme' ),
				'loading'    => __( 'Buscando…', 'centinela-group-theme' ),
				'mostrando'  => __( 'Mostrando', 'centinela-group-theme' ),
				'de'         => __( 'de', 'centinela-group-theme' ),
				'cotizacion' => __( 'cotización(es)', 'centinela-group-theme' ),
				'editar'     => __( 'Editar', 'centinela-group-theme' ),
				'duplicar'   => __( 'Duplicar', 'centinela-group-theme' ),
				'eliminar'   => __( 'Eliminar', 'centinela-group-theme' ),
			),
		)
	);
}
add_action( 'admin_enqueue_scripts', 'centinela_cotizador_enqueue_mis_cotizaciones' );

/**
 * Renderizar la página Mis Cotizaciones (listado de cotizaciones guardadas)
 */
function centinela_cotizador_render_mis_cotizaciones() {
	if ( ! centinela_cotizador_can_manage() ) {
		return;
	}
	// Procesar eliminación (mover a papelera)
	$eliminar_id = isset( $_GET['eliminar'] ) ? absint( $_GET['eliminar'] ) : 0;
	if ( $eliminar_id > 0 && isset( $_GET['_wpnonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&permiso_eliminar=0' ) );
			exit;
		}
		if ( wp_verify_nonce( $nonce, 'centinela_eliminar_cotizacion_' . $eliminar_id ) ) {
			$post = get_post( $eliminar_id );
			if ( $post && $post->post_type === 'cotizacion' ) {
				// No tocar `_cotizacion_numero` de otros posts ni `centinela_cotizador_ultimo_numero`: cada cotización conserva su #.
				wp_trash_post( $eliminar_id );
				wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&eliminado=1' ) );
				exit;
			}
		}
	}
	// Duplicar cotización (mismo permiso que ver el listado: centinela_manage_cotizador).
	$duplicar_id = isset( $_GET['duplicar'] ) ? absint( $_GET['duplicar'] ) : 0;
	if ( $duplicar_id > 0 && isset( $_GET['_wpnonce'] ) ) {
		$nonce_dup = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( ! centinela_cotizador_can_manage() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&permiso_duplicar=0' ) );
			exit;
		}
		if ( wp_verify_nonce( $nonce_dup, 'centinela_duplicar_cotizacion_' . $duplicar_id ) ) {
			$new_id = centinela_cotizador_duplicate_cotizacion_post( $duplicar_id );
			if ( ! is_wp_error( $new_id ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&duplicado=1' ) );
				exit;
			}
			wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones&duplicar_error=1' ) );
			exit;
		}
	}
	$buscar = '';
	if ( isset( $_GET['buscar'] ) && is_string( $_GET['buscar'] ) ) {
		$buscar = trim( sanitize_text_field( wp_unslash( $_GET['buscar'] ) ) );
	}
	if ( strlen( $buscar ) > 160 ) {
		$buscar = substr( $buscar, 0, 160 );
	}
	$paged = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

	$page_result   = centinela_cotizador_mis_cotizaciones_get_page( $buscar, $paged );
	$ids           = $page_result['ids'];
	$mis_found     = (int) $page_result['found_posts'];
	$mis_max_pages = (int) $page_result['max_num_pages'];

	if ( empty( $ids ) ) {
		$query = new WP_Query( array( 'post__in' => array( 0 ) ) );
	} else {
		$query = new WP_Query(
			array(
				'post_type'           => 'cotizacion',
				'post_status'         => 'publish',
				'post__in'            => $ids,
				'orderby'             => 'post__in',
				'posts_per_page'      => count( $ids ),
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);
	}

	$mis_cot_base_url = admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Mis Cotizaciones', 'centinela-group-theme' ); ?></h1>
		<?php if ( isset( $_GET['permiso_eliminar'] ) && $_GET['permiso_eliminar'] === '0' ) : ?>
			<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'No tienes permisos para eliminar cotizaciones. Solicita esta acción al administrador.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['permiso_duplicar'] ) && $_GET['permiso_duplicar'] === '0' ) : ?>
			<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'No tienes permisos para duplicar cotizaciones.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['duplicado'] ) && $_GET['duplicado'] === '1' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cotización duplicada. La copia aparece en el listado con «Copia» en el título.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['duplicar_error'] ) && $_GET['duplicar_error'] === '1' ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'No se pudo duplicar la cotización. Inténtelo de nuevo o contacte al administrador.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['eliminado'] ) && $_GET['eliminado'] === '1' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cotización eliminada.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $_GET['precios_sync'] ) && $_GET['precios_sync'] === '1' ) : ?>
			<?php
			$na = isset( $_GET['cotiz_actualizadas'] ) ? absint( $_GET['cotiz_actualizadas'] ) : 0;
			$nl = isset( $_GET['lineas_corregidas'] ) ? absint( $_GET['lineas_corregidas'] ) : 0;
			$nc = isset( $_GET['productos_api_calls'] ) ? absint( $_GET['productos_api_calls'] ) : 0;
			?>
			<div class="notice notice-success is-dismissible">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: cotizaciones actualizadas, 2: líneas con precio corregido, 3: llamadas API producto */
							__( 'Sincronización Syscom: %1$d cotización(es) actualizada(s), %2$d línea(s) con precio corregido. Consultas a detalle de producto: %3$d.', 'centinela-group-theme' ),
							$na,
							$nl,
							$nc
						)
					);
					?>
				</p>
			</div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Cotizaciones guardadas. Podrás consultarlas, editarlas o enviarlas por email en PDF.', 'centinela-group-theme' ); ?></p>
		<div class="centinela-mis-cotizaciones-buscar" style="margin:12px 0 16px;display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
			<label for="centinela-mis-cotizaciones-buscar" style="font-weight:600;">
				<?php esc_html_e( 'Buscar por cliente', 'centinela-group-theme' ); ?>
			</label>
			<input
				type="search"
				id="centinela-mis-cotizaciones-buscar"
				value="<?php echo esc_attr( $buscar ); ?>"
				class="regular-text"
				style="max-width:min(100%,360px);"
				placeholder="<?php esc_attr_e( 'Nombre o NIT / C.C. (búsqueda al escribir)', 'centinela-group-theme' ); ?>"
				autocomplete="off"
				data-initial-q="<?php echo esc_attr( $buscar ); ?>"
			/>
			<button type="button" class="button" id="centinela-mis-cotizaciones-limpiar"<?php echo $buscar === '' ? ' hidden' : ''; ?>><?php esc_html_e( 'Limpiar', 'centinela-group-theme' ); ?></button>
			<span class="description" id="centinela-mis-cotizaciones-buscar-estado" style="margin:0;" aria-live="polite"></span>
		</div>
		<p class="description" style="margin-top:-8px;">
			<?php esc_html_e( 'La búsqueda filtra al escribir (nombre, NIT / C.C. u otros datos guardados en la cotización).', 'centinela-group-theme' ); ?>
		</p>
		<?php if ( class_exists( 'Centinela_Syscom_API' ) ) : ?>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?action=centinela_cotizador_sync_zero_prices' ), 'centinela_cotizador_sync_zero_prices' ) ); ?>">
					<?php esc_html_e( 'Actualizar precios en $0 desde Syscom', 'centinela-group-theme' ); ?>
				</a>
				<span class="description" style="margin-left:8px;">
					<?php esc_html_e( 'Recorre las cotizaciones guardadas y vuelve a pedir el precio a la API para líneas con precio 0 (misma lógica lista/oferta que al cotizar).', 'centinela-group-theme' ); ?>
				</span>
			</p>
		<?php endif; ?>
		<style>#centinela-mis-cotizaciones-tbody.is-loading{opacity:.55;pointer-events:none}</style>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Título', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Cliente', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'NIT / C.C.', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Moneda', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Total', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Fecha', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Acciones', 'centinela-group-theme' ); ?></th>
				</tr>
			</thead>
			<tbody id="centinela-mis-cotizaciones-tbody">
				<?php if ( $query->have_posts() ) : ?>
					<?php
					while ( $query->have_posts() ) :
						$query->the_post();
						$datos         = get_post_meta( get_the_ID(), '_cotizacion_datos', true );
						$cliente_arr   = is_array( $datos ) && isset( $datos['cliente'] ) && is_array( $datos['cliente'] ) ? $datos['cliente'] : array();
						$cli_nombre    = isset( $cliente_arr['nombre'] ) ? trim( (string) $cliente_arr['nombre'] ) : '';
						$cli_nit       = isset( $cliente_arr['nit_cc'] ) ? trim( (string) $cliente_arr['nit_cc'] ) : '';
						$cli_nombre_disp = $cli_nombre !== '' ? $cli_nombre : '—';
						$cli_nit_disp    = $cli_nit !== '' ? $cli_nit : '—';
						$moneda        = centinela_cotizador_normalize_moneda( is_array( $datos ) && isset( $datos['moneda'] ) ? $datos['moneda'] : 'COP' );
						$total         = is_array( $datos ) && isset( $datos['total'] ) ? floatval( $datos['total'] ) : 0;
						$total_fmt     = $moneda === 'USD' ? 'USD $ ' . number_format( $total, 2, '.', ',' ) : 'CO $ ' . number_format( $total, 0, ',', '.' );
						$editar_url    = add_query_arg( 'editar', get_the_ID(), admin_url( 'admin.php?page=centinela-cotizador' ) );
						$eliminar_url  = add_query_arg(
							array(
								'eliminar' => get_the_ID(),
								'_wpnonce' => wp_create_nonce( 'centinela_eliminar_cotizacion_' . get_the_ID() ),
							),
							admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' )
						);
						$duplicar_url = add_query_arg(
							array(
								'duplicar' => get_the_ID(),
								'_wpnonce' => wp_create_nonce( 'centinela_duplicar_cotizacion_' . get_the_ID() ),
							),
							admin_url( 'admin.php?page=centinela-cotizador-mis-cotizaciones' )
						);
						$eliminar_confirm = esc_attr__( '¿Eliminar esta cotización? No se podrá deshacer.', 'centinela-group-theme' );
						$duplicar_confirm = esc_attr__( '¿Duplicar esta cotización? Se creará una nueva con el mismo contenido y «Copia» en el título.', 'centinela-group-theme' );
						?>
						<tr>
							<td><strong><?php the_title(); ?></strong></td>
							<td><?php echo esc_html( $cli_nombre_disp ); ?></td>
							<td><?php echo esc_html( $cli_nit_disp ); ?></td>
							<td><?php echo esc_html( $moneda ); ?></td>
							<td><?php echo esc_html( $total_fmt ); ?></td>
							<td><?php echo esc_html( get_the_date() ); ?></td>
							<td>
								<a href="<?php echo esc_url( $editar_url ); ?>" class="button button-small"><?php esc_html_e( 'Editar', 'centinela-group-theme' ); ?></a>
								<a href="<?php echo esc_url( $duplicar_url ); ?>" class="button button-small" onclick="return confirm('<?php echo esc_js( $duplicar_confirm ); ?>');"><?php esc_html_e( 'Duplicar', 'centinela-group-theme' ); ?></a>
								<?php if ( current_user_can( 'manage_options' ) ) : ?>
									<a href="<?php echo esc_url( $eliminar_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( $eliminar_confirm ); ?>');"><?php esc_html_e( 'Eliminar', 'centinela-group-theme' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endwhile; ?>
					<?php wp_reset_postdata(); ?>
				<?php elseif ( $buscar !== '' ) : ?>
					<tr class="centinela-mis-cot-no-results">
						<td colspan="7">
							<?php esc_html_e( 'No hay cotizaciones que coincidan con el término:', 'centinela-group-theme' ); ?>
							<strong><?php echo esc_html( $buscar ); ?></strong>.
							<?php esc_html_e( 'Pruebe con otra palabra o con parte del NIT.', 'centinela-group-theme' ); ?>
							<a class="button" style="margin-left:8px;" href="<?php echo esc_url( $mis_cot_base_url ); ?>"><?php esc_html_e( 'Ver todas', 'centinela-group-theme' ); ?></a>
						</td>
					</tr>
				<?php else : ?>
					<tr class="centinela-mis-cot-empty-list">
						<td colspan="7"><?php esc_html_e( 'No hay cotizaciones guardadas. Crea una desde el Cotizador y guárdala.', 'centinela-group-theme' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
		if ( $mis_max_pages > 1 ) {
			$pagination_base = $mis_cot_base_url;
			if ( $buscar !== '' ) {
				$pagination_base = add_query_arg( 'buscar', $buscar, $pagination_base );
			}
			$pagination_base = str_replace(
				'999999999',
				'%#%',
				esc_url( add_query_arg( 'paged', 999999999, $pagination_base ) )
			);
			echo '<div id="centinela-mis-cotizaciones-pagination" class="tablenav" style="margin-top:12px;"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => $pagination_base,
						'format'    => '',
						'current'   => $paged,
						'total'     => $mis_max_pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'type'      => 'plain',
					)
				)
			);
			echo '</div></div>';
		} else {
			echo '<div id="centinela-mis-cotizaciones-pagination" style="display:none;"></div>';
		}
		?>
	</div>
	<?php
}
