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
        WHERE `creation_time` <= DATE_SUB(NOW(), INTERVAL 15 MINUTE) ",
        $cutoff_time
    );

    // Execute the SQL
    $wpdb->query($sql);



}
