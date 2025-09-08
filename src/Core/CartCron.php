<?php

use Mottasl\Utils\Constants;
use Mottasl\Core\MottaslApi;

add_filter('cron_schedules', function ($schedules) {
	$schedules['every-15-minutes'] = array(
		'interval' => 15 * 60,
		'display' => __('Every 15 minutes')
	);
	return $schedules;
});

if (!wp_next_scheduled('my_function_hook')) {
	wp_schedule_event(time(), 'every-15-minutes', 'my_function_hook');
}
add_action('my_function_hook', 'my_function');

// Add cleanup cron for old completed/deleted carts
add_filter('cron_schedules', function ($schedules) {
	$schedules['daily'] = array(
		'interval' => 24 * 60 * 60,
		'display' => __('Daily')
	);
	return $schedules;
});

if (!wp_next_scheduled('wtrackt_cleanup_hook')) {
	wp_schedule_event(time(), 'daily', 'wtrackt_cleanup_hook');
}
add_action('wtrackt_cleanup_hook', 'wtrackt_cleanup_old_carts');

function my_function()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'cart_tracking_wc_cart';

	// Define the time limit in minutes
	$time_limit = 15;

	// SQL to update cart_status to 'abandoned' (no placeholders needed for this query)
	$sql = "UPDATE $table_name
         SET `cart_status` = 'abandoned'
        WHERE `update_time` <= DATE_SUB(NOW(), INTERVAL $time_limit MINUTE) AND `cart_status` = 'new'";

	// Execute the SQL
	$wpdb->query($sql);

	// Only get carts that are abandoned and haven't been notified yet
	$carts = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_name
    WHERE `cart_status` = %s
    AND `notification_sent` = 0", 'abandoned'), ARRAY_A);

	if (empty($carts)) {
		error_log('No new abandoned carts to process');
		return;
	}

	// Transform cart data to match expected API format
	$formatted_carts = [];
	foreach ($carts as $cart) {
		$customer_data = json_decode($cart['customer_data'], true);
		$products = json_decode($cart['products'], true);

		// Convert products to line_items format
		$line_items = [];
		if (is_array($products)) {
			foreach ($products as $index => $product) {
				// Extract clean price (remove HTML)
				$clean_price = $product['price'];
				if (strpos($clean_price, '<span') !== false) {
					// Extract number from HTML price format
					preg_match('/(\d+[,.]?\d*)/', $clean_price, $matches);
					$clean_price = isset($matches[1]) ? str_replace(',', '.', $matches[1]) : '0.00';
				}

				$line_items[] = [
					'id' => ($index + 1) * 1000 + intval($cart['id']), // Generate unique line item ID
					'product_id' => intval($product['product_id']),
					'variant_id' => null,
					'title' => get_the_title($product['product_id']) ?: 'Product #' . $product['product_id'],
					'quantity' => intval($product['quantity']),
					'price' => number_format(floatval($clean_price), 2, '.', ''),
					'total_price' => number_format(floatval($clean_price) * intval($product['quantity']), 2, '.', ''),
					'sku' => get_post_meta($product['product_id'], '_sku', true) ?: '',
					'image_url' => wp_get_attachment_image_url(get_post_thumbnail_id($product['product_id']), 'full') ?: ''
				];
			}
		}

		// Generate cart token (simulate WooCommerce cart token)
		$cart_token = 'wc_cart_token_' . md5($cart['id'] . $cart['creation_time']);

		// Format cart data according to API specification
		$formatted_cart = [
			'store_url' => $cart['store_url'],
			'customer_id' => strval($cart['customer_id']),
			'cart_id' => 'wc_cart_' . $cart['id'],
			'cart_token' => $cart_token,
			'created_at' => date('c', strtotime($cart['creation_time'])), // ISO 8601 format
			'updated_at' => date('c', strtotime($cart['update_time'])), // ISO 8601 format
			'currency' => get_woocommerce_currency(),
			'total_price' => number_format(floatval($cart['cart_total']), 2, '.', ''),
			'total_discount' => '0.00', // Not tracked in current implementation
			'line_items' => $line_items,
			'customer_data' => [
				'customer_id' => intval($cart['customer_id']),
				'email' => $customer_data['email'] ?? '',
				'first_name' => $customer_data['first_name'] ?? '',
				'last_name' => $customer_data['last_name'] ?? '',
				'phone' => $customer_data['phone'] ?? ''
			],
			'billing_address' => [
				'first_name' => $customer_data['first_name'] ?? '',
				'last_name' => $customer_data['last_name'] ?? '',
				'company' => '',
				'address_1' => '',
				'address_2' => '',
				'city' => '',
				'state' => '',
				'postcode' => '',
				'country' => '',
				'email' => $customer_data['email'] ?? '',
				'phone' => $customer_data['phone'] ?? ''
			],
			'shipping_address' => [
				'first_name' => $customer_data['first_name'] ?? '',
				'last_name' => $customer_data['last_name'] ?? '',
				'company' => '',
				'address_1' => '',
				'address_2' => '',
				'city' => '',
				'state' => '',
				'postcode' => '',
				'country' => ''
			],
			'cart_recovery_url' => $cart['store_url'] . '/cart?recover=' . $cart_token,
			'cart_status' => $cart['cart_status'],
			'abandoned_at' => date('c', strtotime($cart['update_time'] . ' +15 minutes'))
		];

		$formatted_carts[] = $formatted_cart;
	}

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send each abandoned cart individually
	$successful_cart_ids = [];
	foreach ($formatted_carts as $formatted_cart) {
		// Send individual cart object (not array)
		$response = $api->post('abandoned_cart.create', $formatted_cart);

		// Track successful submissions
		if (!isset($response['error'])) {
			$successful_cart_ids[] = $formatted_cart['cart_id'];
			error_log('Successfully sent abandoned cart notification: ' . $formatted_cart['cart_id']);
		} else {
			// Log the error for debugging
			error_log('Mottasl API Error for cart ' . $formatted_cart['cart_id'] . ': ' . $response['error']);
		}
	}

	// Update notification_sent status only for successfully sent carts
	if (!empty($successful_cart_ids)) {
		// Extract numeric IDs from cart_id format (wc_cart_X)
		$numeric_ids = [];
		foreach ($successful_cart_ids as $cart_id) {
			if (preg_match('/wc_cart_(\d+)/', $cart_id, $matches)) {
				$numeric_ids[] = intval($matches[1]);
			}
		}

		if (!empty($numeric_ids)) {
			$id_placeholders = implode(',', array_fill(0, count($numeric_ids), '%d'));
			$sql = $wpdb->prepare(
				"UPDATE {$wpdb->prefix}cart_tracking_wc_cart
				 SET `notification_sent` = 1
				 WHERE `cart_status` = 'abandoned' AND `notification_sent` = 0 AND `id` IN ($id_placeholders)",
				...$numeric_ids
			);
			$wpdb->query($sql);
			error_log('Updated notification_sent status for ' . count($numeric_ids) . ' abandoned carts');
		}
	}
}

// Function to cleanup old carts (older than 30 days and completed/deleted)
function wtrackt_cleanup_old_carts()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'cart_tracking_wc_cart';
	$table_items = $wpdb->prefix . 'cart_tracking_wc';

	// Delete carts older than 30 days that are completed or deleted
	$deleted_carts = $wpdb->query(
		"DELETE FROM $table_name
		 WHERE `update_time` <= DATE_SUB(NOW(), INTERVAL 30 DAY)
		 AND `cart_status` IN ('completed', 'deleted')"
	);

	// Clean up orphaned cart items
	$deleted_items = $wpdb->query(
		"DELETE ci FROM $table_items ci
		 LEFT JOIN $table_name c ON ci.cart_number = c.id
		 WHERE c.id IS NULL"
	);

	if ($deleted_carts > 0 || $deleted_items > 0) {
		error_log("Mottasl cleanup: Removed $deleted_carts old carts and $deleted_items orphaned items");
	}
}
