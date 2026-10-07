<?php
/**
 * Product payloads for the JSON endpoints.
 *
 * The store's backend never renders markup, so every product the client needs is
 * described here as structured data. Keeping the shape in one place means the
 * endpoints stay small and the payload contract is easy to change in one spot.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_product_payload' ) ) {
	/**
	 * Full product payload.
	 *
	 * @param int|WC_Product $product Product object or ID.
	 * @return array|null Null when the product is unavailable.
	 */
	function dts_get_product_payload( $product ) {
		$product = $product instanceof WC_Product ? $product : wc_get_product( $product );

		if ( ! $product || ! $product->exists() ) {
			return null;
		}

		$product_id   = $product->get_id();
		$is_variable  = $product->is_type( 'variable' );
		$price        = (float) $product->get_price();
		$regular_price = (float) $product->get_regular_price();

		$payload = array(
			'id'             => $product_id,
			'parent_id'      => $product->is_type( 'variation' ) ? $product->get_parent_id() : 0,
			'type'           => dts_get_product_type( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id ),
			'name'           => $product->get_name(),
			'slug'           => $product->get_slug(),
			'permalink'      => $product->get_permalink(),
			'sku'            => $product->get_sku(),
			'type_label'     => dts_get_product_type_label( dts_get_product_type( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product_id ) ),
			'image'          => dts_get_product_image_payload( $product ),
			'price'          => $price,
			'regular_price'  => $regular_price,
			'on_sale'        => $product->is_on_sale(),
			'sale_price'     => (float) $product->get_sale_price(),
			'price_html'     => $product->get_price_html(),
			'currency'       => get_woocommerce_currency(),
			'price_tax_incl' => dts_get_product_display_price( $product, null, 'display' ),
			'vat_label'      => dts_get_product_vat_label( $product, 'display' ),
			'purchasable'    => $product->is_purchasable() && $product->is_in_stock(),
			'in_stock'       => $product->is_in_stock(),
			'stock_quantity' => $product->get_stock_quantity(),
			'add_to_cart_url' => $product->add_to_cart_url(),
			'add_to_cart_text' => $product->add_to_cart_text(),
			'brand'          => dts_get_product_brand_payload( $product_id ),
			'categories'     => dts_get_product_category_payload( $product_id ),
			'attributes'     => dts_get_product_attribute_payload( $product ),
			'meta'           => dts_get_product_meta_payload( $product_id ),
			'badges'         => dts_get_product_badges( $product_id ),
			'has_variations' => $is_variable,
			'variations'     => $is_variable ? dts_get_product_variation_payloads( $product ) : array(),
			'total_sales'    => (int) get_post_meta( $product_id, 'total_sales', true ),
			'rating'         => array(
				'average' => (float) $product->get_average_rating(),
				'count'   => (int) $product->get_review_count(),
			),
		);

		/**
		 * Filter a product payload before it is sent to the client.
		 *
		 * @param array      $payload Product payload.
		 * @param WC_Product $product Product object.
		 */
		return apply_filters( 'dts_product_payload', $payload, $product );
	}
}

if ( ! function_exists( 'dts_get_products_payload' ) ) {
	/**
	 * Payloads for a list of products, skipping any that cannot be loaded.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array
	 */
	function dts_get_products_payload( $ids ) {
		$payloads = array();

		foreach ( (array) $ids as $product_id ) {
			$payload = dts_get_product_payload( $product_id );

			if ( null !== $payload ) {
				$payloads[] = $payload;
			}
		}

		return $payloads;
	}
}

if ( ! function_exists( 'dts_get_product_type_label' ) ) {
	/** Human label for a product family. */
	function dts_get_product_type_label( $type ) {
		$rules = dts_get_product_type_rules();

		if ( isset( $rules[ $type ]['label'] ) ) {
			return $rules[ $type ]['label'];
		}

		return 'products';
	}
}

if ( ! function_exists( 'dts_get_product_image_payload' ) ) {
	/**
	 * Image descriptor: absolute URL, alt text and dimensions.
	 *
	 * @param WC_Product $product Product object.
	 */
	function dts_get_product_image_payload( $product ) {
		$image_id = $product->get_image_id();

		if ( ! $image_id ) {
			return array(
				'id'    => 0,
				'url'   => wc_placeholder_img_src(),
				'alt'   => $product->get_name(),
				'width' => 0,
				'height' => 0,
			);
		}

		$image = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );

		return array(
			'id'     => $image_id,
			'url'    => $image ? $image[0] : wc_placeholder_img_src(),
			'srcset' => wp_get_attachment_image_srcset( $image_id, 'woocommerce_thumbnail' ) ?: null,
			'alt'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
			'width'  => $image ? (int) $image[1] : 0,
			'height' => $image ? (int) $image[2] : 0,
		);
	}
}

