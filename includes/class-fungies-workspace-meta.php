<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Workspace-scoped meta helper.
 *
 * Stores `_fungies_pushed_*` post meta keyed by a hash of the active Fungies
 * secret key, so toggling Sandbox Mode (or swapping API keys) does not orphan
 * the previous mapping and create duplicates in the destination workspace.
 *
 * Legacy unscoped meta (from <= 2.1.7) is read as a fallback and migrated to
 * the active workspace on the next successful push.
 */
class Fungies_Workspace_Meta {

	const PREFIX_PRODUCT   = '_fungies_pushed_product_id';
	const PREFIX_OFFER     = '_fungies_pushed_offer_id';
	const PREFIX_PUSHED_AT = '_fungies_pushed_at';
	const PREFIX_DISCOUNT  = '_fungies_pushed_discount_id';

	public static function workspace_hash() {
		$secret = (string) Fungies_Admin_Settings::get_active_secret_key();
		if ( '' === $secret ) {
			return Fungies_Admin_Settings::is_sandbox() ? 'stage' : 'prod';
		}
		return substr( hash( 'sha256', $secret ), 0, 12 );
	}

	public static function product_meta_key()   { return self::PREFIX_PRODUCT   . '__' . self::workspace_hash(); }
	public static function offer_meta_key()     { return self::PREFIX_OFFER     . '__' . self::workspace_hash(); }
	public static function pushed_at_meta_key() { return self::PREFIX_PUSHED_AT . '__' . self::workspace_hash(); }
	public static function discount_meta_key()  { return self::PREFIX_DISCOUNT  . '__' . self::workspace_hash(); }

	public static function get_discount_id( $coupon_id ) {
		$val = get_post_meta( $coupon_id, self::discount_meta_key(), true );
		if ( '' !== $val ) return (string) $val;
		return (string) get_post_meta( $coupon_id, self::PREFIX_DISCOUNT, true );
	}

	public static function set_discount_id( $coupon_id, $discount_id ) {
		if ( $discount_id ) {
			update_post_meta( $coupon_id, self::discount_meta_key(), $discount_id );
			delete_post_meta( $coupon_id, self::PREFIX_DISCOUNT );
		}
	}

	public static function get_product_id( $wc_id ) {
		$val = get_post_meta( $wc_id, self::product_meta_key(), true );
		if ( '' !== $val ) return $val;
		return (string) get_post_meta( $wc_id, self::PREFIX_PRODUCT, true );
	}

	public static function get_offer_id( $wc_id ) {
		$val = get_post_meta( $wc_id, self::offer_meta_key(), true );
		if ( '' !== $val ) return $val;
		return (string) get_post_meta( $wc_id, self::PREFIX_OFFER, true );
	}

	public static function set_ids( $wc_id, $product_id, $offer_id ) {
		if ( $product_id ) update_post_meta( $wc_id, self::product_meta_key(), $product_id );
		if ( $offer_id )   update_post_meta( $wc_id, self::offer_meta_key(),   $offer_id );
		update_post_meta( $wc_id, self::pushed_at_meta_key(), current_time( 'mysql' ) );

		// Migration: drop legacy unscoped meta once the active workspace has scoped meta.
		// Legacy meta could only ever describe one workspace at a time, so this is safe.
		delete_post_meta( $wc_id, self::PREFIX_PRODUCT );
		delete_post_meta( $wc_id, self::PREFIX_OFFER );
		delete_post_meta( $wc_id, self::PREFIX_PUSHED_AT );
	}

	public static function set_offer_id( $wc_id, $offer_id ) {
		if ( $offer_id ) update_post_meta( $wc_id, self::offer_meta_key(), $offer_id );
		update_post_meta( $wc_id, self::pushed_at_meta_key(), current_time( 'mysql' ) );
	}

	public static function delete_ids( $wc_id ) {
		delete_post_meta( $wc_id, self::product_meta_key() );
		delete_post_meta( $wc_id, self::offer_meta_key() );
		delete_post_meta( $wc_id, self::pushed_at_meta_key() );
		delete_post_meta( $wc_id, self::PREFIX_PRODUCT );
		delete_post_meta( $wc_id, self::PREFIX_OFFER );
		delete_post_meta( $wc_id, self::PREFIX_PUSHED_AT );
	}

	public static function delete_offer_id( $wc_id ) {
		delete_post_meta( $wc_id, self::offer_meta_key() );
		delete_post_meta( $wc_id, self::PREFIX_OFFER );
	}

	public static function get_all_pushed_offer_ids() {
		global $wpdb;
		// Direct postmeta scan with a LIKE on workspace-suffixed meta keys (e.g.
		// `_fungies_pushed_offer_id__<hash>`). Not expressible via WP_Query, and the
		// result is consumed transiently inside a single Sync Now pass, so we don't
		// cache it.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->postmeta}
				 WHERE ( meta_key = %s OR meta_key LIKE %s )
				   AND meta_value <> ''",
				self::PREFIX_OFFER,
				$wpdb->esc_like( self::PREFIX_OFFER . '__' ) . '%'
			)
		);
		return array_flip( (array) $rows );
	}
}
