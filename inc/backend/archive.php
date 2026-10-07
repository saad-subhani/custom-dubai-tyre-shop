<?php
/**
 * Archive integration: query vars, rewrite rules, URL parsing and WooCommerce
 * archive filtering.
 *
 * The storefront addresses every generated archive through a pretty URL. This
 * module owns the request side of that contract: it registers the query vars,
 * teaches WordPress the URL shapes, normalises the filter segments that arrive
 * in those URLs into request filters, and applies the active filters to the
 * main product query.
 *
 * Filtering deliberately reuses dts_build_tyre_filter_args() /
 * dts_build_battery_filter_args() so an archive page and the JSON grid
 * endpoints can never drift apart: both resolve the same facet set.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_register_archive_query_vars' ) ) {
	/**
	 * Query vars used by the generated archive URLs.
	 *
	 * @param string[] $vars Registered query vars.
	 * @return string[]
	 */
	function dts_register_archive_query_vars( $vars ) {
		$vars[] = 'make';
		$vars[] = 'model';
		$vars[] = 'year';
		$vars[] = 'engine';
		$vars[] = 'tyre_size';
		$vars[] = 'tyre_size_r';
		$vars[] = 't_w';
		$vars[] = 't_h';
		$vars[] = 't_r';
		$vars[] = 't_wr';
		$vars[] = 't_hr';
		$vars[] = 't_rr';
		$vars[] = 'filter_path';
		$vars[] = 'battery_brand';
		$vars[] = 'dts_tax_slug';

		return $vars;
	}
}
add_filter( 'query_vars', 'dts_register_archive_query_vars' );

if ( ! function_exists( 'dts_get_tyre_by_vehicle_url' ) ) {
	/**
	 * Build the vehicle archive URL for a fitment.
	 *
	 * @param string $make   Make slug.
	 * @param string $model  Model slug.
	 * @param string $year   Year.
	 * @param string $engine Engine slug.
	 * @return string
	 */
	function dts_get_tyre_by_vehicle_url( $make, $model = '', $year = '', $engine = '' ) {
		$base = home_url( '/' . dts_get_tyre_by_vehicle_slug() . '/' );

		if ( get_option( 'permalink_structure' ) ) {
			$path = rtrim( $base, '/' ) . '/' . sanitize_title( (string) $make );

			if ( ! empty( $model ) ) {
				$path .= '/' . sanitize_title( (string) $model );
			}
			if ( ! empty( $year ) ) {
				$path .= '/' . rawurlencode( (string) $year );
			}
			if ( ! empty( $engine ) ) {
				$path .= '/' . sanitize_title( (string) $engine );
			}

			return user_trailingslashit( $path );
		}

		return add_query_arg(
			array(
				'make'   => sanitize_title( (string) $make ),
				'model'  => ! empty( $model ) ? sanitize_title( (string) $model ) : false,
				'year'   => ! empty( $year ) ? rawurlencode( (string) $year ) : false,
				'engine' => ! empty( $engine ) ? sanitize_title( (string) $engine ) : false,
			),
			$base
		);
	}
}

if ( ! function_exists( 'dts_get_search_tyres_base_url' ) ) {
	/** Permalink of the page serving the tyre-size archive. */
	function dts_get_search_tyres_base_url() {
		return home_url( '/' . dts_get_search_tyres_slug() . '/' );
	}
}

