<?php
/**
 * Language URLs (/ar/about-us/), locale, text direction and link rewriting.
 *
 * The default language lives at the site root. For another language the
 * code is removed from REQUEST_URI before WordPress parses the request, so
 * /ar/about-us/ resolves to the normal "about-us" page, rendered in Arabic.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Router {

	private static $current   = null;
	private static $home_path = '';
	private static $request   = '/';
	private static $parsing   = false;

	/**
	 * Runs while plugins load: read the language from the URL.
	 */
	public static function detect() {
		self::$current   = EST_Settings::default_code();
		self::$home_path = rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
		self::$request   = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/'; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return;
		}

		// AJAX calls made from a translated page: take the language from the referer.
		if ( wp_doing_ajax() ) {
			if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$code = self::code_from_path( (string) wp_parse_url( wp_unslash( $_SERVER['HTTP_REFERER'] ), PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				if ( $code ) {
					self::$current = $code;
				}
			}
			return;
		}
		if ( is_admin() ) {
			return;
		}

		$path = (string) wp_parse_url( self::$request, PHP_URL_PATH );
		$code = self::code_from_path( $path );
		if ( ! $code ) {
			return;
		}
		self::$current = $code;

		$rel   = substr( $path, strlen( self::$home_path ) );
		$rest  = substr( $rel, strlen( $code ) + 1 );
		$rest  = ( '' === $rest || false === $rest ) ? '/' : $rest;
		$query = (string) wp_parse_url( self::$request, PHP_URL_QUERY );

		self::$request          = self::$home_path . $rest . ( '' !== $query ? '?' . $query : '' );
		$_SERVER['REQUEST_URI'] = self::$request;
	}

	private static function code_from_path( $path ) {
		if ( '' !== self::$home_path ) {
			if ( 0 !== strpos( $path, self::$home_path . '/' ) && $path !== self::$home_path ) {
				return '';
			}
			$path = substr( $path, strlen( self::$home_path ) );
		}
		if ( ! preg_match( '#^/([a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,4})?)(?:/|$)#', (string) $path, $m ) ) {
			return '';
		}
		$code = strtolower( $m[1] );
		$lang = EST_Settings::language( $code );
		return ( $lang && ! empty( $lang['enabled'] ) && EST_Settings::default_code() !== $code ) ? $code : '';
	}

	public static function init() {
		if ( self::is_default() ) {
			return;
		}
		add_filter( 'locale', array( __CLASS__, 'filter_locale' ) );
		foreach ( array( 'setup_theme', 'after_setup_theme', 'init', 'wp', 'change_locale' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'apply_direction' ), 0 );
		}
		add_filter( 'language_attributes', array( __CLASS__, 'language_attributes' ), 99 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'home_url', array( __CLASS__, 'filter_home_url' ), 20, 4 );
		// WordPress reads home_url() while matching the request to a page; it must
		// see the real home (e.g. /off-24) there, or every page path 404s.
		add_filter( 'do_parse_request', array( __CLASS__, 'parse_start' ), 999 );
		add_action( 'parse_request', array( __CLASS__, 'parse_end' ), 0 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'redirect_canonical' ), 20, 2 );
		add_filter( 'nav_menu_link_attributes', array( __CLASS__, 'menu_link' ), 20 );
	}

	/* ------------------------------------------------------------------ */

	public static function current() {
		return self::$current;
	}

	public static function is_default() {
		return self::$current === EST_Settings::default_code();
	}

	public static function current_language() {
		return self::is_default() ? EST_Settings::default_language() : EST_Settings::language( self::$current );
	}

	public static function direction() {
		$lang = self::current_language();
		return ( $lang && 'rtl' === $lang['dir'] ) ? 'rtl' : 'ltr';
	}

	/** The current page URL without any language prefix. */
	public static function current_url_unprefixed() {
		$home = wp_parse_url( get_option( 'home' ) );
		$host = ( is_ssl() ? 'https' : ( $home['scheme'] ?? 'http' ) ) . '://' . ( $home['host'] ?? '' ) . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );
		return $host . self::$request;
	}

	/**
	 * Rewrite an internal URL so it points at $code. External URLs, admin,
	 * REST, assets and files are returned untouched.
	 */
	public static function localize_url( $url, $code ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return $url;
		}
		$p = wp_parse_url( $url );
		if ( false === $p ) {
			return $url;
		}
		if ( isset( $p['scheme'] ) && ! in_array( strtolower( $p['scheme'] ), array( 'http', 'https' ), true ) ) {
			return $url;
		}
		if ( isset( $p['host'] ) ) {
			$home_host = (string) wp_parse_url( get_option( 'home' ), PHP_URL_HOST );
			if ( 0 !== strcasecmp( $p['host'], $home_host ) ) {
				return $url;
			}
		} elseif ( ! isset( $p['path'] ) || '' === $p['path'] || '/' !== $p['path'][0] ) {
			return $url; // Anchors, "?query" and relative paths.
		}

		$path = isset( $p['path'] ) && '' !== $p['path'] ? $p['path'] : '/';
		if ( '' !== self::$home_path ) {
			if ( 0 !== strpos( $path . '/', self::$home_path . '/' ) ) {
				return $url;
			}
			$path = substr( $path, strlen( self::$home_path ) );
			$path = '' === $path ? '/' : $path;
		}
		if ( preg_match( '#^/(wp-admin|wp-content|wp-includes|wp-json|wp-login\.php|xmlrpc\.php|feed/)|\.[a-z0-9]{2,5}$#i', $path ) && ! preg_match( '#\.html?$#i', $path ) ) {
			return $url;
		}

		// Remove an existing language prefix, then add the wanted one.
		if ( preg_match( '#^/([a-z]{2,3}(?:-[a-z0-9]{2,4})?)(/|$)#i', $path, $m ) && EST_Settings::language( strtolower( $m[1] ) ) ) {
			$path = substr( $path, strlen( $m[1] ) + 1 );
			$path = '' === $path ? '/' : $path;
		}
		if ( EST_Settings::default_code() !== $code ) {
			$path = '/' . $code . ( '/' === $path ? '/' : $path );
		}

		$out = '';
		if ( isset( $p['host'] ) ) {
			$out = ( isset( $p['scheme'] ) ? $p['scheme'] . ':' : '' ) . '//' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
		}
		$out .= self::$home_path . $path;
		$out .= isset( $p['query'] ) ? '?' . $p['query'] : '';
		$out .= isset( $p['fragment'] ) ? '#' . $p['fragment'] : '';
		return $out;
	}

	/* ------------------------------------------------------------------ */

	public static function filter_locale( $locale ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $locale;
		}
		$lang = self::current_language();
		return ( $lang && ! empty( $lang['locale'] ) ) ? $lang['locale'] : $locale;
	}

	public static function apply_direction() {
		global $wp_locale;
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( $wp_locale instanceof WP_Locale ) {
			$wp_locale->text_direction = self::direction();
		}
	}

	public static function language_attributes( $output ) {
		$lang = self::current_language();
		$tag  = str_replace( '_', '-', ! empty( $lang['locale'] ) ? $lang['locale'] : self::$current );
		$out  = preg_replace( '/\s*(dir|lang)="[^"]*"/i', '', (string) $output );
		return trim( 'dir="' . self::direction() . '" lang="' . esc_attr( $tag ) . '" ' . $out );
	}

	public static function body_class( $classes ) {
		$classes[] = 'est-lang-' . sanitize_html_class( self::$current );
		$classes[] = 'est-dir-' . self::direction();
		if ( 'rtl' === self::direction() && ! in_array( 'rtl', $classes, true ) ) {
			$classes[] = 'rtl';
		}
		return $classes;
	}

	public static function parse_start( $do ) {
		self::$parsing = (bool) $do;
		return $do;
	}

	public static function parse_end() {
		self::$parsing = false;
	}

	public static function filter_home_url( $url, $path, $orig_scheme, $blog_id = null ) {
		if ( self::$parsing || 'rest' === $orig_scheme || ( is_admin() && ! wp_doing_ajax() ) ) {
			return $url;
		}
		return self::localize_url( $url, self::$current );
	}

	public static function redirect_canonical( $redirect_url, $requested_url ) {
		if ( ! $redirect_url ) {
			return $redirect_url;
		}
		$redirect_url = self::localize_url( $redirect_url, self::$current );
		// $requested_url was built from the stripped REQUEST_URI; compare like for like.
		if ( self::localize_url( $requested_url, self::$current ) === $redirect_url ) {
			return false;
		}
		return $redirect_url;
	}

	public static function menu_link( $atts ) {
		if ( ! empty( $atts['href'] ) && EST_Settings::get( 'localize_links' ) ) {
			$atts['href'] = self::localize_url( $atts['href'], self::$current );
		}
		return $atts;
	}
}
