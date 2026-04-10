<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Checkout {

	public static function init() {
		add_action( 'woocommerce_thankyou_fungies', array( __CLASS__, 'handle_thankyou' ), 10, 1 );
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
}