if ( ! function_exists( 'dts_register_archive_rewrite_rules' ) ) {
	/**
	 * Register every generated-archive URL shape.
	 *
	 * Slugs are read from the configured page settings rather than hard-coded so
	 * a change to the page slug does not require a code change.
	 */
	function dts_register_archive_rewrite_rules() {
		$size    = dts_get_search_tyres_slug();
		$vehicle = dts_get_tyre_by_vehicle_slug();
		$battery = dts_get_battery_slug();
		$shop    = get_option( 'woocommerce_shop_page_id' ) ? trim( (string) get_page_uri( (int) get_option( 'woocommerce_shop_page_id' ) ), '/' ) : 'shop';

		// Vehicle archives, most specific first.
		add_rewrite_rule(
			$vehicle . '/([^/]+)/([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]&year=$matches[3]&engine=$matches[4]',
			'top'
		);
		add_rewrite_rule(
			$vehicle . '/([^/]+)/([^/]+)/([^/]+)/?$',
			'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]&year=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			$vehicle . '/([^/]+)/([^/]+)/?$',
			'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			$vehicle . '/([^/]+)/?$',
			'index.php?pagename=' . $vehicle . '&make=$matches[1]',
			'top'
		);

		// Tyre-size archives. A size pair reads as staggered; a lone size as single.
		add_rewrite_rule(
			$size . '/([0-9][^/]+)/([0-9][^/]+)/filter/(.+)/?$',
			'index.php?pagename=' . $size . '&tyre_size=$matches[1]&tyre_size_r=$matches[2]&filter_path=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			$size . '/([0-9][^/]+)/([0-9][^/]+)/?$',
			'index.php?pagename=' . $size . '&tyre_size=$matches[1]&tyre_size_r=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			$size . '/([^/]+)/filter/(.+)/?$',
			'index.php?pagename=' . $size . '&tyre_size=$matches[1]&filter_path=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			$size . '/([^/]+)/?$',
			'index.php?pagename=' . $size . '&tyre_size=$matches[1]',
			'top'
		);

		// Shop and battery archives carrying a filter path.
		add_rewrite_rule(
			$shop . '/filter/(.+)/?$',
			'index.php?post_type=product&filter_path=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$battery . '/filter/(.+)/?$',
			'index.php?product_cat=' . $battery . '&filter_path=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			$battery . '/([^/]+)/filter/(.+)/?$',
			'index.php?product_cat=' . $battery . '&battery_brand=$matches[1]&filter_path=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			$battery . '/([^/]+)/?$',
			'index.php?product_cat=' . $battery . '&battery_brand=$matches[1]',
			'top'
		);

		// Category archives carrying a filter path.
		add_rewrite_rule(
			'product-category/(.+)/filter/(.+)/?$',
			'index.php?product_cat=$matches[1]&filter_path=$matches[2]',
			'top'
		);

		// Clean taxonomy URLs. Registered last so the specific rules above win.
		add_rewrite_rule(
			'([^/]+)/filter/(.+)/?$',
			'index.php?dts_tax_slug=$matches[1]&filter_path=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'([^/]+)/page/?([0-9]{1,})/?$',
			'index.php?dts_tax_slug=$matches[1]&paged=$matches[2]',
			'top'
		);
		add_rewrite_rule(
			'([^/]+)/?$',
			'index.php?dts_tax_slug=$matches[1]',
			'top'
		);
	}
}
add_action( 'init', 'dts_register_archive_rewrite_rules' );

if ( ! function_exists( 'dts_inject_archive_pagination_rules' ) ) {
	/**
	 * Guarantee the paginated variants of every generated URL.
	 *
	 * WordPress only writes these rules on a flush. Without them a direct hit on
	 * /car-tyres/page/2/ or /tyres-by-vehicle/audi/page/2/ falls through to the
	 * generic pagename rule and 404s, so they are injected on every request.
	 *
	 * @param array $rules Existing rewrite rules.
	 * @return array
	 */
	function dts_inject_archive_pagination_rules( $rules ) {
		$size    = dts_get_search_tyres_slug();
		$vehicle = dts_get_tyre_by_vehicle_slug();

		$extra = array(
			$vehicle . '/([^/]+)/([^/]+)/([^/]+)/([^/]+)/page/?([0-9]{1,})/?$'  => 'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]&year=$matches[3]&engine=$matches[4]&paged=$matches[5]',
			$vehicle . '/([^/]+)/([^/]+)/([^/]+)/page/?([0-9]{1,})/?$'        => 'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]&year=$matches[3]&paged=$matches[4]',
			$vehicle . '/([^/]+)/([^/]+)/page/?([0-9]{1,})/?$'                => 'index.php?pagename=' . $vehicle . '&make=$matches[1]&model=$matches[2]&paged=$matches[3]',
			$vehicle . '/([^/]+)/page/?([0-9]{1,})/?$'                          => 'index.php?pagename=' . $vehicle . '&make=$matches[1]&paged=$matches[2]',
			$size . '/([^/]+)/filter/(.+)/page/?([0-9]{1,})/?$'                 => 'index.php?pagename=' . $size . '&tyre_size=$matches[1]&filter_path=$matches[2]&paged=$matches[3]',
			$size . '/([^/]+)/([^/]+)/page/?([0-9]{1,})/?$'                     => 'index.php?pagename=' . $size . '&tyre_size=$matches[1]&tyre_size_r=$matches[2]&paged=$matches[3]',
			$size . '/([^/]+)/page/?([0-9]{1,})/?$'                             => 'index.php?pagename=' . $size . '&tyre_size=$matches[1]&paged=$matches[2]',
			'([^/]+)/page/?([0-9]{1,})/?$'                                      => 'index.php?dts_tax_slug=$matches[1]&paged=$matches[2]',
		);

		return $extra + $rules;
	}
}
add_filter( 'rewrite_rules_array', 'dts_inject_archive_pagination_rules', 1 );

