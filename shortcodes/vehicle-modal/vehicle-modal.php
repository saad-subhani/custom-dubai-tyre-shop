<?php
/**
 * Vehicle selection modal shortcode and HTML template.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function custom_dubai_render_vehicle_modal_shortcode() {
	$template = get_theme_file_path( '/shortcodes/vehicle-modal/vehicle-modal.html' );
	return is_readable( $template ) ? (string) file_get_contents( $template ) : '';
}
add_shortcode( 'custom_dubai_vehicle_modal', 'custom_dubai_render_vehicle_modal_shortcode' );
