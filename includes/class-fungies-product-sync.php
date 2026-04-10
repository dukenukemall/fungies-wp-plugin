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
		$client = new Fungies_API_Client();

		$offers_response = $client->get_offers( array( 'product.types' => 'OneTimePayment' ) );
		if ( is_wp_error( $offers_response ) ) {
			self::log( 'Offers fetch failed: ' . $offers_response->get_error_message(), 'error' );
			return $offers_response;
		}

		$offers_list = self::extract_list( $offers_response, 'offers' );
		self::log( 'Fetched ' . count( $offers_list ) . ' offers from API.' );

		$offers_list = self::filter_one_time_payment( $offers_list );
		self::log( count( $offers_list ) . ' OneTimePayment offers after filter.' );

		$offer_product_map = self::build_offer_product_map( $client );

		$product_cache = array();
		$synced  = 0;
		$created = 0;
		$updated = 0;

		foreach ( $offers_list as $offer ) {
			$offer_id   = $offer['id'] ?? '';
			$product_id = $offer['productId'] ?? ( $offer['product_id'] ?? '' );

			if ( ! $product_id && isset( $offer_product_map[ $offer_id ] ) ) {
				$product_id = $offer_product_map[ $offer_id ]['id'] ?? '';
			}

			$fg_product = null;
			if ( $product_id ) {
				$fg_product = self::get_product_cached( $client, $product_id, $product_cache );
			}

			if ( ! $fg_product && isset( $offer_product_map[ $offer_id ] ) ) {
				$fg_product = $offer_product_map[ $offer_id ];
			}

			if ( $fg_product && ! self::is_product_one_time( $fg_product ) ) {
				self::log( sprintf( 'Skipping offer %s — product type is not OneTimePayment.', substr( $offer_id, 0, 8 ) ) );
				continue;
			}

			$result = self::sync_from_offer( $offer, $fg_product );

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
			__( 'Synced %d OneTimePayment offers (%d created, %d updated).', 'fungies-wp' ),
			$synced, $created, $updated
		);

		self::log( $summary );

		return array(
			'synced'  => $synced,
			'created' => $created,
			'updated' => $updated,
			'message' => $summary,
		);
	}

	private static function build_offer_product_map( $client ) {
		$map = array();

		$resp = $client->get_products();
		if ( is_wp_error( $resp ) ) {
			self::log( 'Products fetch for name enrichment failed, names will fall back.', 'warning' );
			return $map;
		}

		$products = self::extract_list( $resp, 'products' );
		self::log( 'Loaded ' . count( $products ) . ' products for name enrichment.' );

		foreach ( $products as $product ) {
			$pid = $product['id'] ?? '';
			if ( ! $pid ) {
				continue;
			}

			$detail = $client->get_product( $pid );
			if ( is_wp_error( $detail ) ) {
				continue;
			}

			$full   = $detail['data']['product'] ?? $product;
			$offers = $detail['data']['offers'] ?? array();

			foreach ( $offers as $offer_ref ) {
				$oid = $offer_ref['id'] ?? '';
				if ( $oid ) {
					$map[ $oid ] = $full;
				}
			}
		}

		self::log( 'Mapped ' . count( $map ) . ' offers to their parent products.' );
		return $map;
	}

	private static function get_product_cached( $client, $product_id, &$cache ) {
		if ( isset( $cache[ $product_id ] ) ) {
			return $cache[ $product_id ];
		}

		self::log( 'Fetching product details: ' . $product_id );
		$response = $client->get_product( $product_id );

		if ( is_wp_error( $response ) ) {
			self::log( 'Product fetch failed for ' . $product_id . ': ' . $response->get_error_message(), 'warning' );
			$cache[ $product_id ] = null;
			return null;
		}

		$product = $response['data']['product'] ?? ( $response['product'] ?? null );
		$cache[ $product_id ] = $product;

		if ( $product ) {
			self::log( 'Product loaded: ' . ( $product['name'] ?? '(no name)' ) );
		}

		return $product;
	}

	private static function filter_one_time_payment( $offers ) {
		return array_filter( $offers, function ( $offer ) {
			$types = $offer['product']['types'] ?? ( $offer['productTypes'] ?? array() );
			if ( ! empty( $types ) && is_array( $types ) ) {
				return in_array( 'OneTimePayment', $types, true );
			}
			if ( ! empty( $offer['recurringInterval'] ) ) {
				return false;
			}
			if ( ! empty( $offer['recurringIntervalCount'] ) ) {
				return false;
			}
			if ( ! empty( $offer['trialInterval'] ) ) {
				return false;
			}
			if ( ! empty( $offer['trialIntervalCount'] ) ) {
				return false;
			}
			return true;
		} );
	}

	private static function is_product_one_time( $product ) {
		$types = $product['types'] ?? ( $product['productTypes'] ?? array() );
		if ( empty( $types ) || ! is_array( $types ) ) {
			return true;
		}
		return in_array( 'OneTimePayment', $types, true );
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
		$desc = ! empty( $offer_desc ) ? $offer_desc : $product_desc;

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
		if ( $fg_pid ) {
			update_post_meta( $wc_id, '_fungies_product_id', $fg_pid );
		}

		$type = $fg_product['type'] ?? '';
		if ( $type ) {
			update_post_meta( $wc_id, '_fungies_product_type', $type );
		}

		$checkout_url = $fg_product['checkoutUrl'] ?? ( $fg_product['checkout_url'] ?? '' );
		if ( $checkout_url ) {
			update_post_meta( $wc_id, '_fungies_checkout_url', $checkout_url );
		}

		$developer = $fg_product['developer'] ?? '';
		if ( $developer ) {
			update_post_meta( $wc_id, '_fungies_developer', $developer );
		}

		$publisher = $fg_product['publisher'] ?? '';
		if ( $publisher ) {
			update_post_meta( $wc_id, '_fungies_publisher', $publisher );
		}

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

		$wc_currency = get_woocommerce_currency();
		if ( $currency !== $wc_currency ) {
			self::log( sprintf(
				'Offer %s is in %s (store currency: %s) — price displayed with original currency.',
				substr( $offer_id, 0, 8 ), $currency, $wc_currency
			) );
		}
	}

	private static function find_wc_product_by_offer_id( $offer_id ) {
		global $wpdb;

		$product_id = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fungies_offer_id' AND meta_value = %s
			 LIMIT 1",
			$offer_id
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
