<?php

use Mottasl\Utils\Constants;
use Mottasl\Core\MottaslApi;

add_filter('cron_schedules', function ($schedules) {
	$schedules['every-15-minutes'] = array(
		'interval' => 15 * 60,
		'display' => __('Every 1 minutes')
	);
	return $schedules;
});

if (!wp_next_scheduled('my_function_hook')) {
	wp_schedule_event(time(), 'every-15-minutes', 'my_function_hook');
}
add_action('my_function_hook', 'my_function');

function my_function()
{

	global $wpdb;
	$table_name = $wpdb->prefix . 'cart_tracking_wc_cart';

	// Define the time limit in minutes
	$time_limit = 15;

	// Calculate the cutoff time
	$cutoff_time = current_time('mysql', 1) - ($time_limit * 60);

	// SQL to update cart_status to 'abandoned'
	$sql = $wpdb->prepare(
		"UPDATE $table_name
         SET `cart_status` = 'abandoned'
        WHERE DATE(`update_time`) <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)  AND `cart_status` = 'new'",

		$cutoff_time
	);

	// Execute the SQL
	$wpdb->query($sql);
	$carts = $wpdb->get_results("
    SELECT * FROM $table_name  WHERE `cart_status` = 'abandoned' ", ARRAY_A);
	foreach ($carts as &$cart) {
		$cart['customer_data'] = json_decode($cart['customer_data']);
		$cart['products'] = json_decode($cart['products']);
	}

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send abandoned cart create event
	$response = $api->post('abandoned_cart.create', $carts);

	// Check for successful response before marking as notified
	if (!isset($response['error'])) {
		// Prepare a list of cart IDs to update
		$cart_ids = wp_list_pluck($carts, 'id');
		$cart_ids_placeholder = implode(',', array_fill(0, count($cart_ids), '%d'));

		// Update the notification_sent status to true
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}cart_tracking_wc_cart
             SET `notification_sent` = true
             WHERE `cart_status` = 'abandoned' AND `notification_sent` = 0"
		);
		$wpdb->query($sql);
	} else {
		// Log the error for debugging
		error_log('Mottasl API Error in CartCron: ' . $response['error']);
	}
}
