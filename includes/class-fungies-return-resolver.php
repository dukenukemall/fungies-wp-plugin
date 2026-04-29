<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Resolves the WooCommerce order when the customer is bounced back from
 * the Fungies hosted checkout via the `wc-api=fungies_return` URL.
 *
 * Why this exists:
 *   The return URL has only `fngs-order-id` + `fngs-user-email`. Looking up
 *   the WC order via the `_fungies_order_id` post meta only works once the
 *   `payment_success` webhook has fully run, which is a hard race (especially
 *   on production, where the webhook can land before the user's browser).
 *   This helper layers three deterministic-to-flaky strategies so the user
 *   reliably lands on the order-received page instead of the cart.
 */
class Fungies_Return_Resolver {

	const SESSION_KEY = 'fungies_pending_wc_order_id';
	const POLL_ATTEMPTS = 6;
	const POLL_INTERVAL_US = 500000; // 0.5s × 6 = 3s budget

	/** Called from Fungies_Payment_Gateway::process_payment, before redirect. */
	public static function remember( $order_id ) {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_KEY, (int) $order_id );
		}
	}

	public static function clear() {
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
		}
	}

	/**
	 * @return WC_Order|null
	 */
	public static function resolve( $fungies_order_id, $fungies_email ) {
		// 1. Webhook already linked the meta (best case).
		$order = self::by_meta( $fungies_order_id );
		if ( $order ) return self::trace( 'meta', $order );

		// 2. WC session — set at redirect time, fully independent of webhook.
		$order = self::from_session();
		if ( $order ) return self::trace( 'session', $order );

		// 3. Email fallback, broadened to post-pending statuses (handles fast webhook).
		$order = self::by_recent_email( $fungies_email );
		if ( $order ) return self::trace( 'email', $order );

		// 4. Brief poll — webhook may be racing right now.
		return self::poll( $fungies_order_id, $fungies_email );
	}

	private static function by_meta( $fungies_order_id ) {
		if ( empty( $fungies_order_id ) ) return null;
		$orders = wc_get_orders( array(
			'meta_key'   => '_fungies_order_id',
			'meta_value' => $fungies_order_id,
			'limit'      => 1,
		) );
		return ! empty( $orders ) ? $orders[0] : null;
	}

	private static function from_session() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) return null;
		$id = (int) WC()->session->get( self::SESSION_KEY );
		if ( ! $id ) return null;
		$order = wc_get_order( $id );
		return ( $order && 'fungies' === $order->get_payment_method() ) ? $order : null;
	}

	private static function by_recent_email( $email ) {
		if ( ! $email || ! is_email( $email ) ) return null;
		$orders = wc_get_orders( array(
			'status'         => array( 'pending', 'on-hold', 'processing', 'completed' ),
			'payment_method' => 'fungies',
			'billing_email'  => $email,
			'limit'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );
		return ! empty( $orders ) ? $orders[0] : null;
	}

	private static function poll( $fungies_order_id, $fungies_email ) {
		for ( $i = 1; $i <= self::POLL_ATTEMPTS; $i++ ) {
			usleep( self::POLL_INTERVAL_US );

			$order = self::by_meta( $fungies_order_id );
			if ( $order ) return self::trace( "poll-meta#{$i}", $order );

			$order = self::by_recent_email( $fungies_email );
			if ( $order ) return self::trace( "poll-email#{$i}", $order );
		}
		return null;
	}

	private static function trace( $strategy, $order ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info(
				sprintf( '[Return] Resolved WC order #%d via %s.', $order->get_id(), $strategy ),
				array( 'source' => 'fungies' )
			);
		}
		return $order;
	}
}
