<?php
/**
 * Theme header.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$dts_primary_menu = wp_nav_menu(
	array(
		'theme_location' => 'primary',
		'container'      => false,
		'menu_class'     => 'dts-header__menu',
		'fallback_cb'    => false,
		'echo'           => false,
	)
);
$dts_primary_menu = is_string( $dts_primary_menu ) ? preg_replace( '/\s+id=("|\').*?\1/', '', $dts_primary_menu ) : '';
$dts_header_config = array(
	'homeUrl'    => home_url( '/' ),
	'logoUrl'    => get_theme_file_uri( '/assets/images/dubai-tyre-shop-logo.png' ),
	'siteName'   => get_bloginfo( 'name' ),
	'description'=> get_bloginfo( 'description' ),
	'phoneNumbers' => array( '+971 5562 69517', '+971 54420 4322' ),
	'supportNumber' => '800 145',
	'accountUrl' => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' ),
	'cartUrl'    => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' ),
	'social'     => array_filter(
		array(
			'Facebook'  => (string) get_option( 'dts_store_facebook', '' ),
			'Instagram' => (string) get_option( 'dts_store_instagram', '' ),
			'LinkedIn'  => (string) get_option( 'dts_store_linkedin', '' ),
		),
		'esc_url_raw'
	),
);
?>
<div id="dts-header-root" data-header="<?php echo esc_attr( wp_json_encode( $dts_header_config ) ); ?>">
	<div id="dts-primary-navigation-source" hidden><?php echo $dts_primary_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Produced by wp_nav_menu. ?></div>
</div>
