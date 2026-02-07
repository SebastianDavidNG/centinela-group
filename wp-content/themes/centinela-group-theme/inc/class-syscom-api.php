<?php
/**
 * Cliente API Syscom Colombia
 * OAuth 2.0 + endpoints (categorías). Caché con transients.
 *
 * @package Centinela_Group_Theme
 * @see https://developers.syscomcolombia.com/docs
 * @see https://developers.syscomcolombia.com/guide
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Centinela_Syscom_API {

	const API_BASE   = 'https://developers.syscomcolombia.com/api/v1/';
	const TOKEN_URL  = 'https://developers.syscomcolombia.com/oauth/token';
	const TRANSIENT_TOKEN           = 'centinela_syscom_token';
	const TRANSIENT_CATEGORIAS      = 'centinela_syscom_categorias';
	const TRANSIENT_CATEGORIAS_ARBOL = 'centinela_syscom_categorias_arbol';
	const CACHE_CATEGORIAS_HOURS    = 6;

	// Orden de categorías nivel 1 como en https://www.syscomcolombia.com/ (por coincidencia de nombre)
	const ORDER_NIVEL1_SYSCOM = array(
		'Videovigilancia',
		'Control de Acceso',
		'Energía',
		'Detección de Fuego',
		'Alarmas',           // "Automatización e Intrusión" en API → mostrar como Alarmas / Intrusión y Casa Inteligente
		'Radiocomunicación',
		'Redes',             // "Redes" en API → mostrar como Redes y Audio-Video
		'Cableado Estructurado',
		'IoT',               // IoT / GPS / Telemática...
	);

	// Mapeo nombre API → nombre para mostrar (como en Syscom Colombia)
	const NOMBRE_DISPLAY_SYSCOM = array(
		'Automatización e Intrusión' => 'Alarmas / Intrusión y Casa Inteligente',
		'Redes'                      => 'Redes y Audio-Video',
		'IoT / GPS / Telemática y Señalización Audiovisual' => 'IoT / GPS / Telemática y Señalización Audiovisual',
	);

	/**
	 * Obtener Client ID (opción o constante).
	 *
	 * @return string
	 */
	public static function get_client_id() {
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_ID' ) ) {
			return is_string( CENTINELA_SYSCOM_CLIENT_ID ) ? trim( CENTINELA_SYSCOM_CLIENT_ID ) : '';
		}
		return trim( (string) get_option( 'centinela_syscom_client_id', '' ) );
	}

	/**
	 * Obtener Client Secret (opción o constante).
	 *
	 * @return string
	 */
	public static function get_client_secret() {
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_SECRET' ) ) {
			return is_string( CENTINELA_SYSCOM_CLIENT_SECRET ) ? trim( CENTINELA_SYSCOM_CLIENT_SECRET ) : '';
		}
		return trim( (string) get_option( 'centinela_syscom_client_secret', '' ) );
	}

	/**
	 * ¿Tenemos credenciales configuradas?
	 *
	 * @return bool
	 */
	public static function has_credentials() {
		$id = self::get_client_id();
		$secret = self::get_client_secret();
		return $id !== '' && $secret !== '';
	}

	/**
	 * Obtener token de acceso (desde caché o API).
	 *
	 * @return string|WP_Error Token o error.
	 */
	public static function get_token() {
		$cached = get_transient( self::TRANSIENT_TOKEN );
		if ( is_string( $cached ) && $cached !== '' ) {
			return $cached;
		}

		$client_id     = self::get_client_id();
		$client_secret = self::get_client_secret();
		if ( $client_id === '' || $client_secret === '' ) {
			return new WP_Error( 'centinela_syscom', __( 'Credenciales API Syscom no configuradas.', 'centinela-group-theme' ) );
		}

		// Intentar primero con Basic Auth (común en OAuth2); si falla, con credenciales en el body
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

		$response = null;
		$last_code = 0;
		$last_body = '';
		$last_data = array();

		foreach ( $attempts as $args ) {
			$args['timeout'] = 15;
			$response       = wp_remote_post( self::TOKEN_URL, $args );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$last_code = wp_remote_retrieve_response_code( $response );
			$last_body = wp_remote_retrieve_body( $response );
			$last_data = json_decode( $last_body, true );
			if ( ! is_array( $last_data ) ) {
				$last_data = array();
			}
			if ( $last_code === 200 && ! empty( $last_data['access_token'] ) ) {
				break;
			}
		}

		if ( $last_code !== 200 || ! is_array( $last_data ) || empty( $last_data['access_token'] ) ) {
			$msg = is_array( $last_data ) && isset( $last_data['message'] ) ? $last_data['message'] : $last_body;
			$err = sprintf( __( 'Error al obtener token Syscom: %s', 'centinela-group-theme' ), $msg );
			if ( stripos( $msg, 'client authentication failed' ) !== false || stripos( $msg, 'invalid_client' ) !== false ) {
				$err .= ' ' . __( 'Las credenciales pueden estar vencidas o haber sido revocadas: el cliente debe ingresar a developers.syscomcolombia.com, abrir su aplicación y generar nuevas credenciales (o verificar que el Client ID y el Secret sean los vigentes).', 'centinela-group-theme' );
			}
			return new WP_Error( 'centinela_syscom', $err );
		}

		$token   = $last_data['access_token'];
		$expires = isset( $last_data['expires_in'] ) ? (int) $last_data['expires_in'] : 86400; // 1 día por defecto
		set_transient( self::TRANSIENT_TOKEN, $token, $expires - 300 ); // 5 min antes de expirar

		return $token;
	}

	/**
	 * Obtener categorías (nivel 1 para el submenú). Caché de varias horas.
	 *
	 * @return array|WP_Error Lista de { id, nombre, nivel } o error.
	 */
	public static function get_categorias() {
		$cached = get_transient( self::TRANSIENT_CATEGORIAS );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$token = self::get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_get(
			self::API_BASE . 'categorias',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'       => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'centinela_syscom', sprintf( __( 'Error al obtener categorías Syscom: %s', 'centinela-group-theme' ), $msg ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'centinela_syscom', __( 'Respuesta inválida de categorías.', 'centinela-group-theme' ) );
		}

		// Algunas APIs devuelven { "data": [ ... ] } o { "categorias": [ ... ] }
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data = $data['data'];
		} elseif ( isset( $data['categorias'] ) && is_array( $data['categorias'] ) ) {
			$data = $data['categorias'];
		}

		// Normalizar: lista de items con id, nombre (o name), nivel
		$categorias = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id     = isset( $item['id'] ) ? (string) $item['id'] : '';
			$nombre = isset( $item['nombre'] ) ? (string) $item['nombre'] : ( isset( $item['name'] ) ? (string) $item['name'] : '' );
			$nivel  = isset( $item['nivel'] ) ? (int) $item['nivel'] : ( isset( $item['level'] ) ? (int) $item['level'] : 1 );
			if ( $id !== '' && $nombre !== '' ) {
				$categorias[] = array(
					'id'     => $id,
					'nombre' => $nombre,
					'nivel'  => $nivel,
				);
			}
		}

		set_transient( self::TRANSIENT_CATEGORIAS, $categorias, self::CACHE_CATEGORIAS_HOURS * HOUR_IN_SECONDS );
		return $categorias;
	}

	/**
	 * Obtener categorías en árbol (nivel 1 + subcategorías) para el submenú.
	 * Intenta construir árbol desde la respuesta plana (id_padre/parent_id) o pide hijos por categoría.
	 *
	 * @return array|WP_Error Lista de { id, nombre, nivel, hijos: [] } o error.
	 */
	public static function get_categorias_arbol() {
		$cached = get_transient( self::TRANSIENT_CATEGORIAS_ARBOL );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$token = self::get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Accept'        => 'application/json',
		);

		// 1) Obtener todas las categorías (puede devolver todos los niveles en una sola llamada)
		$response = wp_remote_get(
			self::API_BASE . 'categorias',
			array( 'timeout' => 15, 'headers' => $headers )
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'centinela_syscom', sprintf( __( 'Error al obtener categorías Syscom: %s', 'centinela-group-theme' ), $msg ) );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'centinela_syscom', __( 'Respuesta inválida de categorías.', 'centinela-group-theme' ) );
		}

		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data = $data['data'];
		} elseif ( isset( $data['categorias'] ) && is_array( $data['categorias'] ) ) {
			$data = $data['categorias'];
		}

		$todos = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$id       = isset( $item['id'] ) ? (string) $item['id'] : '';
			$nombre   = isset( $item['nombre'] ) ? (string) $item['nombre'] : ( isset( $item['name'] ) ? (string) $item['name'] : '' );
			$nivel    = isset( $item['nivel'] ) ? (int) $item['nivel'] : ( isset( $item['level'] ) ? (int) $item['level'] : 1 );
			$id_padre = '';
			if ( isset( $item['id_padre'] ) ) {
				$id_padre = (string) $item['id_padre'];
			} elseif ( isset( $item['parent_id'] ) ) {
				$id_padre = (string) $item['parent_id'];
			} elseif ( isset( $item['padre_id'] ) ) {
				$id_padre = (string) $item['padre_id'];
			} elseif ( isset( $item['id_parent'] ) ) {
				$id_padre = (string) $item['id_parent'];
			} elseif ( isset( $item['parent'] ) ) {
				$id_padre = (string) $item['parent'];
			}
			if ( $id !== '' && $nombre !== '' ) {
				$todos[] = array(
					'id'       => $id,
					'nombre'   => trim( $nombre ),
					'nivel'    => $nivel,
					'id_padre' => $id_padre,
				);
			}
		}

		// 2) Construir árbol: si hay id_padre usarlo; si no, raíces = nivel 1 y buscar hijos por endpoint
		$por_id = array();
		foreach ( $todos as $c ) {
			$por_id[ $c['id'] ] = array(
				'id'     => $c['id'],
				'nombre' => $c['nombre'],
				'nivel'  => $c['nivel'],
				'hijos'  => array(),
			);
		}

		$tiene_padre = false;
		foreach ( $todos as $c ) {
			if ( $c['id_padre'] !== '' && $c['id_padre'] !== '0' ) {
				$tiene_padre = true;
				if ( isset( $por_id[ $c['id_padre'] ] ) ) {
					$por_id[ $c['id_padre'] ]['hijos'][] = $por_id[ $c['id'] ];
				}
			}
		}

		if ( $tiene_padre ) {
			$arbol = array();
			foreach ( $todos as $c ) {
				if ( $c['id_padre'] === '' || $c['id_padre'] === '0' ) {
					$arbol[] = $por_id[ $c['id'] ];
				}
			}
			// Si la API solo devolvió 2 niveles, intentar obtener nivel 3 (nietos) por endpoint
			foreach ( $arbol as $i => $raiz ) {
				foreach ( $raiz['hijos'] as $j => $hijo ) {
					$nietos = self::fetch_subcategorias( $hijo['id'], $token, $headers );
					if ( ! empty( $nietos ) ) {
						$arbol[ $i ]['hijos'][ $j ]['hijos'] = $nietos;
					}
				}
			}
			$arbol = self::ordenar_y_mapear_arbol( $arbol );
		} else {
			// Sin id_padre: solo nivel 1 e intentar subcategorías (y nivel 3) por endpoint
			$raices_n1 = array_filter( $todos, function ( $c ) {
				return (int) $c['nivel'] === 1;
			} );
			$arbol = array();
			foreach ( $raices_n1 as $c ) {
				$nodo = array(
					'id'     => $c['id'],
					'nombre' => $c['nombre'],
					'nivel'  => $c['nivel'],
					'hijos'  => array(),
				);
				$hijos = self::fetch_subcategorias( $c['id'], $token, $headers );
				if ( ! empty( $hijos ) ) {
					// Nivel 3: para cada hijo (nivel 2) obtener sus subcategorías (como en Syscom)
					foreach ( $hijos as $idx => $hijo ) {
						$nietos = self::fetch_subcategorias( $hijo['id'], $token, $headers );
						if ( ! empty( $nietos ) ) {
							$hijos[ $idx ]['hijos'] = $nietos;
						}
					}
					$nodo['hijos'] = $hijos;
				}
				$arbol[] = $nodo;
			}
		}

		// Ordenar nivel 1 como en Syscom y aplicar nombres de visualización
		$arbol = self::ordenar_y_mapear_arbol( $arbol );

		set_transient( self::TRANSIENT_CATEGORIAS_ARBOL, $arbol, self::CACHE_CATEGORIAS_HOURS * HOUR_IN_SECONDS );
		return $arbol;
	}

	/**
	 * Ordenar categorías nivel 1 como Syscom y aplicar mapeo de nombres para mostrar.
	 *
	 * @param array $arbol Lista de nodos raíz.
	 * @return array Mismo árbol ordenado y con nombres aplicados.
	 */
	/**
	 * Contar total de elementos en un nodo (grupos nivel 2 + ítems nivel 3) para ordenar.
	 *
	 * @param array $nodo Nodo con 'hijos' opcional.
	 * @return int
	 */
	private static function count_subitems( $nodo ) {
		$hijos = isset( $nodo['hijos'] ) ? $nodo['hijos'] : array();
		$n     = count( $hijos );
		$sub   = 0;
		foreach ( $hijos as $h ) {
			$sub += isset( $h['hijos'] ) ? count( $h['hijos'] ) : 0;
		}
		return $n + $sub;
	}

	private static function ordenar_y_mapear_arbol( $arbol ) {
		$orden = self::ORDER_NIVEL1_SYSCOM;
		$map   = self::NOMBRE_DISPLAY_SYSCOM;

		foreach ( $arbol as $i => $nodo ) {
			$nombre = $nodo['nombre'];
			if ( isset( $map[ $nombre ] ) ) {
				$arbol[ $i ]['nombre'] = $map[ $nombre ];
			}
		}

		// Orden fijo nivel 1 (Videovigilancia, Control de Acceso, etc.)
		usort( $arbol, function ( $a, $b ) use ( $orden ) {
			$nombre_a = $a['nombre'];
			$nombre_b = $b['nombre'];
			$pos_a    = 999;
			$pos_b    = 999;
			foreach ( $orden as $idx => $clave ) {
				if ( stripos( $nombre_a, $clave ) !== false ) {
					$pos_a = $idx;
					break;
				}
			}
			foreach ( $orden as $idx => $clave ) {
				if ( stripos( $nombre_b, $clave ) !== false ) {
					$pos_b = $idx;
					break;
				}
			}
			if ( $pos_a !== $pos_b ) {
				return $pos_a - $pos_b;
			}
			// Mismo grupo: menos elementos primero (más ordenado visualmente)
			return self::count_subitems( $a ) - self::count_subitems( $b );
		} );

		// Dentro de cada categoría: grupos (nivel 2) con menos elementos primero
		foreach ( $arbol as $i => $nodo ) {
			if ( empty( $nodo['hijos'] ) ) {
				continue;
			}
			$hijos = $nodo['hijos'];
			usort( $hijos, function ( $a, $b ) {
				$ca = isset( $a['hijos'] ) ? count( $a['hijos'] ) : 0;
				$cb = isset( $b['hijos'] ) ? count( $b['hijos'] ) : 0;
				return $ca - $cb;
			} );
			$arbol[ $i ]['hijos'] = $hijos;
		}

		return $arbol;
	}

	/**
	 * Intentar obtener subcategorías de una categoría (endpoints posibles según documentación).
	 *
	 * @param string $id_categoria ID de la categoría padre.
	 * @param string $token        Token OAuth.
	 * @param array  $headers      Headers para la petición.
	 * @return array Lista de { id, nombre, nivel, hijos: [] }
	 */
	private static function fetch_subcategorias( $id_categoria, $token, $headers ) {
		$cache_key = 'centinela_syscom_hijos_' . $id_categoria;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$intentos = array(
			self::API_BASE . 'categorias/' . $id_categoria,
			self::API_BASE . 'categorias?padre_id=' . $id_categoria,
			self::API_BASE . 'categorias?parent_id=' . $id_categoria,
			self::API_BASE . 'categorias?id_padre=' . $id_categoria,
		);

		foreach ( $intentos as $url ) {
			$response = wp_remote_get( $url, array( 'timeout' => 10, 'headers' => $headers ) );
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );
			if ( $code !== 200 || ! is_array( $data ) ) {
				continue;
			}

			$lista = array();
			if ( isset( $data['hijos'] ) && is_array( $data['hijos'] ) ) {
				$lista = $data['hijos'];
			} elseif ( isset( $data['subcategorias'] ) && is_array( $data['subcategorias'] ) ) {
				$lista = $data['subcategorias'];
			} elseif ( isset( $data['children'] ) && is_array( $data['children'] ) ) {
				$lista = $data['children'];
			} elseif ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$lista = $data['data'];
			} elseif ( array_values( $data ) === $data && ! empty( $data ) && is_array( $data[0] ) ) {
				$lista = $data;
			}

			$hijos = array();
			foreach ( $lista as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$id     = isset( $item['id'] ) ? (string) $item['id'] : '';
				$nombre = isset( $item['nombre'] ) ? (string) $item['nombre'] : ( isset( $item['name'] ) ? (string) $item['name'] : '' );
				$nivel  = isset( $item['nivel'] ) ? (int) $item['nivel'] : 2;
				if ( $id !== '' && $nombre !== '' ) {
					$hijos[] = array(
						'id'     => $id,
						'nombre' => trim( $nombre ),
						'nivel'  => $nivel,
						'hijos'  => array(),
					);
				}
			}
			if ( ! empty( $hijos ) ) {
				set_transient( $cache_key, $hijos, self::CACHE_CATEGORIAS_HOURS * HOUR_IN_SECONDS );
				return $hijos;
			}
		}

		set_transient( $cache_key, array(), 1 * HOUR_IN_SECONDS );
		return array();
	}

	/**
	 * Obtener detalle de un producto por ID.
	 *
	 * @param string|int $producto_id ID del producto.
	 * @param bool       $cop         true para precios en COP.
	 * @return array|WP_Error Datos del producto o error.
	 */
	public static function get_producto( $producto_id, $cop = true ) {
		$token = self::get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$url = self::API_BASE . 'productos/' . rawurlencode( (string) $producto_id );
		if ( $cop ) {
			$url .= '?cop=1';
		}
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $code !== 200 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'centinela_syscom', sprintf( __( 'Error al obtener producto Syscom: %s', 'centinela-group-theme' ), $msg ) );
		}
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'centinela_syscom', __( 'Respuesta inválida de producto.', 'centinela-group-theme' ) );
		}
		// Algunas APIs envuelven el producto en "data"
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$data = $data['data'];
		}
		return $data;
	}

	/**
	 * Buscar productos (por categoría, página, orden).
	 *
	 * @param array $args 'categoria' (id o ids comma-sep), 'pagina' (1-based), 'orden', 'busqueda', 'cop'.
	 * @return array|WP_Error { cantidad, pagina, paginas, productos: [] } o error.
	 */
	public static function get_productos( $args = array() ) {
		$token = self::get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$defaults = array(
			'categoria' => '',
			'pagina'   => 1,
			'orden'    => 'relevancia',
			'busqueda' => '',
			'cop'      => true,
		);
		$args  = wp_parse_args( $args, $defaults );
		$query = array();
		if ( $args['categoria'] !== '' ) {
			$query['categoria'] = $args['categoria'];
		}
		if ( $args['busqueda'] !== '' ) {
			$query['busqueda'] = $args['busqueda'];
		}
		$query['pagina'] = max( 1, (int) $args['pagina'] );
		$query['orden']  = sanitize_text_field( $args['orden'] );
		if ( $args['cop'] ) {
			$query['cop'] = '1';
		}
		$url = add_query_arg( $query, self::API_BASE . 'productos' );
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $code !== 200 ) {
			$msg = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'centinela_syscom', sprintf( __( 'Error al buscar productos Syscom: %s', 'centinela-group-theme' ), $msg ) );
		}
		return is_array( $data ) ? $data : array( 'cantidad' => 0, 'pagina' => 1, 'paginas' => 0, 'productos' => array() );
	}

	/**
	 * Obtener productos relacionados a un producto.
	 *
	 * @param string|int $producto_id ID del producto.
	 * @return array|WP_Error Lista de productos o error.
	 */
	public static function get_productos_relacionados( $producto_id ) {
		$token = self::get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$url = self::API_BASE . 'productos/' . rawurlencode( (string) $producto_id ) . '/relacionados';
		$response = wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
			),
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( $code !== 200 ) {
			return is_array( $data ) ? new WP_Error( 'centinela_syscom', isset( $data['message'] ) ? $data['message'] : $body ) : array();
		}
		if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
			return $data['data'];
		}
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Invalidar caché (útil al guardar credenciales).
	 */
	public static function flush_cache() {
		delete_transient( self::TRANSIENT_TOKEN );
		delete_transient( self::TRANSIENT_CATEGORIAS );
		delete_transient( self::TRANSIENT_CATEGORIAS_ARBOL );
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_centinela_syscom_hijos_%' OR option_name LIKE '_transient_timeout_centinela_syscom_hijos_%'" );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_centinela_ps_%' OR option_name LIKE '_transient_timeout_centinela_ps_%'" );
	}
}
