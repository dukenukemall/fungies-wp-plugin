<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Product_Metabox {

	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		add_meta_box(
			'fungies-product-details',
			__( 'Fungies Product Link', 'fungies-wp' ),
			array( __CLASS__, 'render' ),
			'product',
			'side',
			'default'
		);
	}

	public static function render( $post ) {
		$product_id   = get_post_meta( $post->ID, '_fungies_product_id', true );
		$offer_id     = get_post_meta( $post->ID, '_fungies_offer_id', true );
		$checkout_url = get_post_meta( $post->ID, '_fungies_checkout_url', true );
		$currency     = get_post_meta( $post->ID, '_fungies_currency', true );
		$product_type = get_post_meta( $post->ID, '_fungies_product_type', true );

		if ( ! $offer_id && ! $product_id ) {
			echo '<p>' . esc_html__( 'This product is not linked to Fungies.', 'fungies-wp' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped"><tbody>';

		if ( $offer_id ) {
			echo '<tr><th>' . esc_html__( 'Offer ID', 'fungies-wp' ) . '</th>';
			echo '<td><code style="font-size:11px;word-break:break-all">' . esc_html( $offer_id ) . '</code></td></tr>';
		}

		if ( $product_id ) {
			echo '<tr><th>' . esc_html__( 'Product ID', 'fungies-wp' ) . '</th>';
			echo '<td><code style="font-size:11px;word-break:break-all">' . esc_html( $product_id ) . '</code></td></tr>';
		}

		if ( $product_type ) {
			echo '<tr><th>' . esc_html__( 'Type', 'fungies-wp' ) . '</th>';
			echo '<td>' . esc_html( $product_type ) . '</td></tr>';
		}

		if ( $currency ) {
			echo '<tr><th>' . esc_html__( 'Currency', 'fungies-wp' ) . '</th>';
			echo '<td>' . esc_html( strtoupper( $currency ) ) . '</td></tr>';
		}

		if ( $checkout_url ) {
			echo '<tr><th>' . esc_html__( 'Checkout', 'fungies-wp' ) . '</th>';
			echo '<td><a href="' . esc_url( $checkout_url ) . '" target="_blank">' . esc_html__( 'Open', 'fungies-wp' ) . '</a></td></tr>';
		}

		echo '</tbody></table>';
	}
}
