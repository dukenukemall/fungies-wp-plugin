<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Fungies_Admin_Settings {

	const OPTION_PREFIX = 'fungies_';

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', array( __CLASS__, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_fungies', array( __CLASS__, 'output_settings' ) );
		add_action( 'woocommerce_update_options_fungies', array( __CLASS__, 'save_settings' ) );
		add_action( 'wp_ajax_fungies_test_connection', array( __CLASS__, 'ajax_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	public static function add_settings_tab( $tabs ) {
		$tabs['fungies'] = __( 'Fungies', 'fungies-wp' );
		return $tabs;
	}

	public static function get_settings() {
		$is_sandbox = self::get_option( 'sandbox_mode', 'no' ) === 'yes';
		$env_label  = $is_sandbox
			? __( '🟡 SANDBOX — Using staging API (api.stage.fungies.net)', 'fungies-wp' )
			: __( '🟢 PRODUCTION — Using live API (api.fungies.io)', 'fungies-wp' );

		return array(
			array(
				'title' => __( 'Environment', 'fungies-wp' ),
				'type'  => 'title',
				'desc'  => $env_label,
				'id'    => 'fungies_env_settings',
			),
			array(
				'title'    => __( 'Sandbox Mode', 'fungies-wp' ),
				'desc'     => __( 'Enable sandbox/test mode — routes all API calls to <code>api.stage.fungies.net</code>. Use staging API keys from the <a href="https://app.stage.fungies.net/devs/api-keys" target="_blank">Fungies Staging Dashboard</a>. Uncheck to use production keys from the <a href="https://app.fungies.io/devs/api-keys" target="_blank">Fungies Production Dashboard</a>.', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'sandbox_mode',
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_env_settings' ),

			array(
				'title' => __( 'Fungies API Keys', 'fungies-wp' ),
				'type'  => 'title',
				'desc'  => $is_sandbox
					? __( 'Enter your <strong>staging</strong> API keys from <a href="https://app.stage.fungies.net/devs/api-keys" target="_blank">Fungies Staging Dashboard → Developers → API Keys</a>.', 'fungies-wp' )
					: __( 'Enter your <strong>production</strong> API keys from <a href="https://app.fungies.io/devs/api-keys" target="_blank">Fungies Dashboard → Developers → API Keys</a>.', 'fungies-wp' ),
				'id'    => 'fungies_api_settings',
			),
			array(
				'title'    => __( 'Public Key', 'fungies-wp' ),
				'desc'     => __( 'Your Fungies public API key (starts with pub_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'public_key',
				'type'     => 'text',
				'css'      => 'min-width: 400px;',
			),
			array(
				'title'    => __( 'Secret Key', 'fungies-wp' ),
				'desc'     => __( 'Your Fungies secret API key (starts with sec_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'secret_key',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
			),
			array(
				'title'    => __( 'Webhook Secret', 'fungies-wp' ),
				'desc'     => __( 'Used to verify webhook signatures from Fungies', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'webhook_secret',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_api_settings' ),

			array(
				'title' => __( 'Checkout Settings', 'fungies-wp' ),
				'type'  => 'title',
				'id'    => 'fungies_checkout_settings',
			),
			array(
				'title'    => __( 'Checkout Mode', 'fungies-wp' ),
				'desc'     => __( 'How the Fungies checkout is presented to customers', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'checkout_mode',
				'type'     => 'select',
				'options'  => array(
					'overlay'  => __( 'Overlay (popup)', 'fungies-wp' ),
					'embedded' => __( 'Embedded (inline)', 'fungies-wp' ),
					'hosted'   => __( 'Hosted (redirect)', 'fungies-wp' ),
				),
				'default'  => 'overlay',
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_checkout_settings' ),
		);
	}

	public static function output_settings() {
		woocommerce_admin_fields( self::get_settings() );

		$is_sandbox  = self::get_option( 'sandbox_mode', 'no' ) === 'yes';
		$api_host    = $is_sandbox ? 'api.stage.fungies.net' : 'api.fungies.io';
		$webhook_url = rest_url( 'fungies/v1/webhook' );
		?>
		<h2><?php esc_html_e( 'Connection & Sync', 'fungies-wp' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active API Host', 'fungies-wp' ); ?></th>
				<td>
					<code><?php echo esc_html( $api_host ); ?></code>
					<?php if ( $is_sandbox ) : ?>
						<span style="color:#b26200;font-weight:bold;margin-left:8px;">⚠ SANDBOX</span>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Webhook URL', 'fungies-wp' ); ?></th>
				<td>
					<code><?php echo esc_url( $webhook_url ); ?></code>
					<p class="description">
						<?php esc_html_e( 'Paste this URL into your Fungies dashboard webhook settings.', 'fungies-wp' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Connection Test', 'fungies-wp' ); ?></th>
				<td>
					<button type="button" class="button" id="fungies-test-connection">
						<?php esc_html_e( 'Test Connection', 'fungies-wp' ); ?>
					</button>
					<span id="fungies-test-result" style="margin-left:10px;"></span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Product Sync', 'fungies-wp' ); ?></th>
				<td>
					<button type="button" class="button button-primary" id="fungies-sync-products">
						<?php esc_html_e( 'Sync Now', 'fungies-wp' ); ?>
					</button>
					<span id="fungies-sync-result" style="margin-left:10px;"></span>
					<?php
					$last_sync = get_option( 'fungies_last_sync', '' );
					if ( $last_sync ) {
						echo '<p class="description">';
						printf( esc_html__( 'Last sync: %s', 'fungies-wp' ), esc_html( $last_sync ) );
						echo '</p>';
					}
					?>
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_settings() {
		woocommerce_update_options( self::get_settings() );
	}

	public static function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}
		if ( ! isset( $_GET['tab'] ) || 'fungies' !== sanitize_text_field( wp_unslash( $_GET['tab'] ) ) ) {
			return;
		}

		wp_enqueue_style(
			'fungies-admin',
			FUNGIES_WP_PLUGIN_URL . 'assets/css/fungies-admin.css',
			array(),
			FUNGIES_WP_VERSION
		);

		wp_enqueue_script(
			'fungies-admin',
			FUNGIES_WP_PLUGIN_URL . 'assets/js/fungies-admin.js',
			array( 'jquery' ),
			FUNGIES_WP_VERSION,
			true
		);

		wp_localize_script( 'fungies-admin', 'fungiesAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'fungies_test_connection' ),
		) );
	}

	public static function ajax_test_connection() {
		check_ajax_referer( 'fungies_test_connection', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'fungies-wp' ) );
		}

		$client   = new Fungies_API_Client();
		$response = $client->get( '/offers/list' );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}

		$is_sandbox = self::get_option( 'sandbox_mode', 'no' ) === 'yes';
		$env        = $is_sandbox ? 'staging' : 'production';

		wp_send_json_success(
			sprintf( __( 'Connected to %s API!', 'fungies-wp' ), $env )
		);
	}

	public static function get_option( $key, $default = '' ) {
		return get_option( self::OPTION_PREFIX . $key, $default );
	}
}
