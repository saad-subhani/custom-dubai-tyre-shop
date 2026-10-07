<?php
/**
 * Shared product query service.
 *
 * Builds the meta/tax queries, facet counts and price bounds for tyre and
 * battery filtering, and resolves staggered pairs. Every consumer — archive
 * pages and the JSON endpoints alike — goes through here, so a page and its
 * first filter interaction always return identical results.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

/* ------------------------------------------------------------------------- */
/* Filter primitives                                                          */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_build_buy3get1_meta_query' ) ) {
	/**
	 * Meta query for the Buy 3 Get 1 filter.
	 *
	 * Selecting both options, or neither, adds no constraint. "no" must also
	 * match products where the key was never written, so it uses a NOT EXISTS
	 * OR "!yes" pair.
	 *
	 * @param string[] $values Selected values, each 'yes' or 'no'.
	 * @return array Meta query fragment, or an empty array for no constraint.
	 */
	function dts_build_buy3get1_meta_query( $values ) {
		if ( empty( $values ) || 2 === count( $values ) ) {
			return array();
		}

		$has_yes = in_array( 'yes', $values, true );
		$has_no  = in_array( 'no', $values, true );

		if ( $has_yes && ! $has_no ) {
			return array(
				'key'     => 'buy_3_get_1',
				'value'   => 'yes',
				'compare' => '=',
			);
		}

		if ( $has_no && ! $has_yes ) {
			return array(
				'relation' => 'OR',
				array(
					'key'     => 'buy_3_get_1',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => 'buy_3_get_1',
					'value'   => 'yes',
					'compare' => '!=',
				),
			);
		}

		return array();
	}
}

if ( ! function_exists( 'dts_is_run_flat_value' ) ) {
	/** Whether a stored run-flat value means "yes". */
	function dts_is_run_flat_value( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'y' ), true );
	}
}

if ( ! function_exists( 'dts_product_is_run_flat' ) ) {
	/**
	 * Whether a product is marked run flat.
	 *
	 * @param int|WC_Product $product Product object or ID.
	 */
	function dts_product_is_run_flat( $product ) {
		$product_id = $product instanceof WC_Product ? $product->get_id() : absint( $product );

		if ( ! $product_id ) {
			return false;
		}

		return dts_is_run_flat_value( get_post_meta( $product_id, 'tyre_run_flat', true ) );
	}
}

if ( ! function_exists( 'dts_normalize_run_flat_filter' ) ) {
	/**
	 * Map the UI's yes/no labels onto the stored 1/0 convention.
	 *
	 * @param string[] $values Raw filter values.
	 * @return string[]
	 */
	function dts_normalize_run_flat_filter( $values ) {
		return array_values(
			array_filter(
				array_map(
					function ( $value ) {
						return 'yes' === $value ? '1' : ( 'no' === $value ? '0' : $value );
					},
					(array) $values
				),
				'strlen'
			)
		);
	}
}

if ( ! function_exists( 'dts_remove_meta_key_from_query' ) ) {
	/**
	 * Recursively strip a meta key from a meta query.
	 *
	 * Facet counts need the same query minus one constraint, so selecting a
	 * brand never zeroes out the other brands.
	 *
	 * @param array  $meta_query Meta query.
	 * @param string $target_key Meta key to remove.
	 * @return array
	 */
	function dts_remove_meta_key_from_query( $meta_query, $target_key ) {
		if ( ! is_array( $meta_query ) ) {
			return $meta_query;
		}

		$result   = array();
		$relation = null;

		foreach ( $meta_query as $key => $value ) {
			if ( 'relation' === $key ) {
				$relation = $value;
				continue;
			}

			if ( is_array( $value ) ) {
				if ( isset( $value['key'] ) && $value['key'] === $target_key ) {
					continue;
				}

				if ( ! isset( $value['key'] ) ) {
					$sub = dts_remove_meta_key_from_query( $value, $target_key );

					if ( ! empty( $sub ) ) {
						$result[] = $sub;
					}

					continue;
				}
			}

			$result[] = $value;
		}

		if ( empty( $result ) ) {
			return array();
		}

		$clean = array_values( $result );

		if ( count( $clean ) > 1 && $relation ) {
			$clean['relation'] = $relation;
		}

		return $clean;
	}
}

if ( ! function_exists( 'dts_remove_taxonomies_from_query' ) ) {
	/**
	 * Strip taxonomies from a tax query.
	 *
	 * @param array    $tax_query  Tax query.
	 * @param string[] $taxonomies Taxonomy names to remove.
	 * @return array
	 */
	function dts_remove_taxonomies_from_query( $tax_query, $taxonomies ) {
		if ( ! is_array( $tax_query ) ) {
			return array();
		}

		$clean = array_values(
			array_filter(
				$tax_query,
				function ( $clause ) use ( $taxonomies ) {
					return ! ( is_array( $clause ) && isset( $clause['taxonomy'] ) && in_array( $clause['taxonomy'], (array) $taxonomies, true ) );
				}
			)
		);

		return $clean;
	}
}

