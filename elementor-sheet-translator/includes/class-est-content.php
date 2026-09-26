<?php
/**
 * Finds the translatable content of the site, grouped into "sources" (one
 * spreadsheet per source): every Elementor page/post/template (headers,
 * footers, popups, sections, theme header/footer post types...), every nav
 * menu, and the general site strings.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Content {

	private static $sources = null;

	/**
	 * @return array[] key => [ key, kind, id, title, label, group ]
	 */
	public static function sources() {
		global $wpdb;
		if ( null !== self::$sources ) {
			return self::$sources;
		}
		EST_Extractor::$extra_keys = self::extra_keys();

		$sources = array();

		$sources['site'] = array(
			'key'   => 'site',
			'kind'  => 'site',
			'id'    => 0,
			'title' => __( 'Site title & tagline', 'est' ),
			'group' => __( 'General', 'est' ),
		);

		$exclude = array( 'revision', 'nav_menu_item', 'attachment', 'customize_changeset', 'oembed_cache', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles' );
		$rows    = $wpdb->get_results(
			"SELECT p.ID, p.post_type, p.post_title
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
			WHERE p.post_status IN ('publish','private','future','draft')
			AND p.post_type NOT IN ('" . implode( "','", array_map( 'esc_sql', $exclude ) ) . "')
			AND m.meta_value NOT IN ('', '[]')
			ORDER BY p.post_type ASC, p.menu_order ASC, p.post_title ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		foreach ( (array) $rows as $row ) {
			$group = self::group_label( (int) $row->ID, $row->post_type );
			if ( false === $group || ! self::is_elementor( (int) $row->ID ) ) {
				continue;
			}
			$title = '' !== trim( $row->post_title ) ? $row->post_title : '#' . $row->ID;
			$key   = 'post-' . $row->ID;

			$sources[ $key ] = array(
				'key'   => $key,
				'kind'  => 'post',
				'id'    => (int) $row->ID,
				'title' => $title,
				'group' => $group,
			);
		}

		// Posts/pages written with the block editor or classic editor (e.g. blog
		// posts and case studies) - their post_content is translated too.
		$public = array_diff( get_post_types( array( 'public' => true ) ), $exclude, array( 'elementor_library' ) );
		if ( $public ) {
			$rows = $wpdb->get_results(
				"SELECT p.ID, p.post_type, p.post_title
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_edit_mode'
				WHERE p.post_status IN ('publish','private','future')
				AND p.post_type IN ('" . implode( "','", array_map( 'esc_sql', $public ) ) . "')
				AND ( m.meta_value IS NULL OR m.meta_value <> 'builder' )
				AND ( p.post_content <> '' OR p.post_title <> '' )
				ORDER BY p.post_type ASC, p.menu_order ASC, p.post_title ASC" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			);
			foreach ( (array) $rows as $row ) {
				$key = 'post-' . $row->ID;
				if ( isset( $sources[ $key ] ) ) {
					continue;
				}
				$obj             = get_post_type_object( $row->post_type );
				$sources[ $key ] = array(
					'key'   => $key,
					'kind'  => 'post',
					'id'    => (int) $row->ID,
					'title' => '' !== trim( $row->post_title ) ? $row->post_title : '#' . $row->ID,
					'group' => $obj ? $obj->labels->singular_name : ucfirst( $row->post_type ),
				);
			}
		}

		foreach ( (array) wp_get_nav_menus() as $menu ) {
			$key             = 'menu-' . $menu->term_id;
			$sources[ $key ] = array(
				'key'   => $key,
				'kind'  => 'menu',
				'id'    => (int) $menu->term_id,
				'title' => $menu->name,
				'group' => __( 'Menu', 'est' ),
			);
		}

		foreach ( $sources as $key => $s ) {
			$sources[ $key ]['label'] = $s['group'] . ' - ' . $s['title'];
		}
		self::$sources = apply_filters( 'est_sources', $sources );
		return self::$sources;
	}

	public static function source( $key ) {
		$all = self::sources();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	private static function group_label( $post_id, $post_type ) {
		if ( 'elementor_library' === $post_type ) {
			$type = (string) get_post_meta( $post_id, '_elementor_template_type', true );
			if ( 'kit' === $type ) {
				return false; // Site settings, no text.
			}
			return '' !== $type ? ucwords( str_replace( array( '-', '_' ), ' ', $type ) ) : __( 'Template', 'est' );
		}
		$obj = get_post_type_object( $post_type );
		return $obj ? $obj->labels->singular_name : ucfirst( $post_type );
	}

	/**
	 * Unique English strings of one source, in page order.
	 *
	 * @return string[]
	 */
	public static function strings( $key ) {
		$source = self::source( $key );
		if ( ! $source ) {
			return array();
		}
		EST_Extractor::$extra_keys = self::extra_keys();
		$out                       = array();

		switch ( $source['kind'] ) {
			case 'site':
				$out[] = get_option( 'blogname' );
				$out[] = get_option( 'blogdescription' );
				break;

			case 'menu':
				foreach ( (array) wp_get_nav_menu_items( $source['id'] ) as $item ) {
					$out[] = $item->title;
					$out[] = $item->attr_title;
					$out[] = $item->description;
				}
				break;

			case 'post':
				$post = get_post( $source['id'] );
				if ( $post ) {
					$out[] = $post->post_title;
					$out[] = $post->post_excerpt;
				}
				if ( self::is_elementor( $source['id'] ) ) {
					$data = get_post_meta( $source['id'], '_elementor_data', true );
					if ( is_string( $data ) ) {
						$data = json_decode( $data, true );
					}
					$out = array_merge( $out, EST_Extractor::extract( is_array( $data ) ? $data : array() ) );
				} elseif ( $post ) {
					$out = array_merge( $out, EST_Html::extract_blocks( $post->post_content ) );
				}
				break;
		}

		$unique = array();
		foreach ( $out as $s ) {
			$s = EST_Text::normalize( (string) $s );
			if ( '' !== $s && preg_match( '/\p{L}/u', $s ) ) {
				$unique[ md5( $s ) ] = $s;
			}
		}
		return array_values( apply_filters( 'est_source_strings', $unique, $source ) );
	}

	/**
	 * Unique image URLs of one source.
	 *
	 * @return string[]
	 */
	public static function images( $key ) {
		$source = self::source( $key );
		if ( ! $source || 'post' !== $source['kind'] ) {
			return array();
		}
		if ( self::is_elementor( $source['id'] ) ) {
			$data = get_post_meta( $source['id'], '_elementor_data', true );
			$data = is_string( $data ) ? json_decode( $data, true ) : $data;
			return EST_Extractor::extract_images( is_array( $data ) ? $data : array() );
		}
		$post = get_post( $source['id'] );
		preg_match_all( '#<img\b[^>]*\ssrc=["\']([^"\']+)#i', $post ? $post->post_content : '', $m );
		return array_values( array_unique( array_filter( $m[1], array( 'EST_Extractor', 'is_image_url' ) ) ) );
	}

	public static function is_elementor( $post_id ) {
		if ( 'elementor_library' === get_post_type( $post_id ) ) {
			return true;
		}
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	public static function extra_keys() {
		$raw = (string) EST_Settings::get( 'extra_keys' );
		return array_values( array_filter( array_map( 'trim', preg_split( '/[\s,]+/', $raw ) ) ) );
	}
}
