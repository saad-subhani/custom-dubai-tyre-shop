<?php
/**
 * Product family detection.
 *
 * The store carries three families (tyres, batteries, wheel protectors) that
 * differ in metadata, filters and queries. Detection is driven by category
 * slug keywords, so adding a family means adding one rule.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_product_type_rules' ) ) {
	/**
	 * Family matching rules: category slug keywords per family.
	 *
	 * @return array
	 */
	function dts_get_product_type_rules() {
		return apply_filters(
			'dts_product_type_rules',
			array(
				'tyre'           => array(
					'keywords' => array( 'tyre', 'tyres' ),
					'label'    => 'tyres',
				),
				'battery'        => array(
					'keywords' => array( 'battery', 'batteries' ),
					'label'    => 'batteries',
				),
				'wheel-protector' => array(
					'keywords' => array( 'wheel-protector', 'wheel-protectors' ),
					'label'    => 'wheel protectors',
				),
			)
		);
	}
}

if ( ! function_exists( 'dts_get_product_category_slugs' ) ) {
	/**
	 * Category slugs assigned to a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string[]
	 */
	function dts_get_product_category_slugs( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return array();
		}

		return wp_list_pluck( $terms, 'slug' );
	}
}

if ( ! function_exists( 'dts_detect_product_type_from_slugs' ) ) {
	/**
	 * Family for a set of category slugs.
	 *
	 * Rules are evaluated in order, so the first family whose keyword appears
	 * in any slug wins. Tyres come first to keep the tyre behaviour dominant.
	 *
	 * @param string[] $slugs    Category slugs.
	 * @param string   $fallback Family used when nothing matches.
	 */
	function dts_detect_product_type_from_slugs( $slugs, $fallback = 'tyre' ) {
		$slugs = array_map( 'sanitize_title', (array) $slugs );

		foreach ( dts_get_product_type_rules() as $type => $rule ) {
			foreach ( (array) $rule['keywords'] as $keyword ) {
				$needle = sanitize_title( $keyword );

				foreach ( $slugs as $slug ) {
					if ( '' !== $needle && false !== strpos( $slug, $needle ) ) {
						return $type;
					}
				}
			}
		}

		return $fallback;
	}
}

if ( ! function_exists( 'dts_get_product_type' ) ) {
	/**
	 * Family of a single product.
	 *
	 * @param int $product_id Product or variation ID.
	 */
	function dts_get_product_type( $product_id ) {
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;

		if ( ! $product ) {
			return 'tyre';
		}

		if ( $product->is_type( 'variation' ) ) {
			$product_id = $product->get_parent_id();
		}

		return dts_detect_product_type_from_slugs( dts_get_product_category_slugs( $product_id ) );
	}
}

if ( ! function_exists( 'dts_get_current_archive_product_type' ) ) {
	/** Family for the current WooCommerce archive. */
	function dts_get_current_archive_product_type() {
		if ( is_product_category() ) {
			$term = get_queried_object();

			if ( $term && ! is_wp_error( $term ) && ! empty( $term->slug ) ) {
				return dts_detect_product_type_from_slugs( array( $term->slug ) );
			}
		}

		return 'tyre';
	}
}

if ( ! function_exists( 'dts_get_product_type_category_slugs' ) ) {
	/**
	 * Every category slug that belongs to a family.
	 *
	 * @param string $type Family key.
	 * @return string[]
	 */
	function dts_get_product_type_category_slugs( $type ) {
		$rules = dts_get_product_type_rules();

		if ( empty( $rules[ $type ]['keywords'] ) ) {
			return array();
		}

		global $wpdb;

		$cache_key = 'dts_type_slugs_' . sanitize_key( $type );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$all_slugs = $wpdb->get_col(
			"SELECT t.slug FROM {$wpdb->terms} t
			 INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
			 WHERE tt.taxonomy = 'product_cat'"
		);

		if ( empty( $all_slugs ) ) {
			return array();
		}

		$keywords = array_map( 'sanitize_title', (array) $rules[ $type ]['keywords'] );
		$matching = array();

		foreach ( $all_slugs as $slug ) {
			foreach ( $keywords as $keyword ) {
				if ( '' !== $keyword && false !== strpos( $slug, $keyword ) ) {
					$matching[] = $slug;
					break;
				}
			}
		}

		set_transient( $cache_key, $matching, DAY_IN_SECONDS );

		return $matching;
	}
}

