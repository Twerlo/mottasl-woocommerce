<?php
use Firebase\JWT\JWT;
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
	function generate_jwt_token($user_id, $secret_key)
{
	$payload = [
		'sub' => $user_id,
	];

	return JWT::encode($payload, 'woocommerce-install', 'HS256');
}

	public static function activate()
	{
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( plugin_basename( __FILE__ ) );
			wp_die( 'This plugin requires WooCommerce to function properly. Please install WooCommerce first.' );
		}


		if (get_option('consumer_key') == '' || get_option('consumer_secret') == '') {
			update_option('activation_note', 'not valid');


		} else {
			Hub_Woocommerce_Activator::register_webhooks();
				}


	}

	// if not there request new merchant install from hubs


	private static function register_webhooks()
	{
		$webhooks_topics_to_register = [
			'order.created',
			'order.updated',
			'product.updated',
			'customer.created',
			'customer.updated',

		];

		// not required though, it is just for webhook secret
		$consumer_key = get_option('consumer_key');
		$consumer_secret = get_option('consumer_secret');
		;
		// Set the webhook status to 'active'
		$webhook_status = 'active';

		// Set the webhook endpoint URL

		foreach ($webhooks_topics_to_register as $webhook_topic) {
			$webhook_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/' . $webhook_topic . '?store_url=' . get_bloginfo('url');
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
