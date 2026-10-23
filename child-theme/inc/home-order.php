<?php
/**
 * Home page sorting – a separate product order just for the home page sections.
 * Loaded by functions.php – do not load this file on its own.
 *
 * Products → Home page sorting (tabs: Products / Promotions), drag & drop.
 * - Until an order is saved, the home page follows WooCommerce's normal "Sorting".
 * - A saved order only changes the home page; shop, category and Promotions pages
 *   keep the normal Sorting. "Reset" goes back to the normal Sorting.
 * - Products added later (not in the saved order) come after the saved ones.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

function fvh_order_sections() {
	return array(
		'products'   => 'Products',
		'promotions' => 'Promotions',
	);
}

function fvh_saved_order( $section ) {
	$ids = get_option( 'fvh_home_order_' . $section, array() );
	return is_array( $ids ) ? array_map( 'absint', $ids ) : array();
}

/* Products for a home section, in the normal Sorting order (menu order, then name) */
function fvh_home_pool( $section, $extra = array() ) {
	$args = array_merge( array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'tax_query'      => array(
			array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ),
			fvs_promo_clause( 'promotions' === $section ? 'IN' : 'NOT IN' ),
		),
	), $extra );
	if ( isset( $extra['tax_query'] ) ) { // keep the base filters and add the extra ones
		$args['tax_query'] = array_merge( array(
			array( 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => array( 'exclude-from-catalog' ), 'operator' => 'NOT IN' ),
			fvs_promo_clause( 'promotions' === $section ? 'IN' : 'NOT IN' ),
		), $extra['tax_query'] );
	}
	return array_map( 'intval', get_posts( $args ) );
}

/* Apply the saved home order: saved products first (in that order), then the rest in Sorting order */
function fvh_apply_home_order( $ids, $section ) {
	$saved = fvh_saved_order( $section );
	if ( ! $saved ) return $ids;
	$pos   = array_flip( $saved );
	$first = array();
	$rest  = array();
	foreach ( $ids as $id ) {
		if ( isset( $pos[ $id ] ) ) $first[ $pos[ $id ] ] = $id;
		else $rest[] = $id;
	}
	ksort( $first );
	return array_merge( array_values( $first ), $rest );
}

/* The "Products" section of the home page: Featured products if any are marked (★),
   otherwise all products – both in the home page order */
function fvh_home_product_ids( $section, $limit, $category_query = null ) {
	$extra = $category_query ? array( 'tax_query' => array( $category_query ) ) : array();
	if ( 'products' === $section ) {
		$featured = wc_get_featured_product_ids();
		if ( $featured ) {
			$extra['post__in'] = $featured;
		}
	}
	$ids = fvh_apply_home_order( fvh_home_pool( $section, $extra ), $section );
	return $limit > 0 ? array_slice( $ids, 0, $limit ) : $ids;
}

/* Admin --------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=product', 'Home page sorting', 'Home page sorting', 'edit_products', 'fvh-home-sorting', 'fvh_home_sorting_page' );
}, 25 );

function fvh_home_sorting_url( $section = 'products' ) {
	return admin_url( 'edit.php?post_type=product&page=fvh-home-sorting&tab=' . $section );
}

/* "Home page sorting" next to "Sorting" on Products → All Products and Products → Promotions */
add_filter( 'views_edit-product', function ( $views ) {
	$section = function_exists( 'fvs_admin_is_promo_list' ) && fvs_admin_is_promo_list() ? 'promotions' : 'products';
	$views['fvh_home_sorting'] = '<a href="' . esc_url( fvh_home_sorting_url( $section ) ) . '">Home page sorting</a>';
	return $views;
}, 20 );

