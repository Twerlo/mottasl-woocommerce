<?php

namespace Mottasl\Admin;

use Mottasl\Core\MottaslApi;
use Mottasl\Utils\Constants;

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
		error_log('Mottasl Setup initialized');
		add_action('activated_plugin', [$this, 'mottasl_redirect']);
		add_action('admin_notices', array($this, 'wpb_admin_notice_warn'));
		add_action('admin_notices', array($this, 'errors_declaration'));
		add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
		add_action('init', array($this, 'register_page')); // Changed from admin_menu to init
		add_action('admin_init', array($this, 'handle_wc_admin_redirect')); // Add redirect handler
		add_filter('woocommerce_get_sections_general', array($this, 'settings_section'));
		add_filter('woocommerce_get_settings_general', array($this, 'mottasl_settings'), 10, 2);
		add_action('woocommerce_admin_field_button', array($this, 'freeship_add_admin_field_button'));
		add_action('woocommerce_admin_field_mottasl_status_display', array($this, 'render_status_display_field'));
		add_filter("plugin_action_links", array($this, "modify_plugin_action_links_defaults"), 10, 4);
		add_action('admin_notices', array($this, 'my_plugin_admin_notices'));
		add_action('plugins_loaded', array($this, 'mottasl_init'));

		// Add AJAX handlers for settings and connection testing
		add_action('wp_ajax_save_mottasl_settings', array($this, 'save_mottasl_settings'));
		add_action('wp_ajax_mottasl_test_connection', array($this, 'test_mottasl_connection'));
		add_action('wp_ajax_mottasl_check_status', array($this, 'check_mottasl_status'));

		apply_filters('woocommerce_webhook_deliver_async', function () {
			return false;
		});
	}

	function mottasl_init()
	{
		if (!function_exists('is_plugin_inactive')):
			require_once(ABSPATH . '/wp-admin/includes/plugin.php');
		endif;
		//COMMON WOOCOMMERCE METHOD
		if (!class_exists('WooCommerce')):
			//ALTERNATIVE METHOD
			//if( is_plugin_inactive( 'woocommerce/woocommerce.php' ) ) :
			add_action('admin_init', array($this, 'mottasl_deactivate'));
			add_action('admin_notices', array($this, 'mottasl_admin_notice'));

		endif;
	}
	function mottasl_deactivate()
	{
		deactivate_plugins('mottasl-woocommerce/mottasl.php');
	}

	function mottasl_admin_notice()
	{
		// echo '<div class="error"><p><strong>WooCommerce</strong> must be installed and activated to use Mottasl.</p></div>';
		// if (isset($_GET['activate']))
		// 	unset($_GET['activate']);
	}
	function modify_plugin_action_links_defaults($actions, $plugin_file, $plugin_data, $context)
	{
		// Check if this is our Mottasl plugin by comparing the plugin file
		if ($plugin_file === MOTTASL_WC_BASENAME) {
			$settings_url = admin_url('admin.php?page=wc-settings&tab=general&section=mottasl_connect');

			// Add Settings link to the beginning of the action links array
			$settings_link = '<a href="' . esc_url($settings_url) . '">' . __('Settings', 'mottasl-woocommerce') . '</a>';
			array_unshift($actions, $settings_link);
		}

		// Check if WooCommerce is not active and show warning for WooCommerce plugin
		if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			if ($plugin_file === 'woocommerce/woocommerce.php') {
				$actions[] = '<span style="color:red; background-color:#fff3cd; border:1px solid #ffeaa7; border-radius:3px; padding:3px 6px; display:inline-block; font-size:11px; font-weight:bold;">' .
					__('Required for Mottasl', 'mottasl-woocommerce') . '</span>';
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
			function handleButtonClick(event) {
				if (event.target.getAttribute('id') === 'connect') {
					// Validate required fields before connecting
					const consumerKey = document.getElementById('consumer_key') ? document.getElementById('consumer_key').value
						.trim() : '';
					const consumerSecret = document.getElementById('consumer_secret') ? document.getElementById('consumer_secret')
						.value.trim() : '';
					const businessId = document.getElementById('mottasl_business_id') ? document.getElementById(
						'mottasl_business_id').value.trim() : '';

					// Run validation to show errors
					validateAllFields();

					if (!consumerKey || !consumerSecret || !businessId) {
						alert(
							'Please fill in all required fields: Consumer Key, Consumer Secret, and Business ID before connecting.'
						);
						return;
					}

					// Validate Business ID format (must be a valid UUID)
					const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
					if (!uuidRegex.test(businessId)) {
						alert(
							'Please enter a valid Business ID. It should be in UUID format (e.g., 123e4567-e89b-12d3-a456-426614174000).'
						);
						return;
					}

					// Show loading state with spinner
					const originalValue = event.target.value;
					event.target.disabled = true;
					event.target.innerHTML = '<span class="spinner" style="float: left; margin-right: 5px;"></span>Connecting...';
					event.target.style.position = 'relative';

					// Save current field values to WordPress options
					const formData = new FormData();
					formData.append('action', 'save_mottasl_settings');
					formData.append('consumer_key', consumerKey);
					formData.append('consumer_secret', consumerSecret);
					formData.append('mottasl_business_id', businessId);
					formData.append('nonce', '<?php echo wp_create_nonce('mottasl_save_settings'); ?>');

					// First save the settings, then attempt connection
					fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
							method: 'POST',
							body: formData
						}).then(() => {
							// Now attempt the connection
							return fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
								method: 'POST',
								headers: {
									'Content-Type': 'application/x-www-form-urlencoded',
								},
								body: new URLSearchParams({
									action: 'mottasl_test_connection',
									nonce: '<?php echo wp_create_nonce('mottasl_test_connection'); ?>'
								})
							});
						}).then(response => response.json())
						.then(data => {
							event.target.disabled = false;
							event.target.innerHTML = originalValue;

							if (data.success) {
								// Connection successful
								alert('Successfully connected to Mottasl! Redirecting to the platform...');
								if (data.data.redirect_url) {
									window.open(data.data.redirect_url, '_blank');
								}
								setTimeout(() => {
									window.location.reload();
								}, 1000);
							} else {
								// Connection failed
								alert('Connection failed: ' + (data.data ? data.data :
									'Please check your credentials and try again.'));
							}
						}).catch(error => {
							event.target.disabled = false;
							event.target.innerHTML = originalValue;
							alert('Connection failed: ' + error.message);
						});

				} else {
					event.preventDefault();
				}
			}

			// Enhanced validation function with proper error display
			function validateAllFields() {
				const fields = ['consumer_key', 'consumer_secret', 'mottasl_business_id'];
				let hasErrors = false;

				fields.forEach(fieldId => {
					const field = document.getElementById(fieldId);
					const errorElement = document.getElementById(fieldId + '_error');

					if (!field || !errorElement) return;

					const value = field.value.trim();
					let isValid = true;
					let errorMessage = '';

					switch (fieldId) {
						case 'mottasl_business_id':
							if (!value) {
								isValid = false;
								errorMessage = 'Business ID is required';
							} else {
								const uuidRegex =
									/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
								if (!uuidRegex.test(value)) {
									isValid = false;
									errorMessage =
										'Please enter a valid UUID format (e.g., 123e4567-e89b-12d3-a456-426614174000)';
								}
							}
							break;

						case 'consumer_key':
							if (!value) {
								isValid = false;
								errorMessage = 'Consumer Key is required';
							} else if (value.length < 10) {
								isValid = false;
								errorMessage = 'Consumer Key seems too short';
							}
							break;

						case 'consumer_secret':
							if (!value) {
								isValid = false;
								errorMessage = 'Consumer Secret is required';
							} else if (value.length < 10) {
								isValid = false;
								errorMessage = 'Consumer Secret seems too short';
							}
							break;
					}

					// Update error display
					if (isValid) {
						errorElement.style.display = 'none';
						errorElement.textContent = '';
						field.style.borderColor = '';
					} else {
						errorElement.style.display = 'inline';
						errorElement.textContent = errorMessage;
						field.style.borderColor = '#d63638';
						hasErrors = true;
					}
				});

				return !hasErrors;
			}

			// Live validation function for individual fields
			function validateSingleField(fieldId) {
				const field = document.getElementById(fieldId);
				const errorElement = document.getElementById(fieldId + '_error');

				if (!field || !errorElement) return;

				const value = field.value.trim();
				let isValid = true;
				let errorMessage = '';

				switch (fieldId) {
					case 'mottasl_business_id':
						if (!value) {
							isValid = false;
							errorMessage = 'Business ID is required';
						} else {
							const uuidRegex = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
							if (!uuidRegex.test(value)) {
								isValid = false;
								errorMessage = 'Please enter a valid UUID format (e.g., 123e4567-e89b-12d3-a456-426614174000)';
							}
						}
						break;

					case 'consumer_key':
						if (!value) {
							isValid = false;
							errorMessage = 'Consumer Key is required';
						} else if (value.length < 10) {
							isValid = false;
							errorMessage = 'Consumer Key seems too short';
						}
						break;

					case 'consumer_secret':
						if (!value) {
							isValid = false;
							errorMessage = 'Consumer Secret is required';
						} else if (value.length < 10) {
							isValid = false;
							errorMessage = 'Consumer Secret seems too short';
						}
						break;
				}

				// Update error display
				if (isValid) {
					errorElement.style.display = 'none';
					errorElement.textContent = '';
					field.classList.remove('mottasl-input-invalid');
					field.classList.add('mottasl-input-valid');
				} else {
					errorElement.style.display = 'inline';
					errorElement.textContent = errorMessage;
					field.classList.remove('mottasl-input-valid');
					field.classList.add('mottasl-input-invalid');
				}

				return isValid;
			}

			// Comprehensive validation function
			function validateFields() {
				const fields = ['consumer_key', 'consumer_secret', 'mottasl_business_id'];
				let allValid = true;

				fields.forEach(fieldId => {
					const isValid = validateSingleField(fieldId);
					if (!isValid) {
						allValid = false;
					}
				});

				// Update connect button state
				const connectButton = document.getElementById('connect');
				if (connectButton) {
					connectButton.disabled = !allValid;
					if (allValid) {
						connectButton.style.opacity = '1';
						connectButton.style.cursor = 'pointer';
					} else {
						connectButton.style.opacity = '0.6';
						connectButton.style.cursor = 'not-allowed';
					}
				}

				return allValid;
			}

			// Add event listeners to form fields
			document.addEventListener('DOMContentLoaded', function() {
				validateFields();

				['consumer_key', 'consumer_secret', 'mottasl_business_id'].forEach(function(fieldId) {
					const field = document.getElementById(fieldId);
					if (field) {
						// Real-time validation on input
						field.addEventListener('input', function() {
							validateSingleField(fieldId);
							validateFields();
						});

						// Validation on blur
						field.addEventListener('blur', function() {
							validateSingleField(fieldId);
						});

						// Validation on change
						field.addEventListener('change', function() {
							validateSingleField(fieldId);
							validateFields();
						});
					}
				});

				// Check status on page load if we have a connect button
				const connectButton = document.getElementById('connect');
				if (connectButton) {
					checkConnectionStatus();
				}
			});

			// Function to check connection status periodically
			function checkConnectionStatus() {
				fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
						body: new URLSearchParams({
							action: 'mottasl_check_status',
							nonce: '<?php echo wp_create_nonce('mottasl_check_status'); ?>'
						})
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							updateConnectionStatusDisplay(data.data.status, data.data.has_credentials);
						}
					})
					.catch(error => {
						console.log('Status check failed:', error);
					});
			}

			// Function to update the status display dynamically
			function updateConnectionStatusDisplay(status, hasCredentials) {
				const statusDisplay = document.querySelector('.mottasl-status-display');
				if (!statusDisplay) return;

				let config = {
					color: '#d63638',
					bg_color: '#fcf0f1',
					border_color: '#d63638',
					icon: '⚠',
					message: 'Connection Required',
					description: 'Please complete the connection process by clicking the Connect button below.'
				};

				switch (status) {
					case 'installed':
						config = {
							color: '#00a32a',
							bg_color: '#d5f4e6',
							border_color: '#00a32a',
							icon: '✓',
							message: 'Successfully Connected',
							description: 'Your store is connected to Mottasl and ready to sync data.'
						};
						break;
					case 'error':
						config = {
							color: '#d63638',
							bg_color: '#fcf0f1',
							border_color: '#d63638',
							icon: '✗',
							message: 'Connection Failed',
							description: 'There was an error connecting to Mottasl. Please try again.'
						};
						break;
				}

				// Update the display
				statusDisplay.style.borderColor = config.border_color;
				statusDisplay.style.backgroundColor = config.bg_color;
				statusDisplay.style.color = config.color;

				const iconElement = statusDisplay.querySelector('div:first-child');
				const messageElement = statusDisplay.querySelector('div:last-child div:first-child');
				const descriptionElement = statusDisplay.querySelector('div:last-child div:last-child');

				if (iconElement) iconElement.textContent = config.icon;
				if (messageElement) messageElement.textContent = config.message;
				if (descriptionElement) descriptionElement.textContent = config.description;
			}
		</script>
		<style>
			.mottasl-error {
				font-size: 12px;
				font-weight: normal;
				line-height: 1.4;
				margin-top: 4px;
			}

			.mottasl-status-display {
				margin-bottom: 20px;
				transition: all 0.3s ease;
			}

			.mottasl-input-invalid {
				border: 1px solid #d63638 !important;
				box-shadow: 0 0 2px rgba(214, 54, 56, 0.3) !important;
			}

			.mottasl-input-valid {
				border: 1px solid #00a32a !important;
				box-shadow: 0 0 2px rgba(0, 163, 42, 0.3) !important;
			}
		</style>
