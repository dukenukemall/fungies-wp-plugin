<?php
/**
 * Plugin Name: Fungies for WooCommerce
 * Plugin URI: https://github.com/dukenukemall/fungies-wp-plugin
 * Description: Connect your WooCommerce store to Fungies.io — sync products, use Fungies checkout, and keep orders in sync.
 * Version: 1.0.0
 * Author: Fungies
 * Author URI: https://fungies.io
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: fungies-wp
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FUNGIES_WP_VERSION', '1.0.0' );
define( 'FUNGIES_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FUNGIES_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FUNGIES_WP_PLUGIN_FILE', __FILE__ );
define( 'FUNGIES_API_BASE_URL', 'https://api.fungies.io/v0' );

require_once FUNGIES_WP_PLUGIN_DIR . 'includes/class-fungies-loader.php';

function fungies_wp_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="error"><p>';
			esc_html_e( 'Fungies for WooCommerce requires WooCommerce to be installed and active.', 'fungies-wp' );
			echo '</p></div>';
		} );
		return;
	}

	Fungies_Loader::instance();
}
add_action( 'plugins_loaded', 'fungies_wp_init' );

register_activation_hook( __FILE__, function () {
	if ( ! wp_next_scheduled( 'fungies_product_sync_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'fungies_product_sync_cron' );
	}
} );

register_deactivation_hook( __FILE__, function () {
	wp_clear_scheduled_hook( 'fungies_product_sync_cron' );
} );
