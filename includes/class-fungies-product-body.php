<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Product_Body {

	public static function product( $wc ) {
		$body = array(
			'name'   => $wc->get_name() ?: 'Product ' . $wc->get_id(),
			'type'   => 'OneTimePayment',
			'status' => 'ACTIVE',
		);
		$desc = $wc->get_description() ?: $wc->get_short_description();
		if ( $desc ) $body['description'] = wp_strip_all_tags( $desc );
		$cover = self::cover_url( $wc );
		if ( $cover ) $body['cover'] = $cover;
		return $body;
	}

	public static function offer( $wc, $currency ) {
		$price = (float) ( $wc->get_price() !== '' ? $wc->get_price() : $wc->get_regular_price() );
		return array(
			'name'     => $wc->get_name() ?: 'Offer ' . $wc->get_id(),
			'currency' => strtoupper( $currency ),
			'price'    => round( $price, 2 ),
			'limit'    => null,
		);
	}

	private static function cover_url( $wc ) {
		$ids = array_filter( array_merge( array( $wc->get_image_id() ), (array) $wc->get_gallery_image_ids() ) );
		foreach ( $ids as $id ) {
			$url = wp_get_attachment_image_url( $id, 'full' );
			if ( $url ) return $url;
		}
		return '';
	}
}
