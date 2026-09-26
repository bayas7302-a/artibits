<?php
/**
 * Plugin settings and language list.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Settings {

	const OPTION = 'est_settings';

	private static $cache = null;

	public static function defaults() {
		return array(
			'default_code'     => 'en',
			'default_name'     => 'English',
			'default_native'   => 'English',
			'default_dir'      => 'ltr',
			'languages'        => array(),
			'extra_keys'       => '',
			'html_fallback'    => 1,
			'localize_links'   => 1,
			'hreflang'         => 1,
			'delete_uninstall' => 0,
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
			if ( ! is_array( self::$cache['languages'] ) ) {
				self::$cache['languages'] = array();
			}
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $values ) {
		$all = array_merge( self::all(), $values );
		update_option( self::OPTION, $all );
		self::$cache = $all;
	}

	public static function default_code() {
		return (string) self::get( 'default_code' );
	}

	/**
	 * Translation languages (not the default), keyed by code.
	 */
	public static function languages( $enabled_only = false ) {
		$langs = self::get( 'languages' );
		if ( $enabled_only ) {
			$langs = array_filter(
				$langs,
				function ( $l ) {
					return ! empty( $l['enabled'] );
				}
			);
		}
		return $langs;
	}

	public static function language( $code ) {
		$langs = self::languages();
		return isset( $langs[ $code ] ) ? $langs[ $code ] : null;
	}

	/**
	 * The default language described like the others.
	 */
	public static function default_language() {
		return array(
			'code'    => self::default_code(),
			'name'    => self::get( 'default_name' ),
			'native'  => self::get( 'default_native' ),
			'locale'  => '',
			'dir'     => self::get( 'default_dir' ),
			'enabled' => 1,
			'css'     => '',
		);
	}

	/**
	 * Default + enabled languages, in switcher order.
	 */
	public static function switcher_languages() {
		return array_merge( array( self::default_code() => self::default_language() ), self::languages( true ) );
	}

	public static function sanitize_code( $code ) {
		$code = strtolower( trim( str_replace( '_', '-', (string) $code ) ) );
		return preg_match( '/^[a-z]{2,3}(-[a-z0-9]{2,4})?$/', $code ) ? $code : '';
	}

	public static function save_language( array $lang ) {
		$code = self::sanitize_code( $lang['code'] ?? '' );
		if ( '' === $code || self::default_code() === $code ) {
			return false;
		}
		$preset = self::presets()[ $code ] ?? array();
		$langs  = self::languages();
		$old    = $langs[ $code ] ?? array();

		$langs[ $code ] = array(
			'code'    => $code,
			'name'    => sanitize_text_field( $lang['name'] ?? ( $old['name'] ?? ( $preset['name'] ?? strtoupper( $code ) ) ) ),
			'native'  => sanitize_text_field( $lang['native'] ?? ( $old['native'] ?? ( $preset['native'] ?? '' ) ) ),
			'locale'  => sanitize_text_field( $lang['locale'] ?? ( $old['locale'] ?? ( $preset['locale'] ?? $code ) ) ),
			'dir'     => ( 'rtl' === ( $lang['dir'] ?? ( $old['dir'] ?? ( $preset['dir'] ?? 'ltr' ) ) ) ) ? 'rtl' : 'ltr',
			'enabled' => isset( $lang['enabled'] ) ? (int) (bool) $lang['enabled'] : ( $old['enabled'] ?? 1 ),
			'css'     => isset( $lang['css'] ) ? wp_strip_all_tags( $lang['css'] ) : ( $old['css'] ?? '' ),
		);
		if ( '' === $langs[ $code ]['name'] ) {
			$langs[ $code ]['name'] = strtoupper( $code );
		}
		self::update( array( 'languages' => $langs ) );
		return $code;
	}

	public static function delete_language( $code ) {
		$langs = self::languages();
		unset( $langs[ $code ] );
		self::update( array( 'languages' => $langs ) );
	}

	/**
	 * Resolve a spreadsheet column header ("Arabic (ar)", "ar", "Arabic",
	 * "العربية") to a language code. Returns '' when unknown.
	 */
	public static function code_from_header( $header ) {
		$header = trim( (string) $header );
		if ( '' === $header ) {
			return '';
		}
		if ( preg_match( '/\(\s*([a-z]{2,3}(?:[-_][a-z0-9]{2,4})?)\s*\)\s*$/i', $header, $m ) ) {
			return self::sanitize_code( $m[1] );
		}
		$code = self::sanitize_code( $header );
		if ( '' !== $code ) {
			return $code;
		}
		$needle = mb_strtolower( $header );
		$pool   = array_merge( self::presets(), self::languages(), array( self::default_code() => self::default_language() ) );
		foreach ( $pool as $code => $l ) {
			if ( mb_strtolower( (string) ( $l['name'] ?? '' ) ) === $needle || mb_strtolower( (string) ( $l['native'] ?? '' ) ) === $needle ) {
				return (string) $code;
			}
		}
		return '';
	}

	public static function header_label( array $lang ) {
		return $lang['name'] . ' (' . $lang['code'] . ')';
	}

	public static function presets() {
		$list = array(
			'ar'    => array( 'Arabic', 'العربية', 'ar', 'rtl' ),
			'he'    => array( 'Hebrew', 'עברית', 'he_IL', 'rtl' ),
			'fa'    => array( 'Persian', 'فارسی', 'fa_IR', 'rtl' ),
			'ur'    => array( 'Urdu', 'اردو', 'ur', 'rtl' ),
			'ku'    => array( 'Kurdish', 'کوردی', 'ckb', 'rtl' ),
			'fr'    => array( 'French', 'Français', 'fr_FR', 'ltr' ),
			'de'    => array( 'German', 'Deutsch', 'de_DE', 'ltr' ),
			'es'    => array( 'Spanish', 'Español', 'es_ES', 'ltr' ),
			'it'    => array( 'Italian', 'Italiano', 'it_IT', 'ltr' ),
			'pt'    => array( 'Portuguese', 'Português', 'pt_PT', 'ltr' ),
			'nl'    => array( 'Dutch', 'Nederlands', 'nl_NL', 'ltr' ),
			'ru'    => array( 'Russian', 'Русский', 'ru_RU', 'ltr' ),
			'tr'    => array( 'Turkish', 'Türkçe', 'tr_TR', 'ltr' ),
			'hi'    => array( 'Hindi', 'हिन्दी', 'hi_IN', 'ltr' ),
			'bn'    => array( 'Bengali', 'বাংলা', 'bn_BD', 'ltr' ),
			'ml'    => array( 'Malayalam', 'മലയാളം', 'ml_IN', 'ltr' ),
			'ta'    => array( 'Tamil', 'தமிழ்', 'ta_IN', 'ltr' ),
			'zh'    => array( 'Chinese', '中文', 'zh_CN', 'ltr' ),
			'ja'    => array( 'Japanese', '日本語', 'ja', 'ltr' ),
			'ko'    => array( 'Korean', '한국어', 'ko_KR', 'ltr' ),
			'id'    => array( 'Indonesian', 'Bahasa Indonesia', 'id_ID', 'ltr' ),
			'ms'    => array( 'Malay', 'Bahasa Melayu', 'ms_MY', 'ltr' ),
			'tl'    => array( 'Filipino', 'Filipino', 'tl', 'ltr' ),
			'pl'    => array( 'Polish', 'Polski', 'pl_PL', 'ltr' ),
			'uk'    => array( 'Ukrainian', 'Українська', 'uk', 'ltr' ),
			'el'    => array( 'Greek', 'Ελληνικά', 'el', 'ltr' ),
			'sv'    => array( 'Swedish', 'Svenska', 'sv_SE', 'ltr' ),
			'en'    => array( 'English', 'English', 'en_US', 'ltr' ),
		);
		$out  = array();
		foreach ( $list as $code => $l ) {
			$out[ $code ] = array(
				'code'   => $code,
				'name'   => $l[0],
				'native' => $l[1],
				'locale' => $l[2],
				'dir'    => $l[3],
			);
		}
		return $out;
	}
}
