<?php

namespace ImageOptimization\Modules\Connect;

use ElementorOne\Connect\Facade;
use ImageOptimization\Classes\Module_Base;
use ImageOptimization\Modules\Connect\Classes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Class Module
 */
class Module extends Module_Base {

	/**
	 * Get module name.
	 * Retrieve the module name.
	 * @access public
	 * @return string Module name.
	 */
	public function get_name() {
		return 'connect';
	}

	public static function is_connected() : bool {
		$facade = self::get_connect();
		$access_token = $facade->data()->get_access_token();
		
		return ! ! $access_token && $facade->utils()->is_valid_home_url();
	}

	public static function get_connect(): ?Facade {
		return Facade::get( Config::PLUGIN_SLUG);
	}

	public static function is_active() : bool {
		return empty( get_option( 'image_optimizer_client_data' ) );
	}

	public function __construct() {
		
		Facade::make( [
			'app_name' => Config::APP_NAME,
			'app_prefix' => Config::APP_PREFIX,
			'app_rest_namespace' => Config::APP_REST_NAMESPACE,
			'base_url' => Config::BASE_URL,
			'admin_page' => Config::ADMIN_PAGE,
			'app_type' => Config::APP_TYPE,
			'scopes' => Config::SCOPES,
			'state_nonce' => Config::STATE_NONCE,
			'connect_mode' => Config::CONNECT_MODE,
			'plugin_slug' => Config::PLUGIN_SLUG,
		] );
	}
}
