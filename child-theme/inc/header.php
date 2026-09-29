<?php
/**
 * Site header – [fanvil_site_header].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Fanvil Shop – site header  [fanvil_site_header]   (v3)
 *
 * Desktop: logo · product search · Main Menu · cart dropdown · account
 * Mobile:  logo · cart · account · menu button → slide-in panel (search, menu, links)
 *
 * The menu panel and cart dropdown open with plain HTML/CSS (no JavaScript needed),
 * so caching/"delay JS" plugins can't break them. JavaScript only adds extras:
 * Esc to close, click-outside, focus handling, remove-from-cart without reload.
 *
 * Options: menu="Main Menu" (default)  logo="image URL"  sticky="yes|no"
 */

define( 'FSH_LOGO', 'https://soharon.co.uk/off-27/wp-content/uploads/2026/09/Group-134.png' );

/* ---------------------------------------------------------------------------
 * Shortcode
 * ------------------------------------------------------------------------ */
function fsh_render_header( $atts ) {
	$atts = shortcode_atts( array(
		'menu'   => 'Main Menu',
		'logo'   => FSH_LOGO,
		'sticky' => 'yes',
	), $atts, 'fanvil_site_header' );

	$GLOBALS['fsh_used'] = array( 'atts' => $atts );

	$count    = fsh_cart_count();
	$acct_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();

	ob_start();
	fsh_print_css( 'yes' === $atts['sticky'] );
	?>
<!-- fanvil-site-header v3 -->
<div class="fsh fsh-header" data-fsh>
	<div class="fsh-bar">
		<?php echo fsh_logo( $atts['logo'] ); // phpcs:ignore ?>

		<div class="fsh-search-wrap"><?php echo fsh_search_form( 'fsh-search-desktop' ); // phpcs:ignore ?></div>

		<nav class="fsh-nav" aria-label="Main menu"><?php echo fsh_menu( $atts['menu'], 'fsh-menu', true ); // phpcs:ignore ?></nav>

		<div class="fsh-actions">
			<div class="fsh-cart">
				<input type="checkbox" id="fsh-cart-toggle" class="fsh-toggle" tabindex="-1" aria-hidden="true">
				<label for="fsh-cart-toggle" class="fsh-icon fsh-cart-btn" role="button" tabindex="0" aria-controls="fsh-minicart" aria-expanded="false" aria-label="Cart">
					<?php echo fsh_icon( 'cart' ); // phpcs:ignore ?>
					<?php echo fsh_count_html( $count ); // phpcs:ignore ?>
				</label>
				<div class="fsh-minicart" id="fsh-minicart" role="dialog" aria-label="Your cart" data-remove-endpoint="<?php echo esc_url( class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'remove_from_cart' ) : '' ); ?>">
					<?php echo fsh_minicart_body(); // phpcs:ignore ?>
				</div>
			</div>
			<a class="fsh-icon fsh-account" href="<?php echo esc_url( $acct_url ); ?>" aria-label="My account"><?php echo fsh_icon( 'user' ); // phpcs:ignore ?></a>
			<label for="fsh-menu-toggle" class="fsh-burger" role="button" tabindex="0" aria-controls="fsh-drawer" aria-expanded="false" aria-label="Open menu">
				<span></span><span></span><span></span>
			</label>
		</div>
	</div>
</div>
	<?php
	return ob_get_clean();
}

add_shortcode( 'fanvil_site_header', 'fsh_safe_render' );
add_shortcode( 'fanvil_header', 'fsh_safe_render' ); // old name keeps working, now shows the new header

function fsh_safe_render( $atts ) {
	$level = ob_get_level();
	try {
		return fsh_render_header( $atts );
	} catch ( \Throwable $e ) {
		while ( ob_get_level() > $level ) {
			ob_end_clean();
		}
		return current_user_can( 'manage_options' )
			? '<div style="padding:12px;border:1px solid #DB141D;color:#DB141D;font-size:14px">Header error (admins only): ' . esc_html( $e->getMessage() ) . ' — line ' . (int) $e->getLine() . '</div>'
			: '';
	}
}

