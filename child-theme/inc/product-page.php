<?php
/**
 * Single product page – [fanvil_product].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ===========================================================================
 * Single product page – shortcode [fanvil_product]
 * Place it in the Elementor Single Product template (Shortcode widget).
 * ======================================================================== */

function fvp_product_shortcode_render( $atts ) {
	if ( ! function_exists( 'WC' ) ) {
		return '';
	}
	$atts    = shortcode_atts( array( 'id' => 0 ), $atts, 'fanvil_product' );
	$product = wc_get_product( $atts['id'] ? (int) $atts['id'] : get_the_ID() );
	if ( ! $product ) {
		return '';
	}

	ob_start();
	fvs_print_assets();
	fvp_print_css();

	$id        = $product->get_id();
	$sku       = $product->get_sku();
	$title     = $sku ? $sku : $product->get_name();
	$url_link  = $product->get_meta( '_product_url_link' );
	$datasheet = $product->get_meta( '_product_datasheet_link' );

	/* Categories: deepest assigned category + its top-level parent */
	$terms   = get_the_terms( $id, 'product_cat' );
	$current = null;
	if ( $terms && ! is_wp_error( $terms ) ) {
		$depth = array();
		foreach ( $terms as $t ) {
			$depth[ $t->term_id ] = count( get_ancestors( $t->term_id, 'product_cat', 'taxonomy' ) );
		}
		usort( $terms, function ( $a, $b ) use ( $depth ) {
			return $depth[ $b->term_id ] - $depth[ $a->term_id ];
		} );
		$current = $terms[0];
	}
	$top = $current ? fvs_top_ancestor( $current ) : null;

	echo '<div class="fvs fvp">';

	if ( function_exists( 'wc_print_notices' ) ) {
		wc_print_notices();
	}

	/* Main category tabs */
	$cats = fvs_top_categories();
	if ( $cats ) {
		echo '<nav class="fvs-maincats" aria-label="Product categories"><div class="fvs-maincats__track">';
		foreach ( $cats as $cat ) {
			$active = $top && $top->term_id === $cat->term_id;
			printf(
				'<a href="%s" class="fvs-maincat%s">%s</a>',
				esc_url( get_term_link( $cat ) ),
				$active ? ' is-active' : '',
				esc_html( $cat->name )
			);
		}
		echo '</div></nav>';
	}

	echo '<div class="fvp-top">';

	/* ---------- Left: gallery + summary ---------- */
	echo '<div class="fvp-left">';

	$img_ids = array_values( array_unique( array_filter( array_merge(
		array( $product->get_image_id() ),
		$product->get_gallery_image_ids()
	) ) ) );
	$count = count( $img_ids );

	echo '<div class="fvp-gallery" data-fvp-gallery>';
	echo '<div class="fvp-stage"><div class="fvp-stage__track">';
	if ( $img_ids ) {
		foreach ( $img_ids as $i => $img ) {
			echo '<div class="fvp-stage__slide" data-full="' . esc_url( wp_get_attachment_image_url( $img, 'full' ) ) . '">' . wp_get_attachment_image( $img, 'woocommerce_single', false, array(
				'loading' => 0 === $i ? 'eager' : 'lazy',
				'alt'     => esc_attr( $product->get_name() ),
			) ) . '</div>';
		}
	} else {
		echo '<div class="fvp-stage__slide">' . wc_placeholder_img( 'woocommerce_single' ) . '</div>';
	}
	echo '</div>';
	if ( $count > 1 ) {
		echo '<button type="button" class="fvp-nav fvp-nav--prev" aria-label="Previous image"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
		echo '<button type="button" class="fvp-nav fvp-nav--next" aria-label="Next image"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>';
		echo '<span class="fvp-counter" aria-live="polite">1 / ' . (int) $count . '</span>';
	}
	if ( $img_ids ) {
		echo '<button type="button" class="fvp-zoom" aria-label="View full-size image"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="6" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-4.5-4.5M11 8.5v5M8.5 11h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>';
	}
	echo '</div>';
	if ( $count > 1 ) {
		echo '<div class="fvp-thumbs">';
		foreach ( $img_ids as $i => $img ) {
			printf(
				'<button type="button" class="fvp-thumb%s" aria-label="Show image %d of %d" aria-current="%s">%s</button>',
				0 === $i ? ' is-active' : '',
				$i + 1,
				$count,
				0 === $i ? 'true' : 'false',
				wp_get_attachment_image( $img, 'woocommerce_gallery_thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) )
			);
		}
		echo '</div>';
	}
	echo '</div>';

	/* Summary */
	echo '<div class="fvp-summary">';
	if ( $current ) {
		echo '<p class="fvp-crumbs">';
		if ( $top && $top->term_id !== $current->term_id ) {
			echo '<a href="' . esc_url( get_term_link( $top ) ) . '">' . esc_html( $top->name ) . '</a><span aria-hidden="true">/</span>';
		}
		echo '<a href="' . esc_url( get_term_link( $current ) ) . '">' . esc_html( $current->name ) . '</a></p>';
	}
	echo '<h1 class="fvp-title">' . esc_html( $title ) . '</h1>';
	echo '<p class="fvp-name">' . esc_html( $product->get_name() ) . '</p>';

	$has_price = '' !== $product->get_price();
	echo '<div class="fvp-buy" data-fvs-buy>';
	if ( $has_price ) {
		echo '<div class="fvp-price">' . wp_kses_post( $product->get_price_html() );
		if ( fvs_show_excl_vat() ) {
			echo '<span class="fvs-card__tax">excl. VAT</span>';
		}
		echo '</div>';
	} else {
		echo '<div class="fvp-price fvs-card__price--poa">Price on request</div>';
	}

	if ( $has_price && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		fvs_render_buy_form( $product, $title, 'fvp-actions' );
	} elseif ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) {
		woocommerce_template_single_add_to_cart(); // standard form for other product types
	}

	echo fvs_stock_html( $product ); // phpcs:ignore
	echo '</div>';

	$short = $product->get_short_description();
	if ( $short ) {
		echo '<div class="fvp-short">' . wp_kses_post( wpautop( $short ) ) . '</div>';
	}

	if ( $datasheet || $url_link ) {
		echo '<div class="fvp-links">';
		if ( $datasheet ) {
			echo '<a class="fvs-btn fvp-link fvp-link--primary" href="' . esc_url( $datasheet ) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Datasheet (PDF)</a>';
		}
		if ( $url_link ) {
			echo '<a class="fvs-btn fvp-link" href="' . esc_url( $url_link ) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M14 5h5v5M19 5l-8 8M17 14v4a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>Online information</a>';
		}
		echo '</div>';
	}

	echo '</div>'; // .fvp-summary
	echo '</div>'; // .fvp-left

	/* ---------- Right: specifications ---------- */
	$desc = $product->get_description();
	if ( $desc ) {
		echo '<section class="fvp-right" aria-labelledby="fvp-spec-title">';
		echo '<h2 id="fvp-spec-title" class="fvp-section-title">Specifications</h2>';
		$spec = wp_kses_post( wc_format_content( $desc ) );
		$spec = preg_replace( '#<table\b#i', '<div class="fvp-table-wrap"><table', $spec );
		$spec = preg_replace( '#</table>#i', '</table></div>', $spec );
		echo '<div class="fvp-spec">' . $spec . '</div>'; // phpcs:ignore
		echo '</section>';
	}

	echo '</div>'; // .fvp-top

	/* ---------- Related products ---------- */
	$related = fvp_related_ids( $product, $current, $top, 4 );
	if ( $related ) {
		echo '<section class="fvp-related" aria-labelledby="fvp-related-title">';
		echo '<h2 id="fvp-related-title" class="fvp-section-title">Related products</h2><ul class="fvs-grid">';
		foreach ( $related as $rid ) {
			$rp = wc_get_product( $rid );
			if ( $rp && $rp->is_visible() ) {
				fvs_render_card( $rp );
			}
		}
		echo '</ul></section>';
	}

	echo '</div>';
	return ob_get_clean();
}


