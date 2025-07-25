<?php

/**
 * Mottasl WooCommerce Integration
 *
 * @package           Mottasl_WooCommerce
 * @author            Ahmed Zidan <ahmed.zidan@twerlo.com>
 * @copyright         2025 Twerlo
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Mottasl for WooCommerce
 * Plugin URI:        https://mottasl.com
 * Description:       Integrates WooCommerce with the Mottasl.ai platform, sending order status updates, abandoned cart events, and generating invoices.
 * Version:           1.0.0
 * Author:            Twerlo
 * Author URI:        https://twerlo.com
 * License:           GPL-3.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       mottasl-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * WC requires at least: 6.0
 * WC tested up to:     8.5
 * Woo: 12345:abcdef1234567890abcdef1234567890
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

// Define plugin constants
define('MOTTASL_WC_VERSION', '1.0.0');
define('MOTTASL_WC_PLUGIN_FILE', __FILE__);
define('MOTTASL_WC_PLUGIN_PATH', plugin_dir_path(MOTTASL_WC_PLUGIN_FILE));
define('MOTTASL_WC_PLUGIN_URL', plugin_dir_url(MOTTASL_WC_PLUGIN_FILE));
define('MOTTASL_WC_BASENAME', plugin_basename(MOTTASL_WC_PLUGIN_FILE)); // Crucial for action links filter
define('MOTTASL_WC_TEXT_DOMAIN', 'mottasl-woocommerce');
define('MOTTASL_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Improved autoloader handling to prevent conflicts with WooCommerce
$jetpack_autoload_file = MOTTASL_WC_PLUGIN_PATH . 'vendor/autoload_packages.php';
$fallback_autoload_file = MOTTASL_WC_PLUGIN_PATH . 'vendor/autoload.php';

// Try to load Jetpack autoloader first (WordPress best practice)
if (file_exists($jetpack_autoload_file)) {
	require_once $jetpack_autoload_file;
} elseif (file_exists($fallback_autoload_file)) {
	// Fallback to standard Composer autoloader
	try {
		require_once $fallback_autoload_file;
	} catch (Exception $e) {
		add_action('admin_notices', function () use ($e) {
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php esc_html_e('Mottasl for WooCommerce', 'mottasl-woocommerce'); ?></strong>:
					<?php
					echo wp_kses_post(
						sprintf(
							__('Failed to load dependencies: %s', 'mottasl-woocommerce'),
							esc_html($e->getMessage())
						)
					);
					?>
				</p>
			</div>
			<?php
		});

		add_action('admin_init', function () {
			if (is_plugin_active(MOTTASL_WC_BASENAME)) {
				deactivate_plugins(MOTTASL_WC_BASENAME);
			}
		});
		return;
	}
} else {
	add_action('admin_notices', function () {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Mottasl for WooCommerce */
						__('<strong>%s</strong> requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory or download a pre-built version.', 'mottasl-woocommerce'),
						esc_html__('Mottasl for WooCommerce', 'mottasl-woocommerce')
					)
				);
				?>
			</p>
		</div>
		<?php
	});

	add_action('admin_init', function () {
		if (is_plugin_active(MOTTASL_WC_BASENAME)) {
			deactivate_plugins(MOTTASL_WC_BASENAME);
		}
	});
	return;
}

