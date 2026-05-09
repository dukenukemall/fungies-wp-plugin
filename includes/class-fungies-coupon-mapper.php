<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Coupon_Mapper {

	public static function build_payload( WC_Coupon $coupon, $currency, $timezone ) {
		$code           = (string) $coupon->get_code();
		$discount_type  = (string) $coupon->get_discount_type();
		$amount_type    = ( 'percent' === $discount_type ) ? 'percentage' : 'fixed';
		$raw_amount     = (float) $coupon->get_amount();
		$amount         = max( 1, $raw_amount );
		$post_status    = get_post_status( $coupon->get_id() );
		$status         = ( 'publish' === $post_status ) ? 'active' : 'inactive';

		$created   = $coupon->get_date_created();
		$valid_from = $created ? (int) $created->getTimestamp() : time();

		$expires    = $coupon->get_date_expires();
		$valid_until = $expires ? (int) $expires->getTimestamp() : null;

		$usage_limit    = $coupon->get_usage_limit();
		$purchase_limit = ( $usage_limit && (int) $usage_limit > 0 ) ? (int) $usage_limit : null;

		$payload = array(
			'type'              => 'code',
			'name'              => $code,
			'discountCode'      => $code,
			'amount'            => $amount,
			'amountType'        => $amount_type,
			'validFrom'         => $valid_from,
			'validUntil'        => $valid_until,
			'currency'          => strtoupper( $currency ),
			'status'            => $status,
			'includesAllOffers' => true,
			'includedOffers'    => array(),
			'excludedOffers'    => array(),
			'timezone'          => $timezone,
		);

		if ( null !== $purchase_limit ) {
			$payload['purchaseLimit'] = $purchase_limit;
		}

		return $payload;
	}

	public static function is_supported( WC_Coupon $coupon ) {
		$type = (string) $coupon->get_discount_type();
		return in_array( $type, array( 'percent', 'fixed_cart', 'fixed_product' ), true );
	}

	public static function diff_for_update( array $payload, array $remote ) {
		$simple_keys = array( 'name', 'discountCode', 'amountType', 'status', 'currency' );
		foreach ( $simple_keys as $k ) {
			if ( (string) ( $payload[ $k ] ?? '' ) !== (string) ( $remote[ $k ] ?? '' ) ) return true;
		}

		$local_amount  = (float) ( $payload['amount'] ?? 0 );
		$remote_amount = (float) ( $remote['amount'] ?? 0 );
		if ( 'fixed' === ( $payload['amountType'] ?? '' ) ) {
			$decimals     = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 2;
			$local_amount = round( $local_amount * pow( 10, $decimals ) );
		}
		if ( $local_amount !== $remote_amount ) return true;

		$local_until  = isset( $payload['validUntil'] ) && $payload['validUntil'] ? (int) $payload['validUntil'] : null;
		$remote_until = isset( $remote['validUntil'] ) && $remote['validUntil'] ? (int) round( $remote['validUntil'] / 1000 ) : null;
		if ( $local_until !== $remote_until ) return true;

		$local_limit  = isset( $payload['purchaseLimit'] ) ? (int) $payload['purchaseLimit'] : null;
		$remote_limit = isset( $remote['purchaseLimit'] ) && $remote['purchaseLimit'] ? (int) $remote['purchaseLimit'] : null;
		if ( $local_limit !== $remote_limit ) return true;

		return false;
	}
}
