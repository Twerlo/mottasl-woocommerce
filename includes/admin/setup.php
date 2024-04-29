<?php

namespace Hub\Admin;

/**
 * Hub Setup Class
 */
class Setup
{
	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct()
	{
		add_action('activated_plugin', array($this, 'mottasl_redirect'));
		add_action('admin_notices', array($this, 'wpb_admin_notice_warn'));
		add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
		add_action('admin_menu', array($this, 'register_page'));
		add_filter( 'woocommerce_get_sections_general', array($this,'settings_section' ));
		add_filter( 'woocommerce_get_settings_general',  array($this,'hub_settings'), 10, 2 );
		add_action( 'woocommerce_admin_field_button' , array($this,'freeship_add_admin_field_button') );
		// add the filter
		add_filter( "plugin_action_links", array($this,"modify_plugin_action_links_defaults"), 10, 4 );
		
	}
	function modify_plugin_action_links_defaults($actions, $plugin_file, $plugin_data, $context) { 
	
		if($plugin_data['Name'] == 'Mottasl') {
$settings_url=admin_url('admin.php?page=wc-settings&tab=general&section=woocommerce_api_section');
			$actions[] = '<a href="'.$settings_url.'">Settings</a>';
		}
		// Update the $actions variable according to your website requirements and return this variable. You can modify the $actions variable conditionally too if you want.
	
		return $actions; 
	}

	function freeship_add_admin_field_button( $value ){     
                ?>      
                    <td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
                
                         <input
                                name ="<?php echo esc_attr( $value['name'] ); ?>"
                                id   ="<?php echo esc_attr( $value['id'] ); ?>"
                                type ="submit"
                                style="display:block;<?php echo esc_attr( $value['css'] ); ?>"
                                value="<?php echo esc_attr( $value['name'] ); ?>"
                                class="<?php echo esc_attr( $value['class'] ); ?>"
							onclick=<?php   	
									
		$store_data = array(
			'store_url' => get_bloginfo('url'),
			'consumer_key'=>get_option( 'consumer_key' ),
			'consumer_secret'=>get_option( 'consumer_secret' ),
            'event_name' => 'install',

		);
		$business_id=get_option( 'business_id' );
		// Set up the request arguments
		$args = array(
			'body' => json_encode($store_data),
			'headers' => array(
				'Content-Type' => 'application/json',
				'X-BUSINESS-Id' => $business_id
			),
			'timeout' => 15,
		);
		$request_url = 'https://hub-api.avaocad0.dev/api/v1/integration/events/woocommerce/app.event';
		$response = wp_remote_post($request_url, $args);
											
					// Check for errors
		if (is_wp_error($response)) {
			echo '<p style="color:red">Please enter a valid woocommerce credentials and try to reconnect</p>
';		} else {
			// Success, delete integration_id
			update_option('installation_status', 'installed');
		}													
							?>
                             
							 ></input>
                    </td>   
           <?php       
}
  

	function my_custom_plugin_meta($plugin_meta, $plugin_file, $plugin_data, $status)
	{
		if ($plugin_data['Name'] == 'Mottasl') {

			// Add your link. You can also append or prepend to existing array elements.
			$encoded_consumer_key = get_option('encoded_consumer_key');
			$encoded_consumer_secret = get_option('encoded_consumer_secret');
			$connecting_link = 'https://app.avocad0.dev/ecommerce-apps?install=woocomerce&consumer_key=' . $encoded_consumer_key . '&consumer_secret=' . $encoded_consumer_secret . '&store_url=' . get_bloginfo('url');

			$new_link = '<a href=' . $connecting_link . '>connect </a>';

			$plugin_meta[] = $new_link;
			$background_color = get_option('installation_status');
			if ($background_color !== 'installed') {
				$background_color = 'red';
			} else {
				$background_color = '#70bbde';
			}
			$new_link = '<p " style="margin:3px;color:white;background-color:' . $background_color . '; padding: 3px;">' . get_option('installation_status') . '</p>';
			$plugin_meta[] = $new_link;
			return $plugin_meta;
		}
		return $plugin_meta;

	}

