<?php
/**
 * Canonical tyre size grammar.
 *
 * Every size string reaching the theme — from a pretty URL, a query string, the
 * size lookup, the vehicle pages or the all-sizes page — is parsed here, so
 * there is exactly one grammar in the codebase.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_normalize_tyre_size_token' ) ) {
	/**
	 * Fold a raw size string down to its comparable token.
	 *
	 *   "205/70 R16C" -> "205-70-r16c"
	 *   "33x12.5 R17" -> "33x12.5-r17"
	 */
	function dts_normalize_tyre_size_token( $size_str ) {
		if ( is_array( $size_str ) || is_object( $size_str ) ) {
			return '';
		}

		$s = strtolower( trim( (string) $size_str ) );

		if ( '' === $s ) {
			return '';
		}

		// Whitespace, slashes and underscores are all just separators.
		$s = preg_replace( '/[\s\/\\_]+/', '-', $s );

		// Keep digits, dots and the size letters (x = flotation, r = rim, c = commercial).
		$s = preg_replace( '/[^0-9.xrc-]+/', '-', $s );

		// Fold repeated dashes and trim them from both ends.
		$s = preg_replace( '/-{2,}/', '-', $s );

		return trim( $s, '-' );
	}
}

if ( ! function_exists( 'dts_tyre_size_rim_token' ) ) {
	/**
	 * Normalise a rim value to the canonical "R17" / "R16C" form.
	 *
	 * Accepts "r17", "17", "R17C", "17c", "17.5".
	 */
	function dts_tyre_size_rim_token( $rim ) {
		$rim = strtoupper( trim( (string) $rim ) );

		if ( '' === $rim ) {
			return '';
		}

		$suffix = '';

		if ( 'C' === substr( $rim, -1 ) ) {
			$suffix = 'C';
			$rim    = substr( $rim, 0, -1 );
		}

		$rim = ltrim( $rim, 'R-' );

		if ( '' === $rim ) {
			return '';
		}

		return 'R' . $rim . $suffix;
	}
}

if ( ! function_exists( 'dts_tyre_size_is_no_profile' ) ) {
	/**
	 * Whether a size carries no aspect ratio at all.
	 *
	 * These are the commercial / LT / implement sizes (165 R16C, 1200 R24, 900 R18).
	 * The store keeps them with a height of "0".
	 */
	function dts_tyre_size_is_no_profile( $height ) {
		if ( null === $height || false === $height ) {
			return true;
		}

		$h = strtolower( trim( (string) $height ) );

		return '' === $h || '0' === $h || 'none' === $h || '-' === $h;
	}
}

