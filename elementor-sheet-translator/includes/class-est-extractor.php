<?php
/**
 * Walks Elementor element data (the decoded `_elementor_data` tree) to
 * collect translatable strings, or to replace them with translations.
 *
 * A setting is treated as translatable text when its key looks like a text
 * field (title, content, editor, text, label, ...) and does not look like a
 * design/technical setting (color, css, url, animation, ...), and its value
 * looks like human text. This covers core Elementor widgets, Elementor Pro
 * and third-party widgets such as the Liquid "Hub" widgets without needing a
 * per-widget list. Extra keys can be added in the plugin settings.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Extractor {

	const KEY_ALLOW = '/(^|_)(title|titles|text|texts|content|contents|editor|heading|headline|label|labels|desc|description|word|words|caption|placeholder|subtitle|html|message|prefix|suffix|before|after|excerpt|quote|name|author|job|role|button|btn|tooltip|alt|loaded|more|filter|highlight|highlighted|rotating|question|answer|price|period|feature|features|info|note|badge|tab|summary|body|intro|cta|empty|success|error|required|submit|step|testimonial|lead|tagline|slogan|details|legend|hint|notice|string)($|_)/i';

	const KEY_DENY = '/(css|class|^_|(^|_)id$|_ids?$|url|link$|href|colou?r|size|(^|_)tag$|_tag_|align|animation|anim_|icon|trigger|font|typography|position|width|height|margin|padding|border|radius|shadow|transition|preset|(^|_)type$|split|offset|selector|style|effect|direction|ratio|(^|_)unit|duration|delay|speed|easing|columns|gap|spacing|display|overflow|z_index|opacity|blend|background|(^|_)bg_|breakpoint|responsive|visibility|(^|_)hide|layout|skin|(^|_)view$|template|query|orderby|taxonomy|post_type|lottie|svg|json|attributes|motion|parallax|sticky|entrance|lqd_|_source$|_format$|_key$|_mode$|(^|_)html_tag)/i';

	/**
	 * Extra keys (exact match) that should always be translated.
	 *
	 * @var string[]
	 */
	public static $extra_keys = array();

	public static function is_translatable_key( $key ) {
		if ( ! is_string( $key ) || '' === $key ) {
			return false;
		}
		if ( in_array( $key, self::$extra_keys, true ) ) {
			return true;
		}
		$ok = preg_match( self::KEY_ALLOW, $key ) && ! preg_match( self::KEY_DENY, $key );
		if ( function_exists( 'apply_filters' ) ) {
			$ok = (bool) apply_filters( 'est_is_translatable_key', $ok, $key );
		}
		return $ok;
	}

	public static function is_translatable_value( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$v = trim( $value );
		if ( '' === $v || ! preg_match( '/\p{L}/u', $v ) ) {
			return false;
		}
		// URLs, anchors, colours, CSS variables.
		if ( preg_match( '#^(https?:)?//|^(mailto|tel|data|javascript):|^\#|^var\(|^rgba?\(|^hsla?\(#i', $v ) ) {
			return false;
		}
		// Slugs / identifiers / option values: "custom", "fade-in", "h2", "top_bottom".
		if ( preg_match( '/^[a-z0-9_\-\.]+$/', $v ) ) {
			return false;
		}
		// JSON blobs and bare shortcodes.
		if ( preg_match( '/^[\{\[].*[\}\]]$/s', $v ) && ( null !== json_decode( $v ) || preg_match( '/^\[[^\]]+\]$/', $v ) ) ) {
			return false;
		}
		// Scripts / styles pasted into HTML widgets.
		if ( preg_match( '/<(script|style)\b/i', $v ) ) {
			return false;
		}
		// CSS declarations like "top: -440px;".
		if ( preg_match( '/^[a-z\-]+\s*:\s*[^;]+;?$/i', $v ) && ! preg_match( '/\s\p{L}+\s\p{L}+/u', $v ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Collect unique translatable strings from an element tree, in page order.
	 *
	 * @param array $elements Decoded Elementor data.
	 * @return string[]
	 */
	public static function extract( $elements ) {
		$out = array();
		if ( is_array( $elements ) ) {
			self::walk_elements( $elements, $out );
		}
		return array_values( $out );
	}

	/**
	 * Return a copy of the element tree with every translatable string
	 * passed through $lookup (which returns the translation or null).
	 *
	 * @param array    $elements Decoded Elementor data.
	 * @param callable $lookup   function( string $source ): ?string.
	 * @return array
	 */
	public static function translate( $elements, $lookup ) {
		if ( ! is_array( $elements ) ) {
			return $elements;
		}
		foreach ( $elements as $i => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$elements[ $i ]['settings'] = self::translate_settings( $element['settings'], $lookup );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$elements[ $i ]['elements'] = self::translate( $element['elements'], $lookup );
			}
		}
		return $elements;
	}

	const IMAGE_EXT = '/\.(jpe?g|png|gif|webp|avif|svg)(\?.*)?$/i';

	public static function is_image_url( $v ) {
		return is_string( $v ) && (bool) preg_match( '#^(https?:)?//#i', trim( $v ) ) && (bool) preg_match( self::IMAGE_EXT, trim( $v ) );
	}

	/**
	 * Unique image URLs used in an element tree (image widgets, backgrounds,
	 * galleries, carousels...: any setting array holding an image "url").
	 *
	 * @return string[]
	 */
	public static function extract_images( $elements ) {
		$out = array();
		array_walk_recursive(
			$elements,
			function ( $v, $k ) use ( &$out ) {
				if ( 'url' === $k && self::is_image_url( $v ) ) {
					$out[ trim( $v ) ] = true;
				}
			}
		);
		return array_keys( $out );
	}

	/**
	 * Swap image URLs (and their attachment IDs, which Elementor renders from).
	 *
	 * @param callable $lookup function( string $url ): ?string new URL.
	 */
	public static function translate_images( $data, $lookup ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		if ( isset( $data['url'] ) && self::is_image_url( $data['url'] ) ) {
			$new = call_user_func( $lookup, trim( $data['url'] ) );
			if ( $new ) {
				$data['url'] = $new;
				if ( array_key_exists( 'id', $data ) ) {
					$id         = function_exists( 'attachment_url_to_postid' ) ? attachment_url_to_postid( $new ) : 0;
					$data['id'] = $id ? $id : '';
				}
			}
		}
		foreach ( $data as $k => $v ) {
			if ( is_array( $v ) ) {
				$data[ $k ] = self::translate_images( $v, $lookup );
			}
		}
		return $data;
	}

	private static function walk_elements( array $elements, array &$out ) {
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				self::walk_settings( $element['settings'], $out );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				self::walk_elements( $element['elements'], $out );
			}
		}
	}

	private static function walk_settings( array $settings, array &$out ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				// Repeater rows or grouped values; the row keys decide. Keys such as
				// __dynamic__ / __globals__ hold Elementor internals, not text.
				if ( self::walk_into( $key ) ) {
					self::walk_settings( $value, $out );
				}
				continue;
			}
			if ( self::is_translatable_key( $key ) && self::is_translatable_value( $value ) ) {
				$norm = EST_Text::normalize( $value );
				$out[ md5( $norm ) ] = $norm;
			}
		}
	}

	private static function walk_into( $key ) {
		return ! is_string( $key ) || '_' !== substr( $key, 0, 1 );
	}

	private static function translate_settings( array $settings, $lookup ) {
		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( self::walk_into( $key ) ) {
					$settings[ $key ] = self::translate_settings( $value, $lookup );
				}
				continue;
			}
			if ( self::is_translatable_key( $key ) && self::is_translatable_value( $value ) ) {
				$translated = call_user_func( $lookup, $value );
				if ( null !== $translated && '' !== $translated ) {
					$settings[ $key ] = $translated;
				}
			}
		}
		return $settings;
	}
}
