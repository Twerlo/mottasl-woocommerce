<?php
declare(strict_types=1);
/**
 * Plugin Name: Mottasl Woocommerce
 * Plugin URI: https://mottasl.com
 * Text Domain: mottasl-woocommerce
 * Requires at least: 5.7
 * Requires PHP: 7.4
 * Tested up to: 6.5
 * Stable tag: 1.0.0
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Version: 0.1.0
 * Author: Twerlo
 * Author URI: https://twerlo.com
 * Domain Path: /languages
 * Description: Integrate your Woocommerce Store to send WhatsApp order status updates and abandoned cart recovery campaigns to your Customers.
 *  * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */

if (!defined('WPINC'))
{
	die;
}
define('MOTTASL_PLUGIN_DIR', plugin_dir_path(__FILE__));
if (file_exists(MOTTASL_PLUGIN_DIR . 'vendor/autoload.php')) {
	require_once MOTTASL_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	wp_die('The Mottasl plugin is missing required dependencies. Please reinstall the plugin.');
	
}

require_once plugin_dir_path(__FILE__) . 'includes/admin/setup.php';


require MOTTASL_PLUGIN_DIR . 'includes/woocommerce-cart-tracking.php';
require MOTTASL_PLUGIN_DIR . 'includes/woocommerce-cart-cron.php';
require MOTTASL_PLUGIN_DIR . 'includes/admin/phone-number-field.php';


define('MOTTASL_WOOCOMMERCE_VERSION', '1.0.0');


use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generate_jwt_token($user_credits, $secret_key)
{
	$payload = $user_credits;

	return JWT::encode($payload, 'woocommerce-install', 'HS256');
}
function activate_mottasl_woocommerce()
{
	if (!class_exists('WooCommerce'))
	{
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('The Mottasl Plugin is required to be activated with WooCommerce. Please install and activate WooCommerce first.', 'Plugin activation error', array('response' => 200, 'back_link' => true));
	}
	// Retrieved from filtered POST data
	require_once plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce-activator.php';
	Mottasl_Woocommerce_Activator::activate();
	$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
	update_option('encoded_user_credits', $encoded_user_credits);
	update_option('installation_status', 'pending');

}
/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-mottasl-woocommerce-deactivator.php
 */
function deactivate_mottasl_woocommerce()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce-deactivator.php';
	Mottasl_Woocommerce_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_mottasl_woocommerce');
register_deactivation_hook(__FILE__, 'deactivate_mottasl_woocommerce');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_mottasl_woocommerce()
{
	/**
	 * The core plugin class that is used to define internationalization,
	 * admin-specific hooks, and public-facing site hooks.
	 */


	add_action('rest_api_init', 'at_rest_init');
	add_action('rest_api_init', function () {
		register_rest_route(
			'hub-api/v1',
			'/carts',
			array (
				'methods' => 'GET',
				'callback' => 'getAllCarts',
			)
		);
	});
	add_action('updated_option', 'your_plugin_option_updated', 10, 3);

	/**
	 * Install merchant in hub backend and register events webhooks in woo
	 *
	 * @since    0.1.0
	 */


	// Call the function to generate the API key

	if (!defined('MAIN_PLUGIN_FILE'))
	{
		define('MAIN_PLUGIN_FILE', __FILE__);
	}
	require plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce.php';

	$plugin = new Mottasl_Woocommerce();
	$plugin->run();
}
function getAllCarts()
{
	if (is_admin())
	{
		return new WP_REST_Response(['message' => 'Access denied in admin context'], 401);
	}



	// Ensure cart is loaded
	if (is_null(WC()->cart))
	{
		wc_load_cart();
	}
	global $wpdb;
	$abandoned_carts = $wpdb->get_results(
		"SELECT * FROM `wp_cart_tracking_wc_cart` ",
		ARRAY_A
	);
	//$abandoned_carts = WC()->cart->get_cart();
	if (empty($abandoned_carts))
	{

		return new WP_REST_Response([], 200);
	}

	// Construct a simplified response of the cart contents
	//$cart_items = [];
	// foreach ($abandoned_carts as $item_key => $values)
	// {
	// 	$product = $values['data'];
	// 	$cart_items[] = [
	// 		'product_id' => $product->get_id(),
	// 		'quantity' => $values['quantity'],
	// 		'price' => $product->get_price(),
	// 		'title' => $product->get_title(),
	// 	];
	// }

	return new WP_REST_Response(['cart_items' => $abandoned_carts], 200);
}

