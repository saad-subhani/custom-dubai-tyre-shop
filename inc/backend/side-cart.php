<?php
/**
 * Side cart backend.
 *
 * The drawer itself is frontend, but everything it needs to render — line
 * items, ex-VAT pricing, VAT labels, promo flags, totals and the fragments the
 * theme refreshes — is assembled here and returned as JSON.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_side_cart_theme' ) ) {
	/** Theme text domain, kept in one place for the cart strings. */
	function dts_get_side_cart_theme() {
		return 'custom-dubai-tyre-shop';
	}
}

if ( ! function_exists( 'dts_get_cart_count_text' ) ) {
	/**
	 * Localised item-count sentence.
	 *
	 * @param int|null $count Optional count, defaults to the live cart.
	 */
	function dts_get_cart_count_text( $count = null ) {
		if ( null === $count ) {
			$count = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
		}

		return sprintf(
			/* translators: %d: cart item count. */
			_n( '%d item in your order', '%d items in your order', $count, dts_get_side_cart_theme() ),
			$count
		);
	}
}

if ( ! function_exists( 'dts_get_side_cart_subtotal' ) ) {
	/**
	 * Cart subtotal after the ex-VAT line prices have been applied.
	 *
	 * @return float
	 */
	function dts_get_side_cart_subtotal() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0.0;
		}

		return dts_get_cart_subtotal_ex_vat( WC()->cart );
	}
}

if ( ! function_exists( 'dts_get_side_cart_subtotal_label' ) ) {
	/** Label for the ex-VAT subtotal row. */
	function dts_get_side_cart_subtotal_label() {
		return __( 'Subtotal (Ex. VAT)', dts_get_side_cart_theme() );
	}
}

if ( ! function_exists( 'dts_get_product_size_label' ) ) {
	/**
	 * Compact tyre-size label for a product, e.g. "205/70 R16C".
	 *
	 * Returns an empty string for products that are not sized tyres.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	function dts_get_product_size_label( $product_id ) {
		$width  = get_post_meta( $product_id, 'tyre_width', true );
		$height = get_post_meta( $product_id, 'tyre_height', true );
		$rim    = get_post_meta( $product_id, 'tyre_rim_size', true );

		if ( ! $width ) {
			$width = get_post_meta( $product_id, '_width', true );
		}

		if ( ! $height ) {
			$height = get_post_meta( $product_id, '_height', true );
		}

		if ( '' === trim( (string) $width ) || '' === trim( (string) $rim ) ) {
			return '';
		}

		$flotation = (bool) preg_match( '/x/i', (string) $width );

		return dts_format_size_parts(
			$flotation ? rtrim( (string) $width, 'xX' ) : $width,
			$height,
			dts_tyre_size_rim_token( $rim )
		);
	}
}

if ( ! function_exists( 'dts_get_side_cart_item_payload' ) ) {
	/**
	 * One cart line as structured data.
	 *
	 * @param string   $cart_item_key Cart item key.
	 * @param array    $cart_item     Cart item.
	 * @return array|null
	 */
	function dts_get_side_cart_item_payload( $cart_item_key, $cart_item ) {
		$product = isset( $cart_item['data'] ) ? $cart_item['data'] : null;

		if ( ! $product instanceof WC_Product || ! $product->exists() ) {
			return null;
		}

		if ( isset( $cart_item['quantity'] ) && $cart_item['quantity'] <= 0 ) {
			return null;
		}

		$quantity   = (int) $cart_item['quantity'];
		$unit_price = dts_get_product_display_price( $product, null, 'cart' );
		$line_total = $unit_price * $quantity;
		$product_id = $product->get_id();
		$promo      = get_post_meta( $product_id, 'buy_3_get_1', true );
		$has_promo  = '' !== $promo && 'yes' === strtolower( trim( (string) $promo ) );

		$image_id  = $product->get_image_id();
		$thumbnail = $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '';

		return array(
			'key'                  => $cart_item_key,
			'product_id'           => $product_id,
			'variation_id'         => (int) ( $cart_item['variation_id'] ?? 0 ),
			'name'                 => $product->get_name(),
			'permalink'            => $product->is_visible() ? $product->get_permalink( $cart_item ) : '',
			'thumbnail'            => $thumbnail,
			'quantity'             => $quantity,
			'size'                 => dts_get_product_size_label( $product_id ),
			'promo'                => $has_promo,
			'unit_price'           => (float) $unit_price,
			'unit_price_formatted' => dts_format_price_amount( $unit_price ),
			'line_total'           => (float) $line_total,
			'line_total_formatted' => dts_format_price_amount( $line_total ),
			'vat_label'            => function_exists( 'dts_get_product_vat_label' ) ? dts_get_product_vat_label( $product, 'cart' ) : '',
		);
	}
}

