<?php
/**
 * WooCommerce: turn off customer login / registration and hide the account
 * button in the header. Customers can still buy as guests.
 *
 * Paste into the child theme's functions.php (without the "<?php" line) or a
 * Code Snippets plugin. Admins keep logging in at /wp-login.php as usual.
 */

// Shop settings: no accounts, guest checkout on.
add_filter( 'pre_option_woocommerce_enable_myaccount_registration', function () { return 'no'; } );
add_filter( 'pre_option_woocommerce_enable_signup_and_login_from_checkout', function () { return 'no'; } );
add_filter( 'pre_option_woocommerce_enable_checkout_login_reminder', function () { return 'no'; } );
add_filter( 'pre_option_woocommerce_enable_guest_checkout', function () { return 'yes'; } );
add_filter( 'pre_option_users_can_register', '__return_zero' );

// "My account" page (login / register / lost password) -> home page for visitors.
add_action( 'template_redirect', function () {
	if ( function_exists( 'is_account_page' ) && is_account_page() && ! is_user_logged_in() ) {
		wp_safe_redirect( home_url( '/' ) );
		exit;
	}
}, 1 );

// No "Returning customer? Click here to login" on the checkout.
add_action( 'init', function () {
	remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
} );

// Remove "My account" / login links from menus.
add_filter( 'wp_nav_menu_objects', function ( $items ) {
	$account = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'myaccount' ) : 0;
	return array_filter( $items, function ( $item ) use ( $account ) {
		$url = (string) $item->url;
		return ! ( ( $account > 0 && (int) $item->object_id === $account && 'page' === $item->object )
			|| false !== strpos( $url, 'wp-login.php' ) || false !== strpos( $url, 'action=register' ) );
	} );
} );

// Hide account / login buttons in the header and the mobile menu (theme and Elementor widgets).
add_action( 'wp_head', function () {
	$id   = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'myaccount' ) : 0;
	$path = $id > 0 ? basename( trim( (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ), '/' ) ) : 'my-account';
	$a    = 'a[href*="/' . esc_attr( $path ) . '"]';
	echo '<style id="no-account-buttons">'
		. 'header ' . $a . ',.main-header ' . $a . ',.elementor-location-header ' . $a . ','
		. 'header a[href*="wp-login.php"],.main-header a[href*="wp-login.php"],'
		. '.fsh-account,.fsh-drawer ' . $a . ',.fsh-drawer__links ' . $a . ',[class*="fsh-"] ' . $a . ','
		. '.elementor-widget-woocommerce-my-account,.ld-module-login,.lqd-module-login'
		. '{display:none!important}</style>' . "\n";
}, 99 );
