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

		if ( ! self::is_main_cart_route( $route ) ) {
			return $response;
		}

		try {
			return self::apply_currency_overrides( $response );
		} catch ( \Exception $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->error(
					'Fungies blocks currency filter error: ' . $e->getMessage(),
					array( 'source' => 'fungies' )
				);
			}
			return $response;
		}
	}

	private static function is_main_cart_route( $route ) {
		$safe_routes = array(
			'/wc/store/v1/cart',
			'/wc/store/cart',
		);

		foreach ( $safe_routes as $safe ) {
			if ( $route === $safe || $route === $safe . '/' ) {
				return true;
			}
		}

		return false;
	}

	private static function apply_currency_overrides( $response ) {
		$data = $response->get_data();

		if ( ! is_array( $data ) ) {
			return $response;
		}

		if ( ! isset( $data['items'] ) || ! is_array( $data['items'] ) || empty( $data['items'] ) ) {
			return $response;
		}

		$fg_currency_used = null;
		$all_fungies      = true;

		foreach ( $data['items'] as $idx => $item ) {
			$product_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

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

			if ( self::is_valid_prices_array( $item ) ) {
				$data['items'][ $idx ]['prices'] = array_merge( $item['prices'], $cur_data );
			}
			if ( self::is_valid_totals_array( $item ) ) {
				$data['items'][ $idx ]['totals'] = array_merge( $item['totals'], $cur_data );
			}
		}

		if ( $all_fungies && $fg_currency_used && self::is_valid_cart_totals( $data ) ) {
			$data['totals'] = array_merge( $data['totals'], self::get_currency_format( $fg_currency_used ) );
		}

		$response->set_data( $data );
		return $response;
	}

	private static function is_valid_prices_array( $item ) {
		return isset( $item['prices'] )
			&& is_array( $item['prices'] )
			&& isset( $item['prices']['price'] )
			&& isset( $item['prices']['raw_prices'] );
	}

	private static function is_valid_totals_array( $item ) {
		return isset( $item['totals'] )
			&& is_array( $item['totals'] )
			&& isset( $item['totals']['line_total'] );
	}

	private static function is_valid_cart_totals( $data ) {
		return isset( $data['totals'] )
			&& is_array( $data['totals'] )
			&& isset( $data['totals']['total_price'] );
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
