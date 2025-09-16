<?php

defined('ABSPATH') || exit;

use Mottasl\Utils\Constants;
use Mottasl\Core\MottaslApi;


add_action(
	'woocommerce_add_to_cart',
	'wtrackt_add_to_cart',
	10,
	3
);
add_action(
	'woocommerce_update_cart_action_cart_updated',
	'wtrackt_update_cart_action_cart_updated',
	10,
	1
);
// // add_action('woocommerce_after_cart_item_quantity_update', 'wtrackt_cart_updated', 200, 0);
add_action(
	'woocommerce_remove_cart_item',
	'wtrackt_cart_item_removed',
	10,
	2
);
//add_action('woocommerce_before_cart_item_quantity_zero', 'wtrackt_item_quantity_zero');
add_action('woocommerce_cart_updated', 'wtrackt_cart_updated');
add_action('woocommerce_store_api_checkout_order_processed', 'wtrackt_new_order');
//add_action( 'woocommerce_cart_item_restored', '');
add_action(
	'wp_login',
	'wtrackt_user_login_update',
	10,
	2
);


function wtrackt_add_to_cart($cart_item_key, $product_id, $quantity)
{
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}

	$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->get_cart_contents_total();
	$customer_id = 0;

	if (is_user_logged_in()) {
		$customer_id = get_current_user_id();
	}

	// Don't create a new cart if the WooCommerce cart is empty (total = 0)
	if ($cart_total <= 0 || sizeof(WC()->cart->get_cart()) == 0) {
		error_log('Skipping empty cart creation during add to cart - cart total: ' . $cart_total . ', items: ' . sizeof(WC()->cart->get_cart()));
		return;
	}

	// Get or create cart using session-based tracking
	$cart_id = wtrackt_get_or_create_cart($customer_id, $cart_total);

	if (!$cart_id) {
		error_log('Failed to get or create cart for add to cart action');
		return;
	}

	// Update cart total and reset try_count for new attempts
	$wpdb->update(
		$table_cart_name,
		array(
			'update_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'customer_id' => $customer_id,
			'try_count' => 0, // Reset try_count to allow retry of notifications
			'notification_sent' => 0 // Reset notification flag
		),
		array('id' => $cart_id),
		array('%s', '%f', '%d', '%d', '%d'),
		array('%d')
	);

	// Handle product-specific logic for the cart
	$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND product_id = %d", $cart_id, $product_id);
	$results = $wpdb->get_results($sql, ARRAY_A);

	if (count($results) > 0) {
		// Product already exists in cart, update quantity
		$cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}mottasl_cart_tracking
				SET quantity = %d, removed = %d, time = %s
				WHERE product_id = %d AND cart_number = %d",
				$cart_quantity,
				false,
				current_time('mysql'),
				$product_id,
				$cart_id
			)
		);
		error_log('Updated product in cart: ' . $product_id . ' in cart: ' . $cart_id);
	} else {
		// Add new product to cart
		$wpdb->insert(
			$table_name,
			array(
				'time' => current_time('mysql'),
				'product_id' => $product_id,
				'quantity' => $quantity,
				'cart_number' => $cart_id,
				'removed' => false,
			)
		);
		error_log('Added new product to cart: ' . $product_id . ' in cart: ' . $cart_id);
	}

	// Store cart ID in session
	WC()->session->set('wtrackt_new_cart', $cart_id);

	// Send cart update notification to API
	wtrackt_send_cart_update_notification($cart_id, null);
}