if ( ! function_exists( 'dts_facet_query_args' ) ) {
	/**
	 * Clone query args into an unpaginated ID-only query for facet counting.
	 *
	 * @param array    $args            Base query args.
	 * @param string[] $strip_meta_keys Meta keys to drop.
	 * @param string[] $strip_tax       Taxonomy names to drop.
	 * @param string   $orderby         Active sort, so any meta_key stays consistent.
	 * @return array
	 */
	function dts_facet_query_args( $args, $strip_meta_keys = array(), $strip_tax = array(), $orderby = '' ) {
		$facet = $args;

		$facet['posts_per_page'] = -1;
		$facet['fields']         = 'ids';
		$facet['no_found_rows']  = true;

		unset( $facet['paged'], $facet['offset'], $facet['orderby'], $facet['order'] );

		if ( ! in_array( $orderby, array( 'price', 'price-desc' ), true ) ) {
			unset( $facet['meta_key'] );
		}

		foreach ( (array) $strip_meta_keys as $meta_key ) {
			if ( isset( $facet['meta_query'] ) ) {
				$facet['meta_query'] = dts_remove_meta_key_from_query( $facet['meta_query'], $meta_key );
			}
		}

		if ( empty( $facet['meta_query'] ) ) {
			unset( $facet['meta_query'] );
		}

		if ( ! empty( $strip_tax ) && isset( $facet['tax_query'] ) ) {
			$facet['tax_query'] = dts_remove_taxonomies_from_query( $facet['tax_query'], $strip_tax );

			if ( empty( $facet['tax_query'] ) ) {
				unset( $facet['tax_query'] );
			}
		}

		return $facet;
	}
}

if ( ! function_exists( 'dts_get_ids' ) ) {
	/**
	 * Run a query and return only the IDs.
	 *
	 * @param array $args WP_Query args.
	 * @return int[]
	 */
	function dts_get_ids( $args ) {
		$args['post_type']   = 'product';
		$args['post_status'] = 'publish';
		$args['fields']      = 'ids';

		if ( ! isset( $args['posts_per_page'] ) ) {
			$args['posts_per_page'] = -1;
		}

		if ( ! empty( $args['paged'] ) ) {
			unset( $args['paged'] );
		}

		$query = new WP_Query( $args );

		return array_map( 'absint', (array) $query->posts );
	}
}

if ( ! function_exists( 'dts_get_price_bounds' ) ) {
	/**
	 * Price bounds across a set of product IDs, ignoring empty values.
	 *
	 * @param int[] $ids         Product IDs.
	 * @param float $default_min Fallback minimum.
	 * @param float $default_max Fallback maximum.
	 * @return array{0: float, 1: float}
	 */
	function dts_get_price_bounds( $ids, $default_min = 0, $default_max = 5000 ) {
		$ids = array_filter( array_map( 'absint', (array) $ids ) );

		if ( empty( $ids ) ) {
			return array( (float) $default_min, (float) $default_max );
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT FLOOR(MIN(CAST(meta_value AS DECIMAL(10,2)))) AS min_price,
				        CEIL(MAX(CAST(meta_value AS DECIMAL(10,2)))) AS max_price
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = '_price'
				   AND meta_value != ''
				   AND meta_value IS NOT NULL
				   AND post_id IN ( $placeholders )",
				$ids
			)
		);

		$min = ( $row && null !== $row->min_price ) ? (float) $row->min_price : (float) $default_min;
		$max = ( $row && null !== $row->max_price ) ? (float) $row->max_price : (float) $default_max;

		return array( $min, $max );
	}
}

