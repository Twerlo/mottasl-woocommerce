<?php
/**
 * Handles WooCommerce abandoned cart events and sends data to Mottasl.ai.
 * Uses a custom database table for tracking.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Events
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Events;

use Mottasl\WooCommerce\Integrations\MottaslAPI;
use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Constants;
use WC_Cart;
use WC_Session_Handler; // Not directly used now but good for context
use WC_Order;

defined( 'ABSPATH' ) || exit;

class AbandonedCartHandler {

    private MottaslAPI $api_handler;
    const CRON_HOOK = 'mottasl_wc_check_abandoned_carts';
    const DEFAULT_ABANDONMENT_TIMEOUT = 3600; // 1 hour
    private string $table_name;

    public function __construct() {
        global $wpdb;
        $this->api_handler = new MottaslAPI();
        $this->table_name = $wpdb->prefix . 'mottasl_abandoned_carts';
    }

    public function init(): void {
        if ( 'yes' !== Helper::get_setting( 'enable_abandoned_cart', 'yes' ) ) {
            $this->unschedule_cron();
            return;
        }

        // Track cart updates
        add_action( 'woocommerce_cart_updated', [ $this, 'track_or_update_cart_activity' ], 20 );
        add_action( 'woocommerce_add_to_cart', [ $this, 'track_or_update_cart_activity_on_add' ], 20, 6 );
        add_action( 'woocommerce_after_cart_item_quantity_update', [ $this, 'track_or_update_cart_activity' ], 20 );
        add_action( 'woocommerce_cart_item_removed', [ $this, 'track_or_update_cart_activity' ], 20 );
        add_action( 'woocommerce_cart_emptied', [ $this, 'delete_tracked_cart_for_current_user' ], 20 );
        add_action('template_redirect', [$this, 'maybe_recover_cart']);
        // Try to capture email from checkout form
        add_action( 'woocommerce_after_checkout_billing_form', [ $this, 'capture_checkout_email_js' ] );
        add_action( 'wp_ajax_mottasl_capture_email', [ $this, 'ajax_capture_email' ] );
        add_action( 'wp_ajax_nopriv_mottasl_capture_email', [ $this, 'ajax_capture_email' ] );

        // Order completion
        add_action( 'woocommerce_checkout_order_processed', [ $this, 'mark_cart_as_recovered' ], 10, 3 );
        add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'mark_cart_as_recovered_block' ], 10, 1 );

        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + 60, 'fifteen_minutes', self::CRON_HOOK ); // Start 1 min in future
        }
        add_action( self::CRON_HOOK, [ $this, 'process_potential_abandoned_carts' ] );
        add_filter( 'cron_schedules', [ $this, 'add_custom_cron_intervals' ] );
    }

    public function add_custom_cron_intervals( array $schedules ): array {
        // (Same as before)
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = ['interval' => 5 * MINUTE_IN_SECONDS, 'display'  => esc_html__( 'Every Five Minutes' )];
        }
        if (!isset($schedules['fifteen_minutes'])) {
            $schedules['fifteen_minutes'] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display'  => esc_html__( 'Every Fifteen Minutes' )];
        }
        return $schedules;
    }

    public function track_or_update_cart_activity_on_add($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data): void {
        // WC()->cart might not be fully updated yet on this hook for totals, but items are there.
        // It's better to rely on 'woocommerce_cart_updated' which fires after calculations.
        // For simplicity, we can call the main tracker here, or just let 'woocommerce_cart_updated' handle it.
        // To ensure it's caught if 'woocommerce_cart_updated' doesn't fire (e.g. only one item added, then user leaves)
        if (WC()->cart && !WC()->cart->is_empty()) {
            $this->save_cart_to_db(WC()->cart);
        }
    }

    public function track_or_update_cart_activity(): void {
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            $this->save_cart_to_db( WC()->cart );
        } else {
            // If cart becomes empty, we can delete the record or mark it inactive.
            $this->delete_tracked_cart_for_current_user();
        }
    }

    /**
     * Saves or updates the current user/guest cart in the custom table.
     *
     * @param WC_Cart $cart
     */
    private function save_cart_to_db( WC_Cart $cart ): void {
        global $wpdb;

        $current_time_gmt = current_time( 'mysql', true );
        $user_id = get_current_user_id(); // 0 if guest
        $session_id = WC()->session ? WC()->session->get_customer_id() : null;

        if ( ! $user_id && ! $session_id ) {
            return; // Cannot track without user_id or session_id
        }

        $cart_contents_data = $this->get_cart_item_details_for_db( $cart );
        $cart_totals_data = [
            'subtotal'      => $cart->get_cart_subtotal(false), // Exclude tax
            'total'         => $cart->get_total('edit'), // Raw total
            'currency'      => get_woocommerce_currency(),
            'coupon_codes'  => $cart->get_applied_coupons(),
        ];

        $data = [
            'user_id'         => $user_id,
            'session_id'      => $session_id,
            // Email might be updated later via AJAX or from existing record
            'cart_hash'       => $cart->get_cart_hash(),
            'cart_contents'   => wp_json_encode( $cart_contents_data ),
            'cart_totals'     => wp_json_encode( $cart_totals_data ),
            'status'          => 'active', // Reset to active on any interaction
            'last_updated_at' => $current_time_gmt,
        ];

        // Try to find an existing active cart for this user/session
        $existing_cart_id = $this->get_active_cart_id( $user_id, $session_id );

        if ( $existing_cart_id ) {
            // Update existing record
            // Retain email if it exists and not provided in current data
            $existing_email = $wpdb->get_var($wpdb->prepare("SELECT email FROM {$this->table_name} WHERE id = %d", $existing_cart_id));
            if (!empty($existing_email) && empty($data['email'])) {
                $data['email'] = $existing_email;
            }

            $wpdb->update(
                $this->table_name,
                $data,
                [ 'id' => $existing_cart_id ],
                $this->get_db_data_formats( $data ),
                [ '%d' ]
            );
        } else {
            // Insert new record
            $data['created_at'] = $current_time_gmt;
            // If user is logged in, get their email
            if ($user_id) {
                $user_info = get_userdata($user_id);
                $data['email'] = $user_info->user_email;
            }
            // Email for guests might come from AJAX capture

            $wpdb->insert(
                $this->table_name,
                $data,
                $this->get_db_data_formats( $data )
            );
        }
    }

    /**
     * Get the ID of an active cart for the user/session.
     */
    private function get_active_cart_id( int $user_id, ?string $session_id ): ?int {
        global $wpdb;
        if ( $user_id > 0 ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE user_id = %d AND status = 'active' ORDER BY last_updated_at DESC LIMIT 1",
                $user_id
            ) );
        } elseif ( $session_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$this->table_name} WHERE session_id = %s AND user_id = 0 AND status = 'active' ORDER BY last_updated_at DESC LIMIT 1",
                $session_id
            ) );
        }
        return null;
    }


    /**
     * Helper to get cart item details in a DB-storable format.
     * @param WC_Cart $cart
     * @return array
     */
    private function get_cart_item_details_for_db(WC_Cart $cart): array {
        // Same as get_cart_item_details() from previous version, ensure it returns serializable data
        $items = [];
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data']; // Already an object
            if (!$_product || !$_product->exists() || $cart_item['quantity'] <= 0) {
                continue;
            }
            $items[] = [
                'product_id'   => $_product->get_id(),
                'product_name' => $_product->get_name(),
                'sku'          => $_product->get_sku(),
                'quantity'     => $cart_item['quantity'],
                'line_subtotal'=> $cart_item['line_subtotal'], // Price for the quantity of this item, before item-specific discounts but after sale price
                'line_total'   => $cart_item['line_total'],    // Price for the quantity of this item, after item-specific discounts
                'variation_id' => $cart_item['variation_id'],
                'variation_attributes' => $cart_item['variation_id'] ? wc_get_formatted_variation($_product, true, false, false) : null, // Get as array
            ];
        }
        return $items;
    }


    public function delete_tracked_cart_for_current_user(): void {
        global $wpdb;
        $user_id = get_current_user_id();
        $session_id = WC()->session ? WC()->session->get_customer_id() : null;

        if ( $user_id > 0 ) {
            $wpdb->delete( $this->table_name, [ 'user_id' => $user_id, 'status' => 'active' ], [ '%d', '%s' ] );
        } elseif ( $session_id ) {
            $wpdb->delete( $this->table_name, [ 'session_id' => $session_id, 'user_id' => 0, 'status' => 'active' ], [ '%s', '%d', '%s' ] );
        }
    }

    public function mark_cart_as_recovered( int $order_id, array $posted_data, WC_Order $order ): void {
        global $wpdb;
        $user_id = $order->get_user_id();
        $session_id = $order->get_meta( '_customer_user_agent' ) ? md5( $order->get_customer_ip_address() . $order->get_meta( '_customer_user_agent' ) ) : null; // Attempt to get session like ID for guest
        // A more reliable way for guests would be if WC stores its session_id with the order, or if we passed it from checkout.
        // For now, we prioritize user_id, then email.

        $where_clauses = [];
        $where_values = [];

        if ( $user_id ) {
            $where_clauses[] = "user_id = %d";
            $where_values[] = $user_id;
        } elseif ( $order->get_billing_email() ) {
            $where_clauses[] = "email = %s AND user_id = 0"; // Only for guests
            $where_values[] = $order->get_billing_email();
        }
        // Could also try to match by session_id if available and reliable

        if (empty($where_clauses)) return;

        $where_sql = implode(' OR ', array_map(fn($clause) => "($clause)", $where_clauses));

        $wpdb->query( $wpdb->prepare(
            "UPDATE {$this->table_name} SET status = 'recovered' WHERE ({$where_sql}) AND status != 'recovered'",
            ...$where_values
        ) );
    }

    public function mark_cart_as_recovered_block( WC_Order $order ): void {
        // Similar logic to mark_cart_as_recovered
        $this->mark_cart_as_recovered($order->get_id(), [], $order);
    }


    /**
     * JavaScript to capture email from checkout billing form.
     */
    public function capture_checkout_email_js(): void {
        if (is_user_logged_in() || !WC()->checkout() || !WC()->checkout()->is_checkout()) { // Only for guests on checkout
            return;
        }
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                var mottaslCaptureEmailTimeout;
                $('#billing_email').on('keyup change', function() {
                    clearTimeout(mottaslCaptureEmailTimeout);
                    var email = $(this).val();
                    // Basic email validation
                    if (email.length > 5 && email.includes('@') && email.includes('.')) {
                        mottaslCaptureEmailTimeout = setTimeout(function() {
                            $.ajax({
                                type: 'POST',
                                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                                data: {
                                    action: 'mottasl_capture_email',
                                    email: email,
                                    nonce: '<?php echo esc_js(wp_create_nonce('mottasl_capture_email_nonce')); ?>'
                                },
                                // success: function(response) { console.log('Mottasl email captured:', response); },
                                // error: function(error) { console.log('Mottasl email capture error:', error); }
                            });
                        }, 1000); // Debounce: wait 1 second after typing stops
                    }
                });
            });
        </script>
        <?php
    }

    /**
     * AJAX handler to update guest cart with email.
     */
    public function ajax_capture_email(): void {
        check_ajax_referer( 'mottasl_capture_email_nonce', 'nonce' );

        if ( is_user_logged_in() ) {
            wp_send_json_error( [ 'message' => 'User is logged in.' ] );
            return;
        }

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : null;
        if ( ! is_email( $email ) ) {
            wp_send_json_error( [ 'message' => 'Invalid email.' ] );
            return;
        }

        $session_id = WC()->session ? WC()->session->get_customer_id() : null;
        if ( ! $session_id ) {
            wp_send_json_error( [ 'message' => 'No session.' ] );
            return;
        }

        global $wpdb;
        $cart_id = $this->get_active_cart_id( 0, $session_id );

        if ( $cart_id ) {
            $wpdb->update(
                $this->table_name,
                [ 'email' => $email ],
                [ 'id' => $cart_id ],
                [ '%s' ],
                [ '%d' ]
            );
            wp_send_json_success( [ 'message' => 'Email updated for cart ID ' . $cart_id ] );
        } else {
            // Optionally, create a cart record if one doesn't exist but user typed email
            // This might happen if they only typed email but cart hooks didn't fire yet.
            // For now, we only update existing active carts.
            wp_send_json_success( [ 'message' => 'No active cart found for session to update email.' ] );
        }
    }


    public function process_potential_abandoned_carts(): void {
        global $wpdb;
        $abandonment_timeout_seconds = (int) Helper::get_setting( 'abandoned_cart_timeout', self::DEFAULT_ABANDONMENT_TIMEOUT );
        $cutoff_time_gmt = gmdate( 'Y-m-d H:i:s', time() - $abandonment_timeout_seconds );

        $carts_to_process = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE status = 'active' AND last_updated_at < %s ORDER BY last_updated_at ASC LIMIT 100", // Process in batches
            $cutoff_time_gmt
        ) );

        if ( empty( $carts_to_process ) ) {
            return;
        }

        foreach ( $carts_to_process as $cart_record ) {
            // Double check if order was placed since last_updated_at (e.g. if mark_cart_as_recovered hook failed)
            $has_recent_order = false;
            if ( ! empty( $cart_record->user_id ) ) {
                $has_recent_order = $this->db_has_recent_order( (int) $cart_record->user_id, null, $cart_record->last_updated_at );
            } elseif ( ! empty( $cart_record->email ) ) {
                $has_recent_order = $this->db_has_recent_order( 0, $cart_record->email, $cart_record->last_updated_at );
            }

            if ( $has_recent_order ) {
                $wpdb->update( $this->table_name, [ 'status' => 'recovered' ], [ 'id' => $cart_record->id ], ['%s'], ['%d'] );
                continue;
            }

            // Send event
            $this->send_abandoned_cart_event_from_db_record( $cart_record );

            // Update status to 'abandoned_sent'
            $wpdb->update(
                $this->table_name,
                [ 'status' => 'abandoned_sent' ],
                [ 'id' => $cart_record->id ],
                [ '%s' ], // format for data
                [ '%d' ]  // format for where
            );
        }
    }


    /**
     * Check DB for recent order more efficiently.
     */
    private function db_has_recent_order( int $user_id, ?string $email, string $cart_last_updated_gmt ): bool {
    $args = [
        'limit'       => 1,
        'orderby'     => 'date',
        'order'       => 'DESC',
        'date_created'=> '>' . strtotime($cart_last_updated_gmt), // wc_get_orders expects timestamp or Y-m-d for date queries
        'status'      => wc_get_is_paid_statuses(), // Get paid statuses
    ];

    if (empty($args['status'])) { // Fallback if no paid statuses defined
        $args['status'] = ['processing', 'completed'];
    }
    // Remove 'wc-' prefix if wc_get_is_paid_statuses() adds it and wc_get_orders expects them without
    // (wc_get_orders generally handles both with and without 'wc-')

    if ( $user_id > 0 ) {
        $args['customer_id'] = $user_id;
    } elseif ( $email ) {
        $args['customer'] = $email; // 'customer' can be user ID, email, or an array of emails/IDs
    } else {
        return false; // Cannot check without user_id or email
    }

    $orders = wc_get_orders( $args );
    return ! empty( $orders );
}


    private function send_abandoned_cart_event_from_db_record( object $cart_record ): void {
        $cart_contents = json_decode( $cart_record->cart_contents, true );
        $cart_totals = json_decode( $cart_record->cart_totals, true );

        $payload = [
            'cart_db_id'   => $cart_record->id,
            'cart_hash'    => $cart_record->cart_hash,
            'customer_type'=> $cart_record->user_id > 0 ? 'user' : 'guest',
            'user_id'      => $cart_record->user_id ?: null,
            'session_id'   => $cart_record->session_id,
            'email'        => $cart_record->email,
            'line_items'   => $cart_contents ?: [],
            'totals'       => $cart_totals ?: [],
            'currency'     => $cart_totals['currency'] ?? get_woocommerce_currency(),
            'abandoned_at' => mysql2date( 'c', $cart_record->last_updated_at ), // ISO 8601
            'created_at'   => mysql2date( 'c', $cart_record->created_at ),
            'store_url'    => Helper::get_store_url(), // Adding store_url here for Mottasl context
            'recover_url'  => $this->get_cart_recovery_url($cart_record),
        ];

        // Add customer name if available
        if ($cart_record->user_id > 0) {
            $user_info = get_userdata($cart_record->user_id);
            if ($user_info) {
                $payload['billing_first_name'] = $user_info->billing_first_name ?: $user_info->first_name;
                $payload['billing_last_name'] = $user_info->billing_last_name ?: $user_info->last_name;
            }
        }
        // For guests, name might be harder to get reliably at this stage unless captured earlier

        $this->api_handler->send_event(
            Constants::EVENT_TOPIC_ABANDONED_CART,
            apply_filters('mottasl_wc_abandoned_cart_payload', $payload, $cart_record),
            Helper::get_store_url() // store_url is also in payload now
        );
    }

    /**
     * Generate a cart recovery URL.
     * This is a basic example; more sophisticated methods exist.
     *
     * @param object $cart_record
     * @return string
     */
    private function get_cart_recovery_url(object $cart_record): string {
         global $wpdb;
        // Option 1: Simple link to cart page (user must be logged in or session must match)
        // return wc_get_cart_url();

        // Option 2: Custom endpoint that reconstructs the cart
        // This requires creating a specific token and an endpoint to handle it.
        $token = wp_hash( $cart_record->id . $cart_record->session_id . NONCE_KEY ); // Simple token
        // Store token with cart record if needed for validation, or validate based on hash.
        $wpdb->update($this->table_name, ['recovery_token' => $token], ['id' => $cart_record->id]);

        return add_query_arg(
            [
                'mottasl_recover_cart' => base64_encode($cart_record->id), // Obfuscate ID a bit
                'token' => $token,
            ],
            home_url('/') // Or wc_get_page_permalink('shop')
        );
    }

    /**
     * Handles cart recovery attempts. Hook this to 'template_redirect' or 'init'.
     * Example: add_action('template_redirect', [$this, 'maybe_recover_cart']);
     */
    public function maybe_recover_cart(): void {
        if (isset($_GET['mottasl_recover_cart']) && isset($_GET['token'])) {
            $cart_db_id = base64_decode(sanitize_text_field(wp_unslash($_GET['mottasl_recover_cart'])));
            $received_token = sanitize_text_field(wp_unslash($_GET['token']));

            if (!is_numeric($cart_db_id)) return;

            global $wpdb;
            $cart_record = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_name} WHERE id = %d AND status IN ('active', 'abandoned_sent')", $cart_db_id));

            if (!$cart_record) {
                wc_add_notice(__('Cart recovery link is invalid or expired.', 'mottasl-woocommerce'), 'error');
                wp_redirect(wc_get_cart_url());
                exit;
            }

            // Validate token
            $expected_token = wp_hash( $cart_record->id . $cart_record->session_id . NONCE_KEY );
            if (!hash_equals($expected_token, $received_token)) {
                 wc_add_notice(__('Cart recovery link is invalid (token mismatch).', 'mottasl-woocommerce'), 'error');
                 wp_redirect(wc_get_cart_url());
                 exit;
            }

            // If user is different from cart owner, handle carefully
            if ($cart_record->user_id > 0 && get_current_user_id() != $cart_record->user_id) {
                // Option: Log them out and ask to log in as cart owner
                // Option: If current user has an active cart, ask if they want to merge or replace
                wc_add_notice(__('This cart belongs to another user. Please log in as the correct user to recover this cart.', 'mottasl-woocommerce'), 'error');
                wp_redirect(wc_get_page_permalink('myaccount')); // Or login page
                exit;
            }

            // Clear current cart
            WC()->cart->empty_cart(true);

            // Rebuild cart from stored data
            $cart_items = json_decode($cart_record->cart_contents, true);
            if (is_array($cart_items)) {
                foreach ($cart_items as $item) {
                    WC()->cart->add_to_cart($item['product_id'], $item['quantity'], $item['variation_id'] ?? 0, $item['variation_attributes'] ?? []);
                }
            }

            // Update cart status in DB (optional, could be 'recovered_attempted')
            // $wpdb->update($this->table_name, ['status' => 'recovered_via_link'], ['id' => $cart_record->id]);

            wc_add_notice(__('Your cart has been restored.', 'mottasl-woocommerce'), 'success');
            wp_redirect(wc_get_cart_url());
            exit;
        }
    }

    private function get_db_data_formats( array $data ): array {
        $formats = [];
        foreach ( $data as $key => $value ) {
            if ( is_int( $value ) ) {
                $formats[] = '%d';
            } elseif ( is_float( $value ) ) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }

    public function unschedule_cron(): void {
        // (Same as before)
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }
}