/* ---------------------------------------------------------------------------
 * Mobile menu panel – printed at the end of <body>, outside any Elementor
 * container, so nothing can clip or hide it.
 * ------------------------------------------------------------------------ */
add_action( 'wp_footer', function () {
	if ( empty( $GLOBALS['fsh_used'] ) ) {
		return;
	}
	$atts     = $GLOBALS['fsh_used']['atts'];
	$count    = fsh_cart_count();
	$cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
	$acct_url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url();
	?>
<input type="checkbox" id="fsh-menu-toggle" class="fsh-toggle fsh-menu-toggle" tabindex="-1" aria-hidden="true">
<div class="fsh fsh-drawer" id="fsh-drawer">
	<label for="fsh-menu-toggle" class="fsh-drawer__overlay" aria-hidden="true"></label>
	<div class="fsh-drawer__panel" role="dialog" aria-modal="true" aria-label="Menu">
		<div class="fsh-drawer__top">
			<?php echo fsh_logo( $atts['logo'] ); // phpcs:ignore ?>
			<div class="fsh-drawer__btns">
				<a class="fsh-round fsh-drawer__home<?php echo is_front_page() ? ' is-current' : ''; ?>" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Home" title="Home"><?php echo fsh_icon( 'home' ); // phpcs:ignore ?></a>
				<label for="fsh-menu-toggle" class="fsh-round fsh-drawer__close" role="button" tabindex="0" aria-label="Close menu"><?php echo fsh_icon( 'close' ); // phpcs:ignore ?></label>
			</div>
		</div>
		<?php echo fsh_search_form( 'fsh-search-mobile' ); // phpcs:ignore ?>
		<nav aria-label="Mobile menu"><?php echo fsh_menu( $atts['menu'], 'fsh-drawer__menu' ); // phpcs:ignore ?></nav>
		<div class="fsh-drawer__links">
			<a href="<?php echo esc_url( $cart_url ); ?>"><?php echo fsh_icon( 'cart' ); // phpcs:ignore ?><span>Cart</span><?php echo fsh_count_html( $count, 'fsh-count--inline' ); // phpcs:ignore ?></a>
			<a href="<?php echo esc_url( $acct_url ); ?>"><?php echo fsh_icon( 'user' ); // phpcs:ignore ?><span>My account</span></a>
		</div>
	</div>
</div>
<script id="fsh-js">
<?php echo fsh_js(); // phpcs:ignore ?>
</script>
	<?php
}, 99 );

/* ---------------------------------------------------------------------------
 * Pieces
 * ------------------------------------------------------------------------ */
function fsh_logo( $url ) {
	$site = get_bloginfo( 'name' );
	if ( ! $url && has_custom_logo() ) {
		$url = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}
	$inner = $url
		? '<img class="fsh-logo__img" src="' . esc_url( $url ) . '" alt="' . esc_attr( $site ) . '" width="140" height="46">'
		: '<span class="fsh-logo__text">' . esc_html( $site ) . '</span>';
	return '<a class="fsh-logo" href="' . esc_url( home_url( '/' ) ) . '" aria-label="' . esc_attr( $site ) . ' – home">' . $inner . '</a>';
}

function fsh_menu( $menu, $class, $home = false ) {
	if ( $menu && ! wp_get_nav_menu_object( $menu ) ) {
		$menu = '';
	}
	if ( ! $menu ) {
		$menus = wp_get_nav_menus();
		$menu  = $menus ? $menus[0]->term_id : '';
	}
	if ( ! $menu ) {
		return '';
	}
	return (string) wp_nav_menu( array(
		'menu'        => $menu,
		'container'   => false,
		'menu_class'  => $class,
		'menu_id'     => '',
		'depth'       => 1,
		'fallback_cb' => false,
		'echo'        => false,
		'items_wrap'  => '<ul class="%2$s">' . ( $home ? str_replace( '%', '%%', fsh_home_item() ) : '' ) . '%3$s</ul>',
	) );
}

