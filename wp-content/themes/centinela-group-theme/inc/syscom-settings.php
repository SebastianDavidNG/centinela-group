<?php
/**
 * Opciones del tema: API Syscom Colombia (Client ID / Client Secret)
 * Permite configurar credenciales y vaciar caché al guardar.
 *
 * @package Centinela_Group_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registrar opciones y menú de configuración
 */
function centinela_syscom_register_settings() {
	register_setting(
		'centinela_syscom_options',
		'centinela_syscom_client_id',
		array(
			'type'              => 'string',
			'sanitize_callback' => function( $value ) {
				return trim( sanitize_text_field( $value ) );
			},
		)
	);
	register_setting(
		'centinela_syscom_options',
		'centinela_syscom_client_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => function( $value ) {
				return trim( sanitize_text_field( $value ) );
			},
		)
	);
}
add_action( 'admin_init', 'centinela_syscom_register_settings' );

/**
 * Procesar vaciado de caché desde la página de opciones (enlace con nonce).
 */
function centinela_syscom_handle_flush_cache() {
	if ( ! isset( $_GET['centinela_syscom_flush'] ) || $_GET['centinela_syscom_flush'] !== '1' ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'centinela_syscom_flush' ) ) {
		return;
	}
	Centinela_Syscom_API::flush_cache();
	$redirect = add_query_arg( 'centinela_syscom_flushed', '1', admin_url( 'themes.php?page=centinela-syscom' ) );
	wp_safe_redirect( $redirect );
	exit;
}
add_action( 'admin_init', 'centinela_syscom_handle_flush_cache' );

/**
 * Vaciar caché API al guardar opciones
 */
function centinela_syscom_flush_cache_on_save( $value ) {
	Centinela_Syscom_API::flush_cache();
	return $value;
}
add_filter( 'pre_update_option_centinela_syscom_client_id', 'centinela_syscom_flush_cache_on_save' );
add_filter( 'pre_update_option_centinela_syscom_client_secret', 'centinela_syscom_flush_cache_on_save' );

/**
 * Añadir página de opciones bajo Apariencia
 */
function centinela_syscom_add_options_page() {
	add_theme_page(
		__( 'API Syscom Colombia', 'centinela-group-theme' ),
		__( 'API Syscom', 'centinela-group-theme' ),
		'manage_options',
		'centinela-syscom',
		'centinela_syscom_options_page_render'
	);
}
add_action( 'admin_menu', 'centinela_syscom_add_options_page' );

/**
 * Contenido de la página de opciones
 */
