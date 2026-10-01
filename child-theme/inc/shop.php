<?php
/**
 * Shop, categories, tags, search and Promotions – [fanvil_shop]; shared shortcode safety wrapper.
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fanvil Shop Archive – shortcode [fanvil_shop]
 *
 * Place [fanvil_shop] in the Elementor Product Archive template (Shortcode widget).
 * - Shop page ............ grid of main categories (4 per row) with thumbnails
 * - Category pages ....... main category tabs (active highlighted), sub-category pills,
 *                          4-column product grid with auto-scrolling image carousel,
 *                          price, quantity + add to cart, stock status, pagination
 */

/* Products per page on category pages (4 columns x 3 rows) */
add_filter( 'loop_shop_per_page', function () {
	return 12;
}, 20 );

/* ---------------------------------------------------------------------------
 * Helpers
 * ------------------------------------------------------------------------ */

/** Main (top-level) categories, excluding "Uncategorized". */
function fvs_top_categories() {
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'parent'     => 0,
		'hide_empty' => false,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		'orderby'    => 'menu_order',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, function ( $t ) {
		return fvs_cat_info( $t )['count'] > 0;
	} ) );
}

/** Sub-categories of a term that contain products. */
function fvs_sub_categories( $parent ) {
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'parent'     => $parent->term_id,
		'hide_empty' => false,
		'orderby'    => 'menu_order',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $terms ) ) {
		return array();
	}
	return array_values( array_filter( $terms, function ( $t ) {
		return fvs_cat_info( $t )['count'] > 0;
	} ) );
}

/**
 * Product count (including sub-categories) and first product ID of a category.
 * Counted from products (not the term count) because imported products are often
 * assigned only to the sub-category. Results are cached for 12 hours and cleared
 * whenever a product or category changes, so each page no longer runs one query per category.
 */
function fvs_cat_info( $term ) {
	global $fvs_cat_cache;
	if ( ! is_array( $fvs_cat_cache ) ) {
		$fvs_cat_cache = get_transient( 'fvs_cat_info_v2' );
		if ( ! is_array( $fvs_cat_cache ) ) {
			$fvs_cat_cache = array();
		}
	}
	if ( isset( $fvs_cat_cache[ $term->term_id ] ) ) {
		return $fvs_cat_cache[ $term->term_id ];
	}
	$q = new WP_Query( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
		'tax_query'      => array(
			array(
				'taxonomy'         => 'product_cat',
				'field'            => 'term_id',
				'terms'            => $term->term_id,
				'include_children' => true,
			),
			fvs_promo_clause( 'NOT IN' ), // promoted products only count on the Promotions page
		),
	) );
	$fvs_cat_cache[ $term->term_id ] = array(
		'count' => (int) $q->found_posts,
		'first' => $q->posts ? (int) $q->posts[0] : 0,
	);
	if ( ! has_action( 'shutdown', 'fvs_save_cat_cache' ) ) {
		add_action( 'shutdown', 'fvs_save_cat_cache' );
	}
	return $fvs_cat_cache[ $term->term_id ];
}

/* Save newly counted categories once at the end of the request */
function fvs_save_cat_cache() {
	global $fvs_cat_cache;
	if ( is_array( $fvs_cat_cache ) ) {
		set_transient( 'fvs_cat_info_v2', $fvs_cat_cache, 12 * HOUR_IN_SECONDS );
	}
}

/* Clear the category cache when products or categories change */
function fvs_clear_cat_cache() {
	global $fvs_cat_cache;
	$fvs_cat_cache = null;
	remove_action( 'shutdown', 'fvs_save_cat_cache' );
	delete_transient( 'fvs_cat_info' );
	delete_transient( 'fvs_cat_info_v2' );
	delete_transient( 'fvs_promo_cats' );
}
foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'created_product_cat', 'edited_product_cat', 'delete_product_cat' ) as $fvs_hook ) {
	add_action( $fvs_hook, 'fvs_clear_cat_cache' );
}
unset( $fvs_hook );
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'product' === $post->post_type && $new !== $old ) {
		fvs_clear_cat_cache();
	}
}, 10, 3 );
add_action( 'deleted_post', function ( $post_id, $post = null ) {
	if ( $post && 'product' === $post->post_type ) {
		fvs_clear_cat_cache();
	}
}, 10, 2 );
add_action( 'set_object_terms', function ( $object_id, $terms, $tt_ids, $taxonomy ) {
	if ( 'product_cat' === $taxonomy || 'product_promotion' === $taxonomy ) {
		fvs_clear_cat_cache();
	}
}, 10, 4 );

/** Category image: its own thumbnail, otherwise the first product's main image. */
function fvs_cat_image_id( $term ) {
	$id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
	if ( ! $id ) {
		$first = fvs_cat_info( $term )['first'];
		$id    = $first ? (int) get_post_thumbnail_id( $first ) : 0;
	}
	return $id;
}

/** Top-level ancestor of a category. */
function fvs_top_ancestor( $term ) {
	$ancestors = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
	return $ancestors ? get_term( end( $ancestors ), 'product_cat' ) : $term;
}

/** Product subtitle: name without the brand and model prefix. */
function fvs_subtitle( $product ) {
	$name = preg_replace( '/^(Fanvil|LINKVIL)\s+/i', '', $product->get_name() );
	$sku  = $product->get_sku();
	if ( $sku && 0 === stripos( $name, $sku ) ) {
		$name = trim( substr( $name, strlen( $sku ) ) );
	}
	return $name;
}

/* ===========================================================================
 * Promotions – product taxonomy "Promotions" with one option: "Promotion"
 * - Product edit screen → "Promotions" box with one checkbox (like Categories)
 * - Page: /promotions/ shows every promoted product with the [fanvil_shop] layout:
 *   "All" + main categories (only those with promoted products) → sub-category
 *   pills → product grid. Category filter: /promotions/?pcat=category-slug
 * The Elementor Product Archive template is used for this page (it is a product
 * taxonomy), so the [fanvil_shop] shortcode there shows it automatically.
 * ======================================================================== */
define( 'FVS_PROMO_TAX', 'product_promotion' );
define( 'FVS_PROMO_TERM', 'promotion' );

add_action( 'init', function () {
	register_taxonomy( FVS_PROMO_TAX, array( 'product' ), array(
		'labels'             => array(
			'name'          => 'Promotions',
			'singular_name' => 'Promotion',
			'menu_name'     => 'Promotions',
			'all_items'     => 'All promotions',
			'edit_item'     => 'Edit promotion',
			'view_item'     => 'View promotion',
			'update_item'   => 'Update promotion',
			'search_items'  => 'Search promotions',
			'not_found'     => 'No promotions found',
		),
		'hierarchical'       => true,
		'public'             => true,
		'show_ui'            => true,
		'show_in_menu'       => false, // one fixed option, nothing to manage
		'show_in_nav_menus'  => true,  // Appearance → Menus → Promotions
		'show_admin_column'  => true,  // "Promotions" column in the Products list
		'show_in_quick_edit' => true,
		'show_in_rest'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'promotions', 'with_front' => false ),
		'meta_box_cb'        => 'fvs_promo_meta_box',
		'capabilities'       => array(
			'manage_terms' => 'manage_product_terms',
			'edit_terms'   => 'manage_product_terms',
			'delete_terms' => 'manage_product_terms',
			'assign_terms' => 'edit_products',
		),
	) );

	// Short address: /promotions/ (and /promotions/page/2/) instead of /promotions/promotion/
	add_rewrite_rule( '^promotions/?$', 'index.php?' . FVS_PROMO_TAX . '=' . FVS_PROMO_TERM, 'top' );
	add_rewrite_rule( '^promotions/page/([0-9]+)/?$', 'index.php?' . FVS_PROMO_TAX . '=' . FVS_PROMO_TERM . '&paged=$matches[1]', 'top' );
}, 5 );