if ( ! function_exists( 'dts_apply_price_filter' ) ) {
	/**
	 * Add a price range constraint to a meta query.
	 *
	 * @param array  $meta_query Meta query to extend.
	 * @param float  $min        Lower bound.
	 * @param float  $max        Upper bound.
	 * @param string $mode       'bounds' for two independent limits, 'between' for a span.
	 * @return array
	 */
	function dts_apply_price_filter( $meta_query, $min, $max, $mode = 'bounds' ) {
		if ( 'between' === $mode ) {
			if ( $min > 0 && $max > 0 ) {
				$meta_query[] = array(
					'key'     => '_price',
					'value'   => array( $min, $max ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				);
			}

			return $meta_query;
		}

		if ( $min > 0 ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $min,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}

		if ( $max > 0 ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $max,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}

		return $meta_query;
	}
}

if ( ! function_exists( 'dts_get_size_request' ) ) {
	/**
	 * Read one side of a size request from a request array.
	 *
	 * @param array  $source    Request data.
	 * @param string $prefix    Optional key prefix, e.g. 'tyre_'.
	 * @param string $suffix    Optional key suffix, e.g. '_r' for the rear size.
	 * @return array{width: string, height: string, rim: string, flotation: bool}
	 */
	function dts_get_size_request( $source, $prefix = '', $suffix = '' ) {
		$source = (array) $source;

		$get = function ( $key ) use ( $source, $prefix, $suffix ) {
			$key = $prefix . $key . $suffix;

			return isset( $source[ $key ] ) ? sanitize_text_field( (string) $source[ $key ] ) : '';
		};

		$height = $get( 'height' );

		// No-profile sizes arrive blank or as '0'; normalise so parsing works.
		if ( '' === trim( $height ) ) {
			$height = '0';
		}

		$flotation_key = $prefix . 'float' . $suffix;

		return array(
			'width'     => $get( 'width' ),
			'height'    => $height,
			'rim'       => $get( 'rim' ),
			'flotation' => isset( $source[ $flotation_key ] ) && '1' === (string) $source[ $flotation_key ],
		);
	}
}

if ( ! function_exists( 'dts_size_meta_query' ) ) {
	/**
	 * Meta query for one side of a size request.
	 *
	 * @param array $size Output of dts_get_size_request().
	 */
	function dts_size_meta_query( $size ) {
		return dts_tyre_size_meta_query(
			$size['width'],
			$size['height'],
			$size['rim'],
			! empty( $size['flotation'] ),
			dts_tyre_size_is_no_profile( $size['height'] )
		);
	}
}

if ( ! function_exists( 'dts_normalize_orderby' ) ) {
	/** Whitelisted product sorts. */
	function dts_normalize_orderby( $orderby, $default = 'menu_order' ) {
		$allowed = array( 'menu_order', 'price', 'price-desc', 'date', 'popularity', 'rating' );

		return in_array( $orderby, $allowed, true ) ? $orderby : $default;
	}
}

if ( ! function_exists( 'dts_apply_orderby' ) ) {
	/**
	 * Apply a sort to query args.
	 *
	 * @param array  $args    Query args, modified in place by reference.
	 * @param string $orderby Sort key.
	 */
	function dts_apply_orderby( $args, $orderby ) {
		switch ( dts_normalize_orderby( $orderby ) ) {
			case 'price':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'ASC';
				break;
			case 'price-desc':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_price';
				$args['order']    = 'DESC';
				break;
			case 'date':
				$args['orderby'] = 'date';
				$args['order']   = 'DESC';
				break;
			case 'popularity':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = 'total_sales';
				$args['order']    = 'DESC';
				break;
			case 'rating':
				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating';
				$args['order']    = 'DESC';
				break;
			case 'menu_order':
			default:
				$args['orderby'] = 'menu_order title';
				$args['order']   = 'ASC';
				break;
		}

		return $args;
	}
}

if ( ! function_exists( 'dts_paginate' ) ) {
	/**
	 * Clamp a requested page against a total and return the window.
	 *
	 * @param int $total  Total items.
	 * @param int $paged  Requested page.
	 * @param int $per_page Items per page.
	 * @return array{paged: int, total_pages: int, offset: int, from: int, to: int, items: array}
	 */
	function dts_paginate( $total, $paged, $per_page, $items = array() ) {
		$total       = max( 0, (int) $total );
		$per_page    = max( 1, (int) $per_page );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$paged       = min( max( 1, (int) $paged ), $total_pages );
		$offset      = ( $paged - 1 ) * $per_page;

		return array(
			'paged'       => $paged,
			'total_pages' => $total_pages,
			'offset'      => $offset,
			'from'        => $total > 0 ? $offset + 1 : 0,
			'to'          => min( $offset + $per_page, $total ),
			'items'       => is_array( $items ) ? array_slice( $items, $offset, $per_page ) : array(),
		);
	}
}

/* ------------------------------------------------------------------------- */
/* Tyre filtering                                                             */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_build_tyre_filter_args' ) ) {
	/**
	 * Translate a set of tyre filter parameters into WP_Query args.
	 *
	 * @param array $params Filter parameters:
	 *   brand, year, run_flat, promo[], price_min, price_max, price_active,
	 *   orderby, paged, per_page, product_cat, brand_page_slug, size (front),
	 *   size_rear (rear), offers_only, is_double.
	 * @return array Query args.
	 */
	function dts_build_tyre_filter_args( $params ) {
		$params = wp_parse_args(
			$params,
			array(
				'brand'           => array(),
				'year'            => array(),
				'run_flat'        => array(),
				'promo'           => array(),
				'width'           => array(),
				'height'          => array(),
				'rim'             => array(),
				'tyre_type'       => array(),
				'vehicle_type'    => array(),
				'tier'            => array(),
				'price_min'       => 0,
				'price_max'       => 0,
				'price_active'    => false,
				'orderby'         => 'menu_order',
				'paged'           => 1,
				'per_page'        => 20,
				'product_cat'     => '',
				'brand_page_slug' => '',
				'size'            => null,
				'size_rear'       => null,
				'offers_only'     => false,
			)
		);

		$meta_query = array();
		$tax_query  = array();

		$brand_tax = dts_get_brand_taxonomy( 'tyre' );

		if ( ! empty( $params['brand'] ) && '' !== $brand_tax ) {
			$tax_query[] = array(
				'taxonomy' => $brand_tax,
				'field'    => 'slug',
				'terms'    => (array) $params['brand'],
			);
		} elseif ( ! empty( $params['brand_page_slug'] ) && '' !== $brand_tax ) {
			// A brand archive with no explicit selection still scopes the grid.
			$tax_query[] = array(
				'taxonomy' => $brand_tax,
				'field'    => 'slug',
				'terms'    => $params['brand_page_slug'],
			);
		}

		if ( ! empty( $params['year'] ) ) {
			$meta_query[] = array(
				'key'     => 'year_manufacture',
				'value'   => (array) $params['year'],
				'compare' => 'IN',
			);
		}

		if ( ! empty( $params['run_flat'] ) ) {
			$meta_query[] = array(
				'key'     => 'tyre_run_flat',
				'value'   => dts_normalize_run_flat_filter( $params['run_flat'] ),
				'compare' => 'IN',
			);
		}

		if ( ! empty( $params['promo'] ) ) {
			$promo_mq = dts_build_buy3get1_meta_query( $params['promo'] );

			if ( ! empty( $promo_mq ) ) {
				$meta_query[] = $promo_mq;
			}
		}

		foreach ( array( 'width' => 'tyre_width', 'height' => 'tyre_height', 'rim' => 'tyre_rim_size' ) as $size_filter => $meta_key ) {
			if ( ! empty( $params[ $size_filter ] ) ) {
				$size_values = array_map( 'sanitize_text_field', (array) $params[ $size_filter ] );
				if ( 'rim' === $size_filter ) {
					$size_values = array_map(
						static function ( $value ) {
							return preg_match( '/^R?\d+$/i', $value ) ? 'R' . ltrim( strtoupper( $value ), 'R' ) : $value;
						},
						$size_values
					);
				}
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => $size_values,
					'compare' => 'IN',
				);
			}
		}
		if ( ! empty( $params['vehicle_type'] ) ) {
			$vehicle_values = array_map( 'sanitize_text_field', (array) $params['vehicle_type'] );
			$fallback_values = array_map(
				static function ( $value ) {
					return 0 === strcasecmp( $value, 'Passenger' ) ? 'Car' : $value;
				},
				$vehicle_values
			);
			$meta_query[] = array(
				'relation' => 'OR',
				array( 'key' => 'vehicle_type', 'value' => $vehicle_values, 'compare' => 'IN' ),
				array( 'key' => 'tyre_type', 'value' => $fallback_values, 'compare' => 'IN' ),
			);
		}
		foreach ( array( 'tyre_type' => 'tyre_type', 'tier' => 'tyre_tier' ) as $meta_filter => $meta_key ) {
			if ( ! empty( $params[ $meta_filter ] ) ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => array_map( 'sanitize_text_field', (array) $params[ $meta_filter ] ),
					'compare' => 'IN',
				);
			}
		}

		if ( ! empty( $params['price_active'] ) ) {
			$meta_query = dts_apply_price_filter( $meta_query, $params['price_min'], $params['price_max'] );
		}

		if ( ! empty( $params['offers_only'] ) ) {
			$meta_query[] = array(
				'key'   => 'buy_3_get_1',
				'value' => 'yes',
			);
		}

		// Size constraints: a single size, or a staggered set where a product
		// matching either the front or the rear size belongs on the page.
		if ( ! empty( $params['size'] ) && ! empty( $params['size']['width'] ) ) {
			$size_mq = dts_size_meta_query( $params['size'] );

			if ( ! empty( $params['size_rear'] ) && ! empty( $params['size_rear']['width'] ) && $params['size_rear'] !== $params['size'] ) {
				$size_mq = array(
					'relation' => 'OR',
					dts_size_meta_query( $params['size'] ),
					dts_size_meta_query( $params['size_rear'] ),
				);
			}

			$meta_query = empty( $meta_query )
				? $size_mq
				: array_merge( array( 'relation' => 'AND' ), array( $size_mq ), array( $meta_query ) );
		}

		if ( count( $meta_query ) > 1 ) {
			$meta_query['relation'] = 'AND';
		}

		// Category scope.
		if ( ! empty( $params['product_cat'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $params['product_cat'],
				'operator' => 'IN',
			);
		} else {
			$tyre_slugs = dts_get_product_type_category_slugs( 'tyre' );

			if ( ! empty( $tyre_slugs ) ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $tyre_slugs,
					'operator' => 'IN',
				);
			}
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $params['per_page'] ),
			'paged'          => max( 1, (int) $params['paged'] ),
		);

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
		}

		if ( ! empty( $tax_query ) ) {
			$args['tax_query'] = $tax_query;
		}

		return dts_apply_orderby( $args, $params['orderby'] );
	}
}

