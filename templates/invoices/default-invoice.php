<?php
/**
 * Default Invoice HTML Template for Mottasl WooCommerce.
 *
 * Variables available:
 * @var WC_Order $order WooCommerce order object.
 * @var string $store_name
 * @var string $store_address
 * @var string $store_vat
 * @var string $store_logo_url
 * @var string $invoice_number
 * @var string $invoice_date
 * @var string $billing_address_html
 * @var string $shipping_address_html
 * @var string $payment_method_title
 * (and any other variables passed via $template_data in InvoiceGenerator)
 *
 * @package Mottasl_WooCommerce
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Note: mPDF works best with inline styles or a <style> block in the HTML.
// Avoid complex CSS selectors if possible. Floats can be tricky; tables are often more reliable for layout.
?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_locale() ); ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title><?php printf( esc_html__( 'Invoice #%s', 'mottasl-woocommerce' ), esc_html( $invoice_number ) ); ?></title>
    <style type="text/css">
        body {
            font-family: 'DejaVu Sans', sans-serif; /* DejaVu Sans supports many characters */
            font-size: 10pt;
            color: #333;
            line-height: 1.6;
        }
        .invoice-container {
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .header, .footer {
            text-align: center;
            margin-bottom: 20px;
        }
        .store-logo img {
            max-width: 200px;
            max-height: 100px;
            margin-bottom: 10px;
        }
        .store-details, .customer-details {
            margin-bottom: 20px;
        }
        .store-details p, .customer-details p {
            margin: 0;
            padding: 0;
        }
        .invoice-info {
            text-align: right;
            margin-bottom: 20px;
        }
        .invoice-info h1 {
            margin: 0;
            font-size: 1.8em;
            color: #000;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table.items th, table.items td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        table.items th {
            background-color: #f9f9f9;
            font-weight: bold;
        }
        table.items td.quantity, table.items td.price, table.items td.total {
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-top: 20px;
        }
        .totals table {
            width: 50%; /* Or 100% and align right */
            margin-left: auto; /* Aligns table to the right if width < 100% */
            border-collapse: collapse;
        }
        .totals th, .totals td {
            padding: 8px;
            text-align: right;
        }
        .totals th {
            text-align: right;
            width: 60%;
        }
        .notes, .payment-info {
            margin-top: 30px;
            font-size: 0.9em;
        }
        .footer-text {
            margin-top: 50px;
            text-align: center;
            font-size: 0.8em;
            color: #777;
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <table width="100%" style="border-bottom: 1px solid #eee; padding-bottom:10px;">
            <tr>
                <td width="50%" style="vertical-align: top;">
                    <?php if ( ! empty( $store_logo_url ) ) : ?>
                        <div class="store-logo">
                            <img src="<?php echo esc_url( $store_logo_url ); ?>" alt="<?php echo esc_attr( $store_name ); ?> Logo">
                        </div>
                    <?php endif; ?>
                    <div class="store-details">
                        <strong><?php echo esc_html( $store_name ); ?></strong><br>
                        <?php echo wp_kses_post( $store_address ); ?><br>
                        <?php if ( ! empty( $store_vat ) ) : ?>
                            <?php echo esc_html__( 'VAT/Tax ID:', 'mottasl-woocommerce' ); ?> <?php echo esc_html( $store_vat ); ?><br>
                        <?php endif; ?>
                    </div>
                </td>
                <td width="50%" style="vertical-align: top; text-align: right;">
                    <div class="invoice-info">
                        <h1><?php esc_html_e( 'INVOICE', 'mottasl-woocommerce' ); ?></h1>
                        <p><strong><?php esc_html_e( 'Invoice #:', 'mottasl-woocommerce' ); ?></strong> <?php echo esc_html( $invoice_number ); ?></p>
                        <p><strong><?php esc_html_e( 'Order #:', 'mottasl-woocommerce' ); ?></strong> <?php echo esc_html( $order->get_order_number() ); ?></p>
                        <p><strong><?php esc_html_e( 'Date:', 'mottasl-woocommerce' ); ?></strong> <?php echo esc_html( $invoice_date ); ?></p>
                    </div>
                </td>
            </tr>
        </table>

        <table width="100%" style="margin-top: 20px;">
            <tr>
                <td width="50%" style="vertical-align: top;">
                    <div class="customer-details">
                        <strong><?php esc_html_e( 'Bill To:', 'mottasl-woocommerce' ); ?></strong><br>
                        <?php echo wp_kses_post( $billing_address_html ); ?>
                        <?php if ($order->get_billing_email()): ?>
                            <br><?php echo esc_html($order->get_billing_email()); ?>
                        <?php endif; ?>
                        <?php if ($order->get_billing_phone()): ?>
                            <br><?php echo esc_html($order->get_billing_phone()); ?>
                        <?php endif; ?>
                    </div>
                </td>
                <?php if ( $order->has_shipping_address() && $shipping_address_html ) : ?>
                <td width="50%" style="vertical-align: top;">
                    <div class="customer-details">
                        <strong><?php esc_html_e( 'Ship To:', 'mottasl-woocommerce' ); ?></strong><br>
                        <?php echo wp_kses_post( $shipping_address_html ); ?>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        </table>


        <table class="items">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Item', 'mottasl-woocommerce' ); ?></th>
                    <th class="quantity"><?php esc_html_e( 'Qty', 'mottasl-woocommerce' ); ?></th>
                    <th class="price"><?php esc_html_e( 'Unit Price', 'mottasl-woocommerce' ); ?></th>
                    <th class="total"><?php esc_html_e( 'Total', 'mottasl-woocommerce' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $order->get_items() as $item_id => $item ) :
                    $product = $item->get_product();
                ?>
                <tr>
                    <td>
                        <?php echo esc_html( $item->get_name() ); ?>
                        <?php if ( $product && $product->get_sku() ) : ?>
                            <br><small>SKU: <?php echo esc_html( $product->get_sku() ); ?></small>
                        <?php endif; ?>
                        <?php
                        // Display item meta (attributes for variations, etc.)
                        wc_display_item_meta( $item, [
                            'before'    => '<div class="item-meta" style="font-size:0.9em; color: #666;">',
                            'separator' => '<br>',
                            'after'     => '</div>',
                            'echo'      => true,
                            'label_before' => '<strong class="item-meta-label">',
                            'label_after'  => ':</strong> ',
                        ] );
                        ?>
                    </td>
                    <td class="quantity"><?php echo esc_html( $item->get_quantity() ); ?></td>
                    <td class="price"><?php echo wp_kses_post( wc_price( $order->get_item_subtotal( $item, false, true ), array( 'currency' => $order->get_currency() ) ) ); ?></td>
                    <td class="total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <th><?php esc_html_e( 'Subtotal:', 'mottasl-woocommerce' ); ?></th>
                    <td><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></td>
                </tr>
                <?php foreach ( $order->get_order_item_totals() as $key => $total ) :
                    // Skip subtotal as we displayed it, skip payment method.
                    if ( in_array( $key, ['subtotal', 'payment_method'], true ) ) continue;
                ?>
                    <tr>
                        <th><?php echo esc_html( $total['label'] ); ?></th>
                        <td><?php echo wp_kses_post( $total['value'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                 <tr>
                    <th><strong><?php esc_html_e( 'Total:', 'mottasl-woocommerce' ); ?></strong></th>
                    <td><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></td>
                </tr>
            </table>
        </div>

        <?php if ( $order->get_customer_note() ) : ?>
        <div class="notes">
            <strong><?php esc_html_e( 'Customer Note:', 'mottasl-woocommerce' ); ?></strong>
            <p><?php echo wp_kses_post( nl2br( esc_html( $order->get_customer_note() ) ) ); ?></p>
        </div>
        <?php endif; ?>

        <div class="payment-info">
            <strong><?php esc_html_e( 'Payment Method:', 'mottasl-woocommerce' ); ?></strong>
            <p><?php echo esc_html( $payment_method_title ); ?></p>
        </div>

        <div class="footer-text">
            <?php printf( esc_html__( 'Thank you for your business! If you have any questions, please contact us at %s.', 'mottasl-woocommerce' ), esc_html( get_option( 'admin_email' ) ) ); ?>
        </div>
    </div>
</body>
</html>