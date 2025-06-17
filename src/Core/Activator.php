<?php

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Core
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Core;

use Mottasl\WooCommerce\Integrations\MottaslAPI;
use Mottasl\WooCommerce\Integrations\MottaslEventsPayload;
use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Constants;
use Mottasl\WooCommerce\Events\AbandonedCartHandler; // For DEFAULT_ABANDONMENT_TIMEOUT

defined('ABSPATH') || exit;

class Activator
{

    /**
     * Main activation tasks.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        // 1. Check if WooCommerce is active.
        if (! class_exists('WooCommerce')) {
            if (is_plugin_active(MOTTASL_WC_BASENAME)) {
                deactivate_plugins(MOTTASL_WC_BASENAME);
                set_transient('mottasl_wc_activation_error_notice', true, 5);
            }
            return;
        }

        // 2. Send an initial installation confirmation to Mottasl.ai (without API keys).
        // API keys and other connection details will be sent when the user saves them in settings.
        self::send_initial_installation();

        // 3. Set up default plugin options.
        self::setup_default_options();

        // 4. Create custom tables if any (e.g., for abandoned carts).
        self::create_abandoned_cart_table();
    }

    /**
     * Sends the initial installation confirmation event to Mottasl.ai.
     * This version does not handle API key generation or transmission.
     *
     * @since 1.0.0
     */
    private static function send_initial_installation()
    {
        if (
            ! class_exists('Mottasl\\WooCommerce\\Integrations\\MottaslAPI') ||
            ! class_exists('Mottasl\\WooCommerce\\Utils\\Helper') ||
            ! class_exists('Mottasl\\WooCommerce\\Constants') ||
            ! class_exists('Mottasl\\WooCommerce\\Integrations\\MottaslEventsPayload') // Check for your payload class
        ) {
            error_log('Mottasl Activation Error: Core classes for sending confirmation not found.');
            return;
        }

        $store_url = Helper::get_store_url();
        if (!$store_url) {
            error_log('Mottasl Activation Error: Could not retrieve store URL.');
            return;
        }
        $data = [
            'contact' => [
                'email' => bloginfo('admin_email'), // Use the admin email as contact
                'name'  => '',             
            ],
        ];

        $api = new MottaslAPI();
        $api->send_event(Constants::MOTTASL_EVENT_PATH_APP, Constants::EVENT_TOPIC_INSTALLED, $data);
    }

    /** 
     * Sets up default plugin options if they don't exist.
     *
     * @since 1.0.0
     */
    private static function setup_default_options()
    {
        $options = get_option(Constants::SETTINGS_OPTION_KEY, []); // Use the constant for option name

        $defaults = [
            'mottasl_business_id'        => '', // User will fill this
            'mottasl_wc_consumer_key'    => '', // User will fill this
            'mottasl_wc_consumer_secret' => '', // User will fill this
            'enable_order_sync'          => 'yes',
            'enable_abandoned_cart'      => 'yes',
            'abandoned_cart_timeout'     => AbandonedCartHandler::DEFAULT_ABANDONMENT_TIMEOUT,
            'invoice_store_name'         => get_bloginfo('name'),
            'invoice_store_address'      => '',
            'invoice_store_vat'          => '',
            'invoice_store_logo_url'     => '',
            'invoice_generate_on_status' => ['processing', 'completed'],
        ];

        $new_options = wp_parse_args($options, $defaults);
        update_option(Constants::SETTINGS_OPTION_KEY, $new_options);
    }

    /**
     * Creates the custom table for storing abandoned cart data.
     *
     * @since 1.0.0
     */
    private static function create_abandoned_cart_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mottasl_abandoned_carts'; // Consider defining this as a constant
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name) {
            // Table exists
            // Optionally, add dbDelta call here for schema updates if you ever change the table
        }

        $sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            session_id VARCHAR(191) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            cart_hash VARCHAR(32) NOT NULL,
            cart_contents LONGTEXT NOT NULL,
            cart_totals TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_updated_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_session_id (session_id),
            KEY idx_email (email),
            KEY idx_status_last_updated (status, last_updated_at)
        ) $charset_collate;";

        if (!function_exists('dbDelta')) { // Ensure dbDelta is available
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }
        dbDelta($sql);

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) !== $table_name) {
            error_log("Mottasl WC: Failed to create abandoned cart table: $table_name");
        }
    }
}

// Admin notice if WooCommerce was missing during activation.
add_action('admin_notices', function () {
    if (get_transient('mottasl_wc_activation_error_notice')) {
?>
<div class="notice notice-error is-dismissible">
    <p><?php esc_html_e('Mottasl for WooCommerce plugin requires WooCommerce to be active and was automatically deactivated.', 'mottasl-woocommerce'); ?>
    </p>
</div>
<?php
        delete_transient('mottasl_wc_activation_error_notice');
    }
});