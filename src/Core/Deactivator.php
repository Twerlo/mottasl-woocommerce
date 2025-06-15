<?php
/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Core
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Core;

use Mottasl\WooCommerce\Constants;
use Mottasl\WooCommerce\Events\AbandonedCartHandler;
use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Integrations\MottaslAPI;
use Mottasl\WooCommerce\Integrations\MottaslEventsPayload;

defined( 'ABSPATH' ) || exit;

class Deactivator {

    /**
     * Main deactivation tasks.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // Clear any scheduled cron jobs related to the plugin
        // Example: if you have a cron for abandoned carts
        // wp_clear_scheduled_hook( 'mottasl_wc_abandoned_cart_check_event' );

        // Optionally, send a deactivation event to Mottasl.ai (if useful for them)
         self::send_deactivation_event();

        // Flush rewrite rules if you had custom rules and want to remove them (usually not needed on deactivation)
        // flush_rewrite_rules();
        // Clear abandoned cart cron job
        $timestamp = wp_next_scheduled( AbandonedCartHandler::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, AbandonedCartHandler::CRON_HOOK );
        }
    }

    /**
     * Example: Send a deactivation event to Mottasl.ai.
     *
     * @since 1.0.0
     */
    private static function send_deactivation_event() {
        if ( ! class_exists( 'Mottasl\\WooCommerce\\Integrations\\MottaslAPI' ) ||
             ! class_exists( 'Mottasl\\WooCommerce\\Utils\\Helper' ) ||
             ! class_exists( 'Mottasl\\WooCommerce\\Constants' ) ) {
            error_log('Mottasl Deactivation Error: Core classes for sending event not found.');
            return;
        }

        $store_url = Helper::get_store_url();
        if ( ! $store_url ) {
            error_log('Mottasl Deactivation Error: Could not retrieve store URL.');
            return;
        }

        $api_handler = new MottaslAPI();


        $payloadObj = new MottaslEventsPayload(Constants::EVENT_TOPIC_UNINSTALLED);
        $payload = $payloadObj->payload();
        $api_handler->send_event( Constants::MOTTASL_EVENT_PATH_APP, $payload, $store_url );
    }
}