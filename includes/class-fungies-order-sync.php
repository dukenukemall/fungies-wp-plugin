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

	private static function handle_payment_success( $payload ) {
		$data = $payload['data'] ?? $payload;

		$fungies_order_id = $data['orderId'] ?? ( $data['order_id'] ?? '' );
		$wc_order_id      = self::extract_wc_order_id( $data );
		$order             = $wc_order_id ? wc_get_order( $wc_order_id ) : null;

		if ( ! $order ) {
			$order = self::create_order_from_webhook( $data );
		}

		if ( ! $order ) {
			self::log( 'Could not create or find WC order for payment_success.', 'error' );
			return;
		}

		$order->payment_complete( $fungies_order_id );
		$order->add_order_note(
			sprintf( __( 'Fungies payment completed. Order ID: %s', 'fungies-wp' ), $fungies_order_id )
		);

		self::store_order_meta( $order, $data );

		self::log( "Order #{$order->get_id()} marked completed via payment_success." );
	}

	private static function handle_payment_failed( $payload ) {
		$data    = $payload['data'] ?? $payload;
		$order   = self::find_order( $data );

		if ( ! $order ) {
			self::log( 'No WC order found for payment_failed.', 'warning' );
			return;
		}

		$order->update_status( 'failed', __( 'Fungies payment failed.', 'fungies-wp' ) );
		self::log( "Order #{$order->get_id()} marked failed." );
	}

	private static function handle_payment_refunded( $payload ) {
		$data  = $payload['data'] ?? $payload;
		$order = self::find_order( $data );

		if ( ! $order ) {
			self::log( 'No WC order found for payment_refunded.', 'warning' );
			return;
		}

		$refund_amount = $data['refundAmount'] ?? ( $data['refund_amount'] ?? $order->get_total() );

		wc_create_refund( array(
			'amount'   => $refund_amount,
			'reason'   => __( 'Refunded via Fungies.', 'fungies-wp' ),
			'order_id' => $order->get_id(),
		) );

		$order->update_status( 'refunded', __( 'Fungies payment refunded.', 'fungies-wp' ) );
		self::log( "Order #{$order->get_id()} refunded." );
	}

	private static function handle_subscription_created( $payload ) {
		$data  = $payload['data'] ?? $payload;
		$order = self::find_order( $data );

		if ( ! $order ) {
			return;
		}

		$sub_id = $data['subscriptionId'] ?? ( $data['subscription_id'] ?? '' );
		$order->update_meta_data( '_fungies_subscription_id', $sub_id );
		$order->save();

		$order->add_order_note(
			sprintf( __( 'Fungies subscription created: %s', 'fungies-wp' ), $sub_id )
		);
		self::log( "Subscription {$sub_id} linked to order #{$order->get_id()}." );
	}

	private static function handle_subscription_interval( $payload ) {
		$data          = $payload['data'] ?? $payload;
		$parent_order  = self::find_order( $data );
		$parent_id     = $parent_order ? $parent_order->get_id() : 0;

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

		$renewal->set_payment_method( 'fungies' );
		$renewal->set_total( $data['amount'] ?? ( $parent_order ? $parent_order->get_total() : 0 ) );
		$renewal->payment_complete();
		self::store_order_meta( $renewal, $data );
		$renewal->save();

		self::log( "Renewal order #{$renewal->get_id()} created for subscription interval." );
	}

	private static function handle_subscription_cancelled( $payload ) {
		$data  = $payload['data'] ?? $payload;
		$order = self::find_order( $data );

		if ( ! $order ) {
			return;
		}

		$sub_id = $data['subscriptionId'] ?? ( $data['subscription_id'] ?? '' );
		$order->update_meta_data( '_fungies_subscription_status', 'cancelled' );
		$order->save();

		$order->add_order_note(
			sprintf( __( 'Fungies subscription cancelled: %s', 'fungies-wp' ), $sub_id )
		);
		self::log( "Subscription {$sub_id} cancelled on order #{$order->get_id()}." );
	}

	private static function extract_wc_order_id( $data ) {
		$custom = $data['customFields'] ?? ( $data['custom_fields'] ?? array() );

		if ( isset( $custom['wc_order_id'] ) ) {
			return (int) $custom['wc_order_id'];
		}

		$metadata = $data['metadata'] ?? array();
		if ( isset( $metadata['wc_order_id'] ) ) {
			return (int) $metadata['wc_order_id'];
		}

		return null;
	}

	private static function find_order( $data ) {
		$wc_order_id = self::extract_wc_order_id( $data );

		if ( $wc_order_id ) {
			$order = wc_get_order( $wc_order_id );
			if ( $order ) return $order;
		}

		$fungies_order_id = $data['orderId'] ?? ( $data['order_id'] ?? '' );
		if ( $fungies_order_id ) {
			return self::find_order_by_meta( '_fungies_order_id', $fungies_order_id );
		}

		return null;
	}

	private static function find_order_by_meta( $key, $value ) {
		$orders = wc_get_orders( array(
			'meta_key'   => $key,
			'meta_value' => $value,
			'limit'      => 1,
		) );

		return ! empty( $orders ) ? $orders[0] : null;
	}

	private static function create_order_from_webhook( $data ) {
		$order = wc_create_order();

		if ( is_wp_error( $order ) ) {
			self::log( 'Failed to create order from webhook: ' . $order->get_error_message(), 'error' );
			return null;
		}

		$customer = $data['customer'] ?? ( $data['billingData'] ?? array() );

		$order->set_billing_email( $customer['email'] ?? '' );
		$order->set_billing_first_name( $customer['firstName'] ?? ( $customer['first_name'] ?? '' ) );
		$order->set_billing_last_name( $customer['lastName'] ?? ( $customer['last_name'] ?? '' ) );
		$order->set_billing_country( $customer['country'] ?? '' );

		self::attach_line_items( $order, $data );

		$order->set_payment_method( 'fungies' );
		$order->set_total( $data['amount'] ?? ( $data['total'] ?? 0 ) );
		$order->save();

		self::log( "Created WC order #{$order->get_id()} from webhook data." );

		return $order;
	}

	private static function attach_line_items( $order, $data ) {
		$items = $data['items'] ?? ( $data['lineItems'] ?? array() );

		foreach ( $items as $item ) {
			$offer_id = $item['offerId'] ?? ( $item['offer_id'] ?? '' );
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

	private static function store_order_meta( $order, $data ) {
		$map = array(
			'_fungies_order_id'      => $data['orderId'] ?? ( $data['order_id'] ?? '' ),
			'_fungies_order_number'  => $data['orderNumber'] ?? ( $data['order_number'] ?? '' ),
			'_fungies_payment_id'    => $data['paymentId'] ?? ( $data['payment_id'] ?? '' ),
			'_fungies_payment_type'  => $data['paymentType'] ?? ( $data['payment_type'] ?? '' ),
			'_fungies_subscription_id' => $data['subscriptionId'] ?? ( $data['subscription_id'] ?? '' ),
			'_fungies_event_id'      => $data['idempotencyKey'] ?? ( $data['event_id'] ?? '' ),
			'_fungies_invoice_url'   => $data['invoiceUrl'] ?? ( $data['invoice_url'] ?? '' ),
			'_fungies_fee'           => $data['fee'] ?? '',
			'_fungies_tax'           => $data['tax'] ?? ( $data['taxAmount'] ?? '' ),
		);

		foreach ( $map as $key => $value ) {
			if ( '' !== $value ) {
				$order->update_meta_data( $key, $value );
			}
		}

		$order->save();
	}

	private static function log( $message, $level = 'info' ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->log( $level, '[Order Sync] ' . $message, array( 'source' => 'fungies' ) );
		}
	}
}
