<?php
/**
 * Transactional email: the contact form and order notifications.
 *
 * The template is inline HTML rather than a WooCommerce email because both the
 * contact form and the order notification need the same branded shell, and
 * order notifications carry information (delivery slot, vehicle details) that
 * the standard order emails have no field for.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'dts_get_email_accent' ) ) {
	/** Accent colour used for links and rules in transactional email. */
	function dts_get_email_accent() {
		$accent = (string) dts_get_store_detail( 'accent' );

		return sanitize_hex_color( $accent ) ? $accent : '#d4a11e';
	}
}

if ( ! function_exists( 'dts_get_email_footer_html' ) ) {
	/** Contact block shown at the foot of every message. */
	function dts_get_email_footer_html() {
		$details = dts_get_store_details();
		$parts   = array();

		$parts[] = '<p style="margin:0 0 4px;">' . esc_html( $details['name'] );

		if ( ! empty( $details['address'] ) ) {
			$parts[0] .= ' &bull; ' . esc_html( $details['address'] );
		}

		$parts[0] .= '</p>';

		$contact = array();

		if ( ! empty( $details['phone'] ) ) {
			$href     = dts_get_store_phone_href();
			$contact[] = $href
				? '<a href="' . esc_attr( $href ) . '" style="color:' . esc_attr( dts_get_email_accent() ) . ';text-decoration:none;">' . esc_html( $details['phone'] ) . '</a>'
				: esc_html( $details['phone'] );
		}

		$email = dts_get_store_email();

		if ( $email ) {
			$contact[] = '<a href="mailto:' . esc_attr( $email ) . '" style="color:' . esc_attr( dts_get_email_accent() ) . ';text-decoration:none;">' . esc_html( $email ) . '</a>';
		}

		if ( ! empty( $contact ) ) {
			$parts[] = '<p style="margin:0;">' . implode( ' &bull; ', $contact ) . '</p>';
		}

		return implode( '', $parts );
	}
}

if ( ! function_exists( 'dts_get_email_html' ) ) {
	/**
	 * Wrap message content in the branded shell.
	 *
	 * @param string $subject        Subject, also used as the visible heading.
	 * @param string $body_html      Trusted HTML body.
	 * @param string $recipient_name Name to greet, or empty to skip the greeting.
	 */
	function dts_get_email_html( $subject, $body_html, $recipient_name = '' ) {
		$details   = dts_get_store_details();
		$accent    = dts_get_email_accent();
		$logo_html = '';

		if ( ! empty( $details['logo_url'] ) ) {
			// A dark header, so the logo is drawn in white on the assumption the
			// artwork is a single-colour mark.
			$logo_html = '<img src="' . esc_url( $details['logo_url'] ) . '" alt="' . esc_attr( $details['name'] ) . '" style="max-height:48px;width:auto;display:block;margin:0 auto;">';
		}

		$header = $logo_html
			? '<tr><td style="background:#1a1a1a;text-align:center;padding:30px 20px;">' . $logo_html . '</td></tr>'
			: '<tr><td style="background:#1a1a1a;text-align:center;padding:30px 20px;">'
				. '<span style="color:#ffffff;font-size:20px;font-weight:700;letter-spacing:.5px;">' . esc_html( $details['name'] ) . '</span>'
				. '</td></tr>';

		$greeting = $recipient_name
			? '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#555;">' . esc_html__( 'Hi', 'custom-dubai-tyre-shop' ) . ' ' . esc_html( $recipient_name ) . ',</p>'
			: '';

		return '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Helvetica,Arial,sans-serif;">
	<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 10px;">
		<tr>
			<td align="center">
				<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;">
					' . $header . '
					<tr>
						<td style="padding:32px 32px 16px;">
							<h1 style="margin:0 0 20px;font-size:22px;color:#1a1a1a;">' . esc_html( $subject ) . '</h1>
							' . $greeting . '
							' . $body_html . '
						</td>
					</tr>
					<tr>
						<td style="padding:16px 32px 32px;text-align:center;border-top:1px solid #eee;color:#999;font-size:13px;line-height:1.6;">
							' . dts_get_email_footer_html() . '
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>';
	}
}

