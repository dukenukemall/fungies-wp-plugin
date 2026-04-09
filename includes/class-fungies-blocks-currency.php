<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Blocks_Currency {

	public static function init() {
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'fix_store_api_currency' ), 10, 3 );
	}

	public static function fix_store_api_currency( $response, $handler, $request ) {
		if ( ! $response instanceof WP_REST_Response ) {
			return $response;
		}

		$route = $request->get_route();

		if ( strpos( $route, 'wc/store' ) === false ) {
			return $response;
		}

		if ( strpos( $route, '/cart' ) === false && strpos( $route, '/checkout' ) === false ) {
			return $response;
		}

		$data = $response->get_data();

		if ( ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return $response;
		}

		$fg_currency_used = null;
		$all_fungies      = true;

		foreach ( $data['items'] as &$item ) {
			$product_id = $item['id'] ?? 0;

			if ( ! $product_id || ! Fungies_Currency::is_fungies_product( $product_id ) ) {
				$all_fungies = false;
				continue;
			}

			$fg_currency = Fungies_Currency::get_fungies_currency( $product_id );
			$wc_currency = get_woocommerce_currency();

			if ( ! $fg_currency || strtoupper( $fg_currency ) === strtoupper( $wc_currency ) ) {
				continue;
			}

			$fg_currency_used = strtoupper( $fg_currency );
			$cur_data         = self::get_currency_format( $fg_currency_used );

			if ( isset( $item['prices'] ) ) {
				$item['prices'] = array_merge( $item['prices'], $cur_data );
			}
			if ( isset( $item['totals'] ) ) {
				$item['totals'] = array_merge( $item['totals'], $cur_data );
			}
		}
		unset( $item );

		if ( $all_fungies && $fg_currency_used && isset( $data['totals'] ) ) {
			$data['totals'] = array_merge( $data['totals'], self::get_currency_format( $fg_currency_used ) );
		}

		$response->set_data( $data );
		return $response;
	}

	private static function get_currency_format( $code ) {
		$symbols = Fungies_Currency::get_currency_symbols();
		$symbol  = $symbols[ $code ] ?? $code;

		$prefix_currencies = array(
			'USD', 'GBP', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD',
			'MXN', 'BRL', 'ZAR', 'INR', 'KRW', 'THB', 'JPY',
		);

		$minor = in_array( $code, array( 'JPY', 'KRW' ), true ) ? 0 : 2;

		if ( in_array( $code, $prefix_currencies, true ) ) {
			return array(
				'currency_code'               => $code,
				'currency_symbol'             => $symbol,
				'currency_minor_unit'         => $minor,
				'currency_decimal_separator'  => '.',
				'currency_thousand_separator' => ',',
				'currency_prefix'             => $symbol,
				'currency_suffix'             => '',
			);
		}

		return array(
			'currency_code'               => $code,
			'currency_symbol'             => $symbol,
			'currency_minor_unit'         => $minor,
			'currency_decimal_separator'  => ',',
			'currency_thousand_separator' => '.',
			'currency_prefix'             => '',
			'currency_suffix'             => ' ' . $symbol,
		);
	}
}