function wtrackt_update_cart_action_cart_updated($cart_updated)
{
	if ($cart_updated) {
		global $wpdb;
		if (!isset(WC()->session)) {
			return;
		}

		$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';
		$cart_total = WC()->cart->get_cart_contents_total();
		$customer_id = 0;

		if (is_user_logged_in()) {
			$customer_id = get_current_user_id();
		}

		// Don't create a new cart if the WooCommerce cart is empty (total = 0)
		if ($cart_total <= 0 || sizeof(WC()->cart->get_cart()) == 0) {
			error_log('Skipping empty cart creation during cart update - cart total: ' . $cart_total . ', items: ' . sizeof(WC()->cart->get_cart()));
			return;
		}

		// Get or create cart using session-based tracking
		$cart_id = wtrackt_get_or_create_cart($customer_id, $cart_total);

		if (!$cart_id) {
			error_log('Failed to get or create cart for cart update action');
			return;
		}

		// Update cart data and reset try_count for new attempts
		$wpdb->update(
			$table_cart_name,
			array(
				'update_time' => current_time('mysql'),
				'cart_total' => $cart_total,
				'customer_id' => $customer_id,
				'notification_sent' => 0,
				'try_count' => 0, // Reset try_count to allow retry of notifications
			),
			array('id' => $cart_id),
			array('%s', '%f', '%d', '%d', '%d'),
			array('%d')
		);

		// Update individual cart items
		if (sizeof(WC()->cart->get_cart()) > 0) {
			foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
				$cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
				$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND product_id = %d", $cart_id, $values['product_id']);
				$results = $wpdb->get_results($sql, ARRAY_A);

				if (count($results) > 0) {
					// Update existing product
					$wpdb->query(
						$wpdb->prepare(
							"UPDATE {$wpdb->prefix}mottasl_cart_tracking
							SET quantity = %d, removed = %d, time = %s
							WHERE product_id = %d AND cart_number = %d",
							$cart_quantity,
							false,
							current_time('mysql'),
							$values['product_id'],
							$cart_id
						)
					);
				} else {
					// Add new product
					$wpdb->insert(
						$table_name,
						array(
							'time' => current_time('mysql'),
							'product_id' => $values['product_id'],
							'quantity' => $cart_quantity,
							'cart_number' => $cart_id,
							'removed' => false,
						)
					);
				}
			}
		}

		// Store cart ID in session
		WC()->session->set('wtrackt_new_cart', $cart_id);
		error_log('Cart updated via cart action: ' . $cart_id);

		// Send cart update notification to API
		wtrackt_send_cart_update_notification($cart_id, null);
	}
}

function wtrackt_cart_updated()
{
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}

	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->get_cart_contents_total();
	$customer_id = get_current_user_id();

	if ($cart_total == 0) {
		error_log('Skipping cart update for empty cart');
		return;
	}

	// Get existing cart using session-based tracking
	$cart_id = wtrackt_get_or_create_cart($customer_id, $cart_total);

	if (!$cart_id) {
		error_log('Failed to get or create cart for cart updated action');
		return;
	}

	// Get previous cart data to compare for actual changes
	$previous_cart = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $table_cart_name WHERE id = %d", $cart_id),
		ARRAY_A
	);

	if (!$previous_cart) {
		error_log('Cart not found in database: ' . $cart_id);
		return;
	}

	$customer_data = [
		'first_name' => WC()->cart->get_customer()->get_billing_first_name(),
		'last_name' => WC()->cart->get_customer()->get_billing_last_name(),
		'email' => WC()->cart->get_customer()->get_billing_email(),
		'customer_id' => $customer_id,
		'phone' => wtrackt_get_customer_phone($customer_id),
	];
	$products = [];

	foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
		$product = $cart_item['data'];
		$product_id = $cart_item['product_id'];
		$quantity = $cart_item['quantity'];
		$price_html = WC()->cart->get_product_price($product);
		$price_clean = wtrackt_extract_clean_price($price_html);
		$link = $product->get_permalink($cart_item);

		$products[] = [
			'product_id' => $product_id,
			'quantity' => $quantity,
			'price' => $price_clean,
			'price_html' => $price_html,
			'link' => $link,
		];
	}

	// Check if there are actual meaningful changes before updating
	$has_changes = false;

	// Check if cart total changed significantly (more than 0.01 difference)
	if (abs(floatval($previous_cart['cart_total']) - $cart_total) > 0.01) {
		$has_changes = true;
	}

	// Check if products changed (compare serialized data)
	$previous_products = json_decode($previous_cart['products'], true) ?: [];
	if (json_encode($products) !== json_encode($previous_products)) {
		$has_changes = true;
	}

	// Check if customer data changed
	$previous_customer_data = json_decode($previous_cart['customer_data'], true) ?: [];
	if (json_encode($customer_data) !== json_encode($previous_customer_data)) {
		$has_changes = true;
	}

	// Only update if there are actual changes
	if ($has_changes) {
		// Update cart data and reset try_count for new attempts
		$wpdb->update(
			$table_cart_name,
			array(
				'cart_total' => $cart_total,
				'customer_data' => json_encode($customer_data),
				'products' => json_encode($products),
				'store_url' => get_bloginfo('url'),
				'update_time' => current_time('mysql'),
				'notification_sent' => 0, // Reset notification flag for updates
				'try_count' => 0, // Reset try_count to allow retry of notifications
			),
			array('id' => $cart_id),
			array(
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%d',
			),
			array('%d')
		);

		error_log('Cart updated with meaningful changes: ' . $cart_id);

		// Send cart update notification to API
		wtrackt_send_cart_update_notification($cart_id, $previous_cart);
	} else {
		error_log('No meaningful changes detected for cart: ' . $cart_id);
	}
}

