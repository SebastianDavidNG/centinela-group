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
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
	register_setting(
		'centinela_syscom_options',
		'centinela_syscom_client_secret',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
		)
	);
}
add_action( 'admin_init', 'centinela_syscom_register_settings' );

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
	</div>
	<?php
}
