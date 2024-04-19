<?php
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
function activate_hub_woocommerce()
{

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
		add_action( 'updated_option', 'your_plugin_option_updated', 10, 3 );

	/**
	 * Install merchant in hub backend and register events webhooks in woo
	 *
	 * @since    0.1.0
	 */
	
	
	// Call the function to generate the API key

	if ( ! defined( 'MAIN_PLUGIN_FILE' ) ) {
		define( 'MAIN_PLUGIN_FILE', __FILE__ );
	}
	
	require plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce.php';
	$plugin = new Hub_Woocommerce();
	$plugin->run();
}
function at_rest_installation_endpoint($req)
{
	$response['installation-status'] = $req['installation-status'];
	update_option( 'installation_status', $req['installation-status'] );
	$res = new WP_REST_Response($response);
	if($req['installation-status'] !== 'installed'){
		$res->set_status(403);
		$response['installation-note'] = 'installation failed';

	}
	else{
		$res->set_status(200);
		$response['installation-note'] = 'installation successfully completed';
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
		Hub_Woocommerce_Activator::activate();

	}
	your_plugin_option_updated();

	return ['req' => $res];
}

/**
 * at_rest_init
 */
function at_rest_init()
{
    // route url: domain.com/wp-json/hub-api/v1/installation-status
    $namespace = 'hub-api/v1';
    $route     = 'installation-status';

    register_rest_route($namespace, $route, array(
        'methods'   =>'POST',
        'callback'  => 'at_rest_installation_endpoint'
    ));
}

 function your_plugin_option_updated( $option_name, $old_value, $new_value ) {
	if (   $option_name === 'consumer_key' || $option_name === 'consumer_secret') {
		$encoded_consumer_key=base64_encode(get_option( 'consumer_key' ));
    	$encoded_consumer_secret=base64_encode(get_option( 'consumer_secret' ));
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-deactivator.php';
		Hub_Woocommerce_Deactivator::deactivate();
		wp_redirect( 'https://bb98-196-153-118-193.ngrok-free.app/ecommerce-apps?install=woocomerce&consumer_key='.$encoded_consumer_key . '&consumer_secret='. $encoded_consumer_secret, );
		
	}
	if($option_name === 'business_id' ){
		$business_id =get_option( 'business_id' );
		$store_id = update_option('store_id', $random_string);
		$store_data = array(
			'store_url' => get_bloginfo('url'),
		);

		// Set up the request arguments
		$args = array(
			'body'        => json_encode($store_data),
			'headers'     => array(
				'Content-Type' => 'application/json',
				'X-BUSINESS-Id'=> $business_id

			),
			'timeout'     => 15,
		);
		$request_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/update-business-id';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		$response_code = wp_remote_retrieve_response_code($response);
		if (is_wp_error($response) ) {
			echo 'Error: ' . $response->get_error_message();

		} 
		}
}
run_hub_woocommerce();
