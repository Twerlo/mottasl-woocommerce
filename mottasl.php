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

// Exclude REST API requests from WooCommerce coming soon mode
// This needs to be added early, before plugins_loaded
add_filter('woocommerce_coming_soon_exclude', function ($is_excluded) {
	// Check if this is a REST API request
	if (defined('REST_REQUEST') && REST_REQUEST) {
		return true;
	}

	// Also check for wp-json in the URL as fallback
	if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-json/') !== false) {
		return true;
	}

	return $is_excluded;
});

use Mottasl\Utils\Helpers;
use Mottasl\Utils\Constants;

/**
 * The code that runs during plugin activation.
 *
 * This action is documented in Core/Activator.php
 *
 * @since    0.1.0
 */
if (!function_exists('activate_mottasl_woocommerce')) {
	function activate_mottasl_woocommerce()
	{
		error_log('activate_mottasl_woocommerce called');
		if (!class_exists('WooCommerce')) {
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('The Mottasl Plugin is required to be activated with WooCommerce. Please install and activate WooCommerce first.', 'Plugin activation error', array('response' => 200, 'back_link' => true));
		}
		error_log('Start activating Mottasl Process');

		// Only call Activator if the class exists
		if (class_exists('\Mottasl\Core\Activator')) {
			try {
				\Mottasl\Core\Activator::activate();
			} catch (Exception $e) {
				error_log('Mottasl Activator error: ' . $e->getMessage());
			}
		}

		// Generate JWT token safely
		$consumer_key = get_option('mottasl_consumer_key', '');
		$consumer_secret = get_option('mottasl_consumer_secret', '');
		$businessId = get_option('mottasl_business_id', '');
		$helpers = new Helpers();
		if (!empty($consumer_key) && !empty($consumer_secret) && !empty($businessId)) {
			$installationToken = $helpers->getInstallationToken();
			update_option('mottasl_installation_token', $installationToken);
		}

		update_option('mottasl_wc_installation_status', 'pending');
		error_log('Mottasl activation completed');
	}
}

/**
 * The code that runs during plugin deactivation.
 */
