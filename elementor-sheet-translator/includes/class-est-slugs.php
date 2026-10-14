<?php
/**
 * Translated URLs: /es/sobre-nosotros/ instead of /es/about-us/.
 *
 * Optional (Languages & Settings > "Translate page URLs"). The slug of each
 * page / post comes from its translated title in the sheets; pages whose title
 * is not translated keep their original slug. The address map is stored in an
 * option so it can be read while plugins load, before WordPress parses the
 * request, and rebuilt whenever content, translations or settings change.
 *
 * Map format: [ code => [ "about-us" => "sobre-nosotros", "services/web" => "servicios/diseno-web" ] ]
 * (paths relative to the home URL, without slashes at either end).
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Slugs {

	const OPTION = 'est_slug_map';

	private static $map     = null;
	private static $reverse = array();

	public static function enabled() {
		return (bool) EST_Settings::get( 'translate_slugs' );
	}

	public static function init() {
		add_action( 'est_translations_updated', array( __CLASS__, 'rebuild' ) );
		add_action( 'save_post', array( __CLASS__, 'schedule' ) );
		add_action( 'deleted_post', array( __CLASS__, 'schedule' ) );
		add_action( 'trashed_post', array( __CLASS__, 'schedule' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'maybe_build' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_untranslated' ), 2 );
	}

	/** Rebuild at the end of the request (once, however many posts were saved). */
	public static function schedule( $post_id = 0 ) {
		if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
			return;
		}
		if ( ! has_action( 'shutdown', array( __CLASS__, 'rebuild' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'rebuild' ) );
		}
	}

	/** First run after enabling / updating the plugin. */
	public static function maybe_build() {
		if ( self::enabled() && false === get_option( self::OPTION ) ) {
			self::rebuild();
		}
	}

	public static function rebuild() {
		if ( ! self::enabled() ) {
			delete_option( self::OPTION );
			self::$map = array();
			return;
		}
		// Original addresses: home_url() must not add language prefixes or translated slugs here.
		$had = has_filter( 'home_url', array( 'EST_Router', 'filter_home_url' ) );
		if ( $had ) {
			remove_filter( 'home_url', array( 'EST_Router', 'filter_home_url' ), 20 );
		}
		self::$map = array();

		$types = array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'elementor_library', 'e-landing-page', 'e-floating-buttons' ) );
		$posts = get_posts(
			array(
				'post_type'        => array_values( $types ),
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'orderby'          => 'menu_order date',
				'order'            => 'ASC',
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'key'     => '_est_source',
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		$front = (int) get_option( 'page_on_front' );
		$home  = rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
		$map   = array();

		foreach ( EST_Settings::languages() as $code => $lang ) {
			if ( $code === EST_Settings::default_code() ) {
				continue;
			}
			$used = array();
			foreach ( $posts as $post ) {
				if ( $post->ID === $front || '' === $post->post_name ) {
					continue;
				}
				$path = (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH );
				if ( '' !== $home ) {
					if ( 0 !== strpos( $path, $home . '/' ) ) {
						continue;
					}
					$path = substr( $path, strlen( $home ) );
				}
				$orig = self::clean( $path );
				if ( '' === $orig || false !== strpos( $orig, '?' ) ) {
					continue;
				}
				// The post and its parent pages, from the last address segment backwards.
				$segs  = explode( '/', $orig );
				$chain = array_merge( array( $post ), array_map( 'get_post', array_reverse( $post->ancestors ? $post->ancestors : array() ) ) );
				$i     = count( $segs ) - 1;
				foreach ( $chain as $p ) {
					if ( ! $p || $i < 0 || self::norm_seg( $p->post_name ) !== $segs[ $i ] ) {
						break;
					}
					$slug = self::translated_slug( $p, $code );
					if ( '' !== $slug ) {
						$segs[ $i ] = $slug;
					}
					$i--;
				}
				$new = implode( '/', $segs );
				// Two pages with the same translated title: the second keeps its address.
				if ( $new !== $orig && ! isset( $used[ $new ] ) ) {
					$used[ $new ]          = true;
					$map[ $code ][ $orig ] = $new;
				}
			}
		}
		if ( $had ) {
			add_filter( 'home_url', array( 'EST_Router', 'filter_home_url' ), 20, 4 );
		}
		self::$map     = $map;
		self::$reverse = array();
		update_option( self::OPTION, $map, true );
	}

	private static function translated_slug( $post, $code ) {
		$title = EST_Text::normalize( $post->post_title );
		if ( '' === $title ) {
			return '';
		}
		$t = EST_Store::get( $code, $title );
		if ( null === $t || '' === trim( $t ) ) {
			return '';
		}
		return self::norm_seg( sanitize_title( wp_strip_all_tags( $t ) ) );
	}

	/** One address segment, in a single form whether it arrives encoded or not. */
	private static function norm_seg( $s ) {
		return strtolower( rawurlencode( rawurldecode( (string) $s ) ) );
	}

	private static function clean( $path ) {
		$segs = array_filter( explode( '/', trim( (string) $path, '/' ) ), 'strlen' );
		return implode( '/', array_map( array( __CLASS__, 'norm_seg' ), $segs ) );
	}

	private static function map( $code ) {
		if ( null === self::$map ) {
			$m         = self::enabled() ? get_option( self::OPTION, array() ) : array();
			self::$map = is_array( $m ) ? $m : array();
		}
		return self::$map[ $code ] ?? array();
	}

	/**
	 * Replace the longest leading part of $path found in $map, so pagination
	 * and child addresses (about-us/page/2/) follow too.
	 */
	private static function swap( $path, array $map ) {
		if ( ! $map || '/' === $path || '' === $path ) {
			return null;
		}
		$trail = '/' === substr( $path, -1 );
		$segs  = explode( '/', self::clean( $path ) );
		for ( $n = count( $segs ); $n > 0; $n-- ) {
			$head = implode( '/', array_slice( $segs, 0, $n ) );
			if ( isset( $map[ $head ] ) ) {
				$tail = array_slice( $segs, $n );
				return '/' . $map[ $head ] . ( $tail ? '/' . implode( '/', $tail ) : '' ) . ( $trail ? '/' : '' );
			}
		}
		return null;
	}

	/** "/about-us/" -> "/sobre-nosotros/" for $code, or null when not translated. */
	public static function to_translated( $path, $code ) {
		return self::swap( $path, self::map( $code ) );
	}

	/** "/sobre-nosotros/" -> "/about-us/" for $code, or null. */
	public static function to_original( $path, $code ) {
		if ( ! isset( self::$reverse[ $code ] ) ) {
			self::$reverse[ $code ] = array_flip( self::map( $code ) );
		}
		return self::swap( $path, self::$reverse[ $code ] );
	}

	/** /es/about-us/ (original slug in a translated language) -> 301 to /es/sobre-nosotros/. */
	public static function redirect_untranslated() {
		if ( ! self::enabled() || EST_Router::is_default() || is_admin() || is_preview() ) {
			return;
		}
		$path = (string) wp_parse_url( EST_Router::requested_path(), PHP_URL_PATH );
		$home = rtrim( (string) wp_parse_url( get_option( 'home' ), PHP_URL_PATH ), '/' );
		if ( '' !== $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		if ( EST_Router::was_translated_slug() || null === self::to_translated( $path, EST_Router::current() ) ) {
			return;
		}
		wp_safe_redirect( EST_Router::localize_url( EST_Router::current_url_unprefixed(), EST_Router::current() ), 301 );
		exit;
	}
}