function wtrackt_cart_item_removed($cart_item_key, $cart)
{
	$product_id = $cart->cart_contents[$cart_item_key]['product_id'];
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}

	$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->total;
	$customer_id = 0;

	if (is_user_logged_in()) {
		$customer_id = get_current_user_id();
	}

	// Check if cart becomes empty after removal
	if ($cart_total <= 0 || sizeof(WC()->cart->get_cart()) == 0) {
		error_log('Cart is empty after item removal - cart total: ' . $cart_total . ', items: ' . sizeof(WC()->cart->get_cart()));

		// Get current cart if exists
		$session_id = wtrackt_get_session_id();
		if ($session_id) {
			$existing_cart_id = wtrackt_find_cart_by_session($session_id, $customer_id);
			if ($existing_cart_id) {
				// Send deletion notification and mark as deleted
				wtrackt_send_cart_deletion_notification($existing_cart_id);
				$wpdb->update(
					$table_cart_name,
					array('cart_status' => 'deleted', 'update_time' => current_time('mysql')),
					array('id' => $existing_cart_id),
					array('%s', '%s'),
					array('%d')
				);
				error_log('Marked cart as deleted due to empty cart: ' . $existing_cart_id);

				// Clear session
				WC()->session->__unset('wtrackt_new_cart');
			}
		}
		return;
	}

	// Get or create cart using session-based tracking
	$cart_id = wtrackt_get_or_create_cart($customer_id, $cart_total);

	if (!$cart_id) {
		error_log('Failed to get or create cart for item removal');
		return;
	}

	// Mark the specific product as removed
	$removed = true;
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}mottasl_cart_tracking
			SET removed = %d, time = %s
			WHERE product_id = %d AND cart_number = %d",
			$removed,
			current_time('mysql'),
			$product_id,
			$cart_id
		)
	);

	// Update cart total and reset try_count for new attempts
	$wpdb->update(
		$table_cart_name,
		array(
			'update_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'try_count' => 0, // Reset try_count to allow retry of notifications
			'notification_sent' => 0 // Reset notification flag
		),
		array('id' => $cart_id),
		array('%s', '%f', '%d', '%d'),
		array('%d')
	);

	// Check if all items are now removed (cart is empty)
	$remaining_items = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND removed = 0",
			$cart_id
		)
	);

	if ($remaining_items == 0) {
		// Send deletion notification before marking as deleted
		wtrackt_send_cart_deletion_notification($cart_id);

		// Mark cart as deleted
		$wpdb->update(
			$table_cart_name,
			array('cart_status' => 'deleted', 'update_time' => current_time('mysql')),
			array('id' => $cart_id),
			array('%s', '%s'),
			array('%d')
		);
		error_log('Cart marked as deleted due to all items being removed: ' . $cart_id);

		// Clear session
		WC()->session->__unset('wtrackt_new_cart');
	} else {
		error_log('Product removed from cart: ' . $product_id . ' in cart: ' . $cart_id . ' (' . $remaining_items . ' items remaining)');

		// Send cart update notification to API
		wtrackt_send_cart_update_notification($cart_id, null);
	}
}
function format_order_items($order_id)
{
	$formatted_items = [];

	// Get the order object
	$order = wc_get_order($order_id);
	$data = $order->get_data(); // order data

	// Ensure the order object is valid
	if (!$order) {
		return $formatted_items;
	}
	unset($data['line_items']);
	$payment_url = $order->get_checkout_payment_url(true);
	$formatted_items = [...$data, 'payment_url' => $payment_url];


	// Iterate over each item in the order
	foreach ($order->get_items() as $item_key => $item) {

		// Retrieve item data
		$item_id = $item->get_id();
		$product = $item->get_product(); // Get the WC_Product object
		$product_id = $item->get_product_id();
		$variation_id = $item->get_variation_id();
		$item_name = $item->get_name();
		$quantity = $item->get_quantity();
		$tax_class = $item->get_tax_class();
		$line_subtotal = $item->get_subtotal();
		$line_subtotal_tax = $item->get_subtotal_tax();
		$line_total = $item->get_total();
		$line_total_tax = $item->get_total_tax();
		$product_sku = $product->get_sku();
		$product_price = $product->get_price();
		$product_image_id = $product->get_image_id();
		$product_image_src = wp_get_attachment_image_src($product_image_id, 'full');

		// Build the item array
		$formatted_items['line_items'][] = [
			'id' => $item_id,
			'name' => $item_name,
			'product_id' => $product_id,
			'variation_id' => $variation_id,
			'quantity' => $quantity,
			'tax_class' => $tax_class,
			'subtotal' => $line_subtotal,
			'subtotal_tax' => $line_subtotal_tax,
			'total' => $line_total,
			'total_tax' => $line_total_tax,
			'taxes' => [],
			'meta_data' => [],
			'sku' => $product_sku,
			'price' => $product_price,
			'image' => [
				'id' => $product_image_id,
				'src' => $product_image_src[0] // Get the URL of the product image
			],
			'parent_name' => null
		];
	}

	// Return the formatted items array
	return $formatted_items;
}

