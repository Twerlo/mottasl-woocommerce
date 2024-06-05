<?php

defined('ABSPATH') || exit;


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
    if (!isset(WC()->session))
    {
        return;
    }

    $new_cart = WC()->session->get('wtrackt_new_cart');
    $table_name = $wpdb->prefix . 'cart_tracking_wc';
    $table_cart_name = $wpdb->prefix . 'cart_tracking_wc_cart';
    $cart_total = WC()->cart->get_cart_contents_total();
    $customer_id = 0;

    if (is_user_logged_in())
    {
        $customer_id = get_current_user_id();
    } else
    {

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else
        {
            $ip_address = $_SERVER['REMOTE_ADDR'];
        }

    }


    if (is_null($new_cart))
    {
        $carts_insert = array(
            'update_time' => current_time('mysql'),
            'cart_total' => $cart_total,
            'customer_id' => $customer_id,
            'cart_status' => 'new',
        );
        if (!is_user_logged_in())
        {
            $carts_insert['ip_address'] = $ip_address;
        }
        $wpdb->insert($table_cart_name, $carts_insert);
        $last_cart_number = $wpdb->insert_id;

        if ($last_cart_number)
        {


            if (sizeof(WC()->cart->get_cart()) > 0)
            {

                foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                {
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
        }

    } else
    {

        $cart_update_query = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table_cart_name\r\n            SET update_time = %s, cart_total = %f\r\n         WHERE id = %d",
                current_time('mysql'),
                $cart_total,
                $new_cart
            )
        );

        if (!$cart_update_query)
        {
            $carts_insert = array(
                'update_time' => current_time('mysql'),
                'cart_total' => $cart_total,
                'customer_id' => $customer_id,
            );
            if (!is_user_logged_in())
            {
                $carts_insert['ip_address'] = $ip_address;
            }
            $wpdb->insert($table_cart_name, $carts_insert);
            $last_cart_number = $wpdb->insert_id;

            if ($last_cart_number)
            {
                if (sizeof(WC()->cart->get_cart()) > 0)
                {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                    {
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
            }

        } else
        {
            $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cart_tracking_wc WHERE cart_number = %d AND product_id = %d", $new_cart, $product_id);
            $results = $wpdb->get_results($sql, ARRAY_A);

            if (count($results) > 0)
            {
                $cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
                $removed = false;
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->prefix}cart_tracking_wc\r\n            SET quantity = %d, removed = %d\r\n         WHERE product_id = %d AND cart_number = %d",
                        $cart_quantity,
                        $removed,
                        $product_id,
                        $new_cart
                    )
                );
            } else
            {
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

    if ($cart_updated)
    {
        global $wpdb;
        if (!isset(WC()->session))
        {
            return;
        }
        $new_cart = WC()->session->get('wtrackt_new_cart');
        $table_name = $wpdb->prefix . 'cart_tracking_wc';
        $table_cart_name = $wpdb->prefix . 'cart_tracking_wc_cart';
        $cart_total = WC()->cart->get_cart_contents_total();
        $customer_id = 0;
        if (is_user_logged_in())
        {
            $customer_id = get_current_user_id();
        }

        if (is_null($new_cart))
        {
            $carts_insert = array(
                'update_time' => current_time('mysql'),
                'creation_time' => current_time('mysql'),
                'cart_total' => $cart_total,
                'customer_id' => $customer_id,
                "notification_sent" => false,

            );

            if (!is_user_logged_in())
            {

                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
                {
                    $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
                } else
                {
                    $ip_address = $_SERVER['REMOTE_ADDR'];
                }

                $carts_insert['ip_address'] = $ip_address;
            }

            $wpdb->insert($table_cart_name, $carts_insert);
            $last_cart_number = $wpdb->insert_id;

            if ($last_cart_number)
            {
                if (sizeof(WC()->cart->get_cart()) > 0)
                {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                    {
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

        } else
        {
            $cart_update_query = $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cart_tracking_wc_cart\r\n                    SET update_time = %s\r\n                 WHERE id = %d", current_time('mysql'), $new_cart));

            if (!$cart_update_query)
            {
                $carts_insert = array(
                    'update_time' => current_time('mysql'),
                    'cart_total' => $cart_total,
                    'customer_id' => $customer_id,
                );

                if (!is_user_logged_in())
                {

                    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
                    {
                        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
                    } else
                    {
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                    }

                    $carts_insert['ip_address'] = $ip_address;
                }

                $wpdb->insert($table_cart_name, $carts_insert);
                $last_cart_number = $wpdb->insert_id;

                if ($last_cart_number)
                {
                    if (sizeof(WC()->cart->get_cart()) > 0)
                    {
                        foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                        {
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
                }

            } else
            {
                if (sizeof(WC()->cart->get_cart()) > 0)
                {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                    {
                        $_product = $values['data'];
                        $cart_quantity = WC()->cart->get_cart()[$cart_item_key]['quantity'];
                        $sql = $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cart_tracking_wc WHERE cart_number = %d AND product_id = %d", $new_cart, $values['product_id']);
                        $results = $wpdb->get_results($sql, ARRAY_A);

                        if (count($results) > 0)
                        {
                            $removed = false;
                            $wpdb->query(
                                $wpdb->prepare(
                                    "UPDATE {$wpdb->prefix}cart_tracking_wc\r\n                            SET quantity = %d, removed=%d\r\n                         WHERE product_id = %d AND cart_number = %d",
                                    $cart_quantity,
                                    $removed,
                                    $values['product_id'],
                                    $new_cart
                                )
                            );
                        } else
                        {
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
    if (!isset(WC()->session))
    {
        return;
    }
    $new_cart = WC()->session->get('wtrackt_new_cart');
    $table_name = $wpdb->prefix . 'cart_tracking_wc';
    $table_cart_name = $wpdb->prefix . 'cart_tracking_wc_cart';
    $cart_total = WC()->cart->get_cart_contents_total();
    $customer_id = get_current_user_id();

    if ($cart_total != 0)
    {
        $customer_data = [

            'first_name' => WC()->cart->get_customer()->get_billing_first_name(),
            'last_name' => WC()->cart->get_customer()->get_billing_last_name(),
            'email' => WC()->cart->get_customer()->get_billing_email(),
            'customer_id' => $customer_id,
        'phone' => get_user_meta($customer_id, 'phone_code', true ).get_user_meta($customer_id, 'phone_number', true),

        ];
        $products = [];
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item)
        {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $price = WC()->cart->get_product_price($product);
            $link = $product->get_permalink($cart_item);
            // Anything related to $product, check $product tutorial
            $products[] = [
                'product_id' => $product_id,
                'quantity' => $quantity,
                'price' => $price,
                'link' => $link,
                // Add other relevant data points here
            ];
        }
        if (!is_null($new_cart))
        {
            $wpdb->update(
                $table_cart_name,
                array(
                    'cart_total' => $cart_total,
                    'customer_data' => json_encode($customer_data),
                    'products' => json_encode($products),
                    'store_url' => get_bloginfo('url')
                ),
                array('id' => $new_cart),
                array(
                    '%f',
                    '%s',
                    '%s',
                    '%s'
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
    if (!isset(WC()->session))
    {
        return;
    }
    $new_cart = WC()->session->get('wtrackt_new_cart');
    $table_name = $wpdb->prefix . 'cart_tracking_wc';
    $table_cart_name = $wpdb->prefix . 'cart_tracking_wc_cart';
    $cart_total = WC()->cart->total;
    $customer_id = 0;
    if (is_user_logged_in())
    {
        $customer_id = get_current_user_id();
    }

    if (is_null($new_cart))
    {
        $carts_insert = array(
            'update_time' => current_time('mysql'),
            'cart_total' => $cart_total,
            'customer_id' => $customer_id,

        );

        if (!is_user_logged_in())
        {

            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
            {
                $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else
            {
                $ip_address = $_SERVER['REMOTE_ADDR'];
            }

            $carts_insert['ip_address'] = $ip_address;
        }

        $wpdb->insert($table_cart_name, $carts_insert);
        $last_cart_number = $wpdb->insert_id;

        if ($last_cart_number)
        {
            if (sizeof(WC()->cart->get_cart()) > 0)
            {
                foreach (WC()->cart->get_cart() as $cart_item_key => $values)
                {
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

    } else
    {
        $removed = true;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->prefix}cart_tracking_wc\r\n                    SET removed = %d\r\n                 WHERE product_id = %d AND cart_number = %d",
                $removed,
                $product_id,
                $new_cart
            )
        );
    }

}
function format_order_items($order_id) {

    // Get the order object
    $order = wc_get_order($order_id);
    $data = $order->get_data(); // order data

    // Ensure the order object is valid
    if (!$order) {
        return $formatted_items;
    }
    unset($data['line_items']);
$payment_url = $order->get_checkout_payment_url( true );
     $formatted_items = [...$data,'payment_url' => $payment_url];
   
    
    // Iterate over each item in the order
    foreach ($order->get_items() as $item_key => $item ) {

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
    if (!isset(WC()->session))
    {
        return;
    }
    $new_cart = WC()->session->get('wtrackt_new_cart');
    $table_name = $wpdb->prefix . 'cart_tracking_wc_cart';

    if (!is_null($new_cart))
    {
        
$order = wc_get_order( $order_id );
$order_data = format_order_items($order_id);
$order_data_id = $order_data['id'];
        $wpdb->update(
            $table_name,
            array(
                'cart_status' => 'completed', // Update cart status to 'ordered'
                'order_created' => $order_data_id, // Associate order with cart
            ),
            array(
                'id' => $new_cart,
            ),
            array(
                '%s', // Data format for 'cart_status'
                '%d' // Data format for 'order_created'
            ),
            array(
                '%d' // Format for the WHERE clause
            )
        );
          
        $cart_details = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE id = %d",
                $new_cart
            ),
            ARRAY_A // This parameter ensures the returned data is in an associative array
        );
 // The Order data

        $cart_details['customer_data'] = json_decode($cart_details['customer_data']);
        $cart_details['products'] = json_decode($cart_details['products']);
  $order_response = wp_remote_post(
            'https://test.hub.avocad0.dev/api/v1/integration/events/woocommerce/order.created?store_url='.get_bloginfo( 'url'),
            array(
                'body' => json_encode($order_data),
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-Business-Id' => get_option('business_id')
                )
            )
        );



        $response = wp_remote_post(
            'https://test.hub.avocad0.dev/api/v1/integration/events/woocommerce/abandoned_cart.complete',
            array(
                'body' => json_encode([$cart_details]),
                'method' => 'POST',
                'headers' => array(
                                        'Content-Type' => 'application/json',

                    'X-Business-Id' => get_option('business_id')
                )
            )
        );

        if (is_wp_error($response))
        {
            $error_message = $response->get_error_message();
            echo $error_message;
            // Handle error,  log it or display a message
        }
        WC()->session->__unset('wtrackt_new_cart');

    }

}

function wtrackt_user_login_update($user_login, $user)
{
    if (!isset(WC()->session))
    {
        return;
    }
    $new_cart = WC()->session->get('wtrackt_new_cart');

    if (!is_null($new_cart))
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'cart_tracking_wc_cart';
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