if ( ! function_exists( 'dts_get_tyre_facets' ) ) {
	/**
	 * Facet options and counts for a tyre result set.
	 *
	 * Each facet group is counted against the same query minus its own
	 * constraint, so a selected brand does not zero out the other brands and the
	 * price slider never shrinks its own bounds.
	 *
	 * @param array  $args       Query args from dts_build_tyre_filter_args().
	 * @param string $orderby    Active sort.
	 * @param bool   $include_promo Whether the promo facet applies (offers page skips it).
	 * @return array
	 */
	function dts_get_tyre_facets( $args, $orderby = 'menu_order', $include_promo = true ) {
		$brand_tax     = dts_get_brand_taxonomy( 'tyre' );
		$brand_counts  = array();
		$brands        = array();
		$years         = array();
		$run_flat      = array();
		$promo         = array();
		$size_facets   = array();
		$attribute_facets = array();

		if ( '' !== $brand_tax ) {
			$brand_ids = dts_get_ids( dts_facet_query_args( $args, array(), array( $brand_tax, 'pa_brand' ), $orderby ) );

			foreach ( $brand_ids as $product_id ) {
				$terms = wp_get_post_terms( $product_id, $brand_tax, array( 'fields' => 'ids' ) );

				if ( is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term_id ) {
					$brand_counts[ $term_id ] = isset( $brand_counts[ $term_id ] ) ? $brand_counts[ $term_id ] + 1 : 1;
				}
			}

			if ( ! empty( $brand_counts ) ) {
				$terms = get_terms(
					array(
						'taxonomy'   => $brand_tax,
						'include'    => array_keys( $brand_counts ),
						'hide_empty' => true,
					)
				);

				if ( ! is_wp_error( $terms ) ) {
					$brands = $terms;
				}
			}
		}

		$year_ids = dts_get_ids( dts_facet_query_args( $args, array( 'year_manufacture' ), array(), $orderby ) );

		foreach ( $year_ids as $product_id ) {
			$year = get_post_meta( $product_id, 'year_manufacture', true );

			if ( '' !== $year && false !== $year ) {
				$years[] = $year;
			}
		}

		$years = array_values( array_unique( $years ) );
		rsort( $years, SORT_NUMERIC );

		foreach ( array( 'width' => 'tyre_width', 'height' => 'tyre_height', 'rim' => 'tyre_rim_size' ) as $size_filter => $meta_key ) {
			$size_ids = dts_get_ids( dts_facet_query_args( $args, array( $meta_key ), array(), $orderby ) );
			$values   = array();
			foreach ( $size_ids as $product_id ) {
				$value = trim( (string) get_post_meta( $product_id, $meta_key, true ) );
				if ( '' !== $value ) {
					$values[] = 'rim' === $size_filter ? ltrim( strtoupper( $value ), 'R' ) : $value;
				}
			}
			$values = array_values( array_unique( $values ) );
			natcasesort( $values );
			$size_facets[ $size_filter ] = dts_format_value_facet( $values );
		}
		foreach ( array( 'tyreType' => 'tyre_type', 'tier' => 'tyre_tier' ) as $facet_name => $meta_key ) {
			$facet_ids = dts_get_ids( dts_facet_query_args( $args, array( $meta_key ), array(), $orderby ) );
			$values = array();
			foreach ( $facet_ids as $product_id ) {
				$value = trim( (string) get_post_meta( $product_id, $meta_key, true ) );
				if ( '' !== $value ) {
					$values[] = $value;
				}
			}
			$values = array_values( array_unique( $values ) );
			natcasesort( $values );
			$attribute_facets[ $facet_name ] = dts_format_value_facet( $values );
		}
		$vehicle_ids = dts_get_ids( dts_facet_query_args( $args, array( 'vehicle_type', 'tyre_type' ), array(), $orderby ) );
		$vehicle_values = array();
		foreach ( $vehicle_ids as $product_id ) {
			$value = trim( (string) get_post_meta( $product_id, 'vehicle_type', true ) );
			if ( '' === $value ) {
				$value = trim( (string) get_post_meta( $product_id, 'tyre_type', true ) );
			}
			if ( 0 === strcasecmp( $value, 'Car' ) ) {
				$value = 'Passenger';
			}
			if ( '' !== $value ) {
				$vehicle_values[] = $value;
			}
		}
		$vehicle_values = array_values( array_unique( $vehicle_values ) );
		natcasesort( $vehicle_values );
		$attribute_facets['vehicleType'] = dts_format_value_facet( $vehicle_values );

		$run_flat_ids = dts_get_ids( dts_facet_query_args( $args, array( 'tyre_run_flat' ), array(), $orderby ) );

		foreach ( $run_flat_ids as $product_id ) {
			$value = get_post_meta( $product_id, 'tyre_run_flat', true );

			if ( '' !== $value && false !== $value ) {
				$run_flat[] = $value;
			}
		}

		$run_flat = array_values( array_unique( $run_flat ) );

		if ( $include_promo ) {
			$promo_ids = dts_get_ids( dts_facet_query_args( $args, array( 'buy_3_get_1' ), array(), $orderby ) );

			foreach ( $promo_ids as $product_id ) {
				$value = get_post_meta( $product_id, 'buy_3_get_1', true );

				if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
					$promo[] = $value;
				}
			}

			$promo = array_values( array_unique( $promo ) );
		}

		// Price bounds ignore the price constraint so the slider does not
		// collapse onto the current selection.
		$price_args = $args;

		$price_args['posts_per_page'] = -1;
		$price_args['no_found_rows']  = true;

		unset( $price_args['paged'], $price_args['meta_key'] );

		if ( ! empty( $price_args['meta_query'] ) ) {
			$price_args['meta_query'] = dts_remove_meta_key_from_query( $price_args['meta_query'], '_price' );

			if ( empty( $price_args['meta_query'] ) ) {
				unset( $price_args['meta_query'] );
			}
		}

		list( $price_min, $price_max ) = dts_get_price_bounds( dts_get_ids( $price_args ), 0, 5000 );

		return array(
			'brands'    => dts_format_brand_facet( $brands, $brand_counts ),
			'years'     => dts_format_value_facet( $years ),
			'run_flat'  => dts_format_run_flat_facet( $run_flat ),
			'promo'     => dts_format_value_facet( $promo ),
			'width'     => $size_facets['width'],
			'height'    => $size_facets['height'],
			'rim'       => $size_facets['rim'],
			'tyreType'  => $attribute_facets['tyreType'],
			'vehicleType' => $attribute_facets['vehicleType'],
			'tier'      => $attribute_facets['tier'],
			'price'     => array(
				'min' => $price_min,
				'max' => $price_max,
			),
		);
	}
}

