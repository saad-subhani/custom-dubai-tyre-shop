<?php
/**
 * VAT handling.
 *
 * Prices are stored VAT-inclusive. The cart converts them to an ex-VAT base so
 * line items and the subtotal read cleanly, then adds VAT back as an explicit
 * fee. WooCommerce's own tax engine stays off via a zero-rate class, so VAT is
 * never counted twice.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DTS_VAT_RATE' ) ) {
	define( 'DTS_VAT_RATE', 0.05 );
}

if ( ! function_exists( 'dts_get_product_vat_mode' ) ) {
	/**
	 * Price storage mode.
	 *
	 * Every product in the store is stored inclusive, so the mode is constant.
	 *
	 * @param int|WC_Product $product Product object or ID.
	 */
	function dts_get_product_vat_mode( $product ) {
		return 'incl';
	}
}

if ( ! function_exists( 'dts_get_product_display_price' ) ) {
	/**
	 * Price for a given display context.
	 *
	 * - 'display': the stored VAT-inclusive price.
	 * - 'cart':    $product->get_price() is already ex-VAT, set by
	 *              dts_set_ex_vat_cart_price, so it is returned untouched.
	 *
	 * @param WC_Product $product Product object.
	 * @param float|null $price   Optional base price override.
	 * @param string     $context 'display' or 'cart'.
	 */
	function dts_get_product_display_price( $product, $price = null, $context = 'display' ) {
		if ( ! $product instanceof WC_Product ) {
			return 0.0;
		}

		return null === $price ? (float) $product->get_price() : (float) $price;
	}
}

if ( ! function_exists( 'dts_get_product_vat_label' ) ) {
	/**
	 * VAT label for a display context.
	 *
	 * @param int|WC_Product $product Product object or ID.
	 * @param string         $context 'display' or 'cart'.
	 */
	function dts_get_product_vat_label( $product, $context = 'display' ) {
		return ( 'cart' === $context )
			? __( 'Ex. VAT', 'custom-dubai-tyre-shop' )
			: __( 'Inc. VAT', 'custom-dubai-tyre-shop' );
	}
}

if ( ! function_exists( 'dts_get_vat_amount' ) ) {
	/**
	 * VAT portion of an inclusive amount.
	 *
	 * @param float $inclusive VAT-inclusive amount.
	 */
	function dts_get_vat_amount( $inclusive ) {
		$inclusive = (float) $inclusive;

		return round( $inclusive - ( $inclusive / ( 1 + DTS_VAT_RATE ) ), wc_get_price_decimals() );
	}
}

if ( ! function_exists( 'dts_get_ex_vat_price' ) ) {
	/**
	 * Ex-VAT base price for an inclusive amount.
	 *
	 * @param float $inclusive VAT-inclusive amount.
	 */
	function dts_get_ex_vat_price( $inclusive ) {
		return round( (float) $inclusive / ( 1 + DTS_VAT_RATE ), wc_get_price_decimals() );
	}
}

if ( ! function_exists( 'dts_format_price_amount' ) ) {
	/**
	 * Format an AED amount without WooCommerce's wrapping markup.
	 *
	 * @param float $amount Amount.
	 */
	function dts_format_price_amount( $amount ) {
		return number_format_i18n( (float) $amount, wc_get_price_decimals() );
	}
}

if ( ! function_exists( 'dts_format_product_price_html' ) ) {
	/**
	 * Price markup with the VAT label appended.
	 *
	 * @param WC_Product $product Product object.
	 * @param float|null $price   Optional base price override.
	 * @param string     $context 'display' or 'cart'.
	 */
	function dts_format_product_price_html( $product, $price = null, $context = 'display' ) {
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		return sprintf(
			'%1$s <small class="dts-vat-label">%2$s</small>',
			wc_price( dts_get_product_display_price( $product, $price, $context ) ),
			esc_html( dts_get_product_vat_label( $product, $context ) )
		);
	}
}

