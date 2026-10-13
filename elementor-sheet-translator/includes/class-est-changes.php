<?php
/**
 * Keeps translations when the English text is edited.
 *
 * Translations are matched by their English text, so editing a sentence in
 * Elementor used to leave the page untranslated. When a page is saved, the
 * old and new texts are compared field by field; where a field's English
 * changed, its translations are carried over to the new text and flagged
 * "outdated" so a translator can check them. The page keeps showing the
 * existing translation in the meantime.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Changes {

	public static function init() {
		// Elementor saves through update_post_meta( '_elementor_data' ).
		add_filter( 'update_post_metadata', array( __CLASS__, 'elementor_save' ), 10, 4 );
		// Titles and block-editor / classic content.
		add_action( 'pre_post_update', array( __CLASS__, 'post_save' ), 10, 2 );
	}

	public static function elementor_save( $check, $post_id, $meta_key, $new ) {
		if ( '_elementor_data' !== $meta_key || ! EST_Settings::languages() || wp_is_post_revision( $post_id ) ) {
			return $check;
		}
		$old = get_post_meta( $post_id, '_elementor_data', true );
		$old = is_string( $old ) ? json_decode( $old, true ) : $old;
		$new = is_string( $new ) ? json_decode( wp_unslash( $new ), true ) : $new;
		if ( is_array( $old ) && is_array( $new ) ) {
			self::carry( EST_Extractor::extract_paths( $old ), EST_Extractor::extract_paths( $new ) );
		}
		return $check;
	}

	public static function post_save( $post_id, $data ) {
		$post = get_post( $post_id );
		if ( ! $post || ! EST_Settings::languages() || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$old = array( 'title' => EST_Text::normalize( $post->post_title ) );
		$new = array( 'title' => EST_Text::normalize( wp_unslash( $data['post_title'] ?? '' ) ) );

		// Block / classic content: pair paragraphs by position when the count is unchanged.
		if ( 'builder' !== get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			$a = EST_Html::extract_blocks( $post->post_content );
			$b = EST_Html::extract_blocks( wp_unslash( $data['post_content'] ?? '' ) );
			if ( count( $a ) === count( $b ) ) {
				foreach ( $a as $i => $text ) {
					$old[ 'b' . $i ] = $text;
					$new[ 'b' . $i ] = $b[ $i ];
				}
			}
		}
		self::carry( $old, $new );
	}

	/**
	 * For every field whose text changed, copy each language's translation of
	 * the old text to the new text (unless the new text is already translated).
	 *
	 * @param array $old path => old text.
	 * @param array $new path => new text.
	 * @return int Translations carried over.
	 */
	public static function carry( array $old, array $new ) {
		$count = 0;
		foreach ( $new as $path => $text ) {
			if ( ! isset( $old[ $path ] ) || '' === $text || $old[ $path ] === $text ) {
				continue;
			}
			$from = md5( $old[ $path ] );
			$to   = md5( $text );
			foreach ( array_keys( EST_Settings::languages() ) as $lang ) {
				$dict = EST_Store::dictionary( $lang );
				if ( isset( $dict[ $from ] ) && ! isset( $dict[ $to ] ) ) {
					EST_Store::save( $lang, $text, $dict[ $from ], 1 );
					$count++;
				}
			}
		}
		return $count;
	}
}
