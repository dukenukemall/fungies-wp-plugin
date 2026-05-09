<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Coupon_Sync {

	public static function sync( Fungies_API_Client $client ) {
		if ( ! function_exists( 'wc_get_coupon_id_by_code' ) ) {
			return self::result( 0, 0, 0, array(), 'WooCommerce coupons unavailable.' );
		}

		$coupon_ids = get_posts( array(
			'post_type'      => 'shop_coupon',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );

		if ( empty( $coupon_ids ) ) {
			return self::result( 0, 0, 0, array() );
		}

		$currency  = strtoupper( get_woocommerce_currency() );
		$timezone  = self::resolve_timezone();
		$remote    = self::index_remote_discounts( $client );

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $coupon_ids as $coupon_id ) {
			$coupon = new WC_Coupon( (int) $coupon_id );
			if ( ! Fungies_Coupon_Mapper::is_supported( $coupon ) ) {
				$skipped++;
				self::log( sprintf( 'Skipped coupon %s — type %s not supported.', $coupon->get_code(), $coupon->get_discount_type() ) );
				continue;
			}

			$res = self::sync_one( $client, $coupon, $currency, $timezone, $remote );
			if ( 'created' === $res['status'] ) $created++;
			elseif ( 'updated' === $res['status'] ) $updated++;
			elseif ( 'unchanged' === $res['status'] ) $skipped++;
			elseif ( 'error' === $res['status'] ) {
				$errors[] = array( 'name' => $coupon->get_code(), 'message' => $res['message'] );
				self::log( sprintf( 'Coupon sync error [%s]: %s', $coupon->get_code(), $res['message'] ), 'warning' );
			}
		}

		return self::result( $created, $updated, $skipped, $errors );
	}

	private static function sync_one( $client, WC_Coupon $coupon, $currency, $timezone, array $remote ) {
		$payload     = Fungies_Coupon_Mapper::build_payload( $coupon, $currency, $timezone );
		$code        = $coupon->get_code();
		$coupon_id   = $coupon->get_id();
		$discount_id = Fungies_Workspace_Meta::get_discount_id( $coupon_id );
		$remote_obj  = null;

		if ( $discount_id && isset( $remote['by_id'][ $discount_id ] ) ) {
			$remote_obj = $remote['by_id'][ $discount_id ];
		} elseif ( isset( $remote['by_code'][ strtolower( $code ) ] ) ) {
			$remote_obj  = $remote['by_code'][ strtolower( $code ) ];
			$discount_id = $remote_obj['id'];
			Fungies_Workspace_Meta::set_discount_id( $coupon_id, $discount_id );
		}

		if ( $discount_id ) {
			if ( $remote_obj && ! Fungies_Coupon_Mapper::diff_for_update( $payload, $remote_obj ) ) {
				return array( 'status' => 'unchanged' );
			}
			$resp = $client->update_discount( $discount_id, $payload );
			if ( ! is_wp_error( $resp ) ) {
				return array( 'status' => 'updated' );
			}
			if ( ! self::is_not_found( $resp ) ) {
				return array( 'status' => 'error', 'message' => $resp->get_error_message() );
			}
			Fungies_Workspace_Meta::set_discount_id( $coupon_id, '' );
			delete_post_meta( $coupon_id, Fungies_Workspace_Meta::discount_meta_key() );
		}

		$resp = $client->create_discount( $payload );
		if ( is_wp_error( $resp ) ) {
			return array( 'status' => 'error', 'message' => $resp->get_error_message() );
		}
		$new_id = $resp['data']['discount']['id'] ?? '';
		if ( $new_id ) {
			Fungies_Workspace_Meta::set_discount_id( $coupon_id, $new_id );
		}
		return array( 'status' => 'created' );
	}

	private static function is_not_found( WP_Error $err ) {
		$data = $err->get_error_data();
		$status = is_array( $data ) ? ( $data['status'] ?? 0 ) : 0;
		if ( 404 === (int) $status ) return true;
		$msg = strtolower( (string) $err->get_error_message() );
		return false !== strpos( $msg, 'not found' );
	}

	private static function index_remote_discounts( $client ) {
		$resp = $client->get_discounts( array( 'type' => 'code', 'take' => 100 ) );
		if ( ! is_wp_error( $resp ) ) {
			return self::collect_remote( $resp['data']['discounts'] ?? array() );
		}
		self::log( 'Bulk discounts list failed (' . $resp->get_error_message() . '), falling back to row-by-row walk.', 'warning' );
		return self::walk_remote_discounts( $client );
	}

	private static function walk_remote_discounts( $client ) {
		$by_code  = array();
		$by_id    = array();
		$consec   = 0;
		$max_skip = 200;

		for ( $skip = 0; $skip < $max_skip; $skip++ ) {
			$resp = $client->get_discounts( array( 'type' => 'code', 'take' => 1, 'skip' => $skip ) );
			if ( is_wp_error( $resp ) ) {
				if ( ++$consec >= 25 ) break;
				continue;
			}
			$consec = 0;
			$list   = $resp['data']['discounts'] ?? array();
			if ( empty( $list ) ) break;
			$res    = self::collect_remote( $list );
			$by_code = $by_code + $res['by_code'];
			$by_id   = $by_id + $res['by_id'];
		}

		return array( 'by_code' => $by_code, 'by_id' => $by_id );
	}

	private static function collect_remote( array $list ) {
		$by_code = array();
		$by_id   = array();
		foreach ( $list as $d ) {
			if ( ! empty( $d['discountCode'] ) ) $by_code[ strtolower( $d['discountCode'] ) ] = $d;
			if ( ! empty( $d['id'] ) ) $by_id[ $d['id'] ] = $d;
		}
		return array( 'by_code' => $by_code, 'by_id' => $by_id );
	}

	private static function resolve_timezone() {
		if ( function_exists( 'wp_timezone_string' ) ) {
			$tz = wp_timezone_string();
			if ( $tz ) return $tz;
		}
		$tz = get_option( 'timezone_string' );
		return $tz ? $tz : 'UTC';
	}

	private static function result( $created, $updated, $skipped, $errors, $message = '' ) {
		return array(
			'created' => (int) $created,
			'updated' => (int) $updated,
			'skipped' => (int) $skipped,
			'errors'  => $errors,
			'message' => $message,
		);
	}

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Coupon Sync] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