function centinela_syscom_options_page_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$client_id     = Centinela_Syscom_API::get_client_id();
	$client_secret = Centinela_Syscom_API::get_client_secret();
	$using_const   = defined( 'CENTINELA_SYSCOM_CLIENT_ID' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'API Syscom Colombia', 'centinela-group-theme' ); ?></h1>
		<p><?php esc_html_e( 'Credenciales para el submenú de categorías (Videovigilancia, Control de Acceso, etc.) desde la API de Syscom.', 'centinela-group-theme' ); ?></p>
		<p>
			<a href="https://developers.syscomcolombia.com/login" target="_blank" rel="noopener"><?php esc_html_e( 'Obtener Client ID y Secret en developers.syscomcolombia.com', 'centinela-group-theme' ); ?></a>
		</p>
		<p class="description"><?php esc_html_e( 'Al pegar, evita espacios al inicio o al final. Si aparece "Client authentication failed", verifica que el Client ID y el Secret correspondan a la misma aplicación y que no hayas regenerado el Secret después de copiarlo.', 'centinela-group-theme' ); ?></p>
		<?php if ( $using_const ) : ?>
			<p><strong><?php esc_html_e( 'Actualmente se usan constantes definidas en wp-config.php (CENTINELA_SYSCOM_CLIENT_ID / CENTINELA_SYSCOM_CLIENT_SECRET). Los valores de abajo se ignoran.', 'centinela-group-theme' ); ?></strong></p>
		<?php endif; ?>
		<form action="options.php" method="post">
			<?php settings_fields( 'centinela_syscom_options' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="centinela_syscom_client_id"><?php esc_html_e( 'Client ID', 'centinela-group-theme' ); ?></label></th>
					<td>
						<input type="text" id="centinela_syscom_client_id" name="centinela_syscom_client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" <?php echo $using_const ? 'readonly' : ''; ?> />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="centinela_syscom_client_secret"><?php esc_html_e( 'Client Secret', 'centinela-group-theme' ); ?></label></th>
					<td>
						<input type="password" id="centinela_syscom_client_secret" name="centinela_syscom_client_secret" value="<?php echo esc_attr( $client_secret ); ?>" class="regular-text" <?php echo $using_const ? 'readonly' : ''; ?> />
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Guardar cambios', 'centinela-group-theme' ) ); ?>
		</form>
		<p><?php esc_html_e( 'Al guardar se vacía la caché de token y categorías para que el submenú se actualice con los datos de la API.', 'centinela-group-theme' ); ?></p>

		<hr style="margin: 2rem 0;" />
		<h2><?php esc_html_e( 'Validación: vista previa del menú', 'centinela-group-theme' ); ?></h2>
		<p><?php esc_html_e( 'Comprueba que la API devuelve las categorías que se mostrarán en el submenú debajo del header (como en Figma).', 'centinela-group-theme' ); ?></p>
		<p>
			<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'centinela_syscom_flush', '1', admin_url( 'themes.php?page=centinela-syscom' ) ), 'centinela_syscom_flush' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Vaciar caché y volver a cargar categorías', 'centinela-group-theme' ); ?></a>
		</p>
		<?php
		if ( ! empty( $_GET['centinela_syscom_flushed'] ) ) {
			echo '<p><strong>' . esc_html__( 'Caché vaciada. Resultados abajo corresponden a una nueva petición a la API.', 'centinela-group-theme' ) . '</strong></p>';
		}

		$arbol = Centinela_Syscom_API::get_categorias_arbol();
		if ( is_wp_error( $arbol ) ) {
			$err_msg = $arbol->get_error_message();
			echo '<div class="notice notice-error inline"><p><strong>' . esc_html__( 'Error al conectar con la API:', 'centinela-group-theme' ) . '</strong> ' . esc_html( $err_msg ) . '</p></div>';
			if ( stripos( $err_msg, 'client authentication failed' ) !== false || stripos( $err_msg, 'invalid_client' ) !== false ) {
				echo '<div class="notice notice-warning inline" style="max-width: 640px;"><p><strong>' . esc_html__( 'Credenciales vencidas o inválidas: qué hacer', 'centinela-group-theme' ) . '</strong></p><ol style="list-style: decimal; margin-left: 1.5em;">';
				echo '<li>' . esc_html__( 'Quien tenga acceso a la cuenta SYSCOM Colombia debe entrar a', 'centinela-group-theme' ) . ' <a href="https://developers.syscomcolombia.com/login" target="_blank" rel="noopener">developers.syscomcolombia.com</a>.</li>';
				echo '<li>' . esc_html__( 'Iniciar sesión con la cuenta regular de SYSCOM (la misma que usa el cliente para pedidos).', 'centinela-group-theme' ) . '</li>';
				echo '<li>' . esc_html__( 'Abrir la aplicación existente o crear una nueva; copiar el Client ID y el Client Secret que se muestran allí (si se regenera el Secret, hay que usar el nuevo).', 'centinela-group-theme' ) . '</li>';
				echo '<li>' . esc_html__( 'Pegar aquí arriba los valores actuales, guardar cambios y volver a usar «Vaciar caché y volver a cargar categorías».', 'centinela-group-theme' ) . '</li>';
				echo '<li>' . esc_html__( 'Si el problema continúa, el cliente puede contactar a SYSCOM para confirmar que la cuenta tiene acceso API y que las credenciales están activas.', 'centinela-group-theme' ) . '</li>';
				echo '</ol></div>';
			} else {
				echo '<p>' . esc_html__( 'Revisa Client ID y Client Secret en developers.syscomcolombia.com y que estén guardados correctamente arriba.', 'centinela-group-theme' ) . '</p>';
			}
		} elseif ( empty( $arbol ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'La API respondió correctamente pero no se obtuvieron categorías (lista vacía). Puede que el endpoint o el formato de respuesta haya cambiado.', 'centinela-group-theme' ) . '</p></div>';
		} else {
			$total_n2 = 0;
			$total_n3 = 0;
			foreach ( $arbol as $c ) {
				$hijos = isset( $c['hijos'] ) ? $c['hijos'] : array();
				$total_n2 += count( $hijos );
				foreach ( $hijos as $h ) {
					$total_n3 += isset( $h['hijos'] ) ? count( $h['hijos'] ) : 0;
				}
			}
			echo '<div class="notice notice-success inline"><p><strong>' . esc_html__( 'Conexión correcta.', 'centinela-group-theme' ) . '</strong> ' . sprintf(
				/* translators: 1: categorías nivel 1, 2: grupos nivel 2, 3: ítems nivel 3 */
				esc_html__( '%1$d categorías (nivel 1), %2$d grupos (nivel 2) y %3$d subítems (nivel 3) para el submenú, como en syscomcolombia.com.', 'centinela-group-theme' ),
				count( $arbol ),
				$total_n2,
				$total_n3
			) . '</p></div>';
			echo '<p style="margin: 0.5rem 0 1rem;"><a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener" class="button button-primary">' . esc_html__( 'Ver submenú en el sitio', 'centinela-group-theme' ) . '</a> ';
			echo '<a href="' . esc_url( home_url( '/tienda/' ) ) . '" target="_blank" rel="noopener" class="button">' . esc_html__( 'Ir a Tienda', 'centinela-group-theme' ) . '</a></p>';
			echo '<table class="widefat striped" style="max-width: 900px;"><thead><tr><th>' . esc_html__( 'Categoría (nivel 1)', 'centinela-group-theme' ) . '</th><th>' . esc_html__( 'Grupos (nivel 2) e ítems (nivel 3)', 'centinela-group-theme' ) . '</th></tr></thead><tbody>';
			foreach ( $arbol as $cat ) {
				$hijos = isset( $cat['hijos'] ) ? $cat['hijos'] : array();
				$cell = '';
				foreach ( $hijos as $h ) {
					$cell .= '<strong>' . esc_html( $h['nombre'] ) . '</strong>';
					$nietos = isset( $h['hijos'] ) ? $h['hijos'] : array();
					if ( ! empty( $nietos ) ) {
						$cell .= ': ' . implode( ', ', array_map( function ( $n ) {
							return esc_html( $n['nombre'] );
						}, $nietos ) );
					}
					$cell .= '<br />';
				}
				if ( $cell === '' ) {
					$cell = '<em>' . esc_html__( '—', 'centinela-group-theme' ) . '</em>';
				}
				echo '<tr><td><strong>' . esc_html( $cat['nombre'] ) . '</strong> <span class="description">(ID: ' . esc_html( $cat['id'] ) . ')</span></td><td>' . $cell . '</td></tr>';
			}
			echo '</tbody></table>';
			echo '<p><em>' . esc_html__( 'Orden y nombres nivel 1 alineados con syscomcolombia.com. En el menú, cada categoría despliega sus grupos (nivel 2) y bajo cada grupo los ítems (nivel 3). Vacía la caché para refrescar.', 'centinela-group-theme' ) . '</em></p>';
		}
		?>
	</div>
	<?php
}
