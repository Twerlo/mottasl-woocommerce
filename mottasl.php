<?php

/**
 * Mottasl WooCommerce Integration
 *
 * @package           Mottasl_WooCommerce
 * @author            Ahmed Zidan <ahmed.zidan@twerlo.com>
 * @copyright         2025 Twerlo
 * @license           GPL-3.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Mottasl for WooCommerce
 * Plugin URI:        https://mottasl.com
 * Description:       Integrates WooCommerce with the Mottasl.ai platform, sending order status updates, abandoned cart events, and generating invoices.
 * Version:           1.0.0
 * Author:            Twerlo
 * Author URI:        https://twerlo.com
 * License:           GPL-3.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:       mottasl-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * WC requires at least: 6.0
 * WC tested up to:     [Enter Latest WooCommerce Version You Tested With, e.g., 8.3]
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}

// Define plugin constants
define('MOTTASL_WC_VERSION', '1.0.0');
define('MOTTASL_WC_PLUGIN_FILE', __FILE__);
define('MOTTASL_WC_PLUGIN_PATH', plugin_dir_path(MOTTASL_WC_PLUGIN_FILE));
define('MOTTASL_WC_PLUGIN_URL', plugin_dir_url(MOTTASL_WC_PLUGIN_FILE));
define('MOTTASL_WC_BASENAME', plugin_basename(MOTTASL_WC_PLUGIN_FILE)); // Crucial for action links filter
define('MOTTASL_WC_TEXT_DOMAIN', 'mottasl-woocommerce');

// Check for Composer's autoloader
if (! file_exists(MOTTASL_WC_PLUGIN_PATH . 'vendor/autoload.php')) {
    add_action('admin_notices', function () {
?>
        <div class="notice notice-error">
            <p>
                <?php
                echo wp_kses_post(
                    sprintf(
                        /* translators: %s: Mottasl for WooCommerce */
                        __('<strong>%s</strong> requires Composer dependencies to be installed. Please run <code>composer install</code> in the plugin directory or download a pre-built version.', 'mottasl-woocommerce'),
                        esc_html__('Mottasl for WooCommerce', 'mottasl-woocommerce')
                    )
                );
                ?>
            </p>
        </div>
        <?php
    });

    add_action('admin_init', function () {
        if (is_plugin_active(MOTTASL_WC_BASENAME)) {
            deactivate_plugins(MOTTASL_WC_BASENAME);
        }
    });
    return;
}

// Include Composer autoloader
require_once MOTTASL_WC_PLUGIN_PATH . 'vendor/autoload.php';

/**
 * The main function to run the plugin.
 */
function mottasl_wc_run_plugin()
{
    // Text domain is loaded by the Plugin class usually, but doing it here is also fine as a fallback.
    load_plugin_textdomain(
        MOTTASL_WC_TEXT_DOMAIN,
        false,
        dirname(MOTTASL_WC_BASENAME) . '/languages/'
    );

    if (class_exists('Mottasl\\WooCommerce\\Plugin')) {
        $plugin = Mottasl\WooCommerce\Plugin::get_instance();
        $plugin->run(); // This will set up all hooks, including admin pages.
    } else {
        error_log('MOTTASL DEBUG ERROR: Mottasl\\WooCommerce\\Plugin class DOES NOT exist in mottasl_wc_run_plugin().');
        add_action('admin_notices', function () {
        ?>
            <div class="notice notice-error">
                <p><?php esc_html_e('Mottasl for WooCommerce could not initialize its main class. Please check for conflicts or reinstall the plugin.', 'mottasl-woocommerce'); ?></p>
            </div>
    <?php
        });
    }
}

/**
 * Display an admin notice if mottasl_business_id is not configured.
 * This notice should only show IF the plugin is active and running.
 */
