<?php
/**
 * Minimal, dependency-free spreadsheet I/O: CSV (UTF-8 with BOM so Excel
 * shows Arabic correctly), XLSX workbooks (one worksheet per page) and ZIP
 * archives of CSV files.
 *
 * A "sheet" is an array of rows; a row is an array of cell strings.
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Sheets {

	/* ---------------------------------------------------------------------
	 * CSV
	 * ------------------------------------------------------------------ */

	public static function csv_write( array $rows ) {
		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, "\xEF\xBB\xBF" );
		foreach ( $rows as $row ) {
			fputcsv( $fh, array_map( 'strval', $row ), ',', '"', '' );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	public static function csv_read( $content ) {
		$content = (string) $content;
		// Excel "Unicode Text" / UTF-16 exports.
		if ( "\xFF\xFE" === substr( $content, 0, 2 ) ) {
			$content = mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16LE' );
		} elseif ( "\xFE\xFF" === substr( $content, 0, 2 ) ) {
			$content = mb_convert_encoding( substr( $content, 2 ), 'UTF-8', 'UTF-16BE' );
		} elseif ( "\xEF\xBB\xBF" === substr( $content, 0, 3 ) ) {
			$content = substr( $content, 3 );
		} elseif ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$content = mb_convert_encoding( $content, 'UTF-8', 'Windows-1256' );
		}

		// Detect the delimiter from the first line (Excel in some locales uses ";").
		$first = strtok( $content, "\n" );
		$delim = ',';
		$best  = 0;
		foreach ( array( ',', ';', "\t" ) as $candidate ) {
			$count = substr_count( (string) $first, $candidate );
			if ( $count > $best ) {
				$best  = $count;
				$delim = $candidate;
			}
		}

		$fh = fopen( 'php://temp', 'r+' );
		fwrite( $fh, $content );
		rewind( $fh );
		$rows = array();
		while ( false !== ( $row = fgetcsv( $fh, 0, $delim, '"', '' ) ) ) {
			if ( array( null ) === $row ) {
				continue; // Blank line.
			}
			$rows[] = array_map( 'strval', $row );
		}
		fclose( $fh );
		return $rows;
	}

	/* ---------------------------------------------------------------------
	 * XLSX
	 * ------------------------------------------------------------------ */

	public static function can_zip() {
		return class_exists( 'ZipArchive' );
	}

	/**
	 * @param array  $sheets name => rows.
	 * @param string $path   Destination file.
	 */
	public static function xlsx_write( array $sheets, $path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$names = array();
		foreach ( array_keys( $sheets ) as $name ) {
			$names[] = self::sheet_name( $name, $names );
		}
		$count = count( $names );

		$types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		for ( $i = 1; $i <= $count; $i++ ) {
			$types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		$types .= '</Types>';
		$zip->addFromString( '[Content_Types].xml', $types );

		$zip->addFromString(
			'_rels/.rels',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>'
		);

		$wb   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
		$rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		foreach ( $names as $i => $name ) {
			$n     = $i + 1;
			$wb   .= '<sheet name="' . self::xml( $name ) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
			$rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
		}
		$wb   .= '</sheets></workbook>';
		$rels .= '<Relationship Id="rId' . ( $count + 1 ) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
		$zip->addFromString( 'xl/workbook.xml', $wb );
		$zip->addFromString( 'xl/_rels/workbook.xml.rels', $rels );

		// Style 1 = bold header, style 2 = wrapped text aligned to top.
		$zip->addFromString(
			'xl/styles.xml',
			'<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf></cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>'
		);

		$i = 0;
		foreach ( $sheets as $rows ) {
			$i++;
			$zip->addFromString( 'xl/worksheets/sheet' . $i . '.xml', self::sheet_xml( $rows ) );
		}
		return $zip->close();
	}

	private static function sheet_xml( array $rows ) {
		$max_cols = 1;
		foreach ( $rows as $row ) {
			$max_cols = max( $max_cols, count( $row ) );
		}
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
			. '<cols><col min="1" max="' . $max_cols . '" width="60" customWidth="1"/></cols><sheetData>';
		foreach ( array_values( $rows ) as $r => $row ) {
			$rn   = $r + 1;
			$xml .= '<row r="' . $rn . '">';
			for ( $c = 0; $c < $max_cols; $c++ ) {
				$ref   = self::col_letter( $c ) . $rn;
				$style = 0 === $r ? 1 : 2;
				$value = isset( $row[ $c ] ) ? (string) $row[ $c ] : '';
				if ( '' === $value ) {
					$xml .= '<c r="' . $ref . '" s="' . $style . '"/>';
				} else {
					$xml .= '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::xml( $value ) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		return $xml . '</sheetData></worksheet>';
	}

	/**
	 * @return array name => rows
	 */
	public static function xlsx_read( $path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array();
		}
		$shared = array();
		$ss     = $zip->getFromName( 'xl/sharedStrings.xml' );
		if ( $ss ) {
			$doc = self::load_xml( $ss );
			if ( $doc ) {
				foreach ( $doc->si as $si ) {
					$shared[] = self::rich_text( $si );
				}
			}
		}

		$targets = array();
		$rels    = self::load_xml( (string) $zip->getFromName( 'xl/_rels/workbook.xml.rels' ) );
		if ( $rels ) {
			foreach ( $rels->Relationship as $rel ) {
				$target = (string) $rel['Target'];
				$target = 0 === strpos( $target, '/' ) ? ltrim( $target, '/' ) : 'xl/' . $target;
				$targets[ (string) $rel['Id'] ] = $target;
			}
		}

		$sheets = array();
		$wb     = self::load_xml( (string) $zip->getFromName( 'xl/workbook.xml' ) );
		if ( ! $wb ) {
			$zip->close();
			return array();
		}
		foreach ( $wb->sheets->sheet as $sheet ) {
			$attrs = $sheet->attributes( 'http://schemas.openxmlformats.org/officeDocument/2006/relationships' );
			$rid   = (string) $attrs['id'];
			if ( ! isset( $targets[ $rid ] ) ) {
				continue;
			}
			$sx = self::load_xml( (string) $zip->getFromName( $targets[ $rid ] ) );
			if ( ! $sx ) {
				continue;
			}
			$rows = array();
			foreach ( $sx->sheetData->row as $row ) {
				$cells = array();
				foreach ( $row->c as $cell ) {
					$idx  = isset( $cell['r'] ) ? self::col_index( (string) $cell['r'] ) : count( $cells );
					$type = (string) $cell['t'];
					if ( 's' === $type ) {
						$v = isset( $shared[ (int) $cell->v ] ) ? $shared[ (int) $cell->v ] : '';
					} elseif ( 'inlineStr' === $type ) {
						$v = self::rich_text( $cell->is );
					} else {
						$v = (string) $cell->v;
					}
					$cells[ $idx ] = $v;
				}
				if ( ! $cells ) {
					continue;
				}
				$line = array_fill( 0, max( array_keys( $cells ) ) + 1, '' );
				foreach ( $cells as $idx => $v ) {
					$line[ $idx ] = $v;
				}
				$rows[] = $line;
			}
			$sheets[ (string) $sheet['name'] ] = $rows;
		}
		$zip->close();
		return $sheets;
	}

	/* ---------------------------------------------------------------------
	 * ZIP of CSV files
	 * ------------------------------------------------------------------ */

	public static function zip_csv_write( array $sheets, $path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return false;
		}
		$used = array();
		foreach ( $sheets as $name => $rows ) {
			$file = self::file_name( $name, $used );
			$used[] = $file;
			$zip->addFromString( $file . '.csv', self::csv_write( $rows ) );
		}
		return $zip->close();
	}

	/**
	 * Read every .csv / .xlsx inside a ZIP.
	 *
	 * @return array name => rows
	 */
	public static function zip_read( $path ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return array();
		}
		$sheets = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( false !== strpos( $name, '__MACOSX' ) ) {
				continue;
			}
			$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
			if ( 'csv' === $ext ) {
				$sheets[ pathinfo( $name, PATHINFO_FILENAME ) ] = self::csv_read( $zip->getFromIndex( $i ) );
			} elseif ( 'xlsx' === $ext ) {
				$tmp = tempnam( sys_get_temp_dir(), 'est' );
				file_put_contents( $tmp, $zip->getFromIndex( $i ) );
				foreach ( self::xlsx_read( $tmp ) as $sheet => $rows ) {
					$sheets[ $sheet ] = $rows;
				}
				unlink( $tmp );
			}
		}
		$zip->close();
		return $sheets;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------ */

	/** Excel sheet names: max 31 chars, no []:*?/\ and unique. */
	public static function sheet_name( $name, array $used ) {
		$base = trim( preg_replace( '#[\[\]\:\*\?/\\\\]#', ' ', (string) $name ) );
		$base = '' === $base ? 'Sheet' : $base;
		$base = self::cut( $base, 31 );
		$try  = $base;
		$n    = 2;
		while ( in_array( mb_strtolower( $try ), array_map( 'mb_strtolower', $used ), true ) ) {
			$suffix = ' (' . $n++ . ')';
			$try    = self::cut( $base, 31 - strlen( $suffix ) ) . $suffix;
		}
		return $try;
	}

	public static function file_name( $name, array $used ) {
		$base = trim( preg_replace( '/[^\p{L}\p{N}\-_ \.]+/u', '', (string) $name ) );
		$base = '' === $base ? 'sheet' : self::cut( preg_replace( '/\s+/', '-', $base ), 80 );
		$try  = $base;
		$n    = 2;
		while ( in_array( $try, $used, true ) ) {
			$try = $base . '-' . $n++;
		}
		return $try;
	}

	private static function cut( $s, $len ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $len ) : substr( $s, 0, $len );
	}

	public static function col_letter( $index ) {
		$s = '';
		for ( $n = $index + 1; $n > 0; $n = intdiv( $n - 1, 26 ) ) {
			$s = chr( 65 + ( $n - 1 ) % 26 ) . $s;
		}
		return $s;
	}

	public static function col_index( $ref ) {
		preg_match( '/^[A-Z]+/i', $ref, $m );
		$n = 0;
		foreach ( str_split( strtoupper( $m[0] ?? 'A' ) ) as $ch ) {
			$n = $n * 26 + ( ord( $ch ) - 64 );
		}
		return $n - 1;
	}

	private static function xml( $s ) {
		// Strip characters that are illegal in XML 1.0.
		$s = preg_replace( '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', (string) $s );
		return htmlspecialchars( (string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8' );
	}

	private static function load_xml( $xml ) {
		if ( '' === $xml ) {
			return null;
		}
		$prev = libxml_use_internal_errors( true );
		$doc  = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NONET );
		libxml_use_internal_errors( $prev );
		return false === $doc ? null : $doc;
	}

	private static function rich_text( $node ) {
		if ( null === $node ) {
			return '';
		}
		if ( isset( $node->t ) ) {
			return (string) $node->t;
		}
		$s = '';
		foreach ( $node->r as $run ) {
			$s .= (string) $run->t;
		}
		return $s;
	}
}
