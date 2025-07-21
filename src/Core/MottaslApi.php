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
			'X-BUSINESS-Id' => get_option('business_id')
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

		return json_decode(wp_remote_retrieve_body($response), true);
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
		$url = $this->getApiUrl($endpoint);

		error_log('Mottasl API POST request URL: ' . $url);
		error_log('Mottasl API POST request data: ' . json_encode($data));
		$response = wp_remote_post($url, [
			'body' => json_encode($data),
			'headers' => $this->getHeaders(),
		]);

		if (is_wp_error($response)) {
			error_log('Mottasl API POST request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		return json_decode(wp_remote_retrieve_body($response), true);
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

		$response = wp_remote_request($url, [
			'method'  => 'PUT',
			'body'    => json_encode($data),
			'headers' => $this->getHeaders(),
		]);

		if (is_wp_error($response)) {
			error_log('Mottasl API PUT request error: ' . $response->get_error_message());
			return ['error' => $response->get_error_message()];
		}

		return json_decode(wp_remote_retrieve_body($response), true);
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
