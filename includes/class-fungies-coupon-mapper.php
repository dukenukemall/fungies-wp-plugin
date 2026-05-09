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

		$payload = array(
			'type'              => 'code',
			'name'              => $code,
			'discountCode'      => $code,
			'amount'            => $amount,
			'amountType'        => $amount_type,
			'validFrom'         => 0,
			'currency'          => strtoupper( $currency ),
			'status'            => $status,
			'includesAllOffers' => true,
			'includedOffers'    => array(),
			'excludedOffers'    => array(),
			'timezone'          => $timezone,
		);

		$expires = $coupon->get_date_expires();
		if ( $expires ) {
			$payload['validUntil'] = (int) $expires->getTimestamp();
		}

		$usage_limit = $coupon->get_usage_limit();
		if ( $usage_limit && (int) $usage_limit > 0 ) {
			$payload['purchaseLimit'] = (int) $usage_limit;
		}

		return $payload;
	}

	public static function is_supported( WC_Coupon $coupon ) {
		$type = (string) $coupon->get_discount_type();
		return in_array( $type, array( 'percent', 'fixed_cart', 'fixed_product' ), true );
	}

	public static function diff_for_update( array $payload, array $remote ) {
		$keys = array(
			'name', 'discountCode', 'amount', 'amountType',
			'validUntil', 'purchaseLimit', 'status', 'currency',
		);
		$changed = false;
		foreach ( $keys as $k ) {
			$local  = $payload[ $k ] ?? null;
			$remoteVal = $remote[ $k ] ?? null;
			if ( 'amount' === $k ) {
				if ( (float) $local !== (float) $remoteVal ) { $changed = true; break; }
			} elseif ( $local != $remoteVal ) {
				$changed = true; break;
			}
		}
		return $changed;
	}
}
