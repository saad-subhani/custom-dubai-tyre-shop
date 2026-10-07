<?php
/**
 * React WooCommerce category slider shortcode.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collect the product categories the slider should show.
 *
 * A category is never dropped for lacking a thumbnail: it falls back to the
 * store placeholder. Skipping those terms is what previously left the slider
 * rendering an empty string on stores with no category images set.
 *
 * @param array $atts Shortcode attributes.
 * @return array List of category payload rows.
 */
function custom_dubai_shop_by_category_terms( $atts ) {
	$args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => 'false' !== $atts['hide_empty'],
		'orderby'    => $atts['orderby'],
		'order'      => $atts['order'],
		'number'     => (int) $atts['number'],
	);

	// 'any' is the only way to ask for nested categories, everything else is
	// treated as a term id so a parent can be pinned deliberately.
	if ( 'any' !== $atts['parent'] ) {
		$args['parent'] = (int) $atts['parent'];
	}

	$terms = get_terms( $args );

	// Top-level only is the preferred shape, but fall back to nested categories
	// so the slider still renders on stores that group everything under a parent.
	$is_top_level = isset( $args['parent'] ) && 0 === $args['parent'];

	if ( is_wp_error( $terms ) || ( empty( $terms ) && $is_top_level ) ) {
		unset( $args['parent'] );
		$terms = get_terms( $args );
	}

	// Still nothing? The categories exist but hold no published products yet, so
	// fall back to listing them rather than rendering an invisible section.
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		$args['hide_empty'] = false;
		$terms               = get_terms( $args );
	}

	if ( is_wp_error( $terms ) ) {
		return array();
	}

	$categories = array();

	foreach ( $terms as $term ) {
		$url = get_term_link( $term );

		if ( is_wp_error( $url ) ) {
			continue;
		}

		$image_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
		$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'medium_large' ) : false;

		$categories[] = array(
			'id'          => (int) $term->term_id,
			'name'        => $term->name,
			'description' => wp_trim_words( wp_strip_all_tags( $term->description ), 8, '' ),
			'url'         => $url,
			'image'       => array(
				'url'    => $image ? $image[0] : wc_placeholder_img_src(),
				'srcset' => $image_id ? (string) wp_get_attachment_image_srcset( $image_id, 'medium_large' ) : '',
				'alt'    => $image_id ? (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) : '',
			),
		);
	}

	return $categories;
}

/**
 * Render the category slider mount point.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function custom_dubai_render_shop_by_category_shortcode( $atts ) {
	$shop_page_id = function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0;

	$atts = shortcode_atts(
		array(
			'number'             => 20,
			'parent'             => 0,
			'orderby'            => 'menu_order',
			'order'              => 'ASC',
			'hide_empty'         => 'true',
			'all_categories_url' => $shop_page_id > 0 ? get_permalink( $shop_page_id ) : home_url( '/shop/' ),
		),
		$atts,
		'custom_dubai_shop_by_category'
	);

	$config = array(
		'categories'       => custom_dubai_shop_by_category_terms( $atts ),
		'allCategoriesUrl' => $atts['all_categories_url'],
	);

	ob_start();
	?>
	<script>window.DTSShopCategories = <?php echo wp_json_encode( $config ); ?>;</script>
	<div id="dts-shop-categories-root"></div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'custom_dubai_shop_by_category', 'custom_dubai_render_shop_by_category_shortcode' );