<?php
/**
 * Cotizaciones Web Form – CPT, menú admin y guardado de envíos
 * Las entradas se guardan en el admin bajo "Cotizaciones Web Form".
 * Opcionalmente se envía copia a uno o más correos configurados en el widget.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar CPT para envíos del formulario web de cotizaciones
 */
function centinela_cotizaciones_web_form_register_cpt() {
	register_post_type( 'cotizacion_web_form', array(
		'labels'             => array(
			'name'               => _x( 'Cotizaciones Web Form', 'post type general name', 'centinela-group-theme' ),
			'singular_name'      => _x( 'Cotización Web', 'post type singular name', 'centinela-group-theme' ),
			'menu_name'          => __( 'Cotizaciones Web Form', 'centinela-group-theme' ),
			'add_new'            => __( 'Añadir nueva', 'centinela-group-theme' ),
			'add_new_item'       => __( 'Añadir nueva cotización', 'centinela-group-theme' ),
			'edit_item'          => __( 'Ver cotización', 'centinela-group-theme' ),
			'new_item'           => __( 'Nueva cotización', 'centinela-group-theme' ),
			'view_item'          => __( 'Ver cotización', 'centinela-group-theme' ),
			'search_items'       => __( 'Buscar cotizaciones', 'centinela-group-theme' ),
			'not_found'          => __( 'No hay cotizaciones.', 'centinela-group-theme' ),
			'not_found_in_trash' => __( 'No hay cotizaciones en la papelera.', 'centinela-group-theme' ),
		),
		'public'             => false,
		'show_ui'            => true,
		'show_in_menu'       => false, // Se muestra bajo nuestro menú personalizado.
		'capability_type'    => 'post',
		'map_meta_cap'       => true,
		'supports'           => array( 'title' ),
		'has_archive'        => false,
		'rewrite'            => false,
	) );
}
add_action( 'init', 'centinela_cotizaciones_web_form_register_cpt' );

/**
 * Registrar menú "Cotizaciones Web Form" y listar entradas del CPT
 */
function centinela_cotizaciones_web_form_register_menu() {
	add_menu_page(
		__( 'Cotizaciones Web Form', 'centinela-group-theme' ),
		__( 'Cotizaciones Web Form', 'centinela-group-theme' ),
		'edit_posts',
		'centinela-cotizaciones-web-form',
		'centinela_cotizaciones_web_form_render_list_page',
		'dashicons-email-alt',
		57
	);
	add_submenu_page(
		'centinela-cotizaciones-web-form',
		__( 'Todas las cotizaciones', 'centinela-group-theme' ),
		__( 'Todas las cotizaciones', 'centinela-group-theme' ),
		'edit_posts',
		'centinela-cotizaciones-web-form',
		'centinela_cotizaciones_web_form_render_list_page'
	);
	// Página de detalle (sin entrada en menú; se accede por ?page=centinela-cotizaciones-web-form-view&id=123)
	add_submenu_page(
		null,
		__( 'Ver cotización', 'centinela-group-theme' ),
		'',
		'edit_posts',
		'centinela-cotizaciones-web-form-view',
		'centinela_cotizaciones_web_form_render_view_page'
	);
}
add_action( 'admin_menu', 'centinela_cotizaciones_web_form_register_menu', 20 );

/**
 * Ocultar el CPT del menú principal (solo mostramos nuestro menú)
 */
function centinela_cotizaciones_web_form_remove_cpt_from_menu() {
	remove_menu_page( 'edit.php?post_type=cotizacion_web_form' );
}
add_action( 'admin_menu', 'centinela_cotizaciones_web_form_remove_cpt_from_menu', 99 );

/**
 * Página de listado de cotizaciones (sustituye la del CPT)
 */