if ( ! function_exists( 'dts_send_email' ) ) {
	/**
	 * Send an HTML message through the shared template.
	 *
	 * @param string $to             Recipient address.
	 * @param string $subject        Subject line.
	 * @param string $body_html      Trusted HTML body.
	 * @param string $recipient_name Name to greet.
	 * @return bool
	 */
	function dts_send_email( $to, $subject, $body_html, $recipient_name = '' ) {
		if ( ! is_email( $to ) ) {
			return false;
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		return wp_mail( $to, $subject, dts_get_email_html( $subject, $body_html, $recipient_name ), $headers );
	}
}

if ( ! function_exists( 'dts_add_notification_recipient' ) ) {
	/**
	 * Copy the store on every outgoing message.
	 *
	 * A misconfigured notification would otherwise be invisible, so the store
	 * address is always added rather than used as the primary recipient.
	 *
	 * @param array $atts wp_mail() arguments.
	 * @return array
	 */
	function dts_add_notification_recipient( $atts ) {
		$additional = dts_get_store_email();

		if ( ! $additional ) {
			return $atts;
		}

		$to = isset( $atts['to'] ) ? $atts['to'] : '';

		if ( is_array( $to ) ) {
			$atts['to'] = array_values( array_unique( array_merge( $to, array( $additional ) ) ) );
		} else {
			$to = trim( (string) $to );

			if ( '' === $to ) {
				$atts['to'] = $additional;
			} elseif ( false === strpos( $to, $additional ) ) {
				$atts['to'] = $to . ', ' . $additional;
			}
		}

		return $atts;
	}
}
add_filter( 'wp_mail', 'dts_add_notification_recipient' );

if ( ! function_exists( 'dts_get_email_detail_rows' ) ) {
	/**
	 * Render a two-column detail table.
	 *
	 * Empty values are dropped so a partially completed form does not produce a
	 * column of blank rows.
	 *
	 * @param array $rows Label => value. Values are escaped.
	 * @param array $opts 'label_width' and 'first' to control the layout.
	 */
	function dts_get_email_detail_rows( $rows, $opts = array() ) {
		$accent = dts_get_email_accent();
		$width  = isset( $opts['label_width'] ) ? (int) $opts['label_width'] : 140;
		$first  = ! empty( $opts['first'] );
		$html   = '';

		foreach ( $rows as $label => $value ) {
			if ( '' === $value || null === $value ) {
				continue;
			}

			$html .= '<tr>'
				. '<td style="padding:8px 0;font-weight:700;color:#1a1a1a;' . ( $first ? 'width:' . $width . 'px;' : '' ) . '">' . esc_html( $label ) . '</td>'
				. '<td style="padding:8px 0;color:#555;">' . $value . '</td>'
				. '</tr>';

			$first = false;
		}

		return $html;
	}
}

if ( ! function_exists( 'dts_ajax_send_contact' ) ) {
	/**
	 * Handle a contact form submission.
	 *
	 * Sends the query to the store and an acknowledgement to the sender.
	 *
	 * @return void
	 */
	function dts_ajax_send_contact() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'dts_contact' ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid security token.', 'custom-dubai-tyre-shop' ) ), 403 );
		}

		// Honeypot: a real visitor never fills a hidden field.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Your message has been sent successfully. We will get back to you shortly.', 'custom-dubai-tyre-shop' ) ) );
		}

		$name    = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$phone   = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$address = isset( $_POST['address'] ) ? sanitize_text_field( wp_unslash( $_POST['address'] ) ) : '';
		$message = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( '' === $name || '' === $email || '' === $message ) {
			wp_send_json_error( array( 'message' => __( 'Please fill in all required fields.', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'custom-dubai-tyre-shop' ) ), 400 );
		}

		$accent  = dts_get_email_accent();
		$store   = dts_get_store_email();
		$body    = nl2br( esc_html( $message ) );
		$summary = '<div style="margin-top:16px;padding:16px;background:#f9f9f9;border-left:3px solid ' . esc_attr( $accent ) . ';border-radius:4px;font-size:14px;line-height:1.6;color:#555;">' . $body . '</div>';

		if ( $store ) {
			$admin_rows = dts_get_email_detail_rows(
				array(
					__( 'Name', 'custom-dubai-tyre-shop' )    => esc_html( $name ),
					__( 'Email', 'custom-dubai-tyre-shop' )   => '<a href="mailto:' . esc_attr( $email ) . '" style="color:' . esc_attr( $accent ) . ';">' . esc_html( $email ) . '</a>',
					__( 'Phone', 'custom-dubai-tyre-shop' )   => $phone ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '" style="color:' . esc_attr( $accent ) . ';">' . esc_html( $phone ) . '</a>' : '',
					__( 'Address', 'custom-dubai-tyre-shop' ) => esc_html( $address ),
				),
				array( 'label_width' => 110, 'first' => true )
			);

			dts_send_email( $store, sprintf( /* translators: %s: sender name. */ __( 'New Contact Query — %s', 'custom-dubai-tyre-shop' ), $name ), $admin_rows . $summary );
		}

		$phone_href = dts_get_store_phone_href();

		$customer_body = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#555;">'
			. esc_html__( 'Thank you for reaching out. We have received your message and will get back to you as soon as possible.', 'custom-dubai-tyre-shop' )
			. '</p>'
			. '<div style="padding:16px;background:#f9f9f9;border-left:3px solid ' . esc_attr( $accent ) . ';border-radius:4px;font-size:14px;line-height:1.6;color:#555;">'
			. '<p style="margin:0 0 8px;font-weight:700;color:#1a1a1a;">' . esc_html__( 'Your message summary:', 'custom-dubai-tyre-shop' ) . '</p>'
			. $body
			. '</div>';

		if ( $phone_href ) {
			$customer_body .= '<p style="margin:16px 0 0;font-size:14px;line-height:1.6;color:#999;">'
				. esc_html__( 'If you need immediate assistance, please call us at', 'custom-dubai-tyre-shop' )
				. ' <a href="' . esc_attr( $phone_href ) . '" style="color:' . esc_attr( $accent ) . ';text-decoration:none;">'
				. esc_html( dts_get_store_detail( 'phone' ) ) . '</a>.</p>';
		}

		dts_send_email( $email, __( 'Thank You for Contacting Us', 'custom-dubai-tyre-shop' ), $customer_body, $name );

		wp_send_json_success( array( 'message' => __( 'Your message has been sent successfully. We will get back to you shortly.', 'custom-dubai-tyre-shop' ) ) );
	}
}
add_action( 'wp_ajax_dts_send_contact', 'dts_ajax_send_contact' );
add_action( 'wp_ajax_nopriv_dts_send_contact', 'dts_ajax_send_contact' );