if ( ! function_exists( 'dts_maybe_flush_archive_rewrites' ) ) {
	/** Flush once after the rule set first lands, keyed on a version option. */
	function dts_maybe_flush_archive_rewrites() {
		$version = 'v1';

		if ( get_option( 'dts_rewrite_version' ) !== $version ) {
			flush_rewrite_rules( false );
			update_option( 'dts_rewrite_version', $version );
		}
	}
}
add_action( 'init', 'dts_maybe_flush_archive_rewrites', 99 );

if ( ! function_exists( 'dts_handle_clean_tax_urls' ) ) {
	/**
	 * Resolve the catch-all single-segment rule against real content.
	 *
	 * The clean-tax rule is deliberately greedy, so it has to hand back anything
	 * that turns out to be a page, a post or a product rather than pretending
	 * every bare slug is a taxonomy archive.
	 *
	 * @param array $query_vars Resolved query vars.
	 * @return array
	 */
	function dts_handle_clean_tax_urls( $query_vars ) {
		if ( empty( $query_vars['dts_tax_slug'] ) ) {
			return $query_vars;
		}

		$slug = sanitize_title( $query_vars['dts_tax_slug'] );
		unset( $query_vars['dts_tax_slug'] );

		if ( '' === $slug ) {
			return $query_vars;
		}

		if ( get_page_by_path( $slug ) ) {
			$query_vars['pagename'] = $slug;

			return $query_vars;
		}

		$term = get_term_by( 'slug', $slug, 'product_cat' );

		if ( $term && ! is_wp_error( $term ) ) {
			$query_vars['product_cat'] = $slug;

			return $query_vars;
		}

		// Both brand taxonomies are checked: a store may still have leftovers in
		// the legacy one, and those archives should keep resolving.
		$brand_taxonomies = array_filter( array( 'product_brand', 'pa_brand' ), 'taxonomy_exists' );

		foreach ( $brand_taxonomies as $brand_tax ) {
			$term = get_term_by( 'slug', $slug, $brand_tax );

			if ( $term && ! is_wp_error( $term ) ) {
				$query_vars[ $brand_tax ] = $slug;
				$query_vars['post_type']  = 'product';

				return $query_vars;
			}
		}
		$blog_post = get_posts(
			array(
				'name'        => $slug,
				'post_type'   => 'post',
				'post_status' => 'publish',
				'numberposts' => 1,
				'fields'      => 'ids',
			)
		);

		if ( ! empty( $blog_post ) ) {
			$query_vars['p']         = $blog_post[0];
			$query_vars['post_type'] = 'post';

			return $query_vars;
		}

		$query_vars['pagename'] = $slug;

		return $query_vars;
	}
}
add_filter( 'request', 'dts_handle_clean_tax_urls' );

