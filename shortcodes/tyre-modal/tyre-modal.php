<?php
/**
 * Tyre size modal shortcode and HTML template.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function custom_dubai_render_tyre_modal_shortcode() {
	$template = get_theme_file_path( '/shortcodes/tyre-modal/tyre-modal.html' );
	return is_readable( $template ) ? (string) file_get_contents( $template ) : '';
}
add_shortcode( 'custom_dubai_tyre_modal', 'custom_dubai_render_tyre_modal_shortcode' );
