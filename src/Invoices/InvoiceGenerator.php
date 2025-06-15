<?php
/**
 * Generates PDF invoices for WooCommerce orders.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Invoices
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Invoices;

use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Constants;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Mpdf\Output\Destination;
use WC_Order;

defined( 'ABSPATH' ) || exit;

class InvoiceGenerator {

    /**
     * Meta key to store the generated invoice filename on the order.
     */
    const INVOICE_FILENAME_META_KEY = Constants::META_PREFIX . 'invoice_filename';

    /**
     * Generates an invoice PDF for the given order ID if it doesn't already exist,
     * or if $force_regenerate is true.
     *
     * @param int  $order_id The ID of the WooCommerce order.
     * @param bool $force_regenerate Whether to regenerate the invoice even if one exists.
     * @return string|false Path to the generated PDF file, or false on failure.
     */
    public function get_or_generate_invoice( int $order_id, bool $force_regenerate = false ): string|false {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            error_log( "Mottasl InvoiceGenerator: Could not find order #{$order_id}." );
            return false;
        }

        $existing_filename = $order->get_meta( self::INVOICE_FILENAME_META_KEY, true );
        $storage_path = Helper::get_invoice_storage_path();

        if ( ! $storage_path ) {
            error_log( "Mottasl InvoiceGenerator: Invoice storage path is not available." );
            return false;
        }

        $invoice_filepath = '';
        if ( ! empty( $existing_filename ) ) {
            $invoice_filepath = trailingslashit( $storage_path ) . $existing_filename;
        }

        // If not forcing regeneration and the file exists, return its path.
        if ( ! $force_regenerate && ! empty( $invoice_filepath ) && file_exists( $invoice_filepath ) ) {
            return $invoice_filepath;
        }

        // Proceed to generate a new invoice PDF.
        try {
            $pdf_content_html = $this->render_invoice_html( $order );
            if ( empty( $pdf_content_html ) ) {
                error_log( "Mottasl InvoiceGenerator: Failed to render HTML for order #{$order_id}." );
                return false;
            }

            // Configure mPDF
            // For full list of options: https://mpdf.github.io/reference/mpdf-variables/overview.html
            $mpdf_config = [
                'mode' => 'utf-8',
                'format' => 'A4',
                'margin_left' => 10,
                'margin_right' => 10,
                'margin_top' => 15,
                'margin_bottom' => 15,
                'tempDir' => trailingslashit( $storage_path ) . 'tmp' // mPDF needs a writable temp directory
            ];

            // Ensure tempDir exists and is writable
            if (!file_exists($mpdf_config['tempDir'])) {
                wp_mkdir_p($mpdf_config['tempDir']);
                Helper::secure_directory($mpdf_config['tempDir']); // Secure it
            }
             if (!is_writable($mpdf_config['tempDir'])) {
                error_log("Mottasl InvoiceGenerator: mPDF temp directory is not writable: " . $mpdf_config['tempDir']);
                // Fallback to mPDF default if plugin's tmp dir fails
                unset($mpdf_config['tempDir']);
            }


            $mpdf = new Mpdf( $mpdf_config );
            $mpdf->SetTitle( sprintf( __( 'Invoice #%s', 'mottasl-woocommerce' ), $order->get_order_number() ) );
            $mpdf->SetAuthor( Helper::get_setting( 'invoice_store_name', get_bloginfo( 'name' ) ) );
            // $mpdf->SetDisplayMode('fullpage');

            // Optional: Add a header/footer
            // $mpdf->SetHTMLHeader($this->get_invoice_header_html($order));
            // $mpdf->SetHTMLFooter($this->get_invoice_footer_html($order));

            $mpdf->WriteHTML( $pdf_content_html );

            $new_filename = $this->generate_invoice_filename( $order );
            $new_filepath = trailingslashit( $storage_path ) . $new_filename;

            // Output the PDF to a file
            $mpdf->Output( $new_filepath, Destination::FILE );

            if ( file_exists( $new_filepath ) ) {
                // Save the new filename to order meta
                $order->update_meta_data( self::INVOICE_FILENAME_META_KEY, $new_filename );
                $order->save_meta_data();
                return $new_filepath;
            } else {
                error_log( "Mottasl InvoiceGenerator: mPDF failed to save the file to {$new_filepath} for order #{$order_id}." );
                return false;
            }

        } catch ( MpdfException $e ) {
            error_log( "Mottasl InvoiceGenerator MpdfException for order #{$order_id}: " . $e->getMessage() );
            return false;
        } catch ( \Exception $e ) {
            error_log( "Mottasl InvoiceGenerator Exception for order #{$order_id}: " . $e->getMessage() );
            return false;
        }
    }

    /**
     * Renders the HTML content for the invoice.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The HTML content for the invoice.
     */
    private function render_invoice_html( WC_Order $order ): string {
        $template_path = MOTTASL_WC_PLUGIN_PATH . 'templates/invoices/default-invoice.php';
        $custom_template_path = get_stylesheet_directory() . '/mottasl-woocommerce/invoices/default-invoice.php'; // For theme overrides

        if ( file_exists( $custom_template_path ) ) {
            $template_to_load = $custom_template_path;
        } elseif ( file_exists( $template_path ) ) {
            $template_to_load = $template_path;
        } else {
            error_log( "Mottasl InvoiceGenerator: Invoice template not found." );
            return ''; // Or a default basic HTML string
        }

        // Data to pass to the template
        $template_data = [
            'order'                  => $order,
            'store_name'             => Helper::get_setting( 'invoice_store_name', get_bloginfo( 'name' ) ),
            'store_address'          => nl2br( esc_html( Helper::get_setting( 'invoice_store_address', '' ) ) ),
            'store_vat'              => Helper::get_setting( 'invoice_store_vat', '' ),
            'store_logo_url'         => Helper::get_setting( 'invoice_store_logo_url', '' ),
            'invoice_number'         => $order->get_order_number(), // Or a custom sequential invoice number if implemented
            'invoice_date'           => $order->get_date_paid() ? $order->get_date_paid()->date_i18n( get_option( 'date_format' ) ) : $order->get_date_created()->date_i18n( get_option( 'date_format' ) ),
            'billing_address_html'   => $order->get_formatted_billing_address(),
            'shipping_address_html'  => $order->get_formatted_shipping_address(),
            'payment_method_title'   => $order->get_payment_method_title(),
            // Add more data as needed by your template
        ];

        ob_start();
        // Make $order and $template_data available in the template scope
        extract( $template_data, EXTR_SKIP );
        include $template_to_load;
        return ob_get_clean();
    }

    /**
     * Generates a unique filename for the invoice PDF.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @return string The generated filename.
     */
    private function generate_invoice_filename( WC_Order $order ): string {
        // Example: invoice-123-2023-10-27.pdf
        $filename = sprintf(
            'invoice-%s-%s.pdf',
            $order->get_order_number(),
            current_time( 'Y-m-d' ) // Or a more unique hash if needed
        );
        return Helper::sanitize_filename( $filename );
    }

    /**
     * Gets the public URL for a generated invoice.
     *
     * @param int $order_id
     * @return string|false URL or false if not found/available.
     */
    public function get_invoice_url( int $order_id ): string|false {
        $order = wc_get_order($order_id);
        if (!$order) return false;

        $filename = $order->get_meta(self::INVOICE_FILENAME_META_KEY, true);
        if (empty($filename)) return false;

        $storage_path = Helper::get_invoice_storage_path();
        if (!$storage_path || !file_exists(trailingslashit($storage_path) . $filename)) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $invoice_dir_name = defined('\Mottasl\WooCommerce\Constants::PDF_STORAGE_DIRECTORY') ? \Mottasl\WooCommerce\Constants::PDF_STORAGE_DIRECTORY : 'mottasl-invoices';

        return trailingslashit($upload_dir['baseurl']) . $invoice_dir_name . '/' . $filename;
    }

    // Optional: HTML for header/footer if using mPDF's SetHTMLHeader/Footer
    /*
    private function get_invoice_header_html(WC_Order $order): string {
        $store_name = Helper::get_setting('invoice_store_name', get_bloginfo('name'));
        return "<div style='text-align: right; font-size: 9pt;'>{$store_name} - Invoice</div>";
    }

    private function get_invoice_footer_html(WC_Order $order): string {
        return "<div style='text-align: center; font-size: 9pt;'>Page {PAGENO} of {nbpg}</div>";
    }
    */
}