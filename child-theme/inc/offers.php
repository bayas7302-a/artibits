<?php
/**
 * Personal promotional offers (WooCommerce coupons allocated to a customer).
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ===========================================================================
 * PERSONAL PROMOTIONAL OFFERS
 * Allocate a code to a customer: Marketing → Coupons → edit coupon →
 * "Usage restriction" → "Allowed emails" (customer's email; *@company.com works too).
 * - My Account → "My offers": every allocated code with a Copy button + details
 * - Cart + checkout: "View my offers" bar → popup with "Apply now" buttons
 *   (signed out: "Log in to see your promotional offers", returns to the page)
 * Only active offers are listed: not expired, uses left for this customer.
 * ======================================================================== */
define( 'SOHARON_OFFERS_ENDPOINT', 'my-offers' );

/* Emails that identify this customer (account email + billing email) */
function soharon_offer_user_emails( $user_id ) {
	$user   = get_userdata( $user_id );
	$emails = array();
	if ( $user ) {
		$emails[] = $user->user_email;
		$emails[] = get_user_meta( $user_id, 'billing_email', true );
	}
	return array_values( array_unique( array_filter( array_map( 'strtolower', array_map( 'trim', $emails ) ) ) ) );
}

/* Does one of the customer's emails match the coupon's "Allowed emails" (wildcards allowed)? */
function soharon_offer_email_match( $restrictions, $emails ) {
	foreach ( $restrictions as $allowed ) {
		$allowed = strtolower( trim( $allowed ) );
		if ( '' === $allowed ) continue;
		$regex = '/^' . str_replace( '\*', '.*', preg_quote( $allowed, '/' ) ) . '$/';
		foreach ( $emails as $email ) {
			if ( $allowed === $email || preg_match( $regex, $email ) ) return true;
		}
	}
	return false;
}

/* Active offers allocated to a customer: array of WC_Coupon */
function soharon_offers_for_user( $user_id = 0 ) {
	static $cache = array();
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id || ! class_exists( 'WC_Coupon' ) ) return array();
	if ( isset( $cache[ $user_id ] ) ) return $cache[ $user_id ];

	$emails = soharon_offer_user_emails( $user_id );
	$ids    = get_posts( array(
		'post_type'      => 'shop_coupon',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array( // only coupons with "Allowed emails" filled in
			array( 'key' => 'customer_email', 'value' => 'a:0:{}', 'compare' => '!=' ),
			array( 'key' => 'customer_email', 'value' => '', 'compare' => '!=' ),
		),
	) );

	$offers = array();
	foreach ( $ids as $id ) {
		$coupon = new WC_Coupon( $id );
		if ( ! soharon_offer_email_match( $coupon->get_email_restrictions(), $emails ) ) continue;

		$expires = $coupon->get_date_expires();
		if ( $expires && $expires->getTimestamp() < time() ) continue;                             // expired
		if ( $coupon->get_usage_limit() && $coupon->get_usage_count() >= $coupon->get_usage_limit() ) continue; // used up
		if ( soharon_offer_uses_left( $coupon, $user_id, $emails ) === 0 ) continue;                     // used up by this customer

		$offers[] = $coupon;
	}

	// Soonest expiry first, offers without an end date last
	usort( $offers, function ( $a, $b ) {
		$ea = $a->get_date_expires() ? $a->get_date_expires()->getTimestamp() : PHP_INT_MAX;
		$eb = $b->get_date_expires() ? $b->get_date_expires()->getTimestamp() : PHP_INT_MAX;
		return $ea <=> $eb;
	} );
	return $cache[ $user_id ] = $offers;
}

/* Uses left for this customer (null = no per-customer limit) */
function soharon_offer_uses_left( $coupon, $user_id, $emails = array() ) {
	$limit = (int) $coupon->get_usage_limit_per_user();
	if ( ! $limit ) return null;
	$used = 0;
	foreach ( (array) $coupon->get_used_by() as $who ) {
		if ( (string) $who === (string) $user_id || in_array( strtolower( (string) $who ), $emails, true ) ) $used++;
	}
	return max( 0, $limit - $used );
}

