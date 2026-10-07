<?php
/** React single product page. */

defined( 'ABSPATH' ) || exit;

get_header();

global $product;
$product = wc_get_product( get_the_ID() );

if ( ! $product ) {
	get_footer();
	return;
}

$dts_single_payload = dts_get_product_payload( $product );
$dts_full_image = $product->get_image_id() ? wp_get_attachment_image_src( $product->get_image_id(), 'woocommerce_single' ) : false;
if ( $dts_full_image ) {
	$dts_single_payload['image']['url'] = $dts_full_image[0];
}
$dts_single_payload['description'] = wp_kses_post( $product->get_short_description() ?: $product->get_description() );
$dts_single_payload['gallery'] = dts_get_product_gallery_payload( $product->get_id() );
foreach ( $dts_single_payload['gallery'] as &$dts_gallery_image ) {
	if ( ! empty( $dts_gallery_image['full'] ) ) {
		$dts_gallery_image['url'] = $dts_gallery_image['full'];
	}
}
unset( $dts_gallery_image );
$dts_single_payload['meta']['size_label'] = dts_get_product_size_label( $product->get_id() );
$dts_single_payload['meta']['load_index'] = (string) get_post_meta( $product->get_id(), 'tyre_load', true );
$dts_single_payload['meta']['speed_rating'] = (string) get_post_meta( $product->get_id(), 'tyre_speed', true );
$dts_single_payload['meta']['sidewall'] = (string) get_post_meta( $product->get_id(), 'sidewall', true );
$dts_single_payload['brand_name'] = ! empty( $dts_single_payload['brand']['name'] ) ? $dts_single_payload['brand']['name'] : '';
$dts_single_payload['price'] = (float) dts_get_product_display_price( $product, null, 'display' );
$dts_single_payload['regular_price'] = (float) dts_get_product_display_price( $product, (float) $product->get_regular_price(), 'display' );
$dts_shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;
$dts_single_payload['shop_url'] = $dts_shop_page_id > 0 ? get_permalink( $dts_shop_page_id ) : home_url( '/shop/' );
$dts_single_payload['whatsapp'] = function_exists( 'dts_get_store_detail' ) ? preg_replace( '/\D+/', '', (string) dts_get_store_detail( 'whatsapp' ) ) : '';
if ( empty( $dts_single_payload['whatsapp'] ) ) {
	$dts_single_payload['whatsapp'] = '971556269517';
}

// Imported tyre sizes are separate simple products. Group products by model so
// the size selector reflects the real sellable SKUs in the catalogue.
$dts_model = trim( (string) get_post_meta( $product->get_id(), 'tyre_model', true ) );
$dts_size_options = array();
if ( '' !== $dts_model ) {
	$dts_option_query = new WP_Query(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => 'tyre_model',
					'value' => $dts_model,
				),
			),
		)
	);
	foreach ( $dts_option_query->posts as $dts_option_id ) {
		$dts_option = wc_get_product( $dts_option_id );
		$dts_label = dts_get_product_size_label( $dts_option_id );
		if ( $dts_option && $dts_label ) {
			$dts_size_options[] = array(
				'id'        => $dts_option_id,
				'label'     => $dts_label,
				'permalink' => get_permalink( $dts_option_id ),
				'price'     => (float) dts_get_product_display_price( $dts_option, null, 'display' ),
				'current'   => (int) $dts_option_id === (int) $product->get_id(),
				'in_stock'  => $dts_option->is_in_stock(),
			);
		}
	}
	usort( $dts_size_options, static function ( $a, $b ) {
		return strnatcasecmp( $a['label'], $b['label'] );
	} );
}
$dts_single_payload['size_options'] = $dts_size_options;
?>
<script>window.DTSSingleProduct = <?php echo wp_json_encode( $dts_single_payload ); ?>;</script>
<main class="dts-product-page">
	<div id="dts-single-product-root"></div>
</main>
<?php get_footer(); ?>
