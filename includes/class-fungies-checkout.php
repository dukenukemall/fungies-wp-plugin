<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Checkout {

	public static function init() {
		add_action( 'woocommerce_thankyou_fungies', array( __CLASS__, 'handle_thankyou' ), 10, 1 );
		add_action( 'woocommerce_api_fungies_return', array( __CLASS__, 'handle_return' ) );
	}

	public static function handle_return() {
		$fungies_order_id = isset( $_GET['fngs-order-id'] ) ? sanitize_text_field( wp_unslash( $_GET['fngs-order-id'] ) ) : '';
		$fungies_email    = isset( $_GET['fngs-user-email'] ) ? sanitize_email( wp_unslash( $_GET['fngs-user-email'] ) ) : '';

		wc_get_logger()->info(
			'[Return] Customer returned from Fungies. fngs-order-id=' . $fungies_order_id,
			array( 'source' => 'fungies' )
		);

		$wc_order = null;

		if ( $fungies_order_id ) {
			$wc_order = self::find_order_by_meta( '_fungies_order_id', $fungies_order_id );
		}

		if ( ! $wc_order ) {
			$wc_order = self::find_latest_pending_fungies_order( $fungies_email );
		}

		if ( $wc_order && $fungies_order_id ) {
			$existing = $wc_order->get_meta( '_fungies_order_id' );
			if ( ! $existing ) {
				$wc_order->update_meta_data( '_fungies_order_id', $fungies_order_id );
				$wc_order->save();
				wc_get_logger()->info(
					sprintf( '[Return] Linked Fungies order %s -> WC order #%d', $fungies_order_id, $wc_order->get_id() ),
					array( 'source' => 'fungies' )
				);
			}
		}

		if ( $wc_order ) {
			wp_safe_redirect( $wc_order->get_checkout_order_received_url() );
			exit;
		}

		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	public static function handle_thankyou( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$status = $order->get_status();

		if ( in_array( $status, array( 'completed', 'processing' ), true ) ) {
			echo '<p class="woocommerce-info">';
			esc_html_e( 'Your Fungies payment was successful! Thank you for your purchase.', 'fungies-wp' );
			echo '</p>';
			return;
		}

		if ( 'pending' === $status ) {
			echo '<p class="woocommerce-info">';
			esc_html_e( 'Your payment is being processed by Fungies. You will receive a confirmation email shortly.', 'fungies-wp' );
			echo '</p>';
			return;
		}

		if ( 'failed' === $status ) {
			echo '<p class="woocommerce-error">';
			esc_html_e( 'Your Fungies payment could not be completed. Please try again or contact support.', 'fungies-wp' );
			echo '</p>';
		}
	}

	public static function get_return_url() {
		return add_query_arg( 'wc-api', 'fungies_return', home_url( '/' ) );
	}

	private static function find_order_by_meta( $key, $value ) {
		if ( empty( $value ) ) {
			return null;
		}

		$orders = wc_get_orders( array(
			'meta_key'   => $key,
			'meta_value' => $value,
			'limit'      => 1,
		) );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	private static function find_latest_pending_fungies_order( $email = '' ) {
		$args = array(
			'status'         => 'pending',
			'payment_method' => 'fungies',
			'limit'          => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( $email ) {
			$args['billing_email'] = $email;
		}

		$orders = wc_get_orders( $args );

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
