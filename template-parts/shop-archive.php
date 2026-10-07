<?php
/** React shop and category archive mount point. */

defined( 'ABSPATH' ) || exit;

get_header();

$shop_archive_is_page_template = is_page_template( 'page-templates/shop.php' );
$shop_archive_term = is_product_category() ? get_queried_object() : null;
$shop_archive_category = $shop_archive_term && ! is_wp_error( $shop_archive_term ) ? $shop_archive_term->slug : '';
$shop_archive_family = $shop_archive_term && ! is_wp_error( $shop_archive_term )
	? dts_detect_product_type_from_slugs( array( $shop_archive_term->slug ) )
	: 'tyre';
$shop_archive_brand = '';
if ( is_tax() ) {
	$shop_archive_object = get_queried_object();
	$shop_archive_taxonomy = $shop_archive_object->taxonomy ?? '';
	$shop_archive_brand_tax = dts_get_brand_taxonomy( $shop_archive_family );
	if ( $shop_archive_taxonomy && $shop_archive_taxonomy === $shop_archive_brand_tax ) {
		$shop_archive_brand = $shop_archive_object->slug;
	}
}
$shop_archive_config = array(
	'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
	'productCategory' => $shop_archive_category,
	'brandSlug'       => $shop_archive_brand,
	'family'          => $shop_archive_family,
	'title'           => is_product_category() ? single_term_title( '', false ) : ( $shop_archive_is_page_template ? get_the_title() : ( function_exists( 'woocommerce_page_title' ) ? woocommerce_page_title( false ) : __( 'Shop', 'custom-dubai-tyre-shop' ) ) ),
	'filters'         => array(
		'brand'    => dts_get_request_filter_param( 'filter_brand' ),
		'year'     => dts_get_request_filter_param( 'filter_year' ),
		'run_flat' => dts_get_request_filter_param( 'filter_run_flat' ),
		'promo'    => dts_get_request_filter_param( 'filter_promo' ),
		'origin'   => dts_get_request_filter_param( 'filter_origin' ),
		'capacity' => dts_get_request_filter_param( 'filter_capacity' ),
		'reserve'  => dts_get_request_filter_param( 'filter_reserve' ),
		'cca'      => dts_get_request_filter_param( 'filter_cca' ),
		'length'   => dts_get_request_filter_param( 'filter_length' ),
		'width'    => dts_get_request_filter_param( 'filter_width' ),
		'height'   => dts_get_request_filter_param( 'filter_height' ),
		'rim'      => dts_get_request_filter_param( 'filter_rim' ),
		'tyreType' => dts_get_request_filter_param( 'filter_tyre_type' ),
		'vehicleType' => dts_get_request_filter_param( 'filter_vehicle_type' ),
		'tier'     => dts_get_request_filter_param( 'filter_tier' ),
		'search'   => isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'price_min' => isset( $_GET['price_min'] ) ? (float) wp_unslash( $_GET['price_min'] ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'price_max' => isset( $_GET['price_max'] ) ? (float) wp_unslash( $_GET['price_max'] ) : null, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'on_sale'  => ! empty( $_GET['on_sale'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		'orderby'  => dts_get_request_orderby(),
	),
);
?>
<script>window.DTSShopArchive = <?php echo wp_json_encode( $shop_archive_config ); ?>;</script>
<?php echo do_shortcode( '[custom_dubai_tyre_modal][custom_dubai_vehicle_modal]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div id="dts-shop-archive-root"></div>
<?php if ( is_product_category() && term_description() ) : ?>
	<section class="collections-term-section"><div class="collections-term-description"><?php echo term_description(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></section>
<?php endif; ?>
<?php
get_footer();
