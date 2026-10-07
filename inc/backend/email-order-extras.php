<?php
/**
 * Extra detail injected into the standard WooCommerce emails.
 *
 * The store sends its own branded order notification (see email.php), but
 * WooCommerce still sends status-change and account emails, and those would
 * otherwise omit everything the customer supplied at checkout: the vehicle, the
 * delivery method, and the fitting centre with its opening hours.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_fitting_center_lookup' ) ) {
	/**
	 * Every fitting centre flattened to id => name / city / hours.
	 *
	 * dts_get_fitting_centers() groups by city because that is how the delivery
	 * picker presents them; an email needs the reverse lookup.
	 *
	 * @return array
	 */
	function dts_get_fitting_center_lookup() {
		$lookup = array();

		foreach ( dts_get_fitting_centers() as $city_key => $city ) {
			$city_label = isset( $city['label'] ) ? $city['label'] : $city_key;

			foreach ( $city['centers'] as $center ) {
				$lookup[ $center['id'] ] = array(
					'name'  => $center['name'],
					'city'  => $city_label,
					'hours' => isset( $center['hours'] ) ? $center['hours'] : array(),
				);
			}
		}

		return $lookup;
	}
}

if ( ! function_exists( 'dts_get_email_vehicle_example' ) ) {
	/**
	 * Placeholder shown for a vehicle detail the customer left blank.
	 *
	 * The fields are optional, so a reminder of the expected format is more
	 * useful in the email than an empty row.
	 *
	 * @param string $label Field label.
	 */
	function dts_get_email_vehicle_example( $label ) {
		$examples = array(
			__( 'Plate number', 'custom-dubai-tyre-shop' ) => 'e.g. ABC 12345',
			__( 'Car make', 'custom-dubai-tyre-shop' )     => 'e.g. Toyota',
			__( 'Model number', 'custom-dubai-tyre-shop' ) => 'e.g. Camry 2.5L',
			__( 'Year', 'custom-dubai-tyre-shop' )        => 'e.g. 2020',
		);

		return isset( $examples[ $label ] ) ? $examples[ $label ] : '';
	}
}