/* Create the "Promotion" option and register the new addresses (one time only) */
add_action( 'init', function () {
	if ( '1' === get_option( 'fvs_promo_setup' ) ) return;
	if ( ! term_exists( FVS_PROMO_TERM, FVS_PROMO_TAX ) ) {
		wp_insert_term( 'Promotion', FVS_PROMO_TAX, array( 'slug' => FVS_PROMO_TERM ) );
	}
	flush_rewrite_rules( false );
	update_option( 'fvs_promo_setup', '1' );
}, 99 );

/* tax_query clause: 'NOT IN' = hide promoted products, 'IN' = only promoted products */
function fvs_promo_clause( $operator = 'NOT IN' ) {
	return array(
		'taxonomy' => FVS_PROMO_TAX,
		'field'    => 'slug',
		'terms'    => array( FVS_PROMO_TERM ),
		'operator' => $operator,
	);
}

/* Promoted products are shown ONLY on the Promotions page (and its category tabs):
   hidden from the shop, categories, sub-categories, tags and search results. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || $q->is_tax( FVS_PROMO_TAX ) ) return;

	$product_taxes = array_diff( get_object_taxonomies( 'product' ), array( FVS_PROMO_TAX ) );
	$post_type     = (array) $q->get( 'post_type' );
	$is_listing    = $q->is_post_type_archive( 'product' )
		|| ( $product_taxes && $q->is_tax( $product_taxes ) )
		|| ( $q->is_search() && ( ! array_filter( $post_type ) || in_array( 'product', $post_type, true ) ) );
	if ( ! $is_listing ) return;

	$tax_query   = (array) $q->get( 'tax_query' );
	$tax_query[] = fvs_promo_clause( 'NOT IN' );
	$q->set( 'tax_query', $tax_query );
}, 25 );

/* Same for WooCommerce's own [products] shortcodes and blocks */
add_filter( 'woocommerce_shortcode_products_query', function ( $args ) {
	$args['tax_query']   = isset( $args['tax_query'] ) ? (array) $args['tax_query'] : array();
	$args['tax_query'][] = fvs_promo_clause( 'NOT IN' );
	return $args;
} );

function fvs_promo_term() {
	static $term = null;
	if ( null === $term ) {
		$term = get_term_by( 'slug', FVS_PROMO_TERM, FVS_PROMO_TAX );
	}
	return $term;
}

/* Link to the promotions page (always the short /promotions/ address) */
function fvs_promo_url() {
	return get_option( 'permalink_structure' )
		? user_trailingslashit( home_url( 'promotions' ) )
		: add_query_arg( FVS_PROMO_TAX, FVS_PROMO_TERM, home_url( '/' ) ); // "Plain" permalinks
}
add_filter( 'term_link', function ( $url, $term, $taxonomy ) {
	return ( FVS_PROMO_TAX === $taxonomy && FVS_PROMO_TERM === $term->slug && get_option( 'permalink_structure' ) ) ? fvs_promo_url() : $url;
}, 10, 3 );

/* Product edit screen: one checkbox */
function fvs_promo_meta_box( $post ) {
	$term = fvs_promo_term();
	if ( ! $term ) {
		echo '<p>Reload this page to finish setting up Promotions.</p>';
		return;
	}
	$on = has_term( $term->term_id, FVS_PROMO_TAX, $post );
	// The hidden 0 lets WordPress remove the product from Promotions when the box is unticked
	echo '<input type="hidden" name="tax_input[' . esc_attr( FVS_PROMO_TAX ) . '][]" value="0">';
	printf(
		'<label style="display:flex;align-items:center;gap:8px;margin:6px 0"><input type="checkbox" name="tax_input[%s][]" value="%d"%s> <strong>Promotion</strong></label>',
		esc_attr( FVS_PROMO_TAX ),
		(int) $term->term_id,
		checked( $on, true, false )
	);
	printf(
		'<p class="description" style="margin:0">Show this product on the <a href="%s" target="_blank">Promotions page</a>.</p>',
		esc_url( get_term_link( $term ) )
	);
}

/* Main categories (and their parents) that contain promoted products – cached, cleared with the category cache */
function fvs_promo_category_ids() {
	static $ids = null;
	if ( null !== $ids ) return $ids;

	$ids = get_transient( 'fvs_promo_cats' );
	if ( is_array( $ids ) ) return $ids;

	$ids      = array();
	$products = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array( 'taxonomy' => FVS_PROMO_TAX, 'field' => 'slug', 'terms' => FVS_PROMO_TERM ),
			array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ),
		),
	) );
	if ( $products ) {
		foreach ( wp_get_object_terms( $products, 'product_cat', array( 'fields' => 'ids' ) ) as $cat_id ) {
			$ids[] = (int) $cat_id;
			foreach ( get_ancestors( $cat_id, 'product_cat', 'taxonomy' ) as $parent ) {
				$ids[] = (int) $parent;
			}
		}
		$ids = array_values( array_unique( $ids ) );
	}
	set_transient( 'fvs_promo_cats', $ids, 12 * HOUR_IN_SECONDS );
	return $ids;
}

/* Selected category on the promotions page (?pcat=slug) */
function fvs_promo_current_cat() {
	if ( empty( $_GET['pcat'] ) ) return null; // phpcs:ignore WordPress.Security.NonceVerification
	$term = get_term_by( 'slug', sanitize_title( wp_unslash( $_GET['pcat'] ) ), 'product_cat' ); // phpcs:ignore WordPress.Security.NonceVerification
	return ( $term && ! is_wp_error( $term ) ) ? $term : null;
}

/* Category filter: limit the promotions page to promoted products in that category.
   Done with post__in so the page stays the Promotions archive (title, template, links). */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() || ! $q->is_tax( FVS_PROMO_TAX ) ) return;
	$cat = fvs_promo_current_cat();
	if ( ! $cat ) return;
	$ids = get_posts( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'tax_query'      => array(
			array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $cat->term_id, 'include_children' => true ),
			array( 'taxonomy' => FVS_PROMO_TAX, 'field' => 'slug', 'terms' => FVS_PROMO_TERM ),
		),
	) );
	$q->set( 'post__in', $ids ? $ids : array( 0 ) );
}, 20 );

/* Categories under $parent (0 = main) that contain promoted products */
function fvs_promo_categories( $parent, $in ) {
	$terms = get_terms( array(
		'taxonomy'   => 'product_cat',
		'parent'     => (int) $parent,
		'hide_empty' => false,
		'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		'orderby'    => 'menu_order',
		'order'      => 'ASC',
	) );
	if ( is_wp_error( $terms ) ) return array();
	return array_values( array_filter( $terms, function ( $t ) use ( $in ) {
		return in_array( (int) $t->term_id, $in, true );
	} ) );
}

