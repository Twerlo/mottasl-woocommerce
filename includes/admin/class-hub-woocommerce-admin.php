<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://hub.com/
 * @since      0.1.0
 *
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 * Also register woocommerce hub integration
 *
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/admin
 * @author     Twerlo <support@twerlo.com>
 */


/**
 * Register the JS and CSS.
 */
function add_extension_register_script()
{
	if (
		!method_exists('Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page') ||
		!\Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()
	) {
		return;
	}


	$script_path       = '../build/index.js';
	$script_asset_path = dirname(dirname(__FILE__)) . '/build/index.asset.php';
	$script_asset      = file_exists($script_asset_path)
		? require($script_asset_path)
		: array('dependencies' => array(), 'version' => filemtime($script_path));
	$script_url = plugins_url($script_path, __FILE__);

	wp_register_script(
		'hub',
		$script_url,
		$script_asset['dependencies'],
		$script_asset['version'],
		true
	);

	wp_register_style(
		'hub',
		plugins_url('../build/index.css', __FILE__),
		// Add any dependencies styles may have, such as wp-components.
		array(),
		filemtime(dirname(dirname(__FILE__)) . '/build/index.css')
	);

	// send integration id to js script
	$script_data = array('store_id' => get_option('store_id', ''));


	wp_enqueue_script('hub');
	wp_localize_script('hub', 'wooParams', $script_data);
	wp_enqueue_style('hub');
}

add_action('admin_enqueue_scripts', 'add_extension_register_script');

/**
 * Register a WooCommerce Admin page.
 */
function add_extension_register_page()
{
	if (!function_exists('wc_admin_register_page')) {
		return;
	}

	wc_admin_register_page(array(
		'id'       => 'hub-page',
		'title'    => __('Hub Page', 'my-textdomain'),
		'parent'   => 'woocommerce',
		'path'     => '/hub',
		'nav_args' => array(
			'order'  => 10,
			'parent' => 'woocommerce',
		),
	));
}


add_action('admin_menu', 'add_extension_register_page');

/**
 * Register a WooCommerce Itegration for Hub.
 */
if (!class_exists('WC_Integration_Hub')) :
	/**
	 * Integration class.
	 */
	class WC_Integration_Hub
	{
		/**
		 * Construct the plugin.
		 */
		public function __construct()
		{
			add_action('plugins_loaded', array($this, 'init'));
		}

		/**
		 * Initialize the plugin.
		 */
		public function init()
		{
			// Checks if WooCommerce is installed.
			if (class_exists('WC_Integration')) {
				// Include our integration class.
				include_once 'class-wc-integration-hub-integration.php';
				// Register the integration.
				add_filter('woocommerce_integrations', array($this, 'add_integration'));
			} else {
				// throw an admin error if you like
			}
		}

		/**
		 * Add a new integration to WooCommerce.
		 *
		 * @param array Array of integrations.
		 */
		public function add_integration($integrations)
		{
			$integrations[] = 'WC_Integration_Hub_Integration';
			return $integrations;
		}
	}
endif;

$WC_Integration_Hub = new WC_Integration_Hub(__FILE__);
