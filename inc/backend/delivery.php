<?php
/**
 * Delivery method and fitting-centre backend.
 *
 * The checkout drawer that renders the cards and the fitting-centre modal is
 * frontend; this file owns the delivery catalogue, the session selection, the
 * shipping and mobile-service fees, the 3+1 discount and the order metadata the
 * emails and PDF receipts read.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_delivery_theme' ) ) {
	/** Text domain used by the delivery strings. */
	function dts_get_delivery_theme() {
		return 'custom-dubai-tyre-shop';
	}
}

if ( ! function_exists( 'dts_get_fitting_centers' ) ) {
	/**
	 * Fitting centres grouped by city.
	 *
	 * Hours are returned as plain lines and as a structured weekly schedule; the
	 * theme renders both, so neither is baked into HTML here.
	 *
	 * @return array
	 */
	function dts_get_fitting_centers() {
		$centers = array(
			'abu_dhabi' => array(
				'label'   => __( 'Abu Dhabi', dts_get_delivery_theme() ),
				'centers' => array(
					array(
						'id'       => 'mussafah',
						'name'     => __( 'Mussafah Industrial Fitting Center', dts_get_delivery_theme() ),
						'hours'    => array(
							'Saturday to Thursday: 09:00 - 20:00',
							'Sunday: 09:00 - 20:00',
							'Friday: 09:00 - 12:00 & 14:30 - 20:00',
						),
						'schedule' => array(
							'Saturday'  => array( array( '09:00', '20:00' ) ),
							'Sunday'    => array( array( '09:00', '20:00' ) ),
							'Monday'    => array( array( '09:00', '20:00' ) ),
							'Tuesday'   => array( array( '09:00', '20:00' ) ),
							'Wednesday' => array( array( '09:00', '20:00' ) ),
							'Thursday'  => array( array( '09:00', '20:00' ) ),
							'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
						),
					),
				),
			),
			'dubai'     => array(
				'label'   => __( 'Dubai', dts_get_delivery_theme() ),
				'centers' => array(
					array(
						'id'       => 'alqouz',
						'name'     => __( 'Alqouz Fitting Center', dts_get_delivery_theme() ),
						'hours'    => array(
							'Saturday to Thursday: 09:00 - 20:00',
							'Sunday: 09:00 - 13:00',
							'Friday: 09:00 - 12:00 & 14:30 - 20:00',
						),
						'schedule' => array(
							'Saturday'  => array( array( '09:00', '20:00' ) ),
							'Sunday'    => array( array( '09:00', '13:00' ) ),
							'Monday'    => array( array( '09:00', '20:00' ) ),
							'Tuesday'   => array( array( '09:00', '20:00' ) ),
							'Wednesday' => array( array( '09:00', '20:00' ) ),
							'Thursday'  => array( array( '09:00', '20:00' ) ),
							'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
						),
					),
					array(
						'id'       => 'tecom',
						'name'     => __( 'Tecom Fitting Center', dts_get_delivery_theme() ),
						'hours'    => array(
							'Saturday to Thursday: 09:00 - 20:00',
							'Sunday: 09:00 - 20:00',
							'Friday: 09:00 - 12:00 & 14:30 - 20:00',
						),
						'schedule' => array(
							'Saturday'  => array( array( '09:00', '20:00' ) ),
							'Sunday'    => array( array( '09:00', '20:00' ) ),
							'Monday'    => array( array( '09:00', '20:00' ) ),
							'Tuesday'   => array( array( '09:00', '20:00' ) ),
							'Wednesday' => array( array( '09:00', '20:00' ) ),
							'Thursday'  => array( array( '09:00', '20:00' ) ),
							'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
						),
					),
					array(
						'id'       => 'ras_al_khor',
						'name'     => __( 'Ras Al Khor Fitting Center', dts_get_delivery_theme() ),
						'hours'    => array(
							'Saturday to Thursday: 09:00 - 20:00',
							'Sunday: 09:00 - 20:00',
							'Friday: 09:00 - 12:00 & 14:30 - 20:00',
						),
						'schedule' => array(
							'Saturday'  => array( array( '09:00', '20:00' ) ),
							'Sunday'    => array( array( '09:00', '20:00' ) ),
							'Monday'    => array( array( '09:00', '20:00' ) ),
							'Tuesday'   => array( array( '09:00', '20:00' ) ),
							'Wednesday' => array( array( '09:00', '20:00' ) ),
							'Thursday'  => array( array( '09:00', '20:00' ) ),
							'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
						),
					),
					array(
						'id'       => 'umm_ramool',
						'name'     => __( 'Umm Ramool Fitting Center', dts_get_delivery_theme() ),
						'hours'    => array(
							'Saturday to Thursday: 09:00 - 20:00',
							'Sunday: Closed',
							'Friday: 09:00 - 12:00 & 14:30 - 20:00',
						),
						'schedule' => array(
							'Saturday'  => array( array( '09:00', '20:00' ) ),
							'Sunday'    => array(),
							'Monday'    => array( array( '09:00', '20:00' ) ),
							'Tuesday'   => array( array( '09:00', '20:00' ) ),
							'Wednesday' => array( array( '09:00', '20:00' ) ),
							'Thursday'  => array( array( '09:00', '20:00' ) ),
							'Friday'    => array( array( '09:00', '12:00' ), array( '14:30', '20:00' ) ),
						),
					),
				),
			),
		);

		/**
		 * Filter the fitting-centre catalogue.
		 *
		 * @param array $centers Centres grouped by city.
		 */
		return apply_filters( 'dts_fitting_centers', $centers );
	}
}

