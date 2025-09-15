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

	$new_cart = WC()->session->get('wtrackt_new_cart');
	$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->get_cart_contents_total();
	$customer_id = 0;

	if (is_user_logged_in()) {
		$customer_id = get_current_user_id();
	} else {
		if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip_address = $_SERVER['REMOTE_ADDR'];
		}
	}

	if (is_null($new_cart)) {
		// Create new cart
		$carts_insert = array(
			'update_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'customer_id' => $customer_id,
			'cart_status' => 'new',
			'notification_sent' => 0,
			'try_count' => 0,
		);
		if (!is_user_logged_in()) {
			$carts_insert['ip_address'] = $ip_address;
		}
		$wpdb->insert($table_cart_name, $carts_insert);
		$last_cart_number = $wpdb->insert_id;

		if ($last_cart_number) {
			if (sizeof(WC()->cart->get_cart()) > 0) {
				foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
					$_product = $values['data'];
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

			// Cart creation notification will be handled by cron job after 15 minutes
			error_log('New cart created, will be processed by cron job: ' . $last_cart_number);
		}
	} else {
		// Update existing cart - first verify cart exists in database
		$existing_cart = $wpdb->get_row(
			$wpdb->prepare("SELECT id FROM $table_cart_name WHERE id = %d", $new_cart),
			ARRAY_A
		);

		if (!$existing_cart) {
			// Cart session exists but database record is missing - clear session and create new cart
			WC()->session->__unset('wtrackt_new_cart');
			error_log('Cart session mismatch detected, clearing session and creating new cart');

			// Create new cart
			$carts_insert = array(
				'update_time' => current_time('mysql'),
				'cart_total' => $cart_total,
				'customer_id' => $customer_id,
				'cart_status' => 'new',
				'notification_sent' => 0,
				'try_count' => 0,
			);
			if (!is_user_logged_in()) {
				$carts_insert['ip_address'] = $ip_address;
			}
			$wpdb->insert($table_cart_name, $carts_insert);
			$last_cart_number = $wpdb->insert_id;

			if ($last_cart_number) {
				if (sizeof(WC()->cart->get_cart()) > 0) {
					foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
						$_product = $values['data'];
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
				error_log('New cart created after session mismatch: ' . $last_cart_number);
			}
		} else {
			// Cart exists - update it properly
			$cart_update_query = $wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_cart_name
					SET update_time = %s, cart_total = %f
					WHERE id = %d",
					current_time('mysql'),
					$cart_total,
					$new_cart
				)
			);

			// Handle product-specific logic for the existing cart
			$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND product_id = %d", $new_cart, $product_id);
			$results = $wpdb->get_results($sql, ARRAY_A);

			if (count($results) > 0) {
				$cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
				$removed = false;
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}mottasl_cart_tracking
						SET quantity = %d, removed = %d
						WHERE product_id = %d AND cart_number = %d",
						$cart_quantity,
						$removed,
						$product_id,
						$new_cart
					)
				);
			} else {
				$wpdb->insert(
					$table_name,
					array(
						'time' => current_time('mysql'),
						'product_id' => $product_id,
						'quantity' => $quantity,
						'cart_number' => $new_cart,
						'removed' => false,
					)
				);
			}
		}
	}
}

