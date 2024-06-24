<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://hub.com/
 * @since      0.1.0
 *
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/includes
 * @author     Twerlo <support@twerlo.com>
 */
class Hub_Woocommerce_Deactivator
{

	/**
	 * Turn merchant installation status to uninstalled in hub backend and unregister events webhooks from woo
	 *
	 * @since    0.1.0
	 */
	public static function deactivate()
	{
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
		{
			Hub_Woocommerce_Deactivator::unregister_webhooks();

		}
		//COMMON WOOCOMMERCE METHOD
		Hub_Woocommerce_Deactivator::wtrackt_drop_table();
		Hub_Woocommerce_Deactivator::uninstall_merchant();
		$timestamp = wp_next_scheduled('my_function_hook');
		if ($timestamp)
		{
			wp_unschedule_event($timestamp, 'my_function_hook');
		}
	}

	public static function wtrackt_drop_table()
	{
		global $wpdb;
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cart_tracking_wc');
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cart_tracking_wc_cart');
		$wpdb->query('DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cart_tracking_wc_logs');
	}
	private static function uninstall_merchant()
	{
		// try to get hub integration id from settings
		$store_id = get_option('store_id', '');


		$store_data = array(
			"platform_id" => $store_id,
			'store_url' => get_bloginfo('url'),
			'event_name' => 'uninstall',
		);

		// Set up the request arguments
		$args = array(
			'body' => json_encode($store_data),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-Business-Id' => get_option('business_id')
			),
			'timeout' => 15,
		);

		$request_url = 'https://test.hub.avocad0.dev/api/v1/integration/events/woocommerce/app.event';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		if (is_wp_error($response) || 200 != wp_remote_retrieve_response_code($response))
		{


			update_option('notice_error', json_decode(wp_remote_retrieve_body($response))->error);

			//echo 'Error: ' . $response;
		} else
		{
			update_option('notice_error', '');

			// Success, delete integration_id
			update_option('business_id', '');
		}
	}

	private static function unregister_webhooks()
	{
		$target_url = 'https://test.hub.avocad0.dev/api/v1/integration/events/woocommerce/';

		$data_store = \WC_Data_Store::load('webhook');
		$webhooks = $data_store->search_webhooks(['paginate' => false]);

		if ($webhooks && is_array($webhooks))
		{
			foreach ($webhooks as $webhook_id)
			{
				// Load the webhook by ID
				$webhook = new \WC_Webhook($webhook_id);
				$url = $webhook->get_delivery_url();

				// Check if the webhook URL starts with the target URL
				if (strncmp($url, $target_url, strlen($target_url)) === 0)
				{
					$webhook->delete(true);
					echo "Webhook with ID $webhook_id deleted successfully.\n";
				}
			}
		} else
		{
			echo 'No webhooks found.';
		}
	}
}
