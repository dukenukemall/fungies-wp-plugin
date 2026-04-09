<?php
if ( ! defined( 'ABSPATH' ) ) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Fungies_Blocks_Payment extends AbstractPaymentMethodType {

	protected $name = 'fungies';

	public function initialize() {
		$this->settings = get_option( 'woocommerce_fungies_settings', array() );
	}

	public function is_active() {
		if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
			$gateways = WC()->payment_gateways->payment_gateways();
			if ( isset( $gateways['fungies'] ) ) {
				return $gateways['fungies']->is_available();
			}
		}

		return filter_var( $this->get_setting( 'enabled', false ), FILTER_VALIDATE_BOOLEAN );
	}

	public function get_payment_method_script_handles() {
		wp_register_script(
			'fungies-blocks-checkout',
			FUNGIES_WP_PLUGIN_URL . 'assets/js/fungies-blocks-checkout.js',
			array(
				'wc-blocks-checkout',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
				'wp-i18n',
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
		$features = $this->get_supported_features();
		if ( empty( $features ) ) {
			$features = array( 'products' );
		}

		return array(
			'title'       => $this->get_setting( 'title' ) ?: 'Fungies Checkout',
			'description' => $this->get_setting( 'description' ) ?: 'Pay securely via Fungies.',
			'supports'    => array_values( $features ),
			'icon'        => FUNGIES_WP_PLUGIN_URL . 'assets/img/fungies-icon.png',
		);
	}
}
