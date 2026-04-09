<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Dashboard_Widget {

	public static function init() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'fungies_sync_status',
			__( 'Fungies Sync Status', 'fungies-wp' ),
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		$last_sync     = get_option( 'fungies_last_sync', '' );
		$product_count = get_option( 'fungies_product_count', 0 );
		$public_key    = Fungies_Admin_Settings::get_option( 'public_key' );
		$connected     = ! empty( $public_key );

		echo '<table class="widefat striped"><tbody>';

		echo '<tr><th>' . esc_html__( 'Connection', 'fungies-wp' ) . '</th><td>';
		echo $connected
			? '<span style="color:green">&#10003; ' . esc_html__( 'Connected', 'fungies-wp' ) . '</span>'
			: '<span style="color:red">&#10007; ' . esc_html__( 'Not configured', 'fungies-wp' ) . '</span>';
		echo '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Products Synced', 'fungies-wp' ) . '</th>';
		echo '<td>' . esc_html( $product_count ) . '</td></tr>';

		echo '<tr><th>' . esc_html__( 'Last Sync', 'fungies-wp' ) . '</th>';
		echo '<td>' . ( $last_sync ? esc_html( $last_sync ) : esc_html__( 'Never', 'fungies-wp' ) ) . '</td></tr>';

		echo '</tbody></table>';

		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=fungies' );
		echo '<p><a class="button" href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Go to Settings', 'fungies-wp' ) . '</a></p>';
	}
}
