<?php
declare(strict_types=1);
/**
 * Plugin Name: Mottasl
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

use Hub\Admin\Setup;

require_once plugin_dir_path(__FILE__) . 'includes/admin/setup.php';




define('HUB_WOOCOMMERCE_VERSION', '0.1.0');


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hub-woocommerce-activator.php
 */
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generate_jwt_token($user_credits, $secret_key)
{
	$payload = $user_credits;

	return JWT::encode($payload, 'woocommerce-install', 'HS256');
}
function activate_hub_woocommerce()
{
	if (!class_exists('WooCommerce'))
	{
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('This plugin requires WooCommerce to function properly. Please install WooCommerce first.');
	}
	// Retrieved from filtered POST data
	require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
	Hub_Woocommerce_Activator::activate();
	$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
	update_option('encoded_user_credits', $encoded_user_credits);
	update_option('installation_status', 'pending');
}
/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hub-woocommerce-deactivator.php
 */
function deactivate_hub_woocommerce()
{
	require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-deactivator.php';
	Hub_Woocommerce_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_hub_woocommerce');
register_deactivation_hook(__FILE__, 'deactivate_hub_woocommerce');

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_hub_woocommerce()
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

	require plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce.php';
	$plugin = new Hub_Woocommerce();
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

	$cart = WC()->cart->get_cart();
	if (empty($cart))
	{
		return new WP_REST_Response(['message' => 'Cart is empty'], 200);
	}

	// Construct a simplified response of the cart contents
	$cart_items = [];
	foreach ($cart as $item_key => $values)
	{
		$product = $values['data'];
		$cart_items[] = [
			'product_id' => $product->get_id(),
			'quantity' => $values['quantity'],
			'price' => $product->get_price(),
			'title' => $product->get_title(),
		];
	}

	return new WP_REST_Response(['cart_items' => $cart_items], 200);
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
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
		update_option('business_id', $req['business_id']);
		update_option('installation_status', 'installed');
		Hub_Woocommerce_Activator::activate();
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

		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-deactivator.php';
		Hub_Woocommerce_Deactivator::deactivate();
		$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
		update_option('encoded_user_credits', $encoded_user_credits);
		update_option('installation_status', 'pending');
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
		Hub_Woocommerce_Activator::activate();
	}
}
run_hub_woocommerce();
