<?php

namespace Mottasl\Core;

use Mottasl\Utils\Constants;

class MottaslApi
{
	/**
	 * The base URL for the Mottasl API.
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * Constructor to initialize the API base URL.
	 */
	public function __construct()
	{
		// Use the new WooCommerce-specific API base URL
		$this->api_base_url = rtrim(Constants::WOOCOMMERCE_API_BASE_URL, '/');
	}

	/**
	 * Get the headers for API requests.
	 *
	 * @return array The headers to include in API requests.
	 */
	private function getHeaders()
	{
		return [
			'Content-Type' => 'application/json',
			'BUSINESS-ID' => get_option('mottasl_business_id')
		];
	}

	/**
	 * Get the API base URL.
	 *
	 * @return string The API base URL.
	 */
	public function getApiBaseUrl()
	{
		return $this->api_base_url;
	}

	/**
	 * Get the full URL for a specific API endpoint.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @return string The full API URL.
	 */
	public function getApiUrl($endpoint)
	{
		// Since WOOCOMMERCE_API_BASE_URL already includes the full path,
		// we just need to append the specific endpoint
		return $this->api_base_url . '/' . ltrim($endpoint, '/');
	}

	/**
	 * Create a prepared data object for API requests.
	 */
	public function prepareData($data)
	{
		$business_id = get_option('mottasl_business_id', '');
		if (empty($business_id)) {
			error_log('Mottasl Warning: Business ID is not configured. Please set up the business ID in WooCommerce > Settings > General > Mottasl WC.');
			$business_id = '';
		}
		$event_name = isset($data['event_name']) ? $data['event_name'] : 'unknown_event';

		// Remove event_name from data to avoid duplication
		unset($data['event_name']);

		$preData = [
			'event_name' 			=> $event_name,
			'business_id' 			=> $business_id,
			'site_url' 				=> get_site_url(),
			'store_url' 			=> get_site_url(),
			'woocommerce_version' 	=> class_exists('WooCommerce') ? \WC()->version : 'Unknown',
			'wordpress_version' 	=> get_bloginfo('version'),
			'plugin_version' 		=> Constants::VERSION,
			// force to use JSON format, even when the data is empty or an array
			'data' 				=> empty($data) ? new \stdClass() : $data,
		];
		error_log('Prepared data for Mottasl API: ' . json_encode($preData));
		return $preData;
	}

