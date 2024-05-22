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
		add_action('admin_notices', array($this, 'errors_declaration'));
		add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
		add_action('admin_menu', array($this, 'register_page'));
		add_filter('woocommerce_get_sections_general', array($this, 'settings_section'));
		add_filter('woocommerce_get_settings_general', array($this, 'hub_settings'), 10, 2);
		add_action('woocommerce_admin_field_button', array($this, 'freeship_add_admin_field_button'));
		add_filter("plugin_action_links", array($this, "modify_plugin_action_links_defaults"), 10, 4);
		add_action('admin_notices', array($this, 'my_plugin_admin_notices'));
		add_action('plugins_loaded', array($this, 'mottasl_init'));

	}

	function mottasl_init()
	{
		if (!function_exists('is_plugin_inactive')):
			require_once (ABSPATH . '/wp-admin/includes/plugin.php');
		endif;
		//COMMON WOOCOMMERCE METHOD
		if (!class_exists('WooCommerce')):
			//ALTERNATIVE METHOD
			//if( is_plugin_inactive( 'woocommerce/woocommerce.php' ) ) :
			add_action('admin_init', array($this, 'hub_deactivate'));
			add_action('admin_notices', array($this, 'hub_admin_notice'));

		endif;
	}
	function hub_deactivate()
	{
		deactivate_plugins('mottasl-woocommerce/hub.php');
	}
	function hub_admin_notice()
	{
		echo '<div class="error"><p><strong>WooCommerce</strong> must be installed and activated to use Mottasl.</p></div>';
		if (isset($_GET['activate']))
			unset($_GET['activate']);
	}
	function modify_plugin_action_links_defaults($actions, $plugin_file, $plugin_data, $context)
	{

		if ($plugin_data['Name'] == 'Mottasl')
		{
			$settings_url = admin_url('admin.php?page=wc-settings&tab=general&section=woocommerce_api_section');
			$actions[] = '<a href="' . $settings_url . '">Settings</a>';
		}
		if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))))
		{
			if ($plugin_data['Name'] == 'WooCommerce')
			{
				$settings_url = admin_url('admin.php?page=wc-settings&tab=general&section=woocommerce_api_section');
				$actions[] = '<p style="color:red; background-color:gold;  border-radius:5px; padding:5px; display:block ">To activate mottasl you need to activate woocommerce and try to reconnect from mottasl settings</p>';
			}
		}


		return $actions;
	}




	function freeship_add_admin_field_button($value)
	{
		?>
		<td class="forminp forminp-<?php echo sanitize_title($value['type']) ?>">

			<input type="button" name="<?php echo esc_attr($value['name']); ?>" id="<?php echo esc_attr($value['id']); ?>"
				style="display:block; width:45%; padding: 5px ;<?php echo esc_attr($value['css']); ?>"
				value="<?php echo esc_attr($value['name']); ?>" class="<?php echo esc_attr($value['class']); ?>"
				onclick="handleButtonClick(event)">
			</input>
		</td>
		<script>
			function handleButtonClick ( event ) {
				if ( event.target.getAttribute( 'id' ) === 'connect' ) {

		<?php			// try to get hub integration id from settings
					$store_id = get_option( 'store_id', );


					$store_data = array(
						'store_url' => get_bloginfo( 'url' ),
						'consumer_key' => get_option( 'consumer_key' ),
						'consumer_secret' => get_option( 'consumer_secret' ),
						'store_email' => get_bloginfo( 'admin_email' ),
						'store_phone' => get_option( 'admin_phone' ),
						'store_name' => get_bloginfo( 'name' ),
						'event_name' => 'installed',
						"platform_id" => $store_id,
					);

					// Set up the request arguments
					$args = array(
						'body' => json_encode( $store_data ),
						'headers' => array(
							'Content-Type' => 'application/json',
							'X-BUSINESS-Id' => get_option( 'business_id' )
						),
						'timeout' => 15,
					);

					$request_url = 'https://test.hub.avocad0.dev/api/v1/integration/events/woocommerce/installation.confirmation';
					$response = wp_remote_post( $request_url, $args );
					// Check for errors
					if ( is_wp_error( $response ) || 200 != wp_remote_retrieve_response_code( $response ) || !empty( $response[ 'body' ] ) && $response[ 'status' ] != 200 ) {
						update_option( 'installation_status', 'pending' );
						update_option( 'notice_error',json_decode(wp_remote_retrieve_body($response))->message );

					} else {
						// Success, delete integration_id
						update_option( 'installation_status', 'installed' );
						update_option( 'notice_error', '' );

					}
					?>
					window.location.reload();

				}
				else {
					event.preventDefault();
				}
			}
		</script>
		<script>

		</script>
		<?php
	}
	function my_plugin_admin_notices()
	{
		if (!get_option('installation_status') == 'installed')
		{
			echo "<div class='updated'><p>Please try to reconnect to Mottasl, if you face any issue please contact the support</p></div>";
		}
	}


	function mottasl_redirect($plugin_name): void
	{

		if ($plugin_name == 'mottasl-woocommerce/hub.php')
		{

			$encoded_user_credits = generate_jwt_token(['consumer_key' => get_option('consumer_key'), 'consumer_secret' => get_option('consumer_secret'), 'store_url' => get_bloginfo('url')], 'woocommerce-install');
			exit(wp_redirect('https://test.app.avocad0.dev/ecommerce-apps?install=woocommerce&code=' . $encoded_user_credits));
		}
	}
	function woocommerce_deactivation($plugin_file, $plugin_data)
	{
		if ($plugin_file == 'woocommerce/woocommerce.php')
		{
			deactivate_plugins('mottasl-woocommerce/hub.php');
		}


		return $plugin_data;
	}

	function errors_declaration()
	{
		$error = get_option('notice_error');
		if ($error)
		{
			echo '<div class="error notice-warning is-dismissible">
			  <p>' . $error . '</p>
			  </div>';
		}

	}
	function wpb_admin_notice_warn()
	{

		if (!get_option('consumer_key') || !get_option('consumer_secret'))
		{
			echo '<div class="error notice-warning is-dismissible">
			  <p>Please enter a valid woocommerce credentials, go to woocommerce --> settings -->general --> Mottasl api v3.0</p>
			  </div>';
			echo '<div class="error notice-warning is-dismissible">
			  <p>to generate woocommerce credentials go to woocommerce --> settings -->advanced --> rest api --> create an Api key<p>
			  </div>';
		}

	}
	function settings_section($sections)
	{
		$sections['woocommerce_api_section'] = __('Mottasl api v3.0', 'text-domain');
		return $sections;
	}


	function hub_settings($settings, $current_section)
	{
		if ('woocommerce_api_section' == $current_section)
		{
			$background_color = '';
			if ($background_color !== 'installed')
			{
				$background_color = 'red';
			} else
			{
				$background_color = '#70bbde';
			}
			$installaion = get_option('installation_status');
			$custom_settings = array(
				array(
					'name' => __('Mottasl Settings'),
					'type' => 'title',
					'desc' => __('Enter valid mottasl credentials'),
					'desc_tip' => true,
					'id' => 'mottasl_Settings',
				),

				array(
					'name' => __($installaion),
					'type' => 'button',
					'desc' => __($installaion),
					'desc_tip' => true,
					'class' => 'button-primary',
					'css' => 'background-color:' . $background_color,
					'id' => 'status',
					'display_callback' => null
				),


				array(
					'name' => __('connect'),
					'type' => 'button',
					'desc' => __('Connect'),
					'desc_tip' => true,
					'class' => 'button-primary ',
					'id' => 'connect',
					'css' => get_option('installation_status') == 'installed' ? 'background-color:grey ;cursor:default' : 'background-color:#70bbde',
					'display_callback' => null
				),


				// Add a custom setting field
				array(
					'name' => __('Consumer Key', 'text-domain'),
					'desc_tip' => __('Enter the generated consumer Key here', 'text-domain'),
					'id' => 'consumer_key',
					'type' => 'text',
					'desc' => __('Enter the generated consumer Key here.', 'text-domain'),
					'display_callback' => null

				),

				array(
					'name' => __('Consumer Secret', 'text-domain'),
					'desc_tip' => __('Enter the generated consumer secret here', 'text-domain'),
					'id' => 'consumer_secret',
					'type' => 'text',
					'desc' => __('Enter the generated consumer secret here.', 'text-domain'),
					'display_callback' => null
				),

				array('type' => 'sectionend', 'id' => 'mottasl_Settings'),

			);


			return $custom_settings;
		}


		return $settings;
	}


	public function register_scripts()
	{
		if (
			!method_exists('Automattic\WooCommerce\Admin\PageController', 'is_admin_or_embed_page') ||
			!\Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()

		)
		{
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

		if (!function_exists('wc_admin_register_page'))
		{
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