/* "10% off", "AED 50 off your order", "Free shipping" … */
function soharon_offer_headline( $coupon ) {
	$amount = (float) $coupon->get_amount();
	$type   = $coupon->get_discount_type();
	$text   = '';
	if ( $amount > 0 ) {
		if ( 'percent' === $type ) {
			$text = wc_format_localized_decimal( $amount + 0 ) . '% off';
		} elseif ( 'fixed_product' === $type ) {
			$text = wp_strip_all_tags( wc_price( $amount ) ) . ' off each item';
		} else {
			$text = wp_strip_all_tags( wc_price( $amount ) ) . ' off your order';
		}
	}
	if ( $coupon->get_free_shipping() ) {
		$text = $text ? $text . ' + free shipping' : 'Free shipping';
	}
	return $text ? $text : 'Special offer';
}

/* Conditions shown under the offer */
function soharon_offer_conditions( $coupon, $user_id ) {
	$c = array();
	if ( (float) $coupon->get_minimum_amount() > 0 ) $c[] = 'Minimum spend ' . wp_strip_all_tags( wc_price( $coupon->get_minimum_amount() ) );
	if ( (float) $coupon->get_maximum_amount() > 0 ) $c[] = 'Maximum spend ' . wp_strip_all_tags( wc_price( $coupon->get_maximum_amount() ) );
	if ( $coupon->get_product_ids() || $coupon->get_product_categories() ) $c[] = 'Valid on selected products';
	if ( $coupon->get_exclude_sale_items() ) $c[] = 'Not valid on sale items';
	if ( $coupon->get_individual_use() ) $c[] = 'Can’t be combined with other codes';
	$left = soharon_offer_uses_left( $coupon, $user_id, soharon_offer_user_emails( $user_id ) );
	if ( null !== $left ) $c[] = 1 === $left ? '1 use left' : $left . ' uses left';
	return $c;
}

function soharon_offer_expiry( $coupon ) {
	$e = $coupon->get_date_expires();
	return $e ? 'Valid until ' . wc_format_datetime( $e ) : 'No expiry date';
}

function soharon_offer_applied( $code ) {
	return function_exists( 'WC' ) && WC()->cart && WC()->cart->has_discount( $code );
}

/* One offer card. $mode 'account' = copy button; 'apply' = Apply now button */
function soharon_offer_card( $coupon, $mode ) {
	$code    = $coupon->get_code();
	$upper   = strtoupper( $code );
	$desc    = $coupon->get_description();
	$applied = 'apply' === $mode && soharon_offer_applied( $code );
	ob_start();
	?>
	<li class="soh-offer<?php echo $applied ? ' is-applied' : ''; ?>">
		<div class="soh-offer__top">
			<strong class="soh-offer__headline"><?php echo esc_html( soharon_offer_headline( $coupon ) ); ?></strong>
			<span class="soh-offer__expiry"><?php echo esc_html( soharon_offer_expiry( $coupon ) ); ?></span>
		</div>
		<?php if ( $desc ) : ?><p class="soh-offer__desc"><?php echo esc_html( $desc ); ?></p><?php endif; ?>
		<?php $conds = soharon_offer_conditions( $coupon, get_current_user_id() ); if ( $conds ) : ?>
			<ul class="soh-offer__conds"><?php foreach ( $conds as $cond ) echo '<li>' . esc_html( $cond ) . '</li>'; ?></ul>
		<?php endif; ?>
		<div class="soh-offer__row">
			<span class="soh-offer__code" aria-label="Promotional code"><?php echo esc_html( $upper ); ?></span>
			<?php if ( 'apply' === $mode ) : ?>
				<button type="button" class="soh-offer__btn" data-soh-apply="<?php echo esc_attr( $code ); ?>"><?php echo $applied ? 'Applied ✓' : 'Apply now'; ?></button>
			<?php else : ?>
				<button type="button" class="soh-offer__btn soh-offer__btn--ghost" data-soh-copy="<?php echo esc_attr( $upper ); ?>">Copy code</button>
			<?php endif; ?>
		</div>
		<p class="soh-offer__msg" role="status" aria-live="polite"></p>
	</li>
	<?php
	return ob_get_clean();
}