if ( ! function_exists( 'dts_handle_tyre_size_pagination' ) ) {
	/**
	 * Unfold a paginated tyre-size path into its query vars.
	 *
	 * WordPress resolves /tyres-size/255-45-r20/page/2/ as a single nested
	 * pagename, which hides both the size and the page number from the archive.
	 *
	 * @param array $query_vars Resolved query vars.
	 * @return array
	 */
	function dts_handle_tyre_size_pagination( $query_vars ) {
		if ( empty( $query_vars['pagename'] ) || empty( $query_vars['paged'] ) ) {
			return $query_vars;
		}

		$pagename = $query_vars['pagename'];

		if ( false === strpos( $pagename, '/' ) ) {
			return $query_vars;
		}

		$slug = dts_get_search_tyres_slug();

		if ( '' === $slug || 0 !== strpos( $pagename, $slug . '/' ) ) {
			return $query_vars;
		}

		$parts  = explode( '/', substr( $pagename, strlen( $slug ) + 1 ) );
		$query_vars['pagename'] = $slug;

		if ( ! empty( $parts[0] ) ) {
			$query_vars['tyre_size'] = $parts[0];
		}

		if ( isset( $parts[1] ) && 'filter' === $parts[1] ) {
			$query_vars['filter_path'] = implode( '/', array_slice( $parts, 2 ) );
		} elseif ( isset( $parts[1] ) && '' !== $parts[1] ) {
			$query_vars['tyre_size_r'] = $parts[1];
		}

		return $query_vars;
	}
}
add_filter( 'request', 'dts_handle_tyre_size_pagination', 5 );

