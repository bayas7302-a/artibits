<?php
/**
 * Plugin Name:       Cookie Consent Banner
 * Description:       GDPR / CNIL cookie banner (Allow all / Reject all / Cookie settings). Blocks Google Analytics (including Site Kit's Google tag) and other trackers until the visitor accepts, and sends Google Consent Mode v2 signals. Texts per language, works with Elementor Sheet Translator.
 * Version:           1.0.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            ArtiBits
 * License:           GPL-2.0-or-later
 * Text Domain:       ccb
 */

defined( 'ABSPATH' ) || exit;

define( 'CCB_VERSION', '1.0.1' );
define( 'CCB_URL', plugin_dir_url( __FILE__ ) );
define( 'CCB_DIR', plugin_dir_path( __FILE__ ) );

require_once CCB_DIR . 'includes/class-ccb.php';
CCB::init();

if ( is_admin() ) {
	require_once CCB_DIR . 'includes/class-ccb-admin.php';
	CCB_Admin::init();
}
