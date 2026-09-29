<?php
/**
 * Home page sections – [fanvil_categories] and [fanvil_products].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ===========================================================================
 * Home page – [fanvil_categories]
 * Desktop: 4 per row (slider with arrows when there are more).
 * Mobile: 2 per row with the next card peeking in, swipe + arrows + progress bar.
 * Options: parent="slug" (show sub-categories of a category), limit="12", title="Shop by category"
 * ======================================================================== */
function fvs_categories_shortcode_render( $atts ) {
	$atts = shortcode_atts( array( 'parent' => '', 'limit' => 0, 'title' => '' ), $atts, 'fanvil_categories' );

	if ( $atts['parent'] ) {
		$parent = get_term_by( 'slug', sanitize_title( $atts['parent'] ), 'product_cat' );
		$cats   = $parent ? fvs_sub_categories( $parent ) : array();
	} else {
		$cats = fvs_top_categories();
	}
	if ( (int) $atts['limit'] > 0 ) {
		$cats = array_slice( $cats, 0, (int) $atts['limit'] );
	}
	if ( ! $cats ) {
		return '';
	}

	ob_start();
	fvs_print_assets();
	fvh_print_home_css();

	echo '<div class="fvs fvs-home">';
	if ( $atts['title'] ) {
		echo '<h2 class="fvs-home__title">' . esc_html( $atts['title'] ) . '</h2>';
	}

	echo '<div class="fvs-slider" data-fvs-slider>';
	echo '<ul class="fvs-slider__track">';
	foreach ( $cats as $cat ) {
		echo '<li>';
		fvs_render_catcard( $cat );
		echo '</li>';
	}
	echo '</ul>';
	echo '<div class="fvs-slider__controls">';
	echo '<button type="button" class="fvs-slider__btn fvs-slider__prev" aria-label="Previous categories"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
	echo '<div class="fvs-slider__progress" aria-hidden="true"><span></span></div>';
	echo '<button type="button" class="fvs-slider__btn fvs-slider__next" aria-label="Next categories"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
	echo '</div>';
	echo '</div>';
	echo '</div>';
	return ob_get_clean();
}
add_shortcode( 'fanvil_categories', function ( $atts ) {
	return fvs_safe_render( 'fvs_categories_shortcode_render', $atts );
} );

/* ===========================================================================
 * Home page – [fanvil_products type="top_picks"]  /  [fanvil_products type="special_offers"]
 * top_picks ....... products marked Featured (★ in Products list);
 *                   if none are featured → best sellers, then newest
 * special_offers .. products with a sale price (scheduled sales included)
 * Also: type="new" (newest) and type="best_sellers".
 * Options: limit="8", category="slug", title="…" (no heading by default), link="URL for View all"
 * ======================================================================== */
function fvs_products_shortcode_render( $atts ) {
	$atts = shortcode_atts( array(
		'type'     => 'top_picks',
		'limit'    => 8,
		'category' => '',
		'title'    => '',
		'link'     => '',
	), $atts, 'fanvil_products' );

	$type  = sanitize_key( $atts['type'] );
	$limit = max( 1, (int) $atts['limit'] );
	$base  = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array( array(
			'taxonomy' => 'product_visibility',
			'field'    => 'name',
			'terms'    => array( 'exclude-from-catalog' ),
			'operator' => 'NOT IN',
		) ),
	);
	if ( $atts['category'] ) {
		$base['tax_query'][] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'slug',
			'terms'            => array_map( 'sanitize_title', explode( ',', $atts['category'] ) ),
			'include_children' => true,
		);
	}

	$best_sellers = array( 'meta_key' => 'total_sales', 'orderby' => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ) );
	$newest       = array( 'orderby' => 'date', 'order' => 'DESC' );
	$ids          = array();
	$default      = 'Top Picks';

	switch ( $type ) {
		case 'special_offers':
			$default = 'Special Offers';
			$on_sale = wc_get_product_ids_on_sale();
			if ( $on_sale ) {
				$ids = get_posts( array_merge( $base, array( 'post__in' => $on_sale, 'orderby' => 'date', 'order' => 'DESC' ) ) );
			}
			break;

		case 'new':
			$default = 'New Arrivals';
			$ids     = get_posts( array_merge( $base, $newest ) );
			break;

		case 'best_sellers':
			$default = 'Best Sellers';
			$ids     = get_posts( array_merge( $base, $best_sellers ) );
			break;

		default: // top_picks
			$featured = wc_get_featured_product_ids();
			if ( $featured ) {
				$ids = get_posts( array_merge( $base, array( 'post__in' => $featured, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) );
			}
			if ( count( $ids ) < $limit ) { // top up with best sellers / newest
				$more = get_posts( array_merge( $base, $best_sellers, array(
					'posts_per_page' => $limit - count( $ids ),
					'post__not_in'   => $ids ? $ids : array( 0 ),
				) ) );
				$ids  = array_merge( $ids, $more );
			}
	}

	if ( ! $ids ) {
		if ( current_user_can( 'manage_options' ) ) {
			return '<p style="padding:12px 16px;border:1px dashed #DB141D;border-radius:10px;color:#DB141D;font-size:14px">'
				. esc_html( 'Special Offers' === $default
					? 'Special Offers is hidden: no products have a sale price yet. Add a Sale price in Product data → General. (Only admins see this note.)'
					: 'No products found for this section. (Only admins see this note.)' )
				. '</p>';
		}
		return '';
	}

	ob_start();
	fvs_print_assets();
	fvh_print_home_css();

	$title = trim( $atts['title'] ); // no heading unless title="…" is given
	echo '<div class="fvs fvs-home">';
	if ( '' !== $title && 'none' !== strtolower( $title ) ) {
		echo '<div class="fvs-home__head"><h2 class="fvs-home__title">' . esc_html( $title ) . '</h2>';
		if ( $atts['link'] ) {
			echo '<a class="fvs-home__link" href="' . esc_url( $atts['link'] ) . '">View all</a>';
		}
		echo '</div>';
	}
	echo '<ul class="fvs-grid">';
	foreach ( $ids as $pid ) {
		$product = wc_get_product( $pid );
		if ( $product && $product->is_visible() ) {
			fvs_render_card( $product );
		}
	}
	echo '</ul></div>';
	return ob_get_clean();
}
add_shortcode( 'fanvil_products', function ( $atts ) {
	return fvs_safe_render( 'fvs_products_shortcode_render', $atts );
} );

