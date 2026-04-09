<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Webhook_Handler {

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'fungies_process_webhook', array( __CLASS__, 'process_event' ), 10, 1 );
	}

	public static function register_route() {
		register_rest_route( 'fungies/v1', '/webhook', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'handle_request' ),
			'permission_callback' => '__return_true',
		) );
	}

	public static function handle_request( WP_REST_Request $request ) {
		$body      = $request->get_body();
		$signature = $request->get_header( 'x-fngs-signature' );

		self::log( 'Webhook received. Signature present: ' . ( $signature ? 'yes' : 'no' ) );

		if ( ! self::verify_signature( $body, $signature ) ) {
			self::log( 'Webhook signature verification failed.', 'error' );
			return new WP_REST_Response( array( 'error' => 'Invalid signature' ), 401 );
		}

		$payload = json_decode( $body, true );

		$event_type = $payload['type'] ?? ( $payload['event'] ?? '' );

		if ( ! $payload || empty( $event_type ) ) {
			self::log( 'Webhook payload invalid or missing event type.', 'error' );
			return new WP_REST_Response( array( 'error' => 'Invalid payload' ), 400 );
		}

		$payload['_event_type'] = $event_type;

		$idempotency = $payload['idempotencyKey'] ?? ( $payload['idempotency_key'] ?? '' );
		if ( $idempotency && self::is_duplicate( $idempotency ) ) {
			self::log( 'Duplicate webhook event skipped: ' . $idempotency );
			return new WP_REST_Response( array( 'status' => 'duplicate' ), 200 );
		}

		if ( $idempotency ) {
			self::mark_processed( $idempotency );
		}

		wp_schedule_single_event( time(), 'fungies_process_webhook', array( $payload ) );

		return new WP_REST_Response( array( 'status' => 'ok' ), 200 );
	}

	private static function verify_signature( $body, $signature ) {
		$secret = Fungies_Admin_Settings::get_active_webhook_secret();

		if ( empty( $secret ) ) {
			self::log( 'No webhook secret configured — skipping verification.', 'warning' );
			return true;
		}

		if ( empty( $signature ) ) {
			return false;
		}

		$computed = 'sha256_' . hash_hmac( 'sha256', $body, $secret );

		return hash_equals( $computed, $signature );
	}

	private static function is_duplicate( $key ) {
		return (bool) get_transient( 'fungies_wh_' . md5( $key ) );
	}

	private static function mark_processed( $key ) {
		set_transient( 'fungies_wh_' . md5( $key ), 1, DAY_IN_SECONDS );
	}

	public static function process_event( $payload ) {
		$event = $payload['_event_type'] ?? ( $payload['type'] ?? ( $payload['event'] ?? '' ) );
		self::log( 'Processing webhook event: ' . $event );

		Fungies_Order_Sync::handle_event( $event, $payload );
	}

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Webhook] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
