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
 function your_plugin_option_updated( $option_name, $old_value, $new_value ) {
	if (  $option_name === 'business_id' || $option_name === 'consumer_key' || $option_name === 'consumer_secret') {
		
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-deactivator.php';
		Hub_Woocommerce_Deactivator::deactivate();
		require_once plugin_dir_path(__FILE__) . 'includes/class-hub-woocommerce-activator.php';
		Hub_Woocommerce_Activator::activate();
	}
}
run_hub_woocommerce();
