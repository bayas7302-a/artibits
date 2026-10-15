<?php
/**
 * Disable public login on the website (paste into the child theme's functions.php,
 * or a code-snippets plugin). Without the opening "<?php" line if the file already has one.
 *
 * - Visitors opening /wp-login.php or /wp-admin/ are sent to the home page (no login form).
 * - Admins log in at the secret address: https://your-site.com/wp-login.php?access=CHANGE-ME
 * - Registration is switched off, login/register links and the "Meta" widget are removed.
 * - XML-RPC (a common password-guessing target) and the public user list are disabled.
 * - Application passwords (MCP / REST tools) and password-protected pages keep working.
 *
 * Change CHANGE-ME below to your own secret word and bookmark the login address.
 * Locked out? Remove this code via FTP / the hosting file manager.
 */

define( 'FV_LOGIN_KEY', 'CHANGE-ME' );

// The login page: only with the secret key (remembered for 1 hour in a cookie).
add_action( 'login_init', function () {
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : 'login';

	// Logging out and password-protected pages must keep working.
	if ( in_array( $action, array( 'logout', 'postpass' ), true ) || is_user_logged_in() ) {
		return;
	}
	if ( isset( $_GET['access'] ) && hash_equals( FV_LOGIN_KEY, (string) wp_unslash( $_GET['access'] ) ) ) {
		setcookie( 'fv_login_access', wp_hash( FV_LOGIN_KEY ), time() + HOUR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
		return;
	}
	if ( isset( $_COOKIE['fv_login_access'] ) && hash_equals( wp_hash( FV_LOGIN_KEY ), (string) $_COOKIE['fv_login_access'] ) ) {
		return;
	}
	fv_login_not_found();
}, 1 );

// /wp-admin/ for logged-out visitors: home page instead of the login page.
// admin-ajax.php and admin-post.php stay open (forms and plugins use them).
add_action( 'init', function () {
	$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';
	if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() && 'admin-post.php' !== $script ) {
		fv_login_not_found();
	}
}, 1 );

function fv_login_not_found() {
	nocache_headers();
	wp_safe_redirect( home_url( '/' ) );
	exit;
}

// No sign-ups, no login / register links.
add_filter( 'pre_option_users_can_register', '__return_zero' );
add_filter( 'register', '__return_empty_string' );
add_filter( 'loginout', function ( $link ) {
	return is_user_logged_in() ? $link : '';
} );
add_action( 'widgets_init', function () {
	unregister_widget( 'WP_Widget_Meta' );
} );

// No password guessing through XML-RPC.
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', function () {
	return array();
} );

// Hide user names: /wp-json/wp/v2/users and /?author=1 for visitors.
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
add_action( 'template_redirect', function () {
	if ( ! is_user_logged_in() && ( is_author() || isset( $_GET['author'] ) ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 ); // Before WordPress redirects ?author=1 to /author/admin/.

// Login errors never say whether the user name exists.
add_filter( 'login_errors', function () {
	return __( 'Incorrect login details.' );
} );