if ( ! function_exists( 'dts_get_delivery_options' ) ) {
	/**
	 * Delivery methods available for a given cart composition.
	 *
	 * @param bool $battery_only          Cart contains batteries only.
	 * @param bool $wheel_protector_only  Cart contains wheel protectors only.
	 * @return array Keyed by method id.
	 */
	function dts_get_delivery_options( $battery_only = false, $wheel_protector_only = false ) {
		$options = array();

		if ( $wheel_protector_only ) {
			$options['store_fitment'] = array(
				'label'       => __( 'Store Fitment', dts_get_delivery_theme() ),
				'description' => __( 'Ship to Installer', dts_get_delivery_theme() ),
				'price'       => 0,
			);
			$options['mobile_van']    = array(
				'label'       => __( 'Mobile Van Shipment', dts_get_delivery_theme() ),
				'description' => __( 'Ship to an Address', dts_get_delivery_theme() ),
				'price'       => 150,
			);

			return apply_filters( 'dts_delivery_options', $options, $battery_only, $wheel_protector_only );
		}

		$options['standard'] = array(
			'label'       => $battery_only
				? __( 'Ship to an Address', dts_get_delivery_theme() )
				: __( 'Standard doorstep delivery', dts_get_delivery_theme() ),
			'description' => $battery_only
				? __( 'Have your battery shipped to your doorstep.', dts_get_delivery_theme() )
				: __( 'T\'C Apply - Standard doorstep delivery.', dts_get_delivery_theme() ),
			'price'       => $battery_only ? 70 : 0,
		);

		$options['fitting_center'] = array(
			'label'       => __( 'Select Fitting Center', dts_get_delivery_theme() ),
			'description' => __( 'Visit one of our approved fitting centers.', dts_get_delivery_theme() ),
			'price'       => 0,
		);

		if ( ! $battery_only ) {
			$options['mobile_250'] = array(
				'label'       => __( 'Mobile Service', dts_get_delivery_theme() ),
				'city'        => __( 'Abu Dhabi', dts_get_delivery_theme() ),
				'description' => __( 'AED 325.00 - Van comes to your location.', dts_get_delivery_theme() ),
				'price'       => 325,
			);
			$options['mobile_200'] = array(
				'label'       => __( 'Mobile Service', dts_get_delivery_theme() ),
				'city'        => __( 'Dubai', dts_get_delivery_theme() ),
				'description' => __( 'AED 225.00 - Van comes to your location.', dts_get_delivery_theme() ),
				'price'       => 225,
			);
		}

		return apply_filters( 'dts_delivery_options', $options, $battery_only, $wheel_protector_only );
	}
}