function fvh_print_home_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style id="fvs-home-css">
.fvs-home{padding:8px 0}
.fvs-home__head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;margin-bottom:22px}
.fvs-home__title{margin:0 0 22px;font-size:26px;font-weight:600;line-height:1.2;color:var(--fvs-ink)}
.fvs-home__head .fvs-home__title{margin:0}
.fvs .fvs-home__link{font-size:14px;font-weight:600;color:var(--fvs-red);text-decoration:underline;text-underline-offset:4px}
.fvs .fvs-home__link:hover{color:var(--fvs-red-dark)}

/* Category slider */
.fvs-slider{position:relative}
.fvs .fvs-slider__track{display:grid;grid-auto-flow:column;grid-auto-columns:calc((100% - 72px) / 4);gap:24px;overflow-x:auto;scroll-snap-type:x mandatory;scrollbar-width:none;padding:10px 4px 30px;margin:-10px -4px -30px;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch}
.fvs-slider__track::-webkit-scrollbar{display:none}
.fvs-slider__track > li{scroll-snap-align:start;min-width:0}
.fvs-slider__controls{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:22px}
.fvs-slider.is-static .fvs-slider__controls{display:none}
.fvs .fvs-slider__btn,.fvs .fvs-slider__btn:hover,.fvs .fvs-slider__btn:focus{display:flex;align-items:center;justify-content:center;flex:0 0 40px;width:40px;height:40px;min-height:0;margin:0;padding:0 !important;border:1px solid var(--fvs-line) !important;border-radius:50%;background:#fff !important;color:var(--fvs-ink) !important;box-shadow:none !important;cursor:pointer;transition:color .2s,border-color .2s,background .2s,opacity .2s}
.fvs .fvs-slider__btn:hover{background:var(--fvs-red) !important;border-color:var(--fvs-red) !important;color:#fff !important}
.fvs .fvs-slider__btn:disabled{opacity:.35;pointer-events:none}
.fvs-slider__progress{position:relative;flex:0 1 180px;height:4px;border-radius:4px;background:var(--fvs-line);overflow:hidden}
.fvs-slider__progress span{position:absolute;top:0;left:0;width:25%;height:100%;border-radius:4px;background:var(--fvs-red);transition:left .12s linear}
@media (max-width:1100px){.fvs .fvs-slider__track{grid-auto-columns:calc((100% - 48px) / 3)}}
@media (max-width:820px){
 /* 2 cards + about 10% of the next one peeking in */
 .fvs .fvs-slider__track{grid-auto-columns:calc((100% - 24px) / 2.1);gap:12px;scroll-padding-left:4px}
 .fvs-slider::after{content:"";position:absolute;top:0;right:-1px;bottom:50px;width:28px;background:linear-gradient(to right,rgba(255,255,255,0),rgba(255,255,255,.9));pointer-events:none;transition:opacity .2s}
 .fvs-slider.at-end::after,.fvs-slider.is-static::after{opacity:0}
 .fvs-slider__controls{gap:12px;margin-top:16px}
 .fvs .fvs-slider__btn,.fvs .fvs-slider__btn:hover,.fvs .fvs-slider__btn:focus{flex-basis:34px;width:34px;height:34px}
 .fvs-slider__progress{flex-basis:120px;height:3px}
}
@media (max-width:560px){.fvs-home__title{font-size:22px}}
</style>
	<?php
}
