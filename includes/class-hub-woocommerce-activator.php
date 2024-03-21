<?php

/**
 * Fired during plugin activation
 *
 * @link       https://hub.com/
 * @since      0.1.0
 *
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    Hub_Woocommerce
 * @subpackage Hub_Woocommerce/includes
 * @author     Twerlo <support@twerlo.com>
 */
class Hub_Woocommerce_Activator
{

	/**
	 * Install merchant in hub backend and register events webhooks in woo
	 *
	 * @since    0.1.0
	 */
	public static function activate()
	{

		Hub_Woocommerce_Activator::install_merchant();
		Hub_Woocommerce_Activator::register_webhooks();
	}

	// if not there request new merchant install from hubs
	private static function install_merchant()
	{
		$random_string = wp_generate_password(12, true);
		$store_id = update_option('store_id', $random_string);

		$store_data = array(
			'event_name' => 'installed',
			'store_name' => get_bloginfo('name'),
			'store_phone' => get_option('admin_phone',''),
			'store_email' => get_option('admin_email'),
			'store_url' => get_bloginfo('url'),
			'platform_id' =>get_option('store_id', ''),
		);

		// Set up the request arguments
		$args = array(
			'body'        => json_encode($store_data),
			'headers'     => array(
				'Content-Type' => 'application/json',
				'X-BUSINESS-Id'=> 'e692e4f4-f85b-4a02-bf01-87f24ac5a817'

			),
			'timeout'     => 15,
		);

		$request_url = 'https://59f7-197-43-174-68.ngrok-free.app/api/v1/integration/events/woocommerce/app.event';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		if (is_wp_error($response)) {
			echo 'Error: ' . $response->get_error_message();
		} else {
			// Success, save integration_id
			$body = wp_remote_retrieve_body($response);
			echo 'Response: ' . $body;
			error_log($body);

			$responseArray = json_decode($body, true);

		}
	}

	private static function register_webhooks()
	{
		$webhooks_topics_to_register = [
			'order.created',
			'order.updated',
			'order.deleted',
			'order.restored',
			'product.created',
			'product.updated',
			'product.deleted',
			'customer.created',
			'customer.updated',
			'customer.deleted',
			'coupon.created',
			'coupon.updated',
			'coupon.deleted'
		];

		// not required though, it is just for webhook secret
		$consumer_key = 'YOUR_CONSUMER_KEY';
		$consumer_secret = 'YOUR_CONSUMER_SECRET';

		// Set the webhook status to 'active'
		$webhook_status = 'active';

		// Set the webhook endpoint URL
		
		foreach ($webhooks_topics_to_register as $webhook_topic) {
			$webhook_url = 'https://59f7-197-43-174-68.ngrok-free.app/api/v1/integration/events/woocommerce/' .$webhook_topic .'?platform_id=' . get_option('store_id', '');
			// Create the webhook data
			$webhook_data = array(
				'name' => 'Hub Event: ' . $webhook_topic,
				'topic' => $webhook_topic,
				'delivery_url' => $webhook_url,
				'status' => $webhook_status,
				'api_version' => 'v3',
				'secret' => wc_api_hash($consumer_key . $consumer_secret),
				'user_id' => get_current_user_id(),
			);

			// Create a new WC_Webhook instance
			$webhook = new WC_Webhook();

			// Set the webhook data
			$webhook->set_props($webhook_data);

			// Save the webhook
			$webhook->save();
		}
	}
}
