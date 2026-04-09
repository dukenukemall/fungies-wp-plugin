<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Currency {

	public static function init() {
		add_filter( 'woocommerce_get_price_html', array( __CLASS__, 'format_product_price' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_price', array( __CLASS__, 'format_cart_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( __CLASS__, 'format_cart_subtotal' ), 10, 3 );
	}

	public static function get_fungies_currency( $product_id ) {
		return get_post_meta( $product_id, '_fungies_currency', true );
	}

	public static function is_fungies_product( $product_id ) {
		return (bool) get_post_meta( $product_id, '_fungies_offer_id', true );
	}

	public static function format_price( $amount, $currency_code ) {
		if ( ! $currency_code ) {
			return wc_price( $amount );
		}

		$symbols = self::get_currency_symbols();
		$symbol  = $symbols[ strtoupper( $currency_code ) ] ?? strtoupper( $currency_code ) . ' ';

		return $symbol . number_format( (float) $amount, 2, '.', ',' );
	}

	public static function format_product_price( $price_html, $product ) {
		$product_id = $product->get_id();

		if ( ! self::is_fungies_product( $product_id ) ) {
			return $price_html;
		}

		$fg_currency  = self::get_fungies_currency( $product_id );
		$wc_currency  = get_woocommerce_currency();

		if ( ! $fg_currency || strtoupper( $fg_currency ) === strtoupper( $wc_currency ) ) {
			return $price_html;
		}

		$price   = (float) $product->get_price();
		$regular = (float) $product->get_regular_price();
		$sale    = $product->get_sale_price();

		if ( $sale !== '' && (float) $sale < $regular ) {
			return '<del>' . self::format_price( $regular, $fg_currency ) . '</del> '
				. '<ins>' . self::format_price( $sale, $fg_currency ) . '</ins>';
		}

		return self::format_price( $price, $fg_currency );
	}

	public static function format_cart_price( $price_html, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];

		if ( ! self::is_fungies_product( $product_id ) ) {
			return $price_html;
		}

		$fg_currency = self::get_fungies_currency( $product_id );
		$wc_currency = get_woocommerce_currency();

		if ( ! $fg_currency || strtoupper( $fg_currency ) === strtoupper( $wc_currency ) ) {
			return $price_html;
		}

		$product = $cart_item['data'];
		return self::format_price( $product->get_price(), $fg_currency );
	}

	public static function format_cart_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		$product_id = $cart_item['product_id'];

		if ( ! self::is_fungies_product( $product_id ) ) {
			return $subtotal_html;
		}

		$fg_currency = self::get_fungies_currency( $product_id );
		$wc_currency = get_woocommerce_currency();

		if ( ! $fg_currency || strtoupper( $fg_currency ) === strtoupper( $wc_currency ) ) {
			return $subtotal_html;
		}

		$product  = $cart_item['data'];
		$subtotal = (float) $product->get_price() * $cart_item['quantity'];
		return self::format_price( $subtotal, $fg_currency );
	}

	public static function get_currency_symbols() {
		return array(
			'USD' => '$', 'EUR' => "\u{20AC}", 'GBP' => "\u{00A3}",
			'PLN' => "z\u{0142}", 'JPY' => "\u{00A5}", 'CAD' => 'CA$',
			'AUD' => 'A$', 'CHF' => 'CHF ', 'SEK' => 'kr ',
			'NOK' => 'kr ', 'DKK' => 'kr ', 'BRL' => 'R$',
			'MXN' => 'MX$', 'INR' => "\u{20B9}", 'KRW' => "\u{20A9}",
			'TRY' => "\u{20BA}", 'RUB' => "\u{20BD}", 'ZAR' => 'R ',
			'SGD' => 'S$', 'HKD' => 'HK$', 'NZD' => 'NZ$',
			'CZK' => "K\u{010D} ", 'HUF' => 'Ft ', 'RON' => 'lei ',
			'THB' => "\u{0E3F}", 'AED' => 'AED ', 'SAR' => 'SAR ',
		);
	}
}