/* Home icon (no visible text; screen readers hear "Home") */
function fsh_home_item() {
	$current = is_front_page() ? ' current-menu-item' : '';
	return '<li class="menu-item fsh-home' . $current . '"><a href="' . esc_url( home_url( '/' ) ) . '" aria-label="Home" title="Home"' . ( $current ? ' aria-current="page"' : '' ) . '>' . fsh_icon( 'home' ) . '</a></li>';
}

function fsh_search_form( $id ) {
	return '<form class="fsh-search" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '">'
		. '<label class="fsh-sr" for="' . esc_attr( $id ) . '">Search products</label>'
		. '<input id="' . esc_attr( $id ) . '" class="fsh-search__input" type="search" name="s" value="' . esc_attr( get_search_query() ) . '" placeholder="Search products" autocomplete="off">'
		. '<input type="hidden" name="post_type" value="product">'
		. '<button type="submit" class="fsh-search__btn" aria-label="Search">' . fsh_icon( 'search' ) . '</button>'
		. '</form>';
}

function fsh_icon( $name ) {
	$icons = array(
		'cart'   => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><path d="M3 4h2.2l2.1 10.2a2 2 0 0 0 2 1.6h7.9a2 2 0 0 0 2-1.5L21 8H6.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="10" cy="20" r="1.4" fill="currentColor"/><circle cx="17" cy="20" r="1.4" fill="currentColor"/></svg>',
		'user'   => '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true"><circle cx="12" cy="12" r="9.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="3.2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M6.2 18.4a6.5 6.5 0 0 1 11.6 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		'search' => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M20 20l-3.5-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>',
		'home'   => '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M3.5 10.5L12 3.5l8.5 7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/><path d="M5.5 9v10.5h4.5v-6h4v6h4.5V9" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		'close'  => '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

function fsh_cart_count() {
	return ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
}

function fsh_count_html( $count, $extra = '' ) {
	return '<span class="fsh-count ' . esc_attr( $extra ) . ( $count ? '' : ' is-empty' ) . '">' . (int) $count . '</span>';
}

/* Cart dropdown contents (own markup – Elementor/theme cart templates can't change it) */
function fsh_minicart_body() {
	$cart  = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart : null;
	$count = $cart ? $cart->get_cart_contents_count() : 0;

	ob_start();
	echo '<div class="fsh-mc">';
	echo '<div class="fsh-mc__head"><p class="fsh-mc__title">Your cart';
	if ( $count ) {
		echo '<span class="fsh-mc__badge">' . esc_html( sprintf( _n( '%d item', '%d items', $count, 'woocommerce' ), $count ) ) . '</span>';
	}
	echo '</p><label for="fsh-cart-toggle" class="fsh-round fsh-mc__close" role="button" tabindex="0" aria-label="Close cart">' . fsh_icon( 'close' ) . '</label></div>';

	if ( ! $cart || $cart->is_empty() ) {
		echo '<div class="fsh-mc__empty">' . fsh_icon( 'cart' ) . '<p>Your cart is empty.</p>';
		echo '<a class="fsh-btn fsh-btn--solid" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Browse products</a></div>';
	} else {
		echo '<ul class="fsh-mc__list">';
		foreach ( $cart->get_cart() as $key => $item ) {
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product || ! $product->exists() || $item['quantity'] <= 0 ) {
				continue;
			}
			$link  = $product->is_visible() ? $product->get_permalink( $item ) : '';
			$name  = $product->get_name();
			$sku   = $product->get_sku();
			$thumb = $product->get_image( 'woocommerce_gallery_thumbnail', array( 'class' => 'fsh-mc__img', 'alt' => '' ) );

			echo '<li class="fsh-mc__item">';
			echo '<span class="fsh-mc__media">' . $thumb . '</span>'; // phpcs:ignore
			echo '<div class="fsh-mc__info">';
			echo $link
				? '<a class="fsh-mc__name" href="' . esc_url( $link ) . '">' . esc_html( $sku ? $sku : $name ) . '</a>'
				: '<span class="fsh-mc__name">' . esc_html( $sku ? $sku : $name ) . '</span>';
			if ( $sku ) {
				echo '<span class="fsh-mc__sub">' . esc_html( $name ) . '</span>';
			}
			echo '<span class="fsh-mc__qty">' . (int) $item['quantity'] . ' &times; ' . wp_kses_post( $cart->get_product_price( $product ) ) . '</span>';
			echo '</div>';
			echo '<div class="fsh-mc__side">';
			printf(
				'<a class="fsh-mc__remove" href="%s" data-cart-key="%s" aria-label="%s">%s</a>',
				esc_url( wc_get_cart_remove_url( $key ) ),
				esc_attr( $key ),
				esc_attr( sprintf( 'Remove %s from cart', $name ) ),
				fsh_icon( 'close' )
			);
			echo '<span class="fsh-mc__line">' . wp_kses_post( $cart->get_product_subtotal( $product, $item['quantity'] ) ) . '</span>';
			echo '</div></li>';
		}
		echo '</ul>';
		echo '<div class="fsh-mc__foot">';
		echo '<div class="fsh-mc__total"><span>Subtotal</span><strong>' . wp_kses_post( $cart->get_cart_subtotal() ) . '</strong></div>';
		echo '<p class="fsh-mc__note">Shipping and taxes are calculated at checkout.</p>';
		echo '<div class="fsh-mc__actions">';
		echo '<a class="fsh-btn" href="' . esc_url( wc_get_cart_url() ) . '">View cart</a>';
		echo '<a class="fsh-btn fsh-btn--solid" href="' . esc_url( wc_get_checkout_url() ) . '">Checkout</a>';
		echo '</div></div>';
	}
	echo '</div>';
	return ob_get_clean();
}

/* Live cart updates (after add to cart / remove) */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
	$count                                  = fsh_cart_count();
	$fragments['.fsh-cart-btn .fsh-count']  = fsh_count_html( $count );
	$fragments['.fsh-drawer__links .fsh-count'] = fsh_count_html( $count, 'fsh-count--inline' );
	$fragments['.fsh-minicart .fsh-mc']     = fsh_minicart_body();
	return $fragments;
} );

