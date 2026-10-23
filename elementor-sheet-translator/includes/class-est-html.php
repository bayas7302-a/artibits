<?php
/**
 * Final-HTML pass: translates plain text nodes / text attributes that match
 * a dictionary entry exactly (a safety net for strings that do not come from
 * Elementor data, e.g. theme or widget strings) and rewrites internal links
 * so that visitors stay in the selected language.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Html {

	/**
	 * @param string        $html   Full page HTML.
	 * @param callable|null $lookup function( string $plain_text ): ?string.
	 * @param callable|null $link   function( string $url ): string.
	 * @return string
	 */
	public static function process( $html, $lookup = null, $link = null ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		$parts = preg_split( '#(<script\b[^>]*>.*?</script>|<style\b[^>]*>.*?</style>|<textarea\b[^>]*>.*?</textarea>|<!--.*?-->)#is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts ) {
			return $html;
		}
		foreach ( $parts as $i => $part ) {
			if ( 1 === $i % 2 || '' === $part ) {
				continue; // Protected block (script, style, textarea, comment).
			}
			if ( $lookup ) {
				$part = self::translate_text_nodes( $part, $lookup );
				$part = self::translate_attributes( $part, $lookup );
			}
			if ( $link ) {
				$part = self::rewrite_links( $part, $link );
			}
			$parts[ $i ] = $part;
		}
		return implode( '', $parts );
	}

	/*
	 * Block-editor / classic content: every innermost text element (paragraph,
	 * heading, list item, table cell, caption...) becomes one translatable
	 * string, keeping inline markup such as <strong> or <a> inside it.
	 */
	const LEAF = '#<(p|h[1-6]|li|figcaption|td|th|dt|dd|blockquote|summary|caption|cite|button|label)\b([^>]*)>((?:(?!<(?:p|h[1-6]|li|ul|ol|div|table|figure|blockquote|section)\b).)*?)</\1>#is';

	/**
	 * @return string[] Unique normalised strings in document order.
	 */
	public static function extract_blocks( $html ) {
		$out = array();
		if ( is_string( $html ) && preg_match_all( self::LEAF, $html, $m ) ) {
			foreach ( $m[3] as $inner ) {
				$norm = EST_Text::normalize( $inner );
				if ( '' !== $norm && preg_match( '/\p{L}/u', strip_tags( $norm ) ) ) {
					$out[ md5( $norm ) ] = $norm;
				}
			}
		}
		// Classic-editor text without <p> tags: paragraphs are blank-line separated.
		if ( is_string( $html ) && ! $out && '' !== trim( $html ) && ! preg_match( '#<(p|div|h[1-6]|ul|table)\b#i', $html ) ) {
			foreach ( preg_split( '/\n\s*\n/', $html ) as $para ) {
				$norm = EST_Text::normalize( $para );
				if ( '' !== $norm && preg_match( '/\p{L}/u', $norm ) ) {
					$out[ md5( $norm ) ] = $norm;
				}
			}
		}
		return array_values( $out );
	}

	public static function translate_blocks( $html, $lookup ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}
		if ( ! preg_match( '#<(p|div|h[1-6]|ul|table)\b#i', $html ) ) {
			$paras = preg_split( '/(\n\s*\n)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
			foreach ( $paras as $i => $para ) {
				if ( 0 === $i % 2 ) {
					$t = call_user_func( $lookup, $para );
					if ( null !== $t && '' !== $t ) {
						$paras[ $i ] = $t;
					}
				}
			}
			return implode( '', $paras );
		}
		$result = preg_replace_callback(
			self::LEAF,
			function ( $m ) use ( $lookup ) {
				$t = call_user_func( $lookup, $m[3] );
				if ( null === $t || '' === $t ) {
					return $m[0];
				}
				return '<' . $m[1] . $m[2] . '>' . EST_Text::keep_spacing( $m[3], $t ) . '</' . $m[1] . '>';
			},
			$html
		);
		return null === $result ? $html : $result;
	}

	private static function translate_text_nodes( $html, $lookup ) {
		$result = preg_replace_callback(
			'/>([^<>]+)</u',
			function ( $m ) use ( $lookup ) {
				$raw = $m[1];
				if ( '' === trim( $raw ) ) {
					return $m[0];
				}
				$plain      = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$translated = call_user_func( $lookup, $plain );
				if ( null === $translated || '' === $translated ) {
					return $m[0];
				}
				preg_match( '/^(\s*)/u', $raw, $lead );
				preg_match( '/(\s*)$/u', $raw, $trail );
				return '>' . $lead[1] . htmlspecialchars( $translated, ENT_NOQUOTES, 'UTF-8', false ) . $trail[1] . '<';
			},
			$html
		);
		return null === $result ? $html : $result;
	}

	private static function translate_attributes( $html, $lookup ) {
		$result = preg_replace_callback(
			'/(\s(?:placeholder|alt|title|aria-label|data-text)=)"([^"]*)"/u',
			function ( $m ) use ( $lookup ) {
				if ( '' === trim( $m[2] ) ) {
					return $m[0];
				}
				$plain      = html_entity_decode( $m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$translated = call_user_func( $lookup, $plain );
				if ( null === $translated || '' === $translated ) {
					return $m[0];
				}
				return $m[1] . '"' . htmlspecialchars( $translated, ENT_QUOTES, 'UTF-8', false ) . '"';
			},
			$html
		);
		return null === $result ? $html : $result;
	}

	private static function rewrite_links( $html, $link ) {
		$result = preg_replace_callback(
			'/<(?:a|form|area)\b[^>]*>/i',
			function ( $tag ) use ( $link ) {
				// Language switcher / hreflang links already point at a specific language.
				if ( preg_match( '/\s(hreflang|data-est-lang)=/i', $tag[0] ) ) {
					return $tag[0];
				}
				$out = preg_replace_callback(
					'/(\s(?:href|action)=)(["\'])([^"\']*)\2/i',
					function ( $m ) use ( $link ) {
						return $m[1] . $m[2] . call_user_func( $link, $m[3] ) . $m[2];
					},
					$tag[0]
				);
				return null === $out ? $tag[0] : $out;
			},
			$html
		);
		return null === $result ? $html : $result;
	}
}