	/**
	 * Make a GET request to the Mottasl API.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array $params Optional parameters to include in the request.
	 * @return array The response from the API.
	 */
	public function get($endpoint, $params = [])
	{
		$url = $this->getApiUrl($endpoint);
		if (!empty($params)) {
			$url .= '?' . http_build_query($params);
		}

		$response = wp_remote_get($url);

		if (is_wp_error($response)) {
			error_log('Mottasl API GET request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($status_code >= 400) {
			$error_message = 'HTTP ' . $status_code . ' Error';
			$decoded_response = json_decode($response_body, true);
			if ($decoded_response && isset($decoded_response['message'])) {
				$error_message .= ': ' . $decoded_response['message'];
			} elseif ($decoded_response && isset($decoded_response['error'])) {
				$error_message .= ': ' . $decoded_response['error'];
			} else {
				$error_message .= ': ' . $response_body;
			}
			return ['error' => $error_message];
		}

		return json_decode($response_body, true);
	}

	/**
	 * Make a POST request to the Mottasl API.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array $data The data to send in the request body.
	 * @return array The response from the API.
	 */
	public function post($endpoint, $data = [])
	{
		// Check if business ID is configured for cart-related endpoints
		$business_id = get_option('mottasl_business_id', '');
		if (empty($business_id) && strpos($endpoint, 'cart') !== false) {
			error_log('Mottasl API Error: Business ID is required for cart operations. Please configure it in WooCommerce > Settings > General > Mottasl WC.');
			return ['error' => 'Business ID is not configured. Please set up your Mottasl Business ID in the plugin settings.'];
		}

		$url = $this->getApiUrl($endpoint);
		$headers = $this->getHeaders();

		// Special handling for abandoned cart endpoint - send data directly
		if ($endpoint === 'abandoned_cart.create') {
			$payload = $data; // Send cart data directly as array
			error_log('Mottasl API POST request URL: ' . $url);
			error_log('Mottasl API POST request headers: ' . json_encode($headers));
			error_log('Mottasl API POST request data (abandoned cart): ' . json_encode($payload));
		} else {
			// Use standard wrapper format for other endpoints
			$payload = $this->prepareData($data);
			error_log('Mottasl API POST request URL: ' . $url);
			error_log('Mottasl API POST request headers: ' . json_encode($headers));
			error_log('Mottasl API POST request data: ' . json_encode($payload));
		}

		$response = wp_remote_post($url, [
			'body' => json_encode($payload),
			'headers' => $headers,
		]);

		if (is_wp_error($response)) {
			error_log('Mottasl API POST request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		error_log('Mottasl API POST response status: ' . $status_code);
		error_log('Mottasl API POST response body: ' . $response_body);

		$decoded_response = json_decode($response_body, true);

		// Check if response has explicit success indicator
		if ($decoded_response && isset($decoded_response['success']) && $decoded_response['success'] === true) {
			// Response indicates success regardless of HTTP status code
			return $decoded_response;
		}

		// Check for success based on HTTP status code for APIs that use proper status codes
		if ($status_code >= 200 && $status_code < 300) {
			return $decoded_response ?: ['success' => true];
		}

		// If status code indicates error AND no explicit success, treat as error
		if ($status_code >= 400) {
			$error_message = 'HTTP ' . $status_code . ' Error';
			if ($decoded_response && isset($decoded_response['message'])) {
				$error_message .= ': ' . $decoded_response['message'];
			} elseif ($decoded_response && isset($decoded_response['error'])) {
				$error_message .= ': ' . $decoded_response['error'];
			} else {
				$error_message .= ': ' . $response_body;
			}
			error_log('Mottasl API POST error: ' . $error_message);
			return ['error' => $error_message];
		}

		return $decoded_response;
	}

	/**
	 * Make a PUT request to the Mottasl API.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param array $data The data to send in the request body.
	 * @return array The response from the API.
	 */
	public function put($endpoint, $data = [])
	{
		$url = $this->getApiUrl($endpoint);
		$data = $this->prepareData($data);
		$response = wp_remote_request($url, [
			'method'  => 'PUT',
			'body'    => json_encode($data),
			'headers' => $this->getHeaders(),
		]);

		if (is_wp_error($response)) {
			error_log('Mottasl API PUT request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code($response);
		$response_body = wp_remote_retrieve_body($response);

		if ($status_code >= 400) {
			$error_message = 'HTTP ' . $status_code . ' Error';
			$decoded_response = json_decode($response_body, true);
			if ($decoded_response && isset($decoded_response['message'])) {
				$error_message .= ': ' . $decoded_response['message'];
			} elseif ($decoded_response && isset($decoded_response['error'])) {
				$error_message .= ': ' . $decoded_response['error'];
			} else {
				$error_message .= ': ' . $response_body;
			}
			return ['error' => $error_message];
		}

		return json_decode($response_body, true);
	}

	/**
	 * Make a DELETE request to the Mottasl API.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @return array The response from the API.
	 */
	public function delete($endpoint)
	{
		$url = $this->getApiUrl($endpoint);

		$response = wp_remote_request($url, [
			'method'  => 'DELETE',
			'headers' => $this->getHeaders(),
		]);

		if (is_wp_error($response)) {
			error_log('Mottasl API DELETE request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		return json_decode(wp_remote_retrieve_body($response), true);
	}

	/**
	 * Make a request to the Mottasl API with custom method.
	 *
	 * @param string $endpoint The API endpoint to call.
	 * @param string $method The HTTP method to use (GET, POST, PUT, DELETE).
	 * @param array $data Optional data to send in the request body.
	 * @return array The response from the API.
	 */
	public function request($endpoint, $method = 'GET', $data = [])
	{
		$url = $this->getApiUrl($endpoint);

		$args = [
			'method'  => $method,
			'headers' => $this->getHeaders(),
		];

		if (!empty($data)) {
			$data = $this->prepareData($data);
			$args['body'] = json_encode($data);
		}

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			error_log('Mottasl API request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		return json_decode(wp_remote_retrieve_body($response), true);
	}
}
