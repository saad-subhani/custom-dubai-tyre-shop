<?php
/**
 * Product card shortcode mount point.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the product card component.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function custom_dubai_render_product_card_shortcode( $atts ) {
	$atts = shortcode_atts(
		array(
			'product_id' => 0,
		),
		$atts,
		'custom_dubai_product_card'
	);

	$product_id = absint( $atts['product_id'] );

	if ( ! $product_id ) {
		$product_id = get_the_ID();
	}

	if ( ! function_exists( 'dts_get_product_payload' ) ) {
		return '';
	}

	$product = dts_get_product_payload( $product_id );

	if ( ! $product ) {
		return '';
	}

	$size_parts = array();
	$meta = $product['meta'];

	if ( ! empty( $meta['width'] ) && ! empty( $meta['rim_size'] ) ) {
		$size_parts[] = $meta['width'];
		if ( ! empty( $meta['height'] ) ) {
			$size_parts[] = $meta['height'];
		}
		$size_parts[] = 'R' . $meta['rim_size'];
	}

	$card_data = array(
		'name'             => $product['name'],
		'image'            => $product['image'],
		'size'             => ! empty( $size_parts ) ? implode('/', $size_parts) : '',
		'brand'            => $product['brand'] ?? '',
		'year'             => $meta['year'] ?? '',
		'origin'           => $meta['origin'] ?? '',
		'origin_flag'      => $meta['origin_flag'] ?? '',
		'warranty'         => $meta['warranty'] ?? '',
		'badge'            => ! empty( $product['badges'] ) ? $product['badges'][0] : '',
		'price'            => $product['price'],
		'regular_price'    => $product['regular_price'],
		'set_price'        => $product['price'] * 4,
		'currency'         => $product['currency'],
		'in_stock'         => $product['in_stock'],
		'fitment_included' => true,
	);

	$json_data = wp_json_encode( $card_data );

	ob_start();
	?>
	<div
		id="custom-dubai-product-card"
		class="custom-dubai-product-card-root"
		data-product="<?php echo esc_attr( $json_data ); ?>"
	></div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_dubai_product_card', 'custom_dubai_render_product_card_shortcode' );
