<?php
add_filter('cron_schedules', function ($schedules) {
    $schedules['every-15-minutes'] = array (
        'interval' => 15 * 60,
        'display' => __('Every 15 minutes')
    );
    return $schedules;
});

if (!wp_next_scheduled('my_function_hook'))
{
    wp_schedule_event(time(), 'every-15-minutes', 'my_function_hook');
}
add_action('my_function_hook', 'my_function');

function my_function()
{

    global $wpdb;

    // Define the time limit in minutes
    $time_limit = 15;

    // Calculate the cutoff time
    $cutoff_time = current_time('mysql', 1) - ($time_limit * 60);

    // SQL to update cart_status to 'abandoned'
    $sql = $wpdb->prepare(
        "UPDATE `wp_cart_tracking_wc_cart`
         SET `cart_status` = 'abandoned'
        WHERE `update_time` <= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ",
        $cutoff_time
    );

    // Execute the SQL
    $wpdb->query($sql);
    $carts = $wpdb->get_results("
        SELECT * FROM `wp_cart_tracking_wc_cart`
        WHERE `cart_status` = 'abandoned'
          AND `notification_sent` = 0
    ", ARRAY_A);

    $response = wp_remote_post('https://hub-api.avocad0.dev/api/v1/integration/events/woocommerce/abandoned.cart', [
        'body' => json_encode($carts),
        'headers' => [
            'Content-Type' => 'application/json'
        ]
    ]);

    // Check for successful response before marking as notified
    if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200)
    {
        // Prepare a list of cart IDs to update
        $cart_ids = wp_list_pluck($carts, 'id');
        $cart_ids_placeholder = implode(',', array_fill(0, count($cart_ids), '%d'));

        // Update the notification_sent status to true
        $wpdb->query($wpdb->prepare("
                UPDATE `wp_cart_tracking_wc_cart`
                SET `notification_sent` = true
                 WHERE `cart_status` = 'abandoned'
          AND `notification_sent` = 0
            ", ));
    }
}



