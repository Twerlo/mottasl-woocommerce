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
	
	public static function activate()
{


		if( get_option( 'consumer_key') == '' ||  get_option( 'consumer_secret') == '' || get_option( 'business_id') == '')
		{
			update_option( 'activation_note','not valid' );
			

		}
		else{

			Hub_Woocommerce_Activator::install_merchant();
		}
	
		
	}
	
	// if not there request new merchant install from hubs
	private static function install_merchant()
	{
		$settings = get_option('woo_commerce_hub_settings');
		$business_id =get_option( 'business_id' );
		$random_string = wp_generate_password(12, true);
		$store_id = update_option('store_id', $random_string);
        $consumer_key = get_option( 'consumer_key' );
        $consumer_secret = get_option( 'consumer_secret' );

		$store_data = array(
			'event_name' => 'installed',
			'consumer_key' => $consumer_key,
			'consumer_secret' => $consumer_secret,
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
				'X-BUSINESS-Id'=> $business_id

			),
			'timeout'     => 15,
		);

		$request_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/app.event';
		$response = wp_remote_post($request_url, $args);

		// Check for errors
		$response_code = wp_remote_retrieve_response_code($response);
		if (is_wp_error($response) ) {
			echo 'Error: ' . $response->get_error_message();

		} 
		if ($response_code !== 200) {
			update_option( 'consumer_key','' );
			update_option( 'consumer_secret','');
			update_option( 'business_id','' );


		} 
		
		else {
			// Success, save integration_id
			$body = wp_remote_retrieve_body($response);
			$responseArray = json_decode($body, true);
			Hub_Woocommerce_Activator::register_webhooks();
			error_log($body);
			?>
			<div class="error notice is-dismissable">
			  <p><?php _e( $body, 'my_plugin_textdomain' ); ?></p>
		  </div>
		  <?php

		}
	}

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
		$consumer_key = 'YOUR_CONSUMER_KEY';
		$consumer_secret = 'YOUR_CONSUMER_SECRET';

		// Set the webhook status to 'active'
		$webhook_status = 'active';

		// Set the webhook endpoint URL
		
		foreach ($webhooks_topics_to_register as $webhook_topic) {
			$webhook_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/'.$webhook_topic .'?store_url=' . get_bloginfo('url');
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