function wtrackt_update_cart_action_cart_updated($cart_updated)
{

	if ($cart_updated) {
		global $wpdb;
		if (!isset(WC()->session)) {
			return;
		}
		$new_cart = WC()->session->get('wtrackt_new_cart');
		$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';
		$cart_total = WC()->cart->get_cart_contents_total();
		$customer_id = 0;
		if (is_user_logged_in()) {
			$customer_id = get_current_user_id();
		}

		if (is_null($new_cart)) {
			$carts_insert = array(
				'update_time' => current_time('mysql'),
				'creation_time' => current_time('mysql'),
				'cart_total' => $cart_total,
				'customer_id' => $customer_id,
				"notification_sent" => false,
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
				if (sizeof(WC()->cart->get_cart()) > 0) {
					foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
						$_product = $values['data'];
						$wpdb->insert(
							$table_name,
							array(
								'time' => current_time('mysql'),
								'product_id' => $values['product_id'],
								'quantity' => $values['quantity'],
								'cart_number' => $last_cart_number,
							)
						);
					}
				}
				WC()->session->set('wtrackt_new_cart', $last_cart_number);
			}
		} else {
			// Update existing cart - first verify cart exists in database
			$existing_cart = $wpdb->get_row(
				$wpdb->prepare("SELECT id FROM $table_cart_name WHERE id = %d", $new_cart),
				ARRAY_A
			);

			if (!$existing_cart) {
				// Cart session exists but database record is missing - clear session and create new cart
				WC()->session->__unset('wtrackt_new_cart');
				error_log('Cart session mismatch detected in cart update, clearing session and creating new cart');

				// Create new cart
				$carts_insert = array(
					'update_time' => current_time('mysql'),
					'creation_time' => current_time('mysql'),
					'cart_total' => $cart_total,
					'customer_id' => $customer_id,
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
					if (sizeof(WC()->cart->get_cart()) > 0) {
						foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
							$_product = $values['data'];
							$wpdb->insert(
								$table_name,
								array(
									'time' => current_time('mysql'),
									'product_id' => $values['product_id'],
									'quantity' => $values['quantity'],
									'cart_number' => $last_cart_number,
								)
							);
						}
					}
					WC()->session->set('wtrackt_new_cart', $last_cart_number);
					error_log('New cart created after session mismatch in update: ' . $last_cart_number);
				}
			} else {
				// Cart exists - update it
				$cart_update_query = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}mottasl_cart
                    SET update_time = %s, cart_total = %f
                 WHERE id = %d", current_time('mysql'), $cart_total, $new_cart));

				// Update individual cart items
				if (sizeof(WC()->cart->get_cart()) > 0) {
					foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
						$_product = $values['data'];
						$cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
						$sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND product_id = %d", $new_cart, $values['product_id']);
						$results = $wpdb->get_results($sql, ARRAY_A);

						if (count($results) > 0) {
							$removed = false;
							$wpdb->query(
								$wpdb->prepare(
									"UPDATE {$wpdb->prefix}mottasl_cart_tracking
                            SET quantity = %d, removed=%d
                         WHERE product_id = %d AND cart_number = %d",
									$cart_quantity,
									$removed,
									$values['product_id'],
									$new_cart
								)
							);
						} else {
							$wpdb->insert(
								$table_name,
								array(
									'time' => current_time('mysql'),
									'product_id' => $values['product_id'],
									'quantity' => $cart_quantity,
									'cart_number' => $new_cart,
									'removed' => false,
								)
							);
						}
					}
				}
			}
		}
	}
}

function wtrackt_cart_updated()
{
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}
	$new_cart = WC()->session->get('wtrackt_new_cart');
	$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->get_cart_contents_total();
	$customer_id = get_current_user_id();

	if ($cart_total != 0 && !is_null($new_cart)) {
		// Get previous cart data to compare for actual changes
		$previous_cart = $wpdb->get_row(
			$wpdb->prepare("SELECT * FROM $table_cart_name WHERE id = %d", $new_cart),
			ARRAY_A
		);

		if (!$previous_cart) {
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
			// Update cart data
			$wpdb->update(
				$table_cart_name,
				array(
					'cart_total' => $cart_total,
					'customer_data' => json_encode($customer_data),
					'products' => json_encode($products),
					'store_url' => get_bloginfo('url'),
					'update_time' => current_time('mysql'),
					'cart_status' => 'new', // Reset to new when updated
					'notification_sent' => 0, // Reset notification flag for updates
				),
				array('id' => $new_cart),
				array(
					'%f',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
				),
				array('%d')
			);
		}
	}
}

