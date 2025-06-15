<?php
/**
 * Handles WooCommerce order events and sends data to Mottasl.ai.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Events
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Events;

use Mottasl\WooCommerce\Integrations\MottaslAPI;
use Mottasl\WooCommerce\Invoices\InvoiceGenerator;
use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Constants;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class OrderHandler {

    /**
     * @var MottaslAPI
     */
    private MottaslAPI $api_handler;

    /**
     * @var InvoiceGenerator|null
     */
    private ?InvoiceGenerator $invoice_generator = null;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->api_handler = new MottaslAPI();
        if ( class_exists( 'Mottasl\\WooCommerce\\Invoices\\InvoiceGenerator' ) ) {
            $this->invoice_generator = new InvoiceGenerator();
        }
    }

    /**
     * Initializes hooks for order events.
     * Called by the main Plugin class.
     *
     * @since 1.0.0
     */
    public function init(): void {
        if ( 'yes' !== Helper::get_setting( 'enable_order_sync', 'yes' ) ) {
            return; // Don't hook if order sync is disabled
        }

        // Order status changes
        // This hook fires for any status transition.
        add_action( 'woocommerce_order_status_changed', [ $this, 'handle_order_status_changed' ], 10, 4 );

        // Order creation (e.g., for newly created orders that might not immediately have a status change trigger if created programmatically or via REST API)
        // 'woocommerce_new_order' is good for orders created through checkout.
        // 'woocommerce_checkout_order_processed' is another option, fires after order is created and meta is saved.
        // For broader coverage, including REST API created orders:
        add_action( 'woocommerce_thankyou', [ $this, 'handle_new_order_on_thankyou' ], 10, 1); // For frontend orders
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'handle_block_checkout_order' ] ); // For block-based checkout
        // Consider 'save_post_shop_order' for admin-created/updated orders, but be careful of recursion and multiple firings.
        // The 'woocommerce_order_status_changed' usually covers most needs for updates.

        // Order deletion (when an order is moved to trash or permanently deleted)
        add_action( 'wp_trash_post', [ $this, 'handle_order_trashed' ], 10, 1 );
        add_action( 'before_delete_post', [ $this, 'handle_order_deleted_permanently' ], 10, 1 );

        // Hook to potentially generate invoice when an order is paid
        add_action( 'woocommerce_payment_complete', [ $this, 'maybe_generate_invoice_on_payment' ], 20, 1 );
        // Also for statuses that signify payment or readiness for fulfillment
        add_action( 'woocommerce_order_status_changed', [ $this, 'maybe_generate_invoice_on_status_change' ], 20, 4 );
    }

    /**
     * Handles the woocommerce_order_status_changed hook.
     *
     * @param int    $order_id        Order ID.
     * @param string $old_status      Old order status (without 'wc-' prefix).
     * @param string $new_status      New order status (without 'wc-' prefix).
     * @param WC_Order $order         Order object.
     */
    public function handle_order_status_changed( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
        // Determine event topic
        // If $old_status is empty or a 'new' status, it could be considered 'created'
        // However, 'woocommerce_new_order' or 'woocommerce_checkout_order_processed' are better for creation.
        // This hook is primarily for updates.
        $event_topic = Constants::EVENT_TOPIC_ORDER_UPDATED;
        if (empty($old_status) || in_array($old_status, ['auto-draft', 'draft', 'new'], true) ) {
             $event_topic = Constants::EVENT_TOPIC_ORDER_CREATED;
        }

        $this->send_order_event( $order_id, $event_topic, [ 'old_status' => $old_status, 'new_status' => $new_status ] );
    }

    /**
     * Handles new orders created via the classic checkout thank you page.
     *
     * @param int $order_id Order ID.
     */
    public function handle_new_order_on_thankyou( int $order_id ): void {
        // Check if already processed by status_changed hook to avoid duplicates
        // A simple transient or order meta could be used for debouncing if necessary,
        // but often the payload or context differs enough or Mottasl handles idempotency.
        $this->send_order_event( $order_id, Constants::EVENT_TOPIC_ORDER_CREATED );
    }

    /**
     * Handle orders processed by the block-based checkout.
     *
     * @param WC_Order $order The order object.
     */
    public function handle_block_checkout_order( WC_Order $order ): void {
        $this->send_order_event( $order->get_id(), Constants::EVENT_TOPIC_ORDER_CREATED );
    }


    /**
     * Handles when an order post is trashed.
     *
     * @param int $post_id Post ID.
     */
    public function handle_order_trashed( int $post_id ): void {
        if ( get_post_type( $post_id ) === 'shop_order' ) {
            $this->send_order_event( $post_id, Constants::EVENT_TOPIC_ORDER_DELETED, [ 'deletion_type' => 'trashed' ] );
        }
    }

    /**
     * Handles when an order post is permanently deleted.
     *
     * @param int $post_id Post ID.
     */
    public function handle_order_deleted_permanently( int $post_id ): void {
        if ( get_post_type( $post_id ) === 'shop_order' ) {
            // Check if it's actually a shop_order because 'before_delete_post' can fire for revisions etc.
            $post = get_post($post_id);
            if ($post && $post->post_type === 'shop_order') {
                 $this->send_order_event( $post_id, Constants::EVENT_TOPIC_ORDER_DELETED, [ 'deletion_type' => 'permanently_deleted' ] );
            }
        }
    }

    /**
     * Prepares and sends the order data to Mottasl.
     *
     * @param int    $order_id      Order ID.
     * @param string $event_topic   Mottasl API event topic.
     * @param array  $context       Additional context for the event (e.g., old/new status).
     */
    private function send_order_event( int $order_id, string $event_topic, array $context = [] ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            error_log( "Mottasl OrderHandler: Could not retrieve order #{$order_id} for event {$event_topic}." );
            return;
        }

        // Prevent sending events for certain order types if needed (e.g. subscription renewals if handled differently)
        if ( apply_filters( 'mottasl_wc_block_order_event', false, $order, $event_topic, $context ) ) {
            return;
        }

        $payload = $this->prepare_order_payload( $order, $context );
        $store_url = Helper::get_store_url();

        $this->api_handler->send_event( $event_topic, $payload, $store_url );
    }

    /**
     * Prepares the payload for an order event.
     *
     * @param WC_Order $order   The WooCommerce order object.
     * @param array    $context Additional context.
     * @return array The payload data.
     */
    private function prepare_order_payload( WC_Order $order, array $context = [] ): array {
        $data = [
            'id'                  => $order->get_id(),
            'order_key'           => $order->get_order_key(),
            'status'              => $order->get_status(), // current status
            'currency'            => $order->get_currency(),
            'total'               => $order->get_total(),
            'total_tax'           => $order->get_total_tax(),
            'shipping_total'      => $order->get_shipping_total(),
            'shipping_tax'        => $order->get_shipping_tax(),
            'customer_id'         => $order->get_customer_id(),
            'customer_note'       => $order->get_customer_note(),
            'billing_address'     => $order->get_address( 'billing' ),
            'shipping_address'    => $order->get_address( 'shipping' ),
            'payment_method'      => $order->get_payment_method(),
            'payment_method_title'=> $order->get_payment_method_title(),
            'date_created'        => $order->get_date_created() ? $order->get_date_created()->date( 'c' ) : null, // ISO 8601
            'date_modified'       => $order->get_date_modified() ? $order->get_date_modified()->date( 'c' ) : null, // ISO 8601
            'date_paid'           => $order->get_date_paid() ? $order->get_date_paid()->date( 'c' ) : null,
            'date_completed'      => $order->get_date_completed() ? $order->get_date_completed()->date( 'c' ) : null,
            'line_items'          => [],
            'shipping_lines'      => [],
            'fee_lines'           => [],
            'coupon_lines'        => [],
            'tax_lines'           => [],
            'metadata'            => $this->get_order_metadata_array($order),
            'customer_ip_address' => $order->get_customer_ip_address(),
            'customer_user_agent' => $order->get_customer_user_agent(),
            'transaction_id'      => $order->get_transaction_id(),
            'context'             => $context, // e.g., old_status, new_status
        ];

        // Line items
        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            $data['line_items'][] = [
                'id'           => $item_id,
                'name'         => $item->get_name(),
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'quantity'     => $item->get_quantity(),
                'sku'          => $product ? $product->get_sku() : null,
                'price'        => $item->get_subtotal(), // Price per unit before discounts
                'total'        => $item->get_total(),    // Total for this line after discounts
                'tax'          => $item->get_total_tax(),
                'meta_data'    => $this->get_item_metadata_array($item),
            ];
        }

        // Shipping lines
        foreach ( $order->get_items( 'shipping' ) as $item_id => $item ) {
            $data['shipping_lines'][] = [
                'id'           => $item_id,
                'method_title' => $item->get_method_title(),
                'method_id'    => $item->get_method_id(),
                'total'        => $item->get_total(),
                'total_tax'    => $item->get_total_tax(),
            ];
        }
        // Fee lines, coupon lines, tax lines can be added similarly if needed by Mottasl.

        // Invoice PDF
        if ( $this->invoice_generator && $this->should_include_invoice( $order, $context ) ) {
            $invoice_pdf_data = $this->get_invoice_data_for_payload( $order );
            if ( $invoice_pdf_data ) {
                $data['invoice_pdf'] = $invoice_pdf_data;
            }
        }

        return apply_filters( 'mottasl_wc_order_payload', $data, $order, $context );
    }

    /**
     * Get order meta data as an array of key-value pairs.
     * Excludes hidden meta (starting with '_').
     *
     * @param WC_Order $order
     * @return array
     */
    private function get_order_metadata_array(WC_Order $order): array {
        $meta_data_array = [];
        $meta_data = $order->get_meta_data();
        foreach ($meta_data as $meta) {
            /** @var \WC_Meta_Data $meta */
            if (strpos($meta->key, '_') !== 0) { // Exclude hidden meta
                $meta_data_array[] = [
                    'key'   => $meta->key,
                    'value' => $meta->value,
                ];
            }
        }
        return $meta_data_array;
    }

    /**
     * Get item meta data as an array of key-value pairs.
     *
     * @param \WC_Order_Item $item
     * @return array
     */
    private function get_item_metadata_array(\WC_Order_Item $item): array {
        $meta_data_array = [];
        $meta_data = $item->get_meta_data(); // Gets all meta
        foreach ($meta_data as $meta) {
             /** @var \WC_Meta_Data $meta */
            $meta_data_array[] = [
                // 'id'    => $meta->id, // WC_Meta_Data object property
                'key'   => $meta->key,
                'label' => $meta->get_display_key(), // Use get_display_key for a nicer label if available
                'value' => $meta->value,
                'display_value' => $meta->get_display_value(),
            ];
        }
        return $meta_data_array;
    }


    /**
     * Determines if an invoice should be generated and included in the payload.
     *
     * @param WC_Order $order   The order object.
     * @param array    $context Event context.
     * @return bool
     */
    private function should_include_invoice( WC_Order $order, array $context ): bool {
        $generate_on_statuses = (array) Helper::get_setting( 'invoice_generate_on_status', ['processing', 'completed'] );
        $current_status = $order->get_status(); // Current status of the order

        // Check if the current order status is one of the statuses for invoice generation
        if ( in_array( $current_status, $generate_on_statuses, true ) ) {
            return true;
        }

        // If the event is an update, check if the *new* status is one for generation
        if ( isset( $context['new_status'] ) && in_array( $context['new_status'], $generate_on_statuses, true ) ) {
            return true;
        }
        return false;
    }

    /**
     * Generates invoice if needed and returns its data for the payload.
     *
     * @param WC_Order $order The order object.
     * @return array|null ['filename' => 'name.pdf', 'content' => 'base64_encoded_pdf_content'] or null.
     */
    private function get_invoice_data_for_payload( WC_Order $order ): ?array {
        if ( ! $this->invoice_generator ) {
            return null;
        }

        $invoice_path = $this->invoice_generator->get_or_generate_invoice( $order->get_id() );

        if ( $invoice_path && file_exists( $invoice_path ) ) {
            $file_content = file_get_contents( $invoice_path );
            if ( $file_content ) {
                return [
                    'filename' => basename( $invoice_path ),
                    'content'  => base64_encode( $file_content ), // Send as base64
                    // 'url' => $this->invoice_generator->get_invoice_url($order->get_id()) // Alternative: send a URL if Mottasl can fetch
                ];
            }
        }
        return null;
    }

    /**
     * Maybe generate an invoice when payment is completed.
     *
     * @param int $order_id
     */
    public function maybe_generate_invoice_on_payment( int $order_id ): void {
        $order = wc_get_order($order_id);
        if (!$order || !$this->invoice_generator) {
            return;
        }

        $generate_on_statuses = (array) Helper::get_setting( 'invoice_generate_on_status', ['processing', 'completed'] );
        // 'processing' or 'completed' are common statuses after payment.
        // This function is hooked to 'woocommerce_payment_complete' which often sets order to 'processing'.
        if ( in_array( $order->get_status(), $generate_on_statuses, true ) ) {
            $this->invoice_generator->get_or_generate_invoice( $order_id, true ); // Force generation if not exists
        }
    }

    /**
     * Maybe generate an invoice when order status changes to one of the configured statuses.
     *
     * @param int    $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function maybe_generate_invoice_on_status_change( int $order_id, string $old_status, string $new_status, WC_Order $order ): void {
        if (!$this->invoice_generator) {
            return;
        }
        $generate_on_statuses = (array) Helper::get_setting( 'invoice_generate_on_status', ['processing', 'completed'] );
        if ( in_array( $new_status, $generate_on_statuses, true ) ) {
            // If status changed to one where invoice should be generated.
            $this->invoice_generator->get_or_generate_invoice( $order_id, true ); // Force generation if not exists
        }
    }
}