function wtrackt_new_order($order_id)
{
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}
	$new_cart = WC()->session->get('wtrackt_new_cart');
	$table_name = $wpdb->prefix . 'mottasl_cart';

	if (!is_null($new_cart)) {
		$order = wc_get_order($order_id);
		$order_data = format_order_items($order_id);
		$order_data_id = $order_data['id'];

		// Get cart details before updating
		$cart_details = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $new_cart),
			ARRAY_A
		);

		$wpdb->update(
			$table_name,
			array(
				'cart_status' => 'completed', // Update cart status to 'completed'
				'order_created' => true, // Flag that order was created from this cart
			),
			array(
				'id' => $new_cart,
			),
			array(
				'%s', // Data format for 'cart_status'
				'%d' // Data format for 'order_created' (boolean as int)
			),
			array(
				'%d' // Format for the WHERE clause
			)
		);

		// Initialize the MottaslApi
		$api = new MottaslApi();

		// Send order created event
		$order_response = $api->post('order.created?store_url=' . get_bloginfo('url'), $order_data);

		// Send cart completion event with new payload structure
		if ($cart_details) {
			$customer_data = json_decode($cart_details['customer_data'], true) ?: [];
			$products = json_decode($cart_details['products'], true) ?: [];

			// Convert products to line_items format
			$line_items = [];
			if (is_array($products)) {
				foreach ($products as $index => $product) {
					$clean_price = $product['price'];
					if (strpos($clean_price, '<span') !== false) {
						preg_match('/(\d+[,.]?\d*)/', $clean_price, $matches);
						$clean_price = isset($matches[1]) ? str_replace(',', '.', $matches[1]) : '0.00';
					}

					$line_items[] = [
						'id' => ($index + 1) * 1000 + intval($cart_details['id']),
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

			// Prepare cart completion data
			$cart_completion_data = [
				'store_url' => $cart_details['store_url'],
				'customer_id' => strval($cart_details['customer_id']),
				'cart_id' => 'wc_cart_' . $cart_details['id'],
				'cart_token' => 'wc_cart_token_' . md5($cart_details['id'] . $cart_details['creation_time']),
				'created_at' => date('c', strtotime($cart_details['creation_time'])),
				'updated_at' => date('c', strtotime($cart_details['update_time'])),
				'currency' => get_woocommerce_currency(),
				'total_price' => number_format(floatval($cart_details['cart_total']), 2, '.', ''),
				'total_discount' => '0.00',
				'line_items' => $line_items,
				'cart_status' => 'completed',
				'order_id' => $order_data_id,
				'event_name' => 'cart.completed',
				'customer_data' => [
					'customer_id' => intval($cart_details['customer_id']),
					'email' => $customer_data['email'] ?? '',
					'first_name' => $customer_data['first_name'] ?? '',
					'last_name' => $customer_data['last_name'] ?? '',
					'phone' => $customer_data['phone'] ?: wtrackt_get_customer_phone($cart_details['customer_id'])
				]
			];

			// Send cart completion event
			$cart_response = $api->post('/cart/completed', $cart_completion_data);

			if (!isset($cart_response['error'])) {
				error_log('Successfully sent cart completion event for cart: wc_cart_' . $cart_details['id']);
			} else {
				error_log('Mottasl API Error for cart completion: ' . $cart_response['error']);
			}
		}
		$products = json_decode($cart_details['products'], true) ?: [];

		// Generate cart token
		$cart_token = 'wc_cart_token_' . md5($cart_details['id'] . $cart_details['creation_time']);

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
					'id' => ($index + 1) * 1000 + intval($cart_details['id']),
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

		// Format cart data according to GoLang AbandonedCart struct
		$formatted_cart = [
			// Primary fields matching the GoLang struct
			'store_url' => $cart_details['store_url'],
			'customer_id' => strval($cart_details['customer_id']),
			'cart_id' => 'wc_cart_' . $cart_details['id'],
			'cart_token' => $cart_token,
			'created_at' => date('c', strtotime($cart_details['creation_time'] ?: $cart_details['update_time'])),
			'updated_at' => date('c', strtotime($cart_details['update_time'])),
			'currency' => get_woocommerce_currency(),
			'total_price' => number_format(floatval($cart_details['cart_total']), 2, '.', ''),
			'total_discount' => '0.00',
			'line_items' => $line_items,
			'customer_data' => [
				'customer_id' => intval($cart_details['customer_id']),
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
			'cart_recovery_url' => $cart_details['store_url'] . '/cart?recover=' . $cart_token,
			'cart_status' => 'completed',
			'abandoned_at' => '',

			// Legacy fields for backward compatibility
			'id' => strval($cart_details['id'])
		];

		// Send cart completion event
		$cart_response = $api->post('/cart/completed', $cart_completion_data);

		if (!isset($cart_response['error'])) {
			error_log('Successfully sent cart completion event for cart: wc_cart_' . $cart_details['id']);
		} else {
			error_log('Mottasl API Error for cart completion: ' . $cart_response['error']);
		}
	}

	WC()->session->__unset('wtrackt_new_cart');
}

// Function to send cart creation notification
function wtrackt_send_cart_creation_notification($cart_id)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mottasl_cart';

	// Get cart data
	$cart = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id),
		ARRAY_A
	);

	if (!$cart) {
		return;
	}

	// Check if enough time has passed since creation/last update (using configured duration)
	$time_diff = time() - strtotime($cart['update_time']);
	if ($time_diff < (Constants::ABANDONED_CART_TIMEOUT * 60)) { // Convert minutes to seconds
		error_log('Cart creation notification skipped - not enough time passed for cart: ' . $cart_id);
		return;
	}

	$customer_data = json_decode($cart['customer_data'], true) ?: [];
	$products = json_decode($cart['products'], true) ?: [];

	// Generate cart token
	$cart_token = 'wc_cart_token_' . md5($cart['id'] . $cart['creation_time']);

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

	// Format cart data with new event structure
	$cart_data = [
		// Primary fields matching the GoLang struct
		'store_url' => get_bloginfo('url'),
		'customer_id' => strval($cart['customer_id']),
		'cart_id' => 'wc_cart_' . $cart['id'],
		'cart_token' => $cart_token,
		'created_at' => date('c', strtotime($cart['creation_time'] ?: $cart['update_time'])),
		'updated_at' => date('c', strtotime($cart['update_time'])),
		'currency' => get_woocommerce_currency(),
		'total_price' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'total_discount' => '0.00',
		'line_items' => $line_items,
		'cart_status' => $cart['cart_status'],
		'event_name' => 'cart.created',
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
		'cart_recovery_url' => get_bloginfo('url') . '/cart?recover=' . $cart_token
	];

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send cart creation notification with new endpoint
	$response = $api->post('/cart/created', $cart_data);

	if (!isset($response['error'])) {
		error_log('Successfully sent cart creation notification for cart: ' . $cart_id);

		// Mark notification as sent
		$wpdb->update(
			$table_name,
			array('notification_sent' => 1),
			array('id' => $cart_id),
			array('%d'),
			array('%d')
		);
	} else {
		error_log('Mottasl API Error for cart creation ' . $cart_id . ': ' . $response['error']);
	}
}

