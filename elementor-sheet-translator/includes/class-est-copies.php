<?php
/**
 * Language copies: a separate Elementor document per language for pages,
 * posts and templates (headers, footers, popups) that need their own design.
 *
 * A copy is a normal post of the same type with meta `_est_source` (the
 * original's ID) and `_est_lang` (the language code). On the front end,
 * Elementor is told to load the copy instead of the original wherever the
 * original would render, so URLs, menus and theme/header assignments stay
 * on the original. Copies have no public URL of their own.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Copies {

	/** Meta never copied to a language version. */
	const SKIP_META = array( '_edit_lock', '_edit_last', '_elementor_css', '_elementor_element_cache', '_elementor_conditions', '_elementor_screenshot', '_elementor_page_assets', '_wp_old_slug', '_est_source', '_est_lang', '_wp_trash_meta_status', '_wp_trash_meta_time' );

	private static $map = null;

	public static function init() {
		add_filter( 'elementor/documents/get/post_id', array( __CLASS__, 'swap_document' ) );
		add_filter( 'the_title', array( __CLASS__, 'copy_title' ), 15, 2 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_copy_css' ), 30 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_copy_urls' ), 1 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_from_queries' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'hide_copies' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'trash_copies' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'delete_copies' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'admin_bar' ), 90 );
		foreach ( array( 'page_link', 'post_link', 'post_type_link' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'copy_permalink' ), 20, 2 );
		}
		add_action( 'elementor/editor/footer', array( __CLASS__, 'editor_bar' ) );
	}

	/* ------------------------------------------------------------------
	 * Lookup
	 * --------------------------------------------------------------- */

	/** source ID => [ lang => [ id, status ] ] for every copy. */
	private static function map() {
		global $wpdb;
		if ( null === self::$map ) {
			self::$map = array();
			$rows      = $wpdb->get_results(
				"SELECT s.post_id AS id, s.meta_value AS source, l.meta_value AS lang, p.post_status AS status
				FROM {$wpdb->postmeta} s
				INNER JOIN {$wpdb->postmeta} l ON l.post_id = s.post_id AND l.meta_key = '_est_lang'
				INNER JOIN {$wpdb->posts} p ON p.ID = s.post_id
				WHERE s.meta_key = '_est_source' AND p.post_status NOT IN ('trash','auto-draft')"
			);
			foreach ( (array) $rows as $r ) {
				self::$map[ (int) $r->source ][ $r->lang ] = array( (int) $r->id, $r->status );
			}
		}
		return self::$map;
	}

	public static function flush() {
		self::$map = null;
	}

	/** Copy ID of $source for $lang (any status unless $published_only), or 0. */
	public static function copy_of( $source, $lang, $published_only = false ) {
		$map = self::map();
		$c   = $map[ (int) $source ][ $lang ] ?? null;
		if ( ! $c || ( $published_only && 'publish' !== $c[1] ) ) {
			return 0;
		}
		return $c[0];
	}

	/** [ lang => copy ID ] of a source. */
	public static function copies_of( $source ) {
		$out = array();
		foreach ( self::map()[ (int) $source ] ?? array() as $lang => $c ) {
			$out[ $lang ] = $c[0];
		}
		return $out;
	}

	public static function is_copy( $post_id ) {
		return (bool) get_post_meta( $post_id, '_est_source', true );
	}

	/** On the front end in a translated language, outside the editor/preview. */
	private static function active() {
		if ( is_admin() || EST_Router::is_default() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview_id'] ) || isset( $_GET['preview'] ) ) {
			return false;
		}
		return ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );
	}

	/* ------------------------------------------------------------------
	 * Front end
	 * --------------------------------------------------------------- */

	public static function swap_document( $post_id ) {
		if ( ! self::active() ) {
			return $post_id;
		}
		$copy = self::copy_of( $post_id, EST_Router::current(), true );
		return $copy ? $copy : $post_id;
	}

	public static function copy_title( $title, $post_id = 0 ) {
		if ( ! $post_id || ! self::active() ) {
			return $title;
		}
		$copy = self::copy_of( $post_id, EST_Router::current(), true );
		return $copy ? get_post_field( 'post_title', $copy, 'raw' ) : $title;
	}

	/** Load the copy's Elementor CSS in <head> for the page being viewed. */
	public static function enqueue_copy_css() {
		if ( ! self::active() || ! is_singular() || ! class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			return;
		}
		$copy = self::copy_of( get_queried_object_id(), EST_Router::current(), true );
		if ( $copy ) {
			\Elementor\Core\Files\CSS\Post::create( $copy )->enqueue();
		}
	}

	/** A copy has no URL of its own: send visitors to the original in that language. */
	public static function redirect_copy_urls() {
		if ( ! is_singular() || is_preview() || isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$id     = get_queried_object_id();
		$source = (int) get_post_meta( $id, '_est_source', true );
		if ( $source && get_post( $source ) ) {
			$lang = (string) get_post_meta( $id, '_est_lang', true );
			wp_safe_redirect( EST_Router::localize_url( get_permalink( $source ), $lang ), 301 );
			exit;
		}
	}

	/**
	 * A copy's own address carries its language prefix, so Elementor's editor
	 * preview renders it in that language and direction (RTL for Arabic).
	 */
	public static function copy_permalink( $link, $post ) {
		$id   = is_object( $post ) ? $post->ID : (int) $post;
		$lang = $id ? (string) get_post_meta( $id, '_est_lang', true ) : '';
		return $lang ? EST_Router::localize_url( $link, $lang ) : $link;
	}

	public static function exclude_from_queries( $args ) {
		$args['meta_query']   = (array) ( $args['meta_query'] ?? array() );
		$args['meta_query'][] = array(
			'key'     => '_est_source',
			'compare' => 'NOT EXISTS',
		);
		return $args;
	}

	/** Keep copies out of front-end listings and (unless asked) admin lists. */
	public static function hide_copies( $query ) {
		if ( $query->get( 'est_include_copies' ) || ( $query->is_singular() && $query->is_main_query() ) ) {
			return;
		}
		// Only listings of content (blog, archives, search, widgets, admin lists),
		// never lookups by ID/slug or menu/template/attachment queries.
		foreach ( array( 'p', 'page_id', 'post__in', 'name', 'pagename', 'attachment_id' ) as $by_id ) {
			if ( $query->get( $by_id ) ) {
				return;
			}
		}
		$types = (array) ( $query->get( 'post_type' ) ? $query->get( 'post_type' ) : 'post' );
		if ( array_intersect( $types, array( 'nav_menu_item', 'attachment', 'revision', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles', 'customize_changeset', 'wpcf7_contact_form' ) ) ) {
			return;
		}
		if ( is_admin() ) {
			global $pagenow;
			if ( 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
				return;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! empty( $_GET['est_copies'] ) ) {
				$query->set( 'meta_query', array( array( 'key' => '_est_source', 'compare' => 'EXISTS' ) ) );
				return;
			}
		}
		$mq   = (array) $query->get( 'meta_query' );
		$mq[] = array(
			'key'     => '_est_source',
			'compare' => 'NOT EXISTS',
		);
		$query->set( 'meta_query', $mq );
	}

	public static function trash_copies( $post_id ) {
		foreach ( self::copies_of( $post_id ) as $copy ) {
			wp_trash_post( $copy );
		}
	}

	public static function delete_copies( $post_id ) {
		foreach ( self::copies_of( $post_id ) as $copy ) {
			wp_delete_post( $copy, true );
		}
	}

	/* ------------------------------------------------------------------
	 * Creating a copy
	 * --------------------------------------------------------------- */

	/**
	 * Duplicate $source for $lang with the existing sheet translations
	 * already applied. Returns the copy ID (existing one if already made).
	 */
	public static function create( $source, $lang ) {
		$post = get_post( $source );
		if ( ! $post || ! EST_Settings::language( $lang ) || self::is_copy( $source ) ) {
			return new WP_Error( 'est_copy', __( 'This item or language cannot be copied.', 'est' ) );
		}
		$existing = self::copy_of( $source, $lang );
		if ( $existing ) {
			return $existing;
		}

		$dict   = EST_Store::dictionary( $lang );
		$lookup = function ( $text ) use ( $dict ) {
			$h = EST_Text::hash( $text );
			return isset( $dict[ $h ] ) ? $dict[ $h ] : null;
		};
		$images = EST_Store::images( $lang );

		$title     = $lookup( $post->post_title );
		$copy_id   = wp_insert_post(
			wp_slash(
				array(
					'post_type'    => $post->post_type,
					'post_status'  => 'publish' === $post->post_status ? 'publish' : $post->post_status,
					'post_title'   => $title ? $title : $post->post_title,
					'post_name'    => $post->post_name . '-' . $lang,
					'post_content' => $post->post_content,
					'post_excerpt' => $post->post_excerpt,
					'post_parent'  => $post->post_parent,
					'menu_order'   => $post->menu_order,
					'post_author'  => get_current_user_id() ? get_current_user_id() : $post->post_author,
				)
			),
			true
		);
		if ( is_wp_error( $copy_id ) ) {
			return $copy_id;
		}

		foreach ( get_post_meta( $source ) as $key => $values ) {
			if ( in_array( $key, self::SKIP_META, true ) ) {
				continue;
			}
			foreach ( $values as $value ) {
				$value = maybe_unserialize( $value );
				if ( '_elementor_data' === $key ) {
					$data = is_string( $value ) ? json_decode( $value, true ) : $value;
					if ( is_array( $data ) ) {
						$data  = EST_Extractor::translate( $data, $lookup );
						$data  = $images ? EST_Extractor::translate_images(
							$data,
							function ( $url ) use ( $images ) {
								return $images[ $url ] ?? null;
							}
						) : $data;
						$value = wp_json_encode( $data );
					}
				}
				add_post_meta( $copy_id, $key, wp_slash( $value ) );
			}
		}
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			wp_set_object_terms( $copy_id, wp_get_object_terms( $source, $tax, array( 'fields' => 'ids' ) ), $tax );
		}
		update_post_meta( $copy_id, '_est_source', $source );
		update_post_meta( $copy_id, '_est_lang', $lang );
		self::flush();
		return $copy_id;
	}

	public static function editor_url( $post_id ) {
		return admin_url( 'post.php?post=' . (int) $post_id . '&action=elementor' );
	}

	public static function create_url( $source, $lang ) {
		return wp_nonce_url( admin_url( 'admin-post.php?action=est_create_copy&post=' . (int) $source . '&lang=' . rawurlencode( $lang ) ), 'est_create_copy' );
	}

	/* ------------------------------------------------------------------
	 * Language bars (admin bar on the front end, Elementor editor)
	 * --------------------------------------------------------------- */

	/** Links for the original + each language: [ code => [ label, url, state ] ]. */
	public static function language_links( $post_id ) {
		$source = (int) get_post_meta( $post_id, '_est_source', true );
		$source = $source ? $source : (int) $post_id;
		$out    = array(
			EST_Settings::default_code() => array( strtoupper( EST_Settings::default_code() ), self::editor_url( $source ), $source === (int) $post_id ? 'current' : 'edit' ),
		);
		foreach ( EST_Settings::languages() as $code => $l ) {
			$copy         = self::copy_of( $source, $code );
			$out[ $code ] = $copy
				? array( strtoupper( $code ), self::editor_url( $copy ), $copy === (int) $post_id ? 'current' : 'edit' )
				: array( '+ ' . strtoupper( $code ), self::create_url( $source, $code ), 'create' );
		}
		return $out;
	}

	public static function admin_bar( $bar ) {
		if ( is_admin() || ! is_singular() || ! current_user_can( 'edit_post', get_queried_object_id() ) ) {
			return;
		}
		$lang = EST_Router::current();
		if ( EST_Router::is_default() ) {
			return;
		}
		$id   = get_queried_object_id();
		$copy = self::copy_of( $id, $lang );
		$bar->add_node(
			array(
				'id'    => 'est-copy',
				'title' => $copy
					? sprintf( /* translators: %s: language */ __( 'Edit %s version', 'est' ), strtoupper( $lang ) )
					: sprintf( /* translators: %s: language */ __( 'Create %s version', 'est' ), strtoupper( $lang ) ),
				'href'  => $copy ? self::editor_url( $copy ) : self::create_url( $id, $lang ),
			)
		);
	}

	/** Floating language bar inside the Elementor editor. */
	public static function editor_bar() {
		$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $post_id || ! EST_Settings::languages() ) {
			return;
		}
		echo '<div id="est-editor-langs" style="position:fixed;bottom:12px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;gap:4px;padding:4px;background:#1f2124;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.3);font:600 12px/1 sans-serif">';
		echo '<span style="color:#9da5ae;padding:8px 6px">' . esc_html__( 'Language', 'est' ) . '</span>';
		foreach ( self::language_links( $post_id ) as $code => $l ) {
			$style = 'current' === $l[2] ? 'background:#93003c;color:#fff' : ( 'create' === $l[2] ? 'color:#9da5ae;border:1px dashed #555' : 'color:#fff;background:#3a3f45' );
			$title = 'create' === $l[2] ? __( 'Create a separate version for this language', 'est' ) : __( 'Edit this language version', 'est' );
			printf(
				'<a href="%s" title="%s" style="padding:8px 12px;border-radius:5px;text-decoration:none;%s">%s</a>',
				esc_url( $l[1] ),
				esc_attr( $title ),
				esc_attr( $style ),
				esc_html( $l[0] )
			);
		}
		echo '</div>';
	}
}
