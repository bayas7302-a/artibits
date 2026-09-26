<?php
/**
 * Translation storage: one row per (language, English source string).
 *
 * @package EST
 */

defined( 'ABSPATH' ) || exit;

class EST_Store {

	const DB_VERSION = '1';

	private static $dicts = array();

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'est_translations';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$collate = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				lang varchar(20) NOT NULL,
				source_hash char(32) NOT NULL,
				source longtext NOT NULL,
				translation longtext NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY lang_hash (lang,source_hash),
				KEY source_hash (source_hash)
			) {$collate};"
		);
		update_option( 'est_db_version', self::DB_VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'est_db_version' ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * All translations for a language: source_hash => translation.
	 */
	public static function dictionary( $lang ) {
		global $wpdb;
		if ( ! isset( self::$dicts[ $lang ] ) ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( 'SELECT source_hash, translation FROM ' . self::table() . ' WHERE lang = %s', $lang ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				ARRAY_N
			);
			$dict = array();
			foreach ( (array) $rows as $row ) {
				if ( '' !== $row[1] ) {
					$dict[ $row[0] ] = $row[1];
				}
			}
			self::$dicts[ $lang ] = $dict;
		}
		return self::$dicts[ $lang ];
	}

	public static function get( $lang, $source ) {
		$dict = self::dictionary( $lang );
		$hash = EST_Text::hash( $source );
		return isset( $dict[ $hash ] ) ? $dict[ $hash ] : null;
	}

	/**
	 * Insert or update a translation. An empty translation deletes it.
	 */
	public static function save( $lang, $source, $translation ) {
		global $wpdb;
		$source = EST_Text::normalize( $source );
		if ( '' === $source ) {
			return false;
		}
		$hash        = md5( $source );
		$translation = str_replace( array( "\r\n", "\r" ), "\n", trim( (string) $translation ) );
		unset( self::$dicts[ $lang ] );

		if ( '' === $translation ) {
			return (bool) $wpdb->delete( self::table(), array( 'lang' => $lang, 'source_hash' => $hash ) );
		}
		return false !== $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . self::table() . ' (lang, source_hash, source, translation, updated_at) VALUES (%s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE source = VALUES(source), translation = VALUES(translation), updated_at = VALUES(updated_at)', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$lang,
				$hash,
				$source,
				$translation,
				current_time( 'mysql', true )
			)
		);
	}

	public static function count( $lang ) {
		return count( self::dictionary( $lang ) );
	}

	public static function delete_language( $lang ) {
		global $wpdb;
		unset( self::$dicts[ $lang ] );
		$wpdb->delete( self::table(), array( 'lang' => $lang ) );
	}

	public static function drop() {
		global $wpdb;
		$wpdb->query( 'DROP TABLE IF EXISTS ' . self::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}
}