function wtrackt_cart_item_removed($cart_item_key, $cart)
{
	$product_id = $cart->cart_contents[$cart_item_key]['product_id'];
	global $wpdb;
	if (!isset(WC()->session)) {
		return;
	}
	$new_cart = WC()->session->get('wtrackt_new_cart');
	$table_name = $wpdb->prefix . 'mottasl_cart_tracking';
	$table_cart_name = $wpdb->prefix . 'mottasl_cart';
	$cart_total = WC()->cart->total;
	$customer_id = 0;
	if (is_user_logged_in()) {
		$customer_id = get_current_user_id();
	}

	if (is_null($new_cart)) {
		$carts_insert = array(
			'update_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'customer_id' => $customer_id,
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
			if (sizeof(WC()->cart->get_cart()) > 0) {
				foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
					$_product = $values['data'];
					$removed = ($product_id === $values['product_id'] ? true : false);
					$wpdb->insert(
						$table_name,
						array(
							'time' => current_time('mysql'),
							'product_id' => $values['product_id'],
							'quantity' => $values['quantity'],
							'cart_number' => $last_cart_number,
							'removed' => $removed,
						)
					);
				}
			}
			WC()->session->set('wtrackt_new_cart', $last_cart_number);
		}
	} else {
		$removed = true;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}mottasl_cart_tracking\r\n                    SET removed = %d\r\n                 WHERE product_id = %d AND cart_number = %d",
				$removed,
				$product_id,
				$new_cart
			)
		);

		// Check if all items are now removed (cart is empty)
		$remaining_items = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}mottasl_cart_tracking WHERE cart_number = %d AND removed = 0",
				$new_cart
			)
		);

		if ($remaining_items == 0) {
			// Mark cart as deleted
			$wpdb->update(
				$table_cart_name,
				array('cart_status' => 'deleted', 'update_time' => current_time('mysql')),
				array('id' => $new_cart),
				array('%s', '%s'),
				array('%d')
			);
			error_log('Cart marked as deleted due to all items being removed: ' . $new_cart);
		}
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
	$response = $api->post('/cart/abandoned', $cart_data);

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

	// Check if enough time has passed since last update (using configured duration)
	$time_diff = time() - strtotime($cart['update_time']);
	if ($time_diff < (Constants::ABANDONED_CART_TIMEOUT * 60)) { // Convert minutes to seconds
		error_log('Cart update notification skipped - not enough time passed for cart: ' . $cart_id);
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

	// Format cart data according to GoLang AbandonedCart struct
	$formatted_cart = [
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
		'creation_time' => $cart['creation_time'] ?: $cart['update_time'],
		'update_time' => $cart['update_time'],
		'cart_total' => number_format(floatval($cart['cart_total']), 2, '.', ''),
		'order_created' => $cart['order_created'] ?? '',
		'notification_sent' => strval($cart['notification_sent'] ?? '0'),
		'ip_address' => $cart['ip_address'] ?? null,
		'products' => $products,
		'previous_status' => $previous_cart['cart_status']
	];

	// Initialize the MottaslApi
	$api = new MottaslApi();

	// Send cart update notification
	$response = $api->post('abandoned_cart.update', $formatted_cart);

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

	// Format cart data according to GoLang AbandonedCart struct
	$formatted_cart = [
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
		'cart_status' => 'deleted',
		'abandoned_at' => '',

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

	// Send cart deletion notification
	$response = $api->post('abandoned_cart.delete', $formatted_cart);

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

	$new_cart = WC()->session->get('wtrackt_new_cart');
	if (!is_null($new_cart)) {
		// Send deletion notification before removing the cart
		wtrackt_send_cart_deletion_notification($new_cart);

		// Mark cart as deleted
		$table_name = $wpdb->prefix . 'mottasl_cart';
		$wpdb->update(
			$table_name,
			array('cart_status' => 'deleted'),
			array('id' => $new_cart),
			array('%s'),
			array('%d')
		);

		// Clear session
		WC()->session->__unset('wtrackt_new_cart');
	}
}

function wtrackt_user_login_update($user_login, $user)
{
	if (!isset(WC()->session)) {
		return;
	}
	$new_cart = WC()->session->get('wtrackt_new_cart');

	if (!is_null($new_cart)) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mottasl_cart';
		$wpdb->update(
			$table_name,
			array(
				'customer_id' => $user->ID,
			),
			array(
				'id' => $new_cart,
			),
			array('%d')
		);
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

		$new_cart = WC()->session->get('wtrackt_new_cart');

		// If no cart in session, create new one
		if (is_null($new_cart)) {
			return wtrackt_create_new_cart($customer_id, $cart_total);
		}

		// Verify cart exists in database
		$existing_cart = $wpdb->get_row(
			$wpdb->prepare("SELECT id FROM $table_cart_name WHERE id = %d", $new_cart),
			ARRAY_A
		);

		if (!$existing_cart) {
			// Cart session exists but database record is missing - clear session and create new cart
			WC()->session->__unset('wtrackt_new_cart');
			error_log('Cart session mismatch detected, clearing session and creating new cart');
			return wtrackt_create_new_cart($customer_id, $cart_total);
		}

		return $new_cart;
	}
}

// Helper function to create a new cart record
if (!function_exists('wtrackt_create_new_cart')) {
	function wtrackt_create_new_cart($customer_id = 0, $cart_total = 0.0)
	{
		global $wpdb;
		$table_cart_name = $wpdb->prefix . 'mottasl_cart';
		$table_name = $wpdb->prefix . 'mottasl_cart_tracking';

		$carts_insert = array(
			'update_time' => current_time('mysql'),
			'creation_time' => current_time('mysql'),
			'cart_total' => $cart_total,
			'customer_id' => $customer_id,
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
			error_log('New cart created: ' . $last_cart_number);
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