// Function to send cart update notification
function wtrackt_send_cart_update_notification($cart_id, $previous_cart)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mottasl_cart';

	// Get updated cart data
	$cart = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id),
		ARRAY_A
	);

	if (!$cart) {
		return;
	}

	// Cart update notifications should be sent immediately, not delayed
	// The time check is only for cart creation notifications (abandoned cart detection)

	$customer_data = json_decode($cart['customer_data'], true) ?: [];
	$products = json_decode($cart['products'], true) ?: [];

	// Generate cart token
	$cart_token = 'wc_cart_token_' . md5($cart['id'] . $cart['creation_time']);

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

	// Format cart data with new event structure
	$cart_data = [
		// Primary fields matching the GoLang struct
		'store_url' => $cart['store_url'],
		'customer_id' => strval($cart['customer_id']),
		'cart_id' => 'wc_cart_' . $cart['id'],
		'cart_token' => $cart_token,
		'created_at' => date('c', strtotime($cart['creation_time'] ?: $cart['update_time'])),
		'updated_at' => date('c', strtotime($cart['update_time'])),
		'currency' => get_woocommerce_currency(),
		'total_price' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'total_discount' => '0.00',
		'line_items' => $line_items,
		'cart_status' => 'updated',
		'event_name' => 'cart.updated',
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
		'abandoned_at' => date('c', strtotime($cart['creation_time']) + Constants::ABANDONED_CART_TIMEOUT * MINUTE_IN_SECONDS),

		// Legacy fields for backward compatibility
		'id' => strval($cart['id']),
		'creation_time' => $cart['creation_time'] ?: $cart['update_time'],
		'update_time' => $cart['update_time'],
		'cart_total' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'order_created' => $cart['order_created'] ?? '',
		'notification_sent' => strval($cart['notification_sent'] ?? '0'),
		'ip_address' => $cart['ip_address'] ?? null,
		'products' => $products,
		'previous_status' => $previous_cart ? $previous_cart['cart_status'] : $cart['cart_status']
	];

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send cart update notification with new endpoint
	$response = $api->post('/cart/updated', $cart_data);

	if (!isset($response['error'])) {
		error_log('Successfully sent cart update notification for cart: ' . $cart_id);

		// Mark notification as sent
		$wpdb->update(
			$table_name,
			array('notification_sent' => 1),
			array('id' => $cart_id),
			array('%d'),
			array('%d')
		);
	} else {
		error_log('Mottasl API Error for cart update ' . $cart_id . ': ' . $response['error']);
	}
}

