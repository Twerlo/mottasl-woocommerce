=== Mottasl for WooCommerce ===
Contributors:        (mottasl.twerlo), twerlo
Tags:                orders, abandoned cart, pdf invoice, whatsapp
Requires at least:   5.6
Tested up to:        [6.4]
Requires PHP:        7.4
WC requires at least:6.0
WC tested up to:     [6.9]
Stable tag:          1.0.0
License:             GPL-3.0-or-later
License URI:         https://www.gnu.org/licenses/gpl-3.0.html

Integrate WooCommerce with Mottasl.ai to send order status updates, abandoned cart events, and generate PDF invoices for your customers.

== Description ==

Mottasl for WooCommerce seamlessly connects your online store with the Mottasl.ai platform. This powerful integration allows you to:

*   **Sync Order Data:** Automatically send new orders and order status updates (e.g., processing, completed, refunded) to Mottasl.ai.
*   **Track Abandoned Carts:** Identify and send abandoned cart events to Mottasl.ai, enabling you to recover potentially lost sales.
*   **Generate PDF Invoices:** Automatically create professional PDF invoices for your WooCommerce orders. Customize invoice details like store name, address, logo, and VAT/Tax ID.
*   **Flexible Configuration:** Choose which order statuses trigger invoice generation and enable/disable specific synchronization features.

By leveraging this integration, you can enhance your customer relationship management (CRM), automate marketing workflows, and gain deeper insights into your sales data through the Mottasl.ai platform.

**Key Features:**

*   Real-time synchronization of order events.
*   Configurable abandoned cart detection and reporting.
*   Automated PDF invoice generation with customizable store information and logo.
*   Option to select specific order statuses for invoice creation.
*   Easy-to-use settings page within WordPress admin.
*   Secure communication with the Mottasl.ai API.
*   Clean and maintainable codebase built with modern PHP practices.

