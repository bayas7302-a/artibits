<?php
/**
 * Admin screens: Content & Export, Import, Edit translations, Languages & Settings.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Admin {

	const CAP = 'manage_options';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_est_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_est_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_est_save_translations', array( __CLASS__, 'handle_save_translations' ) );
		add_action( 'admin_post_est_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_est_add_language', array( __CLASS__, 'handle_add_language' ) );
		add_action( 'admin_post_est_delete_language', array( __CLASS__, 'handle_delete_language' ) );
		add_action( 'admin_head', array( __CLASS__, 'styles' ) );
	}

	public static function menu() {
		add_menu_page( __( 'Sheet Translator', 'est' ), __( 'Sheet Translator', 'est' ), self::CAP, 'est', array( __CLASS__, 'page_content' ), 'dashicons-translation', 81 );
		add_submenu_page( 'est', __( 'Content & Export', 'est' ), __( 'Content & Export', 'est' ), self::CAP, 'est', array( __CLASS__, 'page_content' ) );
		add_submenu_page( 'est', __( 'Import', 'est' ), __( 'Import', 'est' ), self::CAP, 'est-import', array( __CLASS__, 'page_import' ) );
		add_submenu_page( 'est', __( 'Edit Translations', 'est' ), __( 'Edit Translations', 'est' ), self::CAP, 'est-editor', array( __CLASS__, 'page_editor' ) );
		add_submenu_page( 'est', __( 'Languages & Settings', 'est' ), __( 'Languages & Settings', 'est' ), self::CAP, 'est-languages', array( __CLASS__, 'page_languages' ) );
	}

	public static function styles() {
		$screen = get_current_screen();
		if ( ! $screen || false === strpos( $screen->id, 'est' ) ) {
			return;
		}
		?>
		<style>
			.est-wrap { max-width: 1160px; }
			.est-wrap > h1 { font-size: 22px; font-weight: 600; margin-bottom: 4px; display: flex; align-items: center; gap: 10px; }
			.est-wrap > h1::before { content: "\f326"; font-family: dashicons; font-size: 22px; color: #fff; background: #2271b1; border-radius: 8px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center; font-weight: 400; }
			.est-wrap .est-card { background: #fff; border: 1px solid #e2e4e7; border-radius: 10px; padding: 20px 24px; margin: 18px 0; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
			.est-wrap .est-card h2 { margin: 0 0 14px; font-size: 15px; font-weight: 600; padding-bottom: 12px; border-bottom: 1px solid #f0f0f1; }
			.est-wrap table.widefat { border: 1px solid #e2e4e7; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 2px rgba(16,24,40,.04); }
			.est-wrap table.widefat thead th, .est-wrap table.widefat thead td { background: #f6f7f7; font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: #50575e; border-bottom: 1px solid #e2e4e7; }
			.est-wrap table.widefat td, .est-wrap table.widefat th { vertical-align: middle; padding-top: 10px; padding-bottom: 10px; }
			.est-wrap .est-progress { display: inline-block; width: 80px; height: 6px; background: #eef0f2; border-radius: 99px; vertical-align: middle; margin-inline-end: 8px; overflow: hidden; }
			.est-wrap .est-progress span { display: block; height: 100%; background: linear-gradient(90deg, #2271b1, #3f9be0); border-radius: 99px; }
			.est-wrap .est-editor td { vertical-align: top; }
			.est-wrap .est-editor textarea { width: 100%; min-height: 48px; border-radius: 6px; }
			.est-wrap .est-source { white-space: pre-wrap; word-break: break-word; font-size: 13px; color: #1d2327; }
			.est-wrap .est-muted { color: #646970; }
			.est-wrap .est-inline-form { display: inline; }
			.est-wrap .est-lang-table input[type=text] { width: 100%; }
			.est-wrap select, .est-wrap input[type=text], .est-wrap input[type=file] { border-radius: 6px; }
			.est-wrap .button { border-radius: 6px; }
			.est-wrap code.est-big { font-size: 13px; padding: 4px 8px; border-radius: 4px; }
		</style>
		<?php
	}

	private static function check( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'est' ) );
		}
		check_admin_referer( $nonce_action );
	}

	private static function notice() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['est_msg'] ) ) {
			$msg = sanitize_text_field( wp_unslash( $_GET['est_msg'] ) );
			$err = ! empty( $_GET['est_err'] );
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', $err ? 'error' : 'success', esc_html( $msg ) );
		}
		// phpcs:enable
	}

	private static function back( $page, $msg, $error = false, $extra = array() ) {
		$args = array_merge(
			array(
				'page'    => $page,
				'est_msg' => rawurlencode( $msg ),
			),
			$error ? array( 'est_err' => 1 ) : array(),
			$extra
		);
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function no_languages_notice() {
		if ( ! EST_Settings::languages() ) {
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'No translation language yet.', 'est' ),
				esc_url( admin_url( 'admin.php?page=est-languages' ) ),
				esc_html__( 'Add a language (e.g. Arabic) first.', 'est' )
			);
		}
	}

	/* =====================================================================
	 * Content & Export
	 * ================================================================== */

	public static function page_content() {
		$sources = EST_Content::sources();
		$langs   = EST_Settings::languages();
		$dicts   = array();
		foreach ( $langs as $code => $l ) {
			$dicts[ $code ] = EST_Store::dictionary( $code );
		}
		$zip = EST_Sheets::can_zip();
		?>
		<div class="wrap est-wrap">
			<h1><?php esc_html_e( 'Content & Export', 'est' ); ?></h1>
			<?php self::notice(); ?>
			<?php self::no_languages_notice(); ?>

			<p class="est-muted" style="max-width:900px">
				<?php esc_html_e( 'Every Elementor page, post, header, footer, popup and template, plus menus and the site title, is listed below. Export them to a spreadsheet: column A holds the English text and every language you added gets its own column. Fill in the language columns, then upload the file on the Import screen.', 'est' ); ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'est_export' ); ?>
				<input type="hidden" name="action" value="est_export">

				<div class="est-card">
					<h2><?php esc_html_e( 'Export', 'est' ); ?></h2>
					<p>
						<label><strong><?php esc_html_e( 'Format', 'est' ); ?></strong><br>
						<select name="format">
							<?php if ( $zip ) : ?>
								<option value="xlsx"><?php esc_html_e( 'Excel workbook (.xlsx) - one sheet per page', 'est' ); ?></option>
								<option value="zip"><?php esc_html_e( 'ZIP of CSV files - one CSV per page', 'est' ); ?></option>
							<?php endif; ?>
							<option value="csv_single"><?php esc_html_e( 'Single CSV - all unique texts of the selected pages', 'est' ); ?></option>
						</select></label>
					</p>
					<?php if ( $langs ) : ?>
						<p><strong><?php esc_html_e( 'Language columns', 'est' ); ?></strong><br>
						<?php foreach ( $langs as $code => $l ) : ?>
							<label style="margin-inline-end:14px"><input type="checkbox" name="langs[]" value="<?php echo esc_attr( $code ); ?>" checked> <?php echo esc_html( EST_Settings::header_label( $l ) ); ?></label>
						<?php endforeach; ?>
						</p>
					<?php endif; ?>
					<p>
						<label><input type="checkbox" name="include_existing" value="1" checked> <?php esc_html_e( 'Pre-fill translations that already exist (untick to get empty language cells)', 'est' ); ?></label><br>
						<label><input type="checkbox" name="only_missing" value="1"> <?php esc_html_e( 'Only rows that still need a translation', 'est' ); ?></label>
					</p>
					<p><?php esc_html_e( 'Tick pages in the table below to export only those; with nothing ticked, everything is exported.', 'est' ); ?></p>
					<?php submit_button( __( 'Download export', 'est' ), 'primary', 'submit', false ); ?>
				</div>

				<table class="widefat striped" style="max-width:1100px">
					<thead>
						<tr>
							<td class="check-column"><input type="checkbox" onclick="document.querySelectorAll('.est-src').forEach(c=>c.checked=this.checked)"></td>
							<th><?php esc_html_e( 'Type', 'est' ); ?></th>
							<th><?php esc_html_e( 'Title', 'est' ); ?></th>
							<th><?php esc_html_e( 'Texts', 'est' ); ?></th>
							<?php foreach ( $langs as $code => $l ) : ?>
								<th><?php echo esc_html( $l['name'] ); ?></th>
							<?php endforeach; ?>
							<th><?php esc_html_e( 'Actions', 'est' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					foreach ( $sources as $key => $s ) :
						$strings = EST_Content::strings( $key );
						$total   = count( $strings );
						$dl      = wp_nonce_url( admin_url( 'admin-post.php?action=est_export&include_existing=1&sources[]=' . rawurlencode( $key ) ), 'est_export' );
						?>
						<tr>
							<th class="check-column"><input type="checkbox" class="est-src" name="sources[]" value="<?php echo esc_attr( $key ); ?>"></th>
							<td><?php echo esc_html( $s['group'] ); ?></td>
							<td>
								<strong><?php echo esc_html( $s['title'] ); ?></strong>
								<?php if ( 'post' === $s['kind'] && 'publish' === get_post_status( $s['id'] ) && is_post_type_viewable( get_post_type( $s['id'] ) ) ) : ?>
									<br><span class="est-muted">
									<?php
									foreach ( $langs as $code => $l ) {
										if ( ! empty( $l['enabled'] ) ) {
											printf( '<a href="%s" target="_blank">%s</a> ', esc_url( EST_Router::localize_url( get_permalink( $s['id'] ), $code ) ), esc_html( sprintf( __( 'View %s', 'est' ), $l['name'] ) ) );
										}
									}
									?>
									</span>
								<?php endif; ?>
							</td>
							<td><?php echo (int) $total; ?></td>
							<?php
							foreach ( $langs as $code => $l ) :
								$done = 0;
								foreach ( $strings as $t ) {
									$done += isset( $dicts[ $code ][ md5( $t ) ] ) ? 1 : 0;
								}
								$pct = $total ? (int) round( 100 * $done / $total ) : 100;
								?>
								<td><span class="est-progress"><span style="width:<?php echo (int) $pct; ?>%"></span></span><?php echo (int) $done . '/' . (int) $total; ?></td>
							<?php endforeach; ?>
							<td>
								<?php if ( $zip ) : ?>
									<a href="<?php echo esc_url( $dl . '&format=xlsx' ); ?>">XLSX</a> |
								<?php endif; ?>
								<a href="<?php echo esc_url( $dl . '&format=csv_single' ); ?>">CSV</a>
								<?php if ( $langs ) : ?>
									| <a href="<?php echo esc_url( admin_url( 'admin.php?page=est-editor&source=' . rawurlencode( $key ) ) ); ?>"><?php esc_html_e( 'Translate', 'est' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</form>
		</div>
		<?php
	}

	public static function handle_export() {
		self::check( 'est_export' );
		// phpcs:disable WordPress.Security.NonceVerification
		$keys = isset( $_REQUEST['sources'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['sources'] ) ) : array();
		if ( ! $keys ) {
			$keys = array_keys( EST_Content::sources() );
		}
		$codes = isset( $_REQUEST['langs'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_REQUEST['langs'] ) ) : array_keys( EST_Settings::languages() );
		$fmt   = isset( $_REQUEST['format'] ) ? sanitize_key( wp_unslash( $_REQUEST['format'] ) ) : 'xlsx';
		$fill  = ! empty( $_REQUEST['include_existing'] );
		$miss  = ! empty( $_REQUEST['only_missing'] );
		// phpcs:enable

		$sheets = EST_Transfer::build_sheets( $keys, $codes, $fill, $miss );
		if ( ! $sheets ) {
			self::back( 'est', __( 'Nothing to export: every selected text is already translated.', 'est' ), true );
		}

		$base = 1 === count( $keys ) && ( $src = EST_Content::source( $keys[0] ) )
			? sanitize_file_name( $src['label'] )
			: sanitize_file_name( wp_parse_url( home_url(), PHP_URL_HOST ) . '-translations' );
		$base .= '-' . gmdate( 'Y-m-d' );

		if ( ( 'xlsx' === $fmt || 'zip' === $fmt ) && ! EST_Sheets::can_zip() ) {
			$fmt = 'csv_single';
		}

		if ( 'csv_single' === $fmt || 'csv' === $fmt ) {
			$csv = EST_Sheets::csv_write( EST_Transfer::merge_sheets( $sheets ) );
			self::download( $csv, $base . '.csv', 'text/csv; charset=utf-8' );
		}

		$tmp = wp_tempnam( 'est-export' );
		$ok  = 'zip' === $fmt ? EST_Sheets::zip_csv_write( $sheets, $tmp ) : EST_Sheets::xlsx_write( $sheets, $tmp );
		if ( ! $ok ) {
			self::back( 'est', __( 'Could not create the export file.', 'est' ), true );
		}
		$data = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		wp_delete_file( $tmp );
		if ( 'zip' === $fmt ) {
			self::download( $data, $base . '.zip', 'application/zip' );
		}
		self::download( $data, $base . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
	}

	private static function download( $data, $filename, $type ) {
		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $data ) );
		echo $data; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/* =====================================================================
	 * Import
	 * ================================================================== */

	public static function page_import() {
		$report = get_transient( 'est_import_report_' . get_current_user_id() );
		if ( $report ) {
			delete_transient( 'est_import_report_' . get_current_user_id() );
		}
		?>
		<div class="wrap est-wrap">
			<h1><?php esc_html_e( 'Import translations', 'est' ); ?></h1>
			<?php self::notice(); ?>

			<?php if ( $report ) : ?>
				<div class="est-card">
					<h2><?php esc_html_e( 'Import result', 'est' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: sheets, 2: rows */
							esc_html__( 'Read %1$d sheet(s) with %2$d English row(s).', 'est' ),
							(int) $report['sheets'],
							(int) $report['rows']
						);
						?>
					</p>
					<ul>
						<?php foreach ( $report['saved'] as $code => $n ) : ?>
							<?php $l = EST_Settings::language( $code ); ?>
							<li><strong><?php echo esc_html( $l ? EST_Settings::header_label( $l ) : $code ); ?>:</strong> <?php echo (int) $n; ?> <?php esc_html_e( 'translations saved', 'est' ); ?></li>
						<?php endforeach; ?>
						<?php if ( ! $report['saved'] ) : ?>
							<li><?php esc_html_e( 'No translations found. Make sure the language columns (B, C...) are filled in.', 'est' ); ?></li>
						<?php endif; ?>
						<?php if ( $report['skipped'] ) : ?>
							<li><?php echo (int) $report['skipped']; ?> <?php esc_html_e( 'existing translations kept (overwrite was off).', 'est' ); ?></li>
						<?php endif; ?>
						<?php if ( $report['added'] ) : ?>
							<li><?php esc_html_e( 'New languages added:', 'est' ); ?> <?php echo esc_html( implode( ', ', $report['added'] ) ); ?></li>
						<?php endif; ?>
						<?php if ( $report['unknown'] ) : ?>
							<li><?php esc_html_e( 'Ignored columns (not a language):', 'est' ); ?> <?php echo esc_html( implode( ', ', $report['unknown'] ) ); ?></li>
						<?php endif; ?>
						<?php foreach ( $report['messages'] as $m ) : ?>
							<li><?php echo esc_html( $m ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="est-card">
				<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'est_import' ); ?>
					<input type="hidden" name="action" value="est_import">
					<p><input type="file" name="est_files[]" accept=".csv,.xlsx,.zip" multiple required></p>
					<p>
						<label><input type="checkbox" name="overwrite" value="1" checked> <?php esc_html_e( 'Overwrite existing translations with the values in the file', 'est' ); ?></label><br>
						<label><input type="checkbox" name="add_languages" value="1" checked> <?php esc_html_e( 'Add languages found in the header row that are not set up yet (e.g. a new "French (fr)" column)', 'est' ); ?></label>
					</p>
					<?php submit_button( __( 'Import', 'est' ) ); ?>
				</form>
			</div>

			<div class="est-card">
				<h2><?php esc_html_e( 'How the file must look', 'est' ); ?></h2>
				<table class="widefat" style="max-width:700px">
					<tr><th>A</th><th>B</th><th>C</th></tr>
					<tr><td><strong>English (en)</strong></td><td><strong>Arabic (ar)</strong></td><td><strong>French (fr)</strong></td></tr>
					<tr><td>Contact us</td><td dir="rtl">اتصل بنا</td><td>Contactez-nous</td></tr>
					<tr><td>About Us</td><td dir="rtl">من نحن</td><td></td></tr>
				</table>
				<ul style="list-style:disc;padding-inline-start:20px">
					<li><?php esc_html_e( 'Row 1 is the header. Column A is always the English text, exactly as exported.', 'est' ); ?></li>
					<li><?php esc_html_e( 'Each other column is one language. The header can be "Arabic (ar)", "ar" or "Arabic".', 'est' ); ?></li>
					<li><?php esc_html_e( 'Empty cells are skipped and never delete an existing translation.', 'est' ); ?></li>
					<li><?php esc_html_e( 'Texts are matched by their English text, so the same English text gets the same translation on every page.', 'est' ); ?></li>
					<li><?php esc_html_e( 'You can upload the exported .xlsx workbook, a .zip of CSVs, or one or more .csv files. From Excel, save CSV as "CSV UTF-8" so Arabic is kept.', 'est' ); ?></li>
					<li><?php esc_html_e( 'Some cells contain HTML (for example <p>...</p> or <strong>). Keep the tags and translate only the text between them.', 'est' ); ?></li>
				</ul>
			</div>
		</div>
		<?php
	}

	public static function handle_import() {
		self::check( 'est_import' );
		if ( empty( $_FILES['est_files'] ) || empty( $_FILES['est_files']['name'] ) ) {
			self::back( 'est-import', __( 'Please choose a file.', 'est' ), true );
		}
		$files  = $_FILES['est_files']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$sheets = array();
		foreach ( (array) $files['name'] as $i => $name ) {
			if ( UPLOAD_ERR_OK !== (int) $files['error'][ $i ] || ! is_uploaded_file( $files['tmp_name'][ $i ] ) ) {
				continue;
			}
			$name = sanitize_file_name( $name );
			$read = EST_Transfer::read_file( $files['tmp_name'][ $i ], $name );
			if ( is_wp_error( $read ) ) {
				self::back( 'est-import', $read->get_error_message(), true );
			}
			foreach ( $read as $sheet => $rows ) {
				$sheets[ $name . ' / ' . $sheet ] = $rows;
			}
		}
		if ( ! $sheets ) {
			self::back( 'est-import', __( 'The file could not be read.', 'est' ), true );
		}

		$report = EST_Transfer::import( $sheets, ! empty( $_POST['add_languages'] ), ! empty( $_POST['overwrite'] ) );
		set_transient( 'est_import_report_' . get_current_user_id(), $report, HOUR_IN_SECONDS );
		self::flush_caches();
		self::back( 'est-import', __( 'Import finished.', 'est' ) );
	}

	/* =====================================================================
	 * Editor
	 * ================================================================== */

	public static function page_editor() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$langs   = EST_Settings::languages();
		$sources = EST_Content::sources();
		$key     = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		$code    = isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : (string) key( $langs );
		// phpcs:enable
		$lang = EST_Settings::language( $code );
		?>
		<div class="wrap est-wrap">
			<h1><?php esc_html_e( 'Edit translations', 'est' ); ?></h1>
			<?php self::notice(); ?>
			<?php self::no_languages_notice(); ?>

			<form method="get" class="est-card">
				<input type="hidden" name="page" value="est-editor">
				<select name="source">
					<option value=""><?php esc_html_e( '- Choose a page / template -', 'est' ); ?></option>
					<?php foreach ( $sources as $k => $s ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $key, $k ); ?>><?php echo esc_html( $s['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="lang">
					<?php foreach ( $langs as $c => $l ) : ?>
						<option value="<?php echo esc_attr( $c ); ?>" <?php selected( $code, $c ); ?>><?php echo esc_html( EST_Settings::header_label( $l ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php submit_button( __( 'Open', 'est' ), 'secondary', '', false ); ?>
			</form>

			<?php
			if ( $key && $lang && EST_Content::source( $key ) ) :
				$strings = EST_Content::strings( $key );
				$dict    = EST_Store::dictionary( $code );
				?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'est_save_translations' ); ?>
					<input type="hidden" name="action" value="est_save_translations">
					<input type="hidden" name="source" value="<?php echo esc_attr( $key ); ?>">
					<input type="hidden" name="lang" value="<?php echo esc_attr( $code ); ?>">
					<table class="widefat striped est-editor" style="max-width:1300px">
						<thead><tr>
							<th style="width:48%"><?php echo esc_html( EST_Settings::header_label( EST_Settings::default_language() ) ); ?></th>
							<th><?php echo esc_html( EST_Settings::header_label( $lang ) ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $strings as $text ) : ?>
							<?php $h = md5( $text ); ?>
							<tr>
								<td><div class="est-source"><?php echo esc_html( $text ); ?></div></td>
								<td><textarea name="t[<?php echo esc_attr( $h ); ?>]" dir="<?php echo esc_attr( $lang['dir'] ); ?>" rows="<?php echo (int) min( 8, max( 2, ceil( mb_strlen( $text ) / 70 ) ) ); ?>"><?php echo esc_textarea( $dict[ $h ] ?? '' ); ?></textarea></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?php submit_button( __( 'Save translations', 'est' ) ); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function handle_save_translations() {
		self::check( 'est_save_translations' );
		$key  = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$code = isset( $_POST['lang'] ) ? sanitize_text_field( wp_unslash( $_POST['lang'] ) ) : '';
		$in   = isset( $_POST['t'] ) ? (array) wp_unslash( $_POST['t'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! EST_Settings::language( $code ) || ! EST_Content::source( $key ) ) {
			self::back( 'est-editor', __( 'Unknown page or language.', 'est' ), true );
		}
		$dict  = EST_Store::dictionary( $code );
		$saved = 0;
		foreach ( EST_Content::strings( $key ) as $text ) {
			$h = md5( $text );
			if ( ! array_key_exists( $h, $in ) ) {
				continue;
			}
			$value = trim( (string) $in[ $h ] );
			if ( $value === ( $dict[ $h ] ?? '' ) ) {
				continue;
			}
			EST_Store::save( $code, $text, $value );
			$saved++;
		}
		self::flush_caches();
		self::back(
			'est-editor',
			/* translators: %d: count */
			sprintf( __( '%d translation(s) updated.', 'est' ), $saved ),
			false,
			array(
				'source' => rawurlencode( $key ),
				'lang'   => $code,
			)
		);
	}

	/* =====================================================================
	 * Languages & settings
	 * ================================================================== */

	public static function page_languages() {
		$s       = EST_Settings::all();
		$langs   = EST_Settings::languages();
		$presets = EST_Settings::presets();
		$home    = untrailingslashit( get_option( 'home' ) );
		?>
		<div class="wrap est-wrap">
			<h1><?php esc_html_e( 'Languages & Settings', 'est' ); ?></h1>
			<?php self::notice(); ?>

			<div class="est-card">
				<h2><?php esc_html_e( 'Add a language', 'est' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'est_add_language' ); ?>
					<input type="hidden" name="action" value="est_add_language">
					<select name="preset">
						<option value=""><?php esc_html_e( '- Choose a language -', 'est' ); ?></option>
						<?php foreach ( $presets as $code => $p ) : ?>
							<?php
							if ( isset( $langs[ $code ] ) || EST_Settings::default_code() === $code ) {
								continue;
							}
							?>
							<option value="<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $p['name'] . ' - ' . $p['native'] . ' (' . $code . ', ' . strtoupper( $p['dir'] ) . ')' ); ?></option>
						<?php endforeach; ?>
					</select>
					<span class="est-muted"><?php esc_html_e( 'or custom code:', 'est' ); ?></span>
					<input type="text" name="code" placeholder="e.g. ar" size="6">
					<input type="text" name="name" placeholder="<?php esc_attr_e( 'Name', 'est' ); ?>" size="12">
					<select name="dir"><option value="ltr">LTR</option><option value="rtl">RTL</option></select>
					<?php submit_button( __( 'Add language', 'est' ), 'primary', 'submit', false ); ?>
				</form>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'est_save_settings' ); ?>
				<input type="hidden" name="action" value="est_save_settings">

				<div class="est-card">
					<h2><?php esc_html_e( 'Languages', 'est' ); ?></h2>
					<table class="widefat est-lang-table">
						<thead><tr>
							<th style="width:70px"><?php esc_html_e( 'Code', 'est' ); ?></th>
							<th><?php esc_html_e( 'Name', 'est' ); ?></th>
							<th><?php esc_html_e( 'Native name', 'est' ); ?></th>
							<th style="width:90px"><?php esc_html_e( 'WP locale', 'est' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Direction', 'est' ); ?></th>
							<th style="width:70px"><?php esc_html_e( 'Enabled', 'est' ); ?></th>
							<th><?php esc_html_e( 'URL', 'est' ); ?></th>
							<th><?php esc_html_e( 'Translations', 'est' ); ?></th>
						</tr></thead>
						<tbody>
							<tr>
								<td><input type="text" name="default_code" value="<?php echo esc_attr( $s['default_code'] ); ?>"></td>
								<td><input type="text" name="default_name" value="<?php echo esc_attr( $s['default_name'] ); ?>"></td>
								<td><input type="text" name="default_native" value="<?php echo esc_attr( $s['default_native'] ); ?>"></td>
								<td class="est-muted">-</td>
								<td><select name="default_dir"><option value="ltr" <?php selected( $s['default_dir'], 'ltr' ); ?>>LTR</option><option value="rtl" <?php selected( $s['default_dir'], 'rtl' ); ?>>RTL</option></select></td>
								<td class="est-muted"><?php esc_html_e( 'default', 'est' ); ?></td>
								<td><code><?php echo esc_html( $home ); ?>/</code></td>
								<td class="est-muted"><?php esc_html_e( 'Source (column A)', 'est' ); ?></td>
							</tr>
							<?php foreach ( $langs as $code => $l ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $code ); ?></strong></td>
									<td><input type="text" name="languages[<?php echo esc_attr( $code ); ?>][name]" value="<?php echo esc_attr( $l['name'] ); ?>"></td>
									<td><input type="text" name="languages[<?php echo esc_attr( $code ); ?>][native]" value="<?php echo esc_attr( $l['native'] ); ?>" dir="auto"></td>
									<td><input type="text" name="languages[<?php echo esc_attr( $code ); ?>][locale]" value="<?php echo esc_attr( $l['locale'] ); ?>"></td>
									<td><select name="languages[<?php echo esc_attr( $code ); ?>][dir]"><option value="ltr" <?php selected( $l['dir'], 'ltr' ); ?>>LTR</option><option value="rtl" <?php selected( $l['dir'], 'rtl' ); ?>>RTL</option></select></td>
									<td><input type="hidden" name="languages[<?php echo esc_attr( $code ); ?>][enabled]" value="0"><input type="checkbox" name="languages[<?php echo esc_attr( $code ); ?>][enabled]" value="1" <?php checked( ! empty( $l['enabled'] ) ); ?>></td>
									<td><a href="<?php echo esc_url( $home . '/' . $code . '/' ); ?>" target="_blank"><code><?php echo esc_html( $home . '/' . $code . '/' ); ?></code></a></td>
									<td>
										<?php echo (int) EST_Store::count( $code ); ?>
										&nbsp;<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=est_delete_language&code=' . rawurlencode( $code ) ), 'est_delete_language' ) ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Remove this language and ALL its translations?', 'est' ) ); ?>')" style="color:#b32d2e"><?php esc_html_e( 'Remove', 'est' ); ?></a>
									</td>
								</tr>
								<tr>
									<td></td>
									<td colspan="7">
										<details>
											<summary class="est-muted"><?php printf( esc_html__( 'Custom CSS for %s pages (fonts, RTL fixes)', 'est' ), esc_html( $l['name'] ) ); ?></summary>
											<textarea name="languages[<?php echo esc_attr( $code ); ?>][css]" rows="5" style="width:100%;font-family:monospace" placeholder="<?php echo esc_attr( "body, h1, h2, h3, h4, p, a, li { font-family: 'Cairo', sans-serif; }" ); ?>"><?php echo esc_textarea( $l['css'] ); ?></textarea>
										</details>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="est-card">
					<h2><?php esc_html_e( 'Settings', 'est' ); ?></h2>
					<p><label><input type="checkbox" name="localize_links" value="1" <?php checked( $s['localize_links'] ); ?>> <?php esc_html_e( 'Keep visitors in their language: rewrite internal links on translated pages (e.g. /contact-us/ becomes /ar/contact-us/).', 'est' ); ?></label></p>
					<p><label><input type="checkbox" name="html_fallback" value="1" <?php checked( $s['html_fallback'] ); ?>> <?php esc_html_e( 'Also translate matching plain text in the final page HTML (catches theme and widget texts that are not stored in Elementor data).', 'est' ); ?></label></p>
					<p><label><input type="checkbox" name="hreflang" value="1" <?php checked( $s['hreflang'] ); ?>> <?php esc_html_e( 'Output hreflang links for SEO.', 'est' ); ?></label></p>
					<p>
						<label><strong><?php esc_html_e( 'Extra Elementor setting keys to translate', 'est' ); ?></strong><br>
						<input type="text" name="extra_keys" value="<?php echo esc_attr( $s['extra_keys'] ); ?>" class="regular-text" placeholder="e.g. my_widget_label, promo_line"></label><br>
						<span class="est-muted"><?php esc_html_e( 'Only needed if a widget text does not appear in the export. Comma separated.', 'est' ); ?></span>
					</p>
					<p><label><input type="checkbox" name="delete_uninstall" value="1" <?php checked( $s['delete_uninstall'] ); ?>> <?php esc_html_e( 'Delete all translations and settings when the plugin is deleted.', 'est' ); ?></label></p>
				</div>

				<?php submit_button( __( 'Save changes', 'est' ) ); ?>
			</form>

			<div class="est-card">
				<h2><?php esc_html_e( 'Language switcher', 'est' ); ?></h2>
				<p><?php esc_html_e( 'In Elementor, open your Header template and drag the "Language Switcher" widget (search "language") where you want it. Choose dropdown or inline list and style it in the Style tab.', 'est' ); ?></p>
				<p><?php esc_html_e( 'Outside Elementor you can use the shortcode:', 'est' ); ?> <code class="est-big">[est_language_switcher style="dropdown" display="native"]</code></p>
				<p class="est-muted"><?php esc_html_e( 'style: dropdown | list — display: native | name | code | code_native — show_current: yes | no — separator: any text — open_on: click | hover', 'est' ); ?></p>
			</div>
		</div>
		<?php
	}

	public static function handle_add_language() {
		self::check( 'est_add_language' );
		$preset = isset( $_POST['preset'] ) ? sanitize_text_field( wp_unslash( $_POST['preset'] ) ) : '';
		$code   = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';
		if ( '' !== $preset ) {
			$p    = EST_Settings::presets()[ $preset ] ?? null;
			$data = $p ? array_merge( $p, array( 'enabled' => 1 ) ) : array();
		} else {
			$data = array(
				'code'    => $code,
				'name'    => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
				'dir'     => isset( $_POST['dir'] ) && 'rtl' === $_POST['dir'] ? 'rtl' : 'ltr',
				'enabled' => 1,
			);
			if ( '' === $data['name'] ) {
				unset( $data['name'] );
			}
		}
		$saved = $data ? EST_Settings::save_language( $data ) : false;
		if ( ! $saved ) {
			self::back( 'est-languages', __( 'Please choose a language or enter a valid code (e.g. "ar" or "pt-br") that is not the default language.', 'est' ), true );
		}
		self::back( 'est-languages', __( 'Language added. It now has its own column in every export.', 'est' ) );
	}

	public static function handle_delete_language() {
		self::check( 'est_delete_language' );
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( EST_Settings::language( $code ) ) {
			EST_Settings::delete_language( $code );
			EST_Store::delete_language( $code );
			self::flush_caches();
		}
		self::back( 'est-languages', __( 'Language removed.', 'est' ) );
	}

	public static function handle_save_settings() {
		self::check( 'est_save_settings' );
		$p = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$default = EST_Settings::sanitize_code( $p['default_code'] ?? 'en' );
		EST_Settings::update(
			array(
				'default_code'     => '' !== $default ? $default : 'en',
				'default_name'     => sanitize_text_field( $p['default_name'] ?? 'English' ),
				'default_native'   => sanitize_text_field( $p['default_native'] ?? 'English' ),
				'default_dir'      => ( 'rtl' === ( $p['default_dir'] ?? '' ) ) ? 'rtl' : 'ltr',
				'extra_keys'       => sanitize_text_field( $p['extra_keys'] ?? '' ),
				'html_fallback'    => empty( $p['html_fallback'] ) ? 0 : 1,
				'localize_links'   => empty( $p['localize_links'] ) ? 0 : 1,
				'hreflang'         => empty( $p['hreflang'] ) ? 0 : 1,
				'delete_uninstall' => empty( $p['delete_uninstall'] ) ? 0 : 1,
			)
		);
		foreach ( (array) ( $p['languages'] ?? array() ) as $code => $l ) {
			if ( EST_Settings::language( $code ) ) {
				$l['code'] = $code;
				EST_Settings::save_language( $l );
			}
		}
		self::flush_caches();
		self::back( 'est-languages', __( 'Settings saved.', 'est' ) );
	}

	/**
	 * Clear common page caches so visitors see new translations.
	 */
	private static function flush_caches() {
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
		do_action( 'litespeed_purge_all' );
		do_action( 'est_translations_updated' );
	}
}
