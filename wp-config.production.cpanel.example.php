<?php
/**
 * Plantilla wp-config.php para producción en cPanel (Centinela Group).
 *
 * USO:
 * 1) Copiar este archivo como wp-config.php en el servidor (o fusionar bloques con el tuyo).
 * 2) Rellenar DB_* y el bloque de claves/sales (https://api.wordpress.org/secret-key/1.1/salt/).
 * 3) Ajustar WP_HOME / WP_SITEURL si el dominio cambia.
 * 4) Con DISABLE_WP_CRON en true, crear tarea cron en cPanel (ver comentario abajo).
 *
 * No subas a git un wp-config.php con credenciales reales.
 *
 * @package Centinela_Group
 */

define( 'WP_CACHE', true );

// ** Base de datos (cPanel → MySQL® Databases / phpMyAdmin) ** //
define( 'DB_NAME', 'REEMPLAZAR_NOMBRE_BD' );
define( 'DB_USER', 'REEMPLAZAR_USUARIO_BD' );
define( 'DB_PASSWORD', 'REEMPLAZAR_CONTRASENA_BD' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

/**#@+
 * Claves y sales únicas: generar en https://api.wordpress.org/secret-key/1.1/salt/
 * y reemplazar TODO el bloque que devuelve esa página (o pegar cada define aquí).
 */
define( 'AUTH_KEY',         'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'SECURE_AUTH_KEY',  'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'LOGGED_IN_KEY',    'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'NONCE_KEY',        'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'AUTH_SALT',        'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'SECURE_AUTH_SALT', 'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'LOGGED_IN_SALT',   'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
define( 'NONCE_SALT',       'pon-aqui-60-caracteres-aleatorios-o-pega-bloque-salt-api' );
/**#@-*/

$table_prefix = 'wp_';

/* Depuración: en producción siempre desactivado */
define( 'WP_DEBUG', false );
define( 'WP_DEBUG_LOG', false );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', '0' );

/**
 * Endurecimiento: impide editar plugins/temas desde Apariencia → Editor y Plugins → Editor.
 * Las actualizaciones desde el panel y WP-CLI siguen funcionando.
 */
define( 'DISALLOW_FILE_EDIT', true );

/**
 * Fuerza HTTPS en el escritorio (coherente con sitio solo-HTTPS).
 */
define( 'FORCE_SSL_ADMIN', true );

/**
 * URLs públicas (deben coincidir con siteurl/home en la BD o corregir la BD tras migración).
 */
define( 'WP_HOME', 'https://centinelagroup.com' );
define( 'WP_SITEURL', 'https://centinelagroup.com' );

/**
 * Proxy / balanceador delante (cPanel + Cloudflare u host con terminación SSL).
 */
if ( isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ) {
	$_SERVER['HTTPS'] = 'on';
}

define( 'WP_MEMORY_LIMIT', '256M' );
define( 'WP_MAX_MEMORY_LIMIT', '256M' );

/**
 * Cron: con true, WordPress no dispara wp-cron.php en cada visita.
 * En cPanel → Cron Jobs, añadir cada 5–15 minutos, por ejemplo:
 *
 * /usr/bin/php -q /home/TU_USUARIO/public_html/wp-cron.php >/dev/null 2>&1
 *
 * (Sustituye TU_USUARIO y la ruta real a public_html; en algunos hostings es home/USER/domain.com/)
 *
 * Alternativa por URL (útil si prefieres no usar ruta PHP):
 * wget -q -O - "https://centinelagroup.com/wp-cron.php?doing_wp_cron" >/dev/null 2>&1
 */
define( 'DISABLE_WP_CRON', true );

/**
 * Opcional: no definir CENTINELA_MAIL_DEBUG en producción (evita logs sensibles en wp-content/).
 * Opcional: desactivar o no subir mu-plugins de diagnóstico en producción.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
