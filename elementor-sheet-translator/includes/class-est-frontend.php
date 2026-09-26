<?php
/**
 * Applies translations on the front end.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Frontend {

	private static $dict = null;

	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'head' ), 5 );

		if ( EST_Router::is_default() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}

		// Elementor content: pages, posts, headers, footers, popups, templates.
		add_filter( 'elementor/frontend/builder_content_data', array( __CLASS__, 'elementor_data' ), 20, 2 );

		// Elementor's element cache stores rendered HTML per post; never read or
		// write it for a translated page, otherwise languages would mix.
		add_filter( 'elementor/element/should_render_shortcode', '__return_false', 99 );
		add_filter( 'pre_option_elementor_element_cache_ttl', array( __CLASS__, 'disable_element_cache' ) );

		// Block-editor / classic content (blog posts, case studies...). Runs
		// before do_blocks / wpautop / wptexturize so it sees the saved HTML.
		add_filter( 'the_content', array( __CLASS__, 'post_content' ), 8 );

		// Core strings.
		add_filter( 'the_title', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'single_post_title', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'widget_title', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'option_blogname', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'option_blogdescription', array( __CLASS__, 'translate' ), 20 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );
		add_filter( 'wp_nav_menu_objects', array( __CLASS__, 'menu_items' ), 20 );

		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 0 );
	}

	/* ------------------------------------------------------------------ */

	private static function dict() {
		if ( null === self::$dict ) {
			self::$dict = EST_Store::dictionary( EST_Router::current() );
		}
		return self::$dict;
	}

	/**
	 * Translation for an English string, or null.
	 */
	public static function lookup( $text ) {
		if ( ! is_string( $text ) || '' === $text ) {
			return null;
		}
		$dict = self::dict();
		if ( ! $dict ) {
			return null;
		}
		$hash = EST_Text::hash( $text );
		return isset( $dict[ $hash ] ) ? $dict[ $hash ] : null;
	}

	private static $images = null;

	/** Replaced images for the current language: original URL => new URL. */
	public static function images() {
		if ( null === self::$images ) {
			self::$images = EST_Store::images( EST_Router::current() );
		}
		return self::$images;
	}

	public static function image( $url ) {
		$map = self::images();
		return $map[ $url ] ?? null;
	}

	public static function translate( $text ) {
		$t = self::lookup( $text );
		return null === $t ? $text : $t;
	}

	public static function elementor_data( $data ) {
		if ( ! is_array( $data ) || ( ! self::dict() && ! self::images() ) ) {
			return $data;
		}
		EST_Extractor::$extra_keys = EST_Content::extra_keys();
		$data = EST_Extractor::translate( $data, array( __CLASS__, 'lookup' ) );
		return self::images() ? EST_Extractor::translate_images( $data, array( __CLASS__, 'image' ) ) : $data;
	}

	public static function post_content( $content ) {
		return self::dict() ? EST_Html::translate_blocks( $content, array( __CLASS__, 'lookup' ) ) : $content;
	}

	public static function disable_element_cache() {
		return 'disable';
	}

	public static function title_parts( $parts ) {
		foreach ( array( 'title', 'site', 'tagline' ) as $k ) {
			if ( isset( $parts[ $k ] ) ) {
				$parts[ $k ] = self::translate( $parts[ $k ] );
			}
		}
		return $parts;
	}

	public static function menu_items( $items ) {
		foreach ( (array) $items as $item ) {
			$item->title = self::translate( $item->title );
			if ( ! empty( $item->attr_title ) ) {
				$item->attr_title = self::translate( $item->attr_title );
			}
			if ( ! empty( $item->description ) ) {
				$item->description = self::translate( $item->description );
			}
		}
		return $items;
	}

	/* ------------------------------------------------------------------ */

	public static function start_buffer() {
		if ( is_feed() || is_robots() || is_trackback() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! EST_Settings::get( 'html_fallback' ) && ! EST_Settings::get( 'localize_links' ) ) {
			return;
		}
		ob_start( array( __CLASS__, 'end_buffer' ) );
	}

	public static function end_buffer( $html ) {
		if ( ! is_string( $html ) || false === stripos( substr( $html, 0, 2000 ), '<html' ) ) {
			return $html; // Not an HTML document (JSON, XML, partial output...).
		}
		$lookup = EST_Settings::get( 'html_fallback' ) && self::dict() ? array( __CLASS__, 'lookup' ) : null;
		$code   = EST_Router::current();
		$link   = EST_Settings::get( 'localize_links' )
			? function ( $url ) use ( $code ) {
				return EST_Router::localize_url( $url, $code );
			}
			: null;
		$html = EST_Html::process( $html, $lookup, $link );

		// Replaced images anywhere else in the page (block content, inline styles, JSON settings).
		$swap = array();
		foreach ( self::images() as $from => $to ) {
			$swap[ $from ]                          = $to;
			$swap[ str_replace( '/', '\\/', $from ) ] = str_replace( '/', '\\/', $to );
		}
		if ( ! $swap ) {
			return $html;
		}
		$html = strtr( $html, $swap );

		// Background images are written into Elementor's per-page CSS files;
		// inline a copy of any such file that uses a replaced image.
		$uploads = wp_get_upload_dir();
		$result  = preg_replace_callback(
			'#<link\b[^>]*href=["\']([^"\']*/elementor/css/(?:post|loop)-\d+\.css)(?:\?[^"\']*)?["\'][^>]*>#i',
			function ( $m ) use ( $swap, $uploads ) {
				$file = str_replace( $uploads['baseurl'], $uploads['basedir'], $m[1] );
				if ( 0 !== strpos( $file, $uploads['basedir'] ) || ! is_readable( $file ) ) {
					return $m[0];
				}
				$css = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$new = strtr( $css, $swap );
				return $new === $css ? $m[0] : '<style>' . $new . '</style>';
			},
			$html
		);
		return null === $result ? $html : $result;
	}

	/* ------------------------------------------------------------------ */

	/**
	 * hreflang alternates and per-language CSS.
	 */
	public static function head() {
		if ( EST_Settings::get( 'hreflang' ) && ! is_404() && ! is_search() ) {
			$url = EST_Router::current_url_unprefixed();
			foreach ( EST_Settings::switcher_languages() as $code => $lang ) {
				$tag = ! empty( $lang['locale'] ) ? str_replace( '_', '-', $lang['locale'] ) : $code;
				printf( '<link rel="alternate" hreflang="%s" href="%s" />' . "\n", esc_attr( $tag ), esc_url( EST_Router::localize_url( $url, $code ) ) );
			}
			printf( '<link rel="alternate" hreflang="x-default" href="%s" />' . "\n", esc_url( EST_Router::localize_url( $url, EST_Settings::default_code() ) ) );
		}

		$lang = EST_Router::current_language();
		if ( ! EST_Router::is_default() && ! empty( $lang['css'] ) ) {
			echo '<style id="est-language-css">' . wp_strip_all_tags( $lang['css'] ) . "</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
