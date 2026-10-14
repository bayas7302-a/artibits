<?php
/**
 * Front end: settings, texts per language, the banner, and blocking of
 * tracking scripts until consent.
 *
 * How blocking works: every <script> in the page whose source or code
 * matches a tracker pattern is rewritten to type="text/plain" (and its src
 * moved to data-ccb-src), so the browser does not run or download it. When
 * the visitor has consented to that category, ccb.js turns those scripts back
 * into real ones. The page HTML is the same for every visitor, so it works
 * with page caching (LiteSpeed, WP Rocket...).
 *
 * @package CCB
 */

defined( 'ABSPATH' ) || exit;

class CCB {

	const OPTION = 'ccb_settings';
	const TEXTS  = 'ccb_texts';
	const COOKIE = 'ccb_consent';

	private static $settings = null;

	public static function defaults() {
		return array(
			'enabled'           => 1,
			'show_marketing'    => 0,
			'days'              => 182, // CNIL: ask again after about 6 months.
			'version'           => 1,   // Raising it asks every visitor again.
			'color_bg'          => '#ffffff',
			'color_text'        => '#111111',
			'color_btn'         => '#000000',
			'color_btn_text'    => '#ffffff',
			'policy_url'        => '',
			'analytics_patterns' => "googletagmanager.com/gtag/js\ngoogle-analytics.com\ngoogletagmanager.com/gtm.js\ngtag('config'\ngtag(\"config\"\ngtag('js'\ngtag(\"js\"\n_gaq\nclarity.ms\nstatic.hotjar.com",
			'marketing_patterns' => "connect.facebook.net\nfbq(\ndoubleclick.net\ngoogleadservices.com\nsnap.licdn.com\nanalytics.tiktok.com",
		);
	}

	public static function default_texts() {
		return array(
			'title'          => 'Cookie settings',
			'message'        => 'When you visit our website, data may be stored in your browser in the form of cookies. This information is used to make sure the site works properly and can give you a more personalised web experience.',
			'accept'         => 'Allow all cookies',
			'reject'         => 'Reject all',
			'settings'       => 'Cookie settings',
			'modal_header'   => 'Privacy preference centre',
			'modal_title'    => 'Manage consent preferences',
			'modal_text'     => 'A cookie is a small text file placed on your device when you visit the site. You can choose not to allow some types of cookies. Blocking some types may affect your experience of the site.',
			'learn_more'     => 'Learn more',
			'necessary'      => 'Strictly necessary cookies',
			'necessary_desc' => 'Site operation (language, display preferences). Exempt from consent.',
			'always_on'      => 'Always active',
			'analytics'      => 'Audience measurement',
			'analytics_desc' => 'Google Analytics 4 - anonymised traffic statistics, site improvement.',
			'marketing'      => 'Marketing',
			'marketing_desc' => 'Used to measure and show relevant advertising.',
			'modal_accept'   => 'Accept all',
			'modal_reject'   => 'Reject all',
			'save'           => 'Confirm my choices',
			'policy'         => 'Privacy and cookie policy',
			'reopen'         => 'Cookie settings',
			'close'          => 'Close',
		);
	}

	public static function settings() {
		if ( null === self::$settings ) {
			$saved          = get_option( self::OPTION, array() );
			self::$settings = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		}
		return self::$settings;
	}

	public static function get( $key ) {
		return self::settings()[ $key ] ?? null;
	}

	/* ------------------------------------------------------------------
	 * Languages: Elementor Sheet Translator's when present, else the locale.
	 * --------------------------------------------------------------- */

	public static function current_lang() {
		if ( class_exists( 'EST_Router' ) ) {
			return EST_Router::current();
		}
		return strtolower( substr( determine_locale(), 0, 2 ) );
	}

	/** [ code => label ] for the admin text tabs. */
	public static function languages() {
		$out = array();
		if ( class_exists( 'EST_Settings' ) ) {
			foreach ( EST_Settings::switcher_languages() as $code => $l ) {
				$out[ $code ] = $l['name'];
			}
		}
		if ( ! $out ) {
			$out['en'] = 'English';
		}
		foreach ( array_keys( (array) get_option( self::TEXTS, array() ) ) as $code ) {
			if ( ! isset( $out[ $code ] ) ) {
				$out[ $code ] = strtoupper( $code );
			}
		}
		return $out;
	}

