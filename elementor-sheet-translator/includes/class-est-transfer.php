<?php
/**
 * Builds export spreadsheets and imports filled-in ones.
 *
 * Layout of every sheet:
 *   Row 1:  English (en) | Arabic (ar) | French (fr) ...
 *   Row 2+: English text | Arabic text | French text ...
 *
 * On import each row is matched by its English text, so rows can be
 * reordered, merged into one sheet or split across files freely.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Transfer {

	/**
	 * @param string[] $source_keys       Sources to export.
	 * @param string[] $codes             Language columns.
	 * @param bool     $include_existing  Pre-fill translations already saved.
	 * @param bool     $only_missing      Only rows missing a translation in one of $codes.
	 * @return array   sheet name => rows
	 */
	public static function build_sheets( array $source_keys, array $codes, $include_existing = true, $only_missing = false, $with_images = false ) {
		$header = array( EST_Settings::header_label( EST_Settings::default_language() ) );
		$dicts  = array();
		foreach ( $codes as $code ) {
			$lang = EST_Settings::language( $code );
			if ( ! $lang ) {
				continue;
			}
			$header[]       = EST_Settings::header_label( $lang );
			$dicts[ $code ] = EST_Store::dictionary( $code );
		}

		$sheets = array();
		foreach ( $source_keys as $key ) {
			$source = EST_Content::source( $key );
			if ( ! $source ) {
				continue;
			}
			$rows = array( $header );
			foreach ( EST_Content::strings( $key ) as $text ) {
				$hash    = md5( $text );
				$row     = array( $text );
				$missing = false;
				foreach ( $dicts as $dict ) {
					$has     = isset( $dict[ $hash ] );
					$missing = $missing || ! $has;
					$row[]   = ( $include_existing && $has ) ? $dict[ $hash ] : '';
				}
				if ( $only_missing && ! $missing ) {
					continue;
				}
				$rows[] = $row;
			}
			if ( $only_missing && count( $rows ) < 2 ) {
				continue;
			}
			$sheets[ $source['label'] ] = $rows;
		}

		// Images go on their own sheet, never mixed with text.
		if ( $with_images ) {
			$imgs = array();
			foreach ( $codes as $code ) {
				$imgs[ $code ] = EST_Store::images( $code );
			}
			$rows = array( $header );
			$seen = array();
			foreach ( $source_keys as $key ) {
				foreach ( EST_Content::images( $key ) as $url ) {
					if ( isset( $seen[ $url ] ) ) {
						continue;
					}
					$seen[ $url ] = true;
					$row          = array( $url );
					foreach ( array_keys( $dicts ) as $code ) {
						$row[] = ( $include_existing && isset( $imgs[ $code ][ $url ] ) ) ? $imgs[ $code ][ $url ] : '';
					}
					$rows[] = $row;
				}
			}
			if ( count( $rows ) > 1 ) {
				$sheets['Images'] = $rows;
			}
		}
		return $sheets;
	}

	/**
	 * All strings of the given sources in one sheet, de-duplicated.
	 */
	public static function merge_sheets( array $sheets ) {
		$merged = array();
		$seen   = array();
		foreach ( $sheets as $rows ) {
			foreach ( $rows as $i => $row ) {
				if ( 0 === $i ) {
					if ( ! $merged ) {
						$merged[] = $row;
					}
					continue;
				}
				$h = md5( $row[0] );
				if ( ! isset( $seen[ $h ] ) ) {
					$seen[ $h ] = true;
					$merged[]   = $row;
				}
			}
		}
		return $merged;
	}

	/**
	 * Import parsed sheets.
	 *
	 * @param array $sheets        name => rows.
	 * @param bool  $add_languages Create languages found in headers but not configured yet.
	 * @param bool  $overwrite     Replace existing translations (otherwise only fill gaps).
	 * @return array report
	 */
	public static function import( array $sheets, $add_languages = true, $overwrite = true ) {
		$report = array(
			'sheets'   => 0,
			'rows'     => 0,
			'saved'    => array(),
			'skipped'  => 0,
			'added'    => array(),
			'unknown'  => array(),
			'messages' => array(),
		);
		$default = EST_Settings::default_code();

		foreach ( $sheets as $name => $rows ) {
			if ( count( $rows ) < 2 ) {
				continue;
			}
			$report['sheets']++;
			$header = array_shift( $rows );
			$map    = array();
			foreach ( $header as $col => $cell ) {
				if ( 0 === $col ) {
					continue;
				}
				$code = EST_Settings::code_from_header( $cell );
				if ( '' === $code ) {
					if ( '' !== trim( (string) $cell ) ) {
						$report['unknown'][ trim( (string) $cell ) ] = true;
					}
					continue;
				}
				if ( $code === $default ) {
					continue;
				}
				if ( ! EST_Settings::language( $code ) ) {
					// Only create languages from an explicit "Name (code)" header or a known code,
					// so a stray "Notes"-style column never becomes a language.
					$explicit = (bool) preg_match( '/\([^)]+\)\s*$/', (string) $cell );
					if ( ! $add_languages || ( ! $explicit && ! isset( EST_Settings::presets()[ $code ] ) ) ) {
						$report['unknown'][ trim( (string) $cell ) ] = true;
						continue;
					}
					$name_guess = trim( preg_replace( '/\([^)]*\)\s*$/', '', (string) $cell ) );
					$preset     = EST_Settings::presets()[ $code ] ?? array();
					EST_Settings::save_language(
						array(
							'code' => $code,
							'name' => $preset['name'] ?? ( '' !== $name_guess && strtolower( $name_guess ) !== $code ? $name_guess : strtoupper( $code ) ),
						)
					);
					$report['added'][ $code ] = true;
				}
				$map[ $col ] = $code;
			}
			if ( ! $map ) {
				$report['messages'][] = sprintf( 'Sheet "%s": no language columns found in the first row.', $name );
				continue;
			}

			foreach ( $rows as $row ) {
				$source = EST_Text::normalize( $row[0] ?? '' );
				if ( '' === $source ) {
					continue;
				}
				$report['rows']++;
				$is_image = EST_Extractor::is_image_url( $source );
				foreach ( $map as $col => $code ) {
					$value = isset( $row[ $col ] ) ? trim( (string) $row[ $col ] ) : '';
					if ( '' === $value ) {
						continue; // Empty cells never erase existing translations.
					}
					if ( $is_image ) {
						// Image rows (column A is an image URL) map automatically.
						if ( preg_match( '#^(https?:)?//#i', $value ) && ( $overwrite || ! isset( EST_Store::images( $code )[ $source ] ) ) ) {
							EST_Store::save( 'img:' . $code, $source, esc_url_raw( $value ) );
							$report['images'] = ( $report['images'] ?? 0 ) + 1;
						}
						continue;
					}
					if ( ! $overwrite && null !== EST_Store::get( $code, $source ) ) {
						$report['skipped']++;
						continue;
					}
					if ( EST_Store::save( $code, $source, $value ) ) {
						$report['saved'][ $code ] = ( $report['saved'][ $code ] ?? 0 ) + 1;
					}
				}
			}
		}
		$report['added']   = array_keys( $report['added'] );
		$report['unknown'] = array_keys( $report['unknown'] );
		return $report;
	}

	/**
	 * Parse an uploaded file into sheets based on its extension.
	 */
	public static function read_file( $path, $filename ) {
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( 'csv' === $ext || 'txt' === $ext ) {
			return array( pathinfo( $filename, PATHINFO_FILENAME ) => EST_Sheets::csv_read( file_get_contents( $path ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		if ( ! EST_Sheets::can_zip() ) {
			return new WP_Error( 'est_zip', __( 'The PHP Zip extension is not available on this server. Please upload CSV files instead.', 'est' ) );
		}
		if ( 'xlsx' === $ext ) {
			return EST_Sheets::xlsx_read( $path );
		}
		if ( 'zip' === $ext ) {
			return EST_Sheets::zip_read( $path );
		}
		return new WP_Error( 'est_type', __( 'Unsupported file type. Upload a .csv, .xlsx or .zip file.', 'est' ) );
	}
}