/* ------------------------------------------------------------------------- */
/* Battery filtering                                                          */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_build_battery_filter_args' ) ) {
	/**
	 * Translate battery filter parameters into WP_Query args.
	 *
	 * @param array $params Filter parameters:
	 *   brand[], origin[], capacity[], reserve[], cca[], length[], width[],
	 *   height[], on_sale, price_min, price_max, price_active, abs_price_min,
	 *   abs_price_max, orderby, paged, per_page, product_cat, search.
	 * @return array Query args.
	 */
	function dts_build_battery_filter_args( $params ) {
		$params = wp_parse_args(
			$params,
			array(
				'brand'        => array(),
				'origin'       => array(),
				'capacity'     => array(),
				'reserve'      => array(),
				'cca'          => array(),
				'length'       => array(),
				'width'        => array(),
				'height'       => array(),
				'on_sale'      => false,
				'price_min'    => 0,
				'price_max'    => 0,
				'price_active' => false,
				'abs_price_min' => 0,
				'abs_price_max' => 0,
				'orderby'      => 'menu_order',
				'paged'        => 1,
				'per_page'     => 20,
				'product_cat'  => '',
				'search'       => '',
			)
		);

		$tax_query  = array( 'relation' => 'AND' );
		$meta_query = array( 'relation' => 'AND' );

		if ( ! empty( $params['product_cat'] ) ) {
			$tax_query[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $params['product_cat'],
			);
		} else {
			$battery_slugs = dts_get_product_type_category_slugs( 'battery' );

			if ( ! empty( $battery_slugs ) ) {
				$tax_query[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $battery_slugs,
					'operator' => 'IN',
				);
			}
		}

		$brand_tax = dts_get_brand_taxonomy( 'battery' );

		if ( ! empty( $params['brand'] ) && '' !== $brand_tax ) {
			$tax_query[] = array(
				'taxonomy' => $brand_tax,
				'field'    => 'slug',
				'terms'    => (array) $params['brand'],
			);
		}

		// Country of manufacture has a historical key with a stray space.
		if ( ! empty( $params['origin'] ) ) {
			$meta_query[] = array(
				'relation' => 'OR',
				array(
					'key'     => 'country_of_manufacture',
					'value'   => (array) $params['origin'],
					'compare' => 'IN',
				),
				array(
					'key'     => 'country_of_ manufacture',
					'value'   => (array) $params['origin'],
					'compare' => 'IN',
				),
			);
		}

		$meta_map = array(
			'capacity' => 'capacity',
			'reserve'  => 'reserve_capacity',
			'cca'      => 'cca',
			'length'   => 'length_mm',
			'width'    => 'width_mm',
			'height'   => 'height_mm',
		);

		foreach ( $meta_map as $param => $meta_key ) {
			if ( ! empty( $params[ $param ] ) ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => (array) $params[ $param ],
					'compare' => 'IN',
				);
			}
		}

		if ( ! empty( $params['on_sale'] ) ) {
			$meta_query[] = array(
				'key'     => '_sale_price',
				'value'   => 0,
				'compare' => '>',
				'type'    => 'NUMERIC',
			);
		}

		// Only constrain price when the slider actually moved away from the
		// absolute bounds; otherwise an untouched slider would pin the range.
		if ( ! empty( $params['price_active'] ) && ( $params['price_min'] > $params['abs_price_min'] || $params['price_max'] < $params['abs_price_max'] ) ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => array( $params['price_min'], $params['price_max'] ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		} elseif ( $params['price_min'] > 0 && $params['price_min'] === $params['price_max'] ) {
			$meta_query[] = array(
				'key'     => '_price',
				'value'   => $params['price_min'],
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		}

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $params['per_page'] ),
			'paged'          => max( 1, (int) $params['paged'] ),
			'tax_query'      => $tax_query,
			'meta_query'     => $meta_query,
		);

		if ( '' !== $params['search'] ) {
			$args['s'] = $params['search'];
		}

		return dts_apply_orderby( $args, $params['orderby'] );
	}
}

