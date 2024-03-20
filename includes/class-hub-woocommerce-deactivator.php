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
		Hub_Woocommerce_Deactivator::unregister_webhooks();
		Hub_Woocommerce_Deactivator::uninstall_merchant();
	}

	private static function uninstall_merchant()
	{
		// try to get hub integration id from settings
		$store_id = get_option('store_id', '');


		$store_data = array(
			"platform_id" => $store_id,
			'event_name' => 'uninstall',
		);

		// Set up the request arguments
		$args = array(
			'body'        => json_encode($store_data),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 15,
		);

		$request_url = 'https://e6c5-102-186-40-102.ngrok-free.app/api/v1/integration/events/woocommerce/app.event';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		if (is_wp_error($response)) {
			echo 'Error: ' . $response->get_error_message();
		} else {
			// Success, delete integration_id
			update_option('store_id', '');
		}
	}

	private static function unregister_webhooks()
	{
		$target_url = 'https://e6c5-102-186-40-102.ngrok-free.app/api/v1/integration/events/woocommerce/app.event' ;

		$data_store = \WC_Data_Store::load('webhook');
		$webhooks   = $data_store->search_webhooks([ 'paginate' => false]);


		if ($webhooks && is_array($webhooks)) {
			foreach ($webhooks as $webhook_id) {
				// Delete the webhook
				$webhook = new WC_Webhook();
				$webhook->set_id($webhook_id);
				$url = $webhook->get_delivery_url();

				if (strpos($url, $target_url) === 0) {
					$webhook->delete(true);
				  }
			}
			echo 'Webhooks deleted successfully.';
		} else {
			echo 'No webhooks found.';
		}
	}
}