<?php
	}
	function my_plugin_admin_notices()
	{
		if (!get_option('mottasl_wc_installation_status') == 'installed') {
			echo "<div class='updated'><p>Please try to reconnect to Mottasl, if you face any issue please contact the support</p></div>";
		}
	}


	function mottasl_redirect($plugin_name): void
	{
		error_log('Init Mottasl redirect');
		error_log('Plugin name: ' . $plugin_name);
		// Remove automatic redirect on activation - user should use connect button instead
		// The redirect will now only happen when user clicks "Connect" button in settings
		error_log('Plugin activated but no automatic redirect - user should configure settings first');
	}
	function woocommerce_deactivation($plugin_file, $plugin_data)
	{
		if ($plugin_file == 'woocommerce/woocommerce.php') {
			deactivate_plugins('mottasl-woocommerce/mottasl.php');
		}


		return $plugin_data;
	}

	function errors_declaration()
	{
		$error = get_option('notice_error');
		if ($error) {
			echo '<div class="error notice-warning is-dismissible">
			  <p>' . $error . '</p>
			  </div>';
		}
	}

	function wpb_admin_notice_warn()
	{
		if (!get_option('consumer_key') || !get_option('consumer_secret')) {
			$settings_url = admin_url('admin.php?page=wc-settings&tab=general&section=mottasl_connect');
			echo '<div class="notice notice-warning is-dismissible">
				<p><strong>' . __('Mottasl Plugin Configuration Required', 'mottasl-woocommerce') . '</strong></p>
				<p>' . __('Please configure your WooCommerce credentials to connect with Mottasl.', 'mottasl-woocommerce') . '</p>
				<p><a href="' . esc_url($settings_url) . '" class="button button-primary">' . __('Go to Mottasl Settings', 'mottasl-woocommerce') . '</a></p>
			</div>';
		}

		if (!get_option('consumer_key') || !get_option('consumer_secret')) {
			$api_keys_url = admin_url('admin.php?page=wc-settings&tab=advanced&section=keys');
			echo '<div class="notice notice-info is-dismissible">
				<p><strong>' . __('Need WooCommerce API Keys?', 'mottasl-woocommerce') . '</strong></p>
				<p>' . __('Generate WooCommerce REST API credentials to connect your store with Mottasl.', 'mottasl-woocommerce') . '</p>
				<p><a href="' . esc_url($api_keys_url) . '" class="button button-secondary">' . __('Create API Keys', 'mottasl-woocommerce') . '</a></p>
			</div>';
		}
	}
	function settings_section($sections)
	{
		$sections['mottasl_connect'] = __('Mottasl Settings', 'text-domain');
		return $sections;
	}


	function mottasl_settings($settings, $current_section)
	{
		if ('mottasl_connect' == $current_section) {
			$installation_status = get_option('mottasl_wc_installation_status', 'pending');

			// Check if we have all required credentials
			$consumer_key = get_option('consumer_key');
			$consumer_secret = get_option('consumer_secret');
			$business_id = get_option('mottasl_business_id');

			// If credentials are missing, force status to pending
			if (empty($consumer_key) || empty($consumer_secret) || empty($business_id)) {
				$installation_status = 'pending';
				update_option('mottasl_wc_installation_status', 'pending');
			}

			// Determine status colors and messages
			$status_config = array(
				'installed' => array(
					'color' => '#00a32a',
					'bg_color' => '#d5f4e6',
					'border_color' => '#00a32a',
					'icon' => '✓',
					'message' => 'Successfully Connected',
					'description' => 'Your store is connected to Mottasl and ready to sync data.'
				),
				'pending' => array(
					'color' => '#d63638',
					'bg_color' => '#fcf0f1',
					'border_color' => '#d63638',
					'icon' => '⚠',
					'message' => 'Connection Required',
					'description' => 'Please complete the connection process by clicking the Connect button below.'
				),
				'error' => array(
					'color' => '#d63638',
					'bg_color' => '#fcf0f1',
					'border_color' => '#d63638',
					'icon' => '✗',
					'message' => 'Connection Failed',
					'description' => 'There was an error connecting to Mottasl. Please try again.'
				)
			);

			$current_status = isset($status_config[$installation_status]) ? $status_config[$installation_status] : $status_config['pending'];

			$custom_settings = array(
				array(
					'name' => __('Mottasl Settings'),
					'type' => 'title',
					'desc' => __('Configure your connection to the Mottasl platform'),
					'desc_tip' => true,
					'id' => 'mottasl_Settings',
				),

				// Enhanced status display without input field
				array(
					'name' => __('Connection Status'),
					'type' => 'mottasl_status_display',
					'id' => 'mottasl_status_display',
					'status_data' => $current_status,
					'installation_status' => $installation_status,
				),

				// Connect button (only show if not installed)
				array(
					'name' => $installation_status === 'installed' ? __('Reconnect') : __('Connect to Mottasl'),
					'type' => 'button',
					'desc' => $installation_status === 'installed'
						? __('Click to reconnect or refresh your connection with Mottasl')
						: __('Click to establish connection with the Mottasl platform'),
					'desc_tip' => true,
					'class' => 'button-primary',
					'id' => 'connect',
					'css' => $installation_status === 'installed'
						? 'background-color:#0073aa; margin-top: 10px;'
						: 'background-color:#70bbde; margin-top: 10px;',
					'display_callback' => null
				),

				// Add a custom setting field
				array(
					'name' => __('Mottasl Business Id *', 'text-domain'),
					'desc_tip' => __('Enter the generated Mottasl Business Id here', 'text-domain'),
					'id' => 'mottasl_business_id',
					'type' => 'text',
					'desc' => __('Copy the mottasl Business Id from your account in app.mottasl.ai. Must be in UUID format (e.g., 123e4567-e89b-12d3-a456-426614174000).<br><span id="mottasl_business_id_error" class="mottasl-error" style="color: red; display: none;"></span>', 'text-domain'),
					'display_callback' => null
				),
				array(
					'name' => __('Consumer Key *', 'text-domain'),
					'desc_tip' => __('Enter the generated consumer Key here', 'text-domain'),
					'id' => 'consumer_key',
					'type' => 'text',
					'desc' => __('Enter the generated consumer Key here.<br><span id="consumer_key_error" class="mottasl-error" style="color: red; display: none;"></span>', 'text-domain'),
					'display_callback' => null
				),

				array(
					'name' => __('Consumer Secret *', 'text-domain'),
					'desc_tip' => __('Enter the generated consumer secret here', 'text-domain'),
					'id' => 'consumer_secret',
					'type' => 'text',
					'desc' => __('Enter the generated consumer secret here.<br><span id="consumer_secret_error" class="mottasl-error" style="color: red; display: none;"></span>', 'text-domain'),
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

		) {
			return;
		}

		$script_path = dirname(MAIN_PLUGIN_FILE) . '/assets/index.js';

		$script_asset_path = dirname(MAIN_PLUGIN_FILE) . '/assets/index.asset.php';
		$script_asset = file_exists($script_asset_path)
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version' => filemtime($script_path),
			);
		$script_url = plugins_url($script_path, MAIN_PLUGIN_FILE);

		wp_register_script(
			'mottasl',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'mottasl',
			plugins_url('./../assets/index.css', MAIN_PLUGIN_FILE),
			// Add any dependencies styles may have, such as wp-components.
			[],
			filemtime(dirname(MAIN_PLUGIN_FILE) . '/assets/index.css')
		);

		wp_enqueue_script('mottasl');
		wp_enqueue_style('mottasl');
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
			[
				'id' => 'mottasl-connect',
				'title' => __('Mottasl', 'mottasl'),
				'parent' => 'woocommerce',
				'path' => '/mottasl-connect',
				'capability' => 'manage_woocommerce',
				'icon' => 'dashicons-admin-plugins',
				'position' => 56,
			]
		);
	}

	/**
	 * Handle WC Admin page redirect to traditional settings
	 */
	public function handle_wc_admin_redirect()
	{
		// Check if we're trying to access the WC Admin Mottasl page
		if (
			isset($_GET['page']) && $_GET['page'] === 'wc-admin' &&
			isset($_GET['path']) && $_GET['path'] === '/mottasl-connect'
		) {

			// Redirect to the traditional settings page that we know works
			$settings_url = admin_url('admin.php?page=wc-settings&tab=general&section=mottasl_connect');
			wp_redirect($settings_url);
			exit;
		}
	}

	/**
	 * Render the custom status display field
	 */
	public function render_status_display_field($value)
	{
		$installation_status = get_option('mottasl_wc_installation_status', 'pending');

		// Determine status colors and messages
		$status_config = array(
			'installed' => array(
				'color' => '#00a32a',
				'bg_color' => '#d5f4e6',
				'border_color' => '#00a32a',
				'icon' => '✓',
				'message' => 'Successfully Connected',
				'description' => 'Your store is connected to Mottasl and ready to sync data.'
			),
			'pending' => array(
				'color' => '#d63638',
				'bg_color' => '#fcf0f1',
				'border_color' => '#d63638',
				'icon' => '⚠',
				'message' => 'Connection Required',
				'description' => 'Please complete the connection process by clicking the Connect button below.'
			),
			'error' => array(
				'color' => '#d63638',
				'bg_color' => '#fcf0f1',
				'border_color' => '#d63638',
				'icon' => '✗',
				'message' => 'Connection Failed',
				'description' => 'There was an error connecting to Mottasl. Please try again.'
			)
		);

		$current_status = isset($status_config[$installation_status]) ? $status_config[$installation_status] : $status_config['pending'];

		// Render the status display HTML
		echo '<div class="mottasl-status-display" style="padding: 10px; border: 1px solid ' . esc_attr($current_status['border_color']) . '; background-color: ' . esc_attr($current_status['bg_color']) . '; color: ' . esc_attr($current_status['color']) . '; border-radius: 4px; display: flex; align-items: center;">';
		echo '<div style="margin-right: 8px; font-size: 18px;">' . esc_html($current_status['icon']) . '</div>';
		echo '<div>';
		echo '<div style="font-weight: bold; margin-bottom: 4px;">' . esc_html($current_status['message']) . '</div>';
		echo '<div style="font-size: 14px;">' . esc_html($current_status['description']) . '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Save Mottasl settings via AJAX
	 */
	public function save_mottasl_settings()
	{
		// Verify nonce for security
		if (!wp_verify_nonce($_POST['nonce'], 'mottasl_save_settings')) {
			wp_die('Security check failed');
		}

		// Check user capabilities
		if (!current_user_can('manage_woocommerce')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}

		// Sanitize and save the settings
		$consumer_key = sanitize_text_field($_POST['consumer_key']);
		$consumer_secret = sanitize_text_field($_POST['consumer_secret']);
		$business_id = sanitize_text_field($_POST['mottasl_business_id']);

		// Validate Business ID format
		if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $business_id)) {
			wp_send_json_error('Invalid Business ID format. Please enter a valid UUID.');
			return;
		}

		// Save settings
		update_option('consumer_key', $consumer_key);
		update_option('consumer_secret', $consumer_secret);
		update_option('mottasl_business_id', $business_id);
		update_option('business_id', $business_id); // For API headers

		wp_send_json_success('Settings saved successfully');
	}

	/**
	 * Test Mottasl connection via AJAX
	 */
	public function test_mottasl_connection()
	{
		// Verify nonce for security
		if (!wp_verify_nonce($_POST['nonce'], 'mottasl_test_connection')) {
			wp_die('Security check failed');
		}

		// Check user capabilities
		if (!current_user_can('manage_woocommerce')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}

		try {
			// Get saved credentials
			$consumer_key = get_option('consumer_key');
			$consumer_secret = get_option('consumer_secret');
			$business_id = get_option('mottasl_business_id');

			// Validate required fields
			if (empty($consumer_key) || empty($consumer_secret) || empty($business_id)) {
				wp_send_json_error('Missing required credentials. Please save your settings first.');
				return;
			}

			// Initialize the API
			$api = new MottaslApi();

			// Prepare installation data
			$installation_data = array(
				'event_name' 			=> 'installed',
				'business_id' 			=> $business_id,
				'site_url' 				=> get_site_url(),
				'store_url' 			=> get_site_url(),
				'woocommerce_version' 	=> class_exists('WooCommerce') ? \WC()->version : 'Unknown',
				'wordpress_version' 	=> get_bloginfo('version'),
				'plugin_version' 		=> Constants::VERSION,
				'data' 					=> [
					'consumer_key' 		=> $consumer_key,
					'consumer_secret' 	=> $consumer_secret,
					'business_id' 		=> $business_id,
					'store_name' 		=> get_bloginfo('name'),
					'store_email' 		=> get_bloginfo('admin_email'),
					'store_address' 	=> get_option('woocommerce_store_address', ''),
					'store_city' 		=> get_option('woocommerce_store_city', ''),
					'store_postcode' 	=> get_option('woocommerce_store_postcode', ''),
					'store_country' 	=> get_option('woocommerce_store_country', ''),
					'store_state' 		=> get_option('woocommerce_store_state', ''),
					'store_phone' 		=> get_option('woocommerce_store_phone', ''),
					'store_currency' 	=> get_woocommerce_currency(),
					'store_timezone' 	=> get_option('timezone_string', 'UTC'),
					'store_locale' 		=> get_locale(),
					'store_language' 	=> get_bloginfo('language'),
					'store_ssl' 		=> is_ssl() ? 'yes' : 'no'
				]
			);

			error_log('Attempting connection with data: ' . json_encode($installation_data));

			// Make request to installation.confirmation endpoint
			$response = $api->post('installation.confirmation', $installation_data);

			error_log('Installation confirmation response: ' . json_encode($response));

			// Check if the response indicates success
			if (isset($response['error'])) {
				update_option('mottasl_wc_installation_status', 'error');
				wp_send_json_error('Connection failed: ' . $response['error']);
				return;
			}

			// Check for successful response structure
			if (isset($response['success']) && $response['success'] === true) {
				// Connection successful
				update_option('mottasl_wc_installation_status', 'installed');

				// Prepare redirect URL to Mottasl platform
				$redirect_url = Constants::MOTTASL_APP_BASE_URL . '/businesses/' . $business_id . '/integrations';

				wp_send_json_success(array(
					'message' => 'Connection successful',
					'redirect_url' => $redirect_url
				));
				return;
			}

			// Check if response contains expected data (alternative success check)
			if (isset($response['data']) || isset($response['status'])) {
				// Assume success if we get structured data back
				update_option('mottasl_wc_installation_status', 'installed');

				$redirect_url = Constants::MOTTASL_APP_BASE_URL . '/businesses/' . $business_id . '/integrations';

				wp_send_json_success(array(
					'message' => 'Connection successful',
					'redirect_url' => $redirect_url
				));
				return;
			}

			// If we reach here, the response format is unexpected
			update_option('mottasl_wc_installation_status', 'error');
			wp_send_json_error('Unexpected response from Mottasl API. Please check your credentials and try again.');
		} catch (\Exception $e) {
			error_log('Mottasl connection test exception: ' . $e->getMessage());
			update_option('mottasl_wc_installation_status', 'error');
			wp_send_json_error('Connection failed: ' . $e->getMessage());
		}
	}

	/**
	 * Check Mottasl status without attempting connection
	 */
	public function check_mottasl_status()
	{
		// Verify nonce for security
		if (!wp_verify_nonce($_POST['nonce'], 'mottasl_check_status')) {
			wp_die('Security check failed');
		}

		// Check user capabilities
		if (!current_user_can('manage_woocommerce')) {
			wp_die('You do not have sufficient permissions to access this page.');
		}

		$installation_status = get_option('mottasl_wc_installation_status', 'pending');

		// Check if credentials are available
		$consumer_key = get_option('consumer_key');
		$consumer_secret = get_option('consumer_secret');
		$business_id = get_option('mottasl_business_id');

		$has_credentials = !empty($consumer_key) && !empty($consumer_secret) && !empty($business_id);

		wp_send_json_success(array(
			'status' => $installation_status,
			'has_credentials' => $has_credentials,
			'timestamp' => current_time('timestamp')
		));
	}
}
