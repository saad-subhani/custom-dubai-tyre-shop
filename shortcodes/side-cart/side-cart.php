<?php
/**
 * Side cart shortcode mount point.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the side cart component.
 *
 * @return string
 */
function custom_dubai_render_side_cart_shortcode() {
	ob_start();
	?>
	<div id="custom-dubai-side-cart" class="custom-dubai-side-cart-root"></div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_dubai_side_cart', 'custom_dubai_render_side_cart_shortcode' );
