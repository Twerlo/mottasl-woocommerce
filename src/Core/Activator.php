<?php

namespace Mottasl\Core;

use Firebase\JWT\JWT;

/**
 * Fired during plugin activation
 *
 * @link       https://mottasl.com/
 * @since      0.1.0
 *
 * @package    Mottasl
 * @subpackage Mottasl/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    Mottasl
 * @subpackage Mottasl/includes
 * @author     Twerlo <support@twerlo.com>
 */

class Activator
{
	function __construct()
	{
		// Prevent direct access to the class
		if (!defined('ABSPATH')) {
			exit;
		}
		error_log('Activating Mottsl Plugin :)');
		// Check if WooCommerce is active
		if (!class_exists('WooCommerce')) {
			add_action('admin_notices', function () {
?>
				<div class="notice notice-error">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: %s: Mottasl for WooCommerce */
								__('<strong>%s</strong> requires WooCommerce to be installed and activated.', 'mottasl-woocommerce'),
								esc_html__('Mottasl for WooCommerce', 'mottasl-woocommerce')
							)
						);
						?>
					</p>
				</div>
<?php
			});
			return;
		}
		add_action('activated_plugin', array($this, 'activate'));
		add_action('admin_init', array($this, 'woocommerce_cart_tracking_installation'));
		error_log('Mottasl Activator initialized');
	}
	function generate_jwt_token($user_id, $secret_key)
	{
		$payload = [
			'sub' => $user_id,
		];

		return JWT::encode($payload, 'woocommerce-install', 'HS256');
	}

	public static function woocommerce_cart_tracking_installation()
	{

		global $wpdb;
		$table_name = $wpdb->prefix . 'cart_tracking_wc';

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = "CREATE TABLE $table_name (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		product_id bigint(20) NOT NULL,
		quantity double NOT NULL DEFAULT 0,
		cart_number bigint(20) NOT NULL,
        removed boolean NOT NULL DEFAULT false,
		PRIMARY KEY  (id)
	) $charset_collate;";

		dbDelta($sql);

		$table_name = $wpdb->prefix . 'cart_tracking_wc_cart';
		$sql_cart = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            creation_time datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            update_time datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            cart_total double DEFAULT 0,
			cart_status varchar(20)  DEFAULT 'new',
			store_url varchar(300)  DEFAULT '',
            order_created bigint(20)  DEFAULT 0,
			notification_sent boolean  DEFAULT false,
            customer_id bigint(20) DEFAULT 0,
            ip_address varchar(20),
			 customer_data JSON DEFAULT NULL,
            products JSON DEFAULT NULL,


            PRIMARY KEY  (id)
        ) $charset_collate;";

		dbDelta($sql_cart);

		$table_name = $wpdb->prefix . 'cart_tracking_wc_logs';
		$sql_cart = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            op_time datetime DEFAULT '0000-00-00 00:00:00' ,
            op_type bigint(20)  DEFAULT 0,
            customer_id bigint(20)  DEFAULT 0,
            product_id bigint(20) NOT NULL,
			store_url varchar(100)  DEFAULT '',
		quantity double  DEFAULT 0,
		cart_number bigint(20) NOT NULL,
        op_value varchar(100) DEFAULT '',
            PRIMARY KEY  (id)
        ) $charset_collate;";
		// op type 1 for add new product
		// 2 for removed product
		// 3 for order created
		// 4 for order status update
		dbDelta($sql_cart);
	}
	public static function activate()
	{
		error_log("Activate called staticly");
		if (!class_exists('WooCommerce')) {
			error_log('Woocommerce Not found, Deactivating Mottasl');
			deactivate_plugins(plugin_basename(__FILE__));
			wp_die('This plugin requires WooCommerce to function properly. Please install WooCommerce first.');
		}

		$consumerKey = get_option('consumer_secret') ?? '';
		$consumerSecret = get_option('consumer_secret') ?? '';

		if (!$consumerKey || !$consumerSecret) {
			update_option('activation_note', 'not valid');
		} else {
			self::register_webhooks();
			self::woocommerce_cart_tracking_installation();
		}
	}

	// if not there request new merchant install from hubs


	private static function register_webhooks()
	{
		$webhooks_topics_to_register = [
			'order.updated',
			'product.updated',
			'customer.created',
			'customer.updated',

		];

		// not required though, it is just for webhook secret
		$consumer_key = get_option('consumer_key');
		$consumer_secret = get_option('consumer_secret');;
		// Set the webhook status to 'active'
		$webhook_status = 'active';

		// Set the webhook endpoint URL

		foreach ($webhooks_topics_to_register as $webhook_topic) {
			$api = new MottaslApi();
			$webhook_url = $api->getApiUrl('/integration/events/woocommerce/' . $webhook_topic);
			error_log('Webhook URL: ' . $webhook_url);
			// Create the webhook data
			$webhook_data = array(
				'name' => 'Mottasl: ' . $webhook_topic,
				'topic' => $webhook_topic,
				'delivery_url' => $webhook_url,
				'status' => $webhook_status,
				'api_version' => 'v3',
				'secret' => wc_api_hash($consumer_key . $consumer_secret),
				'user_id' => get_current_user_id(),
			);

			// Create a new WC_Webhook instance
			$webhook = new \WC_Webhook();

			// Set the webhook data
			$webhook->set_props($webhook_data);

			// Save the webhook
			$webhook->save();
		}
	}
}