/* ----- Cart / checkout bar ----- */
function soharon_offers_login_url( $back ) {
	return add_query_arg( 'redirect_to', rawurlencode( $back ), wc_get_page_permalink( 'myaccount' ) );
}

function soharon_offers_bar( $back_url ) {
	$gift = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
	if ( ! is_user_logged_in() ) {
		return '<div class="soh-offers-bar is-guest"><span class="soh-offers-bar__icon">' . $gift . '</span><span class="soh-offers-bar__text">Have a promotional offer?</span>'
			. '<a class="soh-offers-bar__btn" href="' . esc_url( soharon_offers_login_url( $back_url ) ) . '">Log in to see your offers</a></div>';
	}
	$n = count( soharon_offers_for_user() );
	if ( ! $n ) return '';
	return '<div class="soh-offers-bar"><span class="soh-offers-bar__icon">' . $gift . '</span><span class="soh-offers-bar__text">'
		. esc_html( sprintf( 1 === $n ? 'You have %d promotional offer' : 'You have %d promotional offers', $n ) ) . '</span>'
		. '<button type="button" class="soh-offers-bar__btn" data-soh-offers-open>View my offers</button></div>';
}

/* Checkout: bar above the form */
add_action( 'woocommerce_before_checkout_form', function () {
	if ( is_wc_endpoint_url( 'order-pay' ) ) return;
	soharon_offers_print_assets();
	echo soharon_offers_bar( wc_get_checkout_url() ); // phpcs:ignore
}, 5 );

/* Cart + checkout: assets and the popup (end of page) */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) ) ) return;
	soharon_offers_print_assets();
	$offers = soharon_offers_for_user();
	if ( ! $offers ) return;
	?>
	<dialog class="soh-offers-pop" id="soh-offers-pop" aria-labelledby="soh-offers-title">
		<div class="soh-offers-pop__head">
			<h2 id="soh-offers-title">Your promotional offers</h2>
			<button type="button" class="soh-offers-pop__close" data-soh-offers-close aria-label="Close">&times;</button>
		</div>
		<ul class="soh-offers-list">
			<?php foreach ( $offers as $coupon ) echo soharon_offer_card( $coupon, 'apply' ); // phpcs:ignore ?>
		</ul>
	</dialog>
	<?php
} );

/* Apply an offer (AJAX) – only codes allocated to this customer */
add_action( 'wp_ajax_soharon_apply_offer', 'soharon_ajax_apply_offer' );
add_action( 'wp_ajax_nopriv_soharon_apply_offer', function () {
	wp_send_json_error( array( 'message' => 'Please log in to use your offers.' ) );
} );
function soharon_ajax_apply_offer() {
	check_ajax_referer( 'soharon_offers', 'nonce' );
	$code    = wc_format_coupon_code( wp_unslash( $_POST['code'] ?? '' ) );
	$allowed = false;
	foreach ( soharon_offers_for_user() as $coupon ) {
		if ( wc_format_coupon_code( $coupon->get_code() ) === $code ) $allowed = true;
	}
	if ( ! $allowed ) {
		wp_send_json_error( array( 'message' => 'This offer isn’t available on your account.' ) );
	}

	$ok = WC()->cart->has_discount( $code ) || WC()->cart->apply_coupon( $code );
	$errors = wc_get_notices( 'error' );
	wc_clear_notices();
	WC()->cart->calculate_totals();

	$data = array();
	if ( ! empty( $_POST['cart'] ) && function_exists( 'soharon_get_cart_table_html' ) ) {
		$data['html'] = soharon_get_cart_table_html();
	}
	if ( $ok ) {
		$data['message'] = 'Offer applied to your order.';
		wp_send_json_success( $data );
	}
	$msg = $errors ? wp_strip_all_tags( is_array( $errors[0] ) ? $errors[0]['notice'] : $errors[0] ) : 'This offer can’t be used with your current cart.';
	wp_send_json_error( array( 'message' => $msg ) );
}

