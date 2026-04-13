#!/usr/bin/env php
<?php
/**
 * Prueba la API Syscom (CLI). Por defecto no carga WordPress.
 *
 * Uso (ruta al script depende del directorio actual):
 *   cd /ruta/wp-content/themes/centinela-group-theme && php scripts/syscom-api-probe.php
 *   php /ruta/al/proyecto/wp-content/themes/centinela-group-theme/scripts/syscom-api-probe.php
 *
 * Credenciales (elige una):
 *   --use-wp-config       # Solo lee wp-config.php (define literales CENTINELA_SYSCOM_*). No usa MySQL.
 *   --wp-config=/ruta/wp-config.php   # Con --use-wp-config, si la ruta por defecto falla
 *   --use-wp              # Carga WordPress: constantes u opciones del tema (requiere BD accesible)
 *   --wp-load=/ruta/wp-load.php   # Con --use-wp, si la detección automática falla
 *   export SYSCOM_CLIENT_ID / SYSCOM_CLIENT_SECRET
 *   --client-id=XXX --client-secret=YYY
 *
 * Opcional:
 *   --categoria=3075      # p. ej. portátiles UHF ICOM+KENWOOD en Syscom CO
 *
 * Verificar si las fichas web existen en la API (mismo criterio que el tema: ID corto y 30002+6dígitos):
 *   php scripts/syscom-api-probe.php --verify-public-urls
 *   php scripts/syscom-api-probe.php --verify-public-urls --url=https://www.syscomcolombia.com/producto/....html
 *   (extensión .php, no .py)
 *   php scripts/syscom-api-probe.php --verify-public-urls --id-prefix=30002
 *
 * @package Centinela_Group_Theme
 */

if ( php_sapi_name() !== 'cli' ) {
	exit( 'Solo CLI.' . PHP_EOL );
}

/**
 * Extrae CENTINELA_SYSCOM_CLIENT_ID / SECRET como cadenas literales en wp-config.php (sin ejecutar WP).
 * No resuelve getenv(), concatenaciones ni rutas _FILE; solo define( '…', 'valor' ) o "valor".
 *
 * @param string $path Ruta a wp-config.php.
 * @return array{0:string,1:string} client_id, client_secret (vacíos si no hay coincidencia literal).
 */
function syscom_probe_parse_wp_config_syscom_literals( $path ) {
	$raw = @file_get_contents( $path );
	if ( ! is_string( $raw ) || $raw === '' ) {
		return array( '', '' );
	}
	$id     = '';
	$secret = '';
	$pat = "/define\s*\(\s*['\"]CENTINELA_SYSCOM_CLIENT_(ID|SECRET)['\"]\s*,\s*['\"]([^'\"\\\\]*)['\"]\s*\)/";
	if ( preg_match_all( $pat, $raw, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $m ) {
			$which = $m[1];
			$val   = isset( $m[2] ) ? trim( (string) $m[2] ) : '';
			if ( $which === 'ID' ) {
				$id = $val;
			} elseif ( $which === 'SECRET' ) {
				$secret = $val;
			}
		}
	}
	return array( $id, $secret );
}

$base    = 'https://developers.syscomcolombia.com/api/v1/';
$token_url = 'https://developers.syscomcolombia.com/oauth/token';

$opts            = array();
$use_wp          = false;
$use_wp_config   = false;
$wp_load_in      = '';
$wp_config_in         = '';
$verify_public_urls   = false;
$id_prefix_opt        = '';
$extra_verify_urls    = array();