if ( ! function_exists( 'dts_get_battery_facets' ) ) {
	/**
	 * Facet options and counts for a battery result set.
	 *
	 * @param array  $args    Query args from dts_build_battery_filter_args().
	 * @param string $orderby Active sort.
	 * @return array
	 */
	function dts_get_battery_facets( $args, $orderby = 'menu_order' ) {
		$brand_tax = dts_get_brand_taxonomy( 'battery' );

		// Brand options come from the whole battery family, not the current
		// selection, so a filtered list still offers the other brands.
		$brand_terms = array();

		if ( '' !== $brand_tax ) {
			$battery_ids = dts_get_ids(
				array(
					'tax_query' => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'slug',
							'terms'    => dts_get_product_type_category_slugs( 'battery' ),
							'operator' => 'IN',
						),
					),
				)
			);

			$brand_id_set = array();

			foreach ( $battery_ids as $product_id ) {
				$terms = wp_get_post_terms( $product_id, $brand_tax, array( 'fields' => 'ids' ) );

				if ( is_wp_error( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term_id ) {
					$brand_id_set[ $term_id ] = true;
				}
			}

			if ( ! empty( $brand_id_set ) ) {
				$brand_terms = get_terms(
					array(
						'taxonomy'   => $brand_tax,
						'include'    => array_keys( $brand_id_set ),
						'hide_empty' => true,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);
			}

			if ( is_wp_error( $brand_terms ) || empty( $brand_terms ) ) {
				$brand_terms = get_terms(
					array(
						'taxonomy'   => $brand_tax,
						'hide_empty' => true,
						'orderby'    => 'name',
						'order'      => 'ASC',
					)
				);
			}

			if ( is_wp_error( $brand_terms ) ) {
				$brand_terms = array();
			}
		}

		$result_ids = dts_get_ids( $args );

		// Brand counts come from a brand-unscoped query, otherwise unselected
		// brands would all report zero.
		$brand_count_ids = $result_ids;

		if ( '' !== $brand_tax && isset( $args['tax_query'] ) ) {
			$unscoped = dts_remove_taxonomies_from_query( $args['tax_query'], array( $brand_tax, 'pa_brand' ) );

			// A shorter clause list means the query carried a brand constraint.
			if ( count( $unscoped ) !== count( (array) $args['tax_query'] ) ) {
				$brand_count_ids = dts_get_ids( dts_facet_query_args( $args, array(), array( $brand_tax, 'pa_brand' ), $orderby ) );
			}
		}

		$brand_counts = array();

		foreach ( $brand_count_ids as $product_id ) {
			$slugs = wp_get_post_terms( $product_id, $brand_tax, array( 'fields' => 'slugs' ) );

			if ( is_wp_error( $slugs ) ) {
				continue;
			}

			foreach ( $slugs as $slug ) {
				$brand_counts[ $slug ] = isset( $brand_counts[ $slug ] ) ? $brand_counts[ $slug ] + 1 : 1;
			}
		}

		$value_facets = array(
			'origin'   => 'origin',
			'capacity' => 'capacity',
			'reserve'  => 'reserve_capacity',
			'cca'      => 'cca',
			'length'   => 'length_mm',
			'width'    => 'width_mm',
			'height'   => 'height_mm',
		);

		$facets = array();

		foreach ( $value_facets as $name => $meta_key ) {
			$counts = array();

			foreach ( $result_ids as $product_id ) {
				$value = ( 'origin' === $name ) ? dts_get_product_origin( $product_id ) : get_post_meta( $product_id, $meta_key, true );

				if ( '' === $value || false === $value || null === $value || '-' === $value ) {
					continue;
				}

				$counts[ $value ] = isset( $counts[ $value ] ) ? $counts[ $value ] + 1 : 1;
			}

			$values = array_keys( $counts );

			if ( in_array( $name, array( 'length', 'width', 'height' ), true ) ) {
				sort( $values, SORT_NUMERIC );
			} else {
				sort( $values );
			}

			$facets[ $name ] = dts_format_value_facet( $values, $counts );
		}

		list( $result_min, $result_max )     = dts_get_price_bounds( $result_ids, 0, 5000 );
		list( $absolute_min, $absolute_max ) = dts_get_battery_absolute_price_bounds();

		return array(
			'brands' => dts_format_brand_facet( $brand_terms, $brand_counts, 'slug' ),
			'origin' => $facets['origin'],
			'capacity' => $facets['capacity'],
			'reserve'  => $facets['reserve'],
			'cca'      => $facets['cca'],
			'length'   => $facets['length'],
			'width'    => $facets['width'],
			'height'   => $facets['height'],
			'price'    => array(
				'min'          => $result_min,
				'max'          => $result_max,
				'absolute_min' => $absolute_min,
				'absolute_max' => $absolute_max,
			),
		);
	}
}

