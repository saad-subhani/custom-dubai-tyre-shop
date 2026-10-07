<?php
/**
 * Vehicle lookup endpoints.
 *
 * The makes, models, years, engines and fitments all come from Wheel-Size,
 * reached through the cached gateway so repeated lookups cost nothing. Makes are
 * served from the local brand map whenever it is warm, which keeps the most
 * common request off the API entirely.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_ws_verify_request' ) ) {
	/**
	 * Guard an API-backed endpoint.
	 *
	 * Rate limiting is checked first so a flood never reaches nonce validation,
	 * and an unavailable gateway reports 503 so the client can distinguish
	 * "no data" from "try again later".
	 */
	function dts_ws_verify_request( $bucket = 'vehicle' ) {
		if ( dts_ws_rate_limited( $bucket ) ) {
			wp_send_json_error( array( 'message' => __( 'Too many requests', 'custom-dubai-tyre-shop' ) ), 429 );
		}

		check_ajax_referer( 'dts_ws_ajax', 'nonce' );
	}
}

if ( ! function_exists( 'dts_ws_send_or_unavailable' ) ) {
	/**
	 * Send a gateway response, or report the service as unavailable.
	 *
	 * @param array|null $data Response from dts_ws_api_get().
	 */
	function dts_ws_send_or_unavailable( $data ) {
		if ( null === $data ) {
			wp_send_json_error(
				array( 'message' => __( 'Vehicle data temporarily unavailable', 'custom-dubai-tyre-shop' ) ),
				503
			);
		}

		wp_send_json_success( $data );
	}
}

if ( ! function_exists( 'dts_ajax_get_car_makes' ) ) {
	/** Vehicle makes, from the local brand map when possible. */
	function dts_ajax_get_car_makes() {
		dts_ws_verify_request( 'vehicle' );

		$map = dts_get_brand_map();

		if ( empty( $map ) ) {
			// Cold start: one cached API call fills the map.
			dts_ws_send_or_unavailable( dts_ws_api_get( 'makes/', array( 'ordering' => 'slug', 'region' => 'medm' ), DAY_IN_SECONDS ) );
		}

		$data = array();

		foreach ( $map as $slug => $info ) {
			$data[] = array(
				'slug' => $slug,
				'name' => $info['name'],
				'logo' => isset( $info['logo'] ) ? $info['logo'] : '',
			);
		}

		wp_send_json_success( array( 'data' => $data ) );
	}
}
add_action( 'wp_ajax_dts_get_car_makes', 'dts_ajax_get_car_makes' );
add_action( 'wp_ajax_nopriv_dts_get_car_makes', 'dts_ajax_get_car_makes' );