// Manually require the MottaslWoocommerce class if autoload not working
if (!class_exists('Mottasl\Core\MottaslWoocommerce')) {
	$mottasl_woocommerce_path = MOTTASL_WC_PLUGIN_PATH . 'src/Core/MottaslWoocommerce.php';
	if (file_exists($mottasl_woocommerce_path)) {
		require_once $mottasl_woocommerce_path;
	}
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

use Mottasl\Utils\Helpers;
use Firebase\JWT\JWT;

function generate_jwt_token($user_credits, $secret_key)
{
	$payload = $user_credits;

	return JWT::encode($payload, 'woocommerce-install', 'HS256');
}

/**
 * The code that runs during plugin activation.
 *
 * This action is documented in Core/Activator.php
 *
 * @since    0.1.0
 */
function activate_mottasl_woocommerce()
{
	error_log('activate_mottasl_woocommerce called');
	if (!class_exists('WooCommerce')) {
		deactivate_plugins(plugin_basename(__FILE__));
		wp_die('The Mottasl Plugin is required to be activated with WooCommerce. Please install and activate WooCommerce first.', 'Plugin activation error', array('response' => 200, 'back_link' => true));
	}
	error_log('Start activating Mottasl Process');
	// Retrieved from filtered POST data
	\Mottasl\Core\Activator::activate();
	$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
	update_option('encoded_user_credits', $encoded_user_credits);
	update_option('mottasl_wc_installation_status', 'pending');
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_mottasl_woocommerce()
{
	\Mottasl\Core\Deactivator::deactivate();
}

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
			array(
				'methods' => 'GET',
				'callback' => 'getAllCarts',
				'permission_callback' => '__return_true',
			)
		);
	});

	add_action('updated_option', 'mottasl_settings_updated', 10, 3);

	/**
	 * Install merchant in hub backend and register events webhooks in woo
	 *
	 * @since    0.1.0
	 */


	// Call the function to generate the API key

	if (!defined('MAIN_PLUGIN_FILE')) {
		define('MAIN_PLUGIN_FILE', __FILE__);
	}

	register_activation_hook(__FILE__, 'activate_mottasl_woocommerce');
	register_deactivation_hook(__FILE__, 'deactivate_mottasl_woocommerce');

	$plugin = new \Mottasl\Core\MottaslWoocommerce();
	$plugin->run();
}
function getAllCarts()
{
	if (is_admin()) {
		return new WP_REST_Response(['message' => 'Access denied in admin context'], 401);
	}



	// Ensure cart is loaded
	if (is_null(WC()->cart)) {
		wc_load_cart();
	}
	global $wpdb;
	$abandoned_carts = $wpdb->get_results(
		"SELECT * FROM `wp_cart_tracking_wc_cart` ",
		ARRAY_A
	);
	//$abandoned_carts = WC()->cart->get_cart();
	if (empty($abandoned_carts)) {

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
	error_log('at_rest_installation_endpoint called');
	if (!$req['auth']) {
		return new WP_REST_Response(['error' => 'not authorized'], 401);
	}
	$accessToken = $req['auth']['access_token'];
	list($consumerKey, $consumerSecret) = explode(':', $accessToken);
	$key = 'woocommerce-install';
	$algo = 'HS256';
	if (!$accessToken) {
		update_option('notice_error', 'please connect to mottasl with correct data');
		return new WP_REST_Response(['error' => 'access token is required'], 403);
	}
	$response = array();
	$res = new WP_REST_Response($response);
	if ($consumerKey !== get_option('consumer_key') && $consumerSecret !== get_option('consumer_secret')) {

		update_option('notice_error', 'please connect to mottasl with correct data');
		update_option('mottasl_wc_installation_status', 'pending');
		return new WP_REST_Response(['error' => 'installation failed', 'mottasl_wc_installation_status' => 'pending'], 403);
	} else {
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
		update_option('business_id', $req['business_id']);
		update_option('mottasl_wc_installation_status', 'installed');
		\Mottasl\Core\Activator::activate();
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
	register_rest_route(
		'hub-api/v1',
		'installation-status',
		[
			'methods' => 'POST',
			'callback' => 'at_rest_installation_endpoint',
			'permission_callback' => function ($request) {
				// Check if the request has the required parameters
				return isset($request['auth']) && isset($request['auth']['access_token']);
			},
		]
	);
}

function mottasl_settings_updated($option_name, $old_value, $new_value)
{
	if ($option_name === 'consumer_key' || $option_name === 'consumer_secret') {
		$consumerKey = get_option('consumer_key');
		$consumerSecret = get_option('consumer_secret');
		if ($consumerKey && $consumerSecret) {
			// If both keys are set, proceed with the installation
			$encoded_user_credits = generate_jwt_token(['consumer_key' => $consumerKey, 'consumer_secret' => $consumerSecret, 'store_url' => get_bloginfo('url')], 'woocommerce-install');
			update_option('encoded_user_credits', $encoded_user_credits);
			update_option('mottasl_wc_installation_status', 'pending');
			\Mottasl\Core\Activator::activate();
		} else {
			// If either key is missing, set the installation status to pending
			update_option('mottasl_wc_installation_status', 'pending');
		}
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

		// exit;
	}
}
run_mottasl_woocommerce();

register_activation_hook(__FILE__, 'mottasl_plugin_activate');

function mottasl_plugin_activate()
{
	if (!function_exists('is_plugin_active')) {
		include_once(ABSPATH . 'wp-admin/includes/plugin.php');
	}
	if (!is_plugin_active('woocommerce/woocommerce.php')) {
		deactivate_plugins(plugin_basename(__FILE__));
		update_option('mottasl_wc_missing_notice', true);
	}
}

add_action('admin_notices', 'mottasl_wc_missing_notice');
function mottasl_wc_missing_notice()
{
	if (get_option('mottasl_wc_missing_notice')) {
		echo '<div class="notice notice-error is-dismissible">
            <p><strong>Mottasl Plugin requires WooCommerce to be installed and activated.</strong></p>
            <p><a href="' . esc_url(admin_url('plugin-install.php?s=woocommerce&tab=search&type=term')) . '">Click here to install WooCommerce</a>.</p>
        </div>';
		delete_option('mottasl_wc_missing_notice');
	}
}
