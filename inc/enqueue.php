<?php
/**
 * Theme and shortcode asset registration.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the shortcode component map used for registration and asset loading.
 *
 * @return array
 */
function custom_dubai_get_components() {
	return array(
		'hero' => array(
			'shortcode' => 'custom_dubai_hero',
			'file'      => '/shortcodes/hero/hero.php',
			'css'       => '/shortcodes/hero/hero.css',
			'js'        => '/shortcodes/hero/hero.js',
			'dependencies' => array(),
			'style_dependencies' => array(),
			'front_page' => true,
		),
		'globe-section' => array(
			'shortcode' => 'custom_dubai_globe_section',
			'file'      => '/shortcodes/globe-section/globe-section.php',
			'css'       => '/shortcodes/globe-section/globe-section.css',
			'js'        => '/shortcodes/globe-section/globe-section.js',
			'dependencies' => array(),
			// The homepage template renders this section itself, so its assets
			// have to load even when the shortcode is not in the post content.
			'front_page' => true,
			// three.js and three-globe live in a separate chunk that is only
			// registered here and downloaded by the component on demand.
			'lazy_script' => array(
				'handle' => 'custom-dubai-globe-engine',
				'file'   => '/shortcodes/globe-section/chunks/globe-engine.js',
			),
		),
		'shop-by-category' => array(
			'shortcode' => 'custom_dubai_shop_by_category',
			'file'      => '/shortcodes/shop-by-category/shop-by-category.php',
			'css'       => '/shortcodes/shop-by-category/shop-by-category.css',
			'js'        => '/shortcodes/shop-by-category/shop-by-category.js',
			'dependencies' => array(),
			'style_dependencies' => array(),
			// The homepage template renders this section itself, so its assets
			// have to load even when the shortcode is not in the post content.
			'front_page' => true,
		),
		'services-section' => array(
			'shortcode' => 'custom_dubai_services_section',
			'file'      => '/shortcodes/services-section/services-section.php',
			'css'       => '/shortcodes/services-section/services-section.css',
			'js'        => '/shortcodes/services-section/services-section.js',
			'dependencies' => array( 'wp-element' ),
			'style_dependencies' => array(),
			'front_page' => true,
		),
		'tyre-modal' => array(
			'shortcode' => 'custom_dubai_tyre_modal',
			'file'      => '/shortcodes/tyre-modal/tyre-modal.php',
			'css'       => '/shortcodes/tyre-modal/tyre-modal.css',
			'js'        => '/shortcodes/tyre-modal/tyre-modal.js',
			'dependencies' => array(),
			'style_dependencies' => array( 'custom-dubai-modal-base' ),
			'requires_hero' => true,
		),
		'vehicle-modal' => array(
			'shortcode' => 'custom_dubai_vehicle_modal',
			'file'      => '/shortcodes/vehicle-modal/vehicle-modal.php',
			'css'       => '/shortcodes/vehicle-modal/vehicle-modal.css',
			'js'        => '/shortcodes/vehicle-modal/vehicle-modal.js',
			'dependencies' => array(),
			'style_dependencies' => array( 'custom-dubai-modal-base' ),
			'requires_hero' => true,
		),
		'product-card' => array(
			'shortcode' => 'custom_dubai_product_card',
			'file'      => '/shortcodes/product-card/product-card.php',
			'css'       => '/shortcodes/product-card/product-card.css',
			'js'        => '/shortcodes/product-card/product-card.js',
			'dependencies' => array(),
			'style_dependencies' => array(),
		),
		'side-cart' => array(
			'shortcode' => 'custom_dubai_side_cart',
			'file'      => '/shortcodes/side-cart/side-cart.php',
			'css'       => '/shortcodes/side-cart/side-cart.css',
			'js'        => '/shortcodes/side-cart/side-cart.js',
			'dependencies' => array(),
			'style_dependencies' => array(),
		),
		'example' => array(
			'shortcode' => 'cdts_example',
			'file'      => '/shortcodes/example/example.php',
			'css'       => '/shortcodes/example/example.css',
			'js'        => '/shortcodes/example/example.js',
			'dependencies' => array(),
			'style_dependencies' => array(),
		),
	);
}

/**
 * Load component PHP so its shortcode is registered.
 */
