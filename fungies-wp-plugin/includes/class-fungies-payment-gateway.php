<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Payment_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'fungies';
		$this->icon               = FUNGIES_WP_PLUGIN_URL . 'assets/img/fungies-icon.png';
		$this->has_fields         = false;
		$this->method_title       = __( 'Fungies Checkout', 'fungies-wp' );
		$this->method_description = __( 'Accept payments via Fungies — the merchant of record handles payments, taxes, and compliance.', 'fungies-wp' );

		$this->supports = array( 'products' );

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled     = $this->get_option( 'enabled' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'fungies-wp' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Fungies Checkout', 'fungies-wp' ),
				'default' => 'yes',
			),
			'title' => array(
				'title'       => __( 'Title', 'fungies-wp' ),
				'type'        => 'text',
				'description' => __( 'Title shown to customers during checkout.', 'fungies-wp' ),
				'default'     => __( 'Fungies Checkout', 'fungies-wp' ),
			),
			'description' => array(
				'title'       => __( 'Description', 'fungies-wp' ),
				'type'        => 'textarea',
				'description' => __( 'Description shown to customers during checkout.', 'fungies-wp' ),
				'default'     => __( 'Pay securely via Fungies. All major payment methods accepted.', 'fungies-wp' ),
			),
		);
	}

	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		$order->update_status( 'pending', __( 'Awaiting Fungies payment.', 'fungies-wp' ) );

		$redirect_url = self::build_hosted_checkout_url( $order );

		wc_get_logger()->info(
			sprintf( '[Gateway] Redirecting order #%d to Fungies hosted checkout: %s', $order_id, $redirect_url ),
			array( 'source' => 'fungies' )
		);

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}

	private static function build_hosted_checkout_url( $order ) {
		$checkout_url = self::resolve_checkout_url( $order );

		if ( ! $checkout_url ) {
			wc_get_logger()->warning(
				sprintf( '[Gateway] No Fungies checkout URL for order #%d — falling back to thank-you page.', $order->get_id() ),
				array( 'source' => 'fungies' )
			);
			return $order->get_checkout_order_received_url();
		}

		$country = $order->get_billing_country();

		$url = add_query_arg( array(
			'fngs-user-email'      => $order->get_billing_email(),
			'fngs-customer-country' => $country ? $country : '',
			'custom_wc_order_id'   => $order->get_id(),
			'success_url'          => $order->get_checkout_order_received_url(),
			'cancel_url'           => wc_get_checkout_url(),
		), $checkout_url );

		return $url;
	}

	private static function resolve_checkout_url( $order ) {
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();

			$stored_url = get_post_meta( $product_id, '_fungies_checkout_url', true );
			if ( $stored_url ) {
				return $stored_url;
			}

			$offer_id = get_post_meta( $product_id, '_fungies_offer_id', true );
			if ( $offer_id ) {
				$built_url = self::build_url_from_offer_id( $offer_id );
				if ( $built_url ) {
					return $built_url;
				}
			}
		}

		return '';
	}

	private static function build_url_from_offer_id( $offer_id ) {
		$store_url = Fungies_Admin_Settings::get_option( 'store_url', '' );

		if ( empty( $store_url ) ) {
			wc_get_logger()->error(
				'[Gateway] Fungies Store URL not configured. Go to WooCommerce → Settings → Fungies to set it.',
				array( 'source' => 'fungies' )
			);
			return '';
		}

		$store_url = untrailingslashit( $store_url );

		return $store_url . '/checkout/' . $offer_id;
	}
}
