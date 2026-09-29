<?php
/**
 * Product URL + Data Sheet fields – [product_url] [product_datasheet] [product_links].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WooCommerce: "Product URL" + "Data Sheet" link fields
 * - Adds two URL fields in Product Data > General (admin)
 * - Saves them as product meta (_product_url_link, _product_datasheet_link)
 * - Shows them on the single product page directly below the Category line
 * - Shortcodes for Elementor: [product_url]  [product_datasheet]  [product_links]
 */

/* Field definitions: meta key => label */
function soh_product_link_fields() {
	return array(
		'_product_url_link'       => __( 'Product URL', 'woocommerce' ),
		'_product_datasheet_link' => __( 'Data Sheet', 'woocommerce' ),
	);
}

/* 1. Admin fields */
add_action( 'woocommerce_product_options_general_product_data', function () {
	echo '<div class="options_group">';
	foreach ( soh_product_link_fields() as $key => $label ) {
		woocommerce_wp_text_input( array(
			'id'          => $key,
			'label'       => $label,
			'placeholder' => 'https://',
			'type'        => 'url',
			'desc_tip'    => true,
			'description' => sprintf( __( '%s link shown below the category on the product page.', 'woocommerce' ), $label ),
		) );
	}
	echo '</div>';
} );

/* 2. Save fields */
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	foreach ( array_keys( soh_product_link_fields() ) as $key ) {
		if ( isset( $_POST[ $key ] ) ) {
			$product->update_meta_data( $key, esc_url_raw( wp_unslash( $_POST[ $key ] ) ) );
		}
	}
} );

/* 3. Build the output for one link */
function soh_get_product_link_html( $key, $product = null ) {
	$product = $product ?: wc_get_product( get_the_ID() );
	$fields  = soh_product_link_fields();
	if ( ! $product || ! isset( $fields[ $key ] ) ) {
		return '';
	}
	$url = $product->get_meta( $key );
	if ( ! $url ) {
		return '';
	}
	$is_datasheet = ( '_product_datasheet_link' === $key );
	return sprintf(
		'<span class="product-link-meta %s">%s: <a href="%s" target="_blank" rel="noopener">%s</a></span>',
		$is_datasheet ? 'product-datasheet-meta' : 'product-url-meta',
		esc_html( $fields[ $key ] ),
		esc_url( $url ),
		$is_datasheet ? esc_html__( 'Download', 'woocommerce' ) : esc_html( $url )
	);
}

/* Both links together */
function soh_get_product_links_html( $product = null ) {
	$html = '';
	foreach ( array_keys( soh_product_link_fields() ) as $key ) {
		$html .= soh_get_product_link_html( $key, $product );
	}
	return $html;
}

/* 4. Display below Category (inside the product meta block) */
add_action( 'woocommerce_product_meta_end', function () {
	global $product;
	echo soh_get_product_links_html( $product );
} );

/* 5. Shortcodes for Elementor */
add_shortcode( 'product_url', function () {
	return soh_get_product_link_html( '_product_url_link' );
} );
add_shortcode( 'product_datasheet', function () {
	return soh_get_product_link_html( '_product_datasheet_link' );
} );
add_shortcode( 'product_links', function () {
	return soh_get_product_links_html();
} );
