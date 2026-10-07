<?php
/** Homepage car services section mount point. */
defined( 'ABSPATH' ) || exit;

function custom_dubai_services_section_shortcode() {
	return '<div id="dts-services-root"></div>';
}
add_shortcode( 'custom_dubai_services_section', 'custom_dubai_services_section_shortcode' );