foreach ( array_slice( $argv, 1 ) as $arg ) {
	if ( $arg === '--verify-public-urls' ) {
		$verify_public_urls = true;
	} elseif ( preg_match( '/^--id-prefix=(\d+)$/', $arg, $mpx ) ) {
		$id_prefix_opt = $mpx[1];
	} elseif ( preg_match( '/^--url=(.+)$/', $arg, $murl ) ) {
		$extra_verify_urls[] = trim( $murl[1] );
	} elseif ( preg_match( '#^https?://#i', $arg ) ) {
		$extra_verify_urls[] = trim( $arg );
	} elseif ( preg_match( '/^--client-id=(.+)$/', $arg, $m ) ) {
		$opts['client_id'] = $m[1];
	} elseif ( preg_match( '/^--client-secret=(.+)$/', $arg, $m ) ) {
		$opts['client_secret'] = $m[1];
	} elseif ( preg_match( '/^--categoria=(.+)$/', $arg, $m ) ) {
		$opts['categoria'] = trim( $m[1] );
	} elseif ( $arg === '--use-wp' || $arg === '--from-wordpress' ) {
		$use_wp = true;
	} elseif ( $arg === '--use-wp-config' ) {
		$use_wp_config = true;
	} elseif ( preg_match( '/^--wp-load=(.+)$/', $arg, $m ) ) {
		$wp_load_in = trim( $m[1] );
	} elseif ( preg_match( '/^--wp-config=(.+)$/', $arg, $m ) ) {
		$wp_config_in = trim( $m[1] );
	}
}

$client_id     = isset( $opts['client_id'] ) ? $opts['client_id'] : ( getenv( 'SYSCOM_CLIENT_ID' ) ?: '' );
$client_secret = isset( $opts['client_secret'] ) ? $opts['client_secret'] : ( getenv( 'SYSCOM_CLIENT_SECRET' ) ?: '' );

if ( $use_wp_config ) {
	$wp_config = $wp_config_in !== '' ? $wp_config_in : dirname( __DIR__, 4 ) . '/wp-config.php';
	if ( ! is_readable( $wp_config ) ) {
		fwrite( STDERR, "No se encontró wp-config.php en:\n  {$wp_config}\nUsa: --wp-config=/ruta/al/wp-config.php\n" );
		exit( 1 );
	}
	list( $parsed_id, $parsed_secret ) = syscom_probe_parse_wp_config_syscom_literals( $wp_config );
	if ( $parsed_id !== '' ) {
		$client_id = $parsed_id;
	}
	if ( $parsed_secret !== '' ) {
		$client_secret = $parsed_secret;
	}
	if ( $client_id === '' || $client_secret === '' ) {
		fwrite( STDERR, "En wp-config.php no hay define( 'CENTINELA_SYSCOM_CLIENT_ID', '…' ) y define( 'CENTINELA_SYSCOM_CLIENT_SECRET', '…' ) con valores entre comillas (literales).\n\n" );
		fwrite( STDERR, "Si las credenciales solo están en el escritorio de WordPress (Apariencia → API Syscom), están en la base de datos: arranca MySQL/Docker y usa --use-wp, o copia el ID/Secret a:\n" );
		fwrite( STDERR, "  export SYSCOM_CLIENT_ID='…' SYSCOM_CLIENT_SECRET='…'\n" );
		exit( 1 );
	}
} elseif ( $use_wp ) {
	$wp_load = $wp_load_in !== '' ? $wp_load_in : dirname( __DIR__, 4 ) . '/wp-load.php';
	if ( ! is_readable( $wp_load ) ) {
		fwrite( STDERR, "No se encontró wp-load.php en:\n  {$wp_load}\nIndica la ruta con: --wp-load=/ruta/al/wp-load.php\n" );
		exit( 1 );
	}
	require_once $wp_load;
	if ( class_exists( 'Centinela_Syscom_API' ) ) {
		$client_id     = Centinela_Syscom_API::get_client_id();
		$client_secret = Centinela_Syscom_API::get_client_secret();
	} else {
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_ID' ) && is_string( CENTINELA_SYSCOM_CLIENT_ID ) ) {
			$client_id = trim( CENTINELA_SYSCOM_CLIENT_ID );
		} else {
			$client_id = trim( (string) get_option( 'centinela_syscom_client_id', '' ) );
		}
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_SECRET' ) && is_string( CENTINELA_SYSCOM_CLIENT_SECRET ) ) {
			$client_secret = trim( CENTINELA_SYSCOM_CLIENT_SECRET );
		} else {
			$client_secret = trim( (string) get_option( 'centinela_syscom_client_secret', '' ) );
		}
	}
}

if ( stripos( $client_id, 'tu_client' ) !== false || stripos( $client_secret, 'tu_client' ) !== false ) {
	fwrite( STDERR, "Parece que aún usas el ejemplo del tutorial: reemplaza por el Client ID y Secret reales de developers.syscomcolombia.com (o copia los de WP: Apariencia → API SYSCOM).\n\n" );
}