if ( ! function_exists( 'dts_get_product_gallery_payload' ) ) {
	/**
	 * Gallery images for a product.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_gallery_payload( $product_id ) {
		$gallery = array();

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );

			if ( $product ) {
				foreach ( $product->get_gallery_image_ids() as $attachment_id ) {
					$image = wp_get_attachment_image_src( $attachment_id, 'woocommerce_gallery_thumbnail' );

					if ( $image ) {
						$gallery[] = array(
							'id'     => $attachment_id,
							'url'    => $image[0],
							'full'   => wp_get_attachment_url( $attachment_id ),
							'alt'    => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
							'width'  => (int) $image[1],
							'height' => (int) $image[2],
						);
					}
				}
			}
		}

		return $gallery;
	}
}

if ( ! function_exists( 'dts_get_product_brand_payload' ) ) {
	/**
	 * Brand term assigned to a product.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_brand_payload( $product_id ) {
		$taxonomy = dts_get_brand_taxonomy();

		if ( '' === $taxonomy ) {
			return null;
		}

		$terms = wp_get_post_terms( $product_id, $taxonomy );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return null;
		}

		$term = $terms[0];

		return array(
			'id'   => (int) $term->term_id,
			'slug' => $term->slug,
			'name' => $term->name,
			'url'  => (string) get_term_link( $term ),
			'logo' => dts_get_brand_logo_url( $term->slug ),
		);
	}
}

if ( ! function_exists( 'dts_get_product_category_payload' ) ) {
	/**
	 * Categories assigned to a product.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_category_payload( $product_id ) {
		$terms = get_the_terms( $product_id, 'product_cat' );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return array();
		}

		$categories = array();

		foreach ( $terms as $term ) {
			$categories[] = array(
				'id'   => (int) $term->term_id,
				'slug' => $term->slug,
				'name' => $term->name,
				'url'  => (string) get_term_link( $term ),
			);
		}

		return $categories;
	}
}

if ( ! function_exists( 'dts_get_product_attribute_payload' ) ) {
	/**
	 * Writable product attributes, as label/value pairs.
	 *
	 * @param WC_Product $product Product object.
	 */
	function dts_get_product_attribute_payload( $product ) {
		$attributes = array();

		foreach ( $product->get_attributes() as $attribute ) {
			if ( ! $attribute->get_visible() ) {
				continue;
			}

			$options = array();

			if ( $attribute->is_taxonomy() ) {
				foreach ( $attribute->get_terms() as $term ) {
					$options[] = array(
						'slug' => $term->slug,
						'name' => $term->name,
					);
				}
			} else {
				foreach ( explode( ',', $attribute->get_options() ) as $option ) {
					$option = trim( $option );

					if ( '' !== $option ) {
						$options[] = array(
							'slug' => sanitize_title( $option ),
							'name' => $option,
						);
					}
				}
			}

			$attributes[] = array(
				'id'      => (int) $attribute->get_id(),
				'name'    => wc_attribute_label( $attribute->get_name() ),
				'slug'    => $attribute->get_name(),
				'taxonomy' => $attribute->get_taxonomy(),
				'options' => $options,
			);
		}

		return $attributes;
	}
}

