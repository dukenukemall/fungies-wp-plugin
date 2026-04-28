<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Checkout_URL_Builder {

	public static function build( $order ) {
		$checkout_url = self::resolve( $order );
		if ( ! $checkout_url ) {
			return $order->get_checkout_order_received_url();
		}
		return add_query_arg( array(
			'fngs-user-email'       => $order->get_billing_email(),
			'fngs-customer-country' => $order->get_billing_country() ? $order->get_billing_country() : '',
		), $checkout_url );
	}

	private static function resolve( $order ) {
		$offer_ids = self::collect_offer_ids( $order );

		if ( empty( $offer_ids ) ) {
			$stored = self::find_stored_url( $order );
			if ( $stored ) return $stored;
			self::log( sprintf( 'Order #%d has no items mapped to Fungies offers.', $order->get_id() ), 'warning' );
			return '';
		}

		if ( count( $offer_ids ) === 1 ) {
			$store = self::store_url();
			return $store ? $store . '/checkout/' . $offer_ids[0] : '';
		}

		return self::build_element_url( $order, $offer_ids );
	}

	private static function collect_offer_ids( $order ) {
		$ids = array();
		foreach ( $order->get_items() as $item ) {
			$pid      = $item->get_product_id();
			$qty      = max( 1, (int) $item->get_quantity() );
			$offer_id = get_post_meta( $pid, '_fungies_offer_id', true );
			if ( ! $offer_id ) {
				$offer_id = get_post_meta( $pid, '_fungies_pushed_offer_id', true );
			}
			if ( ! $offer_id ) {
				self::log( sprintf( 'Order #%d item "%s" (product %d) has no Fungies offer ID — skipping.', $order->get_id(), $item->get_name(), $pid ), 'warning' );
				continue;
			}
			for ( $i = 0; $i < $qty; $i++ ) $ids[] = $offer_id;
		}
		return $ids;
	}

	private static function find_stored_url( $order ) {
		foreach ( $order->get_items() as $item ) {
			$url = get_post_meta( $item->get_product_id(), '_fungies_checkout_url', true );
			if ( $url ) return $url;
		}
		return '';
	}

	private static function build_element_url( $order, $offer_ids ) {
		$store = self::store_url();
		if ( ! $store ) return '';

		$client   = new Fungies_API_Client();
		$response = $client->create_checkout_element( array(
			'name'      => sprintf( 'WC Order #%d', $order->get_id() ),
			'offersIds' => array_values( $offer_ids ),
		) );

		if ( is_wp_error( $response ) ) {
			self::log( sprintf( 'Failed to create Fungies checkout element for order #%d: %s', $order->get_id(), $response->get_error_message() ), 'error' );
			return '';
		}

		$id = $response['data']['checkoutElement']['id'] ?? '';
		if ( ! $id ) {
			self::log( sprintf( 'Fungies checkout element response missing id for order #%d. Response: %s', $order->get_id(), wp_json_encode( $response ) ), 'error' );
			return '';
		}

		$order->update_meta_data( '_fungies_checkout_element_id', $id );
		$order->save();
		self::log( sprintf( 'Created Fungies checkout element %s for order #%d with %d offer(s).', $id, $order->get_id(), count( $offer_ids ) ) );

		return $store . '/checkout-element/' . $id;
	}

	private static function store_url() {
		$url = Fungies_Admin_Settings::get_option( 'store_url', '' );
		if ( empty( $url ) ) {
			self::log( 'Fungies Store URL not configured. Go to WooCommerce → Settings → Fungies to set it.', 'error' );
			return '';
		}
		return untrailingslashit( $url );
	}

	private static function log( $msg, $level = 'info' ) {
		wc_get_logger()->log( $level, '[Gateway] ' . $msg, array( 'source' => 'fungies' ) );
	}
}