if ( $client_id === '' || $client_secret === '' ) {
	fwrite( STDERR, "Falta Client ID / Secret. Opciones:\n" );
	fwrite( STDERR, "  php scripts/syscom-api-probe.php --use-wp-config --categoria=3075   (lee wp-config sin MySQL; requiere define literales CENTINELA_SYSCOM_*)\n" );
	fwrite( STDERR, "  php scripts/syscom-api-probe.php --use-wp --categoria=3075   (requiere base de datos accesible)\n" );
	fwrite( STDERR, "  export SYSCOM_CLIENT_ID / SYSCOM_CLIENT_SECRET\n" );
	fwrite( STDERR, "  --client-id= / --client-secret=\n" );
	exit( 1 );
}

/**
 * @param string $method GET|POST
 * @param string $url
 * @param array  $headers
 * @param string $body   POST body (opcional)
 * @return array{ code: int, body: string, json: ?array }
 */
function syscom_probe_request( $method, $url, array $headers = array(), $body = '' ) {
	$ch = curl_init( $url );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
	if ( $body !== '' ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $body );
	}
	if ( ! empty( $headers ) ) {
		$h = array();
		foreach ( $headers as $k => $v ) {
			$h[] = $k . ': ' . $v;
		}
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $h );
	}
	$resp = curl_exec( $ch );
	$code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
	// curl_close() deprecado en PHP 8.5 (sin efecto desde 8.0); el handle se libera al salir del ámbito.
	$json = null;
	if ( is_string( $resp ) && $resp !== '' ) {
		$json = json_decode( $resp, true );
		if ( ! is_array( $json ) ) {
			$json = null;
		}
	}
	return array(
		'code' => $code,
		'body' => is_string( $resp ) ? $resp : '',
		'json' => $json,
	);
}

// Token (misma estrategia que el tema: Basic primero, luego body).
$token = '';
$attempts = array(
	array(
		'headers' => array(
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => 'Basic ' . base64_encode( $client_id . ':' . $client_secret ),
		),
		'body'    => 'grant_type=client_credentials',
	),
	array(
		'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
		'body'    => http_build_query(
			array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'grant_type'    => 'client_credentials',
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		),
	),
);

foreach ( $attempts as $a ) {
	$r = syscom_probe_request( 'POST', $token_url, $a['headers'], $a['body'] );
	if ( $r['code'] === 200 && is_array( $r['json'] ) && ! empty( $r['json']['access_token'] ) ) {
		$token = $r['json']['access_token'];
		break;
	}
}

if ( $token === '' ) {
	fwrite( STDERR, "No se pudo obtener token OAuth. HTTP y respuesta última:\n" );
	fwrite( STDERR, (string) ( $r['code'] ?? '?' ) . ' ' . substr( $r['body'] ?? '', 0, 500 ) . "\n" );
	if ( (int) ( $r['code'] ?? 0 ) === 401 ) {
		fwrite( STDERR, "\n401 invalid_client = Client ID o Secret incorrectos, revocados o de otra aplicación. Genera credenciales nuevas en el portal Syscom o copia las mismas que usas en WordPress (sin comillas ni espacios extra).\n" );
	}
	exit( 2 );
}

echo "Token OK.\n\n";

$auth_h = array(
	'Accept'        => 'application/json',
	'Authorization' => 'Bearer ' . $token,
);

/**
 * ID numérico final del slug …-NNNNNN.html (web Syscom CO).
 *
 * @param string $url URL o ruta.
 * @return string Dígitos o vacío.
 */
