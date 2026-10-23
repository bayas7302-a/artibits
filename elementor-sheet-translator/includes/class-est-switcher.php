<?php
/**
 * Language switcher markup, shared by the Elementor widget and the
 * [est_language_switcher] shortcode.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Switcher {

	public static function init() {
		add_shortcode( 'est_language_switcher', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'elementor/editor/after_enqueue_styles', array( __CLASS__, 'assets' ) );
		add_action( 'elementor/preview/enqueue_styles', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_enqueue_style( 'est-switcher', EST_URL . 'assets/switcher.css', array(), EST_VERSION );
		wp_enqueue_script( 'est-switcher', EST_URL . 'assets/switcher.js', array(), EST_VERSION, true );
	}

	public static function shortcode( $atts ) {
		return self::render( shortcode_atts( self::defaults(), (array) $atts, 'est_language_switcher' ) );
	}

	public static function defaults() {
		return array(
			'style'        => 'toggle',   // toggle | dropdown | list.
			'display'      => 'native',   // native | name | code | code_native.
			'show_current' => 'yes',      // list style: include the active language.
			'separator'    => '',         // list style: text between items, e.g. "|".
			'open_on'      => 'click',    // dropdown: click | hover.
			'menu_align'   => 'start',    // dropdown: start | end (which edge the menu opens from).
		);
	}

	private static function label( array $lang, $display ) {
		$native = '' !== (string) $lang['native'] ? $lang['native'] : $lang['name'];
		switch ( $display ) {
			case 'name':
				return $lang['name'];
			case 'code':
				return strtoupper( $lang['code'] );
			case 'code_native':
				return strtoupper( $lang['code'] ) . ' - ' . $native;
			default:
				return $native;
		}
	}

	public static function render( array $args ) {
		$args    = array_merge( self::defaults(), $args );
		$langs   = EST_Settings::switcher_languages();
		$current = EST_Router::current();
		if ( count( $langs ) < 2 ) {
			return '';
		}
		$url   = EST_Router::current_url_unprefixed();
		$items = array();
		foreach ( $langs as $code => $lang ) {
			$tag           = ! empty( $lang['locale'] ) ? str_replace( '_', '-', $lang['locale'] ) : $code;
			$items[ $code ] = array(
				'url'    => EST_Router::localize_url( $url, $code ),
				'label'  => self::label( $lang, $args['display'] ),
				'tag'    => $tag,
				'dir'    => 'rtl' === $lang['dir'] ? 'rtl' : 'ltr',
				'active' => $code === $current,
			);
		}

		$link = function ( $code, $item ) {
			return sprintf(
				'<a href="%1$s" hreflang="%2$s" lang="%2$s" dir="%3$s" data-est-lang="%4$s"%5$s>%6$s</a>',
				esc_url( $item['url'] ),
				esc_attr( $item['tag'] ),
				esc_attr( $item['dir'] ),
				esc_attr( $code ),
				$item['active'] ? ' aria-current="true"' : '',
				esc_html( $item['label'] )
			);
		};

		if ( 'toggle' === $args['style'] ) {
			// Segmented button: one outlined box, the active language filled.
			$args['show_current'] = 'yes';
			$args['separator']    = '';
		}
		if ( 'list' === $args['style'] || 'toggle' === $args['style'] ) {
			$html = '<ul class="est-switcher est-switcher--' . ( 'toggle' === $args['style'] ? 'toggle' : 'list' ) . '">';
			$first = true;
			foreach ( $items as $code => $item ) {
				if ( $item['active'] && 'yes' !== $args['show_current'] ) {
					continue;
				}
				if ( ! $first && '' !== (string) $args['separator'] ) {
					$html .= '<li class="est-switcher__sep" aria-hidden="true">' . esc_html( $args['separator'] ) . '</li>';
				}
				$first = false;
				$html .= '<li class="est-switcher__item' . ( $item['active'] ? ' is-active' : '' ) . '">' . $link( $code, $item ) . '</li>';
			}
			return $html . '</ul>';
		}

		$active = $items[ $current ] ?? reset( $items );
		$html   = '<div class="est-switcher est-switcher--dropdown' . ( 'hover' === $args['open_on'] ? ' est-switcher--hover' : '' ) . ( 'end' === $args['menu_align'] ? ' est-switcher--align-end' : '' ) . '">';
		$html  .= '<button type="button" class="est-switcher__current" aria-haspopup="true" aria-expanded="false">'
			. '<span lang="' . esc_attr( $active['tag'] ) . '" dir="' . esc_attr( $active['dir'] ) . '">' . esc_html( $active['label'] ) . '</span>'
			. '<svg class="est-switcher__caret" width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>'
			. '</button><ul class="est-switcher__menu">';
		foreach ( $items as $code => $item ) {
			$html .= '<li class="est-switcher__item' . ( $item['active'] ? ' is-active' : '' ) . '">' . $link( $code, $item ) . '</li>';
		}
		return $html . '</ul></div>';
	}
}
