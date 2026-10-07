<?php
/**
 * Theme setup and WordPress support declarations.
 *
 * @package Custom_Dubai_Tyre_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function custom_dubai_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'woocommerce' );
	register_nav_menus(
		array(
			'primary' => esc_html__( 'Primary Menu', 'custom-dubai-tyre-shop' ),
		)
	);
}
add_action( 'after_setup_theme', 'custom_dubai_theme_setup' );

add_action( 'wp_enqueue_scripts', 'custom_dubai_disable_woocommerce_styles', 20 );
function custom_dubai_disable_woocommerce_styles() {
	wp_dequeue_style( 'woocommerce-general' );
	wp_dequeue_style( 'woocommerce-layout' );
	wp_dequeue_style( 'woocommerce-smallscreen' );
	wp_dequeue_style( 'woocommerce_frontend_styles' );
	wp_dequeue_style( 'woocommerce_fancybox_styles' );
	wp_dequeue_style( 'woocommerce_chosen_styles' );
	wp_dequeue_style( 'woocommerce_prettyPhoto_css' );
	wp_deregister_style( 'woocommerce-general' );
	wp_deregister_style( 'woocommerce-layout' );
	wp_deregister_style( 'woocommerce-smallscreen' );
	wp_deregister_style( 'woocommerce_frontend_styles' );
	wp_deregister_style( 'woocommerce_fancybox_styles' );
	wp_deregister_style( 'woocommerce_chosen_styles' );
	wp_deregister_style( 'woocommerce_prettyPhoto_css' );
}