/* Save / reset */
add_action( 'admin_init', function () {
	if ( empty( $_POST['fvh_home_order_nonce'] ) ) return;
	if ( ! wp_verify_nonce( sanitize_key( $_POST['fvh_home_order_nonce'] ), 'fvh_home_order' ) || ! current_user_can( 'edit_products' ) ) {
		wp_die( 'Sorry, you are not allowed to do that.' );
	}
	$section = isset( $_POST['section'] ) ? sanitize_key( $_POST['section'] ) : '';
	if ( ! isset( fvh_order_sections()[ $section ] ) ) return;

	if ( ! empty( $_POST['fvh_reset'] ) ) {
		delete_option( 'fvh_home_order_' . $section );
		$msg = 'reset';
	} else {
		$pool = array_flip( fvh_home_pool( $section ) );
		$ids  = array_filter( array_map( 'absint', explode( ',', isset( $_POST['fvh_order'] ) ? sanitize_text_field( wp_unslash( $_POST['fvh_order'] ) ) : '' ) ), function ( $id ) use ( $pool ) {
			return isset( $pool[ $id ] );
		} );
		update_option( 'fvh_home_order_' . $section, array_values( array_unique( $ids ) ), false );
		$msg = 'saved';
	}
	wp_safe_redirect( add_query_arg( 'fvh_msg', $msg, fvh_home_sorting_url( $section ) ) );
	exit;
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'product_page_fvh-home-sorting' === $hook ) wp_enqueue_script( 'jquery-ui-sortable' );
} );