/* Promotions page -------------------------------------------------------- */
function fvs_render_promotion_archive( $show_title ) {
	$base    = fvs_promo_url();
	$current = fvs_promo_current_cat();
	$top     = $current ? fvs_top_ancestor( $current ) : null;
	$in      = fvs_promo_category_ids();

	echo '<div class="fvs fvs--promo">';

	/* Main categories: "All" + categories that have promoted products */
	$cats = fvs_promo_categories( 0, $in );
	echo '<nav class="fvs-maincats" aria-label="Promotion categories"><div class="fvs-maincats__track">';
	printf(
		'<a href="%s" class="fvs-maincat%s"%s>All</a>',
		esc_url( $base ),
		$top ? '' : ' is-active',
		$top ? '' : ' aria-current="page"'
	);
	foreach ( $cats as $cat ) {
		$active = $top && $top->term_id === $cat->term_id;
		printf(
			'<a href="%s" class="fvs-maincat%s"%s>%s</a>',
			esc_url( add_query_arg( 'pcat', $cat->slug, $base ) ),
			$active ? ' is-active' : '',
			$active ? ' aria-current="page"' : '',
			esc_html( $cat->name )
		);
	}
	echo '</div></nav>';

	/* Sub-categories of the selected main category (only those with promoted products) */
	if ( $top ) {
		$subs = fvs_promo_categories( $top->term_id, $in );
		if ( $subs ) {
			echo '<div class="fvs-subcats" role="list">';
			printf(
				'<a role="listitem" href="%s" class="fvs-subcat%s">All</a>',
				esc_url( add_query_arg( 'pcat', $top->slug, $base ) ),
				$current->term_id === $top->term_id ? ' is-active' : ''
			);
			foreach ( $subs as $sub ) {
				$active = $current->term_id === $sub->term_id;
				printf(
					'<a role="listitem" href="%s" class="fvs-subcat%s"%s>%s</a>',
					esc_url( add_query_arg( 'pcat', $sub->slug, $base ) ),
					$active ? ' is-active' : '',
					$active ? ' aria-current="page"' : '',
					esc_html( $sub->name )
				);
			}
			echo '</div>';
		}
	}

	fvs_render_results(
		$show_title ? ( $current ? 'Promotions: ' . $current->name : 'Promotions' ) : '',
		$current ? 'There are no promotions in this category right now.' : 'There are no promotions right now. Please check back soon.',
		'Browse all products'
	);

	echo '</div>';
}

/* ---------------------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------------------ */

function fvs_shop_shortcode_render( $atts ) {
	if ( ! function_exists( 'WC' ) ) {
		return '';
	}
	$atts = shortcode_atts( array( 'show_title' => 'yes' ), $atts, 'fanvil_shop' );

	ob_start();
	fvs_print_assets();

	if ( is_post_type_archive( 'fanvil_event' ) && function_exists( 'fve_render_events' ) ) {
		echo fve_render_events( array() ); // phpcs:ignore -- /events/ shown through a shop template
	} elseif ( is_tax( FVS_PROMO_TAX ) ) {
		fvs_render_promotion_archive( 'yes' === $atts['show_title'] );
	} elseif ( is_shop() && ! is_search() && ! is_product_category() ) {
		fvs_render_category_grid();
	} else {
		fvs_render_archive( 'yes' === $atts['show_title'] );
	}
	return ob_get_clean();
}

/* Shop page: main categories -------------------------------------------- */
function fvs_render_category_grid() {
	$cats = fvs_top_categories();
	echo '<div class="fvs fvs--cats">';

	/* Heading – shop page only (this grid is only shown on the main shop page) */
	echo '<header class="fvs-cats-head">';
	echo '<h1 class="fvs-cats-head__title">Our <strong>Categories</strong></h1>';
	echo '<p class="fvs-cats-head__text">Find the right Fanvil solution.</p>';
	echo '</header>';

	if ( ! $cats ) {
		echo '<p class="fvs-empty">No products are available yet.</p></div>';
		return;
	}

	echo '<ul class="fvs-catgrid">';
	foreach ( $cats as $cat ) {
		echo '<li>';
		fvs_render_catcard( $cat );
		echo '</li>';
	}
	echo '</ul></div>';
}

/* One category card (image + red name button) */
function fvs_render_catcard( $cat ) {
	$img_id = fvs_cat_image_id( $cat );
	echo '<a class="fvs-catcard" href="' . esc_url( get_term_link( $cat ) ) . '">';
	echo '<div class="fvs-catcard__media">';
	if ( $img_id ) {
		echo wp_get_attachment_image( $img_id, 'woocommerce_single', false, array(
			'class'   => 'fvs-catcard__img',
			'loading' => 'lazy',
			'alt'     => esc_attr( $cat->name ),
		) );
	}
	echo '</div>';
	echo '<span class="fvs-catcard__label">' . esc_html( $cat->name ) . '</span>';
	echo '</a>';
}

/* Category / archive page ----------------------------------------------- */
function fvs_render_archive( $show_title ) {
	$current = is_product_category() ? get_queried_object() : null;
	$top     = $current ? fvs_top_ancestor( $current ) : null;

	echo '<div class="fvs">';

	/* Main categories */
	$cats = fvs_top_categories();
	if ( $cats ) {
		echo '<nav class="fvs-maincats" aria-label="Product categories"><div class="fvs-maincats__track">';
		foreach ( $cats as $cat ) {
			$active = $top && $top->term_id === $cat->term_id;
			printf(
				'<a href="%s" class="fvs-maincat%s"%s>%s</a>',
				esc_url( get_term_link( $cat ) ),
				$active ? ' is-active' : '',
				$active ? ' aria-current="page"' : '',
				esc_html( $cat->name )
			);
		}
		echo '</div></nav>';
	}

	/* Sub-categories */
	if ( $top ) {
		$subs = fvs_sub_categories( $top );
		if ( $subs ) {
			$all_active = $current->term_id === $top->term_id;
			echo '<div class="fvs-subcats" role="list">';
			printf(
				'<a role="listitem" href="%s" class="fvs-subcat%s">All</a>',
				esc_url( get_term_link( $top ) ),
				$all_active ? ' is-active' : ''
			);
			foreach ( $subs as $sub ) {
				$active = $current->term_id === $sub->term_id;
				printf(
					'<a role="listitem" href="%s" class="fvs-subcat%s"%s>%s</a>',
					esc_url( get_term_link( $sub ) ),
					$active ? ' is-active' : '',
					$active ? ' aria-current="page"' : '',
					esc_html( $sub->name )
				);
			}
			echo '</div>';
		}
	}

	/* Heading: category, tag or search term */
	$title = '';
	$empty = 'There are no products in this category yet.';
	if ( $current ) {
		$title = $current->name;
	} elseif ( is_product_tag() ) {
		$title = single_term_title( '', false );
		$empty = 'There are no products with this tag yet.';
	} elseif ( is_search() ) {
		$title = sprintf( 'Search results for “%s”', get_search_query( false ) );
		$empty = 'No products match your search. Try another word or browse the categories.';
	}
	fvs_render_results( $show_title ? $title : '', $empty, 'Browse all categories' );

	echo '</div>';
}

/* Heading + result count + product grid + pagination (category and promotions pages) */
function fvs_render_results( $title, $empty_text, $empty_button ) {
	global $wp_query;

	$total    = (int) $wp_query->found_posts;
	$per_page = (int) $wp_query->get( 'posts_per_page' );
	$paged    = max( 1, (int) get_query_var( 'paged' ) );
	if ( $total ) {
		$from = ( $paged - 1 ) * $per_page + 1;
		$to   = min( $total, $paged * $per_page );
		echo '<div class="fvs-head">';
		if ( '' !== $title ) {
			echo '<h1 class="fvs-head__title">' . esc_html( $title ) . '</h1>';
		}
		echo '<p class="fvs-head__count">' . esc_html( $total > $per_page ? "Showing {$from}–{$to} of {$total} products" : sprintf( _n( '%d product', '%d products', $total, 'woocommerce' ), $total ) ) . '</p>';
		echo '</div>';
	}

	if ( $wp_query->posts ) {
		echo '<ul class="fvs-grid">';
		foreach ( $wp_query->posts as $post ) {
			$product = wc_get_product( $post );
			if ( $product && $product->is_visible() ) {
				fvs_render_card( $product );
			}
		}
		echo '</ul>';
		fvs_render_pagination();
	} else {
		echo '<div class="fvs-empty"><p>' . esc_html( $empty_text ) . '</p>';
		echo '<a class="fvs-btn fvs-btn--ghost" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html( $empty_button ) . '</a></div>';
	}
}

