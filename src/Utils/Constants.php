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
	 * Api Base URL.
	 */
	const API_BASE_URL = 'https://hub.api.mottasl.ai';

	const Mottasl_APP_BASE_URL = 'https://app.mottasl.ai';

	/**
	 * API Path
	 */
	const API_PATH = 'api/v1/integration/events/woocommerce';
}