/**
 * Related products: only from the product's own (deepest) category,
 * e.g. SIP Phones → Android-Phone shows only Android-Phone products.
 * No mixing with other categories; if there are none, the section is hidden.
 */
function fvp_related_ids( $product, $current, $top, $limit = 4 ) {
	if ( ! $current ) {
		return array();
	}
	return array_map( 'intval', get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'fields'         => 'ids',
		'post__not_in'   => array( $product->get_id() ),
		'orderby'        => 'rand',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $current->term_id,
				'include_children' => false,
			),
			array(
				'taxonomy' => 'product_visibility',
				'field'    => 'name',
				'terms'    => array( 'exclude-from-catalog' ),
				'operator' => 'NOT IN',
			),
			// promoted product → other promotions; normal product → no promotions
			fvs_promo_clause( has_term( FVS_PROMO_TERM, FVS_PROMO_TAX, $product->get_id() ) ? 'IN' : 'NOT IN' ),
		),
	) ) );
}

function fvp_print_css() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style id="fvp-css">
.fvp{padding:8px 0 72px}
.fvp .fvs-maincats{margin-bottom:32px}
.fvp{max-width:100%;overflow-x:clip}
.fvp-top{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:56px;align-items:start}
.fvp-left,.fvp-right,.fvp-summary,.fvp-gallery{min-width:0;max-width:100%}
.fvp-table-wrap{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;margin-bottom:4px;border-radius:12px}
.fvp-spec th,.fvp-spec td{overflow-wrap:anywhere;word-break:normal}
.fvp-section-title{margin:0 0 18px;font-size:22px;font-weight:600;line-height:1.25}

