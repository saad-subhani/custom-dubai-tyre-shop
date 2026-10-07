<?php
/**
 * Tyre size lookup endpoints.
 *
 * The size picker needs the distinct widths, heights and rims that the store
 * actually stocks, plus a count for a chosen size. Both are served from the
 * store's own metadata rather than from the API, and the count reuses the
 * canonical size meta query so it always agrees with the product grid.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_size_dimension_variants' ) ) {
	/**
	 * Every stored spelling of a dimension value.
	 *
	 * Rims are stored both as "R17" and "17", so a narrowing clause has to match
	 * either form or the picker would drop options depending on how the catalogue
	 * was imported.
	 *
	 * @param string $dimension 'width', 'height' or 'rim'.
	 * @param string $value     Chosen value.
	 * @return string[]
	 */
	function dts_get_size_dimension_variants( $dimension, $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return array();
		}

		$variants = array( $value );

		if ( 'rim' === $dimension ) {
			$token = dts_tyre_size_rim_token( $value );

			if ( '' !== $token ) {
				$variants[] = $token;
				$variants[] = ltrim( $token, 'R' );
			}
		}

		return array_values( array_unique( array_filter( $variants, 'strlen' ) ) );
	}
}

if ( ! function_exists( 'dts_get_tyre_size_dimensions_version' ) ) {
	/**
	 * Cache generation for the size picker.
	 *
	 * The picker's cache keys embed this number, so invalidation only has to
	 * bump it instead of hunting for transients to delete. Stale generations
	 * expire on their own.
	 */
	function dts_get_tyre_size_dimensions_version() {
		$version = (int) get_option( 'dts_tyre_size_dimensions_version' );

		if ( $version < 1 ) {
			$version = 1;
			update_option( 'dts_tyre_size_dimensions_version', $version, false );
		}

		return $version;
	}
}

if ( ! function_exists( 'dts_clear_tyre_size_dimension_cache' ) ) {
	/** Retire the cached size dimensions after a product change. */
	function dts_clear_tyre_size_dimension_cache() {
		update_option(
			'dts_tyre_size_dimensions_version',
			dts_get_tyre_size_dimensions_version() + 1,
			false
		);
	}
}
add_action( 'save_post_product', 'dts_clear_tyre_size_dimension_cache' );
add_action( 'deleted_post', 'dts_clear_tyre_size_dimension_cache' );
add_action( 'woocommerce_update_product', 'dts_clear_tyre_size_dimension_cache' );

