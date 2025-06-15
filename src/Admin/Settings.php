<?php

/**
 * Handles the admin settings page for Mottasl WooCommerce integration.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Admin
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Admin;

use Mottasl\WooCommerce\Constants;
use Mottasl\WooCommerce\Utils\Helper;
use Mottasl\WooCommerce\Integrations\MottaslAPI;

defined('ABSPATH') || exit;

class Settings
{

    /**
     * Option group.
     * @var string
     */
    private string $option_group = 'mottasl_wc_options_group';

    /**
     * Option name where settings are stored.
     * @var string
     */
    private string $option_name = Constants::SETTINGS_OPTION_KEY; // 'mottasl_wc_settings'

    /**
     * Page slug for the settings page.
     * @var string
     */
    private string $page_slug = 'mottasl-wc-settings';

    /**
     * Settings array.
     * @var array
     */
    private array $settings = [];


    /**
     * Initializes the admin settings.
     * Adds WordPress hooks for the admin area.
     *
     * @since 1.0.0
     */
    public function init(): void
    {
        $this->settings = Helper::get_setting() ?: []; // Load existing settings

        add_action('admin_menu', [$this, 'add_admin_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // Hook to perform action after settings are updated
        // 'update_option_{$option}' fires after the option has been updated.
        add_action("update_option_{$this->option_name}", [$this, 'handle_settings_update'], 10, 3);
    }

    /**
     * Enqueue scripts and styles for the admin settings page.
     *
     * @param string $hook_suffix The current admin page.
     */
    public function enqueue_admin_scripts(string $hook_suffix): void
    {
        // Only load on our settings page
        // The page hook for a top-level menu is 'toplevel_page_mottasl-wc-settings'
        // The page hook for a submenu under WooCommerce would be 'woocommerce_page_mottasl-wc-settings'
        // Adjust if you make it a submenu of WooCommerce.
        if ('toplevel_page_' . $this->page_slug !== $hook_suffix && 'woocommerce_page_' . $this->page_slug !== $hook_suffix) {
            return;
        }

        wp_enqueue_style(
            'mottasl-admin-styles',
            MOTTASL_WC_PLUGIN_URL . 'assets/css/admin-styles.css',
            [],
            MOTTASL_WC_VERSION
        );

        wp_enqueue_script(
            'mottasl-admin-scripts',
            MOTTASL_WC_PLUGIN_URL . 'assets/js/admin-scripts.js',
            ['jquery', 'wp-color-picker', 'wc-enhanced-select'],
            MOTTASL_WC_VERSION,
            true
        );

        // For media uploader (for logo)
        wp_enqueue_media();
    }

    /**
     * Adds the admin menu page for Mottasl settings.
     *
     * @since 1.0.0
     */
    public function add_admin_menu_page(): void
    {
        // Add a top-level menu page
        // add_menu_page(
        //     __( 'Mottasl Settings', 'mottasl-woocommerce' ), // Page title
        //     __( 'Mottasl', 'mottasl-woocommerce' ),          // Menu title
        //     'manage_options',                                // Capability
        //     $this->page_slug,                                // Menu slug
        //     [ $this, 'render_settings_page' ],               // Callback function
        //     'dashicons-share-alt',                           // Icon URL (or dashicon class)
        //     58                                               // Position
        // );

        // Add as a submenu under WooCommerce:

        add_submenu_page(
            'woocommerce',                                   // Parent slug
            __('Mottasl Settings', 'mottasl-woocommerce'), // Page title
            __('Mottasl', 'mottasl-woocommerce'),          // Menu title
            'manage_woocommerce',                            // Capability (or 'manage_options')
            $this->page_slug,                                // Menu slug
            [$this, 'render_settings_page']               // Callback function
        );
    }

    /**
     * Registers settings, sections, and fields using the WordPress Settings API.
     *
     * @since 1.0.0
     */
    public function register_settings(): void
    {
        register_setting(
            $this->option_group, // Option group
            $this->option_name,  // Option name
            [$this, 'sanitize_settings'] // Sanitize callback
        );

        // // General Settings Section
        // add_settings_section(
        //     'mottasl_wc_general_section', // ID
        //     __('General Settings', 'mottasl-woocommerce'), // Title
        //     [$this, 'render_general_section_intro'], // Callback
        //     $this->page_slug // Page on which to show this section
        // );

        // General Settings Section (or a dedicated API/Connection section)
        add_settings_section(
            'mottasl_wc_connection_section', // New or renamed section
            __('Mottasl.ai Connection', 'mottasl-woocommerce'),
            [$this, 'render_connection_section_intro'],
            $this->page_slug
        );
        add_settings_field(
            'mottasl_business_id',
            __('Mottasl Business ID', 'mottasl-woocommerce'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'mottasl_wc_connection_section',
            ['id' => 'mottasl_business_id', 'desc' => __('Enter your Mottasl.ai Business ID.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'mottasl_wc_consumer_key',
            __('WooCommerce Consumer Key', 'mottasl-woocommerce'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'mottasl_wc_connection_section',
            [
                'id' => 'mottasl_wc_consumer_key',
                'desc' => sprintf(
                    /* translators: %s: Link to WooCommerce REST API settings page */
                    __('Enter the Consumer Key generated from <a href="%s" target="_blank"> Woocommerce Settings</a> for Mottasl.ai. This allows Mottasl.ai to interact with your store.', 'mottasl-woocommerce'),
                    esc_url(admin_url('admin.php?page=wc-settings&tab=advanced&section=keys'))
                )
            ]
        );

        add_settings_field(
            'mottasl_wc_consumer_secret',
            __('WooCommerce Consumer Secret', 'mottasl-woocommerce'),
            [$this, 'render_password_field'], // Use password field for secret
            $this->page_slug,
            'mottasl_wc_connection_section',
            ['id' => 'mottasl_wc_consumer_secret', 'desc' => __('Enter the corresponding Consumer Secret.', 'mottasl-woocommerce')]
        );


        // Invoice Settings Section
        add_settings_section(
            'mottasl_wc_invoice_section',
            __('Invoice Settings', 'mottasl-woocommerce'),
            [$this, 'render_invoice_section_intro'],
            $this->page_slug
        );

        add_settings_field(
            'invoice_store_name',
            __('Store Name for Invoice', 'mottasl-woocommerce'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'mottasl_wc_invoice_section',
            ['id' => 'invoice_store_name', 'desc' => __('The name of your store as it should appear on invoices.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'invoice_store_address',
            __('Store Address for Invoice', 'mottasl-woocommerce'),
            [$this, 'render_textarea_field'],
            $this->page_slug,
            'mottasl_wc_invoice_section',
            ['id' => 'invoice_store_address', 'desc' => __('Full store address (street, city, state, zip, country). One line per entry.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'invoice_store_vat',
            __('Store VAT/Tax ID', 'mottasl-woocommerce'),
            [$this, 'render_text_field'],
            $this->page_slug,
            'mottasl_wc_invoice_section',
            ['id' => 'invoice_store_vat', 'desc' => __('Your store\'s VAT or Tax Identification Number.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'invoice_store_logo_url',
            __('Store Logo URL for Invoice', 'mottasl-woocommerce'),
            [$this, 'render_media_upload_field'],
            $this->page_slug,
            'mottasl_wc_invoice_section',
            ['id' => 'invoice_store_logo_url', 'desc' => __('Upload or enter the URL for your store logo to appear on invoices.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'invoice_generate_on_status',
            __('Generate Invoice on Status(es)', 'mottasl-woocommerce'),
            [$this, 'render_order_status_multiselect_field'],
            $this->page_slug,
            'mottasl_wc_invoice_section',
            ['id' => 'invoice_generate_on_status', 'desc' => __('Select WooCommerce order statuses upon which an invoice should be automatically generated.', 'mottasl-woocommerce')]
        );

        // Event Sync Settings Section
        add_settings_section(
            'mottasl_wc_sync_section',
            __('Event Synchronization', 'mottasl-woocommerce'),
            [$this, 'render_sync_section_intro'],
            $this->page_slug
        );

        add_settings_field(
            'enable_order_sync',
            __('Enable Order Status Sync', 'mottasl-woocommerce'),
            [$this, 'render_checkbox_field'],
            $this->page_slug,
            'mottasl_wc_sync_section',
            ['id' => 'enable_order_sync', 'desc' => __('Send order status updates to Mottasl.ai.', 'mottasl-woocommerce')]
        );

        add_settings_field(
            'enable_abandoned_cart',
            __('Enable Abandoned Cart Tracking', 'mottasl-woocommerce'),
            [$this, 'render_checkbox_field'],
            $this->page_slug,
            'mottasl_wc_sync_section',
            ['id' => 'enable_abandoned_cart', 'desc' => __('Track and send abandoned cart events to Mottasl.ai.', 'mottasl-woocommerce')]
        );
    }

    /**
     * Sanitizes the settings array before saving.
     *
     * @param array $input The settings submitted by the user.
     * @return array The sanitized settings.
     */
    public function sanitize_settings(array $input): array
    {
        $sanitized_input = [];
        $current_settings = Helper::get_setting() ?: []; // Get existing settings to merge with defaults


        $sanitized_input['mottasl_business_id'] = isset($input['mottasl_business_id']) ? sanitize_text_field($input['mottasl_business_id']) : '';
        $sanitized_input['mottasl_wc_consumer_key'] = isset($input['mottasl_wc_consumer_key']) ? sanitize_text_field($input['mottasl_wc_consumer_key']) : '';

        // For consumer secret, if the field is submitted empty, it might mean the user wants to clear it,
        // OR it might mean they didn't want to change it (since it's a password field and might not re-populate).
        // It's common to only update the secret if a new value is provided.
        if (! empty($input['mottasl_wc_consumer_secret'])) {
            $sanitized_input['mottasl_wc_consumer_secret'] = sanitize_text_field($input['mottasl_wc_consumer_secret']);
        } else {
            // Keep the old secret if the new one is empty
            $sanitized_input['mottasl_wc_consumer_secret'] = $old_settings['mottasl_wc_consumer_secret'] ?? '';
        }


        // Invoice settings
        $sanitized_input['invoice_store_name'] = isset($input['invoice_store_name']) ? sanitize_text_field($input['invoice_store_name']) : '';
        $sanitized_input['invoice_store_address'] = isset($input['invoice_store_address']) ? sanitize_textarea_field($input['invoice_store_address']) : '';
        $sanitized_input['invoice_store_vat'] = isset($input['invoice_store_vat']) ? sanitize_text_field($input['invoice_store_vat']) : '';
        $sanitized_input['invoice_store_logo_url'] = isset($input['invoice_store_logo_url']) ? esc_url_raw($input['invoice_store_logo_url']) : '';

        if (isset($input['invoice_generate_on_status']) && is_array($input['invoice_generate_on_status'])) {
            $sanitized_input['invoice_generate_on_status'] = array_map('sanitize_key', $input['invoice_generate_on_status']);
        } else {
            $sanitized_input['invoice_generate_on_status'] = $current_settings['invoice_generate_on_status'] ?? ['processing', 'completed']; // Default if not set
        }

        // Sync settings
        $sanitized_input['enable_order_sync'] = isset($input['enable_order_sync']) ? 'yes' : 'no';
        $sanitized_input['enable_abandoned_cart'] = isset($input['enable_abandoned_cart']) ? 'yes' : 'no';

        // Add any other settings and their sanitization here
        return array_merge($current_settings, $sanitized_input);
    }

    /**
     * New callback for password type fields.
     */
    public function render_password_field(array $args): void
    {
        $id = $args['id'];
        $value = $this->settings[$id] ?? '';
        // For secrets, we often show only a few characters or just asterisks if a value is saved.
        // However, for user input, they need to see what they type.
        // WordPress doesn't have a built-in mechanism to show "••••••••" for saved password fields
        // that then becomes clear on focus without custom JS. Standard practice is to just use type="password".
        echo '<input type="password" id="' . esc_attr($id) . '" name="' . esc_attr($this->option_name . '[' . $id . ']') . '" value="' . esc_attr($value) . '" class="regular-text" autocomplete="new-password" />';
        if (! empty($args['desc'])) {
            echo '<p class="description">' . wp_kses_post($args['desc']) . '</p>';
        }
    }

    public function render_connection_section_intro(): void
    {
        echo '<p>' . esc_html__('Configure the connection details for Mottasl.ai. These are required for the integration to function correctly.', 'mottasl-woocommerce') . '</p>';
    }


    /** Callbacks for rendering sections and fields **/

    public function render_general_section_intro(): void
    {
        echo '<p>' . esc_html__('Configure general settings for the Mottasl WooCommerce integration.', 'mottasl-woocommerce') . '</p>';
    }

    public function render_invoice_section_intro(): void
    {
        echo '<p>' . esc_html__('Set up the details that will appear on your invoices and when they are generated.', 'mottasl-woocommerce') . '</p>';
    }
    public function render_sync_section_intro(): void
    {
        echo '<p>' . esc_html__('Control which events are synchronized with Mottasl.ai.', 'mottasl-woocommerce') . '</p>';
    }

    public function render_business_id_field(): void
    {
        $value = $this->settings['mottasl_business_id'] ?? '';
        echo '<input type="text" id="mottasl_business_id" name="' . esc_attr($this->option_name . '[mottasl_business_id]') . '" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Enter your Mottasl Business ID as it is required for all Mottasl Features.', 'mottasl-woocommerce') . '</p>';
    }

    public function render_text_field(array $args): void
    {
        $id = $args['id'];
        $value = $this->settings[$id] ?? '';
        echo '<input type="text" id="' . esc_attr($id) . '" name="' . esc_attr($this->option_name . '[' . $id . ']') . '" value="' . esc_attr($value) . '" class="regular-text" />';
        if (! empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function render_textarea_field(array $args): void
    {
        $id = $args['id'];
        $value = $this->settings[$id] ?? '';
        echo '<textarea id="' . esc_attr($id) . '" name="' . esc_attr($this->option_name . '[' . $id . ']') . '" rows="5" class="large-text">' . esc_textarea($value) . '</textarea>';
        if (! empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function render_media_upload_field(array $args): void
    {
        $id = $args['id'];
        $value = $this->settings[$id] ?? '';
?>
        <div>
            <input type="text" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($this->option_name . '[' . $id . ']'); ?>" value="<?php echo esc_attr($value); ?>" class="regular-text mottasl-media-url" />
            <button type="button" class="button mottasl-upload-media-button"><?php esc_html_e('Upload/Select Image', 'mottasl-woocommerce'); ?></button>
        </div>
        <div class="mottasl-media-preview" style="margin-top:10px;">
            <?php if ($value) : ?>
                <img src="<?php echo esc_url($value); ?>" style="max-width:200px; height:auto;" />
            <?php endif; ?>
        </div>
        <?php
        if (! empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
    }

    public function render_order_status_multiselect_field(array $args): void
    {
        $id = $args['id'];
        $selected_statuses = $this->settings[$id] ?? ['processing', 'completed']; // Default selected
        $wc_statuses = wc_get_order_statuses(); // e.g., ['wc-pending' => 'Pending payment', ...]

        echo '<select id="' . esc_attr($id) . '" name="' . esc_attr($this->option_name . '[' . $id . '][]') . '" multiple="multiple" class="wc-enhanced-select" style="width:50%;">';
        foreach ($wc_statuses as $status_key => $status_name) {
            // Remove 'wc-' prefix for simpler value if needed by Mottasl, or keep it if that's the standard
            $value = str_replace('wc-', '', $status_key);
            $selected = in_array($value, $selected_statuses, true) ? 'selected="selected"' : '';
            echo '<option value="' . esc_attr($value) . '" ' . $selected . '>' . esc_html($status_name) . '</option>';
        }
        echo '</select>';
        if (! empty($args['desc'])) {
            echo '<p class="description">' . esc_html($args['desc']) . '</p>';
        }
        // Ensure WooCommerce Enhanced Select JS is loaded if not already by WC on this page
        // Usually it is on WooCommerce settings pages. For top-level, you might need to enqueue 'wc-enhanced-select'.
        ?>
        <script type="text/javascript">
            jQuery(function($) {
                if (typeof wc_enhanced_select_params !== 'undefined') {
                    $('#<?php echo esc_attr($id); ?>').select2({
                        // minimumResultsForSearch: Infinity // if you don't want search
                    });
                } else if ($.fn.select2) {
                    $('#<?php echo esc_attr($id); ?>').select2();
                }
            });
        </script>
        <?php
    }


    public function render_checkbox_field(array $args): void
    {
        $id = $args['id'];
        $checked = isset($this->settings[$id]) && $this->settings[$id] === 'yes';
        echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . esc_attr($this->option_name . '[' . $id . ']') . '" value="yes" ' . checked($checked, true, false) . ' />';
        if (! empty($args['desc'])) {
            echo '<label for="' . esc_attr($id) . '"> ' . esc_html($args['desc']) . '</label>';
        }
    }

    /**
     * Renders the HTML for the settings page.
     *
     * @since 1.0.0
     */
    public function render_settings_page(): void
    {
        // Check user capabilities
        if (! current_user_can('manage_options') && ! current_user_can('manage_woocommerce')) { // Adjust capability check as needed
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'mottasl-woocommerce'));
        }

        // Use the template file if it exists, otherwise inline HTML.
        $template_path = MOTTASL_WC_PLUGIN_PATH . 'templates/admin/settings-page.php';
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback basic structure if template is missing
        ?>
            <div class="wrap mottasl-settings-wrap">
                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields($this->option_group);
                    do_settings_sections($this->page_slug);
                    submit_button(__('Save Settings', 'mottasl-woocommerce'));
                    ?>
                </form>
            </div>
<?php
        }
    }

    /**
     * Handle actions after settings are updated.
     * Specifically, re-send installation confirmation if key credentials change.
     *
     * @param mixed $old_value The old option value.
     * @param mixed $value     The new option value.
     * @param string $option_name The name of the updated option.
     */
    public function handle_settings_update($old_value, $value, string $option_name): void
    {
        // $value is the new, full array of settings.
        // $old_value is the old, full array of settings.

        $relevant_keys_changed = false;
        $keys_to_check = [
            'mottasl_business_id',
            'mottasl_wc_consumer_key',
            'mottasl_wc_consumer_secret',
        ];

        foreach ($keys_to_check as $key) {
            if (($old_value[$key] ?? null) !== ($value[$key] ?? null)) {
                $relevant_keys_changed = true;
                break;
            }
        }

        if ($relevant_keys_changed) {
            error_log('Mottasl DEBUG: Relevant API settings changed. Re-sending installation confirmation.');

            // Prepare payload for Mottasl.
            // This is similar to Activator::send_installation_confirmation but uses current settings.
            $store_url = Helper::get_store_url();
            if (! $store_url) {
                error_log('Mottasl Settings Update Error: Could not retrieve store URL.');
                return;
            }

            $api_handler = new MottaslAPI();
            $payload = [
                'event_type'    => Constants::EVENT_TOPIC_UPDATED, // Indicate this is an update
                'store_url'     => $store_url,
                'plugin_version' => MOTTASL_WC_VERSION,
                'wc_version'    => WC()->version ?? null,
                'php_version'   => phpversion(),
                'wp_version'    => get_bloginfo('version'),
                'updated_at'    => current_time('mysql', true),
                'mottasl_business_id' => $value['mottasl_business_id'] ?? null,
                'woocommerce_api_keys' => [ // Send the keys provided by the user
                    'consumer_key'    => $value['mottasl_wc_consumer_key'] ?? null,
                    'consumer_secret' => $value['mottasl_wc_consumer_secret'] ?? null,
                ],
                // Include other settings if Mottasl needs them on update
                'invoice_settings' => [
                    'store_name' => $value['invoice_store_name'] ?? '',
                    'store_address' => $value['invoice_store_address'] ?? '',
                    'store_vat' => $value['invoice_store_vat'] ?? '',
                    'store_logo_url' => $value['invoice_store_logo_url'] ?? '',
                    'generate_on_status' => $value['invoice_generate_on_status'] ?? ['processing', 'completed'], // Default if not set
                ],
                'sync_settings' => [
                    'enable_order_sync' => $value['enable_order_sync'] ?? 'no',
                    'enable_abandoned_cart' => $value['enable_abandoned_cart'] ?? 'no',
                ]
            ];

            // Use the same endpoint topic or a dedicated "update_settings" topic if Mottasl has one.
            // Using installation.confirmation for now, but Mottasl might prefer a different topic for updates.
            $api_handler->send_event(Constants::MOTTASL_EVENT_PATH_APP, $payload);

            // Add an admin notice for feedback
            add_settings_error(
                'mottasl_settings_updated_notice',
                'mottasl_settings_connection_updated',
                __('Mottasl.ai connection details updated and re-confirmed.', 'mottasl-woocommerce'),
                'success' // or 'info'
            );
        }
    }
}
