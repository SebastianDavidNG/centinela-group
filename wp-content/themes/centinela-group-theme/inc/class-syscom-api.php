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
	const TRANSIENT_TOKEN     = 'centinela_syscom_token';
	const TRANSIENT_CATEGORIAS = 'centinela_syscom_categorias';
	const CACHE_CATEGORIAS_HOURS = 6;

	/**
	 * Obtener Client ID (opción o constante).
	 *
	 * @return string
	 */
	public static function get_client_id() {
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_ID' ) ) {
			return CENTINELA_SYSCOM_CLIENT_ID;
		}
		return (string) get_option( 'centinela_syscom_client_id', '' );
	}

	/**
	 * Obtener Client Secret (opción o constante).
	 *
	 * @return string
	 */
	public static function get_client_secret() {
		if ( defined( 'CENTINELA_SYSCOM_CLIENT_SECRET' ) ) {
			return CENTINELA_SYSCOM_CLIENT_SECRET;
		}
		return (string) get_option( 'centinela_syscom_client_secret', '' );
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

		$response = wp_remote_post(
			self::TOKEN_URL,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'client_credentials',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( $code !== 200 || empty( $data['access_token'] ) ) {
			$msg = isset( $data['message'] ) ? $data['message'] : $body;
			return new WP_Error( 'centinela_syscom', sprintf( __( 'Error al obtener token Syscom: %s', 'centinela-group-theme' ), $msg ) );
		}

		$token   = $data['access_token'];
		$expires = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 86400; // 1 día por defecto
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

		// Normalizar: solo nivel 1 para el submenú (o todos si la API no filtra)
		$categorias = array();
		foreach ( $data as $item ) {
			$id     = isset( $item['id'] ) ? (string) $item['id'] : '';
			$nombre = isset( $item['nombre'] ) ? (string) $item['nombre'] : '';
			$nivel  = isset( $item['nivel'] ) ? (int) $item['nivel'] : 1;
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
	 * Invalidar caché (útil al guardar credenciales).
	 */
	public static function flush_cache() {
		delete_transient( self::TRANSIENT_TOKEN );
		delete_transient( self::TRANSIENT_CATEGORIAS );
	}
}