function at_rest_installation_endpoint($req)
{
	if (!$req['auth'])
	{
		return new WP_REST_Response(['error' => 'not authorized'], 401);
	}
	$accessToken = $req['auth']['access_token'];
	list($consumerKey, $consumerSecret) = explode(':', $accessToken);
	$key = 'woocommerce-install';
	$algo = 'HS256';
	if (!$accessToken)
	{
		update_option('notice_error', 'please connect to mottasl with correct data');
		return new WP_REST_Response(['error' => 'access token is required'], 403);


	}
	$response = array();
	$res = new WP_REST_Response($response);
	if ($consumerKey !== get_option('consumer_key') && $consumerSecret !== get_option('consumer_secret'))
	{

		update_option('notice_error', 'please connect to mottasl with correct data');
		update_option('installation_status', 'pending');
		return new WP_REST_Response(['error' => 'installation failed', 'installation_status' => 'pending'], 403);


	} else
	{
		$store_data = array(
			'event_name' => 'installed',
			'store_name' => get_bloginfo('name'),
			'store_email' => get_option('admin_email'),
			'store_url' => get_bloginfo('url'),
			'platform_id' => get_option('store_id', ''),
		);
		$res->set_status(200);
		$response = $store_data;
		$res->set_data($store_data);
		require_once plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce-activator.php';
		update_option('business_id', $req['business_id']);
		update_option('installation_status', 'installed');
		Mottasl_Woocommerce_Activator::activate();
		return new WP_REST_Response($response, 200);
	}

	return ['response' => $res];
}

/**
 * at_rest_init
 */
function at_rest_init()
{
	// route url: domain.com/wp-json/hub-api/v1/installation-status
	$namespace = 'hub-api/v1';
	$route = 'installation-status';

	register_rest_route(
		$namespace,
		$route,
		array(
			'methods' => 'POST',
			'callback' => 'at_rest_installation_endpoint'
		)
	);
}

function your_plugin_option_updated($option_name, $old_value, $new_value)
{
	if ($option_name === 'consumer_key' || $option_name === 'consumer_secret')
	{
		// $last_link = $wpdb->get_var('SELECT * FROM `wp_woocommerce_api_keys');
		// update_option('notice_error', $last_link);

		// $store_url = get_bloginfo('url');
		// $endpoint = '/wc-auth/v1/authorize';
		// $params = [
		// 	'app_name' => 'Mottasl',
		// 	'scope' => 'read-write',
		// 	'user_id' => get_current_user_id(),
		// 	'return_url' => 'https://6719-197-43-50-237.ngrok-free.app/wordpress/wp-admin/plugins.php',
		// 	'callback_url' => 'https://6719-197-43-50-237.ngrok-free.app/wc-api/v3/callback',
		// ];
		// $query_string = http_build_query($params);
		// wp_redirect($store_url . $endpoint . '?' . $query_string);

		require_once plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce-deactivator.php';
		Mottasl_Woocommerce_Deactivator::deactivate();
		$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
		update_option('encoded_user_credits', $encoded_user_credits);
		update_option('installation_status', 'pending');
		require_once plugin_dir_path(__FILE__) . 'includes/class-mottasl-woocommerce-activator.php';
		Mottasl_Woocommerce_Activator::activate();
	}
}
run_mottasl_woocommerce();

register_activation_hook(__FILE__, 'mottasl_plugin_activate');

function mottasl_plugin_activate() {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    if (!is_plugin_active('woocommerce/woocommerce.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        update_option('mottasl_wc_missing_notice', true);
    }
}

add_action('admin_notices', 'mottasl_wc_missing_notice');
function mottasl_wc_missing_notice() {
    if (get_option('mottasl_wc_missing_notice')) {
        echo '<div class="notice notice-error is-dismissible">
            <p><strong>Mottasl Plugin requires WooCommerce to be installed and activated.</strong></p>
            <p><a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">Click here to install WooCommerce</a>.</p>
        </div>';
        delete_option('mottasl_wc_missing_notice');
    }
}