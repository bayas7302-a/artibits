<?php
/**
 * SEO meta title and description per page, per language.
 *
 * Stored in post meta `_est_seo` = [ lang => [ 'title' => ..., 'desc' => ... ] ]
 * (the source language included). On the front end the values for the
 * current language replace the page's <title> and meta description - through
 * Yoast SEO / Rank Math when one of them is active, otherwise by the plugin.
 * An empty value falls back to the normal title/description.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Seo {

	const META = '_est_seo';

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( __CLASS__, 'document_title' ), 99 );
		add_action( 'wp_head', array( __CLASS__, 'meta_description' ), 2 );

		// Yoast SEO.
		add_filter( 'wpseo_title', array( __CLASS__, 'filter_title' ), 99 );
		add_filter( 'wpseo_opengraph_title', array( __CLASS__, 'filter_title' ), 99 );
		add_filter( 'wpseo_twitter_title', array( __CLASS__, 'filter_title' ), 99 );
		add_filter( 'wpseo_metadesc', array( __CLASS__, 'filter_desc' ), 99 );
		add_filter( 'wpseo_opengraph_desc', array( __CLASS__, 'filter_desc' ), 99 );
		add_filter( 'wpseo_twitter_description', array( __CLASS__, 'filter_desc' ), 99 );
		// Rank Math.
		add_filter( 'rank_math/frontend/title', array( __CLASS__, 'filter_title' ), 99 );
		add_filter( 'rank_math/frontend/description', array( __CLASS__, 'filter_desc' ), 99 );
		add_filter( 'rank_math/opengraph/facebook/og_title', array( __CLASS__, 'filter_title' ), 99 );
		add_filter( 'rank_math/opengraph/facebook/og_description', array( __CLASS__, 'filter_desc' ), 99 );
	}

	public static function seo_plugin_active() {
		return defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
	}

	/* ------------------------------------------------------------------ */

	public static function get( $post_id, $lang ) {
		$all = get_post_meta( $post_id, self::META, true );
		$v   = is_array( $all ) && isset( $all[ $lang ] ) ? $all[ $lang ] : array();
		return array(
			'title' => (string) ( $v['title'] ?? '' ),
			'desc'  => (string) ( $v['desc'] ?? '' ),
		);
	}

	public static function set( $post_id, $lang, $title, $desc ) {
		$all = get_post_meta( $post_id, self::META, true );
		$all = is_array( $all ) ? $all : array();
		$title = sanitize_text_field( $title );
		$desc  = sanitize_textarea_field( $desc );
		if ( '' === $title && '' === $desc ) {
			unset( $all[ $lang ] );
		} else {
			$all[ $lang ] = array(
				'title' => $title,
				'desc'  => $desc,
			);
		}
		if ( $all ) {
			update_post_meta( $post_id, self::META, $all );
		} else {
			delete_post_meta( $post_id, self::META );
		}
	}

	/**
	 * Current value for the page being viewed, or ''.
	 *
	 * @param string $field title|desc.
	 */
	private static function current( $field ) {
		if ( ! is_singular() && ! is_front_page() ) {
			return '';
		}
		$id = is_front_page() && 'page' === get_option( 'show_on_front' ) ? (int) get_option( 'page_on_front' ) : get_queried_object_id();
		return $id ? self::get( $id, EST_Router::current() )[ $field ] : '';
	}

	public static function document_title( $title ) {
		$t = self::current( 'title' );
		return '' !== $t ? $t : $title;
	}

	public static function filter_title( $title ) {
		$t = self::current( 'title' );
		return '' !== $t ? $t : $title;
	}

	public static function filter_desc( $desc ) {
		$d = self::current( 'desc' );
		return '' !== $d ? $d : $desc;
	}

	/** Without an SEO plugin, print the description ourselves. */
	public static function meta_description() {
		if ( self::seo_plugin_active() ) {
			return;
		}
		$d = self::current( 'desc' );
		if ( '' !== $d ) {
			echo '<meta name="description" content="' . esc_attr( $d ) . '" />' . "\n";
		}
	}

	/* ------------------------------------------------------------------
	 * Pages that can have SEO meta, and the defaults shown for the source
	 * language (taken from Yoast / Rank Math when they hold a plain value).
	 * --------------------------------------------------------------- */

	/** [ post ID => title ] of public, published pages/posts (no language copies). */
	public static function pages() {
		$types = array_values( array_diff( get_post_types( array( 'public' => true ) ), array( 'attachment', 'elementor_library', 'e-landing-page' ) ) );
		$ids   = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => array( 'post_type' => 'ASC', 'title' => 'ASC' ),
				'fields'         => 'ids',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ $id ] = get_the_title( $id );
		}
		return $out;
	}

	/** What a search engine sees today for the source language (for reference). */
	public static function source_defaults( $post_id ) {
		$title = (string) get_post_meta( $post_id, '_yoast_wpseo_title', true );
		$desc  = (string) get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		if ( '' === $title ) {
			$title = (string) get_post_meta( $post_id, 'rank_math_title', true );
		}
		if ( '' === $desc ) {
			$desc = (string) get_post_meta( $post_id, 'rank_math_description', true );
		}
		// Template variables (%%title%%, %title%) are not plain text.
		$title = false !== strpos( $title, '%' ) ? '' : $title;
		$desc  = false !== strpos( $desc, '%' ) ? '' : $desc;
		return array(
			'title' => $title,
			'desc'  => $desc,
		);
	}

	/* ------------------------------------------------------------------
	 * Spreadsheet: one "SEO" sheet, row per page and field.
	 *   SEO page | Field | English (en) | Arabic (ar) | ...
	 *   About Us #6 | Meta title | ... | ...
	 * --------------------------------------------------------------- */

	const SHEET = 'SEO';

	public static function build_sheet( array $codes, $include_existing = true ) {
		$langs  = array_merge( array( EST_Settings::default_code() ), $codes );
		$header = array( 'SEO page', 'Field' );
		foreach ( $langs as $code ) {
			$l        = EST_Settings::default_code() === $code ? EST_Settings::default_language() : EST_Settings::language( $code );
			$header[] = $l ? EST_Settings::header_label( $l ) : $code;
		}
		$rows = array( $header );
		foreach ( self::pages() as $id => $title ) {
			foreach ( array( 'title' => 'Meta title', 'desc' => 'Meta description' ) as $field => $label ) {
				$row = array( $title . ' #' . $id, $label );
				foreach ( $langs as $code ) {
					$v = self::get( $id, $code )[ $field ];
					if ( '' === $v && EST_Settings::default_code() === $code ) {
						$v = self::source_defaults( $id )[ $field ];
					}
					$row[] = ( $include_existing || EST_Settings::default_code() === $code ) ? $v : '';
				}
				$rows[] = $row;
			}
		}
		return $rows;
	}

	public static function is_seo_sheet( array $rows ) {
		return isset( $rows[0][0] ) && 0 === stripos( trim( (string) $rows[0][0] ), 'SEO page' );
	}

	/** @return int Number of values saved. */
	public static function import_sheet( array $rows, $overwrite = true ) {
		$header = array_shift( $rows );
		$map    = array();
		foreach ( $header as $col => $cell ) {
			if ( $col < 2 ) {
				continue;
			}
			$code = EST_Settings::code_from_header( $cell );
			if ( $code && EST_Settings::is_live_code( $code ) ) {
				$map[ $col ] = $code;
			}
		}
		$saved = 0;
		foreach ( $rows as $row ) {
			if ( ! preg_match( '/#(\d+)\s*$/', (string) ( $row[0] ?? '' ), $m ) || ! get_post( (int) $m[1] ) ) {
				continue;
			}
			$id    = (int) $m[1];
			$field = false !== stripos( (string) ( $row[1] ?? '' ), 'desc' ) ? 'desc' : 'title';
			foreach ( $map as $col => $code ) {
				$value = trim( (string) ( $row[ $col ] ?? '' ) );
				if ( '' === $value ) {
					continue;
				}
				$cur = self::get( $id, $code );
				if ( ! $overwrite && '' !== $cur[ $field ] ) {
					continue;
				}
				if ( $cur[ $field ] === $value ) {
					continue;
				}
				$cur[ $field ] = $value;
				self::set( $id, $code, $cur['title'], $cur['desc'] );
				$saved++;
			}
		}
		return $saved;
	}
}
