<?php
/**
 * Mottasl for WooCommerce - Event Payload Interface and Default Implementation
 */
namespace Mottasl\WooCommerce\Integrations;

use Mottasl\WooCommerce\Utils\Helper;

/**
 * Interface for event payloads.
 */
interface EventPayloadInterface {
    public function payload(): array;
}

/**
 * Default implementation for event payloads.
 * Can be extended or used for any event type.
 */
class MottaslEventsPayload implements EventPayloadInterface {
    protected $event;
    protected $extra_data;

    public function setEvent($event) {
        $this->event = $event;
        return $this;
    }

    public function setExtraData($extra_data) {
        $this->extra_data = $extra_data;
        return $this;
    }

    public function __construct($event, $extra_data = null) {
        $this->event      = $event;
        $this->extra_data = $extra_data;
    }

    public function payload(): array {
        if (empty($this->event) || empty(Helper::get_store_url())) {
            // Log an error if event or store URL is missing
            error_log('Mottasl Events Payload Error: Event or store URL is missing.');
            return [];
        }
        if (!is_array($this->extra_data)) {
            $this->extra_data = [];
        }
        $store_url = Helper::get_store_url();
       
        if (!$store_url || empty($store_url)) {
            error_log('Mottasl Events Payload Error: Could not retrieve store URL.');
            return [];
        }
        if (!filter_var($store_url, FILTER_VALIDATE_URL)) {
            error_log('Mottasl Events Payload Error: Invalid store URL.');
            return [];
        }
        return [
            'event'          => $this->event,
            'plugin_version' => defined('MOTTASL_WC_VERSION') ? MOTTASL_WC_VERSION : '',
            'plugin_name'    => defined('MOTTASL_WC_TEXT_DOMAIN') ? MOTTASL_WC_TEXT_DOMAIN : 'mottasl-woocommerce',
            'plugin_slug'    => defined('MOTTASL_WC_BASENAME') ? MOTTASL_WC_BASENAME : '',
            'wc_version'     => function_exists('WC') ? WC()->version : null, // Get WooCommerce version if available
            'php_version'    => phpversion(),
            'wp_version'     => function_exists('get_bloginfo') ? get_bloginfo('version') : '',
            /**
             * 'site_url'  => The URL of the WordPress site, retrieved using the get_site_url() function if available.
             *                This typically refers to the main URL of the WordPress installation.
             *
             * 'store_url' => The URL of the store, stored in the $this->store_url property.
             *                This may differ from 'site_url' if the store is located at a different URL or subdirectory.
             */
            'site_url'       => function_exists('get_site_url') ? get_site_url() : '',
            'store_url'      =>  $store_url,
            'event_time'     => function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s'),
            'data'           => $this->extra_data,
        ];
    }
}