if ( ! function_exists( 'dts_ajax_get_car_models' ) ) {
	/** Models for a make. */
	function dts_ajax_get_car_models() {
		dts_ws_verify_request( 'vehicle' );

		$make = isset( $_POST['make'] ) ? sanitize_title( wp_unslash( $_POST['make'] ) ) : '';

		if ( '' === $make ) {
			wp_send_json_error( array( 'message' => __( 'Missing make', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable( dts_ws_api_get( 'models/', array( 'make' => $make ), DAY_IN_SECONDS ) );
	}
}
add_action( 'wp_ajax_dts_get_car_models', 'dts_ajax_get_car_models' );
add_action( 'wp_ajax_nopriv_dts_get_car_models', 'dts_ajax_get_car_models' );

if ( ! function_exists( 'dts_ajax_get_car_years' ) ) {
	/** Years for a make and model. */
	function dts_ajax_get_car_years() {
		dts_ws_verify_request( 'vehicle' );

		$make  = isset( $_POST['make'] ) ? sanitize_title( wp_unslash( $_POST['make'] ) ) : '';
		$model = isset( $_POST['model'] ) ? sanitize_title( wp_unslash( $_POST['model'] ) ) : '';

		if ( '' === $make || '' === $model ) {
			wp_send_json_error( array( 'message' => __( 'Missing make or model', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable(
			dts_ws_api_get( 'years/', array( 'make' => $make, 'model' => $model ), DAY_IN_SECONDS )
		);
	}
}
add_action( 'wp_ajax_dts_get_car_years', 'dts_ajax_get_car_years' );
add_action( 'wp_ajax_nopriv_dts_get_car_years', 'dts_ajax_get_car_years' );

if ( ! function_exists( 'dts_ajax_get_car_engines' ) ) {
	/** Engine modifications for a make, model and year. */
	function dts_ajax_get_car_engines() {
		dts_ws_verify_request( 'vehicle' );

		$make  = isset( $_POST['make'] ) ? sanitize_title( wp_unslash( $_POST['make'] ) ) : '';
		$model = isset( $_POST['model'] ) ? sanitize_title( wp_unslash( $_POST['model'] ) ) : '';
		$year  = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';

		if ( '' === $make || '' === $model || '' === $year ) {
			wp_send_json_error( array( 'message' => __( 'Missing make, model or year', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable(
			dts_ws_api_get(
				'modifications/',
				array(
					'make'  => $make,
					'model' => $model,
					'year'  => $year,
				),
				DAY_IN_SECONDS
			)
		);
	}
}
add_action( 'wp_ajax_dts_get_car_engines', 'dts_ajax_get_car_engines' );
add_action( 'wp_ajax_nopriv_dts_get_car_engines', 'dts_ajax_get_car_engines' );

if ( ! function_exists( 'dts_ajax_get_car_fitments' ) ) {
	/** Tyre sizes for a make, model, year and engine. */
	function dts_ajax_get_car_fitments() {
		dts_ws_verify_request( 'vehicle' );

		$make   = isset( $_POST['make'] ) ? sanitize_title( wp_unslash( $_POST['make'] ) ) : '';
		$model  = isset( $_POST['model'] ) ? sanitize_title( wp_unslash( $_POST['model'] ) ) : '';
		$year   = isset( $_POST['year'] ) ? sanitize_text_field( wp_unslash( $_POST['year'] ) ) : '';
		$engine = isset( $_POST['engine'] ) ? sanitize_title( wp_unslash( $_POST['engine'] ) ) : '';

		if ( '' === $make || '' === $model || '' === $year || '' === $engine ) {
			wp_send_json_error( array( 'message' => __( 'Missing vehicle selection', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		dts_ws_send_or_unavailable(
			dts_ws_api_get(
				'search/by_model/',
				array(
					'make'         => $make,
					'model'        => $model,
					'year'         => $year,
					'modification' => $engine,
				),
				7 * DAY_IN_SECONDS
			)
		);
	}
}
add_action( 'wp_ajax_dts_get_car_fitments', 'dts_ajax_get_car_fitments' );
add_action( 'wp_ajax_nopriv_dts_get_car_fitments', 'dts_ajax_get_car_fitments' );

if ( ! function_exists( 'dts_get_vehicle_fitment_urls' ) ) {
	/**
	 * Turn Wheel-Size fitment rows into size links for the size lookup.
	 *
	 * @param array $fitments Response from the by_model endpoint.
	 * @return array List of size descriptors.
	 */
	function dts_get_vehicle_fitment_urls( $fitments ) {
		$sizes = array();

		foreach ( (array) $fitments as $fitment ) {
			// The API nests the sizes one level down under 'data'.
			$rows = isset( $fitment['data'] ) && is_array( $fitment['data'] ) ? $fitment['data'] : array();

			if ( empty( $rows ) && isset( $fitment['tire_size'] ) ) {
				$rows = array( $fitment );
			}

			foreach ( $rows as $row ) {
				$label = '';

				foreach ( array( 'tire_size', 'tireSize', 'size', 'name' ) as $key ) {
					if ( ! empty( $row[ $key ] ) ) {
						$label = (string) $row[ $key ];
						break;
					}
				}

				if ( '' === $label ) {
					continue;
				}

				$parts = dts_parse_tyre_size_string( $label );

				if ( ! $parts ) {
					$sizes[] = array(
						'label' => $label,
						'valid' => false,
					);
					continue;
				}

				$sizes[] = array(
					'label'  => dts_format_tyre_size( $parts ),
					'raw'    => $label,
					'valid'  => true,
					'slug'   => dts_tyre_size_slug( $parts ),
					'url'    => dts_tyre_size_url( $parts ),
					'size'   => $parts,
				);
			}
		}

		// The same fitment is often listed more than once across trims.
		$unique = array();

		foreach ( $sizes as $size ) {
			$key = $size['valid'] ? $size['slug'] : $size['label'];

			if ( ! isset( $unique[ $key ] ) ) {
				$unique[ $key ] = $size;
			}
		}

		return array_values( $unique );
	}
}

if ( ! function_exists( 'dts_get_ws_ajax_nonce' ) ) {
	/** Nonce for the API-backed endpoints. */
	function dts_get_ws_ajax_nonce() {
		return wp_create_nonce( 'dts_ws_ajax' );
	}
}
