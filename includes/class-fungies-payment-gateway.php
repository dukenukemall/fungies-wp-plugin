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

		$redirect_url = Fungies_Checkout_URL_Builder::build( $order );

		wc_get_logger()->info(
			sprintf( '[Gateway] Redirecting order #%d to Fungies hosted checkout: %s', $order_id, $redirect_url ),
			array( 'source' => 'fungies' )
		);

		return array(
			'result'   => 'success',
			'redirect' => $redirect_url,
		);
	}
}