if ( ! function_exists( 'dts_get_side_cart_payload' ) ) {
	/**
	 * Everything the drawer needs, as JSON-ready data.
	 *
	 * @return array
	 */
	function dts_get_side_cart_payload() {
		$has_cart = function_exists( 'WC' ) && WC()->cart;
		$items    = array();

		if ( $has_cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$payload = dts_get_side_cart_item_payload( $cart_item_key, $cart_item );

				if ( null !== $payload ) {
					$items[] = $payload;
				}
			}
		}

		$item_count = (int) ( $has_cart ? WC()->cart->get_cart_contents_count() : 0 );

		return array(
			'is_empty'     => empty( $items ),
			'item_count'   => $item_count,
			'unique_count' => count( $items ),
			'count_text'   => dts_get_cart_count_text( $item_count ),
			'currency'     => function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '',
			'items'        => $items,
			'totals'       => $has_cart ? dts_get_cart_totals_payload( WC()->cart ) : array(),
			'labels'       => array(
				'subtotal' => dts_get_side_cart_subtotal_label(),
				'empty'    => __( 'Your cart is empty', dts_get_side_cart_theme() ),
				'note'     => __( 'To find out your shipping cost, please proceed to checkout.', dts_get_side_cart_theme() ),
			),
			'urls'         => array(
				'cart'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
				'checkout' => function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '',
			),
		);
	}
}

if ( ! function_exists( 'dts_prepare_cart_for_ajax' ) ) {
	/**
	 * Make sure a live cart exists for an AJAX request, then recalculate it.
	 *
	 * @return bool Whether a usable cart is available.
	 */
	function dts_prepare_cart_for_ajax() {
		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		WC()->cart->calculate_totals();

		return true;
	}
}

if ( ! function_exists( 'dts_get_side_cart_nonce' ) ) {
	/** Nonce for the side-cart endpoints. */
	function dts_get_side_cart_nonce() {
		return wp_create_nonce( 'dts_side_cart' );
	}
}

if ( ! function_exists( 'dts_side_cart_ajax_response' ) ) {
	/** Send the current cart as JSON. */
	function dts_side_cart_ajax_response() {
		wp_send_json_success( dts_get_side_cart_payload() );
	}
}

if ( ! function_exists( 'dts_side_cart_get_ajax' ) ) {
	/** Return the current cart. */
	function dts_side_cart_get_ajax() {
		check_ajax_referer( 'dts_side_cart', 'nonce' );

		if ( ! dts_prepare_cart_for_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable', dts_get_side_cart_theme() ) ), 500 );
		}

		dts_side_cart_ajax_response();
	}
}
add_action( 'wp_ajax_dts_get_side_cart', 'dts_side_cart_get_ajax' );
add_action( 'wp_ajax_nopriv_dts_get_side_cart', 'dts_side_cart_get_ajax' );

