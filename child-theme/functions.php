<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'hello-elementor','hello-elementor-theme-style','hello-elementor-header-footer' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

/* Show "AED" instead of the dirham symbol (د.إ) */
add_filter( 'woocommerce_currency_symbol', function ( $symbol, $currency ) {
	return 'AED' === $currency ? 'AED' : $symbol;
}, 10, 2 );

/* ---------------------------------------------------------------------------
 * Features – one file per feature in the inc/ folder (loaded in this order)
 * ------------------------------------------------------------------------ */
foreach ( array(
	'brand.php',
	'cart.php',
	'checkout-account.php',
	'order-tracking.php',
	'product-links.php',
	'header.php',
	'site-templates.php',
	'shop.php',
	'product-page.php',
	'home.php',
	'events.php',
) as $soharon_file ) {
	require_once get_stylesheet_directory() . '/inc/' . $soharon_file;
}
unset( $soharon_file );

/* Branded WooCommerce emails + order tracking in emails */
if ( file_exists( get_stylesheet_directory() . '/soharon-emails.php' ) ) {
	require_once get_stylesheet_directory() . '/soharon-emails.php';
}
