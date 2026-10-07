<?php
/**
 * Brand helpers.
 *
 * Brand artwork lives in term meta on whichever brand taxonomy is active, and
 * brand pages are served by the archive templates rather than by term links, so
 * brand links are built from slugs.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_brand_term' ) ) {
	/**
	 * Resolve a brand term from a term object, ID, or slug.
	 *
	 * @param WP_Term|int|string $term Term, term ID, or brand slug.
	 * @return WP_Term|null
	 */
	function dts_get_brand_term( $term ) {
		if ( is_object( $term ) && isset( $term->term_id ) ) {
			return $term;
		}

		$taxonomy = dts_get_brand_taxonomy();

		if ( '' === $taxonomy ) {
			return null;
		}

		if ( is_numeric( $term ) ) {
			$found = get_term( (int) $term, $taxonomy );
		} else {
			$found = get_term_by( 'slug', sanitize_title( $term ), $taxonomy );
		}

		return ( $found && ! is_wp_error( $found ) ) ? $found : null;
	}
}

if ( ! function_exists( 'dts_get_brand_logo_url' ) ) {
	/**
	 * Brand logo URL, read from the term thumbnail WooCommerce Brands stores.
	 *
	 * @param WP_Term|int|string $term Term, term ID, or brand slug.
	 * @return string Empty string when no logo is set.
	 */
	function dts_get_brand_logo_url( $term ) {
		static $cache = array();

		$resolved = dts_get_brand_term( $term );

		if ( ! $resolved ) {
			return '';
		}

		$term_id = (int) $resolved->term_id;

		if ( isset( $cache[ $term_id ] ) ) {
			return $cache[ $term_id ];
		}

		$thumbnail_id = (int) get_term_meta( $term_id, 'thumbnail_id', true );
		$url          = $thumbnail_id ? wp_get_attachment_url( $thumbnail_id ) : false;

		$cache[ $term_id ] = $url ? $url : '';

		return $cache[ $term_id ];
	}
}

if ( ! function_exists( 'dts_get_brand_link' ) ) {
	/**
	 * Archive URL for a brand.
	 *
	 * Battery brands filter the battery archive, tyre brands point at the tyre
	 * brand page.
	 *
	 * @param WP_Term|int|string $term Term, term ID, or brand slug.
	 * @param string             $type 'tyre' or 'battery'.
	 */
	function dts_get_brand_link( $term, $type = 'tyre' ) {
		$resolved = dts_get_brand_term( $term );
		$fallback = ( is_object( $term ) && isset( $term->slug ) ) ? $term->slug : $term;

		// A stale filter value should still produce a usable link rather than a
		// dead one, so the battery family falls back to its brand archive.
		if ( ! $resolved ) {
			return ( 'battery' === $type && is_string( $fallback ) )
				? dts_get_battery_base_url() . sanitize_title( $fallback ) . '/'
				: '';
		}

		$slug = sanitize_title( $resolved->slug );

		if ( 'battery' === $type ) {
			return dts_get_battery_base_url() . $slug . '/';
		}

		$link = get_term_link( $resolved );

		return is_wp_error( $link ) ? '' : $link;
	}
}

if ( ! function_exists( 'dts_get_brand_slug' ) ) {
	/**
	 * Brand slug for a product.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_brand_slug( $product_id ) {
		$taxonomy = dts_get_brand_taxonomy();

		if ( '' === $taxonomy ) {
			return '';
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy, array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return (string) $terms[0];
	}
}