if ( ! function_exists( 'dts_get_mobile_service_prices' ) ) {
	/** Mobile service prices, keyed by method. */
	function dts_get_mobile_service_prices() {
		return array(
			'mobile_250' => 325,
			'mobile_200' => 225,
		);
	}
}

if ( ! function_exists( 'dts_get_mobile_service_labels' ) ) {
	/** Mobile service labels, keyed by method. */
	function dts_get_mobile_service_labels() {
		return array(
			'mobile_250' => __( 'Mobile Service Abu Dhabi', dts_get_delivery_theme() ),
			'mobile_200' => __( 'Mobile Service Dubai', dts_get_delivery_theme() ),
		);
	}
}

if ( ! function_exists( 'dts_get_delivery_session_key' ) ) {
	/**
	 * Session key for the selected method.
	 *
	 * @param bool $wheel_protector_only Wheel-protector carts use their own key.
	 */
	function dts_get_delivery_session_key( $wheel_protector_only = false ) {
		return $wheel_protector_only ? 'dts_wp_delivery_method' : 'dts_delivery_method';
	}
}

if ( ! function_exists( 'dts_get_session_delivery_method' ) ) {
	/**
	 * Selected delivery method for the current session.
	 *
	 * @param bool $wheel_protector_only Wheel-protector carts default to store fitment.
	 */
	function dts_get_session_delivery_method( $wheel_protector_only = false ) {
		$default = $wheel_protector_only ? 'store_fitment' : 'standard';

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return $default;
		}

		$method = WC()->session->get( dts_get_delivery_session_key( $wheel_protector_only ), $default );

		return is_string( $method ) && '' !== $method ? $method : $default;
	}
}

if ( ! function_exists( 'dts_get_fitting_selection' ) ) {
	/** Fitting centre, date and time held in the session. */
	function dts_get_fitting_selection() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array(
				'center' => '',
				'date'   => '',
				'time'   => '',
			);
		}

		return array(
			'center' => (string) WC()->session->get( 'dts_fitting_center', '' ),
			'date'   => (string) WC()->session->get( 'dts_fitting_date', '' ),
			'time'   => (string) WC()->session->get( 'dts_fitting_time', '' ),
		);
	}
}

if ( ! function_exists( 'dts_get_delivery_payload' ) ) {
	/**
	 * Delivery catalogue plus the current selection, as JSON-ready data.
	 *
	 * @return array
	 */
	function dts_get_delivery_payload() {
		$battery_only         = dts_is_battery_only_cart();
		$wheel_protector_only = dts_is_wheel_protector_only_cart();

		return array(
			'options'           => dts_get_delivery_options( $battery_only, $wheel_protector_only ),
			'fitting_centers'   => dts_get_fitting_centers(),
			'selected'          => dts_get_session_delivery_method( $wheel_protector_only ),
			'mobile_prices'     => dts_get_mobile_service_prices(),
			'battery_only'      => $battery_only,
			'wheel_protector'   => $wheel_protector_only,
			'single_tyre'       => dts_is_single_tyre_cart(),
			'shipping_base_fee' => 30,
			'battery_fee'       => 70,
			'fitment_selection' => dts_get_fitting_selection(),
			'nonce'             => dts_get_delivery_nonce(),
		);
	}
}

if ( ! function_exists( 'dts_is_battery_only_cart' ) ) {
	/** Whether every product in the cart is a battery. */
	function dts_is_battery_only_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		$has_item = false;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) ) {
				continue;
			}

			$product_id = ! empty( $item['variation_id'] ) ? $item['product_id'] : $item['data']->get_id();
			$type       = function_exists( 'dts_get_product_type' ) ? dts_get_product_type( $product_id ) : 'tyre';

			if ( 'battery' !== $type ) {
				return false;
			}

			$has_item = true;
		}

		return $has_item;
	}
}

if ( ! function_exists( 'dts_is_wheel_protector_only_cart' ) ) {
	/** Whether every product in the cart is a wheel protector. */
	function dts_is_wheel_protector_only_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		$has_item = false;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) ) {
				continue;
			}

			$product_id = ! empty( $item['variation_id'] ) ? $item['product_id'] : $item['data']->get_id();
			$type       = function_exists( 'dts_get_product_type' ) ? dts_get_product_type( $product_id ) : 'tyre';

			if ( 'wheel-protector' !== $type && 'wheel_protector' !== $type ) {
				return false;
			}

			$has_item = true;
		}

		return $has_item;
	}
}