if ( ! function_exists( 'dts_parse_tyre_size_string' ) ) {
	/**
	 * Parse a size string into its components.
	 *
	 * @return array {
	 *     @type string $width         Section width, or the X value for flotation.
	 *     @type string $height        Aspect ratio, flotation section width, or "0".
	 *     @type string $rim           Canonical rim: "R16", "R16C", "R17.5".
	 *     @type string $type          "metric" | "commercial" | "flotation".
	 *     @type bool   $commercial    True when a "C" suffix is present.
	 *     @type bool   $no_profile    True when the size has no aspect ratio.
	 *     @type bool   $flotation     True for X-value sizes (33x12.5 R17).
	 *     @type string $x_value       Flotation overall diameter ("33").
	 *     @type string $section_width Flotation section width ("12.5").
	 * }
	 */
	function dts_parse_tyre_size_string( $size_str ) {
		$result = array(
			'width'         => '',
			'height'        => '',
			'rim'           => '',
			'type'          => 'metric',
			'commercial'    => false,
			'no_profile'    => false,
			'flotation'     => false,
			'x_value'       => '',
			'section_width' => '',
		);

		$s = dts_normalize_tyre_size_token( $size_str );

		if ( '' === $s ) {
			return $result;
		}

		// Flotation / X-value: 33x-12.5-r17, 33x12.5r17, 35x-13.5-r22
		if ( preg_match( '/^(\d{1,3})-?x-?(\d+(?:\.\d+)?)-?r-?(\d{1,2}(?:\.\d)?)(c)?$/', $s, $m ) ) {
			$rim = dts_tyre_size_rim_token( $m[3] . ( isset( $m[4] ) ? $m[4] : '' ) );

			$result['flotation']     = true;
			$result['type']          = 'flotation';
			$result['x_value']       = $m[1];
			$result['section_width'] = $m[2];
			$result['width']         = $m[1];
			$result['height']        = $m[2];
			$result['rim']           = $rim;

			if ( 'C' === substr( $rim, -1 ) ) {
				$result['commercial'] = true;
				$result['type']      = 'commercial';
			}

			return $result;
		}

		// Metric with aspect ratio: 205-70-r16c, 215-70-r17.5, 700-15c
		if ( preg_match( '/^(\d{3,4})-(\d{2,3})-?r(\d{1,2}(?:\.\d)?)(c)?$/', $s, $m ) ) {
			$result['width']      = $m[1];
			$result['height']     = $m[2];
			$result['rim']        = dts_tyre_size_rim_token( $m[3] . ( isset( $m[4] ) ? strtoupper( $m[4] ) : '' ) );
			$result['commercial'] = ( 'C' === substr( $result['rim'], -1 ) );
			$result['type']       = $result['commercial'] ? 'commercial' : 'metric';

			return $result;
		}

		// Condensed metric: 24535r20, 22570r15c
		if ( preg_match( '/^(\d{3})(\d{2,3})r(\d{1,2}(?:\.\d)?)(c)?$/', $s, $m ) ) {
			$result['width']      = $m[1];
			$result['height']     = $m[2];
			$result['rim']        = dts_tyre_size_rim_token( $m[3] . ( isset( $m[4] ) ? strtoupper( $m[4] ) : '' ) );
			$result['commercial'] = ( 'C' === substr( $result['rim'], -1 ) );
			$result['type']       = $result['commercial'] ? 'commercial' : 'metric';

			return $result;
		}

		// No aspect ratio: 165-r16c, 1200-r24, 900-r18
		if ( preg_match( '/^(\d{3,4})-?r(\d{1,2}(?:\.\d)?)(c)?$/', $s, $m ) ) {
			$result['width']      = $m[1];
			$result['height']     = '0';
			$result['rim']        = dts_tyre_size_rim_token( $m[2] . ( isset( $m[3] ) ? strtoupper( $m[3] ) : '' ) );
			$result['no_profile'] = true;
			$result['commercial'] = ( 'C' === substr( $result['rim'], -1 ) );
			$result['type']       = $result['commercial'] ? 'commercial' : 'metric';

			return $result;
		}

		return $result;
	}
}

if ( ! function_exists( 'dts_tyre_size_slug' ) ) {
	/**
	 * Build the canonical URL segment for a parsed size.
	 *
	 *   metric     205-70-r16c
	 *   no profile 165-r16c / 1200-r24
	 *   flotation  33x12.5-r17
	 */
	function dts_tyre_size_slug( $parts ) {
		$width  = isset( $parts['width'] ) ? trim( (string) $parts['width'] ) : '';
		$height = isset( $parts['height'] ) ? trim( (string) $parts['height'] ) : '';
		$rim    = dts_tyre_size_rim_token( isset( $parts['rim'] ) ? $parts['rim'] : '' );

		if ( '' === $width || '' === $rim ) {
			return '';
		}

		$rim_dashed = strtolower( $rim );

		if ( ! empty( $parts['flotation'] ) ) {
			$sw = dts_tyre_size_is_no_profile( $height )
				? ( isset( $parts['section_width'] ) ? trim( (string) $parts['section_width'] ) : '' )
				: $height;

			return '' === $sw ? '' : $width . 'x' . $sw . '-' . $rim_dashed;
		}

		if ( dts_tyre_size_is_no_profile( $height ) ) {
			return $width . '-' . $rim_dashed;
		}

		return $width . '-' . $height . '-' . $rim_dashed;
	}
}

if ( ! function_exists( 'dts_tyre_size_url' ) ) {
	/**
	 * Full size-lookup URL for one or two sizes.
	 *
	 * Pass a second parsed array for staggered pages.
	 */
	function dts_tyre_size_url( $parts, $parts_rear = null ) {
		$base  = dts_get_search_tyres_slug();
		$front = dts_tyre_size_slug( $parts );

		if ( '' === $front ) {
			return home_url( '/' . trim( $base, '/' ) . '/' );
		}

		$path = $front;

		if ( ! empty( $parts_rear ) ) {
			$rear = dts_tyre_size_slug( $parts_rear );

			if ( '' !== $rear ) {
				$path .= '/' . $rear;
			}
		}

		return home_url( '/' . trim( $base, '/' ) . '/' . $path . '/' );
	}
}

