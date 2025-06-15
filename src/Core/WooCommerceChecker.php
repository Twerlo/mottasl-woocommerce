<?php
/**
 * Handles WooCommerce dependency checking.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @subpackage Mottasl_WooCommerce/Core
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce\Core;

defined( 'ABSPATH' ) || exit;

class WooCommerceChecker {

    /**
     * Checks if WooCommerce plugin is active.
     *
     * @since  1.0.0
     * @return bool True if WooCommerce is active, false otherwise.
     */
    public static function is_active(): bool {
        // Check if the WooCommerce class exists (primary check)
        if ( ! class_exists( 'WooCommerce' ) ) {
            return false;
        }

        // Additional check: Ensure WooCommerce functions are available (e.g., WC())
        // This can sometimes catch edge cases where the class exists but WC hasn't fully loaded.
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        // You could also check a specific WooCommerce version if your plugin requires it
        // if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
        //     // Log or handle version incompatibility
        //     return false;
        // }

        return true;
    }

    /**
     * Displays an admin notice if WooCommerce is not active.
     * This can be hooked into 'admin_notices'.
     *
     * @since 1.0.0
     */
    public static function display_missing_woocommerce_notice() {
        if ( current_user_can( 'activate_plugins' ) ) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p>
                    <?php
                    echo wp_kses_post(
                        sprintf(
                            /* translators: %1$s: Mottasl for WooCommerce (plugin name), %2$s: WooCommerce (plugin name) */
                            __( '<strong>%1$s</strong> requires %2$s to be installed and active. Please install and activate %2$s to use this plugin.', 'mottasl-woocommerce' ),
                            esc_html__( 'Mottasl for WooCommerce', 'mottasl-woocommerce' ),
                            '<a href="' . esc_url( admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ) ) . '">' . esc_html__( 'WooCommerce', 'mottasl-woocommerce' ) . '</a>'
                        )
                    );
                    ?>
                </p>
            </div>
            <?php
        }
    }
}