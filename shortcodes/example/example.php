<?php
/**
 * Minimal example component shortcode.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the example component.
 *
 * @return string
 */
function custom_dubai_render_example_shortcode() {
	return '<div class="cdts-example">' . esc_html__( 'Example shortcode component.', 'custom-dubai-tyre-shop' ) . '</div>';
}
add_shortcode( 'cdts_example', 'custom_dubai_render_example_shortcode' );