add_action( 'created_product_cat', 'dts_flush_product_type_slug_cache' );
add_action( 'edited_product_cat', 'dts_flush_product_type_slug_cache' );
add_action( 'delete_product_cat', 'dts_flush_product_type_slug_cache' );
if ( ! function_exists( 'dts_flush_product_type_slug_cache' ) ) {
	/** Drop the cached family-to-category mapping when a category changes. */
	function dts_flush_product_type_slug_cache() {
		foreach ( array_keys( dts_get_product_type_rules() ) as $type ) {
			delete_transient( 'dts_type_slugs_' . sanitize_key( $type ) );
		}
	}
}

if ( ! function_exists( 'dts_get_brand_taxonomy' ) ) {
	/**
	 * Active brand taxonomy for a family.
	 *
	 * Prefers the dedicated product_brand taxonomy and falls back to the
	 * WooCommerce attribute taxonomy, so the store works before the plugin that
	 * registers product_brand is active.
	 *
	 * @param string $type Optional family hint.
	 */
	function dts_get_brand_taxonomy( $type = '' ) {
		if ( 'battery' === $type ) {
			return dts_get_battery_brand_taxonomy();
		}

		if ( taxonomy_exists( 'product_brand' ) ) {
			return 'product_brand';
		}

		return taxonomy_exists( 'pa_brand' ) ? 'pa_brand' : '';
	}
}

if ( ! function_exists( 'dts_get_battery_brand_taxonomy' ) ) {
	/** Active brand taxonomy for the battery family. */
	function dts_get_battery_brand_taxonomy() {
		return dts_get_brand_taxonomy();
	}
}

if ( ! function_exists( 'dts_get_battery_base_url' ) ) {
	/** Archive URL for the battery family. */
	function dts_get_battery_base_url() {
		return home_url( '/' . trim( dts_get_battery_slug(), '/' ) . '/' );
	}
}

