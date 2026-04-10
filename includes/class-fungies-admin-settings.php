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
		return array(
			array(
				'title' => __( 'Environment', 'fungies-wp' ),
				'type'  => 'title',
				'id'    => 'fungies_env_settings',
			),
			array(
				'title'    => __( 'Sandbox Mode', 'fungies-wp' ),
				'desc'     => __( 'Enable sandbox/test mode — routes all API calls to <code>api.stage.fungies.net</code>', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'sandbox_mode',
				'type'     => 'checkbox',
				'default'  => 'no',
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_env_settings' ),

			array(
				'title' => __( 'Production API Keys', 'fungies-wp' ),
				'type'  => 'title',
				'desc'  => __( 'Enter your <strong>production</strong> keys from <a href="https://app.fungies.io/devs/api-keys" target="_blank">Fungies Dashboard → Developers → API Keys</a>.', 'fungies-wp' ),
				'id'    => 'fungies_prod_settings',
			),
			array(
				'title'    => __( 'Public Key', 'fungies-wp' ),
				'desc'     => __( 'Production public API key (starts with pub_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'public_key',
				'type'     => 'text',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'production' ),
			),
			array(
				'title'    => __( 'Secret Key', 'fungies-wp' ),
				'desc'     => __( 'Production secret API key (starts with sec_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'secret_key',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'production' ),
			),
			array(
				'title'    => __( 'Webhook Secret', 'fungies-wp' ),
				'desc'     => __( 'Production webhook signature secret', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'webhook_secret',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'production' ),
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_prod_settings' ),

			array(
				'title' => __( 'Staging API Keys', 'fungies-wp' ),
				'type'  => 'title',
				'desc'  => __( 'Enter your <strong>staging</strong> keys from <a href="https://app.stage.fungies.net/devs/api-keys" target="_blank">Fungies Staging Dashboard → Developers → API Keys</a>.', 'fungies-wp' ),
				'id'    => 'fungies_staging_settings',
			),
			array(
				'title'    => __( 'Staging Public Key', 'fungies-wp' ),
				'desc'     => __( 'Staging public API key (starts with pub_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'staging_public_key',
				'type'     => 'text',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'staging' ),
			),
			array(
				'title'    => __( 'Staging Secret Key', 'fungies-wp' ),
				'desc'     => __( 'Staging secret API key (starts with sec_)', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'staging_secret_key',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'staging' ),
			),
			array(
				'title'    => __( 'Staging Webhook Secret', 'fungies-wp' ),
				'desc'     => __( 'Staging webhook signature secret', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'staging_webhook_secret',
				'type'     => 'password',
				'css'      => 'min-width: 400px;',
				'custom_attributes' => array( 'data-env' => 'staging' ),
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_staging_settings' ),

			array(
				'title' => __( 'Checkout Settings', 'fungies-wp' ),
				'type'  => 'title',
				'id'    => 'fungies_checkout_settings',
			),
			array(
				'title'    => __( 'Checkout Mode', 'fungies-wp' ),
				'desc'     => __( 'Customers are redirected to the Fungies hosted checkout page to complete payment.', 'fungies-wp' ),
				'id'       => self::OPTION_PREFIX . 'checkout_mode',
				'type'     => 'select',
				'options'  => array(
					'hosted' => __( 'Hosted Checkout (redirect)', 'fungies-wp' ),
				),
				'default'  => 'hosted',
			),
			array(
				'title'       => __( 'Fungies Store URL', 'fungies-wp' ),
				'desc'        => __( 'Your Fungies store base URL. Find it in <strong>Fungies Dashboard → Go To Store</strong>.<br>Example: <code>https://yourname.app.fungies.io</code>', 'fungies-wp' ),
				'id'          => self::OPTION_PREFIX . 'store_url',
				'type'        => 'text',
				'css'         => 'min-width: 400px;',
				'placeholder' => 'https://yourname.app.fungies.io',
			),
			array( 'type' => 'sectionend', 'id' => 'fungies_checkout_settings' ),
		);
	}

	public static function output_settings() {
		woocommerce_admin_fields( self::get_settings() );

		$webhook_url = rest_url( 'fungies/v1/webhook' );
		?>
		<h2><?php esc_html_e( 'Connection & Sync', 'fungies-wp' ); ?></h2>
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active API Host', 'fungies-wp' ); ?></th>
				<td>
					<code id="fungies-active-host">api.fungies.io</code>
					<span id="fungies-sandbox-badge" style="color:#b26200;font-weight:bold;margin-left:8px;display:none;">⚠ SANDBOX</span>
					<span id="fungies-prod-badge" style="color:#2e7d32;font-weight:bold;margin-left:8px;">🟢 PRODUCTION</span>
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

		$is_sandbox = self::is_sandbox();
		$pub_key    = self::get_active_public_key();
		$sec_key    = self::get_active_secret_key();
		$env        = $is_sandbox ? 'staging' : 'production';
		$host       = $is_sandbox ? 'api.stage.fungies.net' : 'api.fungies.io';

		if ( empty( $pub_key ) ) {
			wp_send_json_error(
				sprintf( 'No %s public key saved. Enter your key and click Save Changes first.', $env )
			);
		}

		$client   = new Fungies_API_Client();
		$response = $client->get( '/offers/list' );

		if ( is_wp_error( $response ) ) {
			$key_preview = substr( $pub_key, 0, 8 ) . '...';
			wp_send_json_error(
				sprintf(
					'%s — Make sure you hit Save Changes before testing the connection. [%s → %s, key: %s]',
					$response->get_error_message(), $env, $host, $key_preview
				)
			);
		}

		wp_send_json_success(
			sprintf( __( 'Connected to %s API! (%s)', 'fungies-wp' ), $env, $host )
		);
	}

	public static function is_sandbox() {
		return self::get_option( 'sandbox_mode', 'no' ) === 'yes';
	}

	public static function get_active_public_key() {
		return self::is_sandbox()
			? self::get_option( 'staging_public_key' )
			: self::get_option( 'public_key' );
	}

	public static function get_active_secret_key() {
		return self::is_sandbox()
			? self::get_option( 'staging_secret_key' )
			: self::get_option( 'secret_key' );
	}

	public static function get_active_webhook_secret() {
		return self::is_sandbox()
			? self::get_option( 'staging_webhook_secret' )
			: self::get_option( 'webhook_secret' );
	}

	public static function get_option( $key, $default = '' ) {
		return get_option( self::OPTION_PREFIX . $key, $default );
	}
}
