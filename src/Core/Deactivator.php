<?php

namespace Mottasl\Core;

use Mottasl\Utils\Constants;

/**
 * Fired during plugin deactivation
 *
 * @link       https://hub.com/
 * @since      0.1.0
 *
 * @package    Mottasl_Woocommerce
 * @subpackage Mottasl_Woocommerce/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    Mottasl_Woocommerce
 * @subpackage Mottasl_Woocommerce/includes
 * @author     Twerlo <support@twerlo.com>
 */
class Deactivator
{

	/**
	 * Turn merchant installation status to uninstalled in hub backend and unregister events webhooks from woo
	 *
	 * @since    0.1.0
	 */
	public static function deactivate()
	{
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			self::unregister_webhooks();
		}
		//COMMON WOOCOMMERCE METHOD
		self::wtrackt_drop_table();
		self::uninstall_merchant();

		// Clean up all cron events
		$timestamp = wp_next_scheduled('my_function_hook');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'my_function_hook');
		}

		$timestamp = wp_next_scheduled('wtrackt_cart_updates_hook');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'wtrackt_cart_updates_hook');
		}

		$timestamp = wp_next_scheduled('wtrackt_cleanup_hook');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'wtrackt_cleanup_hook');
		}
	}

	public static function wtrackt_drop_table()
	{
		global $wpdb;
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mottasl_cart_tracking');
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mottasl_cart');
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'mottasl_cart_logs');
	}
	private static function uninstall_merchant()
	{
		// try to get hub integration id from settings
		$store_id = get_option('store_id', '');


		$store_data = array(
			'event_name' => 'uninstall',
		);

		// Initialize the MottaslApi
		$api = new MottaslApi();

		// Send the uninstall event
		$response = $api->post('app.event', $store_data);

		// Check for errors
		if (isset($response['error'])) {
			update_option('notice_error', $response['error']);
			error_log('Mottasl API Error during uninstall: ' . $response['error']);
		} else {
			update_option('notice_error', '');
			// Success, delete integration_id
			update_option('mottasl_business_id', '');
		}
	}

	private static function unregister_webhooks()
	{
		$target_url = Constants::WOOCOMMERCE_API_BASE_URL . '/';

		$data_store = \WC_Data_Store::load('webhook');
		$webhooks = $data_store->search_webhooks(['paginate' => false]);

		if ($webhooks && is_array($webhooks)) {
			foreach ($webhooks as $webhook_id) {
				// Load the webhook by ID
				$webhook = new \WC_Webhook($webhook_id);
				$url = $webhook->get_delivery_url();

				// Check if the webhook URL starts with the target URL
				if (strncmp($url, $target_url, strlen($target_url)) === 0) {
					$webhook->delete(true);
					echo "Webhook with ID $webhook_id deleted successfully.\n";
				}
			}
		} else {
			echo 'No webhooks found.';
		}
	}
}
