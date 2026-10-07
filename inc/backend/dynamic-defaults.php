<?php
/**
 * Dynamic page defaults.
 *
 * Generated archive pages (brand, tyre size, staggered size, vehicle, battery)
 * have no editor-authored title, so their SEO copy comes from a per-context
 * default that an administrator can override on a Settings screen. Tokens such
 * as {size} or {make} are filled in per request.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_dynamic_page_contexts' ) ) {
	/**
	 * Editable SEO contexts.
	 *
	 * @return array
	 */
	function dts_get_dynamic_page_contexts() {
		return apply_filters(
			'dts_dynamic_page_contexts',
			array(
				'brand'       => array(
					'label'        => __( 'Tyre Brand Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'brand' ),
				),
				'tyre_size'   => array(
					'label'        => __( 'Tyre Size Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'size', 'width', 'height', 'rim' ),
				),
				'double_tyre' => array(
					'label'        => __( 'Staggered Tyre Size Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'size1', 'size2', 'width1', 'height1', 'rim1', 'width2', 'height2', 'rim2' ),
				),
				'vehicle'     => array(
					'label'        => __( 'Vehicle Make Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'make', 'model', 'year' ),
				),
				'model'       => array(
					'label'        => __( 'Vehicle Model Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'make', 'model', 'year' ),
				),
				'battery'     => array(
					'label'        => __( 'Battery Pages', 'custom-dubai-tyre-shop' ),
					'title_tokens' => array( 'make', 'model', 'year', 'type' ),
				),
			)
		);
	}
}

if ( ! function_exists( 'dts_get_dynamic_default' ) ) {
	/**
	 * Default SEO copy for a generated page.
	 *
	 * Resolution order: per-path override stored on the backing page, then the
	 * site-wide default for the context. Tokens are replaced last.
	 *
	 * @param string $path_key     URL path the page is serving, e.g. /tyres-size/255-45-r20/.
	 * @param string $context      Context key from dts_get_dynamic_page_contexts().
	 * @param array  $replacements Token values.
	 * @return array{title:string,desc:string}
	 */
	function dts_get_dynamic_default( $path_key, $context = '', $replacements = array() ) {
		$title = '';
		$desc  = '';

		if ( '' !== $path_key ) {
			$page_id = get_queried_object_id();

			if ( $page_id ) {
				$stored = get_post_meta( $page_id, '_dts_dynamic_seo', true );
				$key    = strtolower( $path_key );

				if ( is_array( $stored ) ) {
					foreach ( array( $key, $path_key ) as $candidate ) {
						if ( isset( $stored[ $candidate ]['title'] ) && '' === $title ) {
							$title = (string) $stored[ $candidate ]['title'];
						}
						if ( isset( $stored[ $candidate ]['desc'] ) && '' === $desc ) {
							$desc = (string) $stored[ $candidate ]['desc'];
						}
					}
				}
			}
		}

		if ( '' !== $context ) {
			if ( '' === $title ) {
				$title = (string) get_option( 'dts_default_' . $context . '_title', '' );
			}
			if ( '' === $desc ) {
				$desc = (string) get_option( 'dts_default_' . $context . '_desc', '' );
			}
		}

		return array(
			'title' => dts_replace_seo_tokens( $title, $replacements ),
			'desc'  => dts_replace_seo_tokens( $desc, $replacements ),
		);
	}
}

if ( ! function_exists( 'dts_replace_seo_tokens' ) ) {
	/**
	 * Replace {token} placeholders.
	 *
	 * @param string $text         Text containing tokens.
	 * @param array  $replacements Token values.
	 */
	function dts_replace_seo_tokens( $text, $replacements = array() ) {
		$text = (string) $text;

		if ( '' === $text || empty( $replacements ) ) {
			return $text;
		}

		$search = array();

		foreach ( array_keys( $replacements ) as $key ) {
			$search[] = '{' . $key . '}';
		}

		return str_replace( $search, array_values( array_map( 'strval', $replacements ) ), $text );
	}
}

if ( ! function_exists( 'dts_register_dynamic_default_settings' ) ) {
	/** Register the per-context title and description options. */
	function dts_register_dynamic_default_settings() {
		foreach ( dts_get_dynamic_page_contexts() as $context => $config ) {
			register_setting( 'dts-dynamic-defaults', 'dts_default_' . $context . '_title' );
			register_setting( 'dts-dynamic-defaults', 'dts_default_' . $context . '_desc' );
		}
	}
}
add_action( 'admin_init', 'dts_register_dynamic_default_settings' );

if ( ! function_exists( 'dts_get_search_tyres_slug' ) ) {
	/**
	 * Slug of the page serving the tyre-size archive.
	 *
	 * @return string
	 */
	function dts_get_search_tyres_slug() {
		static $slug = null;

		if ( null === $slug ) {
			$pages = get_pages(
				array(
					'meta_key'   => '_wp_page_template',
					'meta_value' => 'page-templates/template-search-tyres.php',
					'number'     => 1,
				)
			);

			$slug = ! empty( $pages ) ? get_page_uri( $pages[0]->ID ) : 'tyres-size';
		}

		return $slug;
	}
}

if ( ! function_exists( 'dts_get_battery_slug' ) ) {
	/** Slug of the page serving the battery archive. */
	function dts_get_battery_slug() {
		$slug = get_option( 'dts_battery_slug', 'batteries' );

		return sanitize_title( $slug ? $slug : 'batteries' );
	}
}

if ( ! function_exists( 'dts_get_tyre_by_vehicle_slug' ) ) {
	/** Slug of the page serving the vehicle archive. */
	function dts_get_tyre_by_vehicle_slug() {
		$slug = get_option( 'dts_vehicle_slug', 'tyres-by-vehicle' );

		return sanitize_title( $slug ? $slug : 'tyres-by-vehicle' );
	}
}
