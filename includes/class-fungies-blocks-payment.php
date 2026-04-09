<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Fungies_Blocks_Payment extends AbstractPaymentMethodType {

	protected $name = 'fungies';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_fungies_settings', array() );
	}

	public function is_active() {
		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'fungies-blocks-checkout',
			FUNGIES_WP_PLUGIN_URL . 'assets/js/fungies-blocks-checkout.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			FUNGIES_WP_VERSION,
			true
		);

		return array( 'fungies-blocks-checkout' );
	}

	public function get_payment_method_script_handles_for_admin() {
		return $this->get_payment_method_script_handles();
	}

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => $this->get_supported_features(),
		);
	}
}