function mottasl_wc_missing_business_id_notice()
{
    // Assuming the settings page slug is 'mottasl-wc-settings'
    $settings_url = admin_url('admin.php?page=mottasl-wc-settings');
    ?>
    <div class="notice notice-warning is-dismissible">
        <p>
            <?php
            echo wp_kses_post(
                sprintf(
                    /* translators: %1$s: Plugin Name (Mottasl for WooCommerce), %2$s: Link to settings page. */
                    __('<strong>%1$s:</strong> The Mottasl Business ID is not configured. Please %2$s to enable full functionality.', 'mottasl-woocommerce'),
                    esc_html__('Mottasl for WooCommerce', 'mottasl-woocommerce'),
                    '<a href="' . esc_url($settings_url) . '">' . esc_html__('set your Business ID in the plugin settings', 'mottasl-woocommerce') . '</a>'
                )
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Add settings link to the plugin actions.
 *
 * @param array $actions An array of plugin action links.
 * @return array An array of plugin action links.
 */
function mottasl_wc_add_settings_action_link(array $actions): array
{
    // The slug of your settings page, as defined in Admin\Settings class ($this->page_slug)
    // Assuming it's 'mottasl-wc-settings' and added as a submenu of WooCommerce.
    $settings_page_slug = 'mottasl-wc-settings';
    $settings_url = admin_url('admin.php?page=' . $settings_page_slug);

    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Settings', 'mottasl-woocommerce') . '</a>';
    // Add the 'Settings' link to the beginning of the actions array
    return array_merge(['settings' => $settings_link], $actions);
}

add_filter('plugin_action_links_' . MOTTASL_WC_BASENAME, 'mottasl_wc_add_settings_action_link');


// --- Main Plugin Execution Logic ---

add_action('plugins_loaded', function () {
    if (class_exists('Mottasl\\WooCommerce\\Core\\WooCommerceChecker') && Mottasl\WooCommerce\Core\WooCommerceChecker::is_active()) {

        // Declare HPOS compatibility
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', MOTTASL_WC_PLUGIN_FILE, true);
            }
        });

        mottasl_wc_run_plugin(); // Initialize the plugin

        // Check for business ID configuration after plugin is initialized.
        // It's better to hook this into 'admin_init' or a similar hook that runs on admin pages
        // to avoid running get_option on every single page load if not necessary.
        // Or, let the Settings class itself handle this check if it's more appropriate there.
        // For simplicity here, keeping it, but with a check for admin area.
        if (is_admin() && current_user_can('manage_options')) {
            // Get settings using the helper to ensure consistency
            $plugin_settings = \Mottasl\WooCommerce\Utils\Helper::get_setting();
            if (empty($plugin_settings['mottasl_business_id'])) {
                add_action('admin_notices', 'mottasl_wc_missing_business_id_notice');
            }
        }
    } else {
        // Handle missing WooCommerce dependency
        if (class_exists('Mottasl\\WooCommerce\\Core\\WooCommerceChecker')) {
            add_action('admin_notices', ['Mottasl\\WooCommerce\\Core\\WooCommerceChecker', 'display_missing_woocommerce_notice']);
        } else {
            // Fallback procedural notice if WooCommerceChecker itself failed to load
            add_action('admin_notices', function () {
    ?>
                <div class="notice notice-error is-dismissible">
                    <p><?php esc_html_e('Mottasl for WooCommerce requires WooCommerce to be active. WooCommerceChecker class not found.', 'mottasl-woocommerce'); ?></p>
                </div>
<?php
            });
            error_log('MOTTASL DEBUG ERROR: WooCommerceChecker class itself not found for dependency check.');
        }
    }
}, 10);


/**
 * Register activation and deactivation hooks.
 * These are defined globally, so they are always registered.
 * The class_exists checks within them are good if they rely on the autoloader.
 */
if (class_exists('Mottasl\\WooCommerce\\Core\\Activator')) {
    register_activation_hook(MOTTASL_WC_PLUGIN_FILE, ['Mottasl\\WooCommerce\\Core\\Activator', 'activate']);
} else {
    // Log if Activator class isn't found during WordPress's plugin loading phase.
    // This might happen if there's an issue with the autoloader path or definition very early.
    // Usually, the autoloader check at the top catches this before this point.
    add_action('admin_init', function () { // Delay error log slightly if needed
        if (!class_exists('Mottasl\\WooCommerce\\Core\\Activator')) {
            error_log('MOTTASL Plugin Load Error: Activator class not found when trying to register activation hook.');
        }
    });
}

if (class_exists('Mottasl\\WooCommerce\\Core\\Deactivator')) {
    register_deactivation_hook(MOTTASL_WC_PLUGIN_FILE, ['Mottasl\\WooCommerce\\Core\\Deactivator', 'deactivate']);
} else {
    add_action('admin_init', function () {
        if (!class_exists('Mottasl\\WooCommerce\\Core\\Deactivator')) {
            error_log('MOTTASL Plugin Load Error: Deactivator class not found when trying to register deactivation hook.');
        }
    });
}

// --- For later use, if you create an uninstaller ---
// register_uninstall_hook( __FILE__, ['Mottasl\\WooCommerce\\Core\\Uninstaller', 'uninstall'] );