// Function to send cart deletion notification
function wtrackt_send_cart_deletion_notification($cart_id)
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'mottasl_cart';

	// Get cart data before deletion
	$cart = $wpdb->get_row(
		$wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $cart_id),
		ARRAY_A
	);

	if (!$cart) {
		return;
	}

	$customer_data = json_decode($cart['customer_data'], true) ?: [];
	$products = json_decode($cart['products'], true) ?: [];

	// Generate cart token
	$cart_token = 'wc_cart_token_' . md5($cart['id'] . $cart['creation_time']);

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

	// Format cart data with new event structure
	$cart_data = [
		// Primary fields matching the GoLang struct
		'store_url' => $cart['store_url'],
		'customer_id' => strval($cart['customer_id']),
		'cart_id' => 'wc_cart_' . $cart['id'],
		'cart_token' => $cart_token,
		'created_at' => date('c', strtotime($cart['creation_time'] ?: $cart['update_time'])),
		'updated_at' => date('c', strtotime($cart['update_time'])),
		'currency' => get_woocommerce_currency(),
		'total_price' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'total_discount' => '0.00',
		'line_items' => $line_items,
		'cart_status' => 'deleted',
		'event_name' => 'cart.deleted',
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
		'abandoned_at' => date('c', strtotime($cart['creation_time']) + Constants::ABANDONED_CART_TIMEOUT * MINUTE_IN_SECONDS),

		// Legacy fields for backward compatibility
		'id' => strval($cart['id']),
		'creation_time' => $cart['creation_time'] ?: $cart['update_time'],
		'update_time' => $cart['update_time'],
		'cart_total' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'order_created' => $cart['order_created'] ?? '',
		'notification_sent' => strval($cart['notification_sent'] ?? '0'),
		'ip_address' => $cart['ip_address'] ?? null,
		'products' => $products,
		'previous_status' => $cart['cart_status'],
		'deleted_at' => date('c')
	];

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send cart deletion notification with new endpoint
	$response = $api->post('/cart/deleted', $cart_data);

	if (!isset($response['error'])) {
		error_log('Successfully sent cart deletion notification for cart: ' . $cart_id);
	} else {
		error_log('Mottasl API Error for cart deletion ' . $cart_id . ': ' . $response['error']);
	}
}

