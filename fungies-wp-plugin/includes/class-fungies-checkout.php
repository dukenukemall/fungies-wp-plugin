<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Checkout {

	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'woocommerce_after_checkout_form', array( __CLASS__, 'render_embedded_container' ) );
	}

	public static function enqueue_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		$mode = Fungies_Admin_Settings::get_option( 'checkout_mode', 'overlay' );

		wp_enqueue_script(
			'fungies-sdk',
			'https://cdn.jsdelivr.net/npm/@fungies/fungies-js@latest',
			array(),
			null,
			true
		);

		wp_enqueue_script(
			'fungies-checkout',
			FUNGIES_WP_PLUGIN_URL . 'assets/js/fungies-checkout.js',
			array( 'jquery', 'fungies-sdk' ),
			FUNGIES_WP_VERSION,
			true
		);

		wp_localize_script( 'fungies-checkout', 'fungiesCheckout', array(
			'mode'      => $mode,
			'cartItems' => self::get_cart_items(),
			'returnUrl' => wc_get_checkout_url(),
			'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
		) );
	}

	private static function get_cart_items() {
		$items = array();

		if ( ! WC()->cart ) {
			return $items;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id  = $cart_item['product_id'];
			$offer_id    = get_post_meta( $product_id, '_fungies_offer_id', true );
			$checkout_url = get_post_meta( $product_id, '_fungies_checkout_url', true );

			if ( $offer_id ) {
				$items[] = array(
					'offerId'     => $offer_id,
					'quantity'    => $cart_item['quantity'],
					'checkoutUrl' => $checkout_url,
				);
			}
		}

		return $items;
	}

	public static function render_embedded_container() {
		$mode = Fungies_Admin_Settings::get_option( 'checkout_mode', 'overlay' );

		if ( 'embedded' !== $mode ) {
			return;
		}

		echo '<div id="fungies-checkout-embed" style="min-height:500px;margin-top:20px;"></div>';
	}
}
