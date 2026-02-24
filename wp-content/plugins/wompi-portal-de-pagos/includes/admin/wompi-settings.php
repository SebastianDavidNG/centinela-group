<?php
defined('ABSPATH') || exit;

return apply_filters(
	'wc_wompi_settings',
	array(
		'enabled' => array(
			'title'       => __('Enable/Disable', 'wompi-portal-de-pagos'),
			'label'       => __('Enable Wompi', 'wompi-portal-de-pagos'),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __('Title', 'wompi-portal-de-pagos'),
			'type'        => 'text',
			'description' => __('This controls the title which the user sees during checkout.', 'wompi-portal-de-pagos'),
			'default'     => 'Wompi',
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __('Description', 'wompi-portal-de-pagos'),
			'type'        => 'text',
			'description' => __('This controls the description which the user sees during checkout.', 'wompi-portal-de-pagos'),
			'default'     => __('Pay via Wompi gateway.', 'wompi-portal-de-pagos'),
			'desc_tip'    => true,
		),
		'webhook' => array(
			'title'       => __('Webhook Endpoints', 'wompi-portal-de-pagos'),
			'type'        => 'title',
			 // translators: %s: webhook endpoint
			'description' => sprintf(__('You must add the following webhook endpoint <strong class="wc_wompi-webhook-link">&nbsp;%s&nbsp;</strong> to your <a href="https://comercios.wompi.co/my-account" target="_blank">Wompi account settings</a> for both Production and Sandbox environments.', 'wompi-portal-de-pagos'), add_query_arg('wc-api', 'wc_wompi', trailingslashit(get_home_url()))),
		),
		'testmode' => array(
			'title'       => __('Test mode', 'wompi-portal-de-pagos'),
			'label'       => __('Enable Test Mode', 'wompi-portal-de-pagos'),
			'type'        => 'checkbox',
			'description' => __('Place the payment gateway in test mode using test API keys.', 'wompi-portal-de-pagos'),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_public_key' => array(
			'title'       => __('Test Public Key', 'wompi-portal-de-pagos'),
			'type'        => 'text',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_private_key' => array(
			'title'       => __('Test Private Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_event_secret_key' => array(
			'title'       => __('Test Event Private Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_integrity_key' => array(
			'title'       => __('Test Integrity Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'public_key' => array(
			'title'       => __('Live Public Key', 'wompi-portal-de-pagos'),
			'type'        => 'text',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'private_key' => array(
			'title'       => __('Live Private Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'event_secret_key' => array(
			'title'       => __('Live Event Private Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'integrity_key' => array(
			'title'       => __('Live Integrity Key', 'wompi-portal-de-pagos'),
			'type'        => 'password',
			'description' => __('Get your API keys from your Wompi account.', 'wompi-portal-de-pagos'),
			'default'     => '',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __('Logging', 'wompi-portal-de-pagos'),
			'label'       => __('Log debug messages', 'wompi-portal-de-pagos'),
			'type'        => 'checkbox',
			'description' => __('Save debug messages to the WooCommerce System Status log.', 'wompi-portal-de-pagos'),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