if ( ! function_exists( 'dts_redirect_legacy_tax_urls' ) ) {
	/**
	 * Permanently redirect the old taxonomy bases onto their clean equivalents.
	 */
	function dts_redirect_legacy_tax_urls() {
		if ( is_admin() ) {
			return;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';

		foreach ( array( 'product-category', 'brand' ) as $legacy_base ) {
			if ( preg_match( '#^/' . $legacy_base . '/([^/]+?)(/.+)?/?$#i', $request_uri, $matches ) ) {
				$slug   = sanitize_title( $matches[1] );
				$suffix = ! empty( $matches[2] ) ? rtrim( $matches[2], '/' ) : '';

				wp_redirect( home_url( '/' . $slug . $suffix . '/' ), 301 );
				exit;
			}
		}
	}
}
add_action( 'template_redirect', 'dts_redirect_legacy_tax_urls' );

if ( ! function_exists( 'dts_split_filter_path_values' ) ) {
	/**
	 * Split a filter-path value into its members.
	 *
	 * A single value may still be a list because slugs contain hyphens, so only
	 * a comma is treated as a separator.
	 *
	 * @param string $value Raw path value.
	 * @return string[]
	 */
	function dts_split_filter_path_values( $value ) {
		$parts = explode( ',', $value );

		return array_values( array_filter( array_map( 'sanitize_text_field', $parts ) ) );
	}
}

if ( ! function_exists( 'dts_parse_filter_path' ) ) {
	/**
	 * Turn the filter segment of a generated URL into request filters.
	 *
	 * Reads /filter/key/value/key/value/ and populates the same request filters
	 * the grid endpoints read, so a shared link and a direct query string
	 * produce an identical result set.
	 */
	function dts_parse_filter_path() {
		$path = get_query_var( 'filter_path' );

		if ( empty( $path ) ) {
			return;
		}

		$segments = explode( '/', untrailingslashit( $path ) );
		$count    = count( $segments );

		for ( $i = 0; $i < $count - 1; $i += 2 ) {
			$key   = $segments[ $i ];
			$value = isset( $segments[ $i + 1 ] ) ? $segments[ $i + 1 ] : '';

			if ( '' === $key || '' === $value ) {
				continue;
			}

			switch ( $key ) {
				case 'brand':
				case 'brands':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_brand'] = array_merge( isset( $_GET['filter_brand'] ) ? (array) $_GET['filter_brand'] : array(), $values );
					break;

				case 'year':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_year'] = array_merge( isset( $_GET['filter_year'] ) ? (array) $_GET['filter_year'] : array(), $values );
					break;

				case 'run_flat':
					$values = dts_normalize_run_flat_filter( dts_split_filter_path_values( $value ) );
					$_GET['filter_run_flat'] = array_merge(
						isset( $_GET['filter_run_flat'] ) ? (array) $_GET['filter_run_flat'] : array(),
						$values
					);
					break;

				case 'offer':
					$values = array_values( array_intersect( dts_split_filter_path_values( $value ), array( 'yes', 'no' ) ) );
					if ( ! empty( $values ) ) {
						$_GET['filter_promo'] = array_merge( isset( $_GET['filter_promo'] ) ? (array) $_GET['filter_promo'] : array(), $values );
					}
					break;

				case 'price':
					if ( false !== strpos( $value, '-' ) ) {
						$bounds = explode( '-', $value );

						if ( 2 === count( $bounds ) ) {
							$_GET['price_min'] = (float) $bounds[0];
							$_GET['price_max'] = (float) $bounds[1];
						}
					} else {
						$_GET['orderby'] = 'low' === $value ? 'price' : 'price-desc';
					}
					break;

				case 'origin':
				case 'origins':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_origin'] = array_merge( isset( $_GET['filter_origin'] ) ? (array) $_GET['filter_origin'] : array(), $values );
					break;

				case 'capacity':
				case 'capacities':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_capacity'] = array_merge( isset( $_GET['filter_capacity'] ) ? (array) $_GET['filter_capacity'] : array(), $values );
					break;

				case 'reserve':
				case 'reserve_capacity':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_reserve'] = array_merge( isset( $_GET['filter_reserve'] ) ? (array) $_GET['filter_reserve'] : array(), $values );
					break;

				case 'cca':
					$values = dts_split_filter_path_values( $value );
					$_GET['filter_cca'] = array_merge( isset( $_GET['filter_cca'] ) ? (array) $_GET['filter_cca'] : array(), $values );
					break;

				case 'sale':
					if ( '1' === $value ) {
						$_GET['on_sale'] = '1';
					}
					break;

				case 'page':
					set_query_var( 'paged', absint( $value ) );
					break;

				case 'length':
					$values = array_map( 'sanitize_text_field', explode( '-', $value ) );
					$_GET['filter_length'] = array_merge( isset( $_GET['filter_length'] ) ? (array) $_GET['filter_length'] : array(), $values );
					break;

				case 'width':
					$values = array_map( 'sanitize_text_field', explode( '-', $value ) );
					$_GET['filter_width'] = array_merge( isset( $_GET['filter_width'] ) ? (array) $_GET['filter_width'] : array(), $values );
					break;

				case 'height':
					$values = array_map( 'sanitize_text_field', explode( '-', $value ) );
					$_GET['filter_height'] = array_merge( isset( $_GET['filter_height'] ) ? (array) $_GET['filter_height'] : array(), $values );
					break;

				case 'sort':
					$_GET['orderby'] = sanitize_text_field( $value );
					break;
			}
		}
	}
}
add_action( 'wp', 'dts_parse_filter_path' );

if ( ! function_exists( 'dts_parse_battery_brand_path' ) ) {
	/**
	 * Fold a battery brand path segment into the active brand filter.
	 *
	 * /batteries/bronze/ is a brand archive in its own right, so the segment has
	 * to constrain the query rather than just label the page.
	 */
	function dts_parse_battery_brand_path() {
		$brand = get_query_var( 'battery_brand' );

		if ( empty( $brand ) ) {
			return;
		}

		$brand = sanitize_text_field( wp_unslash( $brand ) );

		if ( '' === $brand ) {
			return;
		}

		$current = isset( $_GET['filter_brand'] ) && is_array( $_GET['filter_brand'] ) ? $_GET['filter_brand'] : array();

		$_GET['filter_brand'] = array_values( array_unique( array_merge( $current, array( $brand ) ) ) );
	}
}
add_action( 'wp', 'dts_parse_battery_brand_path' );

if ( ! function_exists( 'dts_vehicle_query_var_unusable' ) ) {
	/**
	 * Whether a vehicle query var is missing or still an unsubstituted capture.
	 *
	 * A stale rewrite ruleset leaves the query var holding a literal "$n" group
	 * reference, which is neither a valid make nor an empty value, so any
	 * placeholder shape is treated as unusable rather than trusted.
	 *
	 * @param mixed $value Raw query var value.
	 * @return bool
	 */
	function dts_vehicle_query_var_unusable( $value ) {
		$value = trim( (string) $value );

		return '' === $value || (bool) preg_match( '/^\$\d+$/', $value );
	}
}

if ( ! function_exists( 'dts_parse_vehicle_url_fallback' ) ) {
	/**
	 * Recover vehicle fitment segments when rewrite substitution did not happen.
	 *
	 * Reading the request path directly is the cheapest way to keep an archive
	 * correct on a site whose ruleset has not been flushed since a slug change.
	 * A segment is only written when its var is still unusable, so a partially
	 * substituted request keeps whatever the rewrite did resolve.
	 */
	function dts_parse_vehicle_url_fallback() {
		$segments = array(
			1 => array( 'make', 'sanitize_title' ),
			2 => array( 'model', 'sanitize_title' ),
			3 => array( 'year', 'sanitize_text_field' ),
			4 => array( 'engine', 'sanitize_title' ),
		);

		$needs_recovery = false;

		foreach ( $segments as $target ) {
			if ( dts_vehicle_query_var_unusable( get_query_var( $target[0] ) ) ) {
				$needs_recovery = true;
			}
		}

		if ( ! $needs_recovery ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		$path = trim( (string) $path, '/' );

		if ( '' === $path ) {
			return;
		}

		$parts    = explode( '/', $path );
		$slug_pos = array_search( dts_get_tyre_by_vehicle_slug(), $parts, true );

		if ( false === $slug_pos ) {
			return;
		}

		foreach ( $segments as $offset => $target ) {
			list( $var, $sanitizer ) = $target;

			if ( ! isset( $parts[ $slug_pos + $offset ] ) ) {
				break;
			}

			if ( ! dts_vehicle_query_var_unusable( get_query_var( $var ) ) ) {
				continue;
			}

			set_query_var( $var, call_user_func( $sanitizer, $parts[ $slug_pos + $offset ] ) );
		}
	}
}
add_action( 'wp', 'dts_parse_vehicle_url_fallback' );

if ( ! function_exists( 'dts_get_request_filter_param' ) ) {
	/**
	 * Read a request filter that may arrive as a scalar or an array.
	 *
	 * @param string $key Request key.
	 * @return array
	 */
	function dts_get_request_filter_param( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only public archive filter.
		if ( ! isset( $_GET[ $key ] ) ) {
			return array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = wp_unslash( $_GET[ $key ] );

		if ( is_array( $raw ) ) {
			$raw = array_map( 'sanitize_text_field', $raw );
		} else {
			$raw = sanitize_text_field( (string) $raw );
		}

		return array_values( array_filter( (array) $raw, 'strlen' ) );
	}
}

if ( ! function_exists( 'dts_get_request_orderby' ) ) {
	/** Active sort for the current request, normalised to a known key. */
	function dts_get_request_orderby() {
		$orderby = dts_get_request_filter_param( 'orderby' );

		return dts_normalize_orderby( $orderby ? $orderby[0] : '' );
	}
}

if ( ! function_exists( 'dts_get_request_size_parts' ) ) {
	/**
	 * Read the size constraint carried by a tyre-size URL.
	 *
	 * The grid endpoints receive a size in the request body, while a size page
	 * carries it in a query var as a slug, so both routes resolve to the same
	 * parsed size.
	 *
	 * @return array|null Front size parts, or null when the request has no size.
	 */
	function dts_get_request_size_parts() {
		$front = dts_parse_tyre_size_string( (string) get_query_var( 'tyre_size' ) );

		if ( empty( $front['width'] ) ) {
			return null;
		}

		return $front;
	}
}

if ( ! function_exists( 'dts_get_request_rear_size_parts' ) ) {
	/**
	 * Read the rear size of a staggered tyre-size URL.
	 *
	 * @return array|null
	 */
	function dts_get_request_rear_size_parts() {
		$rear = dts_parse_tyre_size_string( (string) get_query_var( 'tyre_size_r' ) );

		return empty( $rear['width'] ) ? null : $rear;
	}
}

if ( ! function_exists( 'dts_get_request_filter_params' ) ) {
	/**
	 * Normalise the request into the parameter shape the filter builders expect.
	 *
	 * @param string $type Product family, `tyre` or `battery`.
	 * @return array
	 */
	function dts_get_request_filter_params( $type = 'tyre' ) {
		$brand   = dts_get_request_filter_param( 'filter_brand' );
		$orderby = dts_get_request_orderby();

		if ( 'battery' === $type ) {
			return array(
				'brand'    => $brand,
				'origin'   => dts_get_request_filter_param( 'filter_origin' ),
				'capacity' => dts_get_request_filter_param( 'filter_capacity' ),
				'reserve'  => dts_get_request_filter_param( 'filter_reserve' ),
				'cca'      => dts_get_request_filter_param( 'filter_cca' ),
				'length'   => dts_get_request_filter_param( 'filter_length' ),
				'width'    => dts_get_request_filter_param( 'filter_width' ),
				'height'   => dts_get_request_filter_param( 'filter_height' ),
				'on_sale'  => (bool) dts_get_request_filter_param( 'on_sale' ),
				'orderby'  => $orderby,
				'search'   => get_search_query(),
			);
		}

		return array(
			'brand'    => $brand,
			'year'     => dts_get_request_filter_param( 'filter_year' ),
			'run_flat' => dts_get_request_filter_param( 'filter_run_flat' ),
			'promo'    => dts_get_request_filter_param( 'filter_promo' ),
			'size'     => dts_get_request_size_parts(),
			'size_rear' => dts_get_request_rear_size_parts(),
			'orderby'  => $orderby,
		);
	}
}

if ( ! function_exists( 'dts_apply_archive_filters' ) ) {
	/**
	 * Apply the active request filters to the main product query.
	 *
	 * The query is rebuilt through the shared filter builders so that an archive
	 * page, a shared filter link and the JSON grid endpoints all resolve the
	 * same products for the same selection.
	 *
	 * @param WP_Query $q Product query.
	 */
	function dts_apply_archive_filters( $q ) {
		if ( is_admin() || ! $q instanceof WP_Query ) {
			return;
		}

		if ( ! $q->is_main_query() ) {
			return;
		}

		$post_type = $q->get( 'post_type' );

		if ( ! in_array( 'product', (array) $post_type, true ) && ! is_shop() && ! is_product_taxonomy() ) {
			return;
		}

		$type   = dts_get_current_archive_product_type();
		$params = dts_get_request_filter_params( $type );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$price_min = isset( $_GET['price_min'] ) ? (float) wp_unslash( $_GET['price_min'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$price_max = isset( $_GET['price_max'] ) ? (float) wp_unslash( $_GET['price_max'] ) : 0;

		$params['price_min']    = $price_min;
		$params['price_max']    = $price_max;
		$params['price_active'] = ( $price_min > 0 || $price_max > 0 );

		// The unfiltered Shop page is the catalogue for every product family.
		// The tyre filter builder adds a tyre-category constraint by default, so
		// using it here hides batteries and any products outside those categories.
		// Keep WooCommerce's native catalogue query until a shop filter is active.
		if ( is_shop() && empty( $params['brand'] ) && empty( $params['year'] ) && empty( $params['run_flat'] ) && empty( $params['promo'] ) && empty( $params['size'] ) && empty( $params['size_rear'] ) && ! $params['price_active'] ) {
			return;
		}

		$args = 'battery' === $type
			? dts_build_battery_filter_args( $params )
			: dts_build_tyre_filter_args( $params );

		$meta_query = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		$tax_query  = isset( $args['tax_query'] ) ? $args['tax_query'] : array();

		if ( ! empty( $meta_query ) ) {
			$q->set( 'meta_query', $meta_query );
		}

		if ( ! empty( $tax_query ) ) {
			$q->set( 'tax_query', $tax_query );
		}

		if ( ! empty( $args['orderby'] ) ) {
			$q->set( 'orderby', $args['orderby'] );
			$q->set( 'order', $args['order'] );
		}

		if ( ! empty( $args['meta_key'] ) ) {
			$q->set( 'meta_key', $args['meta_key'] );
		}
	}
}
add_action( 'woocommerce_product_query', 'dts_apply_archive_filters' );

if ( ! function_exists( 'dts_checkout_vehicle_fields' ) ) {
	/**
	 * Register the vehicle detail fields collected at checkout.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	function dts_checkout_vehicle_fields( $fields ) {
		// UAE addresses carry no state or postcode.
		$fields['billing']['billing_state']['required']    = false;
		$fields['billing']['billing_postcode']['required'] = false;

		$fields['billing']['billing_country']['default'] = 'AE';

		$vehicles = array(
			'billing_plate_number' => array(
				'label'       => __( 'Plate number', 'custom-dubai-tyre-shop' ),
				'placeholder' => __( 'e.g. ABC 12345', 'custom-dubai-tyre-shop' ),
				'class'       => array( 'form-row-first' ),
			),
			'billing_car_make'      => array(
				'label'       => __( 'Car make', 'custom-dubai-tyre-shop' ),
				'placeholder' => __( 'e.g. Toyota', 'custom-dubai-tyre-shop' ),
				'class'       => array( 'form-row-last' ),
			),
			'billing_model_number' => array(
				'label'       => __( 'Model number', 'custom-dubai-tyre-shop' ),
				'placeholder' => __( 'e.g. Camry 2.5L', 'custom-dubai-tyre-shop' ),
				'class'       => array( 'form-row-first' ),
			),
			'billing_car_year'      => array(
				'label'       => __( 'Year', 'custom-dubai-tyre-shop' ),
				'placeholder' => __( 'e.g. 2020', 'custom-dubai-tyre-shop' ),
				'class'       => array( 'form-row-last' ),
			),
		);

		foreach ( $vehicles as $key => $config ) {
			$fields['billing'][ $key ] = array_merge(
				array(
					'type'     => 'text',
					'required' => false,
					'clear'    => true,
				),
				$config
			);
		}

		return $fields;
	}
}
add_filter( 'woocommerce_checkout_fields', 'dts_checkout_vehicle_fields' );

if ( ! function_exists( 'dts_default_billing_country' ) ) {
	/**
	 * Default the billing country for a first-time visitor.
	 *
	 * @param mixed  $value Current value.
	 * @param string $input Field key.
	 * @return mixed
	 */
	function dts_default_billing_country( $value, $input ) {
		if ( 'billing_country' === $input && empty( $value ) ) {
			return 'AE';
		}

		return $value;
	}
}
add_filter( 'woocommerce_checkout_get_value', 'dts_default_billing_country', 10, 2 );

if ( ! function_exists( 'dts_save_checkout_vehicle_meta' ) ) {
	/**
	 * Persist the vehicle details submitted at checkout.
	 *
	 * @param int $order_id Order ID.
	 */
	function dts_save_checkout_vehicle_meta( $order_id ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce verified by WooCommerce checkout.
		$fields = array(
			'billing_plate_number' => 'Plate Number',
			'billing_car_make'      => 'Car Make',
			'billing_model_number' => 'Model Number',
			'billing_car_year'      => 'Car Year',
		);

		foreach ( $fields as $field => $meta_key ) {
			if ( empty( $_POST[ $field ] ) ) {
				continue;
			}

			update_post_meta( $order_id, $meta_key, sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
	}
}
add_action( 'woocommerce_checkout_update_order_meta', 'dts_save_checkout_vehicle_meta' );
