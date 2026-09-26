<?php
/**
 * Plugin Name:       Elementor Sheet Translator
 * Description:       Translate an Elementor-built website (pages, headers, footers, popups, templates, menus) with spreadsheets. Export every page to Excel/CSV with English in the first column, fill in the language columns, import it back. Adds /ar/-style language URLs, correct RTL/LTR output and an Elementor Language Switcher widget.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ArtiBits
 * License:           GPL-2.0-or-later
 * Text Domain:       est
 */

defined( 'ABSPATH' ) || exit;

define( 'EST_VERSION', '1.1.0' );
define( 'EST_FILE', __FILE__ );
define( 'EST_DIR', plugin_dir_path( __FILE__ ) );
define( 'EST_URL', plugin_dir_url( __FILE__ ) );

require_once EST_DIR . 'includes/class-est-text.php';
require_once EST_DIR . 'includes/class-est-extractor.php';
require_once EST_DIR . 'includes/class-est-html.php';
require_once EST_DIR . 'includes/class-est-sheets.php';
require_once EST_DIR . 'includes/class-est-settings.php';
require_once EST_DIR . 'includes/class-est-store.php';
require_once EST_DIR . 'includes/class-est-router.php';
require_once EST_DIR . 'includes/class-est-content.php';
require_once EST_DIR . 'includes/class-est-frontend.php';
require_once EST_DIR . 'includes/class-est-switcher.php';
require_once EST_DIR . 'includes/class-est-transfer.php';

register_activation_hook( __FILE__, array( 'EST_Store', 'install' ) );

// The language prefix (/ar/...) must be detected and stripped from the request
// before WordPress parses it, so this runs immediately while plugins load.
EST_Router::detect();
EST_Router::init();
EST_Frontend::init();
EST_Switcher::init();

add_action( 'plugins_loaded', array( 'EST_Store', 'maybe_upgrade' ) );

if ( is_admin() ) {
	require_once EST_DIR . 'includes/class-est-admin.php';
	EST_Admin::init();
}

add_action(
	'elementor/widgets/register',
	function ( $widgets_manager ) {
		require_once EST_DIR . 'includes/elementor/class-est-switcher-widget.php';
		$widgets_manager->register( new EST_Switcher_Widget() );
	}
);