if ( ! function_exists( 'dts_get_battery_absolute_price_bounds' ) ) {
	/** Price bounds across every battery in the catalog. */
	function dts_get_battery_absolute_price_bounds() {
		$cached = get_transient( 'dts_battery_price_bounds' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$bounds = dts_get_price_bounds(
			dts_get_ids(
				array(
					'tax_query' => array(
						array(
							'taxonomy' => 'product_cat',
							'field'    => 'slug',
							'terms'    => dts_get_product_type_category_slugs( 'battery' ),
							'operator' => 'IN',
						),
					),
				)
			),
			0,
			5000
		);

		set_transient( 'dts_battery_price_bounds', $bounds, DAY_IN_SECONDS );

		return $bounds;
	}
}

/* ------------------------------------------------------------------------- */
/* Facet formatting                                                           */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_format_brand_facet' ) ) {
	/**
	 * Turn brand terms plus counts into a JSON facet.
	 *
	 * @param array    $terms    Term objects.
	 * @param array    $counts   Counts keyed by term_id (or slug when $key is 'slug').
	 * @param string   $key      'term_id' or 'slug'.
	 * @return array
	 */
	function dts_format_brand_facet( $terms, $counts, $key = 'term_id' ) {
		$facet = array();

		foreach ( (array) $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->slug, $term->name ) ) {
				continue;
			}

			$lookup    = ( 'slug' === $key ) ? $term->slug : $term->term_id;
			$facet[]   = array(
				'slug'  => $term->slug,
				'name'  => $term->name,
				'count' => isset( $counts[ $lookup ] ) ? (int) $counts[ $lookup ] : 0,
			);
		}

		return $facet;
	}
}

if ( ! function_exists( 'dts_format_value_facet' ) ) {
	/**
	 * Turn plain values plus counts into a JSON facet.
	 *
	 * @param string[] $values Values.
	 * @param array    $counts Counts keyed by value.
	 * @return array
	 */
	function dts_format_value_facet( $values, $counts = array() ) {
		$facet = array();

		foreach ( (array) $values as $value ) {
			$facet[] = array(
				'value' => (string) $value,
				'label' => (string) $value,
				'count' => isset( $counts[ $value ] ) ? (int) $counts[ $value ] : 0,
			);
		}

		return $facet;
	}
}

if ( ! function_exists( 'dts_format_run_flat_facet' ) ) {
	/**
	 * Run-flat facet with normalised labels so storage quirks never leak out.
	 *
	 * @param string[] $values Stored values.
	 * @return array
	 */
	function dts_format_run_flat_facet( $values ) {
		$facet = array();

		foreach ( (array) $values as $value ) {
			$facet[] = array(
				'value' => dts_is_run_flat_value( $value ) ? '1' : '0',
				'label' => dts_is_run_flat_value( $value ) ? 'Yes' : 'No',
				'count' => 0,
			);
		}

		// Collapse the yes/no/1/0 storage variants into two options.
		$seen = array();
		$out  = array();

		foreach ( $facet as $option ) {
			if ( isset( $seen[ $option['value'] ] ) ) {
				continue;
			}

			$seen[ $option['value'] ] = true;
			$out[]                   = $option;
		}

		return $out;
	}
}

/* ------------------------------------------------------------------------- */
/* Staggered pairs                                                            */
/* ------------------------------------------------------------------------- */

