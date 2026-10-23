<?php
/**
 * Brand colours, inline icons and the Poppins font (used by every other file).
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================
 * SOHARON — BRAND SETTINGS (change colours here only)
 * ============================================
 */
define( 'SOHARON_RED', '#DB141D' );
define( 'SOHARON_RED_DARK', '#B30F17' );

function soharon_brand_vars( $css, $extra = array() ) {
    return strtr( $css, array_merge( array(
        '{RED}'      => SOHARON_RED,
        '{RED_DARK}' => SOHARON_RED_DARK,
    ), $extra ) );
}

/**
 * Inline SVG icons (no icon font needed, always render)
 */
function soharon_icon( $name ) {
    $paths = array(
        'refresh'  => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'minus'    => '<line x1="5" y1="12" x2="19" y2="12"/>',
        'close'    => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    );
    if ( ! isset( $paths[ $name ] ) ) return '';
    return '<svg class="soharon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/* Poppins font (cart, checkout, account and order tracking) */
function soharon_enqueue_poppins() {
    wp_enqueue_style(
        'soharon-poppins-font',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
        array(),
        null
    );
}
