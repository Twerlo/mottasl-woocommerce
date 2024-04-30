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

if (!defined('WPINC')) {
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
	if (!class_exists('WooCommerce')) {
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
	$user_credits = $req['code'];
	$key = 'woocommerce-install';
	$algo = 'HS256';
	if (!$user_credits) {
		return ['response' => 'consumer_key and consumer secret are required'];
	}
	$decoded_user_credit = JWT::decode($user_credits, new Key($key, $algo));
	$response = array();
	$res = new WP_REST_Response($response);
	if ($decoded_user_credit->store_url !== get_bloginfo('url') && $decoded_user_credit->consumer_key !== get_option('consumer_key') && $decoded_user_credit->consumer_secret !== get_option('consumer_secret')) {

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
		$response = $store_data;
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
		$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
		update_option('encoded_user_credits', $encoded_user_credits);
		wp_redirect('https://app.avocad0.dev/ecommerce-apps?install=woocomerce&code=' . $encoded_user_credits);
	}
}
run_hub_woocommerce();