if ( ! function_exists( 'dts_get_email_product_size' ) ) {
	/**
	 * Formatted tyre size for an order line.
	 *
	 * A variation inherits its size from the parent when it has no size meta of
	 * its own, which is how a single-size product is modelled.
	 *
	 * @param WC_Product|null $product Line product.
	 * @return string Empty string when the product has no size.
	 */
	function dts_get_email_product_size( $product ) {
		if ( ! $product || ! is_callable( array( $product, 'get_id' ) ) ) {
			return '';
		}

		$ids = array( $product->get_id() );

		if ( is_callable( array( $product, 'is_type' ) ) && $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			$ids[] = (int) $product->get_parent_id();
		}

		foreach ( array_unique( $ids ) as $product_id ) {
			$size = dts_get_product_size_label( $product_id );

			if ( $size ) {
				return $size;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'dts_filter_email_order_item_name' ) ) {
	/**
	 * Link the product name and show its size on every order line.
	 *
	 * did_action() guards the filter because it also runs while a product name is
	 * rendered on the checkout and order-edit screens, where a mailto-styled
	 * link would be wrong.
	 *
	 * @param string     $item_name    Product name.
	 * @param WC_Order_Item $item     Order item.
	 * @param bool       $is_plain_text Whether the email is plain text.
	 */
	function dts_filter_email_order_item_name( $item_name, $item, $is_plain_text ) {
		if ( ! did_action( 'woocommerce_email_header' ) ) {
			return $item_name;
		}

		$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
		$size    = dts_get_email_product_size( $product );

		if ( $is_plain_text ) {
			return $size ? $item_name . ' [' . $size . ']' : $item_name;
		}

		if ( $product && is_callable( array( $product, 'is_visible' ) ) && $product->is_visible() && $product->get_permalink() ) {
			$item_name = '<a href="' . esc_url( $product->get_permalink() ) . '" style="color:' . esc_attr( dts_get_email_accent() ) . ';text-decoration:underline;">' . wp_kses_post( $item_name ) . '</a>';
		}

		if ( $size ) {
			$item_name .= ' &nbsp;<span style="font-size:90%;color:#8a8a8a;font-weight:600;white-space:nowrap;">' . esc_html( $size ) . '</span>';
		}

		return $item_name;
	}
}
add_filter( 'woocommerce_order_item_name', 'dts_filter_email_order_item_name', 10, 3 );

if ( ! function_exists( 'dts_get_email_extra_detail_blocks' ) ) {
	/**
	 * The vehicle and delivery detail blocks for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	function dts_get_email_extra_detail_blocks( $order ) {
		$vehicle = array(
			__( 'Plate number', 'custom-dubai-tyre-shop' ) => (string) $order->get_meta( 'Plate Number' ),
			__( 'Car make', 'custom-dubai-tyre-shop' )     => (string) $order->get_meta( 'Car Make' ),
			__( 'Model number', 'custom-dubai-tyre-shop' ) => (string) $order->get_meta( 'Model Number' ),
			__( 'Year', 'custom-dubai-tyre-shop' )        => (string) $order->get_meta( 'Car Year' ),
		);

		$method    = (string) $order->get_meta( '_dts_delivery_method' );
		$center_id = (string) $order->get_meta( '_dts_fitting_center' );
		$centers   = dts_get_fitting_center_lookup();

		$center = array();

		if ( '' !== $center_id ) {
			$center = isset( $centers[ $center_id ] )
				? $centers[ $center_id ]
				: array( 'name' => ucwords( str_replace( '_', ' ', $center_id ) ), 'city' => '', 'hours' => array() );
		}

		$delivery = array();

		if ( '' !== $method ) {
			$delivery[ __( 'Method', 'custom-dubai-tyre-shop' ) ] = dts_get_delivery_method_label( $method );

			// A centre only matters when the customer is actually visiting one.
			if ( in_array( $method, array( 'fitting_center', 'store_fitment' ), true ) && $center ) {
				$delivery[ __( 'Fitting centre', 'custom-dubai-tyre-shop' ) ] = $center['name'];

				if ( ! empty( $center['city'] ) ) {
					$delivery[ __( 'Location', 'custom-dubai-tyre-shop' ) ] = $center['city'];
				}

				if ( ! empty( $center['hours'] ) ) {
					$delivery[ __( 'Opening hours', 'custom-dubai-tyre-shop' ) ] = $center['hours'];
				}
			}

			$date = (string) $order->get_meta( '_dts_fitting_date' );
			$time = (string) $order->get_meta( '_dts_fitting_time' );

			if ( '' !== $date ) {
				$delivery[ __( 'Preferred date', 'custom-dubai-tyre-shop' ) ] = $date;
			}

			if ( '' !== $time ) {
				$delivery[ __( 'Preferred time', 'custom-dubai-tyre-shop' ) ] = $time;
			}
		}

		return array(
			'vehicle'  => $vehicle,
			'delivery' => $delivery,
		);
	}
}

if ( ! function_exists( 'dts_render_email_extra_order_details' ) ) {
	/**
	 * Print the vehicle and delivery blocks after the WooCommerce order table.
	 *
	 * @param WC_Order $order          Order.
	 * @param bool     $sent_to_admin  Unused, kept for the hook signature.
	 * @param bool     $plain_text     Whether the email is plain text.
	 * @param WC_Email $email          Unused, kept for the hook signature.
	 */
	function dts_render_email_extra_order_details( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
		if ( ! $order ) {
			return;
		}

		$blocks = dts_get_email_extra_detail_blocks( $order );

		if ( $plain_text ) {
			dts_render_email_extra_plain( $blocks );
			return;
		}

		dts_render_email_extra_html( $blocks );
	}
}
add_action( 'woocommerce_email_after_order_table', 'dts_render_email_extra_order_details', 10, 4 );

if ( ! function_exists( 'dts_render_email_extra_plain' ) ) {
	/**
	 * Plain-text rendering of the extra detail blocks.
	 *
	 * @param array $blocks Output of dts_get_email_extra_detail_blocks().
	 */
	function dts_render_email_extra_plain( $blocks ) {
		$lines = array();

		$vehicle_rows = array();

		foreach ( $blocks['vehicle'] as $label => $value ) {
			$value         = trim( $value );
			$display       = '' !== $value ? $value : dts_get_email_vehicle_example( $label );
			$vehicle_rows[] = $label . ' (optional): ' . $display;
		}

		if ( $vehicle_rows ) {
			$lines[] = '';
			$lines[] = 'VEHICLE DETAILS';
			$lines[] = '--------------------';
			$lines[] = implode( "\n", $vehicle_rows );
		}

		if ( $blocks['delivery'] ) {
			$lines[] = '';
			$lines[] = 'DELIVERY / FITTING CENTRE';
			$lines[] = '--------------------';

			foreach ( $blocks['delivery'] as $label => $value ) {
				$value   = is_array( $value ) ? implode( ' · ', $value ) : (string) $value;
				$lines[] = $label . ': ' . $value;
			}
		}

		if ( ! $lines ) {
			return;
		}

		echo "\n" . implode( "\n", $lines ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body.
	}
}

if ( ! function_exists( 'dts_render_email_extra_html' ) ) {
	/**
	 * HTML rendering of the extra detail blocks.
	 *
	 * Inline styles only, matched to the WooCommerce email shell: a mail client
	 * strips a stylesheet.
	 *
	 * @param array $blocks Output of dts_get_email_extra_detail_blocks().
	 */
	function dts_render_email_extra_html( $blocks ) {
		$font      = "font-family:'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif;";
		$style_row = $font . 'color:#636363;border:1px solid #e5e5e5;vertical-align:middle;padding:12px;';
		$style_lbl = $font . 'color:#757575;font-weight:bold;padding:12px;';
		$style_h2  = 'color:#1a1a1a;display:block;' . $font . 'font-size:16px;font-weight:bold;line-height:130%;margin:16px 0 6px;text-align:left;';
		$style_tbl = 'width:100%;' . $font . 'color:#636363;border:1px solid #e5e5e5;margin-bottom:24px;';

		$vehicle = $blocks['vehicle'];
		$has_vehicle = (bool) array_filter( $vehicle, 'strlen' );

		if ( $has_vehicle ) :
			?>
			<h2 style="<?php echo esc_attr( $style_h2 ); ?>"><?php esc_html_e( 'Vehicle Details', 'custom-dubai-tyre-shop' ); ?></h2>
			<table class="td" cellspacing="0" cellpadding="6" style="<?php echo esc_attr( $style_tbl ); ?>">
				<?php foreach ( $vehicle as $label => $value ) :
					$value    = trim( (string) $value );
					$is_hint  = '' === $value;
					$display  = $is_hint ? dts_get_email_vehicle_example( $label ) : $value;
					?>
				<tr>
					<td style="<?php echo esc_attr( $style_lbl ); ?>"><?php echo esc_html( $label . ' (optional)' ); ?></td>
					<td style="<?php echo esc_attr( $style_row ); ?>">
						<?php if ( $is_hint ) : ?>
						<em style="color:#9e9e9e;"><?php echo esc_html( $display ); ?></em>
						<?php else : ?>
						<?php echo esc_html( $display ); ?>
						<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</table>
			<?php
		endif;

		if ( ! $blocks['delivery'] ) {
			return;
		}
		?>
		<h2 style="<?php echo esc_attr( $style_h2 ); ?>"><?php esc_html_e( 'Delivery & Fitting Centre', 'custom-dubai-tyre-shop' ); ?></h2>
		<table class="td" cellspacing="0" cellpadding="6" style="<?php echo esc_attr( $style_tbl ); ?>">
			<?php foreach ( $blocks['delivery'] as $label => $value ) : ?>
			<tr>
				<td style="<?php echo esc_attr( $style_lbl ); ?>"><?php echo esc_html( $label ); ?></td>
				<td style="<?php echo esc_attr( $style_row ); ?>">
					<?php echo esc_html( is_array( $value ) ? implode( ' · ', $value ) : (string) $value ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}
}

if ( ! function_exists( 'dts_filter_email_header_image' ) ) {
	/**
	 * Use the configured store logo in the WooCommerce email header.
	 *
	 * @param string   $image Current header image markup.
	 * @param WC_Email $email Email being sent.
	 * @return string
	 */
	function dts_filter_email_header_image( $image, $email = null ) {
		$logo = dts_get_store_detail( 'logo_url' );

		return $logo ? $logo : $image;
	}
}
add_filter( 'woocommerce_email_header_image', 'dts_filter_email_header_image', 10, 2 );

if ( ! function_exists( 'dts_filter_email_styles' ) ) {
	/**
	 * Darken the email header so a light logo stays legible.
	 *
	 * @param string $css Inline CSS from WooCommerce.
	 * @return string
	 */
	function dts_filter_email_styles( $css ) {
		$css .= '
		#template_header_image { background-color: #1a1a1a !important; }
		#template_header_image p { margin: 0 !important; padding: 20px !important; }
		';

		return $css;
	}
}
add_filter( 'woocommerce_email_styles', 'dts_filter_email_styles', 20 );

if ( ! function_exists( 'dts_ensure_default_order_emails' ) ) {
	/**
	 * Send WooCommerce's own order emails for an order that is still pending.
	 *
	 * WooCommerce only triggers those emails on a status transition, so an order
	 * left on "pending" by a pay-later or bank-transfer gateway would otherwise
	 * generate no email at all. An order that already moved to another status at
	 * checkout has had its emails sent, so it is skipped to avoid duplicates.
	 *
	 * @param int       $order_id    Order ID.
	 * @param array     $posted_data Posted checkout data, unused.
	 * @param WC_Order  $order       Order, or null to look it up.
	 */
	function dts_ensure_default_order_emails( $order_id, $posted_data = null, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );

		if ( ! $order || 'pending' !== $order->get_status() ) {
			return;
		}

		if ( ! function_exists( 'WC' ) || ! WC()->mailer() ) {
			return;
		}

		$emails = WC()->mailer()->get_emails();

		foreach ( array( 'WC_Email_New_Order', 'WC_Email_Customer_Processing_Order' ) as $class ) {
			if ( isset( $emails[ $class ] ) ) {
				$emails[ $class ]->trigger( $order_id );
			}
		}
	}
}
add_action( 'woocommerce_checkout_order_processed', 'dts_ensure_default_order_emails', 20, 3 );