/* Product card ---------------------------------------------------------- */
function fvs_render_card( $product ) {
	$link  = get_permalink( $product->get_id() );
	$sku   = $product->get_sku();
	$title = $sku ? $sku : $product->get_name();

	$img_ids = array_values( array_unique( array_filter( array_merge(
		array( $product->get_image_id() ),
		$product->get_gallery_image_ids()
	) ) ) );
	$img_ids = array_slice( $img_ids, 0, 3 ); // 3 images per card; the product page shows every image

	echo '<li class="fvs-card">';

	/* Image carousel */
	echo '<div class="fvs-media" data-fvs-carousel>';
	if ( $product->is_on_sale() ) {
		$regular = (float) $product->get_regular_price();
		$sale    = (float) $product->get_sale_price();
		$badge   = ( $regular > 0 && $sale > 0 && $sale < $regular ) ? '−' . round( ( 1 - $sale / $regular ) * 100 ) . '%' : 'Sale';
		echo '<span class="fvs-badge">' . esc_html( $badge ) . '</span>';
	}
	echo '<a class="fvs-media__link" href="' . esc_url( $link ) . '" tabindex="-1" aria-hidden="true"><div class="fvs-media__track">';
	if ( $img_ids ) {
		foreach ( $img_ids as $i => $id ) {
			echo '<div class="fvs-media__slide">' . wp_get_attachment_image( $id, 'woocommerce_single', false, array(
				'class'   => 'fvs-media__img',
				'loading' => 0 === $i ? 'eager' : 'lazy',
				'alt'     => esc_attr( $product->get_name() ),
			) ) . '</div>';
		}
	} else {
		echo '<div class="fvs-media__slide">' . wc_placeholder_img( 'woocommerce_single' ) . '</div>';
	}
	echo '</div></a>';
	if ( count( $img_ids ) > 1 ) {
		echo '<div class="fvs-media__bars">';
		foreach ( $img_ids as $i => $id ) {
			printf(
				'<button type="button" class="fvs-media__bar%s" aria-label="Show image %d of %d"><span></span></button>',
				0 === $i ? ' is-active' : '',
				$i + 1,
				count( $img_ids )
			);
		}
		echo '</div>';
	}
	echo '</div>';

	/* Text */
	echo '<div class="fvs-card__body">';
	echo '<h2 class="fvs-card__title"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h2>';
	echo '<p class="fvs-card__subtitle">' . esc_html( fvs_subtitle( $product ) ) . '</p>';

	/* Price → excl. VAT → quantity + cart → View details → stock & shipping */
	$has_price = '' !== $product->get_price();
	echo '<div class="fvs-card__buy" data-fvs-buy>';
	if ( $has_price ) {
		echo '<div class="fvs-card__price">' . wp_kses_post( $product->get_price_html() );
		if ( fvs_show_excl_vat() ) {
			echo '<span class="fvs-card__tax">excl. VAT</span>';
		}
		echo '</div>';
	} else {
		echo '<div class="fvs-card__price fvs-card__price--poa">Price on request</div>';
	}

	if ( $has_price && $product->is_type( 'simple' ) && $product->is_purchasable() && $product->is_in_stock() ) {
		fvs_render_buy_form( $product, $title, '', true );
	}
	echo '<a class="fvs-btn fvs-btn--ghost fvs-card__details" href="' . esc_url( $link ) . '">View details</a>';
	echo fvs_stock_html( $product ); // phpcs:ignore
	echo '</div>';

	echo '</div></li>';
}


