<?php
/**
 * Filtered product grid endpoint.
 *
 * One endpoint serves the shop archive and the tyre-size pages, because both
 * filter the same tyre family; the context only decides which constraints are
 * mandatory (a size page always filters on size, the shop never does unless a
 * size is posted). Everything is returned as JSON: product payloads, facet
 * options with counts, and the pagination window.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_ajax_filter_grid' ) ) {
	/** Handle the tyre filter grid request. */
	function dts_ajax_filter_grid() {
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.

		$context   = isset( $input['context'] ) ? sanitize_key( $input['context'] ) : 'shop';
		$context   = in_array( $context, array( 'shop', 'tyre-size' ), true ) ? $context : 'shop';
		$page_type = isset( $input['page_type'] ) ? sanitize_key( $input['page_type'] ) : '';

		$per_page = isset( $input['per_page'] ) ? min( 60, max( 1, absint( $input['per_page'] ) ) ) : 20;
		$paged    = isset( $input['paged'] ) ? max( 1, absint( $input['paged'] ) ) : 1;

		$front = dts_get_size_request( $input, 'tyre_' );
		$rear  = dts_get_size_request( $input, 'tyre_', '_r' );

		$params = array(
			'brand'           => isset( $input['filter_brand'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_brand'] ) : array(),
			'year'            => isset( $input['filter_year'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_year'] ) : array(),
			'run_flat'        => isset( $input['filter_run_flat'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_run_flat'] ) : array(),
			'promo'           => isset( $input['filter_promo'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_promo'] ) : array(),
			'width'           => isset( $input['filter_width'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_width'] ) : array(),
			'height'          => isset( $input['filter_height'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_height'] ) : array(),
			'rim'             => isset( $input['filter_rim'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_rim'] ) : array(),
			'tyre_type'       => isset( $input['filter_tyre_type'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_tyre_type'] ) : array(),
			'vehicle_type'    => isset( $input['filter_vehicle_type'] ) ? array_map( 'sanitize_text_field', (array) $input['filter_vehicle_type'] ) : array(),
			'tier'            => isset( $input['filter_tier'] ) ? array_map( 'sanitize_key', (array) $input['filter_tier'] ) : array(),
			'search'          => isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '',
			'orderby'         => isset( $input['orderby'] ) ? dts_normalize_orderby( sanitize_text_field( $input['orderby'] ) ) : 'menu_order',
			'price_min'       => isset( $input['price_min'] ) ? (float) $input['price_min'] : 0,
			'price_max'       => isset( $input['price_max'] ) ? (float) $input['price_max'] : 0,
			'price_active'    => isset( $input['price_filter_active'] ) && '1' === (string) $input['price_filter_active'],
			'product_cat'     => isset( $input['product_cat'] ) ? sanitize_text_field( $input['product_cat'] ) : '',
			'brand_page_slug' => isset( $input['brand_page_slug'] ) ? sanitize_text_field( $input['brand_page_slug'] ) : '',
			'offers_only'     => 'offers' === $page_type,
		);

		// A rear size means the visitor wants staggered sets, which are paired
		// rather than listed individually.
		$is_double = '' !== $rear['width'];

		if ( $is_double ) {
			wp_send_json_success( dts_ajax_filter_grid_double( $front, $rear, $params, $paged, $per_page ) );
		}

		// A size page always constrains size; the shop constrains only when one
		// is posted.
		if ( 'tyre-size' === $context || '' !== $front['width'] ) {
			$params['size'] = $front;
		}

		$search_size = '' !== $params['search'] ? dts_parse_tyre_size_string( $params['search'] ) : array();
		if ( ! empty( $search_size['width'] ) ) {
			$params['size'] = $search_size;
		}
		$args = dts_build_tyre_filter_args( array_merge( $params, array( 'paged' => $paged, 'per_page' => $per_page ) ) );
		if ( '' !== $params['search'] && empty( $search_size['width'] ) ) {
			$args['s'] = $params['search'];
		}
		$query     = new WP_Query( $args );
		$ids       = wp_list_pluck( $query->posts, 'ID' );
		$window    = dts_paginate( (int) $query->found_posts, $paged, $per_page, $ids );
		$facets    = dts_get_tyre_facets( $args, $params['orderby'], 'offers' !== $page_type );

		wp_send_json_success(
			array(
				'mode'       => 'single',
				'context'    => $context,
				'products'   => dts_get_products_payload( $window['items'] ),
				'facets'     => $facets,
				'selection'  => array(
					'brand'    => $params['brand'],
					'year'     => $params['year'],
					'run_flat' => dts_normalize_run_flat_filter( $params['run_flat'] ),
					'promo'    => $params['promo'],
					'price'    => array(
						'min'    => $params['price_min'],
						'max'    => $params['price_max'],
						'active' => (bool) $params['price_active'],
					),
					'orderby'  => $params['orderby'],
				),
				'size'       => isset( $params['size'] ) ? $params['size'] : array(),
				'count'      => (int) $query->found_posts,
				'paged'      => $window['paged'],
				'total_pages' => $window['total_pages'],
				'per_page'   => $per_page,
				'from'       => $window['from'],
				'to'         => $window['to'],
			)
		);
	}
}
add_action( 'wp_ajax_dts_filter_grid', 'dts_ajax_filter_grid' );
add_action( 'wp_ajax_nopriv_dts_filter_grid', 'dts_ajax_filter_grid' );

if ( ! function_exists( 'dts_ajax_filter_grid_double' ) ) {
	/**
	 * Staggered results: matched front/rear sets plus pair facets.
	 *
	 * @param array $front    Front size.
	 * @param array $rear     Rear size.
	 * @param array $params   Facet parameters.
	 * @param int   $paged    Requested page.
	 * @param int   $per_page Items per page.
	 * @return array
	 */
	function dts_ajax_filter_grid_double( $front, $rear, $params, $paged, $per_page ) {
		$result = dts_query_tyre_pairs(
			$front,
			$rear,
			array(
				'brand'       => $params['brand'],
				'year'        => $params['year'],
				'run_flat'    => $params['run_flat'],
				'promo'       => $params['promo'],
				'product_cat' => $params['product_cat'],
			)
		);

		$pairs  = $result['pairs'];
		$total  = count( $pairs );
		$window = dts_paginate( $total, $paged, $per_page, $pairs );

		$payloads = array();

		foreach ( $window['items'] as $pair ) {
			$payloads[] = dts_get_pair_payload( $pair );
		}

		// A set price is four tyres, so the bounds are the single-tyre bounds
		// scaled by four.
		$facets            = dts_get_pair_facets( $pairs );
		$facets['price']   = array(
			'min' => round( $result['price_min'] * 4, 0 ),
			'max' => round( $result['price_max'] * 4, 0 ),
		);

		return array(
			'mode'      => 'double',
			'products'  => $payloads,
			'facets'    => $facets,
			'selection' => array(
				'brand'    => $params['brand'],
				'year'     => $params['year'],
				'run_flat' => dts_normalize_run_flat_filter( $params['run_flat'] ),
				'promo'    => $params['promo'],
				'orderby'  => $params['orderby'],
			),
			'size'      => array(
				'front' => $front,
				'rear'  => $rear,
			),
			'sizes'     => array(
				'front_label' => dts_format_size_parts( $front['width'], $front['height'], $front['rim'] ),
				'rear_label'  => dts_format_size_parts( $rear['width'], $rear['height'], $rear['rim'] ),
				'front_url'   => dts_tyre_size_url( $front ),
				'staggered_url' => dts_tyre_size_url( $front, $rear ),
			),
			'count'     => $total,
			'paged'     => $window['paged'],
			'total_pages' => $window['total_pages'],
			'per_page'  => $per_page,
			'from'      => $window['from'],
			'to'        => $window['to'],
		);
	}
}