/* ----- My Account → My offers ----- */
add_filter( 'woocommerce_get_query_vars', function ( $vars ) {
	$vars[ SOHARON_OFFERS_ENDPOINT ] = SOHARON_OFFERS_ENDPOINT;
	return $vars;
} );
add_action( 'init', function () {
	if ( '1' === get_option( 'soharon_offers_setup' ) ) return;
	flush_rewrite_rules( false ); // one time: registers /my-account/my-offers/
	update_option( 'soharon_offers_setup', '1' );
}, 99 );

add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	$out = array();
	foreach ( $items as $key => $label ) {
		if ( 'customer-logout' === $key && ! isset( $out[ SOHARON_OFFERS_ENDPOINT ] ) ) $out[ SOHARON_OFFERS_ENDPOINT ] = 'My offers';
		$out[ $key ] = $label;
		if ( ( defined( 'SOHARON_TRACK_ENDPOINT' ) ? SOHARON_TRACK_ENDPOINT : 'orders' ) === $key ) $out[ SOHARON_OFFERS_ENDPOINT ] = 'My offers';
	}
	if ( ! isset( $out[ SOHARON_OFFERS_ENDPOINT ] ) ) $out[ SOHARON_OFFERS_ENDPOINT ] = 'My offers';
	return $out;
}, 110 );
add_filter( 'woocommerce_endpoint_' . SOHARON_OFFERS_ENDPOINT . '_title', function () {
	return 'My offers';
} );

add_action( 'woocommerce_account_' . SOHARON_OFFERS_ENDPOINT . '_endpoint', function () {
	soharon_offers_print_assets();
	$offers = soharon_offers_for_user();
	echo '<div class="soh-offers-account">';
	if ( ! $offers ) {
		echo '<div class="soh-offers-empty"><p>You don’t have any promotional offers right now. We’ll show them here when you do.</p>';
		echo '<a class="soh-offer__btn" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Browse products</a></div></div>';
		return;
	}
	echo '<p class="soh-offers-intro">Copy a code and enter it at checkout, or use <strong>View my offers</strong> on the cart or checkout page to apply it in one click.</p>';
	echo '<ul class="soh-offers-list soh-offers-list--grid">';
	foreach ( $offers as $coupon ) echo soharon_offer_card( $coupon, 'account' ); // phpcs:ignore
	echo '</ul>';
	echo '<p class="soh-offers-cta"><a class="soh-offer__btn" href="' . esc_url( wc_get_cart_url() ) . '">Go to cart</a></p>';
	echo '</div>';
} );

/* Dashboard quick link */
add_action( 'woocommerce_account_dashboard', function () {
	$n = count( soharon_offers_for_user() );
	if ( ! $n ) return;
	printf(
		'<p class="soh-offers-dash"><a href="%s">%s</a></p>',
		esc_url( wc_get_account_endpoint_url( SOHARON_OFFERS_ENDPOINT ) ),
		esc_html( sprintf( 1 === $n ? 'You have %d promotional offer – view it' : 'You have %d promotional offers – view them', $n ) )
	);
}, 20 );

/* ----- Styles + script (printed once per page) ----- */
function soharon_offers_print_assets() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	$red  = defined( 'SOHARON_RED' ) ? SOHARON_RED : '#DB141D';
	$css  = <<<'CSS'