	/** Texts for a language: its saved values, then English, then the defaults. */
	public static function texts( $lang ) {
		$all = (array) get_option( self::TEXTS, array() );
		$out = self::default_texts();
		foreach ( array( 'en', $lang ) as $code ) {
			foreach ( (array) ( $all[ $code ] ?? array() ) as $k => $v ) {
				if ( '' !== trim( (string) $v ) ) {
					$out[ $k ] = $v;
				}
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */

	public static function init() {
		if ( is_admin() || ! self::get( 'enabled' ) ) {
			return;
		}
		add_action( 'wp_head', array( __CLASS__, 'consent_defaults' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'banner' ), 5 ); // Before footer scripts (priority 20).
		add_action( 'template_redirect', array( __CLASS__, 'start_buffer' ), 1 );
		add_shortcode( 'cookie_settings', array( __CLASS__, 'reopen_shortcode' ) );
	}

	/**
	 * Google Consent Mode v2: everything denied until the visitor chooses.
	 * Printed first in <head>, before any Google tag.
	 */
	public static function consent_defaults() {
		// nowprocket / data-no-optimize: never delayed or combined by WP Rocket and similar plugins.
		echo "<script data-ccb-skip nowprocket data-no-optimize=\"1\" data-no-defer=\"1\" data-cfasync=\"false\">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',functionality_storage:'granted',security_storage:'granted',wait_for_update:500});</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function assets() {
		$s = self::settings();
		wp_enqueue_style( 'ccb', CCB_URL . 'assets/ccb.css', array(), CCB_VERSION );
		wp_add_inline_style(
			'ccb',
			sprintf(
				'.ccb{--ccb-bg:%s;--ccb-text:%s;--ccb-btn:%s;--ccb-btn-text:%s}',
				esc_attr( $s['color_bg'] ),
				esc_attr( $s['color_text'] ),
				esc_attr( $s['color_btn'] ),
				esc_attr( $s['color_btn_text'] )
			)
		);
		wp_enqueue_script( 'ccb', CCB_URL . 'assets/ccb.js', array(), CCB_VERSION, true );
		wp_localize_script(
			'ccb',
			'ccbConfig',
			array(
				'cookie'  => self::COOKIE,
				'days'    => (int) $s['days'],
				'version' => (int) $s['version'],
				'path'    => COOKIEPATH ? COOKIEPATH : '/',
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Script blocking
	 * --------------------------------------------------------------- */

	public static function start_buffer() {
		if ( is_feed() || is_robots() || is_embed() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		// The Elementor editor preview must keep working normally.
		if ( isset( $_GET['elementor-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		ob_start( array( __CLASS__, 'block_scripts' ) );
	}

	private static function patterns( $key ) {
		return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) self::get( $key ) ) ) ) );
	}

	/** Category a script belongs to (analytics / marketing), or ''. */
	public static function category( $attrs, $code ) {
		if ( false !== stripos( $attrs, 'data-ccb-skip' ) ) {
			return '';
		}
		$src      = preg_match( '/\ssrc=(["\'])(.*?)\1/i', $attrs, $m ) ? $m[2] : '';
		$haystack = $src . "\n" . $code;
		$cats     = array( 'analytics' => 'analytics_patterns' );
		if ( self::get( 'show_marketing' ) ) {
			$cats['marketing'] = 'marketing_patterns';
		}
		foreach ( $cats as $cat => $key ) {
			foreach ( self::patterns( $key ) as $p ) {
				if ( false !== stripos( $haystack, $p ) ) {
					return $cat;
				}
			}
		}
		return '';
	}

	public static function block_scripts( $html ) {
		if ( ! is_string( $html ) || false === stripos( substr( $html, 0, 2000 ), '<html' ) ) {
			return $html;
		}
		$out = preg_replace_callback(
			'#<script\b([^>]*)>(.*?)</script>#is',
			function ( $m ) {
				$attrs = $m[1];
				// Leave JSON / templates and already-plain scripts alone. Scripts delayed by
				// WP Rocket ("rocketlazyloadscript") are still JavaScript and must be blocked.
				if ( preg_match( '/\stype=(["\'])(?!text\/javascript|module|application\/javascript|rocketlazyloadscript)[^"\']*\1/i', $attrs ) ) {
					return $m[0];
				}
				// Delayed scripts keep their URL in data-rocket-src.
				$check = preg_replace( '/\sdata-rocket-src=/i', ' src=', $attrs );
				$cat   = self::category( $check, $m[2] );
				if ( '' === $cat ) {
					return $m[0];
				}
				$attrs = preg_replace( '/\s(type|data-rocket-type)=(["\'])[^"\']*\2/i', '', $attrs );
				$attrs = preg_replace( '/\s(data-rocket-src|src)=/i', ' data-ccb-src=', $attrs );
				$attrs = preg_replace( '/\s(nowprocket|data-no-optimize|data-no-defer)(=(["\'])[^"\']*\3)?/i', '', $attrs );
				// nowprocket: WP Rocket must not delay the placeholder, ccb.js restores it.
				return '<script type="text/plain" data-ccb="' . $cat . '" nowprocket data-no-optimize="1"' . $attrs . '>' . $m[2] . '</script>';
			},
			$html
		);
		return null === $out ? $html : $out;
	}

	/* ------------------------------------------------------------------
	 * Banner
	 * --------------------------------------------------------------- */

	public static function banner() {
		$t      = self::texts( self::current_lang() );
		$policy = (string) self::get( 'policy_url' );
		if ( '' !== $policy && class_exists( 'EST_Router' ) ) {
			$policy = EST_Router::localize_url( $policy, self::current_lang() );
		}
		$cats = array(
			'analytics' => array( $t['analytics'], $t['analytics_desc'] ),
		);
		if ( self::get( 'show_marketing' ) ) {
			$cats['marketing'] = array( $t['marketing'], $t['marketing_desc'] );
		}
		?>
		<div class="ccb" id="ccb" role="region" aria-label="<?php echo esc_attr( $t['title'] ); ?>" hidden>
			<div class="ccb__bar">
				<div class="ccb__text">
					<p class="ccb__title"><?php echo esc_html( $t['title'] ); ?></p>
					<p class="ccb__msg"><?php echo esc_html( $t['message'] ); ?>
						<?php if ( $policy ) : ?>
							<a class="ccb__policy" href="<?php echo esc_url( $policy ); ?>"><?php echo esc_html( $t['policy'] ); ?></a>
						<?php endif; ?>
					</p>
				</div>
				<div class="ccb__actions">
					<button type="button" class="ccb__btn" data-ccb-action="accept"><?php echo esc_html( $t['accept'] ); ?></button>
					<button type="button" class="ccb__btn" data-ccb-action="reject"><?php echo esc_html( $t['reject'] ); ?></button>
					<button type="button" class="ccb__btn" data-ccb-action="settings"><?php echo esc_html( $t['settings'] ); ?></button>
				</div>
			</div>
		</div>

		<div class="ccb ccb-modal" id="ccb-modal" role="dialog" aria-modal="true" aria-labelledby="ccb-modal-head" hidden>
			<div class="ccb-modal__box">
				<div class="ccb-modal__header">
					<p class="ccb-modal__heading" id="ccb-modal-head"><?php echo esc_html( $t['modal_header'] ); ?></p>
					<button type="button" class="ccb-modal__close" data-ccb-action="close" aria-label="<?php echo esc_attr( $t['close'] ); ?>"><svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true"><path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="2" fill="none"/></svg></button>
				</div>
				<div class="ccb-modal__body">
					<p class="ccb-modal__title"><?php echo esc_html( $t['modal_title'] ); ?></p>
					<p class="ccb-modal__text"><?php echo esc_html( $t['modal_text'] ); ?>
						<?php if ( $policy ) : ?>
							<a href="<?php echo esc_url( $policy ); ?>"><?php echo esc_html( $t['learn_more'] ); ?></a>
						<?php endif; ?>
					</p>
					<div class="ccb-cats">
						<div class="ccb-cat">
							<div class="ccb-cat__info"><strong><?php echo esc_html( $t['necessary'] ); ?></strong><span><?php echo esc_html( $t['necessary_desc'] ); ?></span></div>
							<span class="ccb-cat__on"><?php echo esc_html( $t['always_on'] ); ?></span>
						</div>
						<?php foreach ( $cats as $key => $c ) : ?>
							<label class="ccb-cat">
								<span class="ccb-cat__info"><strong><?php echo esc_html( $c[0] ); ?></strong><span><?php echo esc_html( $c[1] ); ?></span></span>
								<span class="ccb-switch"><input type="checkbox" data-ccb-cat="<?php echo esc_attr( $key ); ?>"><span></span></span>
							</label>
						<?php endforeach; ?>
					</div>
				</div>
				<div class="ccb-modal__actions">
					<button type="button" class="ccb__btn ccb__btn--pill" data-ccb-action="accept"><?php echo esc_html( $t['modal_accept'] ); ?></button>
					<button type="button" class="ccb__btn ccb__btn--pill" data-ccb-action="reject"><?php echo esc_html( $t['modal_reject'] ); ?></button>
					<button type="button" class="ccb__btn ccb__btn--pill" data-ccb-action="save"><?php echo esc_html( $t['save'] ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/** [cookie_settings] - a link that reopens the preferences (for the footer). */
	public static function reopen_shortcode( $atts ) {
		$t     = self::texts( self::current_lang() );
		$atts  = shortcode_atts( array( 'label' => $t['reopen'] ), (array) $atts, 'cookie_settings' );
		return '<a href="#" class="ccb-open" role="button">' . esc_html( $atts['label'] ) . '</a>';
	}
}
