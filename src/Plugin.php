<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Mottasl_WooCommerce
 * @author     Your Name <your-email@example.com>
 */

namespace Mottasl\WooCommerce;

use Mottasl\WooCommerce\Admin\Settings as AdminSettings;
use Mottasl\WooCommerce\Events\OrderHandler;
use Mottasl\WooCommerce\Events\AbandonedCartHandler;
// Use Mottasl\WooCommerce\Invoices\InvoiceGenerator; // We'll add this when defining InvoiceGenerator

defined( 'ABSPATH' ) || exit;

class Plugin {

    /**
     * The single instance of the class.
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * The plugin version.
     * @var string
     */
    private string $version;

    /**
     * The plugin text domain.
     * @var string
     */
    private string $text_domain;

    /**
     * Handler for admin settings.
     * @var AdminSettings
     */
    public ?AdminSettings $admin_settings = null;

    /**
     * Handler for order events.
     * @var OrderHandler | null
     */
    public ?OrderHandler $order_handler = null;

    /**
     * Handler for abandoned cart events.
     * @var AbandonedCartHandler | null
	 * @since 1.0.0
     */
    public ?AbandonedCartHandler $abandoned_cart_handler = null;

    /**
     * Ensure only one instance of the class is loaded or can be loaded.
     *
     * @since  1.0.0
     * @return Plugin An instance of this class.
     */
    public static function get_instance(): Plugin {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     * Private to prevent direct object creation.
     *
     * @since 1.0.0
     */
    private function __construct() {
        $this->version     = MOTTASL_WC_VERSION;
        $this->text_domain = MOTTASL_WC_TEXT_DOMAIN;
    }

    /**
     * Initializes the plugin by setting up hooks.
     * This method is called from the main plugin file.
     *
     * @since 1.0.0
     */
    public function run(): void {
        $this->load_dependencies();
        $this->set_locale();
		// Hook component initialization to 'init' (for things not strictly WC dependent early on)
        // add_action('init', [ $this, 'init_components' ], 5);
        $this->init_components();
		// This is often a safer bet for WC-dependent plugins.
        add_action('woocommerce_init', [ $this, 'init_hooks' ], 10);
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * (Currently, classes are autoloaded by Composer. This method is a placeholder
     * if specific files needed to be included manually or for other setup tasks.)
     *
     * @since 1.0.0
     */
    private function load_dependencies(): void {
        // If you had helper functions not in classes, you might include them here.
        // For class-based structure with PSR-4, Composer handles most of this.
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * @since 1.0.0
     */
    private function set_locale(): void {
        add_action( 'plugins_loaded', [ $this, 'load_plugin_textdomain' ] );
    }

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain(): void {
        load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname( MOTTASL_WC_BASENAME ) . '/languages/'
        );
    }

	/**
	 * Initialize all components of the plugin.
	 *
	 * This method sets up the main components like Admin Settings, Order Handler,
	 * and Abandoned Cart Handler.
	 *
	 * @since 1.0.0
	 */
	public function init_components(): void {
		if (!$this->admin_settings) { // Instantiate only if not already done
            $this->admin_settings = new AdminSettings();
        }
        if (!$this->order_handler) {
            $this->order_handler = new OrderHandler();
        }
        if (!$this->abandoned_cart_handler) {
            $this->abandoned_cart_handler = new AbandonedCartHandler();
        }

		// If you have an InvoiceGenerator or other components, instantiate them here
		// if (!$this->invoice_generator) {
		//     $this->invoice_generator = new InvoiceGenerator();
		// }
	}

    /**
     * Register all of the hooks related to the public-facing functionality
     * as well as the admin area.
     *
     * @since 1.0.0
     */
    public function init_hooks(): void {
        
        if ( $this->admin_settings instanceof AdminSettings ) {
            error_log('MOTTASL DEBUG: Calling admin_settings->init().');
            $this->admin_settings->init(); // Settings::init() adds admin_menu and admin_init hooks
        } else {
            error_log('MOTTASL DEBUG ERROR: admin_settings object is not set in init_hooks.');
        }

        if ($this->order_handler instanceof OrderHandler) {
            error_log('MOTTASL DEBUG: Calling order_handler->init().');
            $this->order_handler->init();
        } else {
            error_log('MOTTASL DEBUG ERROR: order_handler object is not set.');
        }

        if ($this->abandoned_cart_handler instanceof AbandonedCartHandler) {
            error_log('MOTTASL DEBUG: Calling abandoned_cart_handler->init().');
            $this->abandoned_cart_handler->init(); // This will call the cron scheduling
        } else {
            error_log('MOTTASL DEBUG ERROR: abandoned_cart_handler object is not set.');
        }

        // Add more hooks here or within the respective component classes
        // For example, if InvoiceGenerator needs global hooks:
        // $this->invoice_generator->init();
    }

    /**
     * Get the plugin version.
     *
     * @since  1.0.0
     * @return string The plugin version.
     */
    public function get_version(): string {
        return $this->version;
    }

    /**
     * Get the plugin text domain.
     *
     * @since  1.0.0
     * @return string The plugin text domain.
     */
    public function get_text_domain(): string {
        return $this->text_domain;
    }

    /**
     * Cloning is forbidden.
     * @since 1.0.0
     */
    public function __clone() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'mottasl-woocommerce' ), '1.0.0' );
    }

    public function __wakeup() {
        _doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'mottasl-woocommerce' ), '1.0.0' );
    }
}