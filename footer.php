<?php
/** Theme footer. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$shop_url     = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/shop/' );
$page_url     = static function ( $slug ) {
	$page = get_page_by_path( $slug );
	return $page ? get_permalink( $page ) : home_url( '/' . trim( $slug, '/' ) . '/' );
};
$category_url = static function ( $slug ) use ( $shop_url ) {
	$term = taxonomy_exists( 'product_cat' ) ? get_term_by( 'slug', $slug, 'product_cat' ) : false;
	$link = $term ? get_term_link( $term ) : $shop_url;
	return is_wp_error( $link ) ? $shop_url : $link;
};
$brand_taxonomy = function_exists( 'dts_get_brand_taxonomy' ) ? dts_get_brand_taxonomy( 'tyre' ) : '';
$brands         = $brand_taxonomy ? get_terms( array( 'taxonomy' => $brand_taxonomy, 'hide_empty' => true, 'number' => 14, 'orderby' => 'count', 'order' => 'DESC' ) ) : array();
$brands         = is_wp_error( $brands ) ? array() : array_map(
	static function ( $brand ) {
		$url = function_exists( 'dts_get_brand_link' ) ? dts_get_brand_link( $brand, 'tyre' ) : get_term_link( $brand );
		return array( 'label' => $brand->name, 'url' => is_wp_error( $url ) ? '' : $url );
	},
	$brands
);
$vehicle_slug = function_exists( 'dts_get_tyre_by_vehicle_slug' ) ? dts_get_tyre_by_vehicle_slug() : 'tyres-by-vehicle';
$size_slug    = function_exists( 'dts_get_search_tyres_slug' ) ? dts_get_search_tyres_slug() : 'tyres-size';
$shop_links   = array(
	array( 'label' => 'All Car Tyres', 'url' => $category_url( 'car-tyres' ) ),
	array( 'label' => 'SUV & 4x4 Tyres', 'url' => $category_url( 'suv-4x4-tyres' ) ),
	array( 'label' => 'Shop by Vehicle', 'url' => home_url( '/' . trim( $vehicle_slug, '/' ) . '/' ) ),
	array( 'label' => 'Shop by Tyre Size', 'url' => home_url( '/' . trim( $size_slug, '/' ) . '/' ) ),
	array( 'label' => 'Special Offers', 'url' => $page_url( 'offers' ) ),
	array( 'label' => 'Mobile Tyre Fitting', 'url' => $page_url( 'mobile-tyre-fitting' ) ),
	array( 'label' => 'Fitting Locations', 'url' => $page_url( 'fitting-centers' ) ),
	array( 'label' => 'Tyre Blog', 'url' => get_permalink( (int) get_option( 'page_for_posts' ) ) ?: $page_url( 'blog' ) ),
);
$sizes = array( '265/60 R18', '205/55 R16', '215/55 R17', '265/65 R17', '225/65 R17', '285/70 R17', '275/60 R20', '235/45 R18', '195/65 R15', '215/60 R16', '245/45 R18', '245/60 R18', '225/40 R18', '235/60 R18' );
$size_links = array_map(
	static function ( $size ) use ( $size_slug ) {
		return array( 'label' => $size, 'url' => home_url( '/' . trim( $size_slug, '/' ) . '/' . sanitize_title( str_replace( ' ', '-', $size ) ) . '/' ) );
	},
	$sizes
);
$support_links = array_map(
	static function ( $slug, $label ) use ( $page_url ) {
		return array( 'label' => $label, 'url' => $page_url( $slug ) );
	},
	array( 'about-us', 'contact-us', 'faqs', 'blog', 'promotions', 'my-account', 'returns-refunds', 'warranty' ),
	array( 'About Us', 'Contact Us', 'FAQs', 'Blog', 'Promotions & Offers', 'Customer Login', 'Returns & Refunds', 'Warranty' )
);
$legal_links = array_map(
	static function ( $slug, $label ) use ( $page_url ) {
		return array( 'label' => $label, 'url' => $page_url( $slug ) );
	},
	array( 'terms-and-conditions', 'privacy-policy', 'terms-of-sale', 'cookie-policy', 'disclaimer', 'returns-refunds', 'warranty' ),
	array( 'Terms & Conditions', 'Privacy Policy', 'Terms of Sale', 'Cookie Policy', 'Disclaimer', 'Returns & Refunds', 'Warranty' )
);
$social_links = array_filter(
	array(
		'Facebook'  => (string) get_option( 'dts_store_facebook', '' ),
		'Instagram' => (string) get_option( 'dts_store_instagram', '' ),
		'LinkedIn'  => (string) get_option( 'dts_store_linkedin', '' ),
	),
	'esc_url_raw'
);
$whatsapp = function_exists( 'dts_get_store_detail' ) ? preg_replace( '/\D+/', '', (string) dts_get_store_detail( 'whatsapp' ) ) : '';
$footer_config = array(
	'introTitle' => 'Buy Tyres Online in the UAE — Fitted & Delivered',
	'introText'  => 'Shop the UAE’s top tyre brands — Michelin, Bridgestone, Pirelli, Continental, Goodyear and more — at guaranteed prices, with fitting included and fast delivery across Dubai, Abu Dhabi, Sharjah and the wider UAE. Browse car and 4x4 tyres by vehicle, brand or size, book a fitting location near you, and drive away with confidence.',
	'columns'    => array(
		array( 'title' => 'Shop Tyres', 'links' => $shop_links ),
		array( 'title' => 'Tyres by Brand', 'links' => $brands, 'more' => array( 'label' => 'View All Brands', 'url' => $page_url( 'all-brands' ) ) ),
		array( 'title' => 'Popular Sizes', 'links' => $size_links, 'more' => array( 'label' => 'All Tyre Sizes', 'url' => home_url( '/' . trim( $size_slug, '/' ) . '/' ) ) ),
		array( 'title' => 'Company & Support', 'links' => $support_links ),
	),
	'legal'      => $legal_links,
	'social'     => $social_links,
	'whatsapp'   => $whatsapp,
	'siteName'   => get_bloginfo( 'name' ),
	'year'       => gmdate( 'Y' ),
);
?>
<?php echo do_shortcode( '[custom_dubai_side_cart]' ); ?>
<div id="dts-footer-root" data-footer="<?php echo esc_attr( wp_json_encode( $footer_config ) ); ?>"></div>
<?php wp_footer(); ?>
</body>
</html>