if ( ! function_exists( 'dts_format_tyre_size' ) ) {
	/**
	 * Human-readable size label.
	 *
	 *   flotation   33X12.5 R17
	 *   C + profile 205/70 R16C
	 *   no profile  165 R16C   (pass '' as $profile_placeholder for "165 R16C")
	 *   metric      205/55 R16
	 *
	 * @param array|string $size               Parsed array, or a raw size string.
	 * @param string       $profile_placeholder Text used in place of a missing aspect ratio.
	 */
	function dts_format_tyre_size( $size, $profile_placeholder = '' ) {
		if ( ! is_array( $size ) ) {
			$size = dts_parse_tyre_size_string( $size );
		}

		if ( empty( $size['width'] ) || empty( $size['rim'] ) ) {
			return '';
		}

		$width  = trim( (string) $size['width'] );
		$height = isset( $size['height'] ) ? trim( (string) $size['height'] ) : '';
		$rim    = dts_tyre_size_rim_token( $size['rim'] );

		if ( ! empty( $size['flotation'] ) || false !== stripos( $width, 'x' ) ) {
			$x = rtrim( $width, 'xX' );

			if ( '' === $x ) {
				$x = isset( $size['x_value'] ) ? trim( (string) $size['x_value'] ) : '';
			}

			$sw = $height;

			if ( dts_tyre_size_is_no_profile( $sw ) ) {
				$sw = isset( $size['section_width'] ) ? trim( (string) $size['section_width'] ) : '';
			}

			return $x . 'X' . ( '' !== $sw ? $sw : '' ) . ' ' . $rim;
		}

		if ( dts_tyre_size_is_no_profile( $height ) ) {
			if ( '' !== (string) $profile_placeholder ) {
				return $width . ' ' . $profile_placeholder . ' ' . $rim;
			}

			return $width . ' ' . $rim;
		}

		return $width . '/' . $height . ' ' . $rim;
	}
}

if ( ! function_exists( 'dts_tyre_size_meta_query' ) ) {
	/**
	 * Width / height / rim meta query shared by the size templates and every
	 * filter endpoint, so a page and its first filter interaction always return
	 * the same products.
	 *
	 * Deliberately tolerant of how products happen to be stored:
	 *  - rim matches both "R16C" and "16C"
	 *  - flotation matches widths "33X", "33x", "33" plus the WooCommerce
	 *    _width / _height dimension fallbacks, and the raw "33x12.5 R17" title form
	 *  - sizes without an aspect ratio match heights stored as '', '0', 'none', '-'
	 *    or a missing height altogether
	 *
	 * @return array Meta query fragment with an AND relation.
	 */
	function dts_tyre_size_meta_query( $width, $height, $rim, $is_flotation = false, $is_commercial = false ) {
		$mq = array( 'relation' => 'AND' );

		$width  = trim( (string) $width );
		$height = ( null === $height ) ? '' : trim( (string) $height );
		$rim    = dts_tyre_size_rim_token( $rim );
		$rim_nd = $rim ? substr( $rim, 1 ) : '';

		// Width / X-value.
		if ( $is_flotation ) {
			// Accept the decorated "33X" form and reduce it to the bare X value.
			$width = rtrim( $width, 'xX' );
		}

		$width_values = array( $width );

		if ( $is_flotation ) {
			// Flotation products store the X value with a trailing "X" in tyre_width.
			$width_values[] = $width . 'X';
			$width_values[] = $width . 'x';
		}

		$width_values = array_values( array_unique( array_filter( $width_values, 'strlen' ) ) );

		$width_clause = array( 'relation' => 'OR' );

		foreach ( $width_values as $wv ) {
			$width_clause[] = array(
				'key'     => 'tyre_width',
				'value'   => $wv,
				'compare' => '=',
			);
			$width_clause[] = array(
				'key'     => '_width',
				'value'   => $wv,
				'compare' => '=',
			);
		}

		$mq[] = $width_clause;

		// Height / section width.
		$no_profile = dts_tyre_size_is_no_profile( $height );

		if ( $no_profile ) {
			$empty_heights  = array( '', '0', 'none', '-' );
			$height_clause  = array( 'relation' => 'OR' );

			foreach ( array( 'tyre_height', '_height' ) as $key ) {
				$height_clause[] = array(
					'relation' => 'OR',
					array(
						'key'     => $key,
						'value'   => $empty_heights,
						'compare' => 'IN',
					),
					array(
						'key'     => $key,
						'compare' => 'NOT EXISTS',
					),
				);
			}

			$mq[] = $height_clause;
		} else {
			$height_clause = array( 'relation' => 'OR' );
			$height_values  = array( $height );

			if ( $is_flotation ) {
				// Flotation section width is a decimal ("12.5"); also allow "12.50".
				$height_values[] = number_format( (float) $height, 2, '.', '' );
				$height_values[] = rtrim( rtrim( number_format( (float) $height, 2, '.', '' ), '0' ), '.' );
			}

			$height_values = array_values( array_unique( array_filter( $height_values, 'strlen' ) ) );

			foreach ( $height_values as $hv ) {
				$height_clause[] = array(
					'key'     => 'tyre_height',
					'value'   => $hv,
					'compare' => '=',
				);
				$height_clause[] = array(
					'key'     => '_height',
					'value'   => $hv,
					'compare' => '=',
				);
			}

			$mq[] = $height_clause;
		}

		// Rim.
		$rim_clause = array( 'relation' => 'OR' );
		$rim_values = array();

		if ( $rim ) {
			$rim_values[] = $rim;

			if ( '' !== $rim_nd && $rim_nd !== $rim ) {
				$rim_values[] = $rim_nd;
			}
		}

		// Flotation products are frequently stored with the whole size in the
		// rim field ("33x12.5 R17"), so match that form too.
		if ( $is_flotation && '' !== $rim && '' !== $width ) {
			$sw = $no_profile ? '' : $height;

			if ( '' !== $sw ) {
				$rim_values[] = $width . 'x' . $sw . ' ' . $rim;
				$rim_values[] = strtolower( $width . 'x' . $sw . ' ' . $rim );
				$rim_values[] = $width . 'X' . $sw . ' ' . $rim;
				$rim_values[] = $width . 'x' . $sw . $rim;
			}
		}

		$rim_values = array_values( array_unique( array_filter( $rim_values, 'strlen' ) ) );

		if ( ! empty( $rim_values ) ) {
			$rim_clause[] = array(
				'key'     => 'tyre_rim_size',
				'value'   => $rim_values,
				'compare' => 'IN',
			);
		}

		$mq[] = $rim_clause;

		return $mq;
	}
}