if ( ! function_exists( 'dts_get_tyre_size_dimensions' ) ) {
	/**
	 * Distinct stored size values, narrowed by the dimensions already chosen.
	 *
	 * @param string $dimension 'width', 'height' or 'rim'.
	 * @param array  $context   Values already chosen: array( 'width' => …, 'height' => … ).
	 * @return array List of array( 'value' => string, 'slug' => string, 'label' => string ).
	 */
	function dts_get_tyre_size_dimensions( $dimension, $context = array() ) {
		$meta_keys = array(
			'width'  => 'tyre_width',
			'height' => 'tyre_height',
			'rim'    => 'tyre_rim_size',
		);

		if ( ! isset( $meta_keys[ $dimension ] ) ) {
			return array();
		}

		$cache_key = sprintf(
			'dts_tyre_dimensions_%d_%s_%s',
			dts_get_tyre_size_dimensions_version(),
			$dimension,
			md5( wp_json_encode( $context ) )
		);

		$cached = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$joins = '';
		$where = array();

		foreach ( $meta_keys as $key => $meta_key ) {
			$alias = 'm_' . $key;

			if ( $key === $dimension ) {
				$target = $alias;
				continue;
			}

			$joins .= $wpdb->prepare(
				" INNER JOIN {$wpdb->postmeta} {$alias} ON {$alias}.post_id = p.ID AND {$alias}.meta_key = %s",
				$meta_key
			);

			// A dimension is only narrowed once its own value is known.
			$variants = dts_get_size_dimension_variants( $key, isset( $context[ $key ] ) ? $context[ $key ] : '' );

			if ( ! empty( $variants ) ) {
				$placeholders = implode( ', ', array_fill( 0, count( $variants ), '%s' ) );

				$where[] = $wpdb->prepare( "{$alias}.meta_value IN ( {$placeholders} )", $variants ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}
		}

		$sql = "SELECT DISTINCT {$target}.meta_value AS value
			FROM {$wpdb->posts} p
			{$joins}
			INNER JOIN {$wpdb->postmeta} {$target} ON {$target}.post_id = p.ID AND {$target}.meta_key = %s
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND {$target}.meta_value != ''
			AND {$target}.meta_value IS NOT NULL";

		$args = array( $meta_keys[ $dimension ] );

		if ( ! empty( $where ) ) {
			$sql .= ' AND ' . implode( ' AND ', $where );
		}

		// Widths and heights sort numerically; rims are "R17"-style, so they fall
		// through to the string pass once the cast ties.
		$sql .= ' ORDER BY CAST(value AS DECIMAL(12,4)) ASC, value ASC';

		$values = $wpdb->get_col( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared

		$results = array();

		foreach ( (array) $values as $value ) {
			$value = trim( (string) $value );

			if ( '' === $value ) {
				continue;
			}

			$label = ( 'rim' === $dimension ) ? dts_tyre_size_rim_token( $value ) : $value;

			$results[] = array(
				'value' => $value,
				'slug'  => $label,
				'label' => $label,
			);
		}

		set_transient( $cache_key, $results, DAY_IN_SECONDS );

		return $results;
	}
}

if ( ! function_exists( 'dts_size_request_to_parts' ) ) {
	/**
	 * Rebuild a parsed size from a size request.
	 *
	 * The size picker talks in separate width / height / rim fields, but slugs,
	 * labels and URLs all want the parsed representation the rest of the theme
	 * uses, so this is where the two meet.
	 *
	 * @param array $size Output of dts_get_size_request().
	 * @return array Parsed size parts.
	 */
	function dts_size_request_to_parts( $size ) {
		$width     = isset( $size['width'] ) ? trim( (string) $size['width'] ) : '';
		$height    = isset( $size['height'] ) ? trim( (string) $size['height'] ) : '';
		$rim       = isset( $size['rim'] ) ? trim( (string) $size['rim'] ) : '';
		$flotation = ! empty( $size['flotation'] );

		if ( '' === $width || '' === $rim ) {
			return array();
		}

		if ( $flotation ) {
			return array(
				'width'         => rtrim( $width, 'xX' ),
				'height'        => dts_tyre_size_is_no_profile( $height ) ? '' : $height,
				'rim'           => dts_tyre_size_rim_token( $rim ),
				'type'          => 'flotation',
				'commercial'    => ( 'C' === substr( strtoupper( $rim ), -1 ) ),
				'no_profile'    => dts_tyre_size_is_no_profile( $height ),
				'flotation'     => true,
				'x_value'       => rtrim( $width, 'xX' ),
				'section_width' => dts_tyre_size_is_no_profile( $height ) ? '' : $height,
			);
		}

		return array(
			'width'      => $width,
			'height'     => $height,
			'rim'        => dts_tyre_size_rim_token( $rim ),
			'type'       => 'metric',
			'commercial' => ( 'C' === substr( strtoupper( $rim ), -1 ) ),
			'no_profile' => dts_tyre_size_is_no_profile( $height ),
			'flotation'  => false,
		);
	}
}

if ( ! function_exists( 'dts_get_size_request_from_input' ) ) {
	/**
	 * Read one side of a size from request data, accepting either key style.
	 *
	 * The size picker historically sent "w" / "h" / "r"; the archive filters use
	 * the full "width" / "height" / "rim" names. Both are accepted so the same
	 * endpoint serves the modal and the size pages.
	 *
	 * @param array  $input Request data.
	 * @param string $side  '' for the front size, '_r' for the rear.
	 * @return array Size request.
	 */
	function dts_get_size_request_from_input( $input, $side = '' ) {
		$aliases = array(
			'width'  => array( 'width', 'w' ),
			'height' => array( 'height', 'h' ),
			'rim'    => array( 'rim', 'r', 'rim_size', 'title' ),
			'float'  => array( 'float', 'flotation' ),
		);

		$source = array();

		foreach ( $aliases as $key => $names ) {
			foreach ( $names as $name ) {
				if ( isset( $input[ $name . $side ] ) && '' !== trim( (string) $input[ $name . $side ] ) ) {
					$source[ $key ] = $input[ $name . $side ];
					break;
				}
			}
		}

		return dts_get_size_request( $source );
	}
}

if ( ! function_exists( 'dts_count_tyre_size' ) ) {
	/**
	 * Count products matching a size, or matching a staggered pair of sizes.
	 *
	 * @param array $front Front size from dts_get_size_request().
	 * @param array $rear  Rear size, or empty for a single size.
	 * @return int Number of products, or of orderable sets when a rear size is given.
	 */
	function dts_count_tyre_size( $front, $rear = array() ) {
		$front_ids = dts_get_ids( array( 'meta_query' => dts_size_meta_query( $front ) ) );

		if ( empty( $rear ) || empty( $rear['width'] ) ) {
			return count( $front_ids );
		}

		$rear_ids = dts_get_ids( array( 'meta_query' => dts_size_meta_query( $rear ) ) );

		if ( empty( $front_ids ) || empty( $rear_ids ) ) {
			return 0;
		}

		return count( dts_pair_left_and_right( dts_get_pair_data( $front_ids ), dts_get_pair_data( $rear_ids ) ) );
	}
}

if ( ! function_exists( 'dts_ajax_get_tyre_widths' ) ) {
	/** All stocked widths. */
	function dts_ajax_get_tyre_widths() {
		wp_send_json_success( array( 'data' => dts_get_tyre_size_dimensions( 'width' ) ) );
	}
}
add_action( 'wp_ajax_dts_get_tyre_widths', 'dts_ajax_get_tyre_widths' );
add_action( 'wp_ajax_nopriv_dts_get_tyre_widths', 'dts_ajax_get_tyre_widths' );

if ( ! function_exists( 'dts_ajax_get_tyre_heights' ) ) {
	/** Heights stocked for a given width. */
	function dts_ajax_get_tyre_heights() {
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.
		$size  = dts_get_size_request_from_input( $input );

		wp_send_json_success( array( 'data' => dts_get_tyre_size_dimensions( 'height', array( 'width' => $size['width'] ) ) ) );
	}
}
add_action( 'wp_ajax_dts_get_tyre_heights', 'dts_ajax_get_tyre_heights' );
add_action( 'wp_ajax_nopriv_dts_get_tyre_heights', 'dts_ajax_get_tyre_heights' );

if ( ! function_exists( 'dts_ajax_get_tyre_rims' ) ) {
	/** Rims stocked for a given width and height. */
	function dts_ajax_get_tyre_rims() {
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.
		$size  = dts_get_size_request_from_input( $input );

		wp_send_json_success(
			array(
				'data' => dts_get_tyre_size_dimensions(
					'rim',
					array(
						'width'  => $size['width'],
						'height' => $size['height'],
					)
				),
			)
		);
	}
}
add_action( 'wp_ajax_dts_get_tyre_rims', 'dts_ajax_get_tyre_rims' );
add_action( 'wp_ajax_nopriv_dts_get_tyre_rims', 'dts_ajax_get_tyre_rims' );

if ( ! function_exists( 'dts_ajax_get_tyre_combinations' ) ) {
	/** Every stocked width/height/rim combination with its product count. */
	function dts_ajax_get_tyre_combinations() {
		wp_send_json_success( array( 'combinations' => dts_get_tyre_combinations_data() ) );
	}
}
add_action( 'wp_ajax_dts_get_tyre_combinations', 'dts_ajax_get_tyre_combinations' );
add_action( 'wp_ajax_nopriv_dts_get_tyre_combinations', 'dts_ajax_get_tyre_combinations' );

if ( ! function_exists( 'dts_ajax_count_tyre_size' ) ) {
	/** Product count for a size, or for a staggered pair of sizes. */
	function dts_ajax_count_tyre_size() {
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.

		$front = dts_get_size_request_from_input( $input );
		$rear  = dts_get_size_request_from_input( $input, '_r' );

		if ( '' === $front['width'] || '' === $front['rim'] ) {
			wp_send_json_error( array( 'message' => __( 'Width and rim are required', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		$is_staggered = '' !== $rear['width'] && '' !== $rear['rim'];
		$count        = dts_count_tyre_size( $front, $is_staggered ? $rear : array() );

		$parts      = dts_size_request_to_parts( $front );
		$parts_rear = $is_staggered ? dts_size_request_to_parts( $rear ) : array();

		$payload = array(
			'count'     => $count,
			'staggered' => $is_staggered,
			'size'      => $front,
		);

		if ( ! empty( $parts ) ) {
			$payload['label'] = dts_format_tyre_size( $parts );
			$payload['slug']  = dts_tyre_size_slug( $parts );
			$payload['url']   = dts_tyre_size_url( $parts, $parts_rear );
		}

		if ( ! empty( $parts_rear ) ) {
			$payload['rear_size'] = $rear;
			$payload['rear_label'] = dts_format_tyre_size( $parts_rear );
			$payload['rear_slug']  = dts_tyre_size_slug( $parts_rear );
		}

		wp_send_json_success( $payload );
	}
}
add_action( 'wp_ajax_dts_count_tyre_size', 'dts_ajax_count_tyre_size' );
add_action( 'wp_ajax_nopriv_dts_count_tyre_size', 'dts_ajax_count_tyre_size' );

if ( ! function_exists( 'dts_ajax_find_product_by_rim' ) ) {
	/** Resolve a rim size to a stocked product. */
	function dts_ajax_find_product_by_rim() {
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- read-only catalogue request, no state change.
		$size  = dts_get_size_request_from_input( $input );
		$rim   = $size['rim'];

		if ( '' === $rim ) {
			wp_send_json_error( array( 'message' => __( 'Missing rim size', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		$variants = dts_get_size_dimension_variants( 'rim', $rim );
		$ids      = dts_get_ids(
			array(
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'     => 'tyre_rim_size',
						'value'   => $variants,
						'compare' => 'IN',
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No product found for that rim size', 'custom-dubai-tyre-shop' ) ), 404 );
		}

		wp_send_json_success(
			array(
				'product_id' => (int) $ids[0],
				'permalink'  => get_permalink( (int) $ids[0] ),
			)
		);
	}
}
add_action( 'wp_ajax_dts_find_product_by_rim', 'dts_ajax_find_product_by_rim' );
add_action( 'wp_ajax_nopriv_dts_find_product_by_rim', 'dts_ajax_find_product_by_rim' );
