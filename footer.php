<?php
/**
 * Theme footer.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

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

$vehicle_slug = function_exists( 'dts_get_tyre_by_vehicle_slug' ) ? dts_get_tyre_by_vehicle_slug() : 'tyres-by-vehicle';
$size_slug    = function_exists( 'dts_get_search_tyres_slug' ) ? dts_get_search_tyres_slug() : 'tyres-size';

// Why Dubai Tyre Shop links
$why_links = array(
	array( 'label' => 'About Us', 'url' => $page_url( 'about-us' ) ),
	array( 'label' => 'All Tyres', 'url' => $shop_url ),
	array( 'label' => 'Tyres by Brand', 'url' => $page_url( 'all-brands' ) ),
	array( 'label' => 'Tyres by Size', 'url' => home_url( '/' . trim( $size_slug, '/' ) . '/' ) ),
	array( 'label' => 'Tyres by Car Brand', 'url' => home_url( '/' . trim( $vehicle_slug, '/' ) . '/' ) ),
	array( 'label' => 'Fitting Locations', 'url' => $page_url( 'fitting-locations' ) ),
);

// Featured tyre brands matching reference layout
$default_brand_names = array( 'Continental', 'Arroyo', 'Pirelli', 'Michelin', 'Goodyear', 'Bridgestone' );
$brand_taxonomy      = function_exists( 'dts_get_brand_taxonomy' ) ? dts_get_brand_taxonomy( 'tyre' ) : 'pwb-brand';
$brand_links         = array();

foreach ( $default_brand_names as $b_name ) {
	$b_slug = sanitize_title( $b_name );
	$b_term = ( taxonomy_exists( $brand_taxonomy ) ) ? get_term_by( 'slug', $b_slug, $brand_taxonomy ) : false;
	if ( ! $b_term && taxonomy_exists( $brand_taxonomy ) ) {
		$b_term = get_term_by( 'name', $b_name, $brand_taxonomy );
	}
	$b_url = ( $b_term && function_exists( 'dts_get_brand_link' ) )
		? dts_get_brand_link( $b_term, 'tyre' )
		: ( ( $b_term && ! is_wp_error( get_term_link( $b_term ) ) ) ? get_term_link( $b_term ) : home_url( '/brand/' . $b_slug . '/' ) );

	$brand_links[] = array(
		'label' => $b_name,
		'url'   => is_wp_error( $b_url ) ? home_url( '/brand/' . $b_slug . '/' ) : $b_url,
	);
}

// Customer Support links
$support_links = array(
	array( 'label' => 'Contact Us', 'url' => $page_url( 'contact-us' ) ),
	array( 'label' => 'Return Refund', 'url' => $page_url( 'returns-refunds' ) ),
	array( 'label' => 'FAQ', 'url' => $page_url( 'faqs' ) ),
	array( 'label' => 'Promotions & Offers', 'url' => $page_url( 'offers' ) ),
	array( 'label' => 'Blog', 'url' => get_permalink( (int) get_option( 'page_for_posts' ) ) ?: $page_url( 'blog' ) ),
	array( 'label' => 'Sitemap', 'url' => home_url( '/sitemap_index.xml' ) ),
);

// Legal links in bottom bar
$legal_links = array(
	array( 'label' => 'Terms & Conditions', 'url' => $page_url( 'terms-and-conditions' ) ),
	array( 'label' => 'Privacy Policy', 'url' => $page_url( 'privacy-policy' ) ),
	array( 'label' => 'Terms of Sales & Services', 'url' => $page_url( 'terms-of-sale' ) ),
);

$social_links = array_filter(
	array(
		'Facebook'  => (string) get_option( 'dts_store_facebook', 'https://facebook.com' ),
		'LinkedIn'  => (string) get_option( 'dts_store_linkedin', 'https://linkedin.com' ),
		'Instagram' => (string) get_option( 'dts_store_instagram', 'https://instagram.com' ),
	),
	'esc_url_raw'
);

$raw_phone    = function_exists( 'dts_get_store_detail' ) ? (string) dts_get_store_detail( 'phone', '800 92552' ) : '800 92552';
if ( empty( $raw_phone ) || false !== strpos( $raw_phone, '0000' ) ) {
	$raw_phone = '800 92552';
}
$raw_whatsapp = function_exists( 'dts_get_store_detail' ) ? preg_replace( '/\D+/', '', (string) dts_get_store_detail( 'whatsapp', '971556269517' ) ) : '971556269517';
if ( empty( $raw_whatsapp ) || false !== strpos( $raw_whatsapp, '0000' ) ) {
	$raw_whatsapp = '971556269517';
}
$raw_email    = function_exists( 'dts_get_store_email' ) ? dts_get_store_email() : 'customercare@dubaityreshop.com';
if ( empty( $raw_email ) || false !== strpos( $raw_email, 'example.com' ) ) {
	$raw_email = 'customercare@dubaityreshop.com';
}

$raw_site_name = get_bloginfo( 'name' );
if ( empty( $raw_site_name ) || preg_match( '/\.(com|net|org|site|live|xyz|local|hostingersite)/i', $raw_site_name ) || 0 === strpos( $raw_site_name, 'http' ) ) {
	$site_name = 'Dubai Tyre Shop';
} else {
	$site_name = $raw_site_name;
}

$footer_config = array(
	'siteName'          => $site_name,
	'logoUrl'           => get_theme_file_uri( '/assets/images/dubai-tyre-shop-logo.png' ),
	'newsletterTyreImg' => get_theme_file_uri( '/assets/images/footer-top-tyre.png' ),
	'homeUrl'           => home_url( '/' ),
	'year'              => gmdate( 'Y' ),
	'contact'           => array(
		'phone'    => $raw_phone,
		'phoneTel' => preg_replace( '/[^0-9+]/', '', $raw_phone ),
		'whatsapp' => $raw_whatsapp,
		'email'    => $raw_email,
	),
	'newsletter'        => array(
		'title'    => 'Get Special Offers And Saving',
		'subtitle' => 'Get All The Latest Information On Events, Sales And Offers.',
	),
	'columns'           => array(
		array(
			'title' => 'Why ' . $site_name . '?',
			'links' => $why_links,
		),
		array(
			'title' => 'Tyres Brand',
			'links' => $brand_links,
		),
		array(
			'title' => 'Customer Support',
			'links' => $support_links,
		),
	),
	'legal'             => $legal_links,
	'social'            => $social_links,
	'developerText'     => 'Developed By',
	'developerCompany'  => 'Klever Tech Solutions',
);
?>
<?php echo do_shortcode( '[custom_dubai_side_cart]' ); ?>
<div id="dts-footer-root" data-footer="<?php echo esc_attr( wp_json_encode( $footer_config ) ); ?>"></div>
<?php wp_footer(); ?>
</body>
</html>
