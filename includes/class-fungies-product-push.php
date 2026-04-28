<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Product_Push {

	public static function push( $wc_id, $client = null, $workspace_currency = null ) {
		$wc_id = (int) $wc_id;
		$post  = get_post( $wc_id );
		if ( ! $post || 'product' !== $post->post_type ) return self::r( 'skipped', $wc_id, '', 'Not a product.' );
		if ( get_post_meta( $wc_id, '_fungies_offer_id', true ) ) return self::r( 'skipped', $wc_id, $post->post_title, 'Imported from Fungies.' );
		if ( 'publish' !== $post->post_status ) return self::r( 'skipped', $wc_id, $post->post_title, 'Not published.' );

		if ( ! $client ) $client = new Fungies_API_Client();
		if ( ! $workspace_currency ) $workspace_currency = Fungies_Product_Sync::get_workspace_currency( $client );

		$wc_currency = strtoupper( get_woocommerce_currency() );
		if ( $wc_currency !== strtoupper( $workspace_currency ) ) {
			return self::r( 'error', $wc_id, $post->post_title, sprintf( 'Currency mismatch: WC=%s, Fungies workspace=%s.', $wc_currency, $workspace_currency ) );
		}

		$wc = wc_get_product( $wc_id );
		if ( ! $wc ) return self::r( 'skipped', $wc_id, $post->post_title, 'Product not loadable.' );

		$pid   = get_post_meta( $wc_id, '_fungies_pushed_product_id', true );
		$oid   = get_post_meta( $wc_id, '_fungies_pushed_offer_id', true );
		$pbody = Fungies_Product_Body::product( $wc );
		$obody = Fungies_Product_Body::offer( $wc, $workspace_currency );

		if ( $pid && $oid ) {
			$upd = self::try_update( $client, $wc_id, $wc, $pid, $oid, $pbody, $obody );
			if ( $upd !== null ) return $upd;
		}

		return self::create( $client, $wc_id, $wc, $pbody, $obody );
	}

	private static function try_update( $client, $wc_id, $wc, $pid, $oid, $pbody, $obody ) {
		$r1 = $client->update_product( $pid, array_merge( array( 'id' => $pid ), $pbody ) );
		if ( is_wp_error( $r1 ) ) {
			if ( self::is_not_found( $r1 ) ) {
				delete_post_meta( $wc_id, '_fungies_pushed_product_id' );
				delete_post_meta( $wc_id, '_fungies_pushed_offer_id' );
				self::log( sprintf( 'Stale Fungies product/offer IDs cleared for WC #%d (%s) — recreating in current workspace.', $wc_id, $wc->get_name() ) );
				return null;
			}
			return self::r( 'error', $wc_id, $wc->get_name(), 'Product update: ' . $r1->get_error_message() );
		}
		$r2 = $client->update_offer( $oid, array_merge( array( 'id' => $oid ), $obody ) );
		if ( is_wp_error( $r2 ) ) {
			if ( self::is_not_found( $r2 ) ) return self::recreate_offer( $client, $wc_id, $wc, $pid, $obody );
			return self::r( 'error', $wc_id, $wc->get_name(), 'Offer update: ' . $r2->get_error_message() );
		}
		update_post_meta( $wc_id, '_fungies_pushed_at', current_time( 'mysql' ) );
		return self::r( 'updated', $wc_id, $wc->get_name(), 'Updated in Fungies.' );
	}

	private static function recreate_offer( $client, $wc_id, $wc, $pid, $obody ) {
		delete_post_meta( $wc_id, '_fungies_pushed_offer_id' );
		$obody['productId'] = $pid;
		$r = $client->create_offer( $obody );
		if ( is_wp_error( $r ) ) return self::r( 'error', $wc_id, $wc->get_name(), 'Offer recreate: ' . $r->get_error_message() );
		$new_oid = $r['data']['offer']['id'] ?? '';
		if ( $new_oid ) update_post_meta( $wc_id, '_fungies_pushed_offer_id', $new_oid );
		update_post_meta( $wc_id, '_fungies_pushed_at', current_time( 'mysql' ) );
		return self::r( 'updated', $wc_id, $wc->get_name(), 'Recreated stale offer in Fungies.' );
	}

	private static function create( $client, $wc_id, $wc, $pbody, $obody ) {
		$r1 = $client->create_product( $pbody );
		if ( is_wp_error( $r1 ) ) return self::r( 'error', $wc_id, $wc->get_name(), 'Product create: ' . $r1->get_error_message() );
		$new_pid = $r1['data']['product']['id'] ?? '';
		if ( ! $new_pid ) return self::r( 'error', $wc_id, $wc->get_name(), 'Product create: missing product id.' );

		$obody['productId'] = $new_pid;
		$r2 = $client->create_offer( $obody );
		if ( is_wp_error( $r2 ) ) return self::r( 'error', $wc_id, $wc->get_name(), 'Offer create: ' . $r2->get_error_message() );
		$new_oid = $r2['data']['offer']['id'] ?? '';

		update_post_meta( $wc_id, '_fungies_pushed_product_id', $new_pid );
		if ( $new_oid ) update_post_meta( $wc_id, '_fungies_pushed_offer_id', $new_oid );
		update_post_meta( $wc_id, '_fungies_pushed_at', current_time( 'mysql' ) );
		return self::r( 'created', $wc_id, $wc->get_name(), 'Created in Fungies.' );
	}

	private static function is_not_found( $err ) {
		$data = $err->get_error_data();
		if ( is_array( $data ) && isset( $data['status'] ) && (int) $data['status'] === 404 ) return true;
		return ( stripos( (string) $err->get_error_message(), 'not found' ) !== false );
	}

	private static function log( $msg ) {
		if ( function_exists( 'wc_get_logger' ) ) wc_get_logger()->info( '[Push] ' . $msg, array( 'source' => 'fungies' ) );
	}

	private static function r( $status, $wc_id, $name, $message ) {
		return array( 'status' => $status, 'wc_id' => $wc_id, 'name' => $name, 'message' => $message );
	}
}
