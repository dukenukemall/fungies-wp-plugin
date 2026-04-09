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

		$checkout_mode = Fungies_Admin_Settings::get_option( 'checkout_mode', 'overlay' );

		if ( 'hosted' === $checkout_mode ) {
			$redirect_url = self::build_hosted_checkout_url( $order );
			return array(
				'result'   => 'success',
				'redirect' => $redirect_url,
			);
		}

		return array(
			'result'       => 'success',
			'redirect'     => '',
			'fungies_mode' => $checkout_mode,
			'order_id'     => $order_id,
		);
	}

	private static function build_hosted_checkout_url( $order ) {
		$items     = array();
		$first_url = '';

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$offer_id   = get_post_meta( $product_id, '_fungies_offer_id', true );

			if ( ! $first_url ) {
				$first_url = get_post_meta( $product_id, '_fungies_checkout_url', true );
			}

			if ( $offer_id ) {
				$items[] = array(
					'offerId'  => $offer_id,
					'quantity' => $item->get_quantity(),
				);
			}
		}

		if ( ! $first_url ) {
			return $order->get_checkout_order_received_url();
		}

		$url = add_query_arg( array(
			'prefill_email'      => $order->get_billing_email(),
			'prefill_first_name' => $order->get_billing_first_name(),
			'prefill_last_name'  => $order->get_billing_last_name(),
			'custom_wc_order_id' => $order->get_id(),
		), $first_url );

		return $url;
	}
}