if (!function_exists('deactivate_mottasl_woocommerce')) {
	function deactivate_mottasl_woocommerce()
	{
		// Only call Deactivator if the class exists
		if (class_exists('\Mottasl\Core\Deactivator')) {
			try {
				\Mottasl\Core\Deactivator::deactivate();
			} catch (Exception $e) {
				error_log('Mottasl Deactivator error: ' . $e->getMessage());
			}
		}
	}
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
if (!function_exists('run_mottasl_woocommerce')) {
	function run_mottasl_woocommerce()
	{
		error_log('run_mottasl_woocommerce function called');
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

		// Only instantiate the main plugin class if it exists
		if (class_exists('\Mottasl\Core\MottaslWoocommerce')) {
			$plugin = new \Mottasl\Core\MottaslWoocommerce();
			$plugin->run();
		} else {
			// If the main class doesn't exist, log it but don't fail
			error_log('Mottasl\Core\MottaslWoocommerce class not found, plugin will work with basic functionality');

			// Fallback: instantiate Setup class directly if main class doesn't exist
			if (class_exists('\Mottasl\Admin\Setup')) {
				new \Mottasl\Admin\Setup();
			}
		}

		// Initialize Cart Tracking functionality by including the file
		$cart_tracking_file = plugin_dir_path(__FILE__) . 'src/Core/CartTracking.php';
		if (file_exists($cart_tracking_file)) {
			require_once $cart_tracking_file;
			error_log('Mottasl CartTracking initialized');
		} else {
			error_log('Mottasl CartTracking file not found: ' . $cart_tracking_file);
		}

		// Initialize Cart Cron functionality by including the file
		$cart_cron_file = plugin_dir_path(__FILE__) . 'src/Core/CartCron.php';
		if (file_exists($cart_cron_file)) {
			require_once $cart_cron_file;
			error_log('Mottasl CartCron initialized');
		} else {
			error_log('Mottasl CartCron file not found: ' . $cart_cron_file);
		}
	}
}

if (!function_exists('getAllCarts')) {
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
			"SELECT * FROM `wp_mottasl_cart` ",
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
}

if (!function_exists('at_rest_installation_endpoint')) {
	function at_rest_installation_endpoint($request)
	{
		error_log('at_rest_installation_endpoint called');
		error_log('Request method: ' . $request->get_method());
		error_log('Request body: ' . $request->get_body());

		// Get auth data from request parameters (properly handles JSON body)
		$auth = $request->get_param('auth');
		if (empty($auth) || !is_array($auth)) {
			return new WP_REST_Response(['error' => 'not authorized'], 401);
		}

		$accessToken = isset($auth['access_token']) ? $auth['access_token'] : '';
		if (empty($accessToken)) {
			update_option('notice_error', 'please connect to mottasl with correct data');
			return new WP_REST_Response(['error' => 'access token is required'], 403);
		}

		list($consumerKey, $consumerSecret) = explode(':', $accessToken);
		$key = Constants::JWT_SECRET_KEY;
		$algo = 'HS256';
		// Check credentials match stored values
		if ($consumerKey !== get_option('mottasl_consumer_key') || $consumerSecret !== get_option('mottasl_consumer_secret')) {
			update_option('notice_error', 'please connect to mottasl with correct data');
			update_option('mottasl_wc_installation_status', 'pending');
			return new WP_REST_Response(['error' => 'installation failed', 'mottasl_wc_installation_status' => 'pending'], 403);
		}

		// Auth successful - proceed with installation
		$business_id = $request->get_param('business_id');
		$store_data = array(
			'event_name' => 'installed',
			'store_name' => get_bloginfo('name'),
			'store_email' => get_option('admin_email'),
			'store_url' => get_bloginfo('url'),
			'platform_id' => get_option('store_id', ''),
		);

		update_option('mottasl_business_id', $business_id);
		update_option('mottasl_wc_installation_status', 'installed');

		// Only call Activator if the class exists
		if (class_exists('\Mottasl\Core\Activator')) {
			try {
				\Mottasl\Core\Activator::activate();
			} catch (Exception $e) {
				error_log('Mottasl Activator error in REST endpoint: ' . $e->getMessage());
			}
		}

		return new WP_REST_Response($store_data, 200);
	}
}

/**
 * at_rest_init
 */
if (!function_exists('at_rest_init')) {
	function at_rest_init()
	{
		error_log('at_rest_init called - registering REST API routes');
		// route url: domain.com/wp-json/hub-api/v1/installation-status
		register_rest_route(
			'hub-api/v1',
			'installation-status',
			[
				'methods' => 'POST',
				'callback' => 'at_rest_installation_endpoint',
				// Allow all requests through - auth is handled in the callback
				'permission_callback' => '__return_true',
			]
		);
		error_log('REST API route registered: hub-api/v1/installation-status');
	}
}

if (!function_exists('mottasl_settings_updated')) {
	function mottasl_settings_updated($option_name, $old_value, $new_value)
	{
		if ($option_name === 'mottasl_consumer_key' || $option_name === 'mottasl_consumer_secret') {
			$consumerKey = get_option('mottasl_consumer_key');
			$consumerSecret = get_option('mottasl_consumer_secret');
			if ($consumerKey && $consumerSecret) {
				$helpers = new Helpers();
				// If both keys are set, proceed with the installation
				$installationToken = $helpers->getInstallationToken();
				update_option('mottasl_installation_token', $installationToken);
				update_option('mottasl_wc_installation_status', 'pending');

				// Only call Activator if the class exists
				if (class_exists('\Mottasl\Core\Activator')) {
					try {
						\Mottasl\Core\Activator::activate();
					} catch (Exception $e) {
						error_log('Mottasl Activator error in settings update: ' . $e->getMessage());
					}
				}
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
}
run_mottasl_woocommerce();

add_action('admin_notices', 'mottasl_wc_missing_notice');
if (!function_exists('mottasl_wc_missing_notice')) {
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
}
