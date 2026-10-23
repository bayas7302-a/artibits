<?php
/**
 * Header, footer and shop/product templates without Elementor Pro, sticky footer.
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ===========================================================================
 * SITE TEMPLATES WITHOUT ELEMENTOR PRO
 * Replaces the Elementor Pro Theme Builder templates:
 * - Header  → [fanvil_site_header] at the top of every page
 * - Footer  → red footer bar (policy links · logo · copyright)
 * - Shop, categories, sub-categories, tags, product search, promotions → [fanvil_shop]
 * - Single product → [fanvil_product]
 * Only active when Elementor Pro is NOT active, so nothing changes (and nothing is
 * duplicated) while Pro is still installed. Page templates live in fanvil-woocommerce.php.
 * Footer links: Appearance → Menus → "Footer links" (optional, defaults below).
 * ======================================================================== */
define( 'FVT_FOOTER_LOGO_ID', 726 ); // Media Library image used in the footer
define( 'FVT_FOOTER_LOGO', 'https://soharon.co.uk/off-27/wp-content/uploads/2026/09/Mask-groupsd.png' );

/* Elementor Pro's Theme Builder in charge? Then leave everything to it. */
function fvt_enabled() {
	return ! function_exists( 'elementor_theme_do_location' );
}

/* Elementor "Canvas" pages have no header or footer by design */
function fvt_is_canvas() {
	return is_singular() && 'elementor_canvas' === get_page_template_slug( get_queried_object_id() );
}

add_action( 'after_setup_theme', function () {
	register_nav_menus( array( 'fvt-footer' => 'Footer links' ) );
} );

/* Hide Hello Elementor's own header/footer, show ours instead */
add_filter( 'hello_elementor_header_footer', function ( $show ) {
	return fvt_enabled() ? false : $show;
} );

add_action( 'wp_body_open', function () {
	if ( ! fvt_enabled() || fvt_is_canvas() ) return;
	echo '<header id="site-header" class="fvt-header">' . do_shortcode( '[fanvil_site_header]' ) . '</header>';
}, 5 );

add_action( 'get_footer', function () {
	if ( ! fvt_enabled() || fvt_is_canvas() ) return;
	fvt_render_footer();
} );

/* Poppins everywhere (Elementor loaded it on its templates before) */
add_action( 'wp_enqueue_scripts', function () {
	if ( fvt_enabled() ) soharon_enqueue_poppins();
} );

/* Shop / archives / single product → fanvil-woocommerce.php */
add_filter( 'template_include', function ( $template ) {
	if ( ! fvt_enabled() || ! function_exists( 'is_woocommerce' ) ) return $template;

	$is_product_search = is_search() && 'product' === get_query_var( 'post_type' );
	if ( ! is_product() && ! is_shop() && ! is_product_taxonomy() && ! $is_product_search ) return $template;

	$file = get_stylesheet_directory() . '/fanvil-woocommerce.php';
	return file_exists( $file ) ? $file : $template;
}, 99 );

/* Footer ------------------------------------------------------------------ */
function fvt_footer_links() {
	$links = array();
	$locations = get_nav_menu_locations();
	if ( ! empty( $locations['fvt-footer'] ) ) {
		foreach ( (array) wp_get_nav_menu_items( $locations['fvt-footer'] ) as $item ) {
			$links[] = array( $item->title, $item->url );
		}
	}
	if ( ! $links ) { // same links as the old Elementor footer
		$privacy = get_privacy_policy_url();
		$links   = array(
			array( 'Privacy Policy', $privacy ? $privacy : home_url( '/privacy-policy/' ) ),
			array( 'Terms & Condition', home_url( '/terms-condition/' ) ),
			array( 'Return Policy', home_url( '/refund_returns/' ) ),
		);
	}
	return $links;
}

function fvt_render_footer() {
	$name = get_bloginfo( 'name' );
	// Media Library image (sharp on every screen via srcset), or the file URL if it was removed
	$logo = wp_get_attachment_image( FVT_FOOTER_LOGO_ID, 'full', false, array( 'alt' => $name, 'loading' => 'lazy' ) );
	if ( ! $logo ) {
		$logo = '<img src="' . esc_url( FVT_FOOTER_LOGO ) . '" width="1381" height="281" alt="' . esc_attr( $name ) . '" loading="lazy">';
	}
	?>
<footer id="site-footer" class="fvt-footer">
	<div class="fvt-footer__inner">
		<nav class="fvt-footer__links" aria-label="Footer">
			<?php foreach ( fvt_footer_links() as $link ) : ?>
				<a href="<?php echo esc_url( $link[1] ); ?>"><?php echo esc_html( $link[0] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<a class="fvt-footer__logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( $name ); ?> – home">
			<?php echo $logo; // phpcs:ignore ?>
		</a>
		<p class="fvt-footer__copy">&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> Fanvil Store | All Rights Reserved</p>
	</div>
</footer>
<style id="fvt-footer-css">
.fvt-footer{background:#CF0912;color:#fff;font-family:'Poppins',sans-serif;font-size:14px;line-height:1.5}
.fvt-footer__inner{display:flex;align-items:center;justify-content:space-between;gap:20px;max-width:1300px;margin:0 auto;padding:20px 10px}
.fvt-footer__links,.fvt-footer__copy{flex:0 0 30%;margin:0}
.fvt-footer__links{display:flex;flex-wrap:wrap;gap:4px 13px}
.fvt-footer__copy{text-align:right}
.fvt-footer a,.fvt-footer a:hover,.fvt-footer a:focus{color:#fff;text-decoration:none}
.fvt-footer__links a:hover{text-decoration:underline;text-underline-offset:3px}
.fvt-footer__logo{flex:0 0 15%;display:block}
.fvt-footer__logo img{display:block;width:100%;height:auto;margin:0 auto}
@media (max-width:767px){
	.fvt-footer{font-size:13px}
	.fvt-footer__inner{flex-direction:column;text-align:center}
	.fvt-footer__logo{order:-1;flex:0 0 auto;width:180px}
	.fvt-footer__links,.fvt-footer__copy{flex:0 0 auto;justify-content:center;text-align:center}
}
</style>
	<?php
}

/* ---------------------------------------------------------------------------
 * Keep the footer at the bottom of the screen on short pages (site-wide)
 * ------------------------------------------------------------------------ */
add_action( 'wp_head', function () {
	?>
<style id="fvs-sticky-footer">
body{display:flex;flex-direction:column;min-height:calc(100vh - var(--wp-admin--admin-bar--height, 0px))}
body > *{flex-shrink:0}
body > footer,body > .elementor-location-footer,body > #site-footer{margin-top:auto}
</style>
	<?php
} );