if ( ! function_exists( 'dts_side_cart_update_ajax' ) ) {
	/** Change the quantity of one line. */
	function dts_side_cart_update_ajax() {
		check_ajax_referer( 'dts_side_cart', 'nonce' );

		if ( ! dts_prepare_cart_for_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable', dts_get_side_cart_theme() ) ), 500 );
		}

		$cart_item_key = isset( $_POST['cart_item_key'] ) ? wc_clean( wp_unslash( $_POST['cart_item_key'] ) ) : '';
		$quantity      = isset( $_POST['quantity'] ) ? max( 0, absint( $_POST['quantity'] ) ) : 0;

		if ( '' === $cart_item_key || ! isset( WC()->cart->cart_contents[ $cart_item_key ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Cart item not found', dts_get_side_cart_theme() ) ), 404 );
		}

		WC()->cart->set_quantity( $cart_item_key, $quantity, true );
		WC()->cart->calculate_totals();

		dts_side_cart_ajax_response();
	}
}
add_action( 'wp_ajax_dts_update_side_cart_item', 'dts_side_cart_update_ajax' );
add_action( 'wp_ajax_nopriv_dts_update_side_cart_item', 'dts_side_cart_update_ajax' );

if ( ! function_exists( 'dts_side_cart_add_ajax' ) ) {
	/** Add a simple product to the cart. */
	function dts_side_cart_add_ajax() {
		check_ajax_referer( 'dts_side_cart', 'nonce' );

		if ( ! dts_prepare_cart_for_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable', dts_get_side_cart_theme() ) ), 500 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity   = isset( $_POST['quantity'] ) ? max( 1, absint( $_POST['quantity'] ) ) : 1;
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( ! $product || ! $product->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'Product not purchasable', dts_get_side_cart_theme() ) ), 400 );
		}

		// Variable products need options, so the client sends the visitor to the
		// product page instead.
		if ( ! $product->is_type( 'simple' ) ) {
			wp_send_json_error(
				array(
					'message'   => __( 'This product has options', dts_get_side_cart_theme() ),
					'action'    => 'redirect',
					'permalink' => $product->get_permalink(),
				),
				409
			);
		}

		if ( ! WC()->cart->add_to_cart( $product_id, $quantity ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not add to cart', dts_get_side_cart_theme() ) ), 400 );
		}

		dts_side_cart_ajax_response();
	}
}
add_action( 'wp_ajax_dts_add_to_side_cart', 'dts_side_cart_add_ajax' );
add_action( 'wp_ajax_nopriv_dts_add_to_side_cart', 'dts_side_cart_add_ajax' );

if ( ! function_exists( 'dts_side_cart_double_add_ajax' ) ) {
	/** Add a staggered front/rear pair in one request. */
	function dts_side_cart_double_add_ajax() {
		check_ajax_referer( 'dts_side_cart', 'nonce' );

		if ( ! dts_prepare_cart_for_ajax() ) {
			wp_send_json_error( array( 'message' => __( 'Cart unavailable', dts_get_side_cart_theme() ) ), 500 );
		}

		$pid1 = isset( $_POST['product_id_1'] ) ? absint( $_POST['product_id_1'] ) : 0;
		$qty1 = isset( $_POST['quantity_1'] ) ? max( 1, absint( $_POST['quantity_1'] ) ) : 1;
		$pid2 = isset( $_POST['product_id_2'] ) ? absint( $_POST['product_id_2'] ) : 0;
		$qty2 = isset( $_POST['quantity_2'] ) ? max( 1, absint( $_POST['quantity_2'] ) ) : 1;

		if ( ! $pid1 || ! $pid2 ) {
			wp_send_json_error( array( 'message' => __( 'Missing product IDs', dts_get_side_cart_theme() ) ), 400 );
		}

		$product1 = wc_get_product( $pid1 );
		$product2 = wc_get_product( $pid2 );

		if ( ! $product1 || ! $product1->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'Front tyre not purchasable', dts_get_side_cart_theme() ) ), 400 );
		}

		if ( ! $product2 || ! $product2->is_purchasable() ) {
			wp_send_json_error( array( 'message' => __( 'Rear tyre not purchasable', dts_get_side_cart_theme() ) ), 400 );
		}

		// Add the rear first: if the second add fails, the front tyre is rolled
		// back so the visitor never ends up with half a set.
		$rear_key = WC()->cart->add_to_cart( $pid2, $qty2 );

		if ( ! $rear_key ) {
			wp_send_json_error( array( 'message' => __( 'Could not add the rear tyre', dts_get_side_cart_theme() ) ), 400 );
		}

		$front_key = WC()->cart->add_to_cart( $pid1, $qty1 );

		if ( ! $front_key ) {
			WC()->cart->remove_cart_item( $rear_key );
			wp_send_json_error( array( 'message' => __( 'Could not add the front tyre', dts_get_side_cart_theme() ) ), 400 );
		}

		$payload              = dts_get_side_cart_payload();
		$payload['pair_keys'] = array(
			'front' => $front_key,
			'rear'  => $rear_key,
		);

		wp_send_json_success( $payload );
	}
}
add_action( 'wp_ajax_dts_add_double_to_side_cart', 'dts_side_cart_double_add_ajax' );
add_action( 'wp_ajax_nopriv_dts_add_double_to_side_cart', 'dts_side_cart_double_add_ajax' );

if ( ! function_exists( 'dts_side_cart_woocommerce_fragments' ) ) {
	/**
	 * Keep WooCommerce's own fragment refresh working.
	 *
	 * The theme no longer ships cart HTML, so the fragment map only carries the
	 * count badge the header script updates.
	 *
	 * @param array $fragments Existing fragments.
	 * @return array
	 */
	function dts_side_cart_woocommerce_fragments( $fragments ) {
		$fragments['.header-cart-link .cart-count'] = '<span class="cart-count">' . esc_html(
			( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0
		) . '</span>';

		return $fragments;
	}
}
add_filter( 'woocommerce_add_to_cart_fragments', 'dts_side_cart_woocommerce_fragments' );
