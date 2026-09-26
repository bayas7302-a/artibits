<?php
/**
 * Removes data only when "Delete all translations and settings when the
 * plugin is deleted" was ticked, so translations survive a reinstall.
 *
 * @package EST
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$est_settings = get_option( 'est_settings', array() );
if ( ! empty( $est_settings['delete_uninstall'] ) ) {
	global $wpdb;
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}est_translations" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	delete_option( 'est_settings' );
	delete_option( 'est_db_version' );
}
