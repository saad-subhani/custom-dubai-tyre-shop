<?php
/**
 * Theme bootstrap.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once get_theme_file_path( '/inc/theme-setup.php' );
require_once get_theme_file_path( '/inc/helpers.php' );
require_once get_theme_file_path( '/inc/enqueue.php' );
require_once get_theme_file_path( '/plugin/home-offer-slider.php' );

/**
 * Backend modules.
 *
 * Load order is dependency-driven: each file may only call a function defined by
 * a file above it. Since every definition is guarded with function_exists() and
 * the hooks are only ever fired after the whole tree is loaded, the order below
 * is about which service owns a concern, not about avoiding redefinition.
 *
 * 1. env-loader          reads the theme .env, everything credentialed needs it
 * 2. wheel-size-api      the only external gateway
 * 3. tyre-size           size grammar, shared by every later module
 * 4. product-types       family detection, needed to scope a query
 * 5. brand               brand terms, logos and archive links
 * 6. dynamic-defaults    archive slugs and SEO defaults
 * 7. product-query       the filter/facet/pair query service
 * 8. vat                 price display and cart pricing
 * 9. product-payload     the JSON product contract
 * 10. side-cart          cart state and its endpoints
 * 11. delivery           delivery method, fees and discounts
 * 12. archive            rewrites, URL parsing and archive filtering
 * 13. dynamic-sections   content-block storage and its admin endpoints
 * 14. store-details      store identity used by email and documents
 * 15. email-order-extras enrichment of the stock WooCommerce emails
 * 16. email              branded contact and order notifications
 * 17. order-receipt-pdf  signed PDF receipt download
 * 18. ajax/*             the read-only catalogue endpoints
 */
$dts_backend_files = array(
	'/inc/backend/env-loader.php',
	'/inc/backend/wheel-size-api.php',
	'/inc/backend/tyre-size.php',
	'/inc/backend/product-types.php',
	'/inc/backend/brand.php',
	'/inc/backend/dynamic-defaults.php',
	'/inc/backend/product-query.php',
	'/inc/backend/vat.php',
	'/inc/backend/product-payload.php',
	'/inc/backend/side-cart.php',
	'/inc/backend/delivery.php',
	'/inc/backend/archive.php',
	'/inc/backend/dynamic-sections.php',
	'/inc/backend/store-details.php',
	'/inc/backend/email-order-extras.php',
	'/inc/backend/email.php',
	'/inc/backend/order-receipt-pdf.php',
	'/inc/backend/ajax/filter-grid.php',
	'/inc/backend/ajax/battery-filter-grid.php',
	'/inc/backend/ajax/vehicle.php',
	'/inc/backend/ajax/flotation.php',
	'/inc/backend/ajax/tyre-size.php',
);

foreach ( $dts_backend_files as $dts_backend_file ) {
	$dts_backend_path = get_theme_file_path( $dts_backend_file );

	if ( is_readable( $dts_backend_path ) ) {
		require_once $dts_backend_path;
	} else {
		// A missing module must not take the storefront down, but it should be
		// impossible to miss in the logs.
		error_log( 'Custom Dubai Tyre Shop: missing backend module ' . $dts_backend_file ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}

unset( $dts_backend_files, $dts_backend_file, $dts_backend_path );
