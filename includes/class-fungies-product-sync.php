<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Product_Sync {

	private static $is_pulling = false;

	public static function init() {
		add_action( 'wp_ajax_fungies_sync_products', array( __CLASS__, 'ajax_sync' ) );
		add_action( 'fungies_product_sync_cron', array( __CLASS__, 'sync' ) );
		add_action( 'woocommerce_update_product', array( __CLASS__, 'on_wc_product_saved' ), 20, 1 );
		add_action( 'woocommerce_new_product', array( __CLASS__, 'on_wc_product_saved' ), 20, 1 );
	}

	public static function ajax_sync() {
		check_ajax_referer( 'fungies_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'fungies-for-woocommerce' ) );
		}

		delete_transient( 'fungies_workspace_currency' );

		$result = self::sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public static function sync() {
		$client = new Fungies_API_Client();

		$pull = self::pull_from_fungies( $client );
		if ( is_wp_error( $pull ) ) {
			return $pull;
		}

		$push    = self::push_to_fungies( $client );
		$coupons = Fungies_Coupon_Sync::sync( $client );

		$pull_synced    = ( $pull['created'] ?? 0 ) + ( $pull['updated'] ?? 0 );
		$push_synced    = ( $push['created'] ?? 0 ) + ( $push['updated'] ?? 0 );
		$err_count      = count( $push['errors'] ?? array() );
		$coupon_synced  = ( $coupons['created'] ?? 0 ) + ( $coupons['updated'] ?? 0 );
		$coupon_errs    = count( $coupons['errors'] ?? array() );

		$message = sprintf(
			/* translators: 1: total pulled, 2: pulls created, 3: pulls updated, 4: total pushed, 5: pushes created, 6: pushes updated, 7: push errors, 8: total coupons synced, 9: coupons created, 10: coupons updated, 11: coupon errors */
			__( 'Pull: %1$d (%2$d created, %3$d updated). Push: %4$d (%5$d created, %6$d updated, %7$d errors). Coupons: %8$d (%9$d created, %10$d updated, %11$d errors).', 'fungies-for-woocommerce' ),
			$pull_synced, $pull['created'], $pull['updated'],
			$push_synced, $push['created'], $push['updated'], $err_count,
			$coupon_synced, $coupons['created'], $coupons['updated'], $coupon_errs
		);

		update_option( 'fungies_last_sync', current_time( 'mysql' ) );
		update_option( 'fungies_product_count', $pull_synced );

		self::log( $message );

		return array(
			'pull'    => $pull,
			'push'    => $push,
			'coupons' => $coupons,
			'message' => $message,
		);
	}

	public static function pull_from_fungies( $client ) {
		self::$is_pulling = true;

		self::cleanup_pushed_duplicates();

		$offers_response = $client->get_offers( array( 'product.types' => 'OneTimePayment' ) );
		if ( is_wp_error( $offers_response ) ) {
			self::$is_pulling = false;
			self::log( 'Offers fetch failed: ' . $offers_response->get_error_message(), 'error' );
			return $offers_response;
		}

		$offers_list       = self::extract_list( $offers_response, 'offers' );
		$offer_product_map = self::build_offer_product_map( $client );
		$pushed_offer_ids  = self::get_pushed_offer_ids();
		$created = 0;
		$updated = 0;

		foreach ( $offers_list as $offer ) {
			$offer_id = $offer['id'] ?? '';
			if ( ! empty( $offer_product_map ) && ! isset( $offer_product_map[ $offer_id ] ) ) {
				continue;
			}
			if ( $offer_id && isset( $pushed_offer_ids[ $offer_id ] ) ) {
				continue;
			}
			$fg_product = isset( $offer_product_map[ $offer_id ] ) ? $offer_product_map[ $offer_id ] : null;
			$result     = self::sync_from_offer( $offer, $fg_product );
			if ( 'created' === $result ) $created++;
			elseif ( 'updated' === $result ) $updated++;
		}

		self::$is_pulling = false;

		return array( 'created' => $created, 'updated' => $updated );
	}

	private static function get_pushed_offer_ids() {
		return Fungies_Workspace_Meta::get_all_pushed_offer_ids();
	}

	private static function cleanup_pushed_duplicates() {
		global $wpdb;
		// Self-join on postmeta to find WC products that were pulled from Fungies
		// but whose offer ID we also pushed from another WC product (i.e. a
		// duplicate created by re-pull after push). Not expressible via WP_Query;
		// transient cleanup pass invoked once per Sync Now, so caching is N/A.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pulled.post_id
				 FROM {$wpdb->postmeta} pulled
				 INNER JOIN {$wpdb->postmeta} pushed
				   ON pushed.meta_value = pulled.meta_value AND pushed.post_id <> pulled.post_id
				 WHERE pulled.meta_key = %s
				   AND ( pushed.meta_key = %s OR pushed.meta_key LIKE %s )",
				'_fungies_offer_id',
				Fungies_Workspace_Meta::PREFIX_OFFER,
				$wpdb->esc_like( Fungies_Workspace_Meta::PREFIX_OFFER . '__' ) . '%'
			)
		);
		$count = 0;
		foreach ( (array) $rows as $pid ) {
			if ( wp_delete_post( (int) $pid, true ) ) $count++;
		}
		if ( $count > 0 ) {
			self::log( sprintf( 'Removed %d duplicate WC product(s) re-imported from offers we pushed.', $count ) );
		}
		return $count;
	}

	public static function push_to_fungies( $client ) {
		$workspace_currency = self::get_workspace_currency( $client );

		// We must filter on the absence of `_fungies_offer_id` post meta to
		// skip Fungies-originated products on push. `_fungies_offer_id` is a
		// single low-cardinality meta key only set on synced products, so this
		// query is fast in practice. Block-suppress because the sniff fires
		// on the `'meta_query'` array-key string token several lines below
		// any `ignore`-style comment we could place above `get_posts(`.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$wc_ids = get_posts( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array(
					'key'     => '_fungies_offer_id',
					'compare' => 'NOT EXISTS',
				),
			),
		) );
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query

		$created = 0;
		$updated = 0;
		$skipped = 0;
		$errors  = array();

		foreach ( $wc_ids as $wc_id ) {
			$res = Fungies_Product_Push::push( $wc_id, $client, $workspace_currency );
			if ( 'created' === $res['status'] ) $created++;
			elseif ( 'updated' === $res['status'] ) $updated++;
			elseif ( 'skipped' === $res['status'] ) $skipped++;
			elseif ( 'error' === $res['status'] ) {
				$errors[] = array( 'name' => $res['name'], 'message' => $res['message'] );
				self::log( sprintf( 'Push error [%s]: %s', $res['name'], $res['message'] ), 'warning' );
			}
		}

		return array(
			'created' => $created,
			'updated' => $updated,
			'skipped' => $skipped,
			'errors'  => $errors,
		);
	}

	public static function get_workspace_currency( $client ) {
		$cached = get_transient( 'fungies_workspace_currency' );
		if ( $cached ) {
			return $cached;
		}

		$resp = $client->get_offers( array( 'take' => 50 ) );
		if ( is_wp_error( $resp ) ) {
			return strtoupper( get_woocommerce_currency() );
		}
		$offers = self::extract_list( $resp, 'offers' );

		$tally = array();
		foreach ( $offers as $o ) {
			$cur = strtoupper( $o['currency'] ?? '' );
			if ( $cur ) $tally[ $cur ] = ( $tally[ $cur ] ?? 0 ) + 1;
		}

		if ( empty( $tally ) ) {
			$currency = strtoupper( get_woocommerce_currency() );
		} else {
			arsort( $tally );
			$currency = (string) array_key_first( $tally );
		}

		set_transient( 'fungies_workspace_currency', $currency, HOUR_IN_SECONDS );
		return $currency;
	}

	public static function on_wc_product_saved( $wc_id ) {
		if ( self::$is_pulling ) {
			return;
		}
		$wc_id = (int) $wc_id;
		if ( get_transient( "fungies_push_lock_$wc_id" ) ) {
			return;
		}
		set_transient( "fungies_push_lock_$wc_id", 1, 5 );
		Fungies_Product_Push::push( $wc_id );
	}

	private static function build_offer_product_map( $client ) {
		$map  = array();
		$resp = $client->get( '/products/list?types[]=OneTimePayment' );
		if ( is_wp_error( $resp ) ) {
			return $map;
		}
		$products = self::extract_list( $resp, 'products' );
		foreach ( $products as $product ) {
			$pid = $product['id'] ?? '';
			if ( ! $pid ) continue;
			$detail = $client->get_product( $pid );
			if ( is_wp_error( $detail ) ) continue;
			$full   = $detail['data']['product'] ?? $product;
			$offers = $detail['data']['offers'] ?? array();
			foreach ( $offers as $offer_ref ) {
				$oid = $offer_ref['id'] ?? '';
				if ( $oid ) $map[ $oid ] = $full;
			}
		}
		return $map;
	}

	private static function extract_list( $response, $key ) {
		if ( isset( $response['data'][ $key ] ) && is_array( $response['data'][ $key ] ) ) {
			return $response['data'][ $key ];
		}
		if ( isset( $response[ $key ] ) && is_array( $response[ $key ] ) ) {
			return $response[ $key ];
		}
		if ( is_array( $response ) && isset( $response[0]['id'] ) ) {
			return $response;
		}
		return array();
	}

	private static function sync_from_offer( $offer, $fg_product ) {
		$offer_id  = $offer['id'] ?? '';
		$existing  = self::find_wc_product_by_offer_id( $offer_id );
		$is_update = (bool) $existing;

		$product_name = ! empty( $fg_product['name'] ) ? $fg_product['name'] : '';
		$offer_name   = $offer['name'] ?? '';

		if ( ! empty( $offer_name ) ) {
			$name = $offer_name;
		} elseif ( ! empty( $product_name ) ) {
			$name = $product_name;
		} else {
			$name = 'Fungies Offer ' . substr( $offer_id, 0, 8 );
		}

		$offer_desc   = $offer['description'] ?? '';
		$product_desc = ! empty( $fg_product['description'] ) ? $fg_product['description'] : '';
		$desc         = ! empty( $offer_desc ) ? $offer_desc : $product_desc;

		$product_data = array(
			'post_title'   => $name,
			'post_content' => $desc,
			'post_status'  => 'publish',
			'post_type'    => 'product',
		);

		if ( $is_update ) {
			$product_data['ID'] = $existing;
			wp_update_post( $product_data );
			$wc_id = $existing;
		} else {
			$wc_id = wp_insert_post( $product_data );
		}

		if ( ! $wc_id || is_wp_error( $wc_id ) ) {
			return false;
		}

		wp_set_object_terms( $wc_id, 'simple', 'product_type' );
		update_post_meta( $wc_id, '_virtual', 'yes' );
		update_post_meta( $wc_id, '_sold_individually', 'no' );
		update_post_meta( $wc_id, '_manage_stock', 'no' );

		self::apply_offer_meta( $wc_id, $offer );
		if ( $fg_product ) {
			self::apply_product_meta( $wc_id, $fg_product, $is_update );
		}

		return $is_update ? 'updated' : 'created';
	}

	private static function apply_product_meta( $wc_id, $fg_product, $is_update ) {
		$fg_pid = $fg_product['id'] ?? '';
		if ( $fg_pid ) update_post_meta( $wc_id, '_fungies_product_id', $fg_pid );

		$type = $fg_product['type'] ?? '';
		if ( $type ) update_post_meta( $wc_id, '_fungies_product_type', $type );

		$checkout_url = $fg_product['checkoutUrl'] ?? ( $fg_product['checkout_url'] ?? '' );
		if ( $checkout_url ) update_post_meta( $wc_id, '_fungies_checkout_url', $checkout_url );

		$developer = $fg_product['developer'] ?? '';
		if ( $developer ) update_post_meta( $wc_id, '_fungies_developer', $developer );

		$publisher = $fg_product['publisher'] ?? '';
		if ( $publisher ) update_post_meta( $wc_id, '_fungies_publisher', $publisher );

		$image_url = $fg_product['imageUrl'] ?? ( $fg_product['image_url'] ?? '' );
		if ( $image_url && ! $is_update ) {
			self::set_product_image( $wc_id, $image_url );
		}
	}

	private static function apply_offer_meta( $product_id, $offer ) {
		$offer_id = $offer['id'] ?? '';
		$price    = $offer['price'] ?? 0;
		$original = $offer['originalPrice'] ?? ( $offer['original_price'] ?? $price );
		$currency = strtoupper( $offer['currency'] ?? 'USD' );

		$price_amount    = $price / 100;
		$original_amount = $original / 100;

		update_post_meta( $product_id, '_fungies_offer_id', $offer_id );
		update_post_meta( $product_id, '_regular_price', $original_amount );
		update_post_meta( $product_id, '_price', $price_amount );

		if ( $original > $price && $price > 0 ) {
			update_post_meta( $product_id, '_sale_price', $price_amount );
		} else {
			delete_post_meta( $product_id, '_sale_price' );
		}

		update_post_meta( $product_id, '_fungies_currency', $currency );
	}

	private static function find_wc_product_by_offer_id( $offer_id ) {
		global $wpdb;
		// Reverse lookup WC post from a Fungies offer ID stored in post meta.
		// Single-row LIMIT 1 against an indexed meta_key/meta_value; running this
		// through WP_Query just to satisfy a lint adds overhead with no benefit.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fungies_offer_id' AND meta_value = %s
			 LIMIT 1",
			$offer_id
		) );
		return $product_id ? (int) $product_id : null;
	}

	private static function set_product_image( $product_id, $image_url ) {
		if ( ! self::is_image_url_allowed( $image_url ) ) {
			self::log( sprintf( 'Refused to sideload product image from disallowed host: %s', $image_url ), 'warning' );
			return;
		}

		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attachment_id = media_sideload_image( $image_url, $product_id, '', 'id' );
		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $product_id, $attachment_id );
		}
	}

	/**
	 * Allowlist gate for outbound image fetches.
	 *
	 * Mitigates SSRF risk: `media_sideload_image` (via `download_url` →
	 * `wp_safe_remote_get`) blocks loopback IPs by default but does not
	 * restrict the destination host. Because we only ever expect images from
	 * the Fungies CDN, we hard-allowlist the two known parent domains and
	 * require HTTPS.
	 *
	 * Site owners with a custom Fungies-hosted CDN can extend the allowlist
	 * via the `fungies_image_host_allowlist` filter — they must opt in
	 * explicitly rather than inherit a permissive default.
	 *
	 * @param string $image_url Raw URL as returned by the Fungies API.
	 * @return bool True iff the URL is HTTPS and its host is on the allowlist.
	 */
	private static function is_image_url_allowed( $image_url ) {
		$image_url = (string) $image_url;
		if ( '' === $image_url ) {
			return false;
		}
		$parts = wp_parse_url( $image_url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		if ( 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		$host = strtolower( $parts['host'] );

		/**
		 * Filter the host suffixes from which Fungies product images may be
		 * sideloaded. Each entry must be a bare domain suffix (no scheme, no
		 * leading dot). Subdomains match automatically: an allowlist entry of
		 * `fungies.io` permits `cdn.fungies.io` and `images.fungies.io`, but
		 * does not permit `evilfungies.io`.
		 *
		 * @param string[] $allowed Default allowlist (production + staging).
		 */
		$allowed = (array) apply_filters( 'fungies_image_host_allowlist', array(
			'fungies.io',
			'fungies.net',
		) );

		foreach ( $allowed as $suffix ) {
			if ( self::host_matches_suffix( $host, $suffix ) ) {
				return true;
			}
		}
		return false;
	}

	private static function host_matches_suffix( $host, $suffix ) {
		$suffix = strtolower( ltrim( (string) $suffix, '.' ) );
		if ( '' === $suffix ) {
			return false;
		}
		if ( $host === $suffix ) {
			return true;
		}
		$dotted = '.' . $suffix;
		// PHP 7.4-compatible suffix check (avoids `str_ends_with`).
		return ( strlen( $host ) > strlen( $dotted ) )
			&& ( substr( $host, -strlen( $dotted ) ) === $dotted );
	}

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Product Sync] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
