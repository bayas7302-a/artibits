<?php
/**
 * Settings > Cookie Banner: general settings and the banner texts per language.
 *
 * @package CCB
 */

defined( 'ABSPATH' ) || exit;

class CCB_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_ccb_save', array( __CLASS__, 'save' ) );
		add_action( 'admin_post_ccb_export', array( __CLASS__, 'export' ) );
		add_action( 'admin_post_ccb_import', array( __CLASS__, 'import' ) );
		add_filter( 'plugin_action_links_cookie-consent-banner/cookie-consent-banner.php', array( __CLASS__, 'links' ) );
	}

	public static function links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=ccb' ) ) . '">' . esc_html__( 'Settings', 'ccb' ) . '</a>' );
		return $links;
	}

	public static function menu() {
		add_options_page( __( 'Cookie Banner', 'ccb' ), __( 'Cookie Banner', 'ccb' ), 'manage_options', 'ccb', array( __CLASS__, 'page' ) );
	}

	private static function labels() {
		return array(
			'title'          => __( 'Banner title', 'ccb' ),
			'message'        => __( 'Banner message', 'ccb' ),
			'accept'         => __( 'Button: allow all', 'ccb' ),
			'reject'         => __( 'Button: reject all', 'ccb' ),
			'settings'       => __( 'Button: cookie settings', 'ccb' ),
			'modal_header'   => __( 'Preferences: window header', 'ccb' ),
			'modal_title'    => __( 'Preferences: title', 'ccb' ),
			'modal_text'     => __( 'Preferences: introduction', 'ccb' ),
			'necessary'      => __( 'Necessary cookies: name', 'ccb' ),
			'necessary_desc' => __( 'Necessary cookies: description', 'ccb' ),
			'always_on'      => __( '"Always on" label', 'ccb' ),
			'analytics'      => __( 'Analytics: name', 'ccb' ),
			'analytics_desc' => __( 'Analytics: description', 'ccb' ),
			'marketing'      => __( 'Marketing: name', 'ccb' ),
			'marketing_desc' => __( 'Marketing: description', 'ccb' ),
			'learn_more'     => __( 'Preferences: "Learn more" link', 'ccb' ),
			'modal_accept'   => __( 'Preferences button: accept all', 'ccb' ),
			'modal_reject'   => __( 'Preferences button: reject all', 'ccb' ),
			'save'           => __( 'Preferences button: confirm my choices', 'ccb' ),
			'close'          => __( 'Close button (screen readers)', 'ccb' ),
			'policy'         => __( 'Policy link text', 'ccb' ),
			'reopen'         => __( 'Footer link text ([cookie_settings])', 'ccb' ),
		);
	}

	public static function page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$saved = ! empty( $_GET['saved'] );
		// phpcs:enable
		$s     = CCB::settings();
		$langs = CCB::languages();
		$all   = (array) get_option( CCB::TEXTS, array() );
		$def   = CCB::default_texts();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Cookie Banner', 'ccb' ); ?></h1>
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved. Clear your page cache so visitors see the change.', 'ccb' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<a class="nav-tab<?php echo 'general' === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=ccb' ) ); ?>"><?php esc_html_e( 'Settings', 'ccb' ); ?></a>
				<?php foreach ( $langs as $code => $name ) : ?>
					<a class="nav-tab<?php echo $code === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'options-general.php?page=ccb&tab=' . rawurlencode( $code ) ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: language */ __( 'Texts - %s', 'ccb' ), $name ) ); ?></a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'ccb_save' ); ?>
				<input type="hidden" name="action" value="ccb_save">
				<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">

				<?php if ( 'general' === $tab ) : ?>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Banner', 'ccb' ); ?></th><td>
							<label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the banner and block trackers until consent', 'ccb' ); ?></label><br>
							<label><input type="checkbox" name="show_marketing" value="1" <?php checked( $s['show_marketing'] ); ?>> <?php esc_html_e( 'Offer a "Marketing" category (only needed if the site uses advertising pixels)', 'ccb' ); ?></label>
						</td></tr>
						<tr><th><?php esc_html_e( 'Colors', 'ccb' ); ?></th><td>
							<?php
							foreach ( array(
								'color_bg'       => __( 'Background', 'ccb' ),
								'color_text'     => __( 'Text', 'ccb' ),
								'color_btn'      => __( 'Buttons', 'ccb' ),
								'color_btn_text' => __( 'Button text', 'ccb' ),
							) as $k => $label ) {
								printf( '<label style="display:inline-block;margin:0 18px 6px 0"><input type="color" name="%1$s" value="%2$s"> %3$s</label>', esc_attr( $k ), esc_attr( $s[ $k ] ), esc_html( $label ) );
							}
							?>
						</td></tr>
						<tr><th><label for="ccb-policy"><?php esc_html_e( 'Privacy / cookie policy page', 'ccb' ); ?></label></th><td>
							<input id="ccb-policy" type="url" class="regular-text" name="policy_url" value="<?php echo esc_attr( $s['policy_url'] ); ?>" placeholder="https://floraveld.fr/privacy-cookie/">
							<p class="description"><?php esc_html_e( 'Linked from the banner. With Elementor Sheet Translator the link follows the visitor\'s language automatically.', 'ccb' ); ?></p>
						</td></tr>
						<tr><th><label for="ccb-days"><?php esc_html_e( 'Remember the choice for', 'ccb' ); ?></label></th><td>
							<input id="ccb-days" type="number" min="1" max="395" name="days" value="<?php echo (int) $s['days']; ?>" class="small-text"> <?php esc_html_e( 'days (CNIL recommends about 6 months = 182 days)', 'ccb' ); ?>
						</td></tr>
						<tr><th><?php esc_html_e( 'Ask everyone again', 'ccb' ); ?></th><td>
							<label><input type="checkbox" name="bump_version" value="1"> <?php esc_html_e( 'Show the banner again to every visitor on their next visit (e.g. after adding a new tracker)', 'ccb' ); ?></label>
						</td></tr>
						<tr><th><?php esc_html_e( 'Blocked until "Analytics" is allowed', 'ccb' ); ?></th><td>
							<textarea name="analytics_patterns" rows="6" class="large-text code"><?php echo esc_textarea( $s['analytics_patterns'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Any script whose address or code contains one of these is held back. Google Analytics and Site Kit\'s Google tag are covered by default.', 'ccb' ); ?></p>
						</td></tr>
						<tr><th><?php esc_html_e( 'Blocked until "Marketing" is allowed', 'ccb' ); ?></th><td>
							<textarea name="marketing_patterns" rows="5" class="large-text code"><?php echo esc_textarea( $s['marketing_patterns'] ); ?></textarea>
						</td></tr>
						<tr><th><?php esc_html_e( 'Footer link', 'ccb' ); ?></th><td>
							<code>[cookie_settings]</code>
							<p class="description"><?php esc_html_e( 'Add this shortcode in the footer (Elementor Shortcode widget) so visitors can change their choice at any time - required by the GDPR/CNIL. Any element with the CSS class "ccb-open" also opens the preferences.', 'ccb' ); ?></p>
						</td></tr>
					</table>
				<?php elseif ( isset( $langs[ $tab ] ) ) : ?>
					<p><?php echo esc_html( 'en' === $tab ? __( 'English texts are used for every language that has no text of its own.', 'ccb' ) : __( 'Leave a field empty to use the English text.', 'ccb' ) ); ?></p>
					<table class="form-table" role="presentation">
						<?php foreach ( self::labels() as $k => $label ) : ?>
							<?php $val = (string) ( $all[ $tab ][ $k ] ?? '' ); ?>
							<tr><th><label for="ccb-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></label></th><td>
								<?php if ( in_array( $k, array( 'message', 'modal_text', 'necessary_desc', 'analytics_desc', 'marketing_desc' ), true ) ) : ?>
									<textarea id="ccb-<?php echo esc_attr( $k ); ?>" name="texts[<?php echo esc_attr( $k ); ?>]" rows="3" class="large-text" placeholder="<?php echo esc_attr( $def[ $k ] ); ?>"><?php echo esc_textarea( $val ); ?></textarea>
								<?php else : ?>
									<input id="ccb-<?php echo esc_attr( $k ); ?>" type="text" class="regular-text" name="texts[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $def[ $k ] ); ?>">
								<?php endif; ?>
							</td></tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>

			<?php if ( 'general' === $tab ) : ?>
				<hr>
				<h2><?php esc_html_e( 'Translate the texts with a spreadsheet', 'ccb' ); ?></h2>
				<p><?php esc_html_e( 'Export every banner text as a CSV file (one row per text, one column per language), fill in the language columns in Excel or Google Sheets, then import it. Empty cells are skipped.', 'ccb' ); ?></p>
				<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ccb_export' ), 'ccb_export' ) ); ?>"><?php esc_html_e( 'Export texts (CSV)', 'ccb' ); ?></a></p>
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'ccb_import' ); ?>
					<input type="hidden" name="action" value="ccb_import">
					<input type="file" name="ccb_file" accept=".csv,text/csv" required>
					<?php submit_button( __( 'Import texts', 'ccb' ), 'secondary', 'submit', false ); ?>
				</form>
				<?php
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( isset( $_GET['imported'] ) ) {
					printf( '<div class="notice notice-success inline"><p>%s</p></div>', esc_html( sprintf( /* translators: %d: count */ __( '%d texts imported.', 'ccb' ), (int) $_GET['imported'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
				?>
			<?php endif; ?>
		</div>
		<?php
	}

	/** Column header for a language: "French (fr)". */
	private static function header( $code, $name ) {
		return $name . ' (' . $code . ')';
	}

	public static function export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ccb' ) );
		}
		check_admin_referer( 'ccb_export' );
		$langs = CCB::languages();
		$all   = (array) get_option( CCB::TEXTS, array() );
		$def   = CCB::default_texts();

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, "\xEF\xBB\xBF" );
		$head = array( 'Key', 'Field' );
		foreach ( $langs as $code => $name ) {
			$head[] = self::header( $code, $name );
		}
		fputcsv( $fh, $head, ',', '"', '' );
		foreach ( self::labels() as $key => $label ) {
			$row = array( $key, $label );
			foreach ( array_keys( $langs ) as $code ) {
				$v = (string) ( $all[ $code ][ $key ] ?? '' );
				// English column shows the text in use, so translators see the source.
				if ( '' === $v && 'en' === $code ) {
					$v = $def[ $key ];
				}
				$row[] = $v;
			}
			fputcsv( $fh, $row, ',', '"', '' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="cookie-banner-texts-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	public static function import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ccb' ) );
		}
		check_admin_referer( 'ccb_import' );
		if ( empty( $_FILES['ccb_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['ccb_file']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			wp_die( esc_html__( 'Please choose a CSV file.', 'ccb' ) );
		}
		$content = (string) file_get_contents( $_FILES['ccb_file']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.WP.AlternativeFunctions
		if ( "\xEF\xBB\xBF" === substr( $content, 0, 3 ) ) {
			$content = substr( $content, 3 );
		} elseif ( "\xFF\xFE" === substr( $content, 0, 2 ) ) {
			$content = mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16LE' );
		}
		$first = strtok( $content, "\n" );
		$delim = ',';
		foreach ( array( ';', "\t" ) as $d ) {
			if ( substr_count( (string) $first, $d ) > substr_count( (string) $first, $delim ) ) {
				$delim = $d;
			}
		}
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $content );
		rewind( $fh );
		$header = fgetcsv( $fh, 0, $delim, '"', '' );
		$cols   = array();
		foreach ( (array) $header as $i => $cell ) {
			if ( $i < 2 ) {
				continue;
			}
			$cell = trim( (string) $cell );
			if ( preg_match( '/\(\s*([a-z]{2,3}(?:[-_][a-z0-9]{2,4})?)\s*\)\s*$/i', $cell, $m ) || preg_match( '/^([a-z]{2,3}(?:[-_][a-z0-9]{2,4})?)$/i', $cell, $m ) ) {
				$cols[ $i ] = strtolower( str_replace( '_', '-', $m[1] ) );
			}
		}
		$keys  = array_keys( CCB::default_texts() );
		$all   = (array) get_option( CCB::TEXTS, array() );
		$count = 0;
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			$key = trim( (string) ( $row[0] ?? '' ) );
			if ( ! in_array( $key, $keys, true ) ) {
				continue;
			}
			foreach ( $cols as $i => $code ) {
				$v = trim( (string) ( $row[ $i ] ?? '' ) );
				if ( '' !== $v ) {
					$all[ $code ][ $key ] = sanitize_textarea_field( $v );
					$count++;
				}
			}
		}
		fclose( $fh );
		update_option( CCB::TEXTS, $all );
		do_action( 'litespeed_purge_all' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		wp_safe_redirect( admin_url( 'options-general.php?page=ccb&imported=' . $count ) );
		exit;
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ccb' ) );
		}
		check_admin_referer( 'ccb_save' );
		$p   = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$tab = sanitize_key( $p['tab'] ?? 'general' );

		if ( 'general' === $tab ) {
			$s = CCB::settings();
			foreach ( array( 'color_bg', 'color_text', 'color_btn', 'color_btn_text' ) as $k ) {
				$s[ $k ] = sanitize_hex_color( $p[ $k ] ?? '' ) ? sanitize_hex_color( $p[ $k ] ) : CCB::defaults()[ $k ];
			}
			$s['enabled']            = empty( $p['enabled'] ) ? 0 : 1;
			$s['show_marketing']     = empty( $p['show_marketing'] ) ? 0 : 1;
			$s['policy_url']         = esc_url_raw( $p['policy_url'] ?? '' );
			$s['days']               = max( 1, min( 395, (int) ( $p['days'] ?? 182 ) ) );
			$s['analytics_patterns'] = sanitize_textarea_field( $p['analytics_patterns'] ?? '' );
			$s['marketing_patterns'] = sanitize_textarea_field( $p['marketing_patterns'] ?? '' );
			if ( ! empty( $p['bump_version'] ) ) {
				$s['version'] = (int) $s['version'] + 1;
			}
			update_option( CCB::OPTION, $s );
		} else {
			$all         = (array) get_option( CCB::TEXTS, array() );
			$all[ $tab ] = array();
			foreach ( array_keys( CCB::default_texts() ) as $k ) {
				$all[ $tab ][ $k ] = sanitize_textarea_field( $p['texts'][ $k ] ?? '' );
			}
			update_option( CCB::TEXTS, $all );
		}

		// Purge common page caches.
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		do_action( 'litespeed_purge_all' );

		wp_safe_redirect( admin_url( 'options-general.php?page=ccb&saved=1' . ( 'general' === $tab ? '' : '&tab=' . rawurlencode( $tab ) ) ) );
		exit;
	}
}
