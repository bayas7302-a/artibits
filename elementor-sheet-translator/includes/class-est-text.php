<?php
/**
 * Text helpers shared by extraction, storage and lookup.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Text {

	/**
	 * Normalise a source string so that small whitespace differences
	 * (trailing spaces, CRLF from Excel, non-breaking spaces) still match.
	 */
	public static function normalize( $text ) {
		$text = (string) $text;
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		$clean = preg_replace( '/[ \t\x{00A0}]+/u', ' ', $text );
		$text  = null === $clean ? preg_replace( '/[ \t]+/', ' ', $text ) : $clean; // Invalid UTF-8 falls back to ASCII rules.
		$text  = preg_replace( '/ *\n */', "\n", $text );
		return trim( $text );
	}

	public static function hash( $text ) {
		return md5( self::normalize( $text ) );
	}
}