/* Header search shows products only */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() && $q->is_main_query() && $q->is_search() && isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] ) { // phpcs:ignore
		$q->set( 'post_type', 'product' );
	}
} );

/* ---------------------------------------------------------------------------
 * CSS
 * ------------------------------------------------------------------------ */
function fsh_print_css( $sticky ) {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	?>
<style id="fsh-css">
.fsh{--r:#DB141D;--rd:#B30F17;--rs:#FDECEC;--ink:#1A1D21;--mut:#6B7280;--line:#E6E8EB;--soft:#F4F5F7;font-family:'Poppins',sans-serif;color:var(--ink);line-height:1.4}
.fsh *,.fsh *::before,.fsh *::after{box-sizing:border-box}
.fsh ul{list-style:none;margin:0;padding:0}
.fsh li{margin:0}
.fsh a{text-decoration:none}
.fsh svg{display:block;flex:0 0 auto}
.fsh .fsh-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
.fsh-toggle{display:none !important}
.fsh :focus-visible{outline:2px solid var(--r);outline-offset:2px}
<?php if ( $sticky ) : ?>
body > .elementor-location-header,body > #site-header{position:sticky;top:var(--wp-admin--admin-bar--height,0px);z-index:999}
@media (max-width:600px){body > .elementor-location-header,body > #site-header{top:0}}
<?php endif; ?>

/* Bar */
.fsh-header{position:relative;z-index:10;background:#fff;transition:box-shadow .25s}
.fsh-header.is-scrolled{box-shadow:0 6px 24px rgba(26,29,33,.08)}
.fsh-bar{display:flex;align-items:center;gap:28px;width:100%;max-width:1350px;margin:0 auto;padding:14px 24px}

/* Logo – locked size so theme/Elementor image rules can't enlarge it */
.fsh a.fsh-logo{display:flex;align-items:center;flex:0 0 auto;line-height:0}
.fsh .fsh-logo img.fsh-logo__img{display:block;width:auto !important;height:46px !important;max-width:none !important;max-height:none !important;margin:0 !important;object-fit:contain}
.fsh-logo__text{font-size:22px;font-weight:700;color:var(--r)}

/* Search */
.fsh-search-wrap{flex:1 1 280px;min-width:220px;max-width:340px}
.fsh .fsh-search{display:flex;align-items:center;gap:8px;width:100%;margin:0}
.fsh .fsh-search__input,.fsh .fsh-search__input:focus{flex:1 1 auto;width:auto !important;min-width:0;height:44px !important;margin:0 !important;padding:0 18px !important;border:1px solid var(--line) !important;border-radius:999px !important;background:#fff !important;box-shadow:none !important;outline:none;font-family:inherit;font-size:14px;color:var(--ink) !important;transition:border-color .2s}
.fsh .fsh-search__input:focus{border-color:var(--r) !important}
.fsh .fsh-search__input::placeholder{color:#9AA0A8}
.fsh .fsh-search__btn,.fsh .fsh-search__btn:hover,.fsh .fsh-search__btn:focus{flex:0 0 44px;display:flex;align-items:center;justify-content:center;width:44px !important;height:44px !important;min-height:0;margin:0 !important;padding:0 !important;border:0 !important;border-radius:50% !important;background:var(--r) !important;color:#fff !important;box-shadow:none !important;cursor:pointer;transition:background .2s}
.fsh .fsh-search__btn:hover{background:var(--rd) !important}

/* Desktop menu */
.fsh-nav{margin-left:auto}
.fsh .fsh-menu{display:flex;align-items:center;gap:4px}
.fsh .fsh-menu a{display:inline-flex;align-items:center;height:42px;padding:0 18px;border-radius:999px;font-size:14px;font-weight:500;letter-spacing:.02em;text-transform:uppercase;color:var(--ink);transition:background .2s,color .2s}
.fsh .fsh-menu a:hover,.fsh .fsh-menu a:focus-visible,
.fsh .fsh-menu .current-menu-item > a,.fsh .fsh-menu .current_page_parent > a,.fsh .fsh-menu .current-menu-ancestor > a{background:var(--r);color:#fff}
.fsh .fsh-menu .fsh-home > a{justify-content:center;width:42px;padding:0}
.fsh .fsh-menu .fsh-home svg{width:20px;height:20px}

/* Icons */
.fsh-actions{display:flex;align-items:center;gap:8px}
.fsh .fsh-icon{position:relative;display:flex;align-items:center;justify-content:center;width:44px;height:44px;margin:0;border-radius:50%;color:var(--r);cursor:pointer;transition:background .2s}
.fsh .fsh-icon:hover{background:var(--rs)}
.fsh .fsh-icon svg{width:26px;height:26px}
.fsh-count{position:absolute;top:3px;right:1px;min-width:19px;height:19px;padding:0 5px;border:2px solid #fff;border-radius:999px;background:var(--r);color:#fff;font-size:10px;font-weight:600;line-height:15px;text-align:center}
.fsh-count.is-empty{display:none}
.fsh-cart{position:relative}
#fsh-cart-toggle:checked ~ .fsh-cart-btn{background:var(--rs)}

/* Cart dropdown */
.fsh-minicart{position:absolute;top:calc(100% + 12px);right:-12px;z-index:1000;width:380px;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 24px 60px rgba(26,29,33,.18);opacity:0;visibility:hidden;transform:translateY(8px);transition:opacity .2s,transform .2s,visibility .2s;text-align:left}
.fsh-minicart::before{content:"";position:absolute;top:-8px;right:27px;width:14px;height:14px;background:#fff;border-left:1px solid var(--line);border-top:1px solid var(--line);transform:rotate(45deg)}
#fsh-cart-toggle:checked ~ .fsh-minicart{opacity:1;visibility:visible;transform:none}
.fsh-mc{position:relative;padding:18px 20px 20px}
.fsh-mc__head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid var(--line)}
.fsh .fsh-mc__title{display:flex;align-items:center;gap:10px;margin:0;font-size:16px;font-weight:600;color:var(--ink)}
.fsh-mc__badge{padding:2px 10px;border-radius:999px;background:var(--rs);color:var(--r);font-size:12px;font-weight:600}
.fsh .fsh-round{display:flex;align-items:center;justify-content:center;width:36px;height:36px;margin:0;border:1px solid var(--line);border-radius:50%;background:#fff;color:var(--mut);cursor:pointer;transition:background .2s,color .2s,border-color .2s}
.fsh .fsh-round:hover{border-color:var(--r);background:var(--rs);color:var(--r)}
.fsh .fsh-mc__list{max-height:320px;overflow-y:auto;margin:0 -6px;padding:0 6px;scrollbar-width:thin}
.fsh-mc__item{display:grid;grid-template-columns:64px minmax(0,1fr) auto;gap:14px;align-items:start;padding:14px 0;border-bottom:1px solid var(--line);transition:opacity .2s}
.fsh-mc__item.is-removing{opacity:.4;pointer-events:none}
.fsh-mc__media{display:block;width:64px;height:64px;padding:6px;border-radius:12px;background:var(--soft)}
.fsh .fsh-mc__media img{display:block;width:100% !important;height:100% !important;max-width:none !important;margin:0 !important;object-fit:contain}
.fsh-mc__info{display:flex;flex-direction:column;gap:2px;min-width:0}
.fsh .fsh-mc__name{font-size:14px;font-weight:600;color:var(--ink)}
.fsh a.fsh-mc__name:hover{color:var(--r)}
.fsh-mc__sub{font-size:12px;line-height:1.4;color:var(--mut);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.fsh-mc__qty{margin-top:4px;font-size:13px;color:var(--mut)}
.fsh-mc__side{display:flex;flex-direction:column;align-items:flex-end;justify-content:space-between;align-self:stretch;gap:10px}
.fsh .fsh-mc__remove{display:flex;align-items:center;justify-content:center;width:26px;height:26px;border:1px solid var(--line);border-radius:50%;color:var(--mut);transition:background .2s,color .2s,border-color .2s}
.fsh .fsh-mc__remove svg{width:13px;height:13px}
.fsh .fsh-mc__remove:hover{border-color:var(--r);background:var(--r);color:#fff}
.fsh-mc__line{font-size:14px;font-weight:600;color:var(--ink);white-space:nowrap}
.fsh-mc__foot{padding-top:16px}
.fsh-mc__total{display:flex;align-items:baseline;justify-content:space-between;font-size:15px;color:var(--ink)}
.fsh-mc__total strong{font-size:19px;font-weight:600;color:var(--r)}
.fsh .fsh-mc__note{margin:4px 0 16px;font-size:12px;color:var(--mut)}
.fsh-mc__actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.fsh .fsh-btn{display:inline-flex;align-items:center;justify-content:center;height:46px;padding:0 16px;border:1px solid var(--r);border-radius:999px;background:#fff;color:var(--r);font-size:14px;font-weight:600;transition:background .2s,color .2s,border-color .2s}
.fsh .fsh-btn:hover{background:var(--rs);color:var(--r)}
.fsh .fsh-btn--solid{background:var(--r);color:#fff}
.fsh .fsh-btn--solid:hover{background:var(--rd);border-color:var(--rd);color:#fff}
.fsh-mc__empty{display:flex;flex-direction:column;align-items:center;gap:10px;padding:26px 0 6px;text-align:center;color:var(--r)}
.fsh-mc__empty svg{width:40px;height:40px}
.fsh .fsh-mc__empty p{margin:0 0 6px;font-size:14px;color:var(--mut)}
.fsh-mc__empty .fsh-btn{min-width:180px}

/* Burger */
.fsh .fsh-burger{display:none;flex-direction:column;justify-content:center;gap:5px;width:44px;height:44px;margin:0;padding:0 11px;border-radius:50%;cursor:pointer;transition:background .2s}
.fsh .fsh-burger:hover{background:var(--rs)}
.fsh-burger span{display:block;height:2px;border-radius:2px;background:var(--ink)}

/* Mobile panel (outside the header, end of page) */
.fsh-drawer{position:fixed;inset:0;z-index:100002;visibility:hidden;transition:visibility 0s linear .3s}
.fsh-menu-toggle:checked + .fsh-drawer{visibility:visible;transition:visibility 0s}
.fsh-drawer__overlay{position:absolute;inset:0;margin:0;background:rgba(15,17,20,.5);opacity:0;transition:opacity .3s;cursor:pointer}
.fsh-menu-toggle:checked + .fsh-drawer .fsh-drawer__overlay{opacity:1}
.fsh-drawer__panel{position:absolute;top:0;right:0;bottom:0;display:flex;flex-direction:column;gap:20px;width:min(360px,88vw);padding:18px 20px 28px;background:#fff;overflow-y:auto;transform:translateX(100%);transition:transform .3s cubic-bezier(.22,.61,.36,1)}
.fsh-menu-toggle:checked + .fsh-drawer .fsh-drawer__panel{transform:none}
.fsh-drawer__top{display:flex;align-items:center;justify-content:space-between}
.fsh-drawer .fsh-logo img.fsh-logo__img{height:38px !important}
.fsh .fsh-drawer__close{width:42px;height:42px;color:var(--ink)}
.fsh-drawer__btns{display:flex;align-items:center;gap:8px}
.fsh a.fsh-drawer__home{width:42px;height:42px;color:var(--ink)}
.fsh a.fsh-drawer__home svg{width:20px;height:20px}
.fsh a.fsh-drawer__home.is-current{border-color:var(--r);background:var(--r);color:#fff}
.fsh .fsh-drawer__menu li{border-bottom:1px solid var(--line)}
.fsh .fsh-drawer__menu a{display:flex;align-items:center;min-height:52px;font-size:15px;font-weight:500;letter-spacing:.02em;text-transform:uppercase;color:var(--ink)}
.fsh .fsh-drawer__menu a:hover,
.fsh .fsh-drawer__menu .current-menu-item > a,.fsh .fsh-drawer__menu .current_page_parent > a,.fsh .fsh-drawer__menu .current-menu-ancestor > a{color:var(--r);font-weight:600}
.fsh-drawer__links{display:flex;flex-direction:column;gap:2px;margin-top:auto;padding-top:12px;border-top:1px solid var(--line)}
.fsh .fsh-drawer__links a{display:flex;align-items:center;gap:12px;min-height:48px;font-size:15px;font-weight:500;color:var(--ink)}
.fsh .fsh-drawer__links a:hover{color:var(--r)}
.fsh-drawer__links svg{width:22px;height:22px;color:var(--r)}
.fsh-drawer__links .fsh-count{position:static;margin-left:auto;border:0}
html:has(.fsh-menu-toggle:checked),html:has(.fsh-menu-toggle:checked) body{overflow:hidden}

@media (max-width:1024px){
 .fsh-bar{gap:10px;padding:10px 16px}
 .fsh-search-wrap,.fsh-nav{display:none}
 .fsh-actions{margin-left:auto;gap:2px}
 .fsh .fsh-burger{display:flex}
 .fsh .fsh-logo img.fsh-logo__img{height:38px !important}
}
@media (max-width:600px){
 .fsh-minicart{position:fixed;top:68px;left:12px;right:12px;width:auto}
 .fsh-minicart::before{display:none}
}
@media (min-width:1025px){.fsh-drawer{display:none}}
@media (prefers-reduced-motion:reduce){.fsh-minicart,.fsh-drawer,.fsh-drawer__panel,.fsh-drawer__overlay{transition:none}}
</style>
	<?php
}

/* ---------------------------------------------------------------------------
 * JavaScript (extras only – the header works without it)
 * ------------------------------------------------------------------------ */
function fsh_js() {
	return <<<'FSHJS'
(function () {
	if (window.fshLoaded) return;
	window.fshLoaded = true;
	var menuT = document.getElementById('fsh-menu-toggle');
	var cartT = document.getElementById('fsh-cart-toggle');
	var header = document.querySelector('[data-fsh]');

	function sync() {
		var m = menuT && menuT.checked, c = cartT && cartT.checked;
		document.querySelectorAll('.fsh-burger').forEach(function (b) { b.setAttribute('aria-expanded', m ? 'true' : 'false'); });
		document.querySelectorAll('.fsh-cart-btn').forEach(function (b) { b.setAttribute('aria-expanded', c ? 'true' : 'false'); });
	}
	function setMenu(open) {
		if (!menuT) return;
		menuT.checked = open;
		if (open && cartT) cartT.checked = false;
		sync();
		if (open) {
			var c = document.querySelector('.fsh-drawer__close');
			if (c) setTimeout(function () { c.focus(); }, 60);
		} else {
			var b = document.querySelector('.fsh-burger');
			if (b && document.activeElement && document.activeElement.closest('.fsh-drawer')) b.focus();
		}
	}
	function setCart(open) { if (cartT) { cartT.checked = open; sync(); } }

	if (menuT) menuT.addEventListener('change', function () { setMenu(menuT.checked); });
	if (cartT) cartT.addEventListener('change', sync);

	/* Keyboard: labels act as buttons */
	document.addEventListener('keydown', function (e) {
		var lbl = e.target.closest && e.target.closest('label[role="button"]');
		if (lbl && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); lbl.click(); return; }
		if (e.key === 'Escape') {
			if (menuT && menuT.checked) setMenu(false);
			if (cartT && cartT.checked) { setCart(false); var cb = document.querySelector('.fsh-cart-btn'); if (cb) cb.focus(); }
		}
		if (e.key === 'Tab' && menuT && menuT.checked) {
			var panel = document.querySelector('.fsh-drawer__panel');
			var f = [].slice.call(panel.querySelectorAll('a[href], [tabindex="0"], input:not([type=hidden]), button')).filter(function (x) { return x.offsetParent !== null; });
			if (!f.length) return;
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		}
	});

	/* Click outside the cart closes it; remove items without reloading */
	document.addEventListener('click', function (e) {
		var rm = e.target.closest('.fsh-mc__remove');
		if (rm && window.fetch) {
			e.preventDefault();
			var box = document.getElementById('fsh-minicart');
			var item = rm.closest('.fsh-mc__item');
			if (item) item.classList.add('is-removing');
			var body = new URLSearchParams();
			body.append('cart_item_key', rm.dataset.cartKey);
			fetch(box.dataset.removeEndpoint, { method: 'POST', body: body, credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res || !res.fragments) throw new Error();
					Object.keys(res.fragments).forEach(function (sel) {
						document.querySelectorAll(sel).forEach(function (n) { n.outerHTML = res.fragments[sel]; });
					});
					if (window.jQuery) window.jQuery(document.body).trigger('removed_from_cart', [res.fragments, res.cart_hash]);
				})
				.catch(function () { window.location = rm.href; });
			return;
		}
		if (cartT && cartT.checked && !e.target.closest('.fsh-cart')) setCart(false);
	});

	window.addEventListener('resize', function () { if (window.innerWidth > 1024 && menuT && menuT.checked) setMenu(false); });

	/* Shadow once the page scrolls */
	if (header) {
		var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 4); };
		window.addEventListener('scroll', onScroll, { passive: true });
		onScroll();
	}
	sync();
})();
FSHJS;
}


/*--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------
	--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------*/
