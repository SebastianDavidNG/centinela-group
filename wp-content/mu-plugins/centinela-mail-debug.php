<?php
/**
 * Plugin Name: Centinela Mail Debug (MU)
 * Description: Registra envíos wp_mail (éxito y fallo) para diagnosticar cotizador vs correo de prueba. Activar con CENTINELA_MAIL_DEBUG en wp-config.php. Quitar o desactivar cuando termine el diagnóstico.
 * Author: Centinela Group
 *
 * @package Centinela_Group
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CENTINELA_MAIL_DEBUG' ) || ! CENTINELA_MAIL_DEBUG ) {
	return;
}

/**
 * @param string $line Línea de log (sin salto final).
 */
function centinela_mail_debug_log( $line ) {
	$line = is_string( $line ) ? $line : wp_json_encode( $line );
	$msg  = '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $line . "\n";
	$file = WP_CONTENT_DIR . '/centinela-mail-debug.log';
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	@file_put_contents( $file, $msg, FILE_APPEND | LOCK_EX );
	error_log( 'Centinela Mail Debug: ' . trim( $line ) );
}

/**
 * Tamaños de adjuntos para ver si el mensaje es muy pesado.
 *
 * @param array $paths Rutas absolutas.
 * @return string
 */
function centinela_mail_debug_attachment_summary( $paths ) {
	if ( empty( $paths ) || ! is_array( $paths ) ) {
		return 'attachments=0';
	}
	$parts = array();
	foreach ( $paths as $key => $p ) {
		if ( ! is_string( $p ) || $p === '' ) {
			continue;
		}
		$bytes = file_exists( $p ) ? (int) filesize( $p ) : -1;
		$label = ( is_string( $key ) && $key !== '' && ! is_numeric( $key ) ) ? $key : basename( $p );
		$parts[] = $label . '=' . $bytes . 'b';
	}
	return 'attachments=' . count( $parts ) . ' [' . implode( '; ', $parts ) . ']';
}

/**
 * Antes del envío: misma ruta que usará el correo (SMTP/mail) y cabeceras vistas por el relay.
 * Prioridad alta para que WP Mail SMTP u otros ya hayan ajustado PHPMailer.
 */
add_action(
	'phpmailer_init',
	static function ( $phpmailer ) {
		if ( ! is_object( $phpmailer ) || ! method_exists( $phpmailer, 'getToAddresses' ) ) {
			return;
		}
		$host   = isset( $phpmailer->Host ) ? (string) $phpmailer->Host : '';
		$port   = isset( $phpmailer->Port ) ? (string) $phpmailer->Port : '';
		$sec    = isset( $phpmailer->SMTPSecure ) ? (string) $phpmailer->SMTPSecure : '';
		$mailer = isset( $phpmailer->Mailer ) ? (string) $phpmailer->Mailer : '';
		$from   = isset( $phpmailer->From ) ? (string) $phpmailer->From : '';
		$fromn  = isset( $phpmailer->FromName ) ? (string) $phpmailer->FromName : '';
		$subj   = isset( $phpmailer->Subject ) ? (string) $phpmailer->Subject : '';

		$to_labels = array();
		foreach ( $phpmailer->getToAddresses() as $pair ) {
			if ( ! empty( $pair[0] ) ) {
				$to_labels[] = $pair[0];
			}
		}
		$rt_labels = array();
		if ( method_exists( $phpmailer, 'getReplyToAddresses' ) ) {
			foreach ( $phpmailer->getReplyToAddresses() as $pair ) {
				if ( ! empty( $pair[0] ) ) {
					$rt_labels[] = $pair[0];
				}
			}
		}
		$fromn_flat = preg_replace( '/\s+/', ' ', $fromn );
		$fromn_flat = is_string( $fromn_flat ) ? $fromn_flat : $fromn;

		$trace_extra = '';
		if ( method_exists( $phpmailer, 'getCustomHeaders' ) ) {
			foreach ( $phpmailer->getCustomHeaders() as $pair ) {
				$hname = isset( $pair[0] ) ? (string) $pair[0] : '';
				if ( stripos( $hname, 'X-Centinela-Cotizacion-Trace' ) === 0 ) {
					$trace_extra = ' | trace=' . ( isset( $pair[1] ) ? (string) $pair[1] : '' );
					break;
				}
			}
		}

		centinela_mail_debug_log(
			'PHPMailer | mailer=' . $mailer
			. ' | host=' . $host
			. ' | port=' . $port
			. ' | SMTPSecure=' . $sec
			. ' | from=' . $from
			. ' | from_name=' . substr( $fromn_flat, 0, 80 )
			. ' | to=' . implode( ',', $to_labels )
			. ' | reply_to=' . implode( ',', $rt_labels )
			. ' | subject=' . substr( $subj, 0, 160 )
			. $trace_extra
		);
	},
	999,
	1
);

add_action(
	'wp_mail_succeeded',
	static function ( $mail_data ) {
		if ( ! is_array( $mail_data ) ) {
			return;
		}
		$to      = isset( $mail_data['to'] ) ? $mail_data['to'] : array();
		$subject = isset( $mail_data['subject'] ) ? (string) $mail_data['subject'] : '';
		$msg     = isset( $mail_data['message'] ) ? (string) $mail_data['message'] : '';
		$atts    = isset( $mail_data['attachments'] ) ? $mail_data['attachments'] : array();
		$headers = isset( $mail_data['headers'] ) ? $mail_data['headers'] : array();

		$to_str = is_array( $to ) ? implode( ', ', $to ) : (string) $to;
		centinela_mail_debug_log(
			'OK | to=' . $to_str
			. ' | subject=' . substr( $subject, 0, 200 )
			. ' | body_bytes=' . strlen( $msg )
			. ' | ' . centinela_mail_debug_attachment_summary( is_array( $atts ) ? $atts : array() )
			. ' | headers_n=' . ( is_array( $headers ) ? count( $headers ) : 0 )
		);
	},
	10,
	1
);

add_action(
	'wp_mail_failed',
	static function ( $error ) {
		$msg = $error instanceof WP_Error ? $error->get_error_message() : 'unknown';
		$data = $error instanceof WP_Error ? $error->get_error_data() : null;
		$extra = '';
		if ( is_array( $data ) ) {
			$to = isset( $data['to'] ) ? $data['to'] : '';
			$extra = ' | to=' . ( is_array( $to ) ? implode( ', ', $to ) : (string) $to );
			if ( ! empty( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
				$extra .= ' | ' . centinela_mail_debug_attachment_summary( $data['attachments'] );
			}
		}
		centinela_mail_debug_log( 'FAIL | ' . $msg . $extra );
	},
	10,
	1
);