function centinela_cotizaciones_web_form_render_list_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$eliminar_id = isset( $_GET['eliminar'] ) ? absint( $_GET['eliminar'] ) : 0;
	if ( $eliminar_id > 0 && isset( $_GET['_wpnonce'] ) ) {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );
		if ( wp_verify_nonce( $nonce, 'centinela_cwf_eliminar_' . $eliminar_id ) ) {
			$post = get_post( $eliminar_id );
			if ( $post && $post->post_type === 'cotizacion_web_form' ) {
				wp_trash_post( $eliminar_id );
				wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizaciones-web-form&eliminado=1' ) );
				exit;
			}
		}
	}
	$paged = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
	$query = new WP_Query( array(
		'post_type'      => 'cotizacion_web_form',
		'post_status'    => 'publish',
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'paged'          => $paged,
	) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Cotizaciones Web Form', 'centinela-group-theme' ); ?></h1>
		<?php if ( isset( $_GET['eliminado'] ) && $_GET['eliminado'] === '1' ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Cotización eliminada.', 'centinela-group-theme' ); ?></p></div>
		<?php endif; ?>
		<p class="description"><?php esc_html_e( 'Envíos del formulario de solicitud de cotización del sitio. Puedes ver el detalle o eliminar.', 'centinela-group-theme' ); ?></p>
		<?php if ( $query->have_posts() ) : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Asunto / Resumen', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Fecha', 'centinela-group-theme' ); ?></th>
						<th><?php esc_html_e( 'Acciones', 'centinela-group-theme' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post(); ?>
						<?php
						$view_url = add_query_arg( array(
							'page'   => 'centinela-cotizaciones-web-form-view',
							'id'     => get_the_ID(),
						), admin_url( 'admin.php' ) );
						$eliminar_url = add_query_arg( array(
							'eliminar' => get_the_ID(),
							'_wpnonce' => wp_create_nonce( 'centinela_cwf_eliminar_' . get_the_ID() ),
						), admin_url( 'admin.php?page=centinela-cotizaciones-web-form' ) );
						?>
						<tr>
							<td><strong><?php the_title(); ?></strong></td>
							<td><?php echo esc_html( get_the_date( '' ) . ' ' . get_the_time( '' ) ); ?></td>
							<td>
								<a href="<?php echo esc_url( $view_url ); ?>" class="button button-small"><?php esc_html_e( 'Ver', 'centinela-group-theme' ); ?></a>
								<a href="<?php echo esc_url( $eliminar_url ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php echo esc_js( __( '¿Eliminar esta cotización?', 'centinela-group-theme' ) ); ?>');"><?php esc_html_e( 'Eliminar', 'centinela-group-theme' ); ?></a>
							</td>
						</tr>
					<?php endwhile; ?>
				</tbody>
			</table>
			<?php
			$total_pages = $query->max_num_pages;
			if ( $total_pages > 1 ) {
				echo '<p class="pagination-links">';
				echo paginate_links( array(
					'base'    => add_query_arg( 'paged', '%#%' ),
					'format'  => '',
					'current' => $paged,
					'total'   => $total_pages,
				) );
				echo '</p>';
			}
			wp_reset_postdata();
			?>
		<?php else : ?>
			<p><?php esc_html_e( 'No hay cotizaciones guardadas. Los envíos del formulario aparecerán aquí.', 'centinela-group-theme' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Página de detalle de una cotización
 */
function centinela_cotizaciones_web_form_render_view_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
	if ( ! $id ) {
		wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizaciones-web-form' ) );
		exit;
	}
	$post = get_post( $id );
	if ( ! $post || $post->post_type !== 'cotizacion_web_form' ) {
		wp_safe_redirect( admin_url( 'admin.php?page=centinela-cotizaciones-web-form' ) );
		exit;
	}
	$datos = get_post_meta( $id, '_cotizacion_web_form_datos', true );
	$datos = is_array( $datos ) ? $datos : array();
	$list_url = admin_url( 'admin.php?page=centinela-cotizaciones-web-form' );
	?>
	<div class="wrap">
		<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php esc_html_e( 'Volver al listado', 'centinela-group-theme' ); ?></a></p>
		<h1><?php echo esc_html( get_the_title( $id ) ); ?></h1>
		<p class="description"><?php echo esc_html( get_the_date( '' ) . ' ' . get_the_time( '' ) ); ?></p>
		<table class="widefat striped">
			<thead>
				<tr>
					<th style="width:30%;"><?php esc_html_e( 'Campo', 'centinela-group-theme' ); ?></th>
					<th><?php esc_html_e( 'Valor', 'centinela-group-theme' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $datos as $label => $value ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $label ); ?></strong></td>
						<td><?php echo esc_html( is_array( $value ) ? wp_json_encode( $value ) : (string) $value ); ?></td>
					</tr>
				<?php endforeach; ?>
				<?php if ( empty( $datos ) ) : ?>
					<tr><td colspan="2"><?php esc_html_e( 'Sin datos guardados.', 'centinela-group-theme' ); ?></td></tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * Guardar envío del formulario (desde AJAX)
 *
 * @param array $datos Datos asociativos label => value.
 * @return int|WP_Error ID del post o error.
 */
function centinela_cotizaciones_web_form_save_submission( $datos ) {
	$titulo = '';
	if ( ! empty( $datos['nombre'] ) ) {
		$titulo = sanitize_text_field( $datos['nombre'] );
	}
	if ( $titulo === '' && ! empty( $datos['email'] ) ) {
		$titulo = sanitize_email( $datos['email'] );
	}
	if ( $titulo === '' && ! empty( $datos['Nombre'] ) ) {
		$titulo = sanitize_text_field( $datos['Nombre'] );
	}
	if ( $titulo === '' && ! empty( $datos['Email'] ) ) {
		$titulo = sanitize_email( $datos['Email'] );
	}
	if ( $titulo === '' ) {
		$titulo = __( 'Cotización web', 'centinela-group-theme' ) . ' ' . current_time( 'Y-m-d H:i' );
	}
	$post_data = array(
		'post_type'   => 'cotizacion_web_form',
		'post_title'  => wp_kses_post( $titulo ),
		'post_status' => 'publish',
		'post_author' => 0,
	);
	$post_id = wp_insert_post( $post_data );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	update_post_meta( $post_id, '_cotizacion_web_form_datos', $datos );
	return $post_id;
}

/**
 * Construir cuerpo del correo (HTML) con los datos del formulario
 *
 * @param array $datos Datos del formulario.
 * @return string HTML.
 */
function centinela_cotizaciones_web_form_build_email_body( $datos ) {
	$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;line-height:1.5;color:#333;max-width:600px;margin:0 auto;padding:20px;">';
	$html .= '<h2 style="margin-top:0;">' . esc_html__( 'Nueva solicitud de cotización (Web Form)', 'centinela-group-theme' ) . '</h2>';
	$html .= '<table style="width:100%;border-collapse:collapse;">';
	foreach ( $datos as $label => $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}
		$html .= '<tr><td style="padding:8px;border:1px solid #ddd;background:#f9f9f9;width:30%;"><strong>' . esc_html( $label ) . '</strong></td><td style="padding:8px;border:1px solid #ddd;">' . esc_html( (string) $value ) . '</td></tr>';
	}
	$html .= '</table>';
	$html .= '<p style="margin-top:1.5em;color:#666;font-size:12px;">' . esc_html__( 'Enviado desde el formulario de cotizaciones del sitio web.', 'centinela-group-theme' ) . '</p>';
	$html .= '</body></html>';
	return $html;
}

/**
 * AJAX: recibir envío del formulario (guardar + opcionalmente enviar copia por correo)
 */
function centinela_cotizaciones_web_form_ajax_submit() {
	$nonce = isset( $_POST['centinela_cwf_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['centinela_cwf_nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'centinela_cotizaciones_web_form_submit' ) ) {
		wp_send_json_error( array( 'message' => __( 'Error de seguridad. Recarga la página e intenta de nuevo.', 'centinela-group-theme' ) ) );
	}
	$raw = isset( $_POST['centinela_cwf_data'] ) ? wp_unslash( $_POST['centinela_cwf_data'] ) : '';
	if ( is_string( $raw ) ) {
		$datos = json_decode( $raw, true );
	} else {
		$datos = is_array( $raw ) ? $raw : array();
	}
	if ( empty( $datos ) || ! is_array( $datos ) ) {
		wp_send_json_error( array( 'message' => __( 'No se recibieron datos del formulario.', 'centinela-group-theme' ) ) );
	}
	// Sanitizar valores (mantener estructura label => value)
	$sanitized = array();
	foreach ( $datos as $label => $value ) {
		$key = sanitize_text_field( $label );
		if ( is_array( $value ) ) {
			$sanitized[ $key ] = array_map( 'sanitize_text_field', $value );
		} else {
			$sanitized[ $key ] = sanitize_textarea_field( (string) $value );
		}
	}
	$post_id = centinela_cotizaciones_web_form_save_submission( $sanitized );
	if ( is_wp_error( $post_id ) ) {
		wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
	}
	$email_recipients = isset( $_POST['centinela_cwf_emails'] ) ? sanitize_textarea_field( wp_unslash( $_POST['centinela_cwf_emails'] ) ) : '';
	$sent = false;
	if ( $email_recipients !== '' ) {
		$emails = preg_split( '/[\s,;]+/', $email_recipients, -1, PREG_SPLIT_NO_EMPTY );
		$emails = array_unique( array_filter( array_map( 'sanitize_email', $emails ) ) );
		if ( ! empty( $emails ) ) {
			$subject = sprintf( __( '[%s] Nueva solicitud de cotización', 'centinela-group-theme' ), get_bloginfo( 'name' ) );
			$body = centinela_cotizaciones_web_form_build_email_body( $sanitized );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			foreach ( $emails as $to ) {
				if ( is_email( $to ) ) {
					$sent = wp_mail( $to, $subject, $body, $headers ) || $sent;
				}
			}
		}
	}
	wp_send_json_success( array(
		'message' => __( 'Tu solicitud de cotización ha sido enviada correctamente. Nos pondremos en contacto contigo.', 'centinela-group-theme' ),
		'saved'   => true,
		'emailed' => $sent,
	) );
}
add_action( 'wp_ajax_centinela_cotizaciones_web_form_submit', 'centinela_cotizaciones_web_form_ajax_submit' );
add_action( 'wp_ajax_nopriv_centinela_cotizaciones_web_form_submit', 'centinela_cotizaciones_web_form_ajax_submit' );