if ( ! function_exists( 'dts_filter_product_price_html' ) ) {
	/**
	 * Apply the VAT label to WooCommerce-rendered prices.
	 *
	 * @param string     $price_html Existing price markup.
	 * @param WC_Product $product    Product object.
	 */
	function dts_filter_product_price_html( $price_html, $product ) {
		if ( is_admin() || ! $product instanceof WC_Product || '' === (string) $product->get_price() ) {
			return $price_html;
		}

		// Variable products already render a "from" price range.
		if ( $product->is_type( 'variable' ) ) {
			return $price_html;
		}

		return dts_format_product_price_html( $product, null, 'display' );
	}
}
add_filter( 'woocommerce_get_price_html', 'dts_filter_product_price_html', 20, 2 );

if ( ! function_exists( 'dts_filter_cart_item_price_html' ) ) {
	/**
	 * Apply the VAT label to cart and checkout unit prices.
	 *
	 * @param string $price_html Existing price markup.
	 * @param array  $cart_item  Cart item.
	 */
	function dts_filter_cart_item_price_html( $price_html, $cart_item ) {
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $price_html;
		}

		return dts_format_product_price_html( $cart_item['data'], null, 'cart' );
	}
}
add_filter( 'woocommerce_cart_item_price', 'dts_filter_cart_item_price_html', 20, 2 );

if ( ! function_exists( 'dts_filter_cart_item_subtotal_html' ) ) {
	/**
	 * Apply the VAT label to cart and checkout line subtotals.
	 *
	 * @param string $subtotal_html Existing subtotal markup.
	 * @param array  $cart_item     Cart item.
	 */
	function dts_filter_cart_item_subtotal_html( $subtotal_html, $cart_item ) {
		if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
			return $subtotal_html;
		}

		$quantity = isset( $cart_item['quantity'] ) ? (int) $cart_item['quantity'] : 1;

		return sprintf(
			'%1$s<br><small class="dts-vat-label">%2$s</small>',
			wc_price( dts_get_product_display_price( $cart_item['data'], null, 'cart' ) * $quantity ),
			esc_html( dts_get_product_vat_label( $cart_item['data'], 'cart' ) )
		);
	}
}
add_filter( 'woocommerce_cart_item_subtotal', 'dts_filter_cart_item_subtotal_html', 20, 2 );

if ( ! function_exists( 'dts_filter_cart_subtotal' ) ) {
	/**
	 * Force the cart subtotal to the ex-VAT total.
	 *
	 * @param string $subtotal_html Existing subtotal markup.
	 * @param string $compound      Unused by WooCommerce.
	 * @param WC_Cart $cart         Cart object.
	 */
	function dts_filter_cart_subtotal( $subtotal_html, $compound, $cart ) {
		if ( ( ! is_cart() && ! is_checkout() ) || ! $cart instanceof WC_Cart ) {
			return $subtotal_html;
		}

		return wc_price( dts_get_cart_subtotal_ex_vat( $cart ) );
	}
}
add_filter( 'woocommerce_cart_subtotal', 'dts_filter_cart_subtotal', 20, 3 );

if ( ! function_exists( 'dts_get_cart_subtotal_ex_vat' ) ) {
	/**
	 * Ex-VAT subtotal for a cart.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	function dts_get_cart_subtotal_ex_vat( $cart ) {
		$subtotal = 0.0;

		if ( ! $cart instanceof WC_Cart ) {
			return $subtotal;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$subtotal += dts_get_product_display_price( $cart_item['data'], null, 'cart' ) * (int) $cart_item['quantity'];
		}

		return $subtotal;
	}
}

if ( ! function_exists( 'dts_rename_cart_totals_heading' ) ) {
	/**
	 * Rename "Cart totals" to "Order totals" on the cart page.
	 *
	 * @param string $translated Translated text.
	 * @param string $text       Original text.
	 * @param string $domain     Text domain.
	 */
	function dts_rename_cart_totals_heading( $translated, $text, $domain ) {
		if ( 'woocommerce' === $domain && 'Cart totals' === $text && is_cart() ) {
			return __( 'Order totals', 'custom-dubai-tyre-shop' );
		}

		return $translated;
	}
}
add_filter( 'gettext', 'dts_rename_cart_totals_heading', 20, 3 );

