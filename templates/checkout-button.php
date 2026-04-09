<?php
/**
 * Fungies checkout button template override.
 * Replaces the default WC place order button when Fungies gateway is active.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$mode = Fungies_Admin_Settings::get_option( 'checkout_mode', 'overlay' );
$label = ( 'hosted' === $mode )
	? __( 'Proceed to Fungies Checkout', 'fungies-wp' )
	: __( 'Pay with Fungies', 'fungies-wp' );
?>
<button type="submit" class="button alt" id="place_order" data-value="<?php echo esc_attr( $label ); ?>">
	<?php echo esc_html( $label ); ?>
</button>