if ( ! function_exists( 'dts_is_single_tyre_cart' ) ) {
	/** Whether the cart contains exactly one tyre. */
	function dts_is_single_tyre_cart() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart || WC()->cart->is_empty() ) {
			return false;
		}

		$count = 0;

		foreach ( WC()->cart->get_cart() as $item ) {
			if ( empty( $item['data'] ) ) {
				continue;
			}

			$product_id = ! empty( $item['variation_id'] ) ? $item['product_id'] : $item['data']->get_id();
			$type       = function_exists( 'dts_get_product_type' ) ? dts_get_product_type( $product_id ) : 'tyre';

			if ( 'tyre' === $type ) {
				$count += (int) $item['quantity'];
			}
		}

		return 1 === $count;
	}
}

if ( ! function_exists( 'dts_store_delivery_method' ) ) {
	/**
	 * Persist the delivery selection from the checkout review request.
	 *
	 * @param string $post_data Serialised checkout form data.
	 */
	function dts_store_delivery_method( $post_data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		parse_str( (string) $post_data, $data );

		$method = isset( $data['dts_delivery_method'] ) ? sanitize_text_field( wp_unslash( $data['dts_delivery_method'] ) ) : 'standard';
		WC()->session->set( 'dts_delivery_method', $method );

		if ( in_array( $method, array( 'fitting_center', 'store_fitment' ), true ) ) {
			WC()->session->set( 'dts_fitting_center', isset( $data['dts_fitting_center'] ) ? sanitize_text_field( wp_unslash( $data['dts_fitting_center'] ) ) : '' );
			WC()->session->set( 'dts_fitting_date', isset( $data['dts_fitting_date'] ) ? sanitize_text_field( wp_unslash( $data['dts_fitting_date'] ) ) : '' );
			WC()->session->set( 'dts_fitting_time', isset( $data['dts_fitting_time'] ) ? sanitize_text_field( wp_unslash( $data['dts_fitting_time'] ) ) : '' );
			return;
		}

		WC()->session->set( 'dts_fitting_center', '' );
		WC()->session->set( 'dts_fitting_date', '' );
		WC()->session->set( 'dts_fitting_time', '' );
	}
}
add_action( 'woocommerce_checkout_update_order_review', 'dts_store_delivery_method' );

if ( ! function_exists( 'dts_get_delivery_nonce' ) ) {
	/** Nonce for the delivery selection endpoint. */
	function dts_get_delivery_nonce() {
		return wp_create_nonce( 'dts_delivery' );
	}
}

if ( ! function_exists( 'dts_set_delivery_method_ajax' ) ) {
	/**
	 * Save a delivery selection without a checkout round-trip.
	 *
	 * The checkout review hook covers the normal flow; this endpoint lets the
	 * theme update the fee as soon as a card is picked.
	 */
	function dts_set_delivery_method_ajax() {
		check_ajax_referer( 'dts_delivery', 'nonce' );

		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}

		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			wp_send_json_error( array( 'message' => __( 'Session unavailable', dts_get_delivery_theme() ) ), 500 );
		}

		$method  = isset( $_POST['delivery_method'] ) ? sanitize_text_field( wp_unslash( $_POST['delivery_method'] ) ) : 'standard';
		$options = dts_get_delivery_options( dts_is_battery_only_cart(), dts_is_wheel_protector_only_cart() );

		if ( ! isset( $options[ $method ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown delivery method', dts_get_delivery_theme() ) ), 400 );
		}

		$wheel_protector_only = dts_is_wheel_protector_only_cart();
		WC()->session->set( dts_get_delivery_session_key( $wheel_protector_only ), $method );

		if ( in_array( $method, array( 'fitting_center', 'store_fitment' ), true ) ) {
			WC()->session->set( 'dts_fitting_center', isset( $_POST['fitting_center'] ) ? sanitize_text_field( wp_unslash( $_POST['fitting_center'] ) ) : '' );
			WC()->session->set( 'dts_fitting_date', isset( $_POST['fitting_date'] ) ? sanitize_text_field( wp_unslash( $_POST['fitting_date'] ) ) : '' );
			WC()->session->set( 'dts_fitting_time', isset( $_POST['fitting_time'] ) ? sanitize_text_field( wp_unslash( $_POST['fitting_time'] ) ) : '' );
		} else {
			WC()->session->set( 'dts_fitting_center', '' );
			WC()->session->set( 'dts_fitting_date', '' );
			WC()->session->set( 'dts_fitting_time', '' );
		}

		if ( function_exists( 'WC' ) && WC()->cart ) {
			WC()->cart->calculate_totals();
		}

		$payload                = dts_get_delivery_payload();
		$payload['selected']    = $method;
		$payload['cart_totals'] = function_exists( 'dts_get_cart_totals_payload' ) && WC()->cart
			? dts_get_cart_totals_payload( WC()->cart )
			: array();

		wp_send_json_success( $payload );
	}
}
add_action( 'wp_ajax_dts_set_delivery_method', 'dts_set_delivery_method_ajax' );
add_action( 'wp_ajax_nopriv_dts_set_delivery_method', 'dts_set_delivery_method_ajax' );