/* Gallery */
.fvp-stage{position:relative;border-radius:16px;background:var(--fvs-soft);overflow:hidden;touch-action:pan-y}
.fvp-stage__track{display:flex;transition:transform .5s cubic-bezier(.22,.61,.36,1)}
.fvp-stage__slide{flex:0 0 100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;padding:28px}
.fvp-stage__slide img{width:100%;height:100%;object-fit:contain;user-select:none;-webkit-user-drag:none}
.fvs .fvp-nav,.fvs .fvp-nav:hover,.fvs .fvp-nav:focus{position:absolute;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:40px;height:40px;min-height:0;margin:0;padding:0 !important;border:1px solid var(--fvs-line) !important;border-radius:50%;background:#fff !important;color:var(--fvs-ink) !important;box-shadow:none !important;cursor:pointer;opacity:0;transition:opacity .2s,color .2s,border-color .2s}
.fvs .fvp-nav:hover{color:var(--fvs-red) !important;border-color:var(--fvs-red) !important}
.fvp-nav--prev{left:14px}.fvp-nav--next{right:14px}
.fvp-stage:hover .fvp-nav,.fvs .fvp-nav:focus-visible{opacity:1}
@media (hover:none){.fvs .fvp-nav{opacity:1}}
.fvp-counter{position:absolute;right:14px;bottom:12px;padding:3px 10px;border-radius:999px;background:rgba(255,255,255,.9);font-size:12px;font-weight:500;color:var(--fvs-muted)}
.fvp-thumbs{display:flex;gap:10px;margin-top:12px;overflow-x:auto;scrollbar-width:thin;padding-bottom:4px}
.fvs .fvp-thumb,.fvs .fvp-thumb:hover,.fvs .fvp-thumb:focus{flex:0 0 72px;width:72px;height:72px;min-height:0;margin:0;padding:6px !important;border:1px solid var(--fvs-line) !important;border-radius:10px;background:var(--fvs-soft) !important;box-shadow:none !important;cursor:pointer;transition:border-color .2s}
.fvs .fvp-thumb:hover{border-color:#C9CDD2 !important}
.fvs .fvp-thumb.is-active{border-color:var(--fvs-red) !important}
.fvp-thumb img{width:100%;height:100%;object-fit:contain;display:block}

.fvp-stage__slide{cursor:zoom-in}
.fvs .fvp-zoom,.fvs .fvp-zoom:hover,.fvs .fvp-zoom:focus{position:absolute;top:14px;right:14px;display:flex;align-items:center;justify-content:center;width:38px;height:38px;min-height:0;margin:0;padding:0 !important;border:1px solid var(--fvs-line) !important;border-radius:50%;background:#fff !important;color:var(--fvs-ink) !important;box-shadow:none !important;cursor:pointer;transition:color .2s,border-color .2s}
.fvs .fvp-zoom:hover{color:var(--fvs-red) !important;border-color:var(--fvs-red) !important}

/* Lightbox */
html.fvp-lb-open,html.fvp-lb-open body{overflow:hidden}
.fvp-lightbox{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;background:rgba(15,17,20,.92);font-family:'Poppins',sans-serif;touch-action:pan-y}
.fvp-lightbox.is-open{display:flex}
.fvp-lb__figure{margin:0;width:100%;height:100%;display:flex;align-items:center;justify-content:center;padding:64px 88px}
.fvp-lb__img{max-width:100%;max-height:100%;object-fit:contain;border-radius:8px;background:#fff;user-select:none;-webkit-user-drag:none}
.fvp-lightbox button,.fvp-lightbox button:hover,.fvp-lightbox button:focus{position:absolute;display:flex;align-items:center;justify-content:center;min-height:0;margin:0;padding:0 !important;border:0 !important;border-radius:50%;background:rgba(255,255,255,.12) !important;color:#fff !important;box-shadow:none !important;cursor:pointer;transition:background .2s}
.fvp-lightbox button:hover{background:#DB141D !important}
.fvp-lightbox button:focus-visible{outline:2px solid #fff;outline-offset:2px}
.fvp-lb__close{top:18px;right:18px;width:44px;height:44px}
.fvp-lb__nav{top:50%;transform:translateY(-50%);width:52px;height:52px}
.fvp-lb__prev{left:20px}.fvp-lb__next{right:20px}
.fvp-lightbox.is-single .fvp-lb__nav{display:none}
.fvp-lb__counter{position:absolute;bottom:20px;left:50%;transform:translateX(-50%);color:rgba(255,255,255,.8);font-size:14px}
@media (max-width:640px){
 .fvp-lb__figure{padding:64px 12px}
 .fvp-lb__nav{top:auto;bottom:12px;transform:none;width:44px;height:44px}
 .fvp-lb__prev{left:16px}.fvp-lb__next{right:16px}
}

/* Summary */
.fvp-summary{margin-top:28px}
.fvp-crumbs{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 10px;font-size:13px;color:var(--fvs-muted)}
.fvs .fvp-crumbs a{color:var(--fvs-muted)}
.fvs .fvp-crumbs a:hover{color:var(--fvs-red)}
.fvp-title{margin:0;font-size:30px;font-weight:600;line-height:1.15}
.fvp-name{margin:6px 0 22px;font-size:15px;color:var(--fvs-muted)}
.fvp-buy{padding:20px 0;border-top:1px solid var(--fvs-line);border-bottom:1px solid var(--fvs-line)}
.fvp-price{font-size:24px;font-weight:600;line-height:1.3;margin-bottom:16px}
.fvp-price.fvs-card__price--poa{font-size:17px;margin-bottom:0}
.fvp-price del{color:var(--fvs-muted);font-weight:400;font-size:17px;margin-right:8px}
.fvp-price ins{text-decoration:none}
.fvp-actions{max-width:380px}
.fvp .fvs-btn--cart{height:46px}
.fvp .fvs-qty{height:46px}
.fvp-short{margin-top:20px;font-size:15px;line-height:1.7;color:#3F444B}
.fvp-short p{margin:0 0 12px}
.fvp-links{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}
.fvs .fvp-link{gap:8px;height:46px;padding:0 22px;border:1px solid var(--fvs-red);background:#fff;color:var(--fvs-red)}
.fvs .fvp-link:hover{background:var(--fvs-red);color:#fff}
.fvs .fvp-link--primary{background:var(--fvs-red);color:#fff}
.fvs .fvp-link--primary:hover{background:var(--fvs-red-dark);border-color:var(--fvs-red-dark);color:#fff}

/* Specifications */
.fvp-spec h4{margin:26px 0 10px;font-size:15px;font-weight:600;color:var(--fvs-ink)}
.fvp-spec h4:first-child{margin-top:0}
.fvp-spec table{width:100%;margin:0;border-collapse:separate;border-spacing:0;border:1px solid var(--fvs-line) !important;border-radius:12px;overflow:hidden;font-size:14px}
.fvp-spec th,.fvp-spec td{padding:11px 16px !important;border:0 !important;border-bottom:1px solid var(--fvs-line) !important;text-align:left;vertical-align:top;line-height:1.5;background:#fff}
.fvp-spec tr:last-child th,.fvp-spec tr:last-child td{border-bottom:0 !important}
.fvp-spec th{width:40%;font-weight:500;color:var(--fvs-muted);background:#FAFAFB}
.fvp-spec td{color:var(--fvs-ink)}
.fvp-spec td[colspan]{background:#fff}
.fvp-spec p{margin:0 0 12px}

/* Related */
.fvp-related{margin-top:72px}

@media (max-width:1024px){
 .fvp-top{grid-template-columns:minmax(0,1fr);gap:40px}
 .fvp-title{font-size:26px}
}
@media (max-width:560px){
 .fvp-stage__slide{padding:16px}
 .fvp-links .fvs-btn{flex:1 1 100%}
 .fvp-actions{max-width:none}
 .fvp .fvs-maincats{margin-bottom:20px}
 .fvp-stage{border-radius:12px}
 .fvp-thumbs{gap:8px}
 .fvs .fvp-thumb,.fvs .fvp-thumb:hover,.fvs .fvp-thumb:focus{flex-basis:58px;width:58px;height:58px}
 .fvp-title{font-size:23px}
 .fvp-section-title{font-size:19px;margin-bottom:14px}
 .fvp-spec table{font-size:13px}
 .fvp-spec th,.fvp-spec td{padding:9px 12px !important}
 .fvp-spec th{width:42%}
 .fvp-related{margin-top:48px}
}
@media (prefers-reduced-motion:reduce){.fvp-stage__track{transition:none}}
</style>
	<?php
}
