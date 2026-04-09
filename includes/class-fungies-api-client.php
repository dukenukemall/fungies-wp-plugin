<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_API_Client {

	private $base_url;
	private $public_key;
	private $secret_key;

	public function __construct() {
		$this->base_url   = Fungies_Admin_Settings::is_sandbox() ? FUNGIES_API_STAGING_URL : FUNGIES_API_BASE_URL;
		$this->public_key = Fungies_Admin_Settings::get_active_public_key();
		$this->secret_key = Fungies_Admin_Settings::get_active_secret_key();
	}

	private function headers() {
		return array(
			'Content-Type'       => 'application/json',
			'x-fngs-public-key'  => $this->public_key,
			'x-fngs-secret-key'  => $this->secret_key,
		);
	}

	public function get( $endpoint, $query = array() ) {
		$url = $this->base_url . $endpoint;

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$this->log( 'GET ' . $url );

		$response = wp_remote_get( $url, array(
			'headers' => $this->headers(),
			'timeout' => 30,
		) );

		return $this->handle_response( $response );
	}

	public function post( $endpoint, $body = array() ) {
		$url = $this->base_url . $endpoint;

		$this->log( 'POST ' . $url );

		$response = wp_remote_post( $url, array(
			'headers' => $this->headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		return $this->handle_response( $response );
	}

	public function patch( $endpoint, $body = array() ) {
		$url = $this->base_url . $endpoint;

		$this->log( 'PATCH ' . $url );

		$response = wp_remote_request( $url, array(
			'method'  => 'PATCH',
			'headers' => $this->headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => 30,
		) );

		return $this->handle_response( $response );
	}

	private function handle_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$this->log( 'HTTP Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		$this->log( "Response {$code}: " . wp_json_encode( $data ) );

		if ( $code < 200 || $code >= 300 ) {
			$message = $data['error']['message']
				?? ( $data['message'] ?? "HTTP {$code}" );
			return new WP_Error( 'fungies_api_error', $message, array( 'status' => $code ) );
		}

		return $data;
	}

	public function get_products() {
		return $this->get( '/products/list' );
	}

	public function get_product( $id ) {
		return $this->get( '/products/' . $id );
	}

	public function get_offers( $query = array() ) {
		return $this->get( '/offers/list', $query );
	}

	public function get_orders() {
		return $this->get( '/orders/list' );
	}

	private function log( $message, $level = 'info' ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, $message, array( 'source' => 'fungies' ) );
	}
}