// Add hook to detect when cart becomes empty (deleted)
add_action('woocommerce_cart_emptied', 'wtrackt_cart_emptied');

function wtrackt_cart_emptied()
{
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}

	$session_id = wtrackt_get_session_id();
	$customer_id = is_user_logged_in() ? get_current_user_id() : 0;

	if ($session_id) {
		// Find current cart by session ID
		$cart_id = wtrackt_find_cart_by_session($session_id, $customer_id);

		if ($cart_id) {
			// Send deletion notification before removing the cart
			wtrackt_send_cart_deletion_notification($cart_id);

			// Mark cart as deleted
			$table_name = $wpdb->prefix . 'mottasl_cart';
			$wpdb->update(
				$table_name,
				array('cart_status' => 'deleted', 'update_time' => current_time('mysql')),
				array('id' => $cart_id),
				array('%s', '%s'),
				array('%d')
			);

			error_log('Cart marked as deleted due to cart emptied: ' . $cart_id);
		}
	}

	// Clear session
	WC()->session->__unset('wtrackt_new_cart');
}

function wtrackt_user_login_update($user_login, $user)
{
	if (!isset(WC()->session)) {
		return;
	}

	global $wpdb;
	$table_name = $wpdb->prefix . 'mottasl_cart';
	$session_id = wtrackt_get_session_id();

	if ($session_id) {
		// Find current cart by session ID
		$cart_id = wtrackt_find_cart_by_session($session_id, 0); // Don't pass customer_id to avoid recursion

		if ($cart_id) {
			// Update the cart with logged-in user ID
			$wpdb->update(
				$table_name,
				array(
					'customer_id' => $user->ID,
					'update_time' => current_time('mysql')
				),
				array('id' => $cart_id),
				array('%d', '%s'),
				array('%d')
			);

			// Store cart ID in session
			WC()->session->set('wtrackt_new_cart', $cart_id);
			error_log('Updated cart customer_id after login: ' . $cart_id . ' for user: ' . $user->ID);
		}
	}
}

// Helper function to get WooCommerce session ID for cart tracking
if (!function_exists('wtrackt_get_session_id')) {
	function wtrackt_get_session_id()
	{
		if (!isset(WC()->session)) {
			return null;
		}

		// Get WooCommerce session customer ID (this is unique per session)
		$session_key = WC()->session->get_customer_id();

		if (empty($session_key)) {
			// If no session key exists, generate one
			WC()->session->set_customer_session_cookie(true);
			$session_key = WC()->session->get_customer_id();
		}

		return $session_key;
	}
}

// Helper function to find existing cart by session ID
if (!function_exists('wtrackt_find_cart_by_session')) {
	function wtrackt_find_cart_by_session($session_id, $customer_id = 0)
	{
		global $wpdb;
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';

		if (empty($session_id)) {
			return null;
		}

		// First try to find by session_id
		$existing_cart = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM $table_cart_name
				 WHERE session_id = %s
				 AND cart_status IN ('new', 'abandoned')
				 ORDER BY update_time DESC LIMIT 1",
				$session_id
			),
			ARRAY_A
		);

		if ($existing_cart) {
			return intval($existing_cart['id']);
		}

		// If no session cart found and user is logged in, check for recent cart by customer_id
		if ($customer_id > 0) {
			$recent_cart = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM $table_cart_name
					 WHERE customer_id = %d
					 AND cart_status IN ('new', 'abandoned')
					 AND update_time >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
					 ORDER BY update_time DESC LIMIT 1",
					$customer_id
				),
				ARRAY_A
			);

			if ($recent_cart) {
				// Update the cart with current session_id
				$wpdb->update(
					$table_cart_name,
					array('session_id' => $session_id),
					array('id' => $recent_cart['id']),
					array('%s'),
					array('%d')
				);
				error_log('Updated existing customer cart with session ID: ' . $recent_cart['id']);
				return intval($recent_cart['id']);
			}
		}

		return null;
	}
}