if ( ! function_exists( 'dts_get_product_meta_payload' ) ) {
	/**
	 * Store-specific product metadata.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_meta_payload( $product_id ) {
		$width  = get_post_meta( $product_id, 'tyre_width', true );
		$height = get_post_meta( $product_id, 'tyre_height', true );
		$rim    = get_post_meta( $product_id, 'tyre_rim_size', true );

		if ( ! $width ) {
			$width = get_post_meta( $product_id, '_width', true );
		}
		if ( ! $height ) {
			$height = get_post_meta( $product_id, '_height', true );
		}

		$origin = dts_get_product_origin( $product_id );
		$model  = get_post_meta( $product_id, 'tyre_model', true );
		$vehicle_type = trim( (string) get_post_meta( $product_id, 'vehicle_type', true ) );
		if ( '' === $vehicle_type ) {
			$vehicle_type = trim( (string) get_post_meta( $product_id, 'tyre_type', true ) );
		}
		if ( 0 === strcasecmp( $vehicle_type, 'car' ) ) {
			$vehicle_type = 'Passenger';
		}

		$meta = array(
			'width'     => (string) $width,
			'height'    => (string) $height,
			'rim'       => (string) $rim,
			'rim_token' => dts_tyre_size_rim_token( $rim ),
			'run_flat'  => dts_is_run_flat_value( get_post_meta( $product_id, 'tyre_run_flat', true ) ),
			'warranty'  => (string) get_post_meta( $product_id, 'warranty', true ),
			'year'      => (string) get_post_meta( $product_id, 'year_manufacture', true ),
			'model'     => (string) ( $model ? $model : '' ),
			'origin'    => $origin,
			'origin_flag' => dts_get_country_flag( $origin ),
			'buy_3_get_1' => strtolower( (string) get_post_meta( $product_id, 'buy_3_get_1', true ) ) === 'yes',
			'tyre_type'  => (string) get_post_meta( $product_id, 'tyre_type', true ),
			'vehicle_type' => $vehicle_type,
			'tyre_tier'  => (string) get_post_meta( $product_id, 'tyre_tier', true ),
		);

		if ( $width && $rim ) {
			$meta['size_label']  = dts_format_tyre_size(
				array(
					'width'      => $width,
					'height'     => $height,
					'rim'        => $rim,
					'flotation'  => ( false !== stripos( $width, 'x' ) ),
				)
			);
			$meta['size_slug']   = dts_tyre_size_slug(
				dts_parse_tyre_size_string( $width . '/' . $height . ' ' . $rim )
			);
		}

		// Battery-specific metadata only travels with battery products.
		$meta['battery'] = array(
			'capacity'         => (string) get_post_meta( $product_id, 'capacity', true ),
			'reserve_capacity' => (string) get_post_meta( $product_id, 'reserve_capacity', true ),
			'cca'              => (string) get_post_meta( $product_id, 'cca', true ),
			'length_mm'        => (string) get_post_meta( $product_id, 'length_mm', true ),
			'width_mm'         => (string) get_post_meta( $product_id, 'width_mm', true ),
			'height_mm'        => (string) get_post_meta( $product_id, 'height_mm', true ),
			'voltage'          => (string) get_post_meta( $product_id, 'voltage', true ),
			'warranty_months'  => (string) get_post_meta( $product_id, 'warranty_months', true ),
		);

		return $meta;
	}
}

if ( ! function_exists( 'dts_get_product_badges' ) ) {
	/**
	 * Marketing badges derived from product data.
	 *
	 * @param int $product_id Product ID.
	 */
	function dts_get_product_badges( $product_id ) {
		$badges = array();

		if ( dts_is_run_flat_value( get_post_meta( $product_id, 'tyre_run_flat', true ) ) ) {
			$badges[] = array(
				'key'  => 'run_flat',
				'label' => 'Run Flat',
			);
		}

		if ( strtolower( (string) get_post_meta( $product_id, 'buy_3_get_1', true ) ) === 'yes' ) {
			$badges[] = array(
				'key'   => 'promo',
				'label' => 'Buy 3 Get 1 Free',
			);
		}

		$product = wc_get_product( $product_id );

		if ( $product && $product->is_on_sale() ) {
			$badges[] = array(
				'key'   => 'sale',
				'label' => 'Sale',
			);
		}

		return $badges;
	}
}

if ( ! function_exists( 'dts_get_product_variation_payloads' ) ) {
	/**
	 * Variations of a variable product.
	 *
	 * @param WC_Product $product Parent product.
	 */
	function dts_get_product_variation_payloads( $product ) {
		$variations = array();

		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );

			if ( ! $variation ) {
				continue;
			}

			$variations[] = array(
				'id'         => $variation_id,
				'sku'        => $variation->get_sku(),
				'attributes' => $variation->get_variation_attributes(),
				'price'      => (float) $variation->get_price(),
				'on_sale'    => $variation->is_on_sale(),
				'in_stock'   => $variation->is_in_stock(),
				'image'      => dts_get_product_image_payload( $variation ),
			);
		}

		return $variations;
	}
}

if ( ! function_exists( 'dts_get_pair_payload' ) ) {
	/**
	 * Payload for one staggered set.
	 *
	 * A set is always sold as two front plus two rear tyres, so the price is
	 * doubled and each side carries its own default quantity of two.
	 *
	 * @param array $pair Pair from dts_pair_left_and_right().
	 */
	function dts_get_pair_payload( $pair ) {
		$left  = $pair['left'];
		$right = $pair['right'];

		$set_price = ( $left['price'] + $right['price'] ) * 2;

		return array(
			'id'         => $left['id'] . '-' . $right['id'],
			'brand_slug' => $left['brand_slug'],
			'brand_name' => $left['brand_name'],
			'model'      => $left['model_key'],
			'run_flat'   => $left['run_flat'],
			'year'       => $left['year'],
			'promo'      => ( 'yes' === $left['promo'] ),
			'warranty'   => $left['warranty'],
			'front'      => $left,
			'rear'       => $right,
			'set'        => array(
				'quantity'   => 4,
				'price'      => round( $set_price, 2 ),
				'price_html' => wc_price( $set_price ),
			),
			'add_to_cart' => array(
				'items' => array(
					array(
						'product_id' => $left['id'],
						'quantity'   => 2,
					),
					array(
						'product_id' => $right['id'],
						'quantity'   => 2,
					),
				),
			),
		);
	}
}