if ( ! function_exists( 'dts_format_size_parts' ) ) {
	/** Compact label for a width/height/rim triple, used by pair payloads. */
	function dts_format_size_parts( $width, $height, $rim ) {
		$height_disp = dts_tyre_size_is_no_profile( $height ) ? '' : trim( (string) $height );

		return trim( $width . ( '' !== $height_disp ? '/' . $height_disp : '' ) . ' ' . strtoupper( (string) $rim ) );
	}
}

if ( ! function_exists( 'dts_strip_size_from_title' ) ) {
	/**
	 * Remove the size from a product title so two sizes of the same product
	 * share a comparison key. Pairing relies on this.
	 */
	function dts_strip_size_from_title( $title, $product_id ) {
		$tw = get_post_meta( $product_id, 'tyre_width', true );
		$th = get_post_meta( $product_id, 'tyre_height', true );
		$tr = get_post_meta( $product_id, 'tyre_rim_size', true );

		if ( ! $tw ) {
			$tw = get_post_meta( $product_id, '_width', true );
		}
		if ( ! $th ) {
			$th = get_post_meta( $product_id, '_height', true );
		}

		$clean = $title;

		if ( $tw && $th && $tr ) {
			$patterns = array(
				$tw . '/' . $th . 'R' . ltrim( (string) $tr, 'Rr' ),
				$tw . '/' . $th . ' R' . ltrim( (string) $tr, 'Rr' ),
				$tw . '/' . $th . ' ' . $tr,
			);

			foreach ( $patterns as $pattern ) {
				$clean = str_replace( $pattern, '', $clean );
			}
		}

		return strtolower( trim( wp_strip_all_tags( $clean ) ) );
	}
}

if ( ! function_exists( 'dts_get_pair_data' ) ) {
	/**
	 * Reduce a list of product IDs to the fields pairing needs.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array List of pair records.
	 */
	function dts_get_pair_data( $ids ) {
		$products = array();

		foreach ( (array) $ids as $pid ) {
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;

			if ( ! $product ) {
				continue;
			}

			$brand_terms = wp_get_post_terms( $pid, 'product_brand' );
			$brand_slug  = '';
			$brand_name  = '';

			if ( ! is_wp_error( $brand_terms ) && ! empty( $brand_terms ) ) {
				$brand_slug = $brand_terms[0]->slug;
				$brand_name = $brand_terms[0]->name;
			}

			$products[] = array(
				'id'         => (int) $pid,
				'brand_slug' => $brand_slug,
				'brand_name' => $brand_name,
				'title'      => $product->get_name(),
				'model_key'  => dts_strip_size_from_title( $product->get_name(), $pid ),
				'run_flat'   => dts_is_run_flat_value( get_post_meta( $pid, 'tyre_run_flat', true ) ),
				'price'      => (float) $product->get_price(),
				'warranty'   => get_post_meta( $pid, 'warranty', true ),
				'year'       => get_post_meta( $pid, 'year_manufacture', true ),
				'promo'      => strtolower( (string) get_post_meta( $pid, 'buy_3_get_1', true ) ),
			);
		}

		return $products;
	}
}

