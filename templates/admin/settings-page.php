<?php

/**
 * Template for the Mottasl WooCommerce Settings page.
 *
 * This template is loaded by Mottasl\WooCommerce\Admin\Settings->render_settings_page().
 *
 * @package Mottasl_WooCommerce
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

// The $this variable in this context refers to the Mottasl\WooCommerce\Admin\Settings instance
// (because this file is included within its render_settings_page method).
// We can access its properties like $this->option_group, $this->page_slug.
?>
<div class="wrap mottasl-settings-wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors(); // Display any settings errors/update messages 
    ?>

    <form method="post" action="options.php">
        <?php
        // WordPress core functions for settings pages
        settings_fields($this->option_group); // Output nonce, action, and option_page fields for a settings page. Corresponds to register_setting() $option_group

        // Output settings sections and fields. Corresponds to add_settings_section() and add_settings_field() $page_slug
        // do_settings_sections( $this->page_slug ); // This will render all sections and fields added to $this->page_slug

        // If you want more control over the layout, like tabs:
        ?>

        <h2 class="nav-tab-wrapper mottasl-nav-tab-wrapper">
            <a href="#mottasl-connection" class="nav-tab nav-tab-active"><?php esc_html_e('Connection', 'mottasl-woocommerce'); ?></a>
            <a href="#mottasl-invoice" class="nav-tab"><?php esc_html_e('Invoice', 'mottasl-woocommerce'); ?></a>
            <a href="#mottasl-sync" class="nav-tab"><?php esc_html_e('Synchronization', 'mottasl-woocommerce'); ?></a>
            <?php do_action('mottasl_wc_settings_tabs_nav'); // Hook for adding more tabs 
            ?>
        </h2>

        <div id="mottasl-connection" class="mottasl-tab-content active">
            <h3><?php esc_html_e('Mottasl.ai Connection', 'mottasl-woocommerce'); ?></h3>
            <table class="form-table">
                <?php do_settings_fields($this->page_slug, 'mottasl_wc_connection_section'); ?>
            </table>
        </div>

        <div id="mottasl-invoice" class="mottasl-tab-content" style="display: none;">
            <h3><?php esc_html_e('Invoice Settings', 'mottasl-woocommerce'); ?></h3>
            <table class="form-table">
                <?php do_settings_fields($this->page_slug, 'mottasl_wc_invoice_section'); ?>
            </table>
        </div>

        <div id="mottasl-sync" class="mottasl-tab-content" style="display: none;">
            <h3><?php esc_html_e('Event Synchronization', 'mottasl-woocommerce'); ?></h3>
            <table class="form-table">
                <?php do_settings_fields($this->page_slug, 'mottasl_wc_sync_section'); ?>
            </table>
        </div>

        <?php do_action('mottasl_wc_settings_tabs_content'); // Hook for adding more tab content 
        ?>

        <?php
        // Output the submit button
        submit_button(__('Save Settings', 'mottasl-woocommerce'));
        ?>
    </form>
</div>