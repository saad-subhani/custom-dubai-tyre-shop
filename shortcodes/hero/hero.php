<?php
/**
 * React hero shortcode mount point.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the hero component mount point.
 *
 * @return string
 */
function custom_dubai_render_hero_shortcode() {
	return '<div class="custom-dubai-hero-root"></div>'
		. do_shortcode( '[custom_dubai_tyre_modal]' )
		. do_shortcode( '[custom_dubai_vehicle_modal]' );
}
add_shortcode( 'custom_dubai_hero', 'custom_dubai_render_hero_shortcode' );