function custom_dubai_register_shortcodes() {
	$components = custom_dubai_get_components();

	foreach ( $components as $component ) {
		$file = get_theme_file_path( $component['file'] );
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
add_action( 'init', 'custom_dubai_register_shortcodes' );

/**
 * Build a cache-busting version string for a theme asset.
 *
 * Hashes the file contents so an edited asset always gets a new URL. Using
 * filemtime() alone silently reuses the old URL when a deploy preserves
 * timestamps, which leaves browsers and CDNs serving a stale file.
 *
 * @param string $path Absolute path to the asset.
 * @return string Version string.
 */
function custom_dubai_asset_version( $path ) {
	if ( ! file_exists( $path ) ) {
		return (string) wp_get_theme()->get( 'Version' );
	}

	$hash = md5_file( $path );

	return $hash ? $hash : (string) filemtime( $path );
}

/**
 * Enqueue component assets only when their shortcode appears in the queried post.
 */
function custom_dubai_enqueue_component_assets() {
	$components = custom_dubai_get_components();
	$post = get_post();
	$is_homepage_template = is_front_page() || is_page_template( 'front-page.php' );
	$hero_shortcode_used = is_singular() && $post && has_shortcode( $post->post_content, 'custom_dubai_hero' );

		$is_shop_page_template = is_page_template( 'page-templates/shop.php' );
		$is_woocommerce_archive = function_exists( 'is_woocommerce' ) && ( is_shop() || is_product_taxonomy() || is_product_category() || is_product_tag() );

	foreach ( $components as $component_name => $component ) {
		$shortcode_used = is_singular() && $post && has_shortcode( $post->post_content, $component['shortcode'] );
		$front_page_component = ! empty( $component['front_page'] ) && $is_homepage_template;
		$component_needs_hero = ! empty( $component['requires_hero'] ) && ( $is_homepage_template || $hero_shortcode_used || $is_woocommerce_archive || $is_shop_page_template );
		$is_global_component = in_array( $component_name, array( 'product-card', 'side-cart' ), true );
		$is_side_cart = 'side-cart' === $component_name;
		if ( ! $shortcode_used && ! $front_page_component && ! $component_needs_hero && ! ( $is_global_component && ( $is_woocommerce_archive || $is_shop_page_template ) ) && ! $is_side_cart ) {
			continue;
		}

		$css_path = get_theme_file_path( $component['css'] );
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'custom-dubai-' . $component['shortcode'] . '-css',
				get_theme_file_uri( $component['css'] ),
				// Components that do not list style dependencies still need the
				// global stylesheet as a dependency.
				array_merge( array( 'custom-dubai-theme' ), $component['style_dependencies'] ?? array() ),
				custom_dubai_asset_version( $css_path )
			);
		}

		$js_path = get_theme_file_path( $component['js'] );
		if ( file_exists( $js_path ) ) {
			wp_enqueue_script(
				'custom-dubai-' . $component['shortcode'] . '-js',
				get_theme_file_uri( $component['js'] ),
				$component['dependencies'],
				custom_dubai_asset_version( $js_path ),
				true
			);
		}

		if ( ! empty( $component['lazy_script'] ) ) {
			$lazy      = $component['lazy_script'];
			$lazy_path = get_theme_file_path( $lazy['file'] );

			// Registered but never enqueued. The shortcode passes this URL to the
			// component, which injects the script itself once the globe is close
			// to entering the viewport, so it stays off the critical path.
			if ( file_exists( $lazy_path ) ) {
				wp_register_script(
					$lazy['handle'],
					get_theme_file_uri( $lazy['file'] ),
					array(),
					custom_dubai_asset_version( $lazy_path )
				);
			}
		}
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_component_assets', 20 );

/** Load the React site header on every front-end page. */
function custom_dubai_enqueue_header_assets() {
	$header_css = get_theme_file_path( '/assets/components/header.css' );
	$header_js  = get_theme_file_path( '/react/components/header.js' );

	if ( file_exists( $header_css ) ) {
		wp_enqueue_style( 'custom-dubai-header', get_theme_file_uri( '/assets/components/header.css' ), array( 'custom-dubai-theme' ), custom_dubai_asset_version( $header_css ) );
	}
	if ( file_exists( $header_js ) ) {
		wp_enqueue_script( 'custom-dubai-header', get_theme_file_uri( '/react/components/header.js' ), array(), custom_dubai_asset_version( $header_js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_header_assets', 110 );

function custom_dubai_enqueue_shop_assets() {
	if ( ! is_page_template( 'page-templates/shop.php' ) && ( ! function_exists( 'is_woocommerce' ) || ! ( is_shop() || is_product_taxonomy() ) ) ) {
		return;
	}

	$shop_css_path = get_theme_file_path( '/react/components/shop.css' );
	if ( file_exists( $shop_css_path ) ) {
		wp_enqueue_style(
			'custom-dubai-shop',
			get_theme_file_uri( '/react/components/shop.css' ),
			array( 'custom-dubai-theme' ),
			custom_dubai_asset_version( $shop_css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_shop_assets', 25 );

/** Load the React archive, reference-theme filters, and reference product-card styles. */
function custom_dubai_enqueue_shop_archive_assets() {
	if ( ! is_page_template( 'page-templates/shop.php' ) && ( ! function_exists( 'is_woocommerce' ) || ! ( is_shop() || is_product_taxonomy() ) ) ) {
		return;
	}

	$archive_css = get_theme_file_path( '/react/components/shop-archive.css' );
	$archive_js  = get_theme_file_path( '/react/components/shop-archive.js' );

	if ( file_exists( $archive_css ) ) {
		wp_enqueue_style( 'custom-dubai-shop-archive', get_theme_file_uri( '/react/components/shop-archive.css' ), array( 'custom-dubai-theme', 'custom-dubai-shop' ), custom_dubai_asset_version( $archive_css ) );
	}
	if ( file_exists( $archive_js ) ) {
		wp_enqueue_script( 'custom-dubai-shop-archive', get_theme_file_uri( '/react/components/shop-archive.js' ), array(), custom_dubai_asset_version( $archive_js ), true );
	}
	$mobile_archive_js = get_theme_file_path( '/react/components/shop-archive-mobile.js' );
	if ( file_exists( $mobile_archive_js ) ) {
		wp_enqueue_script( 'custom-dubai-shop-archive-mobile', get_theme_file_uri( '/react/components/shop-archive-mobile.js' ), array( 'custom-dubai-shop-archive' ), custom_dubai_asset_version( $mobile_archive_js ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_shop_archive_assets', 30 );

/** Load the React product detail page on WooCommerce product pages. */
function custom_dubai_enqueue_single_product_assets() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$css_path = get_theme_file_path( '/react/components/single-product.css' );
	$js_path  = get_theme_file_path( '/react/components/single-product.js' );
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style( 'custom-dubai-single-product', get_theme_file_uri( '/react/components/single-product.css' ), array( 'custom-dubai-theme' ), custom_dubai_asset_version( $css_path ) );
	}
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script( 'custom-dubai-single-product', get_theme_file_uri( '/react/components/single-product.js' ), array(), custom_dubai_asset_version( $js_path ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_single_product_assets', 35 );

/**
 * Enqueue global theme styling.
 */
function custom_dubai_enqueue_global_assets() {
$style_path = get_theme_file_path( '/style.css' );
		wp_enqueue_style(
			'custom-dubai-theme',
			get_stylesheet_uri(),
			array(),
			custom_dubai_asset_version( $style_path )
		);

	$modal_base_path = get_theme_file_path( '/assets/components/modal-base.css' );
	if ( file_exists( $modal_base_path ) ) {
		wp_register_style(
			'custom-dubai-modal-base',
			get_theme_file_uri( '/assets/components/modal-base.css' ),
			array( 'custom-dubai-theme' ),
			custom_dubai_asset_version( $modal_base_path )
		);
	}
}
// Load the theme's design system after plugin/page-builder styles so theme
// typography and full-width layout rules remain in control.
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_global_assets', 100 );

/** Load footer presentation on every front-end page. */
function custom_dubai_enqueue_footer_assets() {
	$footer_css = get_theme_file_path( '/assets/components/footer.css' );
	if ( file_exists( $footer_css ) ) {
		wp_enqueue_style(
			'custom-dubai-footer',
			get_theme_file_uri( '/assets/components/footer.css' ),
			array( 'custom-dubai-theme' ),
			custom_dubai_asset_version( $footer_css )
		);
	}

	$footer_js = get_theme_file_path( '/react/components/footer.js' );
	if ( file_exists( $footer_js ) ) {
		wp_enqueue_script(
			'custom-dubai-footer',
			get_theme_file_uri( '/react/components/footer.js' ),
			array(),
			custom_dubai_asset_version( $footer_js ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'custom_dubai_enqueue_footer_assets', 110 );