if ( ! function_exists( 'dts_set_ex_vat_cart_price' ) ) {
	/**
	 * Convert stored VAT-inclusive prices to an ex-VAT base for the cart.
	 *
	 * WooCommerce multiplies the ex-VAT base by the line quantity, so the
	 * subtotal is tax-exclusive. The VAT fee added by dts_add_vat_cart_fee brings
	 * the grand total back to the inclusive price.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	function dts_set_ex_vat_cart_price( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart ) {
			return;
		}

		$processed = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$product_id = $product->get_id();

			// The same product can appear on several lines; set its price once.
			if ( in_array( $product_id, $processed, true ) ) {
				continue;
			}

			$product->set_price( dts_get_ex_vat_price( (float) $product->get_price( 'edit' ) ) );

			$processed[] = $product_id;
		}
	}
}
add_action( 'woocommerce_before_calculate_totals', 'dts_set_ex_vat_cart_price', 10, 1 );

if ( ! function_exists( 'dts_add_vat_cart_fee' ) ) {
	/**
	 * Add the UAE VAT fee to cart and checkout totals.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	function dts_add_vat_cart_fee( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! $cart instanceof WC_Cart || $cart->is_empty() ) {
			return;
		}

		$vat_base = 0.0;

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! $cart_item['data'] instanceof WC_Product ) {
				continue;
			}

			$line_total = isset( $cart_item['line_total'] )
				? (float) $cart_item['line_total']
				: dts_get_product_display_price( $cart_item['data'], null, 'cart' ) * (int) $cart_item['quantity'];

			$vat_base += max( 0, $line_total );
		}

		$vat_amount = round( $vat_base * DTS_VAT_RATE, wc_get_price_decimals() );

		if ( $vat_amount > 0 ) {
			$cart->add_fee(
				sprintf(
					/* translators: %s: VAT percentage. */
					__( 'VAT (%s%%)', 'custom-dubai-tyre-shop' ),
					number_format_i18n( DTS_VAT_RATE * 100, 0 )
				),
				$vat_amount,
				false
			);
		}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'dts_add_vat_cart_fee', 20 );

if ( ! function_exists( 'dts_force_zero_tax_class' ) ) {
	/**
	 * Keep products out of WooCommerce's own tax engine.
	 *
	 * VAT is added once, by dts_add_vat_cart_fee.
	 *
	 * @param string     $tax_class Tax class.
	 * @param WC_Product $product   Product object.
	 */
	function dts_force_zero_tax_class( $tax_class, $product ) {
		return 'zero-rate';
	}
}
add_filter( 'woocommerce_product_get_tax_class', 'dts_force_zero_tax_class', 20, 2 );
add_filter( 'woocommerce_product_variation_get_tax_class', 'dts_force_zero_tax_class', 20, 2 );

if ( ! function_exists( 'dts_get_cart_totals_payload' ) ) {
	/**
	 * Cart totals as structured data.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return array
	 */
	function dts_get_cart_totals_payload( $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return array();
		}

		$subtotal_ex_vat = dts_get_cart_subtotal_ex_vat( $cart );
		$vat             = round( $subtotal_ex_vat * DTS_VAT_RATE, wc_get_price_decimals() );

		$fees = array();

		if ( method_exists( $cart, 'get_fees' ) ) {
			foreach ( $cart->get_fees() as $fee ) {
				$fees[] = array(
					'id'      => $fee->id,
					'name'    => $fee->name,
					'amount'  => (float) $fee->amount,
					'taxable' => (bool) $fee->taxable,
				);
			}
		}

		return array(
			'subtotal_ex_vat' => $subtotal_ex_vat,
			'vat_rate'        => DTS_VAT_RATE,
			'vat'             => $vat,
			'fees'            => $fees,
			'shipping_total'  => (float) $cart->get_shipping_total(),
			'discount_total'  => (float) $cart->get_discount_total(),
			'total'           => (float) $cart->get_total( 'edit' ),
			'currency'        => get_woocommerce_currency(),
			'labels'          => array(
				'subtotal' => __( 'Subtotal (Ex. VAT)', 'custom-dubai-tyre-shop' ),
				'vat'      => sprintf( 'VAT (%s%%)', number_format_i18n( DTS_VAT_RATE * 100, 0 ) ),
				'total'    => __( 'Total', 'custom-dubai-tyre-shop' ),
			),
		);
	}
}
