<?php
/**
 * Order Receipt (PDF) â€” rendered from HTML/CSS via Dompdf.
 *
 * Produces a professional, branded A4 receipt that mirrors the thank-you
 * page. It contains:
 *   - Page 1: detailed receipt
 *       * Dark header band with logo + "ORDER RECEIPT" + receipt ref/date
 *       * Order summary strip (date / status / payment method / currency)
 *       * Bill-to / ship-to contact blocks
 *       * Line-items table (product, tyre size, qty, unit price, amount)
 *       * Subtotal / shipping / VAT / total breakdown
 *       * Vehicle details + delivery & fitting information
 *   - Page 2: card-style summary matching the thank-you preview
 *       * Green check, "Payment Success!", total payment
 *       * Two-column detail grid + price breakdown
 *
 * The library is vendored under inc/lib/dompdf/ (Dompdf 3.x, self-contained,
 * pure PHP, includes Unicode DejaVu fonts). It is only loaded when a
 * receipt is actually generated, so normal page loads stay lightweight.
 *
 * The download link is protected by a wp_hash() signed URL and verified
 * against the order key, so only the order owner (or admin) can fetch it.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the vendored Dompdf autoloader once.
 *
 * @return bool True when Dompdf is available.
 */
function dts_dompdf_available() {
	static $available = null;
	if ( null === $available ) {
		$autoload = __DIR__ . '/../lib/dompdf/autoload.php';
		$available = file_exists( $autoload );
		if ( $available ) {
			require_once $autoload;
		}
	}
	return $available;
}

/**
 * Absolute path to the site logo used on receipts.
 *
 * @return string
 */
function dts_receipt_logo_file() {
	return function_exists( 'dts_get_store_logo_file' ) ? dts_get_store_logo_file() : '';
}

/**
 * Base64 data-URI for the receipt logo (no filesystem/remote access needed
 * at render time, keeps Dompdf remote-loading disabled).
 *
 * @return string
 */
function dts_receipt_logo_data_uri() {
	$file = dts_receipt_logo_file();
	if ( ! $file ) {
		return '';
	}
	$bytes = @file_get_contents( $file );
	if ( ! $bytes ) {
		return '';
	}
	return 'data:image/png;base64,' . base64_encode( $bytes );
}

/**
 * Format an amount as "AED 1,234.56".
 *
 * @param float  $amount
 * @param string $currency
 * @return string
 */
function dts_receipt_money( $amount, $currency = 'AED' ) {
	$amount   = round( (float) $amount, 2 );
	$negative = $amount < 0;
	$amount   = abs( $amount );
	$symbol   = $currency ? strtoupper( $currency ) : 'AED';
	return ( $negative ? '- ' : '' ) . $symbol . ' ' . number_format( $amount, 2, '.', ',' );
}

/**
 * Translate a country code to its full name (via WooCommerce when present).
 *
 * @param string $code
 * @return string
 */
function dts_receipt_country( $code ) {
	if ( $code && class_exists( 'WC' ) && WC()->countries ) {
		$countries = WC()->countries->get_countries();
		return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}
	return $code;
}

/**
 * Escape a value for use inside HTML.
 *
 * @param string $s
 * @return string
 */
function dts_receipt_h( $s ) {
	return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
}

/**
 * Status colour used for the status badge.
 *
 * @param string $status
 * @return string Hex colour.
 */
function dts_receipt_status_color( $status ) {
	$colors = array(
		'pending'    => '#f59e0b',
		'processing' => '#3b82f6',
		'on-hold'    => '#9ca3af',
		'completed'  => '#10b981',
		'cancelled'  => '#A35C00',
		'refunded'   => '#A35C00',
		'failed'     => '#A35C00',
	);
	return isset( $colors[ $status ] ) ? $colors[ $status ] : '#9ca3af';
}

/**
 * Build the receipt HTML (used by the PDF renderer and by tests).
 *
 * @param WC_Order $order
 * @return string
 */
