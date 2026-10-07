<?php
/**
 * Flotation (high flotation) sizing endpoints.
 *
 * Flotation tyres are sized by overall diameter, section width and rim rather
 * than by an aspect ratio, and the valid values come from Wheel-Size rather
 * than from the store's own data.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_ajax_get_flotation_diameters' ) ) {
	/** Overall diameter prefixes, e.g. 29x, 33x. */
	function dts_ajax_get_flotation_diameters() {
		dts_ws_verify_request( 'flotation' );

		dts_ws_send_or_unavailable( dts_ws_api_get( 'by_hf_tire/od/', array(), 7 * DAY_IN_SECONDS ) );
	}
}
add_action( 'wp_ajax_dts_get_flotation_diameters', 'dts_ajax_get_flotation_diameters' );
add_action( 'wp_ajax_nopriv_dts_get_flotation_diameters', 'dts_ajax_get_flotation_diameters' );

if ( ! function_exists( 'dts_ajax_get_flotation_widths' ) ) {
	/** Section widths available for an overall diameter. */
	function dts_ajax_get_flotation_widths() {
		dts_ws_verify_request( 'flotation' );

		$diameter = isset( $_POST['diameter'] ) ? sanitize_text_field( wp_unslash( $_POST['diameter'] ) ) : '';

		if ( '' === $diameter ) {
			wp_send_json_error( array( 'message' => __( 'Missing overall diameter', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable(
			dts_ws_api_get( 'by_hf_tire/sw/', array( 'hf_overallDiameters' => $diameter ), 7 * DAY_IN_SECONDS )
		);
	}
}
add_action( 'wp_ajax_dts_get_flotation_widths', 'dts_ajax_get_flotation_widths' );
add_action( 'wp_ajax_nopriv_dts_get_flotation_widths', 'dts_ajax_get_flotation_widths' );

if ( ! function_exists( 'dts_ajax_get_flotation_rims' ) ) {
	/** Rims available for an overall diameter and section width. */
	function dts_ajax_get_flotation_rims() {
		dts_ws_verify_request( 'flotation' );

		$diameter = isset( $_POST['diameter'] ) ? sanitize_text_field( wp_unslash( $_POST['diameter'] ) ) : '';
		$width    = isset( $_POST['width'] ) ? sanitize_text_field( wp_unslash( $_POST['width'] ) ) : '';

		if ( '' === $diameter || '' === $width ) {
			wp_send_json_error( array( 'message' => __( 'Missing overall diameter or section width', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable(
			dts_ws_api_get(
				'by_hf_tire/rd/',
				array(
					'hf_overallDiameters' => $diameter,
					'hf_sectionWidths'    => $width,
				),
				7 * DAY_IN_SECONDS
			)
		);
	}
}
add_action( 'wp_ajax_dts_get_flotation_rims', 'dts_ajax_get_flotation_rims' );
add_action( 'wp_ajax_nopriv_dts_get_flotation_rims', 'dts_ajax_get_flotation_rims' );
