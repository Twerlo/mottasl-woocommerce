<?php

/**
 * Plugin-wide constants.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce;

defined('ABSPATH') || exit;

class Constants
{

    /**
     * Base URL for the Mottasl API.
     * @var string
     */
    const MOTTASL_API_BASE_URL =  'https://f414-102-184-121-206.ngrok-free.app';

    /**
     * The Path that will be used to send the app events such as installed, uninstalled, and deleted.
     */
    const MOTTASL_EVENT_PATH_APP = '/app.event';
    const MOTTASL_EVENT_PATH_ORDER = '/order.event';
    const MOTTASL_EVENT_PATH_CART = '/cart.event';
    const MOTTASL_EVENT_PATH_CUSTOMER = '/customer.event';
    const MOTTASL_EVENT_PATH_PRODUCT = '/product.event';
    const MOTTASL_EVENT_PATH_INVOICE = '/invoice.event';

    /**
     * Webhook topic for installation confirmation.
     * @var string
     */
    const EVENT_TOPIC_INSTALLED = 'installed';

    /**
     * Webhook topic for update confirmation.
     * @var string
     */
    const EVENT_TOPIC_SETTINGS_UPDATED = 'settings.updated';

    /**
     * Webhook topic for uninstallation confirmation.
     * @var string
     */
    const EVENT_TOPIC_UNINSTALLED = 'uninstalled';

    /**
     * Webhook topic for order creation.
     * @var string
     */
    const EVENT_TOPIC_ORDER_CREATED = 'order.created';

    /**
     * Webhook topic for order updates.
     * @var string
     */
    const EVENT_TOPIC_ORDER_UPDATED = 'order.updated';

    /**
     * Webhook topic for order deletion.
     * (Note: WooCommerce orders are usually trashed or anonymized, not fully deleted by default.)
     * @var string
     */
    const EVENT_TOPIC_ORDER_DELETED = 'order.deleted';

    /**
     * Webhook topic for abandoned carts.
     * @var string
     */
    const EVENT_TOPIC_ABANDONED_CART = 'cart.abandoned';


    /**
     * Option key for plugin settings.
     * @var string
     */
    const SETTINGS_OPTION_KEY = 'mottasl_wc_settings';

    /**
     * Prefix for plugin meta keys (e.g., for storing invoice PDF path on order meta).
     * @var string
     */
    const META_PREFIX = '_mottasl_wc_';


    // You can add more constants here as needed, for example:
    // const DEFAULT_ABANDONED_CART_TIMEOUT = 3600; // 1 hour in seconds
    // const PDF_STORAGE_DIRECTORY = 'mottasl-invoices'; // Relative to wp-content/uploads
}