function dts_build_receipt_html( $order ) {
	$site_name = get_bloginfo( 'name' );
	if ( empty( $site_name ) ) {
		$site_name = function_exists( 'dts_get_store_detail' ) ? dts_get_store_detail( 'name' ) : 'Tyre Shop';
	}
	$site_url = home_url( '/' );

	$order_number = $order->get_order_number();
	$currency     = $order->get_currency();
	$created      = $order->get_date_created();
	$date_label   = $created ? $created->date( 'd M Y' ) : '';
	$date_full    = $created ? $created->date_i18n( 'F j, Y' ) : '';
	$time_label   = $created ? $created->date( 'g:i A' ) : '';

	$status_labels = array(
		'pending'    => 'Pending Payment',
		'processing' => 'Processing',
		'on-hold'    => 'On Hold',
		'completed'  => 'Completed',
		'cancelled'  => 'Cancelled',
		'refunded'   => 'Refunded',
		'failed'     => 'Failed',
	);
	$status       = $order->get_status();
	$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
	$status_color = dts_receipt_status_color( $status );
	$payment      = $order->get_payment_method_title();
	$billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
	$email        = $order->get_billing_email();

	// Vehicle + delivery / fitting data.
	$method    = $order->get_meta( '_dts_delivery_method' );
	$centre_id = $order->get_meta( '_dts_fitting_center' );
	$centre    = array();

	if ( $centre_id && function_exists( 'dts_get_fitting_center_lookup' ) ) {
		$lookup = dts_get_fitting_center_lookup();
		$centre = isset( $lookup[ $centre_id ] )
			? $lookup[ $centre_id ]
			: array( 'name' => ucwords( str_replace( '_', ' ', (string) $centre_id ) ), 'city' => '', 'hours' => array() );
	}
	$fit_date = $order->get_meta( '_dts_fitting_date' );
	$fit_time = $order->get_meta( '_dts_fitting_time' );

	$vehicle_make  = $order->get_meta( 'Car Make' );
	$vehicle_model = $order->get_meta( 'Model Number' );
	$vehicle_plate = $order->get_meta( 'Plate Number' );
	$vehicle_year  = $order->get_meta( 'Car Year' );

	// Fee totals (VAT vs other fees vs skip shipping / mobile service).
	$fee_total = 0.0;
	$vat_total = 0.0;
	foreach ( $order->get_fees() as $fee ) {
		$fname = $fee->get_name();
		if ( false !== stripos( $fname, 'VAT' ) ) {
			$vat_total += (float) $fee->get_total();
		} elseif ( false !== stripos( $fname, 'Shipping' ) || false !== stripos( $fname, 'Mobile Service' ) ) {
			continue;
		} else {
			$fee_total += (float) $fee->get_total();
		}
	}

	// Totals breakdown.
	$ship_has_total = (float) $order->get_shipping_total() > 0;
	$break_rows     = array();
	$break_rows[]   = array( 'Subtotal', dts_receipt_money( $order->get_subtotal(), $currency ), false );
	if ( (float) $order->get_discount_total() > 0 ) {
		$break_rows[] = array( 'Discount', '- ' . dts_receipt_money( $order->get_discount_total(), $currency ), false );
	}
	if ( 0.0 !== $fee_total ) {
		$break_rows[] = array( 'Fees', dts_receipt_money( $fee_total, $currency ), false );
	}
	if ( $ship_has_total ) {
		$ship_label = $order->get_shipping_method();
		$break_rows[] = array( $ship_label ? $ship_label : 'Shipping', dts_receipt_money( $order->get_shipping_total(), $currency ), false );
	}
	if ( 0.0 !== $vat_total ) {
		$break_rows[] = array( 'VAT (5%)', dts_receipt_money( $vat_total, $currency ), false );
	}
	$grand_total = dts_receipt_money( $order->get_total(), $currency );

	// â”€â”€ Line items â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$item_rows = array();
	foreach ( $order->get_items() as $idx => $item ) {
		$product = $item->get_product();
		$pname   = $product ? $product->get_name() : $item->get_name();
		$size    = ( $product && function_exists( 'dts_get_email_product_size' ) ) ? dts_get_email_product_size( $product ) : '';
		$qty     = max( 1, (int) $item->get_quantity() );
		$unit    = (float) $item->get_subtotal() / $qty;
		$line    = (float) $item->get_total();
		$item_rows[] = array(
			'name' => $pname,
			'size' => $size,
			'qty'  => $qty,
			'unit' => dts_receipt_money( $unit, $currency ),
			'total' => dts_receipt_money( $line, $currency ),
			'zebra' => ( 0 === $idx % 2 ),
		);
	}

	// â”€â”€ Contact blocks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$billing_addr = array_filter( array(
		$order->get_billing_address_1(),
		$order->get_billing_address_2(),
		trim( trim( $order->get_billing_city() . ' ' . $order->get_billing_state() ) . ' ' . $order->get_billing_postcode() ),
		dts_receipt_country( $order->get_billing_country() ),
	) );

	$has_shipping = (bool) $order->get_shipping_address_1();
	$shipping_addr = array_filter( array(
		$order->get_shipping_address_1(),
		$order->get_shipping_address_2(),
		trim( trim( $order->get_shipping_city() . ' ' . $order->get_shipping_state() ) . ' ' . $order->get_shipping_postcode() ),
		dts_receipt_country( $order->get_shipping_country() ),
	) );

	$logo_uri = dts_receipt_logo_data_uri();

	// â”€â”€ CSS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$css = <<<'CSS'
		@page {
			size: A4 portrait;
			margin: 10mm 9mm 13mm 9mm;
		}
		body {
			font-family: 'DejaVu Sans', sans-serif;
			color: #222222;
			font-size: 8.5pt;
			line-height: 1.25;
			margin: 0;
		}
		.hdr {
			background: #1a1a1a;
			border-radius: 5pt;
			overflow: hidden;
		}
		.hdr table { width: 100%; border-collapse: collapse; }
		.hdr td { padding: 10pt 14pt; vertical-align: middle; }
		.hdr .brand { color: #ffffff; }
		.hdr .brand-right { text-align: right; }
		.hdr .bname { font-size: 16pt; font-weight: bold; color: #ffffff; margin: 0; }
		.hdr .burl { font-size: 7.5pt; color: #bbbbbb; margin-top: 2pt; }
		.hdr .logo { height: 40pt; }
		.hdr .btitle { font-size: 13pt; font-weight: bold; color: #d4a11e; letter-spacing: 1.5pt; text-transform: uppercase; }
		.hdr .bmeta { font-size: 8.5pt; color: #ffffff; margin-top: 3pt; }
		.hdr .bdate { font-size: 7.5pt; color: #bbbbbb; margin-top: 1pt; }
		.goldbar { height: 3pt; background: #d4a11e; border-radius: 0 0 5pt 5pt; margin-bottom: 7pt; }

		.sec {
			border-left: 3pt solid #d4a11e;
			padding-left: 6pt;
			margin: 8pt 0 5pt 0;
			font-size: 9pt;
			font-weight: bold;
			color: #b8890d;
			text-transform: uppercase;
			letter-spacing: 1pt;
		}
		.nobreak { page-break-inside: avoid; }

		.hero { border: 0.5pt solid #e4e4e4; border-radius: 5pt; background: #fafafa; }
		.hero table { width: 100%; border-collapse: collapse; }
		.hero td { width: 25%; padding: 6pt 9pt; border-right: 0.5pt solid #e9e9e9; }
		.hero td.nobr { border-right: none; }
		.hero .lbl { font-size: 6pt; color: #9a9a9a; text-transform: uppercase; letter-spacing: 0.5pt; }
		.hero .val { font-size: 8pt; font-weight: bold; color: #111111; margin-top: 1pt; }

		.cols { width: 100%; border-collapse: collapse; }
		.cols td { width: 50%; vertical-align: top; padding: 0 4pt; }
		.cols td:first-child { padding-left: 0; }
		.cols td:nth-child(2) { padding-right: 0; }
		.pair { margin: 0 0 2pt 0; }
		.pair .lbl { font-size: 6pt; color: #9a9a9a; text-transform: uppercase; letter-spacing: 0.4pt; }
		.pair .val { font-size: 8pt; color: #222222; word-wrap: break-word; }

		table.items { width: 100%; border-collapse: collapse; margin-top: 4pt; }
		table.items th {
			background: #1a1a1a; color: #ffffff; font-size: 6.5pt; text-transform: uppercase;
			letter-spacing: 0.4pt; text-align: left; padding: 4pt 6pt;
		}
		table.items td { padding: 3pt 6pt; border-bottom: 0.5pt solid #ececec; font-size: 8pt; vertical-align: top; }
		table.items tr.zebra td { background: #f8f8f8; }
		table.items .amt, table.items .qty { text-align: right; white-space: nowrap; }
		table.items td.size { color: #555555; }
		table.items tr.row { page-break-inside: avoid; }

		table.totals { width: 100%; border-collapse: collapse; margin-top: 6pt; }
		table.totals td { padding: 2pt 6pt; font-size: 8pt; }
		table.totals td.lbl { width: 68%; text-align: right; color: #666666; }
		table.totals td.amt { width: 32%; text-align: right; color: #222222; white-space: nowrap; }
		table.totals tr.grand td { background: #d4a11e; color: #ffffff; font-weight: bold; font-size: 9.5pt; padding: 5pt 6pt; }
		table.totals tr.grand td.lbl { color: #ffffff; }

		.thanks { margin-top: 10pt; border-top: 0.5pt solid #e3e3e3; padding-top: 6pt; }
		.thanks .t1 { font-size: 8pt; color: #555555; }
		.thanks .t2 { font-size: 6.5pt; color: #999999; margin-top: 1pt; }

		.page-break { page-break-before: always; }

		.card {}
		.done {
			width: 38pt; height: 38pt; line-height: 38pt; border-radius: 50%;
			background: #10b981; color: #ffffff; font-size: 19pt; font-weight: bold;
			text-align: center; margin: 4pt auto 8pt auto;
		}
		h2.card-title { margin: 0; text-align: center; font-size: 16pt; color: #1a1a1a; }
		.card-sub { margin: 2pt 0 10pt 0; text-align: center; font-size: 9pt; color: #888888; }
		.g-sep { width: 55%; border-top: 0.5pt solid #e0e0e0; margin: 0 auto 10pt auto; }
		.total-lbl { margin: 0; text-align: center; font-size: 7pt; color: #999999; text-transform: uppercase; letter-spacing: 1pt; }
		.total-amt { margin: 1pt 0 12pt 0; text-align: center; font-size: 21pt; font-weight: bold; color: #1a1a1a; }

		table.grid { width: 100%; border-collapse: collapse; margin: 4pt 0 10pt 0; }
		table.grid td { border: 0.5pt solid #e0e0e0; padding: 4pt 6pt; vertical-align: top; width: 50%; }
		table.grid .gl { font-size: 6pt; color: #9a9a9a; text-transform: uppercase; letter-spacing: 0.4pt; }
		table.grid .gv { font-size: 8pt; font-weight: bold; color: #1a1a1a; margin-top: 1pt; word-wrap: break-word; }

		.pagefoot {
			position: fixed;
			bottom: -13mm;
			left: 0;
			right: 0;
			text-align: center;
			font-size: 7pt;
			color: #999999;
			border-top: 0.5pt solid #dcdcdc;
			padding-top: 3pt;
			margin: 0 12mm;
		}
	CSS;

	// â”€â”€ Build item table rows â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$item_html = '';
	if ( empty( $item_rows ) ) {
		$item_html = '<tr><td colspan="5">No items.</td></tr>';
	} else {
		foreach ( $item_rows as $ir ) {
			$row_class = $ir['zebra'] ? ' zebra' : '';
			$item_html .= '<tr class="row' . $row_class . '">';
			$item_html .= '<td class="pname">' . dts_receipt_h( $ir['name'] ) . '</td>';
			$item_html .= '<td class="size">' . dts_receipt_h( $ir['size'] ) . '</td>';
			$item_html .= '<td class="qty">' . dts_receipt_h( (string) $ir['qty'] ) . '</td>';
			$item_html .= '<td class="amt">' . dts_receipt_h( $ir['unit'] ) . '</td>';
			$item_html .= '<td class="amt">' . dts_receipt_h( $ir['total'] ) . '</td>';
			$item_html .= '</tr>';
		}
	}

	// â”€â”€ Totals block â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$totals_html = '';
	foreach ( $break_rows as $brk ) {
		$totals_html .= '<tr><td class="lbl">' . dts_receipt_h( $brk[0] ) . '</td><td class="amt">' . dts_receipt_h( $brk[1] ) . '</td></tr>';
	}
	$totals_html .= '<tr class="grand"><td class="lbl">Total</td><td class="amt">' . dts_receipt_h( $grand_total ) . '</td></tr>';

	// â”€â”€ Billing / shipping blocks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$billing_html = '';
	if ( $billing_name ) {
		$billing_html .= '<p class="pair"><span class="lbl">Name</span><br /><span class="val">' . dts_receipt_h( $billing_name ) . '</span></p>';
	}
	if ( $email ) {
		$billing_html .= '<p class="pair"><span class="lbl">Email</span><br /><span class="val">' . dts_receipt_h( $email ) . '</span></p>';
	}
	if ( $order->get_billing_phone() ) {
		$billing_html .= '<p class="pair"><span class="lbl">Phone</span><br /><span class="val">' . dts_receipt_h( $order->get_billing_phone() ) . '</span></p>';
	}
	if ( ! empty( $billing_addr ) ) {
		$billing_html .= '<p class="pair"><span class="lbl">Address</span><br /><span class="val">' . dts_receipt_h( implode( ', ', $billing_addr ) ) . '</span></p>';
	}

	$shipping_html = '';
	if ( $has_shipping ) {
		$shipping_name = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( $shipping_name ) {
			$shipping_html .= '<p class="pair"><span class="lbl">Name</span><br /><span class="val">' . dts_receipt_h( $shipping_name ) . '</span></p>';
		}
		if ( ! empty( $shipping_addr ) ) {
			$shipping_html .= '<p class="pair"><span class="lbl">Address</span><br /><span class="val">' . dts_receipt_h( implode( ', ', $shipping_addr ) ) . '</span></p>';
		}
	}

	$contact_html  = '<div class="nobreak">';
	$contact_html .= '<div class="sec">Billed To</div><div class="cols"><table><tr>';
	$contact_html .= '<td>' . ( $billing_html ? $billing_html : '&nbsp;' ) . '</td>';
	$contact_html .= '<td>' . ( $shipping_html ? '<div class="sec" style="margin-top:0">Shipping To</div>' . $shipping_html : '&nbsp;' ) . '</td>';
	$contact_html .= '</tr></table></div></div>';

	// â”€â”€ Vehicle + fitting blocks â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$vehicle_pairs = array();
	if ( $vehicle_plate ) { $vehicle_pairs[] = array( 'Plate Number', $vehicle_plate ); }
	if ( $vehicle_make ) { $vehicle_pairs[] = array( 'Car Make', $vehicle_make ); }
	if ( $vehicle_model ) { $vehicle_pairs[] = array( 'Model Number', $vehicle_model ); }
	if ( $vehicle_year ) { $vehicle_pairs[] = array( 'Year', $vehicle_year ); }

	$dts_render_pairs = function ( $pairs ) {
		if ( empty( $pairs ) ) {
			return '<p class="pair"><span class="val" style="color:#999">Not provided</span></p>';
		}
		$half   = (int) ceil( count( $pairs ) / 2 );
		$col_a  = array_slice( $pairs, 0, $half );
		$col_b  = array_slice( $pairs, $half );
		$one    = function ( $pair ) {
			return '<p class="pair"><span class="lbl">' . dts_receipt_h( $pair[0] ) . '</span><br /><span class="val">' . dts_receipt_h( $pair[1] ) . '</span></p>';
		};
		$html_a = '';
		foreach ( $col_a as $p ) {
			$html_a .= $one( $p );
		}
		$html_b = '';
		foreach ( $col_b as $p ) {
			$html_b .= $one( $p );
		}
		return '<div class="cols"><table><tr><td>' . $html_a . '</td><td>' . ( $html_b ? $html_b : '&nbsp;' ) . '</td></tr></table></div>';
	};

	$vehicle_html = $dts_render_pairs( $vehicle_pairs );
	$fit_pairs = array();
	if ( $method && function_exists( 'dts_get_delivery_method_label' ) ) {
		$fit_pairs[] = array( 'Method', dts_get_delivery_method_label( $method ) );
	}
	if ( ! empty( $centre['name'] ) ) {
		$fit_pairs[] = array( 'Fitting Centre', $centre['name'] );
	}
	if ( ! empty( $centre['city'] ) ) {
		$fit_pairs[] = array( 'Location', $centre['city'] );
	}
	if ( ! empty( $centre['hours'] ) ) {
		$fit_pairs[] = array(
			'Opening Hours',
			is_array( $centre['hours'] ) ? implode( ' · ', $centre['hours'] ) : (string) $centre['hours'],
		);
	}
	if ( $fit_date ) {
		$fit_pairs[] = array( 'Preferred Date', $fit_date );
	}
	if ( $fit_time ) {
		$fit_pairs[] = array( 'Preferred Time', $fit_time );
	}

	$fit_html = $dts_render_pairs( $fit_pairs );

	// â”€â”€ Card summary rows â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$card_rows = array();
	$card_rows[] = array( 'Name', $billing_name, 'Email', $email );

	$payment_time = $date_label ? $date_label . ', ' . $time_label : '';
	$card_rows[] = array( 'Ref Number', '#' . $order_number, 'Payment Time', $payment_time );
	$card_rows[] = array( 'Payment Method', $payment ? $payment : 'â€”', 'Status', $status_label );

	$product_lines = array();
	$qty_lines     = array();
	foreach ( $order->get_items() as $item ) {
		$product = $item->get_product();
		$pname   = $product ? $product->get_name() : $item->get_name();
		$size    = ( $product && function_exists( 'dts_get_email_product_size' ) ) ? dts_get_email_product_size( $product ) : '';
		if ( $size ) {
			$pname .= ' (Tyre Size: ' . $size . ')';
		}
		$product_lines[] = $pname;
		$qty_lines[]     = (string) max( 1, (int) $item->get_quantity() );
	}
	$card_rows[] = array( 'Product', implode( "\n", $product_lines ), 'Quantity', implode( "\n", $qty_lines ) );

	if ( ! empty( $centre['name'] ) || $fit_date || $fit_time ) {
		$slot = array_filter( array( $fit_date, $fit_time ) );
		$card_rows[] = array(
			'Fitting Centre', ! empty( $centre['name'] ) ? $centre['name'] : 'â€”',
			'Date & Time', $slot ? implode( ' at ', $slot ) : 'â€”',
		);
	}
	if ( $vehicle_make || $vehicle_model ) {
		$card_rows[] = array( 'Car Make', $vehicle_make ? $vehicle_make : 'â€”', 'Model No', $vehicle_model ? $vehicle_model : 'â€”' );
	}
	if ( $vehicle_plate || $vehicle_year ) {
		$card_rows[] = array( 'Plate No', $vehicle_plate ? $vehicle_plate : 'â€”', 'Year', $vehicle_year ? $vehicle_year : 'â€”' );
	}

	$grid_html = '';
	foreach ( $card_rows as $cri => $cr ) {
		$grid_html .= '<tr>';
		$grid_html .= '<td><div class="gl">' . dts_receipt_h( $cr[0] ) . '</div><div class="gv">' . nl2br( dts_receipt_h( $cr[1] ) ) . '</div></td>';
		$grid_html .= '<td><div class="gl">' . dts_receipt_h( $cr[2] ) . '</div><div class="gv">' . nl2br( dts_receipt_h( $cr[3] ) ) . '</div></td>';
		$grid_html .= '</tr>';
	}

	$card_break_html = '';
	foreach ( $break_rows as $brk ) {
		$card_break_html .= '<tr><td class="lbl">' . dts_receipt_h( $brk[0] ) . '</td><td class="amt">' . dts_receipt_h( $brk[1] ) . '</td></tr>';
	}
	$card_break_html .= '<tr class="grand"><td class="lbl">Total</td><td class="amt">' . dts_receipt_h( $grand_total ) . '</td></tr>';

	// â”€â”€ Header brand cell â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	if ( $logo_uri ) {
		$brand_cell = '<td class="brand"><img class="logo" src="' . $logo_uri . '" /></td>';
	} else {
		$brand_cell = '<td class="brand"><div class="bname">' . dts_receipt_h( $site_name ) . '</div><div class="burl">' . dts_receipt_h( $site_url ) . '</div></td>';
	}

	$hero_cells = '';
	$hero_cells .= '<td><div class="lbl">Order Date</div><div class="val">' . dts_receipt_h( $date_full ) . '</div></td>';
	$hero_cells .= '<td><div class="lbl">Status</div><div class="val" style="color:' . $status_color . '">' . dts_receipt_h( $status_label ) . '</div></td>';
	$hero_cells .= '<td><div class="lbl">Payment Method</div><div class="val">' . dts_receipt_h( $payment ? $payment : 'â€”' ) . '</div></td>';
	$hero_cells .= '<td class="nobr"><div class="lbl">Currency</div><div class="val">' . dts_receipt_h( $currency ? strtoupper( $currency ) : 'AED' ) . '</div></td>';

	// â”€â”€ Assemble HTML â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
{$css}
</style>
</head>
<body>

	<!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ PAGE 1 : DETAILED RECEIPT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
	<div class="hdr">
		<table cellpadding="0" cellspacing="0"><tr>
			{$brand_cell}
			<td class="brand-right">
				<div class="btitle">Order Receipt</div>
				<div class="bmeta">Receipt #{$order_number}</div>
				<div class="bdate">{$date_label}</div>
			</td>
		</tr></table>
	<div class="goldbar"></div>
</div>

	{$contact_html}

	<div class="sec">Items</div>
	<table class="items nobreak">
		<thead>
			<tr>
				<th style="width:44%">Product</th>
				<th style="width:16%">Tyre Size</th>
				<th style="width:8%">Qty</th>
				<th style="width:16%">Unit Price</th>
				<th style="width:16%">Amount</th>
			</tr>
		</thead>
		<tbody>
			{$item_html}
		</tbody>
	</table>

	<table class="totals nobreak">{$totals_html}</table>

	<div class="sec">Vehicle Details</div>
	<div class="cols"><table><tr><td>{$vehicle_html}</td><td>&nbsp;</td></tr></table></div>

	<div class="sec">Delivery &amp; Fitting</div>
	<div class="cols"><table><tr><td>{$fit_html}</td><td>&nbsp;</td></tr></table></div>

	<div class="thanks">
		<div class="t1">Thank you for shopping with {$site_name}!</div>
		<div class="t2">{$site_url} &nbsp;|&nbsp; This is a system-generated receipt.</div>
	</div>

	<!-- â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ PAGE 2 : CARD-STYLE SUMMARY â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ -->
	<div class="card page-break">

	<div class="hdr">
		<table cellpadding="0" cellspacing="0"><tr>
			{$brand_cell}
			<td class="brand-right">
				<div class="btitle">Order Summary</div>
				<div class="bmeta">Receipt #{$order_number}</div>
				<div class="bdate">{$date_label}</div>
			</td>
		</tr></table>
		<div class="goldbar"></div>
	</div>

	<h2 class="card-title">Payment Success</h2>
	<div class="done">&#10004;</div>
	<p class="card-sub">Your order has been placed successfully.</p>
	<div class="g-sep"></div>
	<p class="total-lbl">Total Payment</p>
	<p class="total-amt">{$grand_total}</p>

	<table class="grid">
		{$grid_html}
	</table>

	<table class="totals nobreak">{$card_break_html}</table>

	<div class="thanks">
		<div class="t1">Thank you for shopping with {$site_name}!</div>
		<div class="t2">{$site_url} &nbsp;|&nbsp; This is a system-generated receipt.</div>
	</div>

	</div>

	<div class="pagefoot">{$site_url} &nbsp;|&nbsp; This is a system-generated receipt.</div>

</body>
</html>
HTML;

	// â”€â”€ Render via Dompdf â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
	return $html;
}

/**
 * Build the receipt PDF for an order as a binary string.
 *
 * @param WC_Order $order
 * @return string PDF binary data.
 */
function dts_generate_order_receipt( $order ) {
	if ( ! dts_dompdf_available() ) {
		return '';
	}

	$html = dts_build_receipt_html( $order );

	$options = new Dompdf\Options();
	$options->set( 'isRemoteEnabled', false );
	$options->set( 'isHtml5ParserEnabled', true );
	$options->set( 'isPhpEnabled', false );
	$options->set( 'defaultFont', 'DejaVu Sans' );
	$options->set( 'fontHeightRatio', 1.15 );
	$options->set( 'dpi', 96 );

	$dompdf = new Dompdf\Dompdf( $options );
	$dompdf->loadHtml( $html );
	$dompdf->setPaper( 'A4', 'portrait' );
	$dompdf->render();
	return $dompdf->output();
}

/**
 * Signed hash for a receipt download link.
 *
 * @param int    $order_id
 * @param string $order_key
 * @return string
 */
function dts_order_receipt_hash( $order_id, $order_key ) {
	return wp_hash( 'dts-receipt|' . $order_id . '|' . $order_key );
}

/**
 * Receipt download URL for the given order.
 *
 * @param WC_Order $order
 * @return string
 */
function dts_order_receipt_url( $order ) {
	return add_query_arg(
		array(
			'action'   => 'dts_download_receipt',
			'order_id' => $order->get_id(),
			'hash'     => dts_order_receipt_hash( $order->get_id(), $order->get_order_key() ),
		),
		admin_url( 'admin-ajax.php' )
	);
}

/**
 * Stream the receipt PDF to the browser.
 */
add_action( 'wp_ajax_dts_download_receipt', 'dts_download_receipt' );
add_action( 'wp_ajax_nopriv_dts_download_receipt', 'dts_download_receipt' );
function dts_download_receipt() {
	$order_id = absint( $_GET['order_id'] ?? 0 );
	$hash     = sanitize_text_field( $_GET['hash'] ?? '' );
	$order    = $order_id ? wc_get_order( $order_id ) : false;

	if ( ! $order ) {
		status_header( 404 );
		wp_die( esc_html__( 'Receipt not found.', 'custom-dubai-tyre-shop' ) );
	}
	if ( ! hash_equals( dts_order_receipt_hash( $order->get_id(), $order->get_order_key() ), $hash ) ) {
		status_header( 403 );
		wp_die( esc_html__( 'Invalid receipt link.', 'custom-dubai-tyre-shop' ) );
	}

	// Logged-in customers may only download their own receipts.
	if ( is_user_logged_in() && ! current_user_can( 'manage_woocommerce' ) ) {
		$uid = (int) $order->get_user_id();
		if ( $uid && $uid !== get_current_user_id() ) {
			status_header( 403 );
			wp_die( esc_html__( 'Invalid receipt link.', 'custom-dubai-tyre-shop' ) );
		}
	}

	try {
		$pdf = dts_generate_order_receipt( $order );
	} catch ( Throwable $e ) {
		$pdf = '';
	}

	if ( empty( $pdf ) ) {
		status_header( 500 );
		wp_die( esc_html__( 'Receipt generation is currently unavailable.', 'custom-dubai-tyre-shop' ) );
	}

	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="tyre-shop-receipt-' . $order->get_order_number() . '.pdf"' );
	echo $pdf;
	exit;
}