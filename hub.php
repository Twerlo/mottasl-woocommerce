<?php
declare(strict_types=1);
/**
 * Plugin Name: Hub
 * Version: 0.1.0
 * Author: Twerlo
 * Author URI: https://twerlo.com
 * Text Domain: hub
 * Domain Path: /languages
 * Description: Integrate your Woocommerce Store to send WhatsApp order status updates and abandoned cart recovery campaigns to your Customers.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package extension
 */

if (!defined('WPINC')) {
	die;
}

require_once plugin_dir_path(__FILE__) . 'includes/admin/setup.php';
use Hub\Admin\Setup;




define('HUB_WOOCOMMERCE_VERSION', '0.1.0');


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hub-woocommerce-activator.php
 */
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generate_jwt_token($user_id, $secret_key)
{
	$payload = [
		'sub' => $user_id,
	];

	return JWT::encode($payload, 'woocommerce-install', 'HS256');
}
function activate_hub_woocommerce()
{
	// Retrieved from filtered POST data



	require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
	Hub_Woocommerce_Activator::activate();
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
	add_action('updated_option', 'your_plugin_option_updated', 10, 3);

	/**
	 * Install merchant in hub backend and register events webhooks in woo
	 *
	 * @since    0.1.0
	 */


	// Call the function to generate the API key

	if (!defined('MAIN_PLUGIN_FILE')) {
		define('MAIN_PLUGIN_FILE', __FILE__);
	}

	require plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce.php';
	$plugin = new Hub_Woocommerce();
	$plugin->run();
}
function at_rest_installation_endpoint($req)
{
	$consumer_key = $req['consumer_key'];
	$consumer_secret = $req['consumer_secret'];
	$key = 'woocommerce-install';
	$algo = 'HS256';
	if (!$consumer_key || !$consumer_secret) {
		return ['response' => 'consumer_key and consumer secret are required'];
	}

	$decoded_consumer_secret = JWT::decode($consumer_secret, new Key($key, $algo));
	$decoded_consumer_key = JWT::decode($consumer_key, new Key($key, $algo));


	$response = array();
	$res = new WP_REST_Response($response);
	if ($decoded_consumer_secret->sub !== get_option('consumer_key') && $decoded_consumer_secret->sub !== get_option('consumer_secret')) {

		$res->set_status(403);
		$response['installation-note'] = 'installation failed';
		update_option('installation_status', 'pending');

	} else {
		$business_id = get_option('business_id');
		$store_data = array(
			'event_name' => 'installed',
			'store_name' => get_bloginfo('name'),
			'store_email' => get_option('admin_email'),
			'store_url' => get_bloginfo('url'),
			'platform_id' => get_option('store_id', ''),
		);
		$res->set_status(200);
		$response=$store_data;
		$res->set_data($store_data);
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
		Hub_Woocommerce_Activator::activate();
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
	if ($option_name === 'consumer_key' || $option_name === 'consumer_secret') {

		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-deactivator.php';
		Hub_Woocommerce_Deactivator::deactivate();
		$encoded_consumer_key = generate_jwt_token(get_option('consumer_key'), 'woocommerce-install');
		$encoded_consumer_secret = generate_jwt_token(get_option('consumer_secret'), 'woocommerce-install');

		update_option('encoded_consumer_key', $encoded_consumer_key);
		wp_redirect('https://app.avocad0.dev/ecommerce-apps?install=woocomerce&consumer_key=' . $encoded_consumer_key . '&consumer_secret=' . $encoded_consumer_secret . '&store_url=' . get_bloginfo('url'));

	}
	if ($option_name === 'business_id') {
		$business_id = get_option('business_id');
		$store_id = update_option('store_id', $random_string);
		$store_data = array(
			'store_url' => get_bloginfo('url'),
		);

		// Set up the request arguments
		$args = array(
			'body' => json_encode($store_data),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-BUSINESS-Id' => $business_id

			),
			'timeout' => 15,
		);
		$request_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/update-business-id';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		$response_code = wp_remote_retrieve_response_code($response);
		if (is_wp_error($response)) {
			echo 'Error: ' . $response->get_error_message();

		}
	}
}
run_hub_woocommerce();