if ( ! function_exists( 'dts_pair_left_and_right' ) ) {
	/**
	 * Match front products to rear products for a staggered fitment.
	 *
	 * A pair is valid when brand, run-flat flag and size-stripped model name all
	 * match. Each rear product is consumed at most once so a set of two front and
	 * two rear products yields exactly two pairs.
	 *
	 * @param array $left_products  Front records from dts_get_pair_data().
	 * @param array $right_products Rear records from dts_get_pair_data().
	 * @return array List of array( 'left' => record, 'right' => record ).
	 */
	function dts_pair_left_and_right( $left_products, $right_products ) {
		$pairs      = array();
		$used_right = array();

		foreach ( $left_products as $left ) {
			foreach ( $right_products as $right_idx => $right ) {
				if ( in_array( $right_idx, $used_right, true ) ) {
					continue;
				}
				if ( $left['brand_slug'] !== $right['brand_slug'] ) {
					continue;
				}
				if ( $left['run_flat'] !== $right['run_flat'] ) {
					continue;
				}
				if ( $left['model_key'] !== $right['model_key'] ) {
					continue;
				}

				$pairs[]      = array(
					'left'  => $left,
					'right' => $right,
				);
				$used_right[] = $right_idx;

				break;
			}
		}

		return $pairs;
	}
}

if ( ! function_exists( 'dts_get_tyre_combinations_data' ) ) {
	/**
	 * All width/height/rim combinations that have at least one product, cached
	 * because the size lookup reads it on every interaction.
	 *
	 * @return array List of array( 'width', 'height', 'rim', 'count', 'flotation' ).
	 */
	function dts_get_tyre_combinations_data() {
		$cached = get_transient( 'dts_tyre_combinations' );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT w.meta_value AS width, h.meta_value AS height, r.meta_value AS rim
			 FROM {$wpdb->postmeta} w
			 INNER JOIN {$wpdb->postmeta} h ON h.post_id = w.post_id AND h.meta_key = 'tyre_height'
			 INNER JOIN {$wpdb->postmeta} r ON r.post_id = w.post_id AND r.meta_key = 'tyre_rim_size'
			 INNER JOIN {$wpdb->posts} p ON p.ID = w.post_id
			 WHERE w.meta_key = 'tyre_width'
			   AND w.meta_value != ''
			   AND r.meta_value != ''
			   AND p.post_status = 'publish'
			 GROUP BY w.meta_value, h.meta_value, r.meta_value
			 ORDER BY CAST(w.meta_value AS UNSIGNED) ASC",
			ARRAY_A
		);

		$combinations = array();
		$counts       = array();

		foreach ( (array) $rows as $row ) {
			$width  = trim( (string) $row['width'] );
			$height = trim( (string) $row['height'] );
			$rim    = trim( (string) $row['rim'] );

			if ( '' === $width || '' === $rim ) {
				continue;
			}

			$flotation = ( false !== stripos( $width, 'x' ) );

			$key = strtolower( $width . '|' . $height . '|' . $rim );

			if ( isset( $counts[ $key ] ) ) {
				++$counts[ $key ];
				continue;
			}

			$counts[ $key ] = 1;

			$combinations[] = array(
				'width'     => $flotation ? rtrim( $width, 'xX' ) : $width,
				'height'    => $height,
				'rim'       => dts_tyre_size_rim_token( $rim ),
				'flotation' => $flotation,
			);
		}

		foreach ( $combinations as $idx => $combination ) {
			$key = strtolower( $combination['width'] . '|' . $combination['height'] . '|' . $combination['rim'] );
			$combinations[ $idx ]['count'] = isset( $counts[ $key ] ) ? $counts[ $key ] : 0;
		}

		set_transient( 'dts_tyre_combinations', $combinations, DAY_IN_SECONDS );

		return $combinations;
	}
}

add_action( 'save_post_product', 'dts_clear_tyre_combinations_cache' );
if ( ! function_exists( 'dts_clear_tyre_combinations_cache' ) ) {
	/** Invalidate the combinations cache whenever a product changes. */
	function dts_clear_tyre_combinations_cache() {
		delete_transient( 'dts_tyre_combinations' );
	}
}