function syscom_probe_extract_slug_product_id( $url ) {
	if ( preg_match( '/-(\d{4,16})\.html\b/i', (string) $url, $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * Candidatos GET /productos/{id} (igual que centinela_search_syscom_api_id_candidates en el tema).
 *
 * @param string $raw_id  ID extraído del slug.
 * @param string $prefix  p. ej. 30002 para slugs de 6 dígitos.
 * @return string[]
 */
function syscom_probe_producto_id_candidates( $raw_id, $prefix ) {
	$d = preg_replace( '/\D/', '', (string) $raw_id );
	if ( $d === '' ) {
		return array();
	}
	$out = array( $d );
	if ( strlen( $d ) === 6 && $prefix !== '' && preg_match( '/^\d+$/', $prefix ) ) {
		$out[] = $prefix . $d;
	}
	return array_values( array_unique( $out ) );
}

/**
 * @param string $id ID producto API.
 * @return array{ code: int, body: string, json: ?array }
 */
function syscom_probe_get_producto_detail( $base, array $auth_h, $id ) {
	$url = rtrim( $base, '/' ) . '/productos/' . rawurlencode( (string) $id ) . '?cop=1';
	return syscom_probe_request( 'GET', $url, $auth_h );
}

/**
 * Aplana envoltorios típicos de la API hasta un array “producto” o null.
 *
 * @param mixed $data JSON decodificado.
 * @return ?array
 */
function syscom_probe_flatten_product_detail_payload( $data ) {
	if ( ! is_array( $data ) ) {
		return null;
	}
	$max = 8;
	for ( $i = 0; $i < $max; $i++ ) {
		if ( isset( $data['producto'] ) && is_array( $data['producto'] ) ) {
			$data = $data['producto'];
			continue;
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$keys = array_keys( $data );
			if ( count( $keys ) === 1 && $keys[0] === 'data' ) {
				$data = $data['data'];
				continue;
			}
			// { "data": { ... }, "meta": ... }
			if ( is_array( $data['data'] ) && ( isset( $data['data']['titulo'] ) || isset( $data['data']['modelo'] ) || isset( $data['data']['producto_id'] ) || isset( $data['data']['id'] ) ) ) {
				$data = $data['data'];
				continue;
			}
		}
		if ( array_keys( $data ) === array( 0 ) && isset( $data[0] ) && is_array( $data[0] ) ) {
			$data = $data[0];
			continue;
		}
		break;
	}
	return is_array( $data ) ? $data : null;
}

/**
 * Extrae identificador / modelo / título de una fila producto API.
 *
 * @param array $row Payload aplanado.
 * @return array{ pid: string, mod: string, tit: string }
 */
function syscom_probe_extract_product_row_fields( array $row ) {
	$pid = isset( $row['producto_id'] ) ? (string) $row['producto_id'] : ( isset( $row['id'] ) ? (string) $row['id'] : '' );
	$mod = isset( $row['modelo'] ) ? (string) $row['modelo'] : ( isset( $row['model'] ) ? (string) $row['model'] : ( isset( $row['sku'] ) ? (string) $row['sku'] : '' ) );
	$tit = isset( $row['titulo'] ) ? (string) $row['titulo'] : ( isset( $row['nombre'] ) ? (string) $row['nombre'] : ( isset( $row['name'] ) ? (string) $row['name'] : ( isset( $row['title'] ) ? (string) $row['title'] : '' ) ) );
	return array(
		'pid' => trim( $pid ),
		'mod' => trim( $mod ),
		'tit' => trim( $tit ),
	);
}

/**
 * @param array{ pid: string, mod: string, tit: string } $f
 */
function syscom_probe_product_row_has_identifiable_content( array $f ) {
	return $f['pid'] !== '' || $f['mod'] !== '' || $f['tit'] !== '';
}

/**
 * Respuesta 200 con cuerpo de error lógico (p. ej. {"error":"product_not_available"}).
 *
 * @param mixed $json JSON decodificado.
 * @return string Código de error o cadena vacía.
 */
function syscom_probe_json_logical_error( $json ) {
	if ( ! is_array( $json ) ) {
		return '';
	}
	if ( isset( $json['error'] ) ) {
		if ( is_string( $json['error'] ) && trim( $json['error'] ) !== '' ) {
			return trim( $json['error'] );
		}
		if ( is_array( $json['error'] ) && isset( $json['error']['code'] ) ) {
			return trim( (string) $json['error']['code'] );
		}
	}
	return '';
}

if ( $verify_public_urls ) {
	$default_verify_urls = array(
		'https://www.syscomcolombia.com/producto/TK-3000-KV2-KENWOOD-136674.html',
		'https://www.syscomcolombia.com/producto/PKT-300K-KENWOOD-232442.html',
		'https://www.syscomcolombia.com/producto/NX-1300-AK4-KENWOOD-191061.html',
		'https://www.syscomcolombia.com/producto/NX-1300-AK-KENWOOD-191059.html',
		'https://www.syscomcolombia.com/producto/NX1300AK4DMR-KENWOOD-208343.html',
		'https://www.syscomcolombia.com/producto/NX-1300-AK5-KENWOOD-191062.html',
		'https://www.syscomcolombia.com/producto/NX-1300-AK2-KENWOOD-191060.html',
	);
	$urls_to_check = array_values( array_unique( array_filter( array_merge( $default_verify_urls, $extra_verify_urls ) ) ) );
	$prefix        = $id_prefix_opt !== '' ? $id_prefix_opt : '30002';

	echo "=== Verificación GET /productos/{id} (developers.syscomcolombia.com) ===\n";
	echo "Slugs web: sufijo -NNNNNN.html. Candidatos: [slug] y [{$prefix}+slug] si el slug tiene 6 dígitos (como en el tema WordPress).\n\n";

	$any_fail = false;
	foreach ( $urls_to_check as $u ) {
		$slug_id = syscom_probe_extract_slug_product_id( $u );
		if ( $slug_id === '' ) {
			fwrite( STDERR, "No se pudo extraer ID del slug: {$u}\n" );
			$any_fail = true;
			continue;
		}
		$cands   = syscom_probe_producto_id_candidates( $slug_id, $prefix );
		$ok      = false;
		$last_r  = null;
		$last_ok_200_json = null;
		$last_logical_err = '';
		foreach ( $cands as $try_id ) {
			$last_r = syscom_probe_get_producto_detail( $base, $auth_h, $try_id );
			if ( (int) $last_r['code'] !== 200 || ! is_array( $last_r['json'] ) ) {
				continue;
			}
			$last_ok_200_json = $last_r['json'];
			$log_err          = syscom_probe_json_logical_error( $last_r['json'] );
			if ( $log_err !== '' ) {
				$last_logical_err = $log_err;
				continue;
			}
			$flat = syscom_probe_flatten_product_detail_payload( $last_r['json'] );
			if ( ! is_array( $flat ) ) {
				continue;
			}
			$fields = syscom_probe_extract_product_row_fields( $flat );
			if ( ! syscom_probe_product_row_has_identifiable_content( $fields ) ) {
				continue;
			}
			echo "OK   slug_web={$slug_id}  id_usado_en_peticion={$try_id}  producto_id_en_json={$fields['pid']}\n";
			echo "     modelo: {$fields['mod']}\n";
			$tit      = $fields['tit'];
			$tit_show = function_exists( 'mb_substr' ) ? mb_substr( $tit, 0, 120, 'UTF-8' ) : substr( $tit, 0, 120 );
			echo '     titulo: ' . $tit_show . ( strlen( $tit ) > 120 ? '…' : '' ) . "\n";
			echo "     web: {$u}\n\n";
			$ok = true;
			break;
		}
		if ( ! $ok ) {
			$any_fail   = true;
			$last_code  = $last_r ? (int) $last_r['code'] : 0;
			$last_snip  = $last_r && is_string( $last_r['body'] ) ? substr( $last_r['body'], 0, 220 ) : '';
			$hint_keys  = '';
			if ( is_array( $last_ok_200_json ) && $last_logical_err === '' ) {
				$flat_dbg = syscom_probe_flatten_product_detail_payload( $last_ok_200_json );
				if ( is_array( $flat_dbg ) ) {
					$hint_keys = implode( ', ', array_slice( array_keys( $flat_dbg ), 0, 25 ) );
				} else {
					$hint_keys = implode( ', ', array_slice( array_keys( $last_ok_200_json ), 0, 25 ) );
				}
			}
			echo "FAIL slug_web={$slug_id}  ids_probados: " . implode( ', ', $cands ) . "  ultimo_HTTP={$last_code}\n";
			echo "     web: {$u}\n";
			if ( $last_logical_err !== '' ) {
				echo "     API (HTTP 200, error lógico): {$last_logical_err}\n";
				echo "     → Suele indicar que el SKU existe en la web pública pero NO está habilitado para vuestra aplicación OAuth / catálogo API. Contactar a Syscom developers.\n";
			} elseif ( $last_code === 200 && $hint_keys !== '' ) {
				echo "     (HTTP 200 pero sin titulo/modelo/id reconocibles; claves JSON tras aplanar: {$hint_keys})\n";
			}
			echo "     cuerpo: {$last_snip}\n\n";
		}
	}

	if ( $any_fail ) {
		echo "Conclusión: revisa los mensajes FAIL arriba. Si ves \"product_not_available\" en todos, no es un fallo de búsqueda por texto en WordPress: la API confirma que esos productos no están disponibles para vuestras credenciales.\n";
		echo "Pedir a Syscom: habilitar catálogo/líneas (Kenwood portátiles, etc.) para el Client ID de developers.syscomcolombia.com o aclarar qué IDs/catálogo aplica.\n";
		exit( 3 );
	}
	$n_urls = count( $urls_to_check );
	echo "Conclusión: las {$n_urls} URL(s) tienen respuesta GET /productos/{id} con datos de producto (id/modelo/título) usando al menos un ID candidato.\n";
	echo "Si antes veías OK con campos vacíos, era un falso positivo (200 sin cuerpo útil); vuelve a ejecutar con este script actualizado.\n";
	exit( 0 );
}

/**
 * @param array  $query
 * @return array{ count: int, cantidad: ?int, paginas: ?int, sample_titles: string[] }
 */
function syscom_get_productos( $base, array $auth_h, array $query ) {
	$url = $base . 'productos?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	$r   = syscom_probe_request( 'GET', $url, $auth_h );
	if ( $r['code'] !== 200 || ! is_array( $r['json'] ) ) {
		return array(
			'error'    => 'HTTP ' . $r['code'] . ' ' . substr( $r['body'], 0, 200 ),
			'count'    => 0,
			'sample'   => array(),
			'raw_list' => array(),
		);
	}
	$data = $r['json'];
	$list = isset( $data['productos'] ) && is_array( $data['productos'] ) ? $data['productos'] : array();
	$titles = array();
	foreach ( array_slice( $list, 0, 3 ) as $p ) {
		if ( is_array( $p ) && isset( $p['titulo'] ) ) {
			$titles[] = (string) $p['titulo'];
		}
	}
	return array(
		'count'    => count( $list ),
		'cantidad' => isset( $data['cantidad'] ) ? (int) $data['cantidad'] : null,
		'paginas'  => isset( $data['paginas'] ) ? (int) $data['paginas'] : null,
		'sample'   => $titles,
		'raw_list' => $list,
		'error'    => null,
	);
}

$refs = array( 'TK-3000-KV2', 'PKT-300K', 'NX-1300-AK4', 'NX-1300-AK', 'NX1300AK4DMR', 'NX-1300-AK5', 'NX-1300-AK2' );

echo "=== Búsqueda por referencia (página 1, cop=1) ===\n";
foreach ( $refs as $q ) {
	$res = syscom_get_productos(
		$base,
		$auth_h,
		array(
			'busqueda' => $q,
			'pagina'   => 1,
			'cop'      => '1',
		)
	);
	if ( $res['error'] ) {
		echo "{$q} => ERROR {$res['error']}\n";
		continue;
	}
	$extra = '';
	if ( $res['cantidad'] !== null || $res['paginas'] !== null ) {
		$extra = ' (cantidad=' . ( $res['cantidad'] ?? '?' ) . ', paginas=' . ( $res['paginas'] ?? '?' ) . ')';
	}
	echo "{$q} => {$res['count']} en pág.1{$extra}\n";
	if ( ! empty( $res['sample'] ) ) {
		foreach ( $res['sample'] as $t ) {
			echo "    · " . $t . "\n";
		}
	}
}

$extra_global = array( 'NX+1300', 'NX 1300', 'nx1300', 'PKT+300', 'PKT 300K', 'KENWOOD+NX-1300' );
echo "\n=== Búsqueda global extra (pág.1) ===\n";
foreach ( $extra_global as $q ) {
	$res = syscom_get_productos( $base, $auth_h, array( 'busqueda' => $q, 'pagina' => 1, 'cop' => '1' ) );
	if ( $res['error'] ) {
		echo "{$q} => ERROR {$res['error']}\n";
		continue;
	}
	$cant = $res['cantidad'] !== null ? (string) $res['cantidad'] : '?';
	$pags = $res['paginas'] !== null ? (string) $res['paginas'] : '?';
	echo "{$q} => {$res['count']} en pág.1 (cantidad={$cant}, paginas={$pags})\n";
}

$categoria = isset( $opts['categoria'] ) ? trim( (string) $opts['categoria'] ) : '';
if ( $categoria !== '' ) {
	echo "\n=== Categoría {$categoria} ===\n";
	$r1 = syscom_get_productos( $base, $auth_h, array( 'categoria' => $categoria, 'pagina' => 1, 'cop' => '1' ) );
	if ( $r1['error'] ) {
		echo 'solo categoria p1: ' . $r1['error'] . "\n";
	} else {
		$c1 = $r1['cantidad'] !== null ? (string) $r1['cantidad'] : '?';
		$p1 = $r1['paginas'] !== null ? (string) $r1['paginas'] : '?';
		echo "solo categoria p1: {$r1['count']} productos en esta página (API cantidad={$c1}, paginas={$p1})\n";
	}
	$r2 = syscom_get_productos( $base, $auth_h, array( 'categoria' => $categoria, 'busqueda' => 'KENWOOD', 'pagina' => 1, 'cop' => '1' ) );
	echo 'categoria+KENWOOD p1: ' . ( $r2['error'] ? $r2['error'] : $r2['count'] . ' productos' ) . "\n";

	echo "\n--- Misma categoría + busqueda (refs que dieron 0 en global) ---\n";
	foreach ( $refs as $q ) {
		$rg = syscom_get_productos( $base, $auth_h, array( 'categoria' => $categoria, 'busqueda' => $q, 'pagina' => 1, 'cop' => '1' ) );
		if ( $rg['error'] ) {
			echo "cat+\"{$q}\" => ERROR {$rg['error']}\n";
			continue;
		}
		if ( $rg['count'] < 1 ) {
			continue;
		}
		echo "cat+\"{$q}\" => {$rg['count']} en pág.1\n";
		foreach ( array_slice( $rg['raw_list'], 0, 5 ) as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			$t = isset( $p['titulo'] ) ? (string) $p['titulo'] : '';
			$m = isset( $p['modelo'] ) ? (string) $p['modelo'] : '';
			echo '    · ' . ( $m !== '' ? "[{$m}] " : '' ) . $t . "\n";
		}
	}

	$max_pages = 15;
	if ( ! $r1['error'] && $r1['paginas'] !== null && $r1['paginas'] > 0 ) {
		$max_pages = min( $max_pages, (int) $r1['paginas'] );
	}
	echo "\n--- Listado categoría (todas las páginas hasta {$max_pages}) ---\n";
	$idx = 0;
	for ( $pg = 1; $pg <= $max_pages; $pg++ ) {
		$rp = syscom_get_productos( $base, $auth_h, array( 'categoria' => $categoria, 'pagina' => $pg, 'cop' => '1' ) );
		if ( $rp['error'] || empty( $rp['raw_list'] ) ) {
			break;
		}
		foreach ( $rp['raw_list'] as $p ) {
			if ( ! is_array( $p ) ) {
				continue;
			}
			++$idx;
			$t = isset( $p['titulo'] ) ? (string) $p['titulo'] : '';
			$m = isset( $p['modelo'] ) ? (string) $p['modelo'] : '';
			echo sprintf( "%3d. [%s] %s\n", $idx, $m !== '' ? $m : '-', $t );
		}
		if ( $rp['paginas'] !== null && $pg >= (int) $rp['paginas'] ) {
			break;
		}
	}
	if ( $idx === 0 ) {
		echo "(vacío)\n";
	}
} else {
	echo "\n(Sin --categoria=ID: omite prueba categoría+KENWOOD. Pásala si ya tienes el ID Syscom de Portátiles UHF KENWOOD.)\n";
}

echo "\nListo.\n";
