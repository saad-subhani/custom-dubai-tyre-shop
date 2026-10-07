<?php
/**
 * Filtered battery grid endpoint.
 *
 * Mirrors the tyre grid endpoint but with battery facets: brand, country of
 * manufacture, capacity, reserve capacity, CCA and case dimensions. The price
 * slider is bounded by absolute catalog bounds rather than by the current
 * result, so narrowing the filter never shrinks the slider.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_ajax_battery_filter_grid' ) ) {
	/** Handle the battery filter grid request. */
	function dts_ajax_battery_filter_grid() {
		try {
			$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.

			$paged    = isset( $input['paged'] ) ? max( 1, absint( $input['paged'] ) ) : 1;
			$per_page = isset( $input['per_page'] ) ? min( 60, max( 1, absint( $input['per_page'] ) ) ) : 20;

			list( $abs_min, $abs_max ) = dts_get_battery_absolute_price_bounds();

			$list = function ( $key ) use ( $input ) {
				return isset( $input[ $key ] ) ? array_values( array_filter( array_map( 'sanitize_text_field', (array) $input[ $key ] ), 'strlen' ) ) : array();
			};

			$params = array(
				'brand'         => $list( 'filter_brand' ),
				'origin'        => $list( 'filter_origin' ),
				'capacity'      => $list( 'filter_capacity' ),
				'reserve'       => $list( 'filter_reserve' ),
				'cca'           => $list( 'filter_cca' ),
				'length'        => $list( 'filter_length' ),
				'width'         => $list( 'filter_width' ),
				'height'        => $list( 'filter_height' ),
				'on_sale'       => ! empty( $input['on_sale'] ),
				'price_min'     => isset( $input['price_min'] ) ? (float) $input['price_min'] : 0,
				'price_max'     => isset( $input['price_max'] ) ? (float) $input['price_max'] : 0,
				'price_active'  => ! empty( $input['price_filter_active'] ),
				'abs_price_min' => $abs_min,
				'abs_price_max' => $abs_max,
				'orderby'       => isset( $input['orderby'] ) ? dts_normalize_orderby( sanitize_text_field( $input['orderby'] ) ) : 'menu_order',
				'product_cat'   => isset( $input['product_cat'] ) ? sanitize_text_field( $input['product_cat'] ) : '',
				'search'        => isset( $input['s'] ) ? sanitize_text_field( $input['s'] ) : '',
			);

			$args   = dts_build_battery_filter_args( array_merge( $params, array( 'paged' => $paged, 'per_page' => $per_page ) ) );
			$query  = new WP_Query( $args );
			$ids    = wp_list_pluck( $query->posts, 'ID' );
			$window = dts_paginate( (int) $query->found_posts, $paged, $per_page, $ids );

			wp_send_json_success(
				array(
					'mode'      => 'battery',
					'products'  => dts_get_products_payload( $window['items'] ),
					'facets'    => dts_get_battery_facets( $args, $params['orderby'] ),
					'selection' => array(
						'brand'    => $params['brand'],
						'origin'   => $params['origin'],
						'capacity' => $params['capacity'],
						'reserve'  => $params['reserve'],
						'cca'      => $params['cca'],
						'length'   => $params['length'],
						'width'    => $params['width'],
						'height'   => $params['height'],
						'on_sale'  => $params['on_sale'],
						'price'    => array(
							'min'    => $params['price_min'],
							'max'    => $params['price_max'],
							'active' => (bool) $params['price_active'],
						),
						'orderby'  => $params['orderby'],
						'search'   => $params['search'],
					),
					'count'     => (int) $query->found_posts,
					'paged'     => $window['paged'],
					'total_pages' => $window['total_pages'],
					'per_page'  => $per_page,
					'from'      => $window['from'],
					'to'        => $window['to'],
				)
			);
		} catch ( \Throwable $e ) {
			dts_log_endpoint_error( 'battery-filter-grid', $e );

			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
				500
			);
		}
	}
}
add_action( 'wp_ajax_dts_battery_filter_grid', 'dts_ajax_battery_filter_grid' );
add_action( 'wp_ajax_nopriv_dts_battery_filter_grid', 'dts_ajax_battery_filter_grid' );

if ( ! function_exists( 'dts_log_endpoint_error' ) ) {
	/**
	 * Log an endpoint failure without exposing internals to the client.
	 *
	 * @param string    $endpoint Endpoint name.
	 * @param \Throwable $error   Thrown error.
	 */
	function dts_log_endpoint_error( $endpoint, $error ) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'DTS %s error: %s in %s:%d',
				$endpoint,
				$error->getMessage(),
				$error->getFile(),
				$error->getLine()
			)
		);
	}
}
