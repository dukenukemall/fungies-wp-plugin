<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Product_Sync {

	public static function init() {
		add_action( 'wp_ajax_fungies_sync_products', array( __CLASS__, 'ajax_sync' ) );
		add_action( 'fungies_product_sync_cron', array( __CLASS__, 'sync' ) );
	}

	public static function ajax_sync() {
		check_ajax_referer( 'fungies_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'fungies-wp' ) );
		}

		$result = self::sync();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public static function sync() {
		$client   = new Fungies_API_Client();
		$products = $client->get_products();

		if ( is_wp_error( $products ) ) {
			self::log( 'Product sync failed: ' . $products->get_error_message(), 'error' );
			return $products;
		}

		$offers = $client->get_offers();
		if ( is_wp_error( $offers ) ) {
			$offers = array();
		}

		$offers_by_product = self::group_offers_by_product( $offers );

		$synced  = 0;
		$created = 0;
		$updated = 0;

		$product_list = self::extract_product_list( $products );

		foreach ( $product_list as $fg_product ) {
			$fg_id          = $fg_product['id'] ?? '';
			$product_offers = $offers_by_product[ $fg_id ] ?? array();

			$result = self::sync_single_product( $fg_product, $product_offers );

			if ( 'created' === $result ) {
				$created++;
			} elseif ( 'updated' === $result ) {
				$updated++;
			}
			$synced++;
		}

		update_option( 'fungies_last_sync', current_time( 'mysql' ) );
		update_option( 'fungies_product_count', $synced );

		$summary = sprintf(
			__( 'Synced %d products (%d created, %d updated).', 'fungies-wp' ),
			$synced,
			$created,
			$updated
		);

		self::log( $summary );

		return array(
			'synced'  => $synced,
			'created' => $created,
			'updated' => $updated,
			'message' => $summary,
		);
	}

	private static function extract_product_list( $response ) {
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			return $response['data'];
		}
		if ( isset( $response['products'] ) && is_array( $response['products'] ) ) {
			return $response['products'];
		}
		if ( is_array( $response ) && isset( $response[0]['id'] ) ) {
			return $response;
		}
		return array();
	}

	private static function group_offers_by_product( $offers_response ) {
		$grouped = array();
		$list    = array();

		if ( isset( $offers_response['data'] ) ) {
			$list = $offers_response['data'];
		} elseif ( isset( $offers_response['offers'] ) ) {
			$list = $offers_response['offers'];
		} elseif ( is_array( $offers_response ) && isset( $offers_response[0]['id'] ) ) {
			$list = $offers_response;
		}

		foreach ( $list as $offer ) {
			$pid = $offer['productId'] ?? ( $offer['product_id'] ?? '' );
			if ( $pid ) {
				$grouped[ $pid ][] = $offer;
			}
		}

		return $grouped;
	}

	private static function sync_single_product( $fg_product, $offers ) {
		$fg_id     = $fg_product['id'];
		$existing  = self::find_wc_product_by_fungies_id( $fg_id );
		$is_update = (bool) $existing;

		$primary_offer = ! empty( $offers ) ? $offers[0] : null;

		$product_data = array(
			'post_title'   => $fg_product['name'] ?? '',
			'post_content' => $fg_product['description'] ?? '',
			'post_status'  => 'publish',
			'post_type'    => 'product',
		);

		if ( $is_update ) {
			$product_data['ID'] = $existing;
			wp_update_post( $product_data );
			$wc_product_id = $existing;
		} else {
			$wc_product_id = wp_insert_post( $product_data );
		}

		if ( ! $wc_product_id || is_wp_error( $wc_product_id ) ) {
			return false;
		}

		wp_set_object_terms( $wc_product_id, 'simple', 'product_type' );

		update_post_meta( $wc_product_id, '_fungies_product_id', $fg_id );
		update_post_meta( $wc_product_id, '_virtual', 'yes' );
		update_post_meta( $wc_product_id, '_sold_individually', 'no' );
		update_post_meta( $wc_product_id, '_manage_stock', 'no' );

		if ( $primary_offer ) {
			self::apply_offer_meta( $wc_product_id, $primary_offer );
		}

		$checkout_url = $fg_product['checkoutUrl'] ?? ( $fg_product['checkout_url'] ?? '' );
		if ( $checkout_url ) {
			update_post_meta( $wc_product_id, '_fungies_checkout_url', $checkout_url );
		}

		$image_url = $fg_product['imageUrl'] ?? ( $fg_product['image_url'] ?? ( $fg_product['image'] ?? '' ) );
		if ( $image_url && ! $is_update ) {
			self::set_product_image( $wc_product_id, $image_url );
		}

		return $is_update ? 'updated' : 'created';
	}

	private static function apply_offer_meta( $product_id, $offer ) {
		$offer_id = $offer['id'] ?? '';
		$price    = $offer['price'] ?? 0;
		$original = $offer['originalPrice'] ?? ( $offer['original_price'] ?? $price );
		$currency = $offer['currency'] ?? 'USD';

		update_post_meta( $product_id, '_fungies_offer_id', $offer_id );
		update_post_meta( $product_id, '_regular_price', $original );
		update_post_meta( $product_id, '_price', $price );

		if ( $original > $price ) {
			update_post_meta( $product_id, '_sale_price', $price );
		}

		update_post_meta( $product_id, '_fungies_currency', $currency );
	}

	private static function find_wc_product_by_fungies_id( $fungies_id ) {
		global $wpdb;

		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fungies_product_id' AND meta_value = %s
			 LIMIT 1",
			$fungies_id
		) );

		return $product_id ? (int) $product_id : null;
	}

	private static function set_product_image( $product_id, $image_url ) {
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

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Product Sync] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