.soh-offers-bar,.soh-offers-pop,.soh-offers-account{--o-red:{RED};--o-ink:#1A1A1A;--o-muted:#6B7280;--o-line:#ECECEC;--o-soft:#F7F7F7;font-family:'Poppins',sans-serif}
.soh-offers-bar{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:0 0 18px;padding:12px 14px 12px 16px;border:1px dashed var(--o-red);border-radius:16px;background:#FFF7F7;color:var(--o-ink);font-size:14px;line-height:1.4}
.soharon-cart-summary .soh-offers-bar{margin:14px 0}
.soh-offers-bar__icon{display:flex;color:var(--o-red)}
.soh-offers-bar__text{flex:1 1 150px;font-weight:500}
.soh-offers-bar .soh-offers-bar__btn,.soh-offers-bar .soh-offers-bar__btn:hover,.soh-offers-bar .soh-offers-bar__btn:focus{display:inline-flex;align-items:center;margin:0;padding:9px 18px;border:0;border-radius:999px;background:var(--o-red);color:#fff;font:600 13px/1.2 'Poppins',sans-serif;text-decoration:none;text-transform:none;box-shadow:none;cursor:pointer;white-space:nowrap}
.soh-offers-bar.is-guest .soh-offers-bar__btn{background:#fff;color:var(--o-red);border:1px solid var(--o-red)}

.soh-offers-list{list-style:none;margin:0;padding:0;display:grid;gap:14px}
.soh-offers-list--grid{grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.soh-offer{position:relative;margin:0;padding:18px 18px 16px;border:1px solid var(--o-line);border-radius:18px;background:#fff}
.soh-offer.is-applied{border-color:#17804A;background:#F4FBF6}
.soh-offer__top{display:flex;flex-direction:column;gap:2px}
.soh-offer__headline{font-size:20px;font-weight:700;line-height:1.25;color:var(--o-red)}
.soh-offer__expiry{font-size:12px;color:var(--o-muted)}
.soh-offer__desc{margin:10px 0 0;font-size:14px;line-height:1.55;color:#374151}
.soh-offer__conds{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0 0;padding:0;list-style:none}
.soh-offer__conds li{margin:0;padding:3px 10px;border-radius:999px;background:var(--o-soft);font-size:12px;color:#555}
.soh-offer__row{display:flex;align-items:center;gap:10px;margin-top:14px}
.soh-offer__code{flex:1;min-width:0;padding:10px 14px;border:1.5px dashed #BFC3C8;border-radius:12px;background:var(--o-soft);font:700 16px/1.2 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;letter-spacing:.08em;color:var(--o-ink);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.soh-offers-list--grid .soh-offer{display:flex;flex-direction:column}
.soh-offers-list--grid .soh-offer__row{flex-direction:column;align-items:stretch;margin-top:auto;padding-top:14px}
.soh-offer__btn,a.soh-offer__btn,button.soh-offer__btn{display:inline-flex;align-items:center;justify-content:center;margin:0;padding:11px 20px;border:1px solid var(--o-red);border-radius:999px;background:var(--o-red);color:#fff !important;font:600 14px/1.2 'Poppins',sans-serif;text-decoration:none !important;text-transform:none;box-shadow:none;cursor:pointer;white-space:nowrap;transition:background .2s,color .2s}
.soh-offer__btn:hover{filter:brightness(.92)}
.soh-offer__btn--ghost,button.soh-offer__btn--ghost{background:#fff;color:var(--o-red) !important}
.soh-offer__btn--ghost:hover{background:var(--o-red);color:#fff !important;filter:none}
.soh-offer.is-applied .soh-offer__btn{background:#17804A;border-color:#17804A}
.soh-offer__btn[disabled]{opacity:.6;cursor:wait}
.soh-offer__msg{margin:8px 0 0;font-size:13px;min-height:0}
.soh-offer__msg:empty{display:none}
.soh-offer__msg.is-error{color:var(--o-red)}
.soh-offer__msg.is-ok{color:#17804A}

.soh-offers-pop{width:min(560px,calc(100vw - 24px));max-height:calc(100vh - 40px);margin:auto;padding:0;border:0;border-radius:22px;background:#fff;color:var(--o-ink);box-shadow:0 30px 80px rgba(0,0,0,.25);overflow:auto}
.soh-offers-pop::backdrop{background:rgba(17,17,17,.55)}
.soh-offers-pop__head{position:sticky;top:0;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--o-line);background:#fff}
.soh-offers-pop__head h2{margin:0;font-size:18px;font-weight:700;color:var(--o-ink);text-transform:none}
.soh-offers-pop .soh-offers-pop__close{display:flex;align-items:center;justify-content:center;width:36px;height:36px;margin:0;padding:0;border:0;border-radius:50%;background:var(--o-soft);color:var(--o-ink);font-size:22px;line-height:1;cursor:pointer;box-shadow:none}
.soh-offers-pop .soh-offers-list{padding:16px 20px 20px}
body.soh-lock{overflow:hidden}

.soh-offers-intro{margin:0 0 18px;color:#555}
.soh-offers-cta{margin:20px 0 0}
.soh-offers-empty{padding:28px;text-align:center;border:1px dashed var(--o-line);border-radius:20px}
.soh-offers-empty p{margin:0 0 14px;color:var(--o-muted)}
.soh-offers-dash a{font-weight:600}
@media (max-width:480px){.soh-offer__row{flex-direction:column;align-items:stretch}}
CSS;
	echo '<style id="soh-offers-css">' . str_replace( '{RED}', esc_attr( $red ), $css ) . '</style>'; // phpcs:ignore
	?>
<script id="soh-offers-js">
(function () {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var nonce   = <?php echo wp_json_encode( wp_create_nonce( 'soharon_offers' ) ); ?>;
	function dlg() { return document.getElementById('soh-offers-pop'); }
	function openPop() {
		var d = dlg(); if (!d) return;
		if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
		document.body.classList.add('soh-lock');
	}
	function closePop() {
		var d = dlg(); if (!d) return;
		if (d.open && typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); }
		document.body.classList.remove('soh-lock');
	}
	function copy(text, btn) {
		var done = function () { var t = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function () { btn.textContent = t; }, 1600); };
		if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(text).then(done); return; }
		var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
		document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) {} document.body.removeChild(ta);
	}
	function apply(btn) {
		var card = btn.closest('.soh-offer'), msg = card.querySelector('.soh-offer__msg');
		var cartWrap = document.getElementById('soharon-cart-wrapper');
		var body = new FormData();
		body.append('action', 'soharon_apply_offer'); body.append('nonce', nonce);
		body.append('code', btn.getAttribute('data-soh-apply')); if (cartWrap) body.append('cart', '1');
		btn.disabled = true; btn.textContent = 'Applying…'; msg.className = 'soh-offer__msg'; msg.textContent = '';
		fetch(ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				btn.disabled = false;
				var data = res && res.data ? res.data : {};
				if (res && res.success) {
					card.classList.add('is-applied'); btn.textContent = 'Applied ✓';
					msg.className = 'soh-offer__msg is-ok'; msg.textContent = data.message || 'Offer applied.';
					if (cartWrap && data.html) cartWrap.innerHTML = data.html;
					if (window.jQuery) { jQuery(document.body).trigger('update_checkout').trigger('wc_fragment_refresh'); }
					setTimeout(closePop, 1100);
				} else {
					btn.textContent = card.classList.contains('is-applied') ? 'Applied ✓' : 'Apply now';
					msg.className = 'soh-offer__msg is-error'; msg.textContent = data.message || 'Something went wrong. Please try again.';
				}
			})
			.catch(function () { btn.disabled = false; btn.textContent = 'Apply now'; msg.className = 'soh-offer__msg is-error'; msg.textContent = 'Connection problem. Please try again.'; });
	}
	document.addEventListener('click', function (e) {
		var t;
		if ((t = e.target.closest('[data-soh-offers-open]'))) { e.preventDefault(); openPop(); }
		else if ((t = e.target.closest('[data-soh-offers-close]'))) { closePop(); }
		else if ((t = e.target.closest('[data-soh-apply]'))) { apply(t); }
		else if ((t = e.target.closest('[data-soh-copy]'))) { copy(t.getAttribute('data-soh-copy'), t); }
		else if (e.target === dlg()) { closePop(); } // click outside the box
	});
	document.addEventListener('close', function (e) { if (e.target === dlg()) document.body.classList.remove('soh-lock'); }, true);
})();
</script>
	<?php
}