function fvh_home_sorting_page() {
	$sections = fvh_order_sections();
	$section  = isset( $_GET['tab'] ) && isset( $sections[ sanitize_key( $_GET['tab'] ) ] ) ? sanitize_key( $_GET['tab'] ) : 'products'; // phpcs:ignore WordPress.Security.NonceVerification
	$custom   = (bool) fvh_saved_order( $section );
	$featured = 'products' === $section && wc_get_featured_product_ids();
	$ids      = fvh_home_product_ids( $section, 0 );
	$msg      = isset( $_GET['fvh_msg'] ) ? sanitize_key( $_GET['fvh_msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	?>
	<div class="wrap fvh-sort">
		<h1>Home page sorting</h1>
		<?php if ( 'saved' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Home page order saved.</p></div><?php endif; ?>
		<?php if ( 'reset' === $msg ) : ?><div class="notice notice-success is-dismissible"><p>Home page order reset – it follows the normal Sorting again.</p></div><?php endif; ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $sections as $key => $label ) : ?>
				<a href="<?php echo esc_url( fvh_home_sorting_url( $key ) ); ?>" class="nav-tab<?php echo $key === $section ? ' nav-tab-active' : ''; ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>

		<p class="fvh-sort__intro">
			Drag the <?php echo 'promotions' === $section ? 'promotions' : 'products'; ?> into the order you want on the <strong>home page</strong>, then click <strong>Save order</strong>.
			This only changes the home page – the shop<?php echo 'promotions' === $section ? ' and Promotions page keep' : ' and category pages keep'; ?> the normal
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&orderby=menu_order+title&order=ASC' . ( 'promotions' === $section ? '&' . FVS_PROMO_TAX . '=' . FVS_PROMO_TERM : '' ) ) ); ?>">Sorting</a>.
		</p>
		<p class="fvh-sort__state">
			<?php if ( $custom ) : ?>
				<span class="fvh-badge fvh-badge--on">Custom home page order</span>
			<?php else : ?>
				<span class="fvh-badge">Same as Sorting</span> (no home page order saved yet)
			<?php endif; ?>
			<?php if ( $featured ) : ?>
				<br><em>Only Featured (★) products are listed – the home page shows Featured products while any are marked.</em>
			<?php endif; ?>
		</p>

		<?php if ( ! $ids ) : ?>
			<p>No <?php echo esc_html( strtolower( $sections[ $section ] ) ); ?> to sort yet.</p>
		<?php else : ?>
			<form method="post" class="fvh-sort__form">
				<?php wp_nonce_field( 'fvh_home_order', 'fvh_home_order_nonce' ); ?>
				<input type="hidden" name="section" value="<?php echo esc_attr( $section ); ?>">
				<input type="hidden" name="fvh_order" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">
				<ol class="fvh-list">
					<?php foreach ( $ids as $i => $id ) :
						$p = wc_get_product( $id );
						if ( ! $p ) continue; ?>
						<li class="fvh-item" data-id="<?php echo (int) $id; ?>">
							<span class="fvh-item__handle dashicons dashicons-menu" aria-hidden="true"></span>
							<span class="fvh-item__pos"><?php echo (int) $i + 1; ?></span>
							<span class="fvh-item__img"><?php echo $p->get_image( array( 44, 44 ) ); // phpcs:ignore ?></span>
							<span class="fvh-item__text"><strong><?php echo esc_html( $p->get_sku() ? $p->get_sku() : $p->get_name() ); ?></strong><?php if ( $p->get_sku() ) : ?><small><?php echo esc_html( $p->get_name() ); ?></small><?php endif; ?></span>
							<span class="fvh-item__move">
								<button type="button" class="button-link fvh-up" aria-label="Move up"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
								<button type="button" class="button-link fvh-down" aria-label="Move down"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
							</span>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="fvh-sort__actions">
					<button type="submit" class="button button-primary">Save order</button>
					<?php if ( $custom ) : ?>
						<button type="submit" name="fvh_reset" value="1" class="button" onclick="return confirm('Reset the home page order to the normal Sorting?');">Reset to normal Sorting</button>
					<?php endif; ?>
					<span class="description">New <?php echo 'promotions' === $section ? 'promotions' : 'products'; ?> are added at the end until you sort them here.</span>
				</p>
			</form>
		<?php endif; ?>
	</div>
	<style>
		.fvh-sort__intro{max-width:760px;font-size:14px}
		.fvh-badge{display:inline-block;padding:2px 10px;border-radius:999px;background:#f0f0f1;font-weight:600}
		.fvh-badge--on{background:#d1e7dd;color:#0f5132}
		.fvh-list{max-width:760px;margin:16px 0;padding:0;list-style:none}
		.fvh-item{display:flex;align-items:center;gap:12px;margin:0 0 6px;padding:8px 12px;background:#fff;border:1px solid #dcdcde;border-radius:6px;cursor:move}
		.fvh-item:hover{border-color:#2271b1}
		.fvh-item.ui-sortable-helper{box-shadow:0 6px 18px rgba(0,0,0,.15)}
		.fvh-placeholder{height:58px;margin:0 0 6px;border:2px dashed #2271b1;border-radius:6px;background:#f0f6fc}
		.fvh-item__handle{color:#8c8f94}
		.fvh-item__pos{width:26px;text-align:right;font-weight:600;color:#50575e}
		.fvh-item__img img{display:block;width:44px;height:44px;object-fit:contain;border-radius:4px;background:#f6f7f7}
		.fvh-item__text{display:flex;flex-direction:column;flex:1;min-width:0}
		.fvh-item__text small{color:#646970;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
		.fvh-item__move .button-link{color:#50575e;cursor:pointer}
		.fvh-sort__actions{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
	</style>
	<script>
	jQuery(function ($) {
		var list = $('.fvh-list'), input = $('input[name="fvh_order"]');
		function refresh() {
			var ids = [];
			list.children('.fvh-item').each(function (i) { $(this).find('.fvh-item__pos').text(i + 1); ids.push($(this).data('id')); });
			input.val(ids.join(','));
		}
		list.sortable({ handle: false, placeholder: 'fvh-placeholder', axis: 'y', update: refresh });
		list.on('click', '.fvh-up', function () { var li = $(this).closest('li'); li.prev().before(li); refresh(); });
		list.on('click', '.fvh-down', function () { var li = $(this).closest('li'); li.next().after(li); refresh(); });
	});
	</script>
	<?php
}