if ( ! function_exists( 'dts_get_delivery_payload_ajax' ) ) {
	/** Return the delivery catalogue and selection. */
	function dts_get_delivery_payload_ajax() {
		check_ajax_referer( 'dts_delivery', 'nonce' );

		if ( function_exists( 'wc_load_cart' ) && ( ! function_exists( 'WC' ) || ! WC()->cart ) ) {
			wc_load_cart();
		}

		wp_send_json_success( dts_get_delivery_payload() );
	}
}
add_action( 'wp_ajax_dts_get_delivery', 'dts_get_delivery_payload_ajax' );
add_action( 'wp_ajax_nopriv_dts_get_delivery', 'dts_get_delivery_payload_ajax' );

if ( ! function_exists( 'dts_remove_shipping_rates_for_single_tyre' ) ) {
	/**
	 * A single tyre carries a flat AED 30 fee instead of live shipping rates.
	 *
	 * @param array $rates   Available rates.
	 * @param array $package Shipping package.
	 * @return array
	 */
	function dts_remove_shipping_rates_for_single_tyre( $rates, $package ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $rates;
		}

		if ( dts_is_single_tyre_cart() ) {
			return array();
		}

		return $rates;
	}
}
add_filter( 'woocommerce_package_rates', 'dts_remove_shipping_rates_for_single_tyre', 10, 2 );

if ( ! function_exists( 'dts_add_delivery_fee' ) ) {
	/**
	 * Add the shipping and mobile-service fees.
	 *
	 * Runs after the VAT fee so the fee list keeps a predictable order.
	 */
	function dts_add_delivery_fee() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$battery_only  = dts_is_battery_only_cart();
		$single_tyre   = dts_is_single_tyre_cart();
		$method        = dts_get_session_delivery_method();
		$mobile_prices = dts_get_mobile_service_prices();
		$mobile_labels = dts_get_mobile_service_labels();

		// Batteries ship flat-rate, and only on checkout.
		if ( $battery_only && 'standard' === $method && ! is_cart() ) {
			WC()->cart->add_fee( __( 'Shipping', dts_get_delivery_theme() ), 70.0, false );
			return;
		}

		if ( $single_tyre && ( 'standard' === $method || isset( $mobile_prices[ $method ] ) ) ) {
			WC()->cart->add_fee( __( 'Shipping', dts_get_delivery_theme() ), 30.0, false );
		}

		// The mobile surcharge shows everywhere except the cart page, where the
		// visitor is still choosing.
		if ( isset( $mobile_prices[ $method ] ) && ! is_cart() ) {
			WC()->cart->add_fee( $mobile_labels[ $method ], $mobile_prices[ $method ], false );
		}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'dts_add_delivery_fee', 15 );