// Helper function to validate and get existing cart or create new one
if (!function_exists('wtrackt_get_or_create_cart')) {
	function wtrackt_get_or_create_cart($customer_id = 0, $cart_total = 0.0)
	{
		global $wpdb;
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';

		if (!isset(WC()->session)) {
			return null;
		}

		$session_id = wtrackt_get_session_id();
		if (empty($session_id)) {
			error_log('Failed to get session ID for cart tracking');
			return null;
		}

		// First check if we have a cart in session storage
		$new_cart = WC()->session->get('wtrackt_new_cart');

		// Validate cart exists in database and belongs to current session
		if (!is_null($new_cart)) {
			$existing_cart = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id, session_id FROM $table_cart_name
					 WHERE id = %d AND session_id = %s",
					$new_cart,
					$session_id
				),
				ARRAY_A
			);

			if ($existing_cart) {
				return $new_cart; // Cart is valid
			} else {
				// Cart in session doesn't match database, clear it
				WC()->session->__unset('wtrackt_new_cart');
				error_log('Cart session mismatch detected, clearing session cart: ' . $new_cart);
			}
		}

		// Look for existing cart by session ID
		$existing_cart_id = wtrackt_find_cart_by_session($session_id, $customer_id);
		if ($existing_cart_id) {
			WC()->session->set('wtrackt_new_cart', $existing_cart_id);
			error_log('Found existing cart by session: ' . $existing_cart_id);
			return $existing_cart_id;
		}

		// No existing cart found, create new one
		return wtrackt_create_new_cart($customer_id, $cart_total, $session_id);
	}
}

// Helper function to create a new cart record
if (!function_exists('wtrackt_create_new_cart')) {
	function wtrackt_create_new_cart($customer_id = 0, $cart_total = 0.0, $session_id = null)
	{
		global $wpdb;
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';
		$table_name = $wpdb->prefix . 'mottasl_cart_tracking';

		if (empty($session_id)) {
			$session_id = wtrackt_get_session_id();
		}

		if (empty($session_id)) {
			error_log('Cannot create cart without session ID');
			return null;
		}

		$carts_insert = array(
			'update_time' => current_time('mysql'),
			'creation_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'customer_id' => $customer_id,
			'session_id' => $session_id,
			'cart_status' => 'new',
			'notification_sent' => 0,
			'try_count' => 0,
		);

		if (!is_user_logged_in()) {
			if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
			} else {
				$ip_address = $_SERVER['REMOTE_ADDR'];
			}
			$carts_insert['ip_address'] = $ip_address;
		}

		$wpdb->insert($table_cart_name, $carts_insert);
		$last_cart_number = $wpdb->insert_id;

		if ($last_cart_number) {
			// Add current cart items to the tracking table
			if (isset(WC()->cart) && sizeof(WC()->cart->get_cart()) > 0) {
				foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
					$wpdb->insert(
						$table_name,
						array(
							'time' => current_time('mysql'),
							'product_id' => $values['product_id'],
							'quantity' => $values['quantity'],
							'cart_number' => $last_cart_number,
							'removed' => false,
						)
					);
				}
			}

			WC()->session->set('wtrackt_new_cart', $last_cart_number);
			error_log('New cart created with session ID (' . $session_id . '): ' . $last_cart_number);
		}

		return $last_cart_number;
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

		// If still empty and WooCommerce customer is available, get from current session
		if (empty($phone) && WC()->cart && WC()->cart->get_customer()) {
			$customer = WC()->cart->get_customer();
			$phone = $customer->get_billing_phone();

			// Try shipping phone if billing phone is empty
			if (empty($phone)) {
				$phone = $customer->get_shipping_phone();
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

// Helper function to extract clean price from HTML formatted price
if (!function_exists('wtrackt_extract_clean_price')) {
	function wtrackt_extract_clean_price($html_price)
	{
		// If it's already a clean number, return it
		if (is_numeric($html_price)) {
			return number_format(floatval($html_price), 2, '.', '');
		}

		// Remove HTML tags first
		$clean_price = strip_tags($html_price);

		// Extract numbers and decimal/comma separators
		// This regex will match patterns like: 19,99 or 19.99 or 1999 or 19 99
		if (preg_match('/(\d+(?:[,.]?\d*)?)/', $clean_price, $matches)) {
			$price = $matches[1];
			// Replace comma with dot for decimal separator
			$price = str_replace(',', '.', $price);
			return number_format(floatval($price), 2, '.', '');
		}

		return '0.00';
	}
}
