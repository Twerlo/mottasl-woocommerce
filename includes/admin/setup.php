<?php

namespace Hub\Admin;

/**
 * Hub Setup Class
 */
class Setup {
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		
		add_action( 'admin_notices', array( $this,'wpb_admin_notice_warn') );
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_filter( 'woocommerce_get_sections_general', array($this,'settings_section' ));
		add_filter( 'woocommerce_get_settings_general',  array($this,'hub_settings'), 10, 2 );
	}
	function wpb_admin_notice_warn() {
		if(! get_option( 'consumer_key') || ! get_option( 'consumer_secret') || get_option( 'business_id') == ''){
		echo '<div class="error notice-warning is-dismissible">
			  <p>Please enter a valid woocommerce credentials, go to woocommerce --> settings -->general --> Mottasl api v3.0</p>
			  </div>'; 
			  echo '<div class="error notice-warning is-dismissible">
			  <p>to generate woocommerce credentials go to woocommerce --> settings -->advanced --> rest api --> create an Api key<p>
			  </div>'; 
		}
	}
	function settings_section( $sections ) {
		$sections['woocommerce_api_section'] = __( 'Mottasl api v3.0', 'text-domain' );
		return $sections;
	}
	

	function hub_settings( $settings, $current_section ) {
		if ( 'woocommerce_api_section' == $current_section ) {
			$custom_settings = array();
			// Add Title
			$custom_settings[] = array(
				'name' => __( 'Custom Settings', 'text-domain' ),
				'type' => 'title',
				'desc' => __( 'Enter woocommerce api key details.', 'text-domain' ),
				'id' => 'woocommerce_api_section_desc'
			);
	
			// Add a custom setting field
			$custom_settings[] = array(
				'name'     => __( 'Consumer Key', 'text-domain' ),
				'desc_tip' => __( 'Enter the generated consumer Key here', 'text-domain' ),
				'id'       => 'consumer_key',
				'type'     => 'text',
				'desc'     => __( 'Enter the generated consumer Key here.', 'text-domain' ),
			);
			$custom_settings[] = array(
				'name'     => __( 'Consumer Secret', 'text-domain' ),
				'desc_tip' => __( 'Enter the generated consumer secret here', 'text-domain' ),
				'id'       => 'consumer_secret',
				'type'     => 'text',
				'desc'     => __( 'Enter the generated consumer secret here.', 'text-domain' ),
			);
			$custom_settings[] = array(
				'name'     => __( 'Mottasl Business ID', 'text-domain' ),
				'desc_tip' => __( 'Enter the received business id from Mottasl here', 'text-domain' ),
				'id'       => 'business_id',
				'type'     => 'text',
				'desc'     => __( 'Enter the received business id from Mottasl here.', 'text-domain' ),
			);
	
			// Section end
	
			return $custom_settings;
		}
	
		return $settings;
	}
	
	public function register_scripts() {
		if ( ! method_exists( 'Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page' ) ||
		! \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()
		
		) {
			return;
		}

		$script_path       = '/build/index.js';
		$script_asset_path = dirname( MAIN_PLUGIN_FILE ) . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
		? require $script_asset_path
		: array(
			'dependencies' => array(),
			'version'      => filemtime( $script_path ),
		);
		$script_url        = plugins_url( $script_path, MAIN_PLUGIN_FILE );

		wp_register_script(
			'hub',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'hub',
			plugins_url( './../build/index.css', MAIN_PLUGIN_FILE ),
			// Add any dependencies styles may have, such as wp-components.
			array(),
			filemtime( dirname( MAIN_PLUGIN_FILE ) . '/build/index.css' )
		);

		wp_enqueue_script( 'hub' );
		wp_enqueue_style( 'hub' );
	}

	/**
	 * Register page in wc-admin.
	 *
	 * @since 1.0.0
	 */
	
		public function register_page() {

		if ( ! function_exists( 'wc_admin_register_page' ) ) {
			return;
		}

		wc_admin_register_page(
			array(
				'id'     => 'hub-example-page',
				'title'  => __( 'Hub', 'hub' ),
				'parent' => 'woocommerce',
				'path'   => '/hub',
			)
		);
	}

}
