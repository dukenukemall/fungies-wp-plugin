<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Order_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		add_meta_box(
			'fungies-order-details',
			__( 'Fungies Order Details', 'fungies-wp' ),
			array( __CLASS__, 'render' ),
			'shop_order',
			'side',
			'high'
		);

		// HPOS compatibility
		add_meta_box(
			'fungies-order-details',
			__( 'Fungies Order Details', 'fungies-wp' ),
			array( __CLASS__, 'render' ),
			'woocommerce_page_wc-orders',
			'side',
			'high'
		);
	}

	public static function render( $post_or_order ) {
		$order = ( $post_or_order instanceof WP_Post )
			? wc_get_order( $post_or_order->ID )
			: $post_or_order;

		if ( ! $order ) return;

		$fields = array(
			'_fungies_order_id'        => __( 'Fungies Order ID', 'fungies-wp' ),
			'_fungies_order_number'    => __( 'Order Number', 'fungies-wp' ),
			'_fungies_payment_id'      => __( 'Payment ID', 'fungies-wp' ),
			'_fungies_payment_type'    => __( 'Payment Type', 'fungies-wp' ),
			'_fungies_subscription_id' => __( 'Subscription ID', 'fungies-wp' ),
			'_fungies_fee'             => __( 'Fungies Fee', 'fungies-wp' ),
			'_fungies_tax'             => __( 'Tax Amount', 'fungies-wp' ),
		);

		$has_data = false;
		echo '<table class="widefat striped"><tbody>';

		foreach ( $fields as $key => $label ) {
			$value = $order->get_meta( $key );
			if ( ! $value ) continue;
			$has_data = true;

			echo '<tr><th style="width:40%">' . esc_html( $label ) . '</th>';
			echo '<td><code>' . esc_html( $value ) . '</code></td></tr>';
		}

		$invoice = $order->get_meta( '_fungies_invoice_url' );
		if ( $invoice ) {
			$has_data = true;
			echo '<tr><th>' . esc_html__( 'Invoice', 'fungies-wp' ) . '</th>';
			echo '<td><a href="' . esc_url( $invoice ) . '" target="_blank">' . esc_html__( 'View Invoice', 'fungies-wp' ) . '</a></td></tr>';
		}

		if ( ! $has_data ) {
			echo '<tr><td colspan="2">' . esc_html__( 'No Fungies data for this order.', 'fungies-wp' ) . '</td></tr>';
		}

		echo '</tbody></table>';
	}
}