/* "excl. VAT" is shown unless WooCommerce is set to display prices including tax */
function fvs_show_excl_vat() {
	return ! ( wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_shop' ) );
}

/* "Ready to ship in 24h" under "In stock" on product cards and the product page.
   Hidden for now – change false to true to show it again. */
if ( ! defined( 'FVS_SHOW_SHIP_NOTE' ) ) {
	define( 'FVS_SHOW_SHIP_NOTE', false );
}

/* Stock + shipping line */
function fvs_stock_html( $product ) {
	$status = $product->get_stock_status();
	$labels = array(
		'instock'     => 'In stock',
		'onbackorder' => 'Available on backorder',
		'outofstock'  => 'Out of stock',
	);
	$html  = '<div class="fvs-status">';
	$html .= '<p class="fvs-stock fvs-stock--' . esc_attr( $status ) . '"><span class="fvs-stock__dot" aria-hidden="true"></span><span class="fvs-stock__label">' . esc_html( isset( $labels[ $status ] ) ? $labels[ $status ] : $status ) . '</span></p>';
	if ( FVS_SHOW_SHIP_NOTE && 'instock' === $status ) {
		$html .= '<p class="fvs-ship"><svg viewBox="0 0 24 24" width="15" height="15" aria-hidden="true"><path d="M2 6h11v9H2zM13 9h4l3 3v3h-7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="6" cy="17.5" r="1.8" fill="#fff" stroke="currentColor" stroke-width="1.8"/><circle cx="16.5" cy="17.5" r="1.8" fill="#fff" stroke="currentColor" stroke-width="1.8"/></svg><span>Ready to ship in 24h</span></p>';
	}
	return $html . '</div>';
}

/* Quantity + Add to cart form.
 * A normal WooCommerce form: JavaScript adds to cart without reloading,
 * and if scripts are blocked or delayed the form still posts and adds to cart. */
function fvs_render_buy_form( $product, $label, $extra_class = '', $with_icon = false ) {
	$max = $product->get_max_purchase_quantity();
	printf(
		'<form class="fvs-card__actions %s" method="post" action="%s" data-fvs-form data-fvs-endpoint="%s" data-fvs-cart="%s">',
		esc_attr( $extra_class ),
		esc_url( get_permalink( $product->get_id() ) ),
		esc_url( WC_AJAX::get_endpoint( 'add_to_cart' ) ),
		esc_url( wc_get_cart_url() )
	);
	echo '<div class="fvs-qty">';
	echo '<button type="button" class="fvs-qty__btn" data-fvs-step="-1" aria-label="Decrease quantity">&minus;</button>';
	printf(
		'<input class="fvs-qty__input" type="number" name="quantity" inputmode="numeric" min="1" %s value="1" aria-label="Quantity for %s">',
		$max > 0 ? 'max="' . (int) $max . '"' : '',
		esc_attr( $label )
	);
	echo '<button type="button" class="fvs-qty__btn" data-fvs-step="1" aria-label="Increase quantity">+</button>';
	echo '</div>';
	$icon = $with_icon ? '<svg class="fvs-btn__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M3 4h2.2l2.1 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.5L21 8H6.1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.4" fill="currentColor"/><circle cx="17" cy="20" r="1.4" fill="currentColor"/></svg>' : '';
	printf(
		'<button type="submit" name="add-to-cart" value="%d" class="fvs-btn fvs-btn--cart%s" aria-label="Add %s to cart">%s<span class="fvs-btn__label">Add to cart</span></button>',
		$product->get_id(),
		$with_icon ? ' fvs-btn--icon' : '',
		esc_attr( $label ),
		$icon // phpcs:ignore
	);
	echo '</form>';
}

/* Pagination ------------------------------------------------------------ */
function fvs_render_pagination() {
	global $wp_query;
	$total = (int) $wp_query->max_num_pages;
	if ( $total < 2 ) {
		return;
	}
	$links = paginate_links( array(
		'base'      => esc_url_raw( str_replace( 999999999, '%#%', remove_query_arg( 'add-to-cart', get_pagenum_link( 999999999, false ) ) ) ),
		'format'    => '',
		'current'   => max( 1, (int) get_query_var( 'paged' ) ),
		'total'     => $total,
		'prev_text' => '<span aria-hidden="true">&lsaquo;</span><span class="fvs-sr">Previous page</span>',
		'next_text' => '<span aria-hidden="true">&rsaquo;</span><span class="fvs-sr">Next page</span>',
		'type'      => 'list',
		'end_size'  => 1,
		'mid_size'  => 1,
	) );
	echo '<nav class="fvs-pagination" aria-label="Product pages">' . $links . '</nav>';
}

/* ---------------------------------------------------------------------------
 * CSS + JS (printed once per page)
 * ------------------------------------------------------------------------ */
function fvs_print_assets() {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style id="fvs-css">
.fvs{--fvs-red:#DB141D;--fvs-red-dark:#B30F17;--fvs-ink:#1A1D21;--fvs-muted:#6B7280;--fvs-line:#E6E8EB;--fvs-soft:#F4F5F7;--fvs-green:#1F9D55;font-family:'Poppins',sans-serif;color:var(--fvs-ink)}
.fvs *{box-sizing:border-box}
.fvs ul{list-style:none;margin:0;padding:0}
.fvs a{text-decoration:none;color:inherit}
.fvs .fvs-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.fvs :focus-visible{outline:2px solid var(--fvs-red);outline-offset:2px}

/* Main categories */
.fvs-maincats{border-bottom:1px solid var(--fvs-line);margin-bottom:20px}
.fvs-maincats__track{display:flex;gap:32px;overflow-x:auto;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.fvs-maincats__track::-webkit-scrollbar{display:none}
.fvs-maincat{position:relative;flex:0 0 auto;padding:14px 0;font-size:15px;font-weight:500;color:var(--fvs-muted);white-space:nowrap;transition:color .2s}
.fvs-maincat:hover{color:var(--fvs-ink)}
.fvs-maincat.is-active{color:var(--fvs-ink);font-weight:600}
.fvs-maincat.is-active::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:3px;border-radius:3px 3px 0 0;background:var(--fvs-red)}

/* Sub-categories */
.fvs-subcats{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:28px}
.fvs .fvs-subcat{display:inline-flex;align-items:center;margin:0;padding:8px 18px;border:1px solid var(--fvs-line);border-radius:999px;background:#fff;font-size:14px;font-weight:500;line-height:1.3;color:var(--fvs-ink);transition:border-color .2s,background .2s,color .2s}
.fvs .fvs-subcat:hover{border-color:var(--fvs-red);color:var(--fvs-red)}
.fvs .fvs-subcat.is-active,.fvs .fvs-subcat.is-active:hover{background:var(--fvs-red);border-color:var(--fvs-red);color:#fff}

/* Heading */
.fvs-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px}
.fvs-head__title{margin:0;font-size:26px;font-weight:600;line-height:1.2}
.fvs-head__count{margin:0;font-size:14px;color:var(--fvs-muted)}

/* Grids */
.fvs-cats-head{margin:8px 0 32px}
.fvs .fvs-cats-head__title{margin:0;font-size:44px;font-weight:400;line-height:1.15;color:var(--fvs-ink);letter-spacing:-.01em;text-transform:none}
.fvs .fvs-cats-head__title strong{font-weight:700;color:var(--fvs-red)}
.fvs .fvs-cats-head__text{margin:12px 0 0;font-size:16px;line-height:1.5;color:#4B5563}
@media (max-width:820px){.fvs .fvs-cats-head__title{font-size:34px}.fvs-cats-head{margin-bottom:24px}}
@media (max-width:560px){.fvs .fvs-cats-head__title{font-size:28px}.fvs .fvs-cats-head__text{font-size:15px}}
.fvs-grid,.fvs-catgrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:24px}
@media (max-width:1100px){.fvs-grid,.fvs-catgrid{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media (max-width:820px){.fvs-grid,.fvs-catgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}}
/* Mobile: 2 per row, compact cards */
@media (max-width:560px){
 .fvs-grid,.fvs-catgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
 .fvs-card{border-radius:12px}
 .fvs-card .fvs-media__slide{padding:10px 10px 22px}
 .fvs-card .fvs-media__bars{bottom:6px;gap:0}
 .fvs-card .fvs-media__bar span{width:6px;height:6px}
 .fvs-card .fvs-badge{top:8px;left:8px;padding:2px 8px;font-size:11px}
 .fvs-card .fvs-card__body{padding:12px 12px 14px}
 .fvs-card .fvs-card__title{font-size:15px}
 .fvs-card .fvs-card__subtitle{margin:2px 0 10px;font-size:12px}
 .fvs-card .fvs-card__price{font-size:15px}
 .fvs-card .fvs-card__price del{font-size:12px}
 .fvs-card .fvs-card__price--poa{font-size:13px}
 .fvs-card .fvs-card__tax{font-size:11px}
 .fvs-card .fvs-card__actions{gap:6px}
 .fvs-card .fvs-qty{height:38px}
 .fvs .fvs-card .fvs-qty__btn{flex-basis:28px;width:28px}
 .fvs .fvs-card .fvs-btn--cart,.fvs .fvs-card .fvs-btn--cart:focus{flex-basis:38px;width:38px;height:38px}
 .fvs .fvs-card .fvs-btn--ghost{height:38px;padding:0 10px;font-size:13px}
 .fvs .fvs-card .fvs-card__details{margin-top:8px}
 .fvs-card .fvs-card__price,.fvs-card .fvs-card__price--poa{margin-bottom:10px}
 .fvs-card .fvs-status{gap:5px 8px;margin-top:10px}
 .fvs .fvs-card .fvs-stock{gap:6px;font-size:11.5px}
 .fvs .fvs-card .fvs-ship{padding:2px 8px 2px 6px !important;font-size:10.5px}
 .fvs .fvs-card .fvs-ship svg{flex-basis:12px;width:12px;height:12px}
 .fvs .fvs-card .fvs-card__viewcart{font-size:12px}

 .fvs--cats{padding:28px 0 44px}
 .fvs .fvs-catcard{padding:8px 8px 10px;border-radius:12px}
 .fvs .fvs-catcard:hover{transform:none}
 .fvs-catcard__media{padding:4px 6px 10px}
 .fvs-catcard__label{min-height:34px;padding:6px 10px;font-size:12px}

 .fvs-maincat{padding:12px 0;font-size:14px}
 .fvs-subcats{gap:8px;margin-bottom:20px}
 .fvs .fvs-subcat{padding:6px 14px;font-size:13px}
 .fvs-head{margin-bottom:14px}
 .fvs-head__count{font-size:13px}
 .fvs-pagination{margin-top:28px}
 .fvs .fvs-pagination li > .page-numbers{min-width:38px;height:38px;font-size:13px}
}

/* Category card */
.fvs--cats{padding:64px 0 88px}
@media (max-width:820px){.fvs--cats{padding:40px 0 56px}}
.fvs .fvs-catcard{display:flex;flex-direction:column;height:100%;padding:12px 12px 14px;background:#fff;border-radius:14px;box-shadow:0 8px 28px rgba(26,29,33,.08);transition:transform .25s ease,box-shadow .25s ease;color:var(--fvs-ink)}
.fvs .fvs-catcard:hover{transform:translateY(-4px);box-shadow:0 14px 36px rgba(26,29,33,.14)}
.fvs-catcard__media{aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;padding:8px 12px 14px}
.fvs-catcard__img{width:100%;height:100%;object-fit:contain;transition:transform .35s ease}
.fvs-catcard:hover .fvs-catcard__img{transform:scale(1.04)}
.fvs-catcard__label{margin-top:auto;display:flex;align-items:center;justify-content:center;min-height:40px;padding:8px 16px;border-radius:999px;background:var(--fvs-red);color:#fff;font-size:14px;font-weight:600;line-height:1.25;text-align:center;transition:background .2s}
.fvs-catcard:hover .fvs-catcard__label{background:var(--fvs-red-dark)}
@media (prefers-reduced-motion:reduce){.fvs .fvs-catcard,.fvs-catcard__img{transition:none}.fvs .fvs-catcard:hover{transform:none}.fvs-catcard:hover .fvs-catcard__img{transform:none}}

/* Product card */
.fvs-card{display:flex;flex-direction:column;background:#fff;border:1px solid var(--fvs-line);border-radius:14px;overflow:hidden;transition:border-color .2s}
.fvs-badge{position:absolute;top:12px;left:12px;z-index:2;padding:4px 10px;border-radius:999px;background:var(--fvs-red);color:#fff;font-size:12px;font-weight:600;line-height:1.3}
.fvs-media{position:relative;background:var(--fvs-soft);overflow:hidden;touch-action:pan-y;-webkit-tap-highlight-color:transparent}
.fvs .fvs-media__link,.fvs .fvs-media__link:focus{display:block;outline:none;border:0;box-shadow:none}
.fvs-media__track{display:flex;transition:transform .55s cubic-bezier(.22,.61,.36,1);will-change:transform}
.fvs-media__slide{flex:0 0 100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;padding:18px 18px 30px}
.fvs-media__img,.fvs-media__slide img{width:100%;height:100%;object-fit:contain;user-select:none;-webkit-user-drag:none}
.fvs-media__bars{position:absolute;left:0;right:0;bottom:10px;display:flex;justify-content:center;gap:2px}
.fvs .fvs-media__bar,.fvs .fvs-media__bar:hover,.fvs .fvs-media__bar:focus,.fvs .fvs-media__bar:active{display:flex;align-items:center;justify-content:center;flex:0 0 18px;width:18px;height:18px;min-height:0;padding:0 !important;margin:0;border:0 !important;border-radius:50%;background:transparent !important;box-shadow:none !important;outline:none;cursor:pointer}
.fvs .fvs-media__bar:focus-visible{outline:2px solid var(--fvs-red);outline-offset:0}
.fvs-media__bar span{display:block;width:7px;height:7px;border-radius:50%;background:rgba(26,29,33,.2);transition:background .2s,transform .2s}
.fvs-media__bar:hover span{background:rgba(26,29,33,.4)}
.fvs-media__bar.is-active span{background:var(--fvs-red);transform:scale(1.15)}

.fvs-card__body{display:flex;flex-direction:column;flex:1;padding:18px 20px 20px}
.fvs-card__title{margin:0;font-size:17px;font-weight:600;line-height:1.2}
.fvs-card__title a:hover{color:var(--fvs-red)}
.fvs-card__subtitle{margin:4px 0 18px;font-size:14px;line-height:1.45;color:var(--fvs-muted);min-height:2.9em;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.fvs-card__buy{margin-top:auto}
.fvs-card__price{font-size:19px;font-weight:600;margin-bottom:14px;line-height:1.3}
.fvs-card__price del{color:var(--fvs-muted);font-weight:400;font-size:15px;margin-right:6px}
.fvs-card__price ins{text-decoration:none}
.fvs-card__price--poa{font-size:15px;font-weight:500;color:var(--fvs-muted)}
.fvs-card__tax{display:block;margin-top:2px;font-size:12px;font-weight:400;color:var(--fvs-muted)}
.fvs .fvs-btn--icon{gap:8px}
.fvs-btn__icon{flex:0 0 auto}
.fvs .fvs-card__details{margin-top:10px}
.fvs-card .fvs-card__actions{gap:8px}
.fvs-card .fvs-card__actions{justify-content:flex-start}
.fvs-card .fvs-qty{flex:0 0 auto}
.fvs .fvs-card .fvs-btn--cart,.fvs .fvs-card .fvs-btn--cart:focus{flex:0 0 42px;width:42px;height:42px;padding:0 !important;border-radius:50%}
.fvs .fvs-card .fvs-btn--cart .fvs-btn__icon{width:20px;height:20px}
.fvs-card .fvs-btn__label{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.fvs-card .fvs-card__price{margin-bottom:14px}
.fvs-card .fvs-card__price--poa{margin-bottom:14px}
.fvs-card__actions{display:flex;align-items:center;gap:10px}

.fvs-qty{display:flex;align-items:center;border:1px solid var(--fvs-line);border-radius:999px;height:42px;flex:0 0 auto}
.fvs .fvs-qty__btn,.fvs .fvs-qty__btn:hover,.fvs .fvs-qty__btn:focus,.fvs .fvs-qty__btn:active{flex:0 0 32px;width:32px;height:100%;min-height:0;margin:0;padding:0 !important;border:0 !important;border-radius:999px;background:transparent !important;box-shadow:none !important;font-family:inherit;font-size:18px;line-height:1;color:var(--fvs-ink) !important;cursor:pointer}
.fvs .fvs-qty__btn:hover{color:var(--fvs-red) !important}
.fvs .fvs-qty__input,.fvs .fvs-qty__input:focus{flex:0 0 auto;width:calc(2ch + 6px) !important;min-width:0;height:100% !important;margin:0;padding:0 !important;border:0 !important;border-radius:0;background:transparent !important;box-shadow:none !important;outline:none;text-align:center;font-family:inherit;font-size:15px;font-weight:500;color:var(--fvs-ink) !important;-moz-appearance:textfield}
.fvs-qty__input::-webkit-outer-spin-button,.fvs-qty__input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}

.fvs-btn{display:inline-flex;align-items:center;justify-content:center;height:42px;padding:0 20px;border-radius:999px;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s,color .2s,border-color .2s;white-space:nowrap}
.fvs .fvs-btn--cart,.fvs .fvs-btn--cart:focus{flex:1 1 auto;min-width:0;height:42px;margin:0;padding:0 16px !important;border:0 !important;border-radius:999px;background:var(--fvs-red) !important;color:#fff !important;box-shadow:none !important;text-transform:none}
.fvs .fvs-btn--cart:hover{background:var(--fvs-red-dark) !important;color:#fff !important}
.fvs .fvs-btn--cart[disabled]{opacity:.7;cursor:progress}
.fvs .fvs-btn--cart.is-added{background:var(--fvs-green) !important}
.fvs .fvs-btn--ghost{width:100%;border:1px solid var(--fvs-red);background:#fff;color:var(--fvs-red)}
.fvs .fvs-btn--ghost:hover,.fvs .fvs-btn--ghost:focus-visible{background:var(--fvs-red);border-color:var(--fvs-red);color:#fff}
.fvs-empty .fvs-btn--ghost{width:auto}

.fvs-status{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px;margin-top:14px}
.fvs .fvs-stock,.fvs .fvs-ship{display:flex !important;align-items:center;gap:8px;margin:0 !important;padding:0;font-size:13px;line-height:1.35;color:var(--fvs-muted);visibility:visible}
.fvs-stock__dot{flex:0 0 8px}
.fvs-stock__label{font-weight:500;color:var(--fvs-ink)}
.fvs .fvs-ship{gap:5px;padding:3px 10px 3px 8px !important;border-radius:999px;background:#E8F6EE;color:#17804A;font-size:12px;font-weight:500;white-space:nowrap}
.fvs .fvs-ship svg{display:block;flex:0 0 14px;width:14px;height:14px;color:#17804A}
.fvs .fvs-ship span{display:inline;color:#17804A}
.fvs-stock__dot{width:8px;height:8px;border-radius:50%;background:var(--fvs-green)}
.fvs-stock--instock .fvs-stock__label{color:var(--fvs-green)}
.fvs-stock--outofstock .fvs-stock__dot{background:var(--fvs-red)}
.fvs-stock--onbackorder .fvs-stock__dot{background:#D97706}
.fvs .added_to_cart{display:none !important}
.fvs .fvs-card__viewcart{display:inline-block;margin-top:10px;font-size:13px;font-weight:500;color:var(--fvs-red);text-decoration:underline;text-underline-offset:3px}
.fvs-card__error{margin:10px 0 0;font-size:13px;color:var(--fvs-red)}

/* Pagination */
.fvs-pagination{margin-top:40px}
.fvs .fvs-pagination ul.page-numbers{display:flex;justify-content:center;flex-wrap:wrap;gap:8px;margin:0;padding:0;border:0 !important;border-radius:0;background:none;box-shadow:none}
.fvs .fvs-pagination li{margin:0;padding:0;border:0 !important;background:none;float:none}
.fvs .fvs-pagination li > .page-numbers{display:flex;align-items:center;justify-content:center;min-width:42px;height:42px;padding:0 12px;border:1px solid var(--fvs-line);border-radius:999px;font-size:14px;font-weight:500;background:#fff;color:var(--fvs-ink);transition:border-color .2s,color .2s,background .2s}
.fvs .fvs-pagination li > .prev,.fvs .fvs-pagination li > .next{font-size:20px;line-height:1}
.fvs .fvs-pagination a.page-numbers:hover{border-color:var(--fvs-red);color:var(--fvs-red)}
.fvs .fvs-pagination li > .page-numbers.current{background:var(--fvs-red);border-color:var(--fvs-red);color:#fff}
.fvs .fvs-pagination li > .page-numbers.dots{border-color:transparent;background:none}

.fvs-empty{padding:48px 0;text-align:center;color:var(--fvs-muted)}
.fvs-empty p{margin:0 0 16px}

@media (max-width:560px){
 .fvs-maincats__track{gap:24px}
 .fvs-head__title{font-size:22px}
}
@media (prefers-reduced-motion:reduce){
 .fvs-media__track{transition:none}
}
</style>
	<?php
	wp_enqueue_script( 'fvs' );
}

function fvs_script_js() {
	return <<<'FVSJS'
(function () {
	if (window.fvsLoaded) return;
	window.fvsLoaded = true;
	var DELAY = 3500;
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	/* ---------- Card image carousel ---------- */
	/* Hover-only: images slide only while the mouse is over that one card,
	   and return to the main image when the mouse leaves. Touch: swipe / tap dots. */
	var canHover = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
	function initCarousel(el) {
		if (el.dataset.fvsReady) return;
		el.dataset.fvsReady = '1';
		var track = el.querySelector('.fvs-media__track');
		var dots = [].slice.call(el.querySelectorAll('.fvs-media__bar'));
		var n = track.children.length;
		if (n < 2) return;
		var i = 0, first = null, timer = null;
		function go(k) {
			i = (k + n) % n;
			track.style.transform = 'translateX(' + (-i * 100) + '%)';
			dots.forEach(function (d, j) {
				d.classList.toggle('is-active', j === i);
				d.setAttribute('aria-current', j === i ? 'true' : 'false');
			});
		}
		function stop() {
			clearTimeout(first); clearInterval(timer);
			first = timer = null;
		}
		dots.forEach(function (d, j) {
			d.addEventListener('click', function (e) { e.preventDefault(); go(j); });
		});
		var card = el.closest('.fvs-card') || el;
		if (canHover) {
			card.addEventListener('mouseenter', function () {
				stop();
				first = setTimeout(function () {
					go(i + 1);
					timer = setInterval(function () { go(i + 1); }, 1400);
				}, 350);
			});
			card.addEventListener('mouseleave', function () { stop(); go(0); });
		}
		var startX = null, moved = false;
		el.addEventListener('pointerdown', function (e) { startX = e.clientX; moved = false; });
		el.addEventListener('pointerup', function (e) {
			if (startX === null) return;
			var dx = e.clientX - startX;
			if (Math.abs(dx) > 40) { moved = true; stop(); go(dx < 0 ? i + 1 : i - 1); }
			startX = null;
		});
		el.addEventListener('click', function (e) { if (moved) { e.preventDefault(); moved = false; } }, true);
	}

	/* ---------- Product page gallery (click handling is delegated, see below) ---------- */
	function gallery(el) {
		if (el._fvp) return el._fvp;
		var track = el.querySelector('.fvp-stage__track');
		var slides = [].slice.call(track.children);
		var thumbs = [].slice.call(el.querySelectorAll('.fvp-thumb'));
		var counter = el.querySelector('.fvp-counter');
		var api = { i: 0, n: slides.length, slides: slides, moved: false };
		api.go = function (k) {
			var n = api.n;
			api.i = ((k % n) + n) % n;
			track.style.transform = 'translateX(' + (-api.i * 100) + '%)';
			thumbs.forEach(function (t, j) {
				t.classList.toggle('is-active', j === api.i);
				t.setAttribute('aria-current', j === api.i ? 'true' : 'false');
			});
			var t = thumbs[api.i];
			if (t && t.parentNode) {
				var box = t.parentNode;
				box.scrollTo({ left: t.offsetLeft - box.clientWidth / 2 + t.clientWidth / 2, behavior: reduceMotion ? 'auto' : 'smooth' });
			}
			if (counter) counter.textContent = (api.i + 1) + ' / ' + n;
		};
		var stage = el.querySelector('.fvp-stage'), startX = null;
		stage.addEventListener('pointerdown', function (e) { startX = e.clientX; api.moved = false; });
		stage.addEventListener('pointerup', function (e) {
			if (startX === null) return;
			var dx = e.clientX - startX;
			if (Math.abs(dx) > 40 && api.n > 1) { api.moved = true; api.go(dx < 0 ? api.i + 1 : api.i - 1); }
			startX = null;
		});
		el.addEventListener('keydown', function (e) {
			if (e.target.closest('.fvp-lightbox')) return;
			if (e.key === 'ArrowLeft') api.go(api.i - 1);
			if (e.key === 'ArrowRight') api.go(api.i + 1);
		});
		el._fvp = api;
		return api;
	}

	/* ---------- Lightbox ---------- */
	var lb = null, lbState = null;
	function buildLightbox() {
		lb = document.createElement('div');
		lb.className = 'fvp-lightbox';
		lb.setAttribute('role', 'dialog');
		lb.setAttribute('aria-modal', 'true');
		lb.setAttribute('aria-label', 'Image viewer');
		lb.innerHTML =
			'<button type="button" class="fvp-lb__close" aria-label="Close image viewer"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>' +
			'<button type="button" class="fvp-lb__nav fvp-lb__prev" aria-label="Previous image"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M15 5l-7 7 7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
			'<figure class="fvp-lb__figure"><img class="fvp-lb__img" alt=""></figure>' +
			'<button type="button" class="fvp-lb__nav fvp-lb__next" aria-label="Next image"><svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M9 5l7 7-7 7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>' +
			'<span class="fvp-lb__counter" aria-live="polite"></span>';
		document.body.appendChild(lb);
		lb.addEventListener('click', function (e) {
			if (e.target.closest('.fvp-lb__close') || e.target === lb || e.target.classList.contains('fvp-lb__figure')) closeLightbox();
			else if (e.target.closest('.fvp-lb__prev')) showLb(lbState.i - 1);
			else if (e.target.closest('.fvp-lb__next')) showLb(lbState.i + 1);
		});
		var sx = null;
		lb.addEventListener('pointerdown', function (e) { sx = e.clientX; });
		lb.addEventListener('pointerup', function (e) {
			if (sx === null) return;
			var dx = e.clientX - sx;
			if (Math.abs(dx) > 50) showLb(dx < 0 ? lbState.i + 1 : lbState.i - 1);
			sx = null;
		});
		document.addEventListener('keydown', function (e) {
			if (!lb.classList.contains('is-open')) return;
			if (e.key === 'Escape') closeLightbox();
			if (e.key === 'ArrowLeft') showLb(lbState.i - 1);
			if (e.key === 'ArrowRight') showLb(lbState.i + 1);
			if (e.key === 'Tab') { // keep focus inside the viewer
				var f = [].slice.call(lb.querySelectorAll('button')).filter(function (b) { return b.offsetParent !== null; });
				var first = f[0], last = f[f.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			}
		});
	}
	function showLb(k) {
		var n = lbState.api.n;
		lbState.i = ((k % n) + n) % n;
		var slide = lbState.api.slides[lbState.i];
		var img = slide.querySelector('img');
		var el = lb.querySelector('.fvp-lb__img');
		el.src = slide.dataset.full || (img && (img.currentSrc || img.src)) || '';
		el.alt = img ? img.alt : '';
		lb.querySelector('.fvp-lb__counter').textContent = n > 1 ? (lbState.i + 1) + ' / ' + n : '';
		lb.classList.toggle('is-single', n < 2);
		lbState.api.go(lbState.i); // keep the page gallery in sync
	}
	function openLightbox(api) {
		if (!lb) buildLightbox();
		lbState = { api: api, i: api.i, returnFocus: document.activeElement };
		showLb(api.i);
		lb.classList.add('is-open');
		document.documentElement.classList.add('fvp-lb-open');
		lb.querySelector('.fvp-lb__close').focus();
	}
	function closeLightbox() {
		lb.classList.remove('is-open');
		document.documentElement.classList.remove('fvp-lb-open');
		if (lbState && lbState.returnFocus && lbState.returnFocus.focus) lbState.returnFocus.focus();
	}

	/* ---------- Delegated clicks: gallery, lightbox, quantity ---------- */
	document.addEventListener('click', function (e) {
		var g = e.target.closest('[data-fvp-gallery]');
		if (g) {
			var api = gallery(g);
			var thumb = e.target.closest('.fvp-thumb');
			if (thumb) { e.preventDefault(); api.go([].indexOf.call(g.querySelectorAll('.fvp-thumb'), thumb)); return; }
			if (e.target.closest('.fvp-nav--prev')) { e.preventDefault(); api.go(api.i - 1); return; }
			if (e.target.closest('.fvp-nav--next')) { e.preventDefault(); api.go(api.i + 1); return; }
			if (e.target.closest('.fvp-zoom') || e.target.closest('.fvp-stage__slide')) {
				if (api.moved) { api.moved = false; return; }
				e.preventDefault(); openLightbox(api); return;
			}
		}
		var step = e.target.closest('[data-fvs-step]');
		if (step) {
			var input = step.parentNode.querySelector('.fvs-qty__input');
			var max = parseInt(input.max, 10) || Infinity;
			var v = (parseInt(input.value, 10) || 1) + parseInt(step.dataset.fvsStep, 10);
			input.value = Math.min(Math.max(v, 1), max);
			fitQty(input);
		}
	});
	function fitQty(input) {
		input.style.setProperty('width', 'calc(' + Math.max(2, String(input.value).length) + 'ch + 6px)', 'important');
	}
	document.addEventListener('input', function (e) {
		if (e.target.classList && e.target.classList.contains('fvs-qty__input')) fitQty(e.target);
	});

	/* ---------- Add to cart (AJAX, falls back to a normal form post) ---------- */
	document.addEventListener('submit', function (e) {
		var form = e.target.closest('[data-fvs-form]');
		if (!form || !window.fetch) return;
		e.preventDefault();
		var btn = form.querySelector('.fvs-btn--cart');
		if (btn.disabled) return;
		var wrap = form.closest('[data-fvs-buy]') || form.parentNode;
		var old = wrap.querySelector('.fvs-card__error');
		if (old) old.remove();

		var body = new URLSearchParams();
		body.append('product_id', btn.value);
		body.append('quantity', Math.max(1, parseInt(form.querySelector('.fvs-qty__input').value, 10) || 1));

		var txt = btn.querySelector('.fvs-btn__label') || btn;
		var label = txt.textContent;
		btn.disabled = true;
		txt.textContent = 'Adding…';

		fetch(form.dataset.fvsEndpoint, {
			method: 'POST',
			body: body,
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' }
		})
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res || res.error) throw new Error('rejected');
				if (res.fragments) {
					Object.keys(res.fragments).forEach(function (sel) {
						document.querySelectorAll(sel).forEach(function (node) { node.outerHTML = res.fragments[sel]; });
					});
				}
				if (window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [res.fragments, res.cart_hash]);
				btn.classList.add('is-added');
				txt.textContent = 'Added';
				if (!wrap.querySelector('.fvs-card__viewcart')) {
					var a = document.createElement('a');
					a.className = 'fvs-card__viewcart';
					a.href = form.dataset.fvsCart;
					a.textContent = 'View cart';
					wrap.appendChild(a);
				}
				setTimeout(function () {
					btn.classList.remove('is-added');
					txt.textContent = label;
					btn.disabled = false;
				}, 2000);
			})
			.catch(function () {
				/* Let WooCommerce handle it the standard way (and show its own message) */
				txt.textContent = label;
				btn.disabled = false;
				var hidden = document.createElement('input');
				hidden.type = 'hidden';
				hidden.name = 'add-to-cart';
				hidden.value = btn.value;
				form.appendChild(hidden);
				HTMLFormElement.prototype.submit.call(form);
			});
	});

	/* ---------- Category slider (home page, more than 4 categories) ---------- */
	function initSlider(el) {
		if (el.dataset.fvsReady) return;
		el.dataset.fvsReady = '1';
		var track = el.querySelector('.fvs-slider__track');
		var prev = el.querySelector('.fvs-slider__prev');
		var next = el.querySelector('.fvs-slider__next');
		function step() {
			var item = track.querySelector('li');
			if (!item) return track.clientWidth;
			var gap = parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 0;
			var perView = Math.max(1, Math.round((track.clientWidth + gap) / (item.offsetWidth + gap)));
			return perView * (item.offsetWidth + gap);
		}
		var bar = el.querySelector('.fvs-slider__progress span');
		function update() {
			var max = track.scrollWidth - track.clientWidth;
			var atStart = track.scrollLeft <= 2, atEnd = track.scrollLeft >= max - 2;
			prev.disabled = atStart;
			next.disabled = atEnd;
			el.classList.toggle('is-static', max <= 2);
			el.classList.toggle('at-end', atEnd);
			if (bar && track.scrollWidth) {
				var w = Math.max(12, track.clientWidth / track.scrollWidth * 100);
				bar.style.width = w + '%';
				bar.style.left = (max > 0 ? track.scrollLeft / max : 0) * (100 - w) + '%';
			}
		}
		prev.addEventListener('click', function () { track.scrollBy({ left: -step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
		next.addEventListener('click', function () { track.scrollBy({ left: step(), behavior: reduceMotion ? 'auto' : 'smooth' }); });
		track.addEventListener('scroll', function () { window.requestAnimationFrame(update); }, { passive: true });
		window.addEventListener('resize', update);
		update();
	}

	function initAll() {
		document.querySelectorAll('[data-fvs-slider]').forEach(initSlider);
		document.querySelectorAll('[data-fvs-carousel]').forEach(initCarousel);
		document.querySelectorAll('[data-fvp-gallery]').forEach(gallery);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initAll);
	else initAll();
	window.addEventListener('load', initAll);
	window.fvsInit = initAll;
})();
FVSJS;
}

/* Register the script once; it is enqueued on shop pages and wherever a shortcode is used */
add_action( 'wp_enqueue_scripts', function () {
	wp_register_script( 'fvs', false, array(), '1.2', true );
	wp_add_inline_script( 'fvs', fvs_script_js() );
	if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
		wp_enqueue_script( 'fvs' );
	}
} );

/* ---------------------------------------------------------------------------
 * Safe wrapper: if anything inside a shortcode fails, the rest of the page
 * (header, footer, scripts) still loads. Admins see the exact error message.
 * ------------------------------------------------------------------------ */
function fvs_safe_render( $callback, $atts ) {
	$level = ob_get_level();
	try {
		return call_user_func( $callback, $atts );
	} catch ( \Throwable $e ) {
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
		if ( current_user_can( 'manage_options' ) ) {
			return '<div style="padding:16px;border:1px solid #DB141D;border-radius:10px;color:#DB141D;font-size:14px">'
				. '<strong>Shop layout error (visible to admins only):</strong> '
				. esc_html( $e->getMessage() ) . ' — ' . esc_html( basename( $e->getFile() ) ) . ' line ' . (int) $e->getLine()
				. '</div>';
		}
		return '';
	}
}
add_shortcode( 'fanvil_shop', function ( $atts ) {
	return fvs_safe_render( 'fvs_shop_shortcode_render', $atts );
} );
add_shortcode( 'fanvil_product', function ( $atts ) {
	return fvs_safe_render( 'fvp_product_shortcode_render', $atts );
} );