if ( ! function_exists( 'dts_get_product_origin' ) ) {
	/**
	 * Country of manufacture, tolerating the historical meta key typo.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_origin( $product_id ) {
		$origin = get_post_meta( $product_id, 'country_of_manufacture', true );

		if ( '' === $origin || false === $origin ) {
			$origin = get_post_meta( $product_id, 'country_of_ manufacture', true );
		}

		return is_string( $origin ) ? $origin : '';
	}
}

if ( ! function_exists( 'dts_country_flag_codes' ) ) {
	/**
	 * Country name to ISO 3166-1 alpha-2 map, keyed by slug.
	 *
	 * Slugs are the lowercased country name with spaces turned into hyphens and
	 * every other non-alphanumeric character dropped, which is how the values are
	 * looked up in dts_get_country_flag().
	 *
	 * @return array<string, string>
	 */
	function dts_country_flag_codes() {
		static $codes = null;

		if ( null !== $codes ) {
			return $codes;
		}

		$map = array(
			'poland' => 'PL', 'germany' => 'DE', 'japan' => 'JP', 'china' => 'CN',
			'usa' => 'US', 'u-s-a' => 'US', 'united-states' => 'US', 'united-states-of-america' => 'US',
			'america' => 'US', 'italy' => 'IT', 'france' => 'FR', 'spain' => 'ES',
			'south-korea' => 'KR', 'korea' => 'KR', 'republic-of-korea' => 'KR',
			'united-kingdom' => 'GB', 'uk' => 'GB', 'great-britain' => 'GB', 'england' => 'GB',
			'scotland' => 'GB', 'wales' => 'GB', 'northern-ireland' => 'GB',
			'thailand' => 'TH', 'indonesia' => 'ID', 'india' => 'IN',
			'czech-republic' => 'CZ', 'czechia' => 'CZ', 'czech' => 'CZ',
			'taiwan' => 'TW', 'slovakia' => 'SK', 'hungary' => 'HU', 'romania' => 'RO',
			'turkey' => 'TR', 'turkiye' => 'TR', 'türkiye' => 'TR',
			'portugal' => 'PT', 'austria' => 'AT', 'netherlands' => 'NL',
			'belgium' => 'BE', 'sweden' => 'SE', 'finland' => 'FI', 'russia' => 'RU',
			'russian-federation' => 'RU', 'brazil' => 'BR', 'mexico' => 'MX',
			'canada' => 'CA', 'australia' => 'AU', 'argentina' => 'AR',
			'south-africa' => 'ZA', 'malaysia' => 'MY', 'vietnam' => 'VN',
			'singapore' => 'SG', 'philippines' => 'PH', 'egypt' => 'EG',
			'saudi-arabia' => 'SA', 'uae' => 'AE', 'u-a-e' => 'AE',
			'united-arab-emirates' => 'AE', 'emirates' => 'AE',
			'serbia' => 'RS', 'bulgaria' => 'BG', 'morocco' => 'MA',
			'algeria' => 'DZ', 'tunisia' => 'TN', 'slovenia' => 'SI',
			'croatia' => 'HR', 'lithuania' => 'LT', 'latvia' => 'LV',
			'estonia' => 'EE', 'belarus' => 'BY', 'ukraine' => 'UA',
			'pakistan' => 'PK', 'bangladesh' => 'BD', 'sri-lanka' => 'LK',
			'myanmar' => 'MM', 'cambodia' => 'KH', 'laos' => 'LA',
			'mongolia' => 'MN', 'kazakhstan' => 'KZ', 'uzbekistan' => 'UZ',
			'iran' => 'IR', 'iraq' => 'IQ', 'israel' => 'IL',
			'jordan' => 'JO', 'lebanon' => 'LB', 'oman' => 'OM',
			'qatar' => 'QA', 'bahrain' => 'BH', 'kuwait' => 'KW',
			'yemen' => 'YE', 'cyprus' => 'CY', 'malta' => 'MT',
			'luxembourg' => 'LU', 'monaco' => 'MC', 'liechtenstein' => 'LI',
			'switzerland' => 'CH', 'denmark' => 'DK', 'norway' => 'NO',
			'iceland' => 'IS', 'ireland' => 'IE', 'greece' => 'GR',
			'new-zealand' => 'NZ', 'nigeria' => 'NG', 'kenya' => 'KE',
			'ethiopia' => 'ET', 'ghana' => 'GH', 'angola' => 'AO',
			'mozambique' => 'MZ', 'tanzania' => 'TZ', 'zimbabwe' => 'ZW',
			'chile' => 'CL', 'colombia' => 'CO', 'peru' => 'PE',
			'venezuela' => 'VE', 'ecuador' => 'EC', 'dominican-republic' => 'DO',
			'costa-rica' => 'CR', 'panama' => 'PA', 'guatemala' => 'GT',
			'hong-kong' => 'HK', 'macau' => 'MO', 'tajikistan' => 'TJ',
			'turkmenistan' => 'TM', 'kyrgyzstan' => 'KG', 'georgia' => 'GE',
			'armenia' => 'AM', 'azerbaijan' => 'AZ', 'kosovo' => 'XK',
		);

		/**
		 * Filter the country name to ISO code map used for flag rendering.
		 *
		 * @param array $map Slug to ISO 3166-1 alpha-2 code.
		 */
		$codes = apply_filters( 'dts_country_flag_codes', $map );

		return $codes;
	}
}

if ( ! function_exists( 'dts_get_country_flag' ) ) {
	/**
	 * Regional-indicator flag emoji for a country name.
	 *
	 * Returns an empty string for anything the map does not recognise. Guessing
	 * from the first two letters produced confidently wrong flags (Taiwan became
	 * Trinidad and Tobago, Slovakia became Saint Lucia), so an unknown country
	 * shows its name without a flag instead of the wrong one.
	 *
	 * @param string $country_name Country name.
	 * @return string Flag emoji, or '' when the country is unknown.
	 */
	function dts_get_country_flag( $country_name ) {
		$country_name = trim( (string) $country_name );

		if ( '' === $country_name ) {
			return '';
		}

		$slug = strtolower( $country_name );
		$slug = str_replace( ' ', '-', $slug );
		$slug = preg_replace( '/[^a-z0-9-]/', '', $slug );
		$slug = preg_replace( '/-+/', '-', $slug );
		$slug = trim( $slug, '-' );

		$codes = dts_country_flag_codes();
		$code  = isset( $codes[ $slug ] ) ? $codes[ $slug ] : '';

		if ( 2 !== strlen( $code ) || ! ctype_alpha( $code ) ) {
			return '';
		}

		$flag = '';

		foreach ( str_split( strtoupper( $code ) ) as $letter ) {
			$flag .= mb_chr( 0x1F1E6 + ( ord( $letter ) - 65 ), 'UTF-8' );
		}

		return $flag;
	}
}
