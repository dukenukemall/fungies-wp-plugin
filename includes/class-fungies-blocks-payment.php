<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Fungies_Blocks_Payment extends AbstractPaymentMethodType {

	protected $name = 'fungies';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_fungies_settings', array() );
	}

	public function is_active() {
		return ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'];
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

	public function get_payment_method_data() {
		return array(
			'title'       => $this->get_setting( 'title' ),
			'description' => $this->get_setting( 'description' ),
			'supports'    => array( 'products' ),
		);
	}
}