if ( ! function_exists( 'dts_apply_buy3get1_discount' ) ) {
	/**
	 * Apply the 3+1 offer: every fourth eligible tyre is free.
	 */
	function dts_apply_buy3get1_discount() {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}

		$discount = 0.0;

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = ! empty( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
			$promo      = get_post_meta( $product_id, 'buy_3_get_1', true );

			if ( '' === $promo || 'yes' !== strtolower( trim( (string) $promo ) ) ) {
				continue;
			}

			$quantity = (int) $cart_item['quantity'];

			if ( $quantity < 4 || empty( $cart_item['data'] ) ) {
				continue;
			}

			$free_quantity = (int) floor( $quantity / 4 );
			$unit_price    = (float) $cart_item['data']->get_price();

			$discount += $free_quantity * $unit_price;
		}

		if ( $discount > 0 ) {
			WC()->cart->add_fee( __( 'Buy 3 Get 1 Free Discount', dts_get_delivery_theme() ), -$discount, false );
		}
	}
}
add_action( 'woocommerce_cart_calculate_fees', 'dts_apply_buy3get1_discount', 25 );

if ( ! function_exists( 'dts_save_delivery_order_meta' ) ) {
	/**
	 * Copy the delivery selection onto the order.
	 *
	 * @param WC_Order $order Order being created.
	 * @param array    $data  Posted checkout data.
	 */
	function dts_save_delivery_order_meta( $order, $data ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		$method = WC()->session->get( 'dts_delivery_method', '' );

		if ( $method ) {
			$order->update_meta_data( '_dts_delivery_method', $method );
		}

		if ( in_array( $method, array( 'fitting_center', 'store_fitment' ), true ) ) {
			$selection = dts_get_fitting_selection();

			if ( $selection['center'] ) {
				$order->update_meta_data( '_dts_fitting_center', $selection['center'] );
			}
			if ( $selection['date'] ) {
				$order->update_meta_data( '_dts_fitting_date', $selection['date'] );
			}
			if ( $selection['time'] ) {
				$order->update_meta_data( '_dts_fitting_time', $selection['time'] );
			}
		}
	}
}
add_action( 'woocommerce_checkout_create_order', 'dts_save_delivery_order_meta', 10, 2 );

if ( ! function_exists( 'dts_maybe_init_delivery_session' ) ) {
	/** Ensure a session exists so the delivery selection survives AJAX. */
	function dts_maybe_init_delivery_session() {
		if ( function_exists( 'WC' ) && WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
	}
}
add_action( 'wp', 'dts_maybe_init_delivery_session' );

if ( ! function_exists( 'dts_get_delivery_method_label' ) ) {
	/**
	 * Human label for a stored delivery method key.
	 *
	 * @param string $method Stored method key.
	 * @return string
	 */
	function dts_get_delivery_method_label( $method ) {
		$labels = array(
			'standard'       => __( 'Standard Doorstep Delivery', dts_get_delivery_theme() ),
			'fitting_center' => __( 'Fitting Center Visit', dts_get_delivery_theme() ),
			'store_fitment'  => __( 'Store Fitment (Ship to Installer)', dts_get_delivery_theme() ),
			'mobile_250'     => __( 'Mobile Service (Abu Dhabi) - AED 325', dts_get_delivery_theme() ),
			'mobile_200'     => __( 'Mobile Service (Dubai) - AED 225', dts_get_delivery_theme() ),
			'mobile_van'     => __( 'Mobile Van Shipment (Ship to an Address)', dts_get_delivery_theme() ),
		);

		if ( isset( $labels[ $method ] ) ) {
			return $labels[ $method ];
		}

		return '' === (string) $method ? '' : ucfirst( str_replace( '_', ' ', (string) $method ) );
	}
}

if ( ! function_exists( 'dts_get_fitting_center_name' ) ) {
	/**
	 * Resolve a fitting-centre id to its display name.
	 *
	 * @param string $center_id Centre id, or empty.
	 * @return string
	 */
	function dts_get_fitting_center_name( $center_id ) {
		if ( '' === (string) $center_id ) {
			return '';
		}

		foreach ( dts_get_fitting_centers() as $city ) {
			foreach ( $city['centers'] as $center ) {
				if ( $center['id'] === $center_id ) {
					return $center['name'];
				}
			}
		}

		return ucwords( str_replace( '_', ' ', (string) $center_id ) );
	}
}