if ( ! function_exists( 'dts_get_order_email_vehicle_rows' ) ) {
	/**
	 * Vehicle details recorded at checkout.
	 *
	 * @param WC_Order $order Order.
	 * @return array Label => escaped value.
	 */
	function dts_get_order_email_vehicle_rows( $order ) {
		return array(
			__( 'Make', 'custom-dubai-tyre-shop' )    => esc_html( (string) $order->get_meta( 'Car Make' ) ),
			__( 'Model', 'custom-dubai-tyre-shop' )   => esc_html( (string) $order->get_meta( 'Model Number' ) ),
			__( 'Year', 'custom-dubai-tyre-shop' )    => esc_html( (string) $order->get_meta( 'Car Year' ) ),
			__( 'Plate Number', 'custom-dubai-tyre-shop' ) => esc_html( (string) $order->get_meta( 'Plate Number' ) ),
		);
	}
}

if ( ! function_exists( 'dts_get_order_email_delivery_rows' ) ) {
	/**
	 * Delivery choice and address as recorded on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return array Label => escaped value.
	 */
	function dts_get_order_email_delivery_rows( $order ) {
		$method = (string) $order->get_meta( '_dts_delivery_method' );
		$rows   = array(
			__( 'Delivery Method', 'custom-dubai-tyre-shop' ) => esc_html( dts_get_delivery_method_label( $method ) ),
		);

		if ( 'fitting_center' === $method ) {
			$rows[ __( 'Fitting Center', 'custom-dubai-tyre-shop' ) ] = esc_html( dts_get_fitting_center_name( (string) $order->get_meta( '_dts_fitting_center' ) ) );
		}

		$rows[ __( 'Preferred Date', 'custom-dubai-tyre-shop' ) ] = esc_html( (string) $order->get_meta( '_dts_fitting_date' ) );
		$rows[ __( 'Preferred Time', 'custom-dubai-tyre-shop' ) ] = esc_html( (string) $order->get_meta( '_dts_fitting_time' ) );

		$address = dts_format_order_address(
			array(
				$order->get_shipping_first_name(),
				$order->get_shipping_last_name(),
				$order->get_shipping_address_1(),
				$order->get_shipping_address_2(),
				$order->get_shipping_city(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
			)
		);

		if ( $address ) {
			$rows[ __( 'Shipping Address', 'custom-dubai-tyre-shop' ) ] = $address;
		}

		return $rows;
	}
}

if ( ! function_exists( 'dts_format_order_address' ) ) {
	/**
	 * Render an address as comma-separated HTML with a line break after the street.
	 *
	 * @param array $parts Address components, in order.
	 */
	function dts_format_order_address( $parts ) {
		$parts = array_map( 'trim', array_map( 'strval', (array) $parts ) );
		$parts = array_values( array_filter( $parts, 'strlen' ) );

		if ( empty( $parts ) ) {
			return '';
		}

		$street = array_shift( $parts );
		$html   = esc_html( $street );

		if ( $parts ) {
			$html .= '<br>' . esc_html( implode( ', ', $parts ) );
		}

		return $html;
	}
}

if ( ! function_exists( 'dts_get_order_email_items_html' ) ) {
	/**
	 * Line items, each with the brand and size the customer actually ordered.
	 *
	 * @param WC_Order $order Order.
	 */
	function dts_get_order_email_items_html( $order ) {
		$html = '';

		foreach ( $order->get_items() as $item ) {
			$product    = $item->get_product();
			$product_id = $product ? $product->get_id() : 0;
			$name       = $product ? $product->get_name() : $item->get_name();

			$details = array();
			$brand   = $product_id ? dts_get_brand_term( dts_get_brand_slug( $product_id ) ) : null;

			if ( $brand && ! empty( $brand->name ) ) {
				$details[] = '<strong>' . esc_html( $brand->name ) . '</strong>';
			}

			$size = $product && function_exists( 'dts_get_email_product_size' )
				? dts_get_email_product_size( $product )
				: dts_get_product_size_label( $product_id );

			if ( $size ) {
				$details[] = esc_html__( 'Size', 'custom-dubai-tyre-shop' ) . ': ' . esc_html( $size );
			}

			$details_html = $details
				? '<div style="margin-top:3px;font-size:12px;color:#888;">' . implode( ' &bull; ', $details ) . '</div>'
				: '';

			$html .= '<tr>'
				. '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;color:#555;">'
				. esc_html( $name ) . ' &times; ' . esc_html( (string) $item->get_quantity() )
				. $details_html
				. '</td>'
				. '<td style="padding:10px 0;border-bottom:1px solid #eee;font-size:14px;color:#555;text-align:right;">'
				. wp_kses_post( wc_price( $item->get_total() ) )
				. '</td>'
				. '</tr>';
		}

		return $html;
	}
}

if ( ! function_exists( 'dts_get_order_email_blocks' ) ) {
	/**
	 * The shared sections of the order notification.
	 *
	 * Both the store copy and the customer copy describe the same order, so the
	 * blocks are built once and reused rather than duplicated per recipient.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	function dts_get_order_email_blocks( $order ) {
		$accent = dts_get_email_accent();
		$items  = dts_get_order_email_items_html( $order );
		$total  = wp_kses_post( wc_price( $order->get_total() ) );

		$vehicle_rows = dts_get_order_email_vehicle_rows( $order );
		$vehicle_html = '';

		if ( array_filter( $vehicle_rows ) ) {
			$vehicle_html = '<h2 style="margin:24px 0 12px;font-size:16px;color:#1a1a1a;">'
				. esc_html__( 'Vehicle Details', 'custom-dubai-tyre-shop' ) . '</h2>'
				. '<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">'
				. dts_get_email_detail_rows( $vehicle_rows, array( 'first' => true ) )
				. '</table>';
		}

		return array(
			'total'        => $total,
			'accent'       => $accent,
			'items'        => $items,
			'vehicle_html' => $vehicle_html,
			'delivery_html' => '<h2 style="margin:24px 0 12px;font-size:16px;color:#1a1a1a;">'
				. esc_html__( 'Delivery Details', 'custom-dubai-tyre-shop' ) . '</h2>'
				. '<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">'
				. dts_get_email_detail_rows( dts_get_order_email_delivery_rows( $order ), array( 'first' => true ) )
				. '</table>',
			'items_html'   => '<h2 style="margin:24px 0 12px;font-size:16px;color:#1a1a1a;">'
				. esc_html__( 'Order Items', 'custom-dubai-tyre-shop' ) . '</h2>'
				. '<table style="width:100%;border-collapse:collapse;">' . $items . '</table>',
		);
	}
}

if ( ! function_exists( 'dts_send_order_emails' ) ) {
	/**
	 * Send the store notification and the customer acknowledgement.
	 *
	 * @param int       $order_id   Order ID.
	 * @param array     $posted_data Posted checkout data, unused.
	 * @param WC_Order  $order      Order, or null to look it up.
	 */
	function dts_send_order_emails( $order_id, $posted_data = null, $order = null ) {
		$order = $order ? $order : wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$blocks         = dts_get_order_email_blocks( $order );
		$order_number   = $order->get_order_number();
		$customer_email = $order->get_billing_email();
		$customer_name  = $order->get_billing_first_name();
		$store          = dts_get_store_email();

		$billing_address = dts_format_order_address(
			array(
				$order->get_billing_first_name(),
				$order->get_billing_last_name(),
				$order->get_billing_address_1(),
				$order->get_billing_address_2(),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
			)
		);

		if ( $store ) {
			$rows = array(
				__( 'Order Number', 'custom-dubai-tyre-shop' ) => '#' . esc_html( $order_number ),
				__( 'Customer', 'custom-dubai-tyre-shop' )    => esc_html( trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) ),
				__( 'Email', 'custom-dubai-tyre-shop' )      => '<a href="mailto:' . esc_attr( $customer_email ) . '" style="color:' . esc_attr( $blocks['accent'] ) . ';">' . esc_html( $customer_email ) . '</a>',
				__( 'Phone', 'custom-dubai-tyre-shop' )      => esc_html( $order->get_billing_phone() ),
				__( 'Billing Address', 'custom-dubai-tyre-shop' ) => $billing_address,
				__( 'Total', 'custom-dubai-tyre-shop' )      => '<span style="font-size:18px;font-weight:700;color:#1a1a1a;">' . $blocks['total'] . '</span>',
			);

			$admin_body = '<h2 style="margin:0 0 16px;font-size:16px;color:#1a1a1a;">'
				. esc_html__( 'Order Information', 'custom-dubai-tyre-shop' ) . '</h2>'
				. '<table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">'
				. dts_get_email_detail_rows( $rows, array( 'first' => true ) )
				. '</table>'
				. $blocks['vehicle_html']
				. $blocks['delivery_html']
				. $blocks['items_html'];

			dts_send_email( $store, sprintf( /* translators: %s: order number. */ __( 'New Order Received — #%s', 'custom-dubai-tyre-shop' ), $order_number ), $admin_body );
		}

		$customer_body = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#555;">'
			. esc_html__( 'Thank you for your order! We have received it and will process it shortly. You will receive a notification once your order is on its way.', 'custom-dubai-tyre-shop' )
			. '</p>'
			. '<div style="padding:16px;background:#f9f9f9;border-left:3px solid ' . esc_attr( $blocks['accent'] ) . ';border-radius:4px;margin-bottom:16px;">'
			. '<p style="margin:0 0 4px;font-size:14px;color:#999;">' . esc_html__( 'Order Number', 'custom-dubai-tyre-shop' ) . '</p>'
			. '<p style="margin:0;font-size:20px;font-weight:700;color:#1a1a1a;">#' . esc_html( $order_number ) . '</p>'
			. '</div>'
			. $blocks['vehicle_html']
			. $blocks['delivery_html']
			. $blocks['items_html']
			. '<div style="margin-top:16px;padding-top:16px;border-top:2px solid #1a1a1a;text-align:right;font-size:18px;font-weight:700;color:#1a1a1a;">'
			. esc_html__( 'Total', 'custom-dubai-tyre-shop' ) . ': ' . $blocks['total']
			. '</div>';

		$phone_href = dts_get_store_phone_href();

		if ( $phone_href ) {
			$customer_body .= '<p style="margin:20px 0 0;font-size:14px;line-height:1.6;color:#999;">'
				. esc_html__( 'If you have any questions, call us at', 'custom-dubai-tyre-shop' ) . ' '
				. '<a href="' . esc_attr( $phone_href ) . '" style="color:' . esc_attr( $blocks['accent'] ) . ';text-decoration:none;">'
				. esc_html( dts_get_store_detail( 'phone' ) ) . '</a>.</p>';
		}

		dts_send_email( $customer_email, sprintf( /* translators: %s: order number. */ __( 'Order Received — #%s', 'custom-dubai-tyre-shop' ), $order_number ), $customer_body, $customer_name );
	}
}
add_action( 'woocommerce_checkout_order_processed', 'dts_send_order_emails', 10, 3 );
