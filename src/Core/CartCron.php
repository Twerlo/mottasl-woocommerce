<?php

use Mottasl\Utils\Constants;
use Mottasl\Core\MottaslApi;

add_filter('cron_schedules', function ($schedules) {
	$schedules['every-minute'] = array(
		'interval' => 60,
		'display' => __('Every minute')
	);
	$schedules['every-15-minutes'] = array(
		'interval' => 15 * 60,
		'display' => __('Every 15 minutes')
	);
	return $schedules;
});

if (!wp_next_scheduled('my_function_hook')) {
	wp_schedule_event(time(), 'every-minute', 'my_function_hook');
}
add_action('my_function_hook', 'my_function');

// Add cron job for cart updates that need notification after the configured duration
if (!wp_next_scheduled('wtrackt_cart_updates_hook')) {
	wp_schedule_event(time(), 'every-minute', 'wtrackt_cart_updates_hook');
}
add_action('wtrackt_cart_updates_hook', 'wtrackt_process_cart_updates');

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

	// Define the time limit in minutes from constants
	$time_limit = Constants::CART_ABANDONED_DURATION;

	// SQL to update cart_status to 'abandoned' (no placeholders needed for this query)
	$sql = "UPDATE $table_name
         SET `cart_status` = 'abandoned'
        WHERE `update_time` <= DATE_SUB(NOW(), INTERVAL $time_limit MINUTE) AND `cart_status` = 'new'";

	// Execute the SQL
	$wpdb->query($sql);

	// Only get carts that are abandoned, haven't been notified yet, and are older than the configured duration
	$abandoned_carts = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_name
    WHERE `cart_status` = %s
    AND `notification_sent` = 0
    AND `update_time` <= DATE_SUB(NOW(), INTERVAL " . Constants::CART_ABANDONED_DURATION . " MINUTE)", 'abandoned'), ARRAY_A);

	// Also get new carts that haven't been notified and are older than the configured duration
	$new_carts = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM $table_name
    WHERE `cart_status` = %s
    AND `notification_sent` = 0
    AND `update_time` <= DATE_SUB(NOW(), INTERVAL " . Constants::CART_ABANDONED_DURATION . " MINUTE)", 'new'), ARRAY_A);

	// Combine both arrays
	$carts = array_merge($abandoned_carts, $new_carts);

	// Debug logging
	error_log('Found ' . count($abandoned_carts) . ' abandoned carts and ' . count($new_carts) . ' new carts to process');
	if (!empty($carts)) {
		$cart_ids = array_column($carts, 'id');
		error_log('Cart IDs to process: ' . implode(', ', $cart_ids));
	}

	if (empty($carts)) {
		error_log('No carts to process (new or abandoned)');
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

		// Format cart data according to GoLang AbandonedCart struct specification
		$formatted_cart = [
			// Primary fields matching the GoLang struct
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
				'phone' => $customer_data['phone'] ?: wtrackt_get_customer_phone($cart['customer_id'])
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
				'phone' => $customer_data['phone'] ?: wtrackt_get_customer_phone($cart['customer_id'])
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
			'abandoned_at' => date('c', strtotime($cart['update_time'] . ' +5 minutes')),

			// Legacy fields for backward compatibility (with omitempty behavior)
			'id' => strval($cart['id']),
			'creation_time' => $cart['creation_time'],
			'update_time' => $cart['update_time'],
			'cart_total' => number_format(floatval($cart['cart_total']), 2, '.', ''),
			'order_created' => $cart['order_created'] ?? '',
			'notification_sent' => strval($cart['notification_sent']),
			'ip_address' => $cart['ip_address'] ?? null,
			'products' => $products // Include original products array for legacy compatibility
		];

		$formatted_carts[] = $formatted_cart;
	}

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send each cart individually with appropriate endpoint
	$successful_cart_ids = [];
	foreach ($formatted_carts as $formatted_cart) {
		// Determine the appropriate API endpoint based on cart status
		$endpoint = 'abandoned_cart.create';
		$log_message = 'cart notification';

		if ($formatted_cart['cart_status'] === 'new') {
			$endpoint = 'abandoned_cart.create';
			$log_message = 'new cart notification';
		} elseif ($formatted_cart['cart_status'] === 'abandoned') {
			$endpoint = 'abandoned_cart.create'; // Still use create for abandoned carts
			$log_message = 'abandoned cart notification';
		}

		// Send individual cart object (not array)
		$response = $api->post($endpoint, $formatted_cart);

		// Track successful submissions
		if (!isset($response['error'])) {
			$successful_cart_ids[] = $formatted_cart['cart_id'];
			error_log('Successfully sent ' . $log_message . ': ' . $formatted_cart['cart_id']);
		} else {
			// Log the error for debugging
			error_log('Mottasl API Error for ' . $log_message . ' ' . $formatted_cart['cart_id'] . ': ' . $response['error']);
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
				 WHERE `cart_status` IN ('new', 'abandoned') AND `notification_sent` = 0 AND `id` IN ($id_placeholders)",
				...$numeric_ids
			);
			$wpdb->query($sql);
			error_log('Updated notification_sent status for ' . count($numeric_ids) . ' carts (new and abandoned)');
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

// Function to process cart updates that need notification
function wtrackt_process_cart_updates()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'cart_tracking_wc_cart';

	// Get carts that have been updated and need notification after the configured duration
	// This includes carts that were updated from abandoned status back to new
	$updated_carts = $wpdb->get_results("
		SELECT c1.* FROM $table_name c1
		WHERE c1.notification_sent = 0
		AND c1.cart_status = 'new'
		AND c1.update_time <= DATE_SUB(NOW(), INTERVAL " . Constants::CART_ABANDONED_DURATION . " MINUTE)
		AND EXISTS (
			SELECT 1 FROM $table_name c2
			WHERE c2.id = c1.id
			AND c2.creation_time < c1.update_time
		)", ARRAY_A);

	if (empty($updated_carts)) {
		error_log('No cart updates to process');
		return;
	}

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Process each updated cart
	$successful_cart_ids = [];
	foreach ($updated_carts as $cart) {
		$customer_data = json_decode($cart['customer_data'], true) ?: [];
		$products = json_decode($cart['products'], true) ?: [];

		// Convert products to line_items format
		$line_items = [];
		if (is_array($products)) {
			foreach ($products as $index => $product) {
				// Extract clean price (remove HTML)
				$clean_price = $product['price'];
				if (strpos($clean_price, '<span') !== false) {
					preg_match('/(\d+[,.]?\d*)/', $clean_price, $matches);
					$clean_price = isset($matches[1]) ? str_replace(',', '.', $matches[1]) : '0.00';
				}

				$line_items[] = [
					'id' => ($index + 1) * 1000 + intval($cart['id']),
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

		// Generate cart token
		$cart_token = 'wc_cart_token_' . md5($cart['id'] . $cart['creation_time']);

		// Format cart data according to GoLang AbandonedCart struct
		$formatted_cart = [
			// Primary fields matching the GoLang struct
			'store_url' => $cart['store_url'],
			'customer_id' => strval($cart['customer_id']),
			'cart_id' => 'wc_cart_' . $cart['id'],
			'cart_token' => $cart_token,
			'created_at' => date('c', strtotime($cart['creation_time'])),
			'updated_at' => date('c', strtotime($cart['update_time'])),
			'currency' => get_woocommerce_currency(),
			'total_price' => number_format(floatval($cart['cart_total']), 2, '.', ''),
			'total_discount' => '0.00',
			'line_items' => $line_items,
			'customer_data' => [
				'customer_id' => intval($cart['customer_id']),
				'email' => $customer_data['email'] ?? '',
				'first_name' => $customer_data['first_name'] ?? '',
				'last_name' => $customer_data['last_name'] ?? '',
				'phone' => $customer_data['phone'] ?: wtrackt_get_customer_phone($cart['customer_id'])
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
				'phone' => $customer_data['phone'] ?: wtrackt_get_customer_phone($cart['customer_id'])
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
			'cart_status' => 'updated',
			'abandoned_at' => '',

			// Legacy fields for backward compatibility
			'id' => strval($cart['id']),
			'creation_time' => $cart['creation_time'],
			'update_time' => $cart['update_time'],
			'cart_total' => number_format(floatval($cart['cart_total']), 2, '.', ''),
			'order_created' => $cart['order_created'] ?? '',
			'notification_sent' => strval($cart['notification_sent']),
			'ip_address' => $cart['ip_address'] ?? null,
			'products' => $products
		];

		// Send cart update notification
		$response = $api->post('abandoned_cart.update', $formatted_cart);

		if (!isset($response['error'])) {
			$successful_cart_ids[] = intval($cart['id']);
			error_log('Successfully sent cart update notification: wc_cart_' . $cart['id']);
		} else {
			error_log('Mottasl API Error for cart update wc_cart_' . $cart['id'] . ': ' . $response['error']);
		}
	}

	// Update notification_sent status for successfully sent carts
	if (!empty($successful_cart_ids)) {
		$id_placeholders = implode(',', array_fill(0, count($successful_cart_ids), '%d'));
		$sql = $wpdb->prepare(
			"UPDATE {$wpdb->prefix}cart_tracking_wc_cart
			 SET `notification_sent` = 1
			 WHERE `id` IN ($id_placeholders)",
			...$successful_cart_ids
		);
		$wpdb->query($sql);
		error_log('Updated notification_sent status for ' . count($successful_cart_ids) . ' updated carts');
	}
}

// Helper function to get customer phone number from multiple sources
if (!function_exists('wtrackt_get_customer_phone')) {
	function wtrackt_get_customer_phone($customer_id = 0)
	{
		$phone = '';

		if ($customer_id > 0) {
			// Try to get phone from user meta (custom fields)
			$phone_code = get_user_meta($customer_id, 'phone_code', true);
			$phone_number = get_user_meta($customer_id, 'phone_number', true);

			if (!empty($phone_code) && !empty($phone_number)) {
				$phone = $phone_code . $phone_number;
			}

			// If still empty, try billing phone from user meta
			if (empty($phone)) {
				$phone = get_user_meta($customer_id, 'billing_phone', true);
			}

			// If still empty, try shipping phone from user meta
			if (empty($phone)) {
				$phone = get_user_meta($customer_id, 'shipping_phone', true);
			}
		}

		// Clean and format phone number
		if (!empty($phone)) {
			// Remove any HTML tags or special characters
			$phone = strip_tags($phone);
			$phone = preg_replace('/[^0-9+\-\s\(\)]/', '', $phone);
			$phone = trim($phone);
		}

		return $phone ?: '';
	}
}
