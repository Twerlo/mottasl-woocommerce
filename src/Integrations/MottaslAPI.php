<?php
/**
 * Handles communication with the Mottasl.ai API.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Integrations
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Integrations;

use Mottasl\WooCommerce\Constants;
use Mottasl\WooCommerce\Utils\Helper; // If needed for API key or other settings

defined( 'ABSPATH' ) || exit;

class MottaslAPI {

    /**
     * Sends an event to the Mottasl API.
     *
     * @since 1.0.0
     * @param string $event_path The specific event topic (e.g., 'installation.confirmation').
     * @param array  $payload       The data to send.
     * @param string $store_url     The WooCommerce store URL.
     * @return bool|array True on success (or API response body), false or WP_Error on failure.
     */
    public function send_event( string $event_path, array $payload ) {
        if ( empty( $event_path ) ) {
            error_log( 'Mottasl API Error: Webhook event path is missing.' );
            return false;
        }

        $api_url_base = Constants::MOTTASL_API_BASE_URL;
        if ( ! filter_var( $api_url_base, FILTER_VALIDATE_URL ) ) {
            error_log( 'Mottasl API Error: Invalid API base URL.' );
            return false;
        }
        // Add forwarding slash if missing
        $endpoint_url = rtrim( $api_url_base, '/' ) . '/api/v1/integration/events/woocommerce' ;
        if ( ! filter_var( $endpoint_url, FILTER_VALIDATE_URL ) ) {
            error_log( 'Mottasl API Error: Invalid endpoint URL: ' . $endpoint_url );
            return false;
        }
        $endpoint_url .= $event_path;
        // $full_url     = add_query_arg( 'store_url', urlencode( $store_url ), $endpoint_url );
        
        // Retrieve API key from settings if Mottasl requires it
        $api_key = Helper::get_setting( 'mottasl_business_id' );
        if ( empty( $api_key ) ) {
            error_log( 'Mottasl API Error: Mottasl Business ID is not configured.' );
            return false;
        }

        $args = [
            'method'      => 'POST',
            'timeout'     => 30, // seconds
            'redirection' => 5,
            'httpversion' => '1.1', // Or '1.0' depending on server
            'blocking'    => true, // Set to false for non-blocking (asynchronous) requests if appropriate
            'headers'     => [
                'Content-Type' => 'application/json; charset=utf-8',
                'X-Mottasl-Plugin-Version' => MOTTASL_WC_VERSION,
                'x-business-id' => $api_key, 
                'User-Agent' => 'Mottasl WooCommerce Integration/' . MOTTASL_WC_VERSION . '; WordPress/' . get_bloginfo('version') . '; WooCommerce/' . ( WC()->version ?? null),
            ],
            'body'        => wp_json_encode( $payload ),
            'data_format' => 'body',
            // 'sslverify'   => true, // Set to false only for local development with self-signed certs, not recommended for production
        ];

        // For non-blocking requests, you might want to handle them differently.
        // If blocking is false, wp_remote_post returns true immediately if the request is dispatched,
        // not the actual response from the server.
        if ( apply_filters( 'mottasl_wc_api_non_blocking_request', false, $event_path ) ) {
            $args['blocking'] = false;
            $args['timeout'] = 5; // Shorter timeout for non-blocking
        }

        $response = wp_remote_post( $endpoint_url, $args );

        if ( is_wp_Error( $response ) ) {
            $error_message = $response->get_error_message();
            error_log( "Mottasl API Error ({$event_path}): " . $error_message . " | URL: " . $endpoint_url );
            // Optionally, log the payload for debugging (be careful with sensitive data)
            // error_log( "Mottasl API Payload ({$event_path}): " . wp_json_encode( $payload ) );
            return $response; // Return WP_Error object
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Check for successful HTTP status codes (2xx range)
        if ( $response_code >= 200 && $response_code < 300 ) {
            // Log successful submission for debugging if needed
            // error_log( "Mottasl API Success ({$event_path}): Response Code {$response_code}" );
            $decoded_body = json_decode( $response_body, true );
            return $decoded_body ?? true; // Return decoded body or true if body is empty/not JSON
        } else {
            error_log( "Mottasl API HTTP Error ({$event_path}): Status {$response_code} | URL: " . $endpoint_url . " | Response: " . $response_body );
            // Optionally, log the payload for debugging
            // error_log( "Mottasl API Payload ({$event_path}): " . wp_json_encode( $payload ) );
            return false;
        }
    }
}