if ( ! function_exists( 'dts_query_tyre_pairs' ) ) {
	/**
	 * Resolve a staggered fitment into matched pairs.
	 *
	 * The front query carries the facet filters, because a set is sold and priced
	 * as a unit; the rear query is unfiltered so every front product can find its
	 * counterpart.
	 *
	 * @param array $front  Front size from dts_get_size_request().
	 * @param array $rear   Rear size from dts_get_size_request().
	 * @param array $params Facet parameters: brand, year, run_flat, promo, product_cat.
	 * @return array{ pairs: array, price_min: float, price_max: float, front_ids: int[] }
	 */
	function dts_query_tyre_pairs( $front, $rear, $params = array() ) {
		$params = wp_parse_args(
			$params,
			array(
				'brand'       => array(),
				'year'        => array(),
				'run_flat'    => array(),
				'promo'       => array(),
				'product_cat' => '',
			)
		);

		$front_mq = dts_size_meta_query( $front );
		$rear_mq  = dts_size_meta_query( $rear );

		$filter_meta_query = array();

		if ( ! empty( $params['year'] ) ) {
			$filter_meta_query[] = array(
				'key'     => 'year_manufacture',
				'value'   => (array) $params['year'],
				'compare' => 'IN',
			);
		}

		if ( ! empty( $params['run_flat'] ) ) {
			$filter_meta_query[] = array(
				'key'     => 'tyre_run_flat',
				'value'   => dts_normalize_run_flat_filter( $params['run_flat'] ),
				'compare' => 'IN',
			);
		}

		if ( ! empty( $params['promo'] ) ) {
			$promo_mq = dts_build_buy3get1_meta_query( $params['promo'] );

			if ( ! empty( $promo_mq ) ) {
				$filter_meta_query[] = $promo_mq;
			}
		}

		if ( count( $filter_meta_query ) > 1 ) {
			$filter_meta_query['relation'] = 'AND';
		}

		$front_meta_query = empty( $filter_meta_query )
			? $front_mq
			: array_merge( array( 'relation' => 'AND' ), array( $front_mq ), array( $filter_meta_query ) );

		$front_args = array( 'meta_query' => $front_meta_query );

		$rear_args = array( 'meta_query' => $rear_mq );

		$brand_tax = dts_get_brand_taxonomy( 'tyre' );

		if ( ! empty( $params['brand'] ) && '' !== $brand_tax ) {
			$front_args['tax_query'] = array(
				array(
					'taxonomy' => $brand_tax,
					'field'    => 'slug',
					'terms'    => (array) $params['brand'],
				),
			);
		}

		$scope_tax = array();

		if ( ! empty( $params['product_cat'] ) ) {
			$scope_tax[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $params['product_cat'],
				'operator' => 'IN',
			);
		} else {
			$tyre_slugs = dts_get_product_type_category_slugs( 'tyre' );

			if ( ! empty( $tyre_slugs ) ) {
				$scope_tax[] = array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $tyre_slugs,
					'operator' => 'IN',
				);
			}
		}

		if ( ! empty( $scope_tax ) ) {
			if ( isset( $front_args['tax_query'] ) ) {
				$front_args['tax_query'][] = $scope_tax[0];
			} else {
				$front_args['tax_query'] = $scope_tax;
			}

			$rear_args['tax_query'] = $scope_tax;
		}

		$front_ids = dts_get_ids( $front_args );
		$rear_ids  = dts_get_ids( $rear_args );

		// Price bounds are quoted per set of four, hence the doubling.
		list( $price_min, $price_max ) = dts_get_price_bounds( $front_ids, 0, 99999 );

		$pairs = array();

		if ( ! empty( $front_ids ) && ! empty( $rear_ids ) ) {
			$pairs = dts_pair_left_and_right(
				dts_get_pair_data( $front_ids ),
				dts_get_pair_data( $rear_ids )
			);
		}

		return array(
			'pairs'     => $pairs,
			'front_ids' => $front_ids,
			'price_min' => $price_min,
			'price_max' => $price_max,
		);
	}
}

if ( ! function_exists( 'dts_get_pair_facets' ) ) {
	/**
	 * Facet options derived from resolved pairs.
	 *
	 * Counting from pairs rather than from individual products keeps the counts
	 * honest: every count is a real orderable set.
	 *
	 * @param array $pairs Pairs from dts_query_tyre_pairs().
	 * @return array
	 */
	function dts_get_pair_facets( $pairs ) {
		$brand_tax    = dts_get_brand_taxonomy( 'tyre' );
		$brand_counts = array();
		$years        = array();
		$run_flat     = array();
		$promo        = array();

		foreach ( $pairs as $pair ) {
			$product_id = $pair['left']['id'];

			if ( '' !== $brand_tax ) {
				$terms = wp_get_post_terms( $product_id, $brand_tax, array( 'fields' => 'ids' ) );

				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term_id ) {
						$brand_counts[ $term_id ] = isset( $brand_counts[ $term_id ] ) ? $brand_counts[ $term_id ] + 1 : 1;
					}
				}
			}

			if ( ! empty( $pair['left']['year'] ) ) {
				$years[] = $pair['left']['year'];
			}

			$run_flat[ $pair['left']['run_flat'] ? '1' : '0' ] = true;

			if ( 'yes' === $pair['left']['promo'] ) {
				$promo[] = 'yes';
			}
		}

		$years = array_values( array_unique( $years ) );
		rsort( $years, SORT_NUMERIC );

		$brands = array();

		if ( ! empty( $brand_counts ) ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $brand_tax,
					'include'    => array_keys( $brand_counts ),
					'hide_empty' => true,
				)
			);

			if ( ! is_wp_error( $terms ) ) {
				$brands = $terms;
			}
		}

		return array(
			'brands'   => dts_format_brand_facet( $brands, $brand_counts ),
			'years'    => dts_format_value_facet( $years ),
			'run_flat' => dts_format_run_flat_facet( array_keys( $run_flat ) ),
			'promo'    => ! empty( $promo ) ? dts_format_value_facet( array( 'yes' ) ) : array(),
		);
	}
}
