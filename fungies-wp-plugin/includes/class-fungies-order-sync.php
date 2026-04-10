<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Order_Sync {

	public static function handle_event( $event, $payload ) {
		self::log( "Handling event: {$event}" );

		switch ( $event ) {
			case 'payment_success':
				self::handle_payment_success( $payload );
				break;
			case 'payment_failed':
				self::handle_payment_failed( $payload );
				break;
			case 'payment_refunded':
			case 'payment_refund':
				self::handle_payment_refunded( $payload );
				break;
			case 'subscription_created':
				self::handle_subscription_created( $payload );
				break;
			case 'subscription_interval':
				self::handle_subscription_interval( $payload );
				break;
			case 'subscription_cancelled':
				self::handle_subscription_cancelled( $payload );
				break;
			default:
				self::log( "Unhandled event type: {$event}", 'warning' );
		}
	}

	private static function extract_event_data( $payload ) {
		$data     = $payload['data'] ?? $payload;
		$order    = $data['order'] ?? $data;
		$payment  = $data['payment'] ?? array();
		$customer = $data['customer'] ?? ( $data['user'] ?? array() );
		$items    = $data['items'] ?? array();

		return compact( 'data', 'order', 'payment', 'customer', 'items' );
	}

	private static function cents_to_dollars( $cents ) {
		if ( ! is_numeric( $cents ) ) return 0;
		return round( (float) $cents / 100, 2 );
	}

	private static function handle_payment_success( $payload ) {
		$ev = self::extract_event_data( $payload );

		$fungies_order_id = $ev['order']['id'] ?? '';
		$wc_order_id      = self::extract_wc_order_id( $ev );

		self::log( "payment_success — Fungies order: {$fungies_order_id}, extracted WC order ID: " . ( $wc_order_id ?: 'none' ) );

		$wc_order = $wc_order_id ? wc_get_order( $wc_order_id ) : null;

		if ( ! $wc_order ) {
			self::log( 'Looking up WC order by _fungies_order_id meta...' );
			$wc_order = self::find_order_by_meta( '_fungies_order_id', $fungies_order_id );
		}

		if ( ! $wc_order ) {
			self::log( 'Looking up pending Fungies order by billing email...' );
			$wc_order = self::find_pending_order_by_email( $ev );
		}

		if ( ! $wc_order ) {
			self::log( 'No existing WC order found — creating from webhook data.' );
			$wc_order = self::create_order_from_webhook( $ev );
		}

		if ( ! $wc_order ) {
			self::log( 'Could not create or find WC order for payment_success.', 'error' );
			return;
		}

		$current_status = $wc_order->get_status();
		if ( in_array( $current_status, array( 'completed', 'processing' ), true ) ) {
			self::log( "Order #{$wc_order->get_id()} already {$current_status} — skipping duplicate payment_success." );
			return;
		}

		$wc_order->payment_complete( $fungies_order_id );
		$wc_order->add_order_note(
			sprintf( __( 'Fungies payment completed. Order ID: %s', 'fungies-wp' ), $fungies_order_id )
		);

		self::store_order_meta( $wc_order, $ev );

		self::log( "Order #{$wc_order->get_id()} marked completed via payment_success." );
	}

	private static function handle_payment_failed( $payload ) {
		$ev    = self::extract_event_data( $payload );
		$order = self::find_order( $ev );

		if ( ! $order ) {
			self::log( 'No WC order found for payment_failed.', 'warning' );
			return;
		}

		$order->update_status( 'failed', __( 'Fungies payment failed.', 'fungies-wp' ) );
		self::log( "Order #{$order->get_id()} marked failed." );
	}

	private static function handle_payment_refunded( $payload ) {
		$ev    = self::extract_event_data( $payload );
		$order = self::find_order( $ev );

		if ( ! $order ) {
			self::log( 'No WC order found for payment_refunded.', 'warning' );
			return;
		}

		$refund_cents = $ev['payment']['refundAmount']
			?? ( $ev['order']['value'] ?? 0 );
		$refund_amount = self::cents_to_dollars( $refund_cents );

		wc_create_refund( array(
			'amount'   => $refund_amount,
			'reason'   => __( 'Refunded via Fungies.', 'fungies-wp' ),
			'order_id' => $order->get_id(),
		) );

		$order->update_status( 'refunded', __( 'Fungies payment refunded.', 'fungies-wp' ) );
		self::log( "Order #{$order->get_id()} refunded ({$refund_amount})." );
	}

	private static function handle_subscription_created( $payload ) {
		$ev    = self::extract_event_data( $payload );
		$order = self::find_order( $ev );

		if ( ! $order ) return;

		$sub = $ev['data']['subscription'] ?? array();
		$sub_id = $sub['id'] ?? ( $ev['order']['subscriptionId'] ?? '' );

		$order->update_meta_data( '_fungies_subscription_id', $sub_id );
		$order->save();

		$order->add_order_note(
			sprintf( __( 'Fungies subscription created: %s', 'fungies-wp' ), $sub_id )
		);
		self::log( "Subscription {$sub_id} linked to order #{$order->get_id()}." );
	}

	private static function handle_subscription_interval( $payload ) {
		$ev           = self::extract_event_data( $payload );
		$parent_order = self::find_order( $ev );
		$parent_id    = $parent_order ? $parent_order->get_id() : 0;

		$renewal = wc_create_order( array( 'parent' => $parent_id ) );

		if ( is_wp_error( $renewal ) ) {
			self::log( 'Failed to create renewal order: ' . $renewal->get_error_message(), 'error' );
			return;
		}

		if ( $parent_order ) {
			foreach ( $parent_order->get_items() as $item ) {
				$renewal->add_product( $item->get_product(), $item->get_quantity() );
			}
		}

		$total = self::cents_to_dollars( $ev['order']['value'] ?? 0 );
		if ( ! $total && $parent_order ) {
			$total = $parent_order->get_total();
		}

		$renewal->set_payment_method( 'fungies' );
		$renewal->set_total( $total );
		$renewal->payment_complete();
		self::store_order_meta( $renewal, $ev );
		$renewal->save();

		self::log( "Renewal order #{$renewal->get_id()} created for subscription interval." );
	}

	private static function handle_subscription_cancelled( $payload ) {
		$ev    = self::extract_event_data( $payload );
		$order = self::find_order( $ev );

		if ( ! $order ) return;

		$sub = $ev['data']['subscription'] ?? array();
		$sub_id = $sub['id'] ?? ( $ev['order']['subscriptionId'] ?? '' );

		$order->update_meta_data( '_fungies_subscription_status', 'cancelled' );
		$order->save();

		$order->add_order_note(
			sprintf( __( 'Fungies subscription cancelled: %s', 'fungies-wp' ), $sub_id )
		);
		self::log( "Subscription {$sub_id} cancelled on order #{$order->get_id()}." );
	}

	private static function extract_wc_order_id( $ev ) {
		$candidates = array( 'wc_order_id', 'custom_wc_order_id' );

		foreach ( $ev['items'] as $item ) {
			$cf = $item['customFields'] ?? ( $item['custom_fields'] ?? array() );
			foreach ( $candidates as $key ) {
				if ( ! empty( $cf[ $key ] ) ) {
					return (int) $cf[ $key ];
				}
			}
		}

		$custom = $ev['data']['customFields'] ?? ( $ev['data']['custom_fields'] ?? array() );
		foreach ( $candidates as $key ) {
			if ( ! empty( $custom[ $key ] ) ) {
				return (int) $custom[ $key ];
			}
		}

		$metadata = $ev['data']['metadata'] ?? array();
		foreach ( $candidates as $key ) {
			if ( ! empty( $metadata[ $key ] ) ) {
				return (int) $metadata[ $key ];
			}
		}

		$query_params = $ev['data']['queryParams'] ?? ( $ev['data']['query_params'] ?? array() );
		foreach ( $candidates as $key ) {
			if ( ! empty( $query_params[ $key ] ) ) {
				return (int) $query_params[ $key ];
			}
		}

		return null;
	}

	private static function find_order( $ev ) {
		$wc_order_id = self::extract_wc_order_id( $ev );

		if ( $wc_order_id ) {
			$order = wc_get_order( $wc_order_id );
			if ( $order ) return $order;
		}

		$fungies_order_id = $ev['order']['id'] ?? '';
		if ( $fungies_order_id ) {
			$order = self::find_order_by_meta( '_fungies_order_id', $fungies_order_id );
			if ( $order ) return $order;
		}

		return self::find_pending_order_by_email( $ev );
	}

	private static function find_pending_order_by_email( $ev ) {
		$customer = $ev['customer'];
		$email    = $customer['email'] ?? ( $customer['username'] ?? '' );

		if ( ! $email || ! is_email( $email ) ) {
			return null;
		}

		$offer_id = '';
		foreach ( $ev['items'] as $item ) {
			$offer = $item['offer'] ?? array();
			$offer_id = $offer['id'] ?? ( $item['offerId'] ?? '' );
			if ( $offer_id ) break;
		}

		$orders = wc_get_orders( array(
			'status'         => 'pending',
			'payment_method' => 'fungies',
			'billing_email'  => $email,
			'limit'          => 5,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		if ( empty( $orders ) ) {
			return null;
		}

		if ( $offer_id ) {
			foreach ( $orders as $order ) {
				foreach ( $order->get_items() as $item ) {
					$product = $item->get_product();
					if ( $product && $product->get_meta( '_fungies_offer_id' ) === $offer_id ) {
						self::log( sprintf( 'Matched pending order #%d by email + offer ID', $order->get_id() ) );
						return $order;
					}
				}
			}
		}

		self::log( sprintf( 'Matched pending order #%d by email (fallback)', $orders[0]->get_id() ) );
		return $orders[0];
	}

	private static function find_order_by_meta( $key, $value ) {
		if ( empty( $value ) ) return null;

		$orders = wc_get_orders( array(
			'meta_key'   => $key,
			'meta_value' => $value,
			'limit'      => 1,
		) );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	private static function create_order_from_webhook( $ev ) {
		$order = wc_create_order();

		if ( is_wp_error( $order ) ) {
			self::log( 'Failed to create order: ' . $order->get_error_message(), 'error' );
			return null;
		}

		$customer = $ev['customer'];

		$order->set_billing_email( $customer['email'] ?? ( $customer['username'] ?? '' ) );
		$order->set_billing_first_name( $customer['firstName'] ?? ( $customer['first_name'] ?? '' ) );
		$order->set_billing_last_name( $customer['lastName'] ?? ( $customer['last_name'] ?? '' ) );
		$order->set_billing_country( $ev['order']['country'] ?? '' );

		self::attach_line_items( $order, $ev['items'] );

		$total = self::cents_to_dollars( $ev['order']['value'] ?? 0 );

		$order->set_payment_method( 'fungies' );
		$order->set_total( $total );
		$order->save();

		self::log( "Created WC order #{$order->get_id()} from webhook data." );

		return $order;
	}

	private static function attach_line_items( $order, $items ) {
		foreach ( $items as $item ) {
			$offer    = $item['offer'] ?? array();
			$offer_id = $offer['id'] ?? ( $item['offerId'] ?? '' );
			if ( ! $offer_id ) continue;

			$product_id = self::find_product_by_offer( $offer_id );
			$product    = $product_id ? wc_get_product( $product_id ) : null;
			$qty        = $item['quantity'] ?? 1;

			if ( $product ) {
				$order->add_product( $product, $qty );
			}
		}
	}

	private static function find_product_by_offer( $offer_id ) {
		global $wpdb;

		return $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta}
			 WHERE meta_key = '_fungies_offer_id' AND meta_value = %s
			 LIMIT 1",
			$offer_id
		) );
	}

	private static function store_order_meta( $wc_order, $ev ) {
		$o = $ev['order'];
		$p = $ev['payment'];

		$map = array(
			'_fungies_order_id'        => $o['id'] ?? '',
			'_fungies_order_number'    => $o['number'] ?? '',
			'_fungies_payment_id'      => $p['id'] ?? '',
			'_fungies_payment_type'    => $p['type'] ?? '',
			'_fungies_subscription_id' => $o['subscriptionId'] ?? '',
			'_fungies_event_id'        => $ev['data']['idempotencyKey'] ?? '',
			'_fungies_invoice_url'     => $p['invoiceUrl'] ?? ( $p['invoice_url'] ?? '' ),
			'_fungies_fee'             => self::cents_to_dollars( $o['fee'] ?? 0 ),
			'_fungies_tax'             => self::cents_to_dollars( $o['tax'] ?? 0 ),
		);

		foreach ( $map as $key => $value ) {
			if ( '' !== $value && 0 !== $value ) {
				$wc_order->update_meta_data( $key, $value );
			}
		}

		$wc_order->save();
	}

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Order Sync] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
