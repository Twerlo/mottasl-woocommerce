<?php

namespace Mottasl\Utils;

defined('ABSPATH') || exit;
/**
 * Constants for the Mottasl plugin.
 *
 * @package Mottasl\Utils
 * @since 1.0.0
 */
class Constants
{
	/**
	 * The plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.2.0';

	/**
	 * The plugin name.
	 *
	 * @var string
	 */
	const PLUGIN_NAME = 'Mottasl WooCommerce';

	/**
	 * The plugin slug.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'mottasl-woocommerce';
	/**
	 * The plugin text domain.
	 *
	 * @var string
	 */
	const TEXT_DOMAIN = 'mottasl-woocommerce';
	/**
	 * The plugin URL.
	 *
	 * @var string
	 */
	const PLUGIN_URL = 'https://mottasl.com/woocommerce';
	/**
	 * The plugin support URL.
	 *
	 * @var string
	 */
	const SUPPORT_URL = 'https://mottasl.com/support';

	/**
	 * Environment-specific API base URL.
	 * Can be easily changed for different environments (dev, staging, prod)
	 *
	 * @var string
	 */
	//const MOTTASL_API_BASE_URL = 'https://hub.api.mottasl.ai';
	const MOTTASL_API_BASE_URL = 'https://test.hub.avocad0.dev';
	// const MOTTASL_API_BASE_URL = 'https://042e5cc36435.ngrok-free.app';

	/**
	 * Mottasl application base URL for redirects
	 *
	 * @var string
	 */
	// const MOTTASL_APP_BASE_URL = 'https://app.mottasl.ai'; //'http://localhost:3001'; //
	const MOTTASL_APP_BASE_URL = 'https://test.app.avocad0.dev'; //'http://localhost:3001'; //

	/**
	 * Complete API endpoint URL for WooCommerce integration
	 * Combines base URL with the full WooCommerce integration path
	 *
	 * @var string
	 */
	const WOOCOMMERCE_API_BASE_URL = self::MOTTASL_API_BASE_URL . '/api/v1/integration/events/woocommerce';

	/**
	 * @deprecated Use MOTTASL_API_BASE_URL instead
	 * Kept for backward compatibility
	 */
	const API_BASE_URL = self::MOTTASL_API_BASE_URL;

	/**
	 * @deprecated Use WOOCOMMERCE_API_BASE_URL instead
	 * Kept for backward compatibility
	 */
	const API_PATH = 'api/v1/integration/events/woocommerce';

	/**
	 * JWT token secret key.
	 *
	 * @var string
	 */
	const JWT_SECRET_KEY = 'woocommerce-install';

	/**
	 * Cart abandonment duration in minutes.
	 * Time to wait before considering a cart as abandoned and sending notifications.
	 *
	 * @var int
	 */
	const CART_ABANDONED_DURATION = 15;
}