To use this plugin, you will need an active account with [Mottasl.ai](https://mottasl.ai) (link to their website or relevant integration page).

== Installation ==

1.  **Automatic Installation (Recommended):**
    *   Log in to your WordPress admin panel.
    *   Navigate to `Plugins > Add New`.
    *   Search for "Mottasl for WooCommerce".
    *   Click "Install Now" and then "Activate".

2.  **Manual Installation:**
    *   Download the plugin ZIP file from the WordPress.org plugin repository (or from where you obtained it).
    *   Log in to your WordPress admin panel.
    *   Navigate to `Plugins > Add New`.
    *   Click the "Upload Plugin" button at the top of the page.
    *   Select the downloaded ZIP file and click "Install Now".
    *   After installation, click "Activate".

3.  **Installation via FTP:**
    *   Download the plugin ZIP file and extract its contents.
    *   Using an FTP client, upload the `mottasl` folder to the `wp-content/plugins/` directory on your server.
    *   Log in to your WordPress admin panel.
    *   Navigate to `Plugins > Installed Plugins`.
    *   Find "Mottasl for WooCommerce" and click "Activate".

**After Activation:**

1.  Navigate to `Mottasl > Settings` in your WordPress admin menu.
2.  Configure the required settings:
    *   (If applicable) Enter your Mottasl Business ID.
    *   Set up your store details for invoice generation (Store Name, Address, VAT/Tax ID, Logo).
    *   Choose the order statuses for automatic invoice generation.
    *   Enable or disable Order Sync and Abandoned Cart tracking as needed.
3.  Save your settings. The plugin will start sending installation confirmation and subsequent events to Mottasl.ai.

== Frequently Asked Questions ==

= Does this plugin require a Mottasl.ai account? =

Yes, this plugin is designed to integrate your WooCommerce store with the Mottasl.ai platform. You will need an active Mottasl.ai account to utilize the features.

= Where can I find my Mottasl Business ID? =

(Provide instructions or a link to Mottasl.ai documentation on where users can find their API key, if applicable. If not applicable, remove this question or state it's not needed.)
Please refer to the Mottasl.ai platform documentation or contact their support for details on obtaining your API key.

= Which order statuses trigger invoice generation? =

You can configure this in the plugin settings under `Mottasl > Settings > Invoice Settings`. By default, invoices are typically generated for "Processing" and "Completed" orders, but you can select multiple statuses.

= How does abandoned cart tracking work? =

The plugin monitors user cart activity. If a cart remains inactive for a configurable period (default is 1 hour), and no order has been placed, it's considered abandoned and the event is sent to Mottasl.ai. For guests, email capture on the checkout page helps identify them.

= Can I customize the invoice template? =

Yes. The plugin uses a default invoice template. To customize it, you can copy the `default-invoice.php` file from `wp-content/plugins/mottasl/templates/invoices/` to your active theme's directory: `wp-content/themes/your-theme-name/mottasl-woocommerce/invoices/default-invoice.php`. Then, you can edit the copied file.

= Where are the generated PDF invoices stored? =

Generated PDF invoices are stored in a dedicated folder within your WordPress uploads directory (`wp-content/uploads/mottasl-invoices/`).

== Screenshots ==

1.  **Mottasl Settings Page - General Tab:** Shows the general settings available for the plugin. (Upload as `screenshot-1.png` or `.jpg`)
2.  **Mottasl Settings Page - Invoice Tab:** Displays options for configuring invoice details like store name, address, logo, and VAT. (Upload as `screenshot-2.png` or `.jpg`)
3.  **Mottasl Settings Page - Synchronization Tab:** Shows toggles for enabling order sync, abandoned cart tracking, and setting the timeout. (Upload as `screenshot-3.png` or `.jpg`)
4.  **Example Generated PDF Invoice:** A sample of what the generated PDF invoice looks like. (Upload as `screenshot-4.png` or `.jpg`)

(You'll need to create these actual screenshots and place them in an `assets` folder in your plugin's SVN repository root on WordPress.org, not within the plugin zip itself. For local development, you can just note them.)

== Changelog ==

= 1.0.0 - YYYY-MM-DD =
*   Initial release.
*   Features: Order status synchronization, abandoned cart tracking with custom table, PDF invoice generation, installation confirmation.

== Upgrade Notice ==

= 1.0.0 =
Initial release of the Mottasl for WooCommerce integration plugin.

== Support ==

For support, please contact [Mottasl.ai support](link-to-mottasl-support-page) or visit our [plugin support forum on WordPress.org](link-will-be-generated-on-wp.org).

== Developer Documentation ==

(Optional: If you have more detailed developer docs, link them here or provide a brief overview of hooks/filters.)

**Available Filters:**

*   `mottasl_wc_api_non_blocking_request (bool $non_blocking, string $webhook_topic)`: Filter whether API requests should be non-blocking for a specific topic.
*   `mottasl_wc_block_order_event (bool $block, WC_Order $order, string $event_topic, array $context)`: Filter to block sending a specific order event.
*   `mottasl_wc_order_payload (array $payload, WC_Order $order, array $context)`: Filter the payload for order events.
*   `mottasl_wc_abandoned_cart_payload (array $payload, object $cart_record)`: Filter the payload for abandoned cart events.
*   `mottasl_wc_abandoned_cart_batch_size (int $batch_size)`: Filter the number of abandoned carts processed per cron run.
*   `mottasl_wc_invoice_template (string $template, WC_Order $order)`: Filter the invoice template used for generating PDF invoices.
*   `mottasl_wc_invoice_data (array $data, WC_Order $order)`: Filter the data used to generate the PDF invoice.
*   `mottasl_wc_invoice_logo (string $logo_url, WC_Order $order)`: Filter the logo URL used in the PDF invoice.
*   `mottasl_wc_invoice_store_info (array $store_info, WC_Order $order)`: Filter the store information used in the PDF invoice.
*   `mottasl_wc_invoice_statuses (array $statuses)`: Filter the order statuses that trigger invoice generation.
*   `mottasl_wc_invoice_generate (bool $generate, WC_Order $order)`: Filter whether to generate an invoice for a specific order.
*   `mottasl_wc_invoice_save_path (string $path, WC_Order $order)`: Filter the path where the PDF invoice is saved.
*   `mottasl_wc_invoice_filename (string $filename, WC_Order $order)`: Filter the filename of the generated PDF invoice.