	function mottasl_redirect(): void
	{
		$encoded_consumer_key = get_option('encoded_consumer_key');
		$encoded_consumer_secret = get_option('encoded_consumer_secret');
		exit(wp_redirect('https://app.avocad0.dev/ecommerce-apps?install=woocomerce&consumer_key=' . $encoded_consumer_key . '&consumer_secret=' . $encoded_consumer_secret . '&store_url=' . get_bloginfo('url')));
		exit();
	}
	function wpb_admin_notice_warn()
	{
		if (!get_option('consumer_key') || !get_option('consumer_secret')) {
			echo '<div class="error notice-warning is-dismissible">
			  <p>Please enter a valid woocommerce credentials, go to woocommerce --> settings -->general --> Mottasl api v3.0</p>
			  </div>';
			echo '<div class="error notice-warning is-dismissible">
			  <p>to generate woocommerce credentials go to woocommerce --> settings -->advanced --> rest api --> create an Api key<p>
			  </div>';
		}
		if (get_option('business_id') == '') {
			echo '<div class="error notice-warning is-dismissible">
			  <p> If there is any issues with mottasll business ID please contact mottasl customer care </p>
			  </div>';
		}
	}
	function settings_section($sections)
	{
		$sections['woocommerce_api_section'] = __('Mottasl api v3.0', 'text-domain');
		return $sections;
	}


	function hub_settings( $settings, $current_section ) {
		if ( 'woocommerce_api_section' == $current_section ) {
			$custom_settings = array();
			
			$background_color = '';
			if ($background_color !== 'installed') {
				$background_color = 'red';
			} else {
				$background_color = '#70bbde';
			}
			$installaion = '<p style="margin:3px;color:white;background-color:' . $background_color . ';font-size: 15px;font-weight: bold;  text-align: center;width: 50%; border-radius: 5px;padding: 5px;">' . get_option('installation_status') . '</p>';
			
				$custom_settings[] = array(
				'name' => __( 'Installation Status', 'text-domain' ),
				'type' => 'title',
				'desc' => __( $installaion , 'text-domain' ),
				'id' => 'woocommerce_api_section_desc'
			);
		if(get_option( 'installation_status')!=='installed'){
					$custom_settings[] =  array(
                'name' => __( 'connect' ),
                'type' => 'button',
                'desc' => __( 'Connect'),
                'desc_tip' => true,
                'class' => 'button-primary',
                'id'    => 'connect',
            );
			}
	
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
	

	public function register_scripts()
	{
		if (
			!method_exists('Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page') ||
			!\Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()

		) {
			return;
		}

		$script_path = '/build/index.js';
		$script_asset_path = dirname(MAIN_PLUGIN_FILE) . '/build/index.asset.php';
		$script_asset = file_exists($script_asset_path)
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version' => filemtime($script_path),
			);
		$script_url = plugins_url($script_path, MAIN_PLUGIN_FILE);

		wp_register_script(
			'hub',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'hub',
			plugins_url('./../build/index.css', MAIN_PLUGIN_FILE),
			// Add any dependencies styles may have, such as wp-components.
			array(),
			filemtime(dirname(MAIN_PLUGIN_FILE) . '/build/index.css')
		);

		wp_enqueue_script('hub');
		wp_enqueue_style('hub');
	}

	/**
	 * Register page in wc-admin.
	 *
	 * @since 1.0.0
	 */

	public function register_page()
	{

		if (!function_exists('wc_admin_register_page')) {
			return;
		}

		wc_admin_register_page(
			array(
				'id' => 'hub-example-page',
				'title' => __('Hub', 'hub'),
				'parent' => 'woocommerce',
				'path' => '/hub',
			)
		);
	}

}
