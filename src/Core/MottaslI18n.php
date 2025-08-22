<?php

namespace Mottasl\Core;

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://twerlo.com/
 * @since      0.1.0
 *
 * @package    Mottasl_Woocommerce
 * @subpackage Mottasl_Woocommerce/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.1.0
 * @package    Mottasl_Woocommerce
 * @subpackage Mottasl_Woocommerce/includes
 * @author     Twerlo <support@twerlo.com>
 */
class MottaslI18n
{


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.1.0
	 */
	public function load_plugin_textdomain()
	{

		load_plugin_textdomain(
			'mottasl-woocommerce',
			false,
			dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
		);
	}
}
