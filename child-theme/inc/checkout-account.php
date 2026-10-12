<?php
/**
 * Checkout [soharon_checkout], thank-you screen and My Account [soharon_my_account].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================
 * SHARED HELPERS FOR CHECKOUT + MY ACCOUNT
 * ============================================
 */

/* Does the current page contain a given shortcode? */
function soharon_page_has_shortcode( $tag ) {
    if ( is_admin() ) return false;
    $post = get_post();
    return ( $post instanceof WP_Post ) && has_shortcode( $post->post_content, $tag );
}

/* Tell WooCommerce our shortcode pages are the checkout / account pages,
   so its scripts (payment gateways, country fields, password meter) load. */
add_filter( 'woocommerce_is_checkout', function ( $is ) {
    return $is || soharon_page_has_shortcode( 'soharon_checkout' );
} );
add_filter( 'woocommerce_is_account_page', function ( $is ) {
    return $is || soharon_page_has_shortcode( 'soharon_my_account' );
} );

/* Print inline CSS after WooCommerce + theme styles so ours wins */
function soharon_enqueue_inline_css( $handle, $css ) {
    $deps = array();
    foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'hello-elementor' ) as $h ) {
        if ( wp_style_is( $h, 'registered' ) ) $deps[] = $h;
    }
    wp_register_style( $handle, false, $deps );
    wp_enqueue_style( $handle );
    wp_add_inline_style( $handle, $css );
}

add_action( 'wp_enqueue_scripts', 'soharon_checkout_account_assets', 20 );
function soharon_checkout_account_assets() {
    $is_checkout = is_checkout();
    $is_account  = is_account_page();
    if ( ! $is_checkout && ! $is_account ) return;

    soharon_enqueue_poppins();

    $css = '';
    if ( $is_checkout ) {
        $css .= soharon_wc_shared_css( 'body .soharon-checkout' ) . soharon_checkout_css();
    }
    if ( $is_account ) {
        $css .= soharon_wc_shared_css( 'body .soharon-account' ) . soharon_account_css();
    }
    soharon_enqueue_inline_css( 'soharon-wc-pages', $css );

    if ( $is_account && ! is_user_logged_in() ) {
        wp_register_script( 'soharon-auth-js', false, array(), null, true );
        wp_enqueue_script( 'soharon-auth-js' );
        wp_add_inline_script( 'soharon-auth-js', soharon_auth_js() );
    }
}

/* Styling shared by checkout, thank-you and my account: fields, tables, buttons, notices */
function soharon_wc_shared_css( $scope ) {
    $css = <<<'CSS'
{X}{
    --s-red:{RED}; --s-red-dark:{RED_DARK}; --s-red-soft:#FDECEC;
    --s-green:#22A55B; --s-green-soft:#EDF8F1;
    --s-text:#1A1A1A; --s-muted:#8A8A8A; --s-border:#ECECEC; --s-field:#E3E3E3; --s-bg:#F7F7F7;
    font-family:'Poppins',sans-serif; color:var(--s-text);
}
{X} *,{X} *::before,{X} *::after{ box-sizing:border-box; }
{X} input,{X} select,{X} textarea,{X} button,{X} .select2-selection{ font-family:'Poppins',sans-serif; }
{X} a{ color:var(--s-red); }
{X} a:hover{ color:var(--s-red-dark); }
{X} h2,{X} h3{ font-size:18px; font-weight:600; line-height:1.3; color:var(--s-text); margin:0 0 18px; text-transform:none; }

/* ---------- Fields ---------- */
{X} form .form-row{ margin:0 0 16px; padding:0; }
{X} form .form-row-first,{X} form .form-row-last{ width:calc(50% - 8px); }
{X} form .form-row label{ display:block; margin:0 0 6px; font-size:13px; font-weight:500; color:#555; line-height:1.4; }
{X} form .form-row label.checkbox,
{X} form .form-row label.woocommerce-form__label-for-checkbox{
    display:inline-flex; align-items:center; gap:10px; font-size:14px; color:var(--s-text); cursor:pointer;
}
{X} input[type=checkbox],{X} input[type=radio]{ accent-color:var(--s-red); width:16px; height:16px; margin:0; }
{X} form .form-row .required{ color:var(--s-red); text-decoration:none; border:0; }
{X} .optional{ color:var(--s-muted); font-weight:400; }

{X} form .form-row input.input-text,
{X} form .form-row textarea,
{X} form .form-row select,
{X} input.input-text{
    width:100%; min-height:50px; padding:13px 16px; margin:0;
    border:1px solid var(--s-field); border-radius:14px; background:var(--s-bg);
    font-size:14px; line-height:1.4; color:var(--s-text); box-shadow:none;
    transition:border-color .2s, box-shadow .2s, background .2s;
}
{X} form .form-row textarea{ min-height:110px; resize:vertical; }
{X} input.input-text::placeholder,{X} textarea::placeholder{ color:#A5A5A5; }
{X} form .form-row input.input-text:focus,
{X} form .form-row textarea:focus,
{X} form .form-row select:focus,
{X} input.input-text:focus{
    outline:none; border-color:var(--s-red); background:#fff; box-shadow:0 0 0 4px rgba(219,20,29,.10);
}
{X} form .form-row.woocommerce-invalid input.input-text,
{X} form .form-row.woocommerce-invalid .select2-selection{ border-color:var(--s-red); }
{X} form .form-row.woocommerce-validated input.input-text{ border-color:var(--s-field); }

/* Show-password eye */
{X} .password-input{ position:relative; display:block; width:100%; }
{X} .password-input input.input-text{ padding-right:48px; }
{X} .show-password-input,
{X} .show-password-input:hover,
{X} .show-password-input:focus{
    position:absolute !important; top:50% !important; right:14px !important; transform:translateY(-50%);
    padding:0 !important; margin:0 !important; border:0 !important; background:transparent !important;
    color:var(--s-muted) !important; box-shadow:none !important; cursor:pointer;
}

/* Country / state dropdown (select2) */
{X} .select2-container--default .select2-selection--single{
    height:50px; border:1px solid var(--s-field); border-radius:14px; background:var(--s-bg);
}
{X} .select2-container--default .select2-selection--single .select2-selection__rendered{
    line-height:48px; padding-left:16px; padding-right:40px; color:var(--s-text); font-size:14px;
}
{X} .select2-container--default .select2-selection--single .select2-selection__arrow{ height:48px; right:12px; }
{X} .select2-container--open .select2-selection--single,
{X} .select2-container--focus .select2-selection--single{ border-color:var(--s-red); background:#fff; }
body .select2-dropdown{ border-color:#E3E3E3; border-radius:14px; overflow:hidden; font-family:'Poppins',sans-serif; }
body .select2-container--default .select2-results__option--highlighted[aria-selected],
body .select2-container--default .select2-results__option--highlighted[data-selected]{ background:{RED} !important; color:#fff !important; }
body .select2-search--dropdown .select2-search__field{ border-radius:10px; border-color:#E3E3E3; }

/* ---------- Tables ---------- */
{X} table.shop_table{ width:100%; margin:0 0 20px; border:0; border-radius:0; border-collapse:collapse; }
{X} table.shop_table th,{X} table.shop_table td,
{X} table.shop_table tfoot th,{X} table.shop_table tfoot td{
    padding:13px 10px; border:0; border-bottom:1px solid var(--s-border);
    background:transparent; font-size:14px; color:var(--s-text); text-align:left; vertical-align:middle;
}
{X} table.shop_table tr > :first-child{ padding-left:0; }
{X} table.shop_table tr > :last-child{ padding-right:0; }
{X} table.shop_table thead th{ padding-top:0; font-size:13px; font-weight:500; color:var(--s-muted); }
{X} table.shop_table tfoot th{ font-weight:500; color:#555; }
{X} table.shop_table tr.order-total th,{X} table.shop_table tr.order-total td{
    border-bottom:0; padding-top:16px; font-size:17px; font-weight:700; color:var(--s-text);
}
{X} table.shop_table tr.order-total .woocommerce-Price-amount{ color:var(--s-red); }

/* ---------- Buttons (all pill-shaped, brand red) ---------- */
{X} .button,{X} a.button,{X} button.button,{X} input.button,{X} .button.alt,{X} a.button.alt,{X} button.button.alt{
    display:inline-flex; align-items:center; justify-content:center; gap:8px;
    padding:13px 26px; margin:0; border:0; border-radius:999px;
    background:var(--s-red) !important; color:#fff !important;
    font-size:14px; font-weight:600; line-height:1.2; text-decoration:none !important;
    text-transform:none; box-shadow:none; cursor:pointer; transition:background .2s;
}
{X} .button:hover,{X} .button:focus-visible,{X} .button.alt:hover,{X} .button.alt:focus-visible{
    background:var(--s-red-dark) !important; color:#fff !important;
}
{X} .button:disabled,{X} .button.disabled{ opacity:.5; cursor:not-allowed; }
{X} a.button.soharon-btn-ghost,{X} a.button.soharon-btn-ghost:focus{
    background:#fff !important; color:var(--s-text) !important; border:1px solid var(--s-border);
}
{X} a.button.soharon-btn-ghost:hover{ background:var(--s-red-soft) !important; color:var(--s-red) !important; border-color:var(--s-red); }

/* ---------- Notices ---------- */
{X} .woocommerce-message,{X} .woocommerce-info,{X} .woocommerce-error{
    position:relative; margin:0 0 20px; padding:14px 18px 14px 48px;
    border:0; border-left:4px solid var(--s-red); border-radius:14px;
    background:var(--s-red-soft); color:var(--s-text); font-size:14px; line-height:1.5; list-style:none;
}
{X} .woocommerce-message::before,{X} .woocommerce-info::before,{X} .woocommerce-error::before{ top:15px; left:18px; color:var(--s-red); }
{X} .woocommerce-message{ border-left-color:var(--s-green); background:var(--s-green-soft); }
{X} .woocommerce-message::before{ color:var(--s-green); }
{X} .woocommerce-error li{ margin:0; }
{X} .woocommerce-message .button,{X} .woocommerce-info .button{ float:right; padding:8px 18px; font-size:13px; }
{X} mark{ padding:2px 10px; border-radius:999px; background:var(--s-red-soft); color:var(--s-red); font-weight:600; }

{X}{
    --s-ico-info:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2' stroke-linecap='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cpath d='M12 8v4M12 16h.01'/%3E%3C/svg%3E");
    --s-ico-check:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='black' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6 9 17l-5-5'/%3E%3C/svg%3E");
    --s-ico-tag:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23DB141D' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82zM7 7h.01'/%3E%3C/svg%3E");
}

/* Country dropdown: vertically centred text */
{X} .select2-container--default .select2-selection--single{ display:flex; align-items:center; padding:0; }
{X} .select2-container--default .select2-selection--single .select2-selection__rendered{
    flex:1; padding-top:0; padding-bottom:0; line-height:1.4 !important;
}
{X} .select2-container--default .select2-selection--single .select2-selection__arrow{ top:50%; transform:translateY(-50%); }

/* Notice icons as SVG instead of the icon font */
{X} .woocommerce-message::before,{X} .woocommerce-info::before,{X} .woocommerce-error::before{
    content:""; font-family:inherit; width:18px; height:18px; background:currentColor;
    -webkit-mask:var(--s-ico-info) center/contain no-repeat; mask:var(--s-ico-info) center/contain no-repeat;
}
{X} .woocommerce-message::before{ -webkit-mask-image:var(--s-ico-check); mask-image:var(--s-ico-check); }

/* Textarea (order notes etc.): force same look as other fields */
{X} textarea,{X} textarea.input-text,{X} form .form-row textarea{
    width:100%; min-height:110px; padding:13px 16px !important; font-size:14px; line-height:1.5; color:var(--s-text);
    border:1px solid var(--s-field) !important; border-radius:14px !important;
    background:var(--s-bg) !important; box-shadow:none !important; resize:vertical;
}
{X} textarea:focus,{X} textarea.input-text:focus,{X} form .form-row textarea:focus{
    outline:none; border-color:var(--s-red) !important; background:#fff !important;
    box-shadow:0 0 0 4px rgba(219,20,29,.10) !important;
}

@media (max-width:768px){
    {X} form .form-row-first,{X} form .form-row-last{ width:100%; float:none; }
}
CSS;
    return soharon_brand_vars( $css, array( '{X}' => $scope ) );
}


/**
 * ============================================
 * SOHARON CHECKOUT — [soharon_checkout]
 * Uses WooCommerce's own checkout engine (gateways, validation,
 * coupons, shipping, order-pay) with custom styling, plus a
 * custom thank-you screen after the order is placed.
 * ============================================
 */
add_shortcode( 'soharon_checkout', 'soharon_render_checkout' );
function soharon_render_checkout() {
    if ( ! function_exists( 'WC' ) || is_null( WC()->cart ) ) {
        return '<p>Checkout preview is not available in the editor.</p>';
    }

    ob_start();
    echo '<div class="soharon-checkout">';

    if ( is_wc_endpoint_url( 'order-received' ) ) {
        echo '<div class="woocommerce">';
        soharon_render_thankyou();
        echo '</div>';
    } else {
        // Native WooCommerce checkout (also handles the order-pay page)
        echo do_shortcode( '[woocommerce_checkout]' );
    }

    echo '</div>';
    return ob_get_clean();
}

/**
 * Thank-you screen
 */
function soharon_render_thankyou() {
    global $wp;

    $order_id  = isset( $wp->query_vars['order-received'] ) ? absint( $wp->query_vars['order-received'] ) : 0;
    $order_key = isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
    $order     = $order_id ? wc_get_order( $order_id ) : false;

    // Same security checks WooCommerce uses: valid key, and the order must belong to the logged-in user
    if ( $order && ! hash_equals( $order->get_order_key(), $order_key ) ) {
        $order = false;
    }
    if ( $order && $order->get_customer_id() && is_user_logged_in() && $order->get_customer_id() !== get_current_user_id() ) {
        $order = false;
    }

    if ( WC()->session ) {
        unset( WC()->session->order_awaiting_payment );
    }

    $check_svg = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
    $alert_svg = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="7" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';

    /* ----- Order not found ----- */
    if ( ! $order ) {
        ?>
        <div class="soharon-thankyou">
            <div class="soharon-ty-hero is-failed">
                <div class="soharon-ty-icon"><?php echo $alert_svg; ?></div>
                <h2>We couldn't find this order</h2>
                <p>The link may be incomplete. Sign in to your account to see your orders.</p>
            </div>
            <div class="soharon-ty-actions">
                <a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">Go to my account</a>
                <a class="button soharon-btn-ghost" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Continue shopping</a>
            </div>
        </div>
        <?php
        return;
    }

    do_action( 'woocommerce_before_thankyou', $order->get_id() );

    /* ----- Payment failed ----- */
    if ( $order->has_status( 'failed' ) ) {
        ?>
        <div class="soharon-thankyou">
            <div class="soharon-ty-hero is-failed">
                <div class="soharon-ty-icon"><?php echo $alert_svg; ?></div>
                <h2>Your payment didn't go through</h2>
                <p>Order #<?php echo esc_html( $order->get_order_number() ); ?> is saved. You can try paying again now.</p>
            </div>
            <div class="soharon-ty-actions">
                <a class="button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">Try payment again</a>
                <?php if ( is_user_logged_in() ) : ?>
                    <a class="button soharon-btn-ghost" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">My account</a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return;
    }

    /* ----- Success ----- */
    $totals = $order->get_order_item_totals();
    unset( $totals['payment_method'] ); // shown in the meta strip instead
    ?>
    <div class="soharon-thankyou">

        <div class="soharon-ty-hero">
            <div class="soharon-ty-icon"><?php echo $check_svg; ?></div>
            <h2>Thank you for your order</h2>
            <p><?php echo wp_kses_post( apply_filters(
                'woocommerce_thankyou_order_received_text',
                sprintf( 'We\'ve received your order and sent a confirmation to <strong>%s</strong>.', esc_html( $order->get_billing_email() ) ),
                $order
            ) ); ?></p>
        </div>

        <div class="soharon-ty-meta">
            <div><span>Order number</span><strong><?php echo esc_html( $order->get_order_number() ); ?></strong></div>
            <div><span>Date</span><strong><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></strong></div>
            <div><span>Total</span><strong><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></strong></div>
            <?php if ( $order->get_payment_method_title() ) : ?>
                <div><span>Payment method</span><strong><?php echo esc_html( $order->get_payment_method_title() ); ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="soharon-ty-hooks">
            <?php
            // Payment instructions (e.g. bank transfer details) + tracking / conversion scripts
            remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
            do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
            do_action( 'woocommerce_thankyou', $order->get_id() );
            ?>
        </div>

        <div class="soharon-ty-grid">
            <div class="soharon-ty-card">
                <h3>Order summary</h3>
                <ul class="soharon-ty-items">
                    <?php foreach ( $order->get_items() as $item ) :
                        if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) continue;
                        $product = $item->get_product();
                        $image   = $product ? $product->get_image( array( 64, 64 ) ) : wc_placeholder_img( array( 64, 64 ) );
                        ?>
                        <li>
                            <div class="soharon-ty-thumb"><?php echo $image; ?></div>
                            <div class="soharon-ty-item-info">
                                <strong><?php echo esc_html( $item->get_name() ); ?></strong>
                                <span>Qty <?php echo esc_html( $item->get_quantity() ); ?></span>
                                <?php echo wc_display_item_meta( $item, array( 'echo' => false ) ); ?>
                            </div>
                            <div class="soharon-ty-item-total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="soharon-ty-totals">
                    <?php foreach ( $totals as $key => $total ) : ?>
                        <div class="soharon-ty-total-row <?php echo 'order_total' === $key ? 'is-grand' : ''; ?>">
                            <span><?php echo wp_kses_post( rtrim( $total['label'], ':' ) ); ?></span>
                            <span><?php echo wp_kses_post( $total['value'] ); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ( $order->get_customer_note() ) : ?>
                    <div class="soharon-ty-note"><span>Order note</span><?php echo wp_kses_post( nl2br( wptexturize( $order->get_customer_note() ) ) ); ?></div>
                <?php endif; ?>
            </div>

            <div class="soharon-ty-card">
                <h3>Billing address</h3>
                <address>
                    <?php echo wp_kses_post( $order->get_formatted_billing_address( '—' ) ); ?>
                    <?php if ( $order->get_billing_phone() ) : ?><br><?php echo esc_html( $order->get_billing_phone() ); ?><?php endif; ?>
                    <?php if ( $order->get_billing_email() ) : ?><br><?php echo esc_html( $order->get_billing_email() ); ?><?php endif; ?>
                </address>

                <?php if ( $order->needs_shipping_address() && $order->get_formatted_shipping_address() ) : ?>
                    <h3 class="soharon-ty-sub">Shipping address</h3>
                    <address><?php echo wp_kses_post( $order->get_formatted_shipping_address() ); ?></address>
                <?php endif; ?>
            </div>
        </div>

        <div class="soharon-ty-actions">
            <a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Continue shopping</a>
            <?php if ( is_user_logged_in() && $order->get_customer_id() === get_current_user_id() ) : ?>
                <a class="button soharon-btn-ghost" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">View order in my account</a>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/* Payment options, terms and "Place order" in the LEFT card (under Additional information)
   instead of under the order summary. Still inside the checkout form, and WooCommerce keeps
   refreshing it in place (it updates ".woocommerce-checkout-payment" wherever it is). */
add_action( 'wp', function () {
    if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
        remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
        add_action( 'woocommerce_checkout_shipping', 'soharon_checkout_payment_section', 99 );
    }
} );
function soharon_checkout_payment_section() {
    echo '<div class="soharon-pay-section">';
    if ( WC()->cart && WC()->cart->needs_payment() ) {
        echo '<h3 class="soharon-pay-title">Payment</h3>';
    }
    woocommerce_checkout_payment();
    echo '</div>';
}

/* Checkout + thank-you layout */
function soharon_checkout_css() {
    $css = <<<'CSS'
/* ---------- Coupon / login toggles above the form ---------- */
{S} .woocommerce-form-coupon-toggle .woocommerce-info,
{S} .woocommerce-form-login-toggle .woocommerce-info{ background:#fff; border:1px solid var(--s-border); border-left:4px solid var(--s-red); }
{S} form.checkout_coupon,{S} form.woocommerce-form-login{
    margin:0 0 20px; padding:20px 24px; border:1px solid var(--s-border); border-radius:20px; background:#fff;
}
{S} form.checkout_coupon .form-row-last .button{ width:100%; min-height:50px; }

/* ---------- Two-column layout ---------- */
{S} form.checkout{
    display:grid; grid-template-columns:minmax(0,1fr) 400px;
    grid-template-rows:auto auto auto 1fr;
    grid-template-areas:"notice notice" "details heading" "details review" "details .";
    column-gap:28px; margin:0;
}
{S} form.checkout::before,{S} form.checkout::after{ display:none; }
{S} form.checkout > .woocommerce-NoticeGroup{ grid-area:notice; }

{S} #customer_details{
    grid-area:details; padding:28px; background:#fff;
    border:1px solid var(--s-border); border-radius:24px;
}
{S} #customer_details .col-1,{S} #customer_details .col-2{ float:none; width:100%; max-width:none; padding:0; }
{S} #customer_details .col-2{ margin-top:8px; padding-top:24px; border-top:1px solid var(--s-border); }
{S} #customer_details .col-2:empty{ display:none; }
{S} #ship-to-different-address{ margin:0 0 16px; font-size:16px; }
{S} #ship-to-different-address label{ display:inline-flex; align-items:center; gap:10px; font-weight:600; cursor:pointer; }

{S} #order_review_heading{
    grid-area:heading; margin:0; padding:28px 28px 8px; background:#fff;
    border:1px solid var(--s-border); border-bottom:0; border-radius:24px 24px 0 0;
}
{S} #order_review{
    grid-area:review; padding:8px 28px 28px; background:#fff;
    border:1px solid var(--s-border); border-top:0; border-radius:0 0 24px 24px;
}
{S} .product-quantity{ color:var(--s-muted); font-weight:500; }
{S} table.shop_table .product-total,{S} table.shop_table tfoot td{ text-align:right; }

/* ---------- Payment ---------- */
{S} #payment{ margin-top:8px; padding:0; background:transparent; border-radius:0; }
{S} #payment ul.payment_methods{ list-style:none; margin:0 0 16px; padding:0; border:0; }
{S} #payment ul.payment_methods li{
    display:flex; flex-wrap:wrap; align-items:center; gap:12px;
    margin:0 0 10px; padding:16px 18px; border:1.5px solid var(--s-border);
    border-radius:14px; background:#fff; font-size:14px; line-height:1.5; transition:border-color .2s;
}
{S} #payment ul.payment_methods li:hover{ border-color:#D5D8DC; }
{S} #payment ul.payment_methods li:has(> input:checked){ border-color:var(--s-red); box-shadow:0 0 0 3px rgba(219,20,29,.06); }
{S} #payment ul.payment_methods li > input.input-radio{ flex:0 0 auto; width:18px; height:18px; margin:0; cursor:pointer; }
{S} #payment ul.payment_methods li > label{ flex:1 1 auto; display:flex; align-items:center; gap:8px; margin:0; font-weight:600; color:var(--s-text); cursor:pointer; }
{S} #payment ul.payment_methods li img{ max-height:24px; vertical-align:middle; margin:0 0 0 4px; }
{S} #payment ul.payment_methods li.woocommerce-info{ padding-left:48px; border:0; border-left:4px solid var(--s-red); background:var(--s-red-soft); }

/* Details of the selected method (e.g. Stripe card form): same box, below a thin line */
{S} #payment div.payment_box{
    flex:0 0 100%; margin:4px 0 0; padding:16px 0 0; border-top:1px solid var(--s-border);
    border-radius:0; background:transparent; font-size:13px; color:#555;
}
{S} #payment div.payment_box::before{ display:none; }
{S} #payment div.payment_box p:last-child{ margin-bottom:0; }
{S} #payment div.payment_box fieldset,{S} #payment .wc-payment-form{ min-width:0; margin:0; padding:0; border:0; background:transparent; }
{S} #payment .wc-stripe-upe-element{ margin:0; }
{S} #payment #wc-stripe-upe-errors:not(:empty){ margin-top:10px; color:var(--s-red); font-size:13px; }

/* Stripe test-mode note */
{S} #payment .wc-stripe-payment-method-instruction{
    margin:0 0 14px; padding:10px 12px; border-radius:10px; background:#FFF7E6; color:#7A5300; font-size:12.5px; line-height:1.6;
}
{S} #payment .wc-stripe-payment-method-instruction a{ color:#7A5300; text-decoration:underline; }
{S} #payment .wc-stripe-copy-test-number{
    display:inline; margin:0; padding:0; border:0; background:none; box-shadow:none;
    color:inherit; font:inherit; font-weight:600; cursor:pointer;
}
{S} #payment .wc-stripe-copy-test-number i{ display:none; }

/* "Payment" heading above the methods */
{S} .soharon-pay-section{ margin-top:24px; padding-top:24px; border-top:1px solid var(--s-border); }
{S} .soharon-pay-title{ margin:0 0 14px; font-size:18px; font-weight:600; color:var(--s-text); }
{S} .soharon-pay-section #payment{ margin-top:0; }
{S} #payment div.form-row{ margin:0; padding:0; }
{S} .woocommerce-privacy-policy-text p,
{S} .woocommerce-terms-and-conditions-checkbox-text{ font-size:12px; line-height:1.6; color:var(--s-muted); }
{S} .woocommerce-terms-and-conditions-wrapper{ margin-bottom:8px; }
{S} #payment #place_order{ width:100%; float:none; margin-top:16px; padding:17px 24px; font-size:16px; }

/* ---------- Thank you ---------- */
{S} .soharon-thankyou{ max-width:960px; margin:0 auto; }
{S} .soharon-ty-hero{ text-align:center; padding:12px 16px 28px; }
{S} .soharon-ty-icon{
    display:inline-flex; align-items:center; justify-content:center; width:68px; height:68px;
    margin-bottom:18px; border-radius:50%; background:var(--s-green-soft); color:var(--s-green);
}
{S} .soharon-ty-hero.is-failed .soharon-ty-icon{ background:var(--s-red-soft); color:var(--s-red); }
{S} .soharon-ty-hero h2{ margin:0 0 8px; font-size:28px; font-weight:600; }
{S} .soharon-ty-hero p{ margin:0; font-size:15px; color:#555; }

{S} .soharon-ty-meta{
    display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr));
    margin-bottom:24px; background:#fff; border:1px solid var(--s-border); border-radius:24px; overflow:hidden;
}
{S} .soharon-ty-meta > div{ display:flex; flex-direction:column; gap:4px; padding:20px 24px; border-right:1px solid var(--s-border); }
{S} .soharon-ty-meta > div:last-child{ border-right:0; }
{S} .soharon-ty-meta span{ font-size:12px; color:var(--s-muted); }
{S} .soharon-ty-meta strong{ font-size:15px; font-weight:600; }

{S} .soharon-ty-hooks:empty{ display:none; }
{S} .soharon-ty-hooks{ margin-bottom:24px; }
{S} .soharon-ty-hooks > *{ padding:24px; background:#fff; border:1px solid var(--s-border); border-radius:24px; }
{S} .soharon-ty-hooks ul.wc-bacs-bank-details{ list-style:none; padding:0; margin:0; }

{S} .soharon-ty-grid{ display:grid; grid-template-columns:minmax(0,1.6fr) minmax(0,1fr); gap:24px; align-items:start; }
{S} .soharon-ty-card{ padding:28px; background:#fff; border:1px solid var(--s-border); border-radius:24px; }
{S} .soharon-ty-sub{ margin-top:24px; }
{S} .soharon-ty-items{ list-style:none; margin:0; padding:0; }
{S} .soharon-ty-items li{ display:flex; align-items:center; gap:14px; padding:12px 0; border-bottom:1px solid var(--s-border); }
{S} .soharon-ty-items li:first-child{ padding-top:0; }
{S} .soharon-ty-thumb{
    flex:0 0 56px; width:56px; height:56px; border-radius:12px; overflow:hidden;
    background:var(--s-bg); display:flex; align-items:center; justify-content:center;
}
{S} .soharon-ty-thumb img{ width:100%; height:100%; object-fit:contain; margin:0; }
{S} .soharon-ty-item-info{ flex:1; min-width:0; display:flex; flex-direction:column; gap:2px; }
{S} .soharon-ty-item-info strong{ font-size:14px; font-weight:600; }
{S} .soharon-ty-item-info > span{ font-size:12px; color:var(--s-muted); }
{S} .soharon-ty-item-info .wc-item-meta{ list-style:none; margin:4px 0 0; padding:0; font-size:12px; color:#555; }
{S} .soharon-ty-item-info .wc-item-meta p{ display:inline; margin:0; }
{S} .soharon-ty-item-total{ font-size:14px; font-weight:600; white-space:nowrap; }
{S} .soharon-ty-totals{ margin-top:12px; }
{S} .soharon-ty-total-row{ display:flex; justify-content:space-between; gap:16px; padding:6px 0; font-size:14px; }
{S} .soharon-ty-total-row span:first-child{ color:#555; }
{S} .soharon-ty-total-row.is-grand{ margin-top:8px; padding-top:14px; border-top:1px solid var(--s-border); font-size:17px; font-weight:700; }
{S} .soharon-ty-total-row.is-grand span:first-child{ color:var(--s-text); }
{S} .soharon-ty-total-row.is-grand .woocommerce-Price-amount{ color:var(--s-red); }
{S} .soharon-ty-note{ margin-top:18px; padding:14px 16px; border-radius:14px; background:var(--s-bg); font-size:13px; color:#555; }
{S} .soharon-ty-note span{ display:block; margin-bottom:4px; font-weight:600; color:var(--s-text); }
{S} .soharon-thankyou address{ font-style:normal; font-size:14px; line-height:1.7; color:#555; }
{S} .soharon-ty-actions{ display:flex; flex-wrap:wrap; justify-content:center; gap:12px; margin-top:28px; }

/* Coupon bar: dashed pill with a tag icon */
{S} .woocommerce-form-coupon-toggle .woocommerce-info{
    display:flex; align-items:center; gap:12px; margin:0 0 20px; padding:12px 20px 12px 12px;
    background:#fff; border:1.5px dashed #E8C4C6; border-radius:999px; color:#555;
}
{S} .woocommerce-form-coupon-toggle .woocommerce-info::before{
    position:static; flex:0 0 36px; width:36px; height:36px; border-radius:50%;
    -webkit-mask:none; mask:none;
    background:var(--s-red-soft) var(--s-ico-tag) center/16px no-repeat;
}
{S} .woocommerce-form-coupon-toggle .showcoupon{ font-weight:600; text-decoration:none; }
{S} .woocommerce-form-coupon-toggle .showcoupon:hover{ text-decoration:underline; }

/* Your order: header row as a soft rounded bar */
{S} #order_review_heading{ padding-bottom:16px; }
{S} #order_review table.shop_table{ border-collapse:separate; border-spacing:0; }
{S} #order_review table.shop_table thead th,
{S} #order_review table.shop_table thead tr > th:first-child,
{S} #order_review table.shop_table thead tr > th:last-child{
    padding:10px 14px; border:0; border-block-start:0;
    background:var(--s-bg); font-size:12px; font-weight:600; color:var(--s-muted);
}
{S} #order_review table.shop_table thead th:first-child{ border-radius:12px 0 0 12px; }
{S} #order_review table.shop_table thead th:last-child{ border-radius:0 12px 12px 0; text-align:right; }
{S} #order_review table.shop_table tbody tr:first-child td{ padding-top:16px; }

/* No payment methods notice: centred dashed card */
{S} #payment{ margin-top:16px; }
{S} #payment ul.payment_methods li:has(.woocommerce-info){ padding:0; border:0; background:transparent; }
{S} #payment .woocommerce-info,
{S} #payment ul.payment_methods li.woocommerce-info{
    display:flex; flex-direction:column; align-items:center; gap:10px; margin:0;
    padding:22px 20px; text-align:center; font-size:13px; line-height:1.6; color:#555;
    background:#fff; border:1.5px dashed var(--s-field); border-radius:18px;
}
{S} #payment .woocommerce-info::before,
{S} #payment ul.payment_methods li.woocommerce-info::before{ position:static; width:22px; height:22px; color:var(--s-muted); }

/* Desktop: "Your order" stays in view while the customer scrolls the form
   (heading + summary are two grid items, so both stick; the heading has a fixed height) */
@media (min-width:901px){
    {S} form.checkout{ --s-stick:110px; }
    {S} #order_review_heading{ position:sticky; top:var(--s-stick); z-index:2; height:64px; box-sizing:border-box; }
    {S} #order_review{ position:sticky; top:calc(var(--s-stick) + 64px); z-index:1; align-self:start; }
}

/* ---------- Mobile ---------- */
@media (max-width:900px){
    {S} form.checkout{
        grid-template-columns:1fr; grid-template-rows:auto;
        grid-template-areas:"notice" "heading" "review" "details";
    }
    {S} #order_review{ margin-bottom:20px; }
    {S} .soharon-ty-grid{ grid-template-columns:1fr; }
}
@media (max-width:600px){
    {S} #customer_details,{S} .soharon-ty-card{ padding:20px; border-radius:20px; }
    {S} #order_review_heading{ padding:20px 20px 6px; border-radius:20px 20px 0 0; }
    {S} #order_review{ padding:6px 20px 20px; border-radius:0 0 20px 20px; }
    {S} .soharon-ty-meta > div{ border-right:0; border-bottom:1px solid var(--s-border); }
    {S} .soharon-ty-meta > div:last-child{ border-bottom:0; }
    {S} .soharon-ty-hero h2{ font-size:23px; }
}
CSS;
    return soharon_brand_vars( $css, array( '{S}' => 'body .soharon-checkout' ) );
}


/**
 * ============================================
 * SOHARON MY ACCOUNT — [soharon_my_account]
 * Logged out: sign in / create account (WooCommerce's own handlers)
 * Logged in: full WooCommerce account area, without Downloads
 * ============================================
 */
add_shortcode( 'soharon_my_account', 'soharon_render_my_account' );
function soharon_render_my_account() {
    if ( ! function_exists( 'WC' ) ) return '';

    ob_start();

    if ( is_user_logged_in() ) {
        // Dashboard, orders, view order, addresses, account details, logout
        echo '<div class="soharon-account is-member">' . do_shortcode( '[woocommerce_my_account]' ) . '</div>';

    } elseif ( is_wc_endpoint_url( 'lost-password' ) ) {
        // Lost password → email link → set new password (all native)
        echo '<div class="soharon-account"><div class="soharon-auth"><div class="soharon-auth-card">';
        echo '<h2 class="soharon-auth-title">Reset your password</h2>';
        echo do_shortcode( '[woocommerce_my_account]' );
        echo '<p class="soharon-auth-back"><a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">Back to sign in</a></p>';
        echo '</div></div></div>';

    } else {
        echo '<div class="soharon-account">';
        soharon_render_auth_forms();
        echo '</div>';
    }

    return ob_get_clean();
}

function soharon_render_auth_forms() {
    $reg_enabled  = 'yes' === get_option( 'woocommerce_enable_myaccount_registration' );
    $gen_username = 'yes' === get_option( 'woocommerce_registration_generate_username' );
    $gen_password = 'yes' === get_option( 'woocommerce_registration_generate_password' );

    $active = 'login';
    if ( $reg_enabled && ( isset( $_POST['register'] ) || ( isset( $_GET['tab'] ) && 'register' === $_GET['tab'] ) ) ) {
        $active = 'register';
    }
    ?>
    <div class="soharon-auth">
        <div class="soharon-auth-card woocommerce">

            <?php if ( $reg_enabled ) : ?>
                <div class="soharon-auth-tabs" role="tablist">
                    <button type="button" role="tab" class="soharon-auth-tab <?php echo 'login' === $active ? 'is-active' : ''; ?>" data-target="login" aria-selected="<?php echo 'login' === $active ? 'true' : 'false'; ?>">Sign in</button>
                    <button type="button" role="tab" class="soharon-auth-tab <?php echo 'register' === $active ? 'is-active' : ''; ?>" data-target="register" aria-selected="<?php echo 'register' === $active ? 'true' : 'false'; ?>">Create account</button>
                </div>
            <?php endif; ?>

            <?php if ( WC()->session ) wc_print_notices(); ?>

            <!-- SIGN IN -->
            <div class="soharon-auth-panel" data-panel="login" <?php echo 'login' !== $active ? 'hidden' : ''; ?>>
                <h2 class="soharon-auth-title">Welcome back</h2>
                <p class="soharon-auth-sub">Sign in to track orders and manage your details.</p>

                <form class="woocommerce-form woocommerce-form-login login" method="post">
                    <?php do_action( 'woocommerce_login_form_start' ); ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="username">Email or username&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="username" autocomplete="username"
                               value="<?php echo ( isset( $_POST['login'] ) && ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required />
                    </p>
                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="password">Password&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input class="woocommerce-Input woocommerce-Input--text input-text" type="password" name="password" id="password" autocomplete="current-password" required />
                    </p>

                    <?php do_action( 'woocommerce_login_form' ); ?>

                    <div class="soharon-auth-row">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox woocommerce-form-login__rememberme">
                            <input class="woocommerce-form__input woocommerce-form__input-checkbox" name="rememberme" type="checkbox" id="rememberme" value="forever" />
                            <span>Remember me</span>
                        </label>
                        <a class="soharon-auth-link" href="<?php echo esc_url( wc_lostpassword_url() ); ?>">Forgot password?</a>
                    </div>

                    <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                    <?php
                    // Back to the page that sent them here (e.g. "Log in to see your offers" on cart/checkout)
                    $soharon_back = isset( $_GET['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), '' ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
                    if ( $soharon_back ) {
                        echo '<input type="hidden" name="redirect" value="' . esc_url( $soharon_back ) . '">';
                    }
                    ?>
                    <button type="submit" class="woocommerce-button button woocommerce-form-login__submit" name="login" value="Log in">Sign in</button>

                    <?php do_action( 'woocommerce_login_form_end' ); ?>
                </form>
            </div>

            <?php if ( $reg_enabled ) : ?>
            <!-- CREATE ACCOUNT -->
            <div class="soharon-auth-panel" data-panel="register" <?php echo 'register' !== $active ? 'hidden' : ''; ?>>
                <h2 class="soharon-auth-title">Create your account</h2>
                <p class="soharon-auth-sub">Save your addresses and check out faster.</p>

                <form method="post" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
                    <?php do_action( 'woocommerce_register_form_start' ); ?>

                    <?php if ( ! $gen_username ) : ?>
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_username">Username&nbsp;<span class="required" aria-hidden="true">*</span></label>
                            <input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="username" id="reg_username" autocomplete="username"
                                   value="<?php echo ( isset( $_POST['register'] ) && ! empty( $_POST['username'] ) ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" required />
                        </p>
                    <?php endif; ?>

                    <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                        <label for="reg_email">Email address&nbsp;<span class="required" aria-hidden="true">*</span></label>
                        <input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" autocomplete="email"
                               value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" required />
                    </p>

                    <?php if ( ! $gen_password ) : ?>
                        <p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
                            <label for="reg_password">Password&nbsp;<span class="required" aria-hidden="true">*</span></label>
                            <input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" autocomplete="new-password" required />
                        </p>
                    <?php else : ?>
                        <p class="soharon-auth-note">We'll email you a link to set your password.</p>
                    <?php endif; ?>

                    <?php do_action( 'woocommerce_register_form' ); ?>

                    <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                    <button type="submit" class="woocommerce-Button woocommerce-button button woocommerce-form-register__submit" name="register" value="Register">Create account</button>

                    <?php do_action( 'woocommerce_register_form_end' ); ?>
                </form>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php
}

/* Sign in / Create account tab switching */
function soharon_auth_js() {
    return <<<'JS'
document.addEventListener('click', function (e) {
    var tab = e.target.closest('.soharon-auth-tab');
    if (!tab) return;
    var root = tab.closest('.soharon-auth');
    root.querySelectorAll('.soharon-auth-tab').forEach(function (b) {
        var on = b === tab;
        b.classList.toggle('is-active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    root.querySelectorAll('.soharon-auth-panel').forEach(function (p) {
        p.hidden = p.getAttribute('data-panel') !== tab.getAttribute('data-target');
    });
});
JS;
}

/* ----- Physical store: remove Downloads from My Account ----- */
add_filter( 'woocommerce_account_menu_items', 'soharon_remove_downloads_menu', 99 );
function soharon_remove_downloads_menu( $items ) {
    unset( $items['downloads'] );
    return $items;
}
add_action( 'template_redirect', 'soharon_block_downloads_endpoint' );
function soharon_block_downloads_endpoint() {
    if ( is_account_page() && is_wc_endpoint_url( 'downloads' ) ) {
        wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
        exit;
    }
}

/* ----- Quick links on the account dashboard ----- */
add_action( 'woocommerce_account_dashboard', 'soharon_account_dashboard_cards' );
function soharon_account_dashboard_cards() {
    $cards = array(
        'orders'       => array( 'Orders', 'View your order history' ),
        SOHARON_TRACK_ENDPOINT => array( 'Track order', 'See the status of an order' ),
        'edit-address' => array( 'Addresses', 'Update billing and shipping' ),
        'edit-account' => array( 'Account details', 'Change name, email or password' ),
    );
    echo '<div class="soharon-dash-cards">';
    foreach ( $cards as $endpoint => $card ) {
        printf(
            '<a class="soharon-dash-card" href="%s"><strong>%s</strong><span>%s</span></a>',
            esc_url( wc_get_account_endpoint_url( $endpoint ) ),
            esc_html( $card[0] ),
            esc_html( $card[1] )
        );
    }
    echo '</div>';
}

/* Orders list: show the status as a coloured pill */
add_action( 'woocommerce_my_account_my_orders_column_order-status', function ( $order ) {
    $status = $order->get_status();
    printf(
        '<span class="soharon-status soharon-status--%s">%s</span>',
        esc_attr( $status ),
        esc_html( wc_get_order_status_name( $status ) )
    );
} );

/* My Account layout */
function soharon_account_css() {
    $css = <<<'CSS'
{A} .woocommerce::before,{A} .woocommerce::after{ display:none; }

/* ---------- Signed out: sign in / create account ---------- */
{A} .soharon-auth{ display:flex; justify-content:center; padding:40px 16px; }
{A} .soharon-auth-card{
    width:100%; max-width:460px; padding:32px; background:#fff;
    border:1px solid var(--s-border); border-radius:28px; box-shadow:0 12px 40px rgba(0,0,0,.05);
}
{A} .soharon-auth-tabs{
    display:grid; grid-template-columns:1fr 1fr; gap:4px; padding:4px; margin-bottom:28px;
    background:var(--s-bg); border-radius:999px;
}
{A} .soharon-auth-tab,{A} .soharon-auth-tab:focus{
    padding:11px 16px; margin:0; border:0; border-radius:999px; background:transparent;
    color:#555; font-size:14px; font-weight:600; line-height:1.2; cursor:pointer; box-shadow:none;
    transition:background .2s, color .2s, box-shadow .2s;
}
{A} .soharon-auth-tab:hover{ background:transparent; color:var(--s-red); }
{A} .soharon-auth-tab.is-active{ background:#fff; color:var(--s-text); box-shadow:0 2px 8px rgba(0,0,0,.06); }
{A} .soharon-auth-panel[hidden]{ display:none; }
{A} .soharon-auth-title{ margin:0 0 6px; font-size:24px; font-weight:600; }
{A} .soharon-auth-sub{ margin:0 0 24px; font-size:14px; color:var(--s-muted); }
{A} .soharon-auth form.login,{A} .soharon-auth form.register,{A} .soharon-auth form.lost_reset_password{
    margin:0; padding:0; border:0; border-radius:0;
}
{A} .soharon-auth .form-row-first,{A} .soharon-auth .form-row-last{ width:100%; float:none; }
{A} .soharon-auth-row{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin:4px 0 22px; font-size:13px; }
{A} .soharon-auth-row label{ display:inline-flex; align-items:center; gap:8px; margin:0; font-size:13px; color:var(--s-text); cursor:pointer; }
{A} .soharon-auth-link,{A} .soharon-auth-back a{ font-size:13px; font-weight:500; }
{A} .soharon-auth .button{ width:100%; float:none; padding:15px 24px; font-size:15px; }
{A} .soharon-auth-note,{A} .soharon-auth .woocommerce-privacy-policy-text p{ margin:0 0 16px; font-size:12px; line-height:1.6; color:var(--s-muted); }
{A} .soharon-auth-back{ margin:18px 0 0; text-align:center; }
{A} .woocommerce-password-strength{ margin-top:8px; padding:8px 12px; border-radius:10px; font-size:12px; }
{A} .woocommerce-password-hint{ display:block; margin-top:6px; font-size:12px; color:var(--s-muted); }

/* ---------- Signed in: nav + content ---------- */
{A}.is-member > .woocommerce{
    display:grid; grid-template-columns:260px minmax(0,1fr); gap:28px; align-items:start;
}
{A} .woocommerce-MyAccount-navigation{
    float:none; width:auto; padding:14px; background:#fff;
    border:1px solid var(--s-border); border-radius:24px; position:sticky; top:24px;
}
{A} .woocommerce-MyAccount-navigation ul{ list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:4px; }
{A} .woocommerce-MyAccount-navigation li{ margin:0; padding:0; border:0; }
{A} .woocommerce-MyAccount-navigation li a{
    display:block; padding:12px 18px; border-radius:999px; color:var(--s-text);
    font-size:14px; font-weight:500; text-decoration:none; transition:background .2s, color .2s;
}
{A} .woocommerce-MyAccount-navigation li a:hover{ background:var(--s-bg); color:var(--s-red); }
{A} .woocommerce-MyAccount-navigation li.is-active a{ background:var(--s-red-soft); color:var(--s-red); font-weight:600; }
{A} .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout{
    margin-top:8px; padding-top:8px; border-top:1px solid var(--s-border);
}
{A} .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a{ color:var(--s-muted); }
{A} .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout a:hover{ color:var(--s-red); }

{A} .woocommerce-MyAccount-content{
    float:none; width:auto; min-width:0; padding:32px; background:#fff;
    border:1px solid var(--s-border); border-radius:24px; font-size:14px; line-height:1.7;
}
{A} .woocommerce-MyAccount-content > p{ color:#555; }

/* Dashboard quick links */
{A} .soharon-dash-cards{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-top:24px; }
{A} a.soharon-dash-card{
    display:block; padding:20px 22px; border:1px solid var(--s-border); border-radius:20px;
    background:var(--s-bg); color:var(--s-text); text-decoration:none; transition:border-color .2s, background .2s;
}
{A} a.soharon-dash-card:hover{ border-color:var(--s-red); background:#fff; color:var(--s-text); }
{A} .soharon-dash-card strong{ display:block; margin-bottom:4px; font-size:15px; font-weight:600; }
{A} .soharon-dash-card span{ font-size:13px; color:var(--s-muted); }

/* Orders table */
{A} .woocommerce-pagination{ display:flex; gap:10px; margin-top:12px; }

/* Addresses */
{A} .woocommerce-Addresses,{A} .woocommerce-customer-details .col2-set{
    display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; margin-top:16px;
}
{A} .woocommerce-Addresses::before,{A} .woocommerce-Addresses::after,
{A} .woocommerce-customer-details .col2-set::before,{A} .woocommerce-customer-details .col2-set::after,
{A} .woocommerce-Address-title::before,{A} .woocommerce-Address-title::after{ display:none; }
{A} .woocommerce-Addresses .woocommerce-Address,{A} .woocommerce-customer-details .woocommerce-column{
    float:none; width:auto; max-width:none; padding:22px; border:1px solid var(--s-border);
    border-radius:20px; background:var(--s-bg);
}
{A} .woocommerce-Address-title{ display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:10px; }
{A} .woocommerce-Address-title h2,{A} .woocommerce-Address-title h3{ margin:0; font-size:16px; }
{A} .woocommerce-Address-title a.edit{ float:none; font-size:13px; font-weight:600; }
{A} address,{A} .woocommerce .woocommerce-customer-details address{
    padding:0; border:0; border-radius:0; font-style:normal; line-height:1.7; color:#555;
}
{A} .woocommerce-customer-details h2{ font-size:16px; }

/* Edit account: password section */
{A} fieldset{ margin:24px 0 20px; padding:22px; border:1px solid var(--s-border); border-radius:20px; }
{A} fieldset legend{ padding:0 8px; font-size:15px; font-weight:600; }
{A} .woocommerce-EditAccountForm em{ display:block; margin-top:6px; font-size:12px; color:var(--s-muted); }

/* Account notices: quiet bordered rows, dark outline action */
{A} .woocommerce-MyAccount-content .woocommerce-info,
{A} .woocommerce-MyAccount-content .woocommerce-message,
{A} .woocommerce-MyAccount-content .woocommerce-error{
    display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    margin:0 0 12px; padding:16px 18px 16px 52px;
    background:#fff; border:1px solid var(--s-border); border-radius:16px;
    color:var(--s-text); font-size:14px; line-height:1.5;
}
{A} .woocommerce-MyAccount-content .woocommerce-info::before,
{A} .woocommerce-MyAccount-content .woocommerce-message::before,
{A} .woocommerce-MyAccount-content .woocommerce-error::before{
    top:50%; left:18px; transform:translateY(-50%); color:var(--s-muted);
}
{A} .woocommerce-MyAccount-content .woocommerce-message::before{ color:var(--s-green); }
{A} .woocommerce-MyAccount-content .woocommerce-error::before{ color:var(--s-red); }
{A} .woocommerce-MyAccount-content .woocommerce-info .button,
{A} .woocommerce-MyAccount-content .woocommerce-message .button{
    order:2; float:none; margin-left:auto; padding:9px 18px; font-size:13px; font-weight:500;
    background:transparent !important; color:var(--s-red) !important;
    border:1px solid var(--s-red); border-radius:999px;
}
{A} .woocommerce-MyAccount-content .woocommerce-info .button:hover,
{A} .woocommerce-MyAccount-content .woocommerce-message .button:hover{
    background:var(--s-red) !important; color:#fff !important;
}

/* Order details (view order / thank-you): clean bordered table */
{A} .woocommerce-order-details__title,{A} .woocommerce-column__title{ margin:28px 0 12px; font-size:18px; font-weight:600; }
{A} .woocommerce-MyAccount-content mark{ padding:0; background:transparent; color:var(--s-text); font-weight:600; }
{A} table.shop_table.order_details{
    width:100%; margin:0 0 8px; background:#fff;
    border:1px solid var(--s-border) !important; border-collapse:separate !important; border-spacing:0;
    border-radius:18px; overflow:hidden;
}
{A} table.order_details th,{A} table.order_details td{
    padding:14px 18px !important; border:0 !important; border-top:1px solid var(--s-border) !important;
    background:transparent !important; text-align:left; vertical-align:top; font-size:14px; line-height:1.5;
}
{A} table.order_details thead th{
    border-top:0 !important; background:var(--s-bg) !important;
    font-size:13px; font-weight:600; color:var(--s-muted);
}
{A} table.order_details .product-total,{A} table.order_details tfoot td{ text-align:right; white-space:nowrap; }
{A} table.order_details .product-name a{ color:var(--s-text); font-weight:500; }
{A} table.order_details .product-name a:hover{ color:var(--s-red); }
{A} table.order_details .product-quantity{ color:var(--s-muted); font-weight:500; }
{A} table.order_details tfoot th{ font-weight:500; color:var(--s-muted); }
{A} table.order_details tfoot td{ font-weight:600; color:var(--s-text); }

/* Orders list: clean bordered table */
{A} table.my_account_orders{
    width:100%; margin:0; background:#fff;
    border:1px solid var(--s-border) !important; border-collapse:separate !important; border-spacing:0;
    border-radius:18px; overflow:hidden;
}
{A} table.my_account_orders th,{A} table.my_account_orders td{
    padding:14px 18px !important; border:0 !important; border-top:1px solid var(--s-border) !important;
    background:transparent !important; text-align:left; vertical-align:middle; font-size:14px; line-height:1.5;
}
{A} table.my_account_orders thead th{
    border-top:0 !important; background:var(--s-bg) !important;
    font-size:13px; font-weight:600; color:var(--s-muted);
}
{A} table.my_account_orders .woocommerce-orders-table__cell-order-number a{ font-weight:600; color:var(--s-text); }
{A} table.my_account_orders .woocommerce-orders-table__cell-order-number a:hover{ color:var(--s-red); }
{A} table.my_account_orders .woocommerce-orders-table__cell-order-date{ color:var(--s-muted); }
{A} table.my_account_orders .woocommerce-orders-table__cell-order-total{ color:var(--s-muted); }
{A} table.my_account_orders .woocommerce-orders-table__cell-order-total .amount{ color:var(--s-text); font-weight:600; }
{A} table.my_account_orders .woocommerce-orders-table__header-order-actions,
{A} table.my_account_orders .woocommerce-orders-table__cell-order-actions{ text-align:right; }
{A} table.my_account_orders .button{
    display:inline-flex; align-items:center; margin:2px 0 2px 6px; padding:7px 18px !important;
    border:1px solid var(--s-red) !important; border-radius:999px !important;
    background:transparent !important; color:var(--s-red) !important;
    font-size:13px; font-weight:600; line-height:1.4; box-shadow:none !important;
}
{A} table.my_account_orders .button:hover{ background:var(--s-red) !important; color:#fff !important; }

/* Status pills */
{A} .soharon-status{
    display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px;
    font-size:12px; font-weight:600; line-height:1.4; white-space:nowrap;
    background:#F1F2F4; color:#4B5563;
}
{A} .soharon-status::before{ content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
{A} .soharon-status--processing{ background:#FEF3E2; color:#B45309; }
{A} .soharon-status--on-hold{ background:#FEF3E2; color:#B45309; }
{A} .soharon-status--completed{ background:#E8F6EE; color:#17804A; }
{A} .soharon-status--cancelled,{A} .soharon-status--failed{ background:var(--s-red-soft); color:var(--s-red); }

/* Billing / shipping address card */
{A} .woocommerce-customer-details address,
{A} .woocommerce .woocommerce-customer-details address{
    max-width:440px; margin:0 !important; padding:20px 22px !important;
    background:#fff !important; border:1px solid var(--s-border) !important; border-radius:18px !important;
    font-style:normal; font-size:14px; line-height:1.75; color:#555;
}
{A} .woocommerce-customer-details address::first-line{ font-weight:600; color:var(--s-text); }

/* Phone + email rows with round red icons */
{A} .woocommerce-customer-details address p,
{A} .woocommerce .woocommerce-customer-details address p{
    position:relative; display:flex; align-items:center; gap:12px;
    margin:14px 0 0 !important; padding:14px 0 0 !important;
    border-top:1px solid var(--s-border); color:var(--s-text); font-size:14px; line-height:1.4;
}
{A} .woocommerce-customer-details address p + p,
{A} .woocommerce .woocommerce-customer-details address p + p{
    margin-top:10px !important; padding-top:0 !important; border-top:0;
}
{A} .woocommerce-customer-details address p::before,
{A} .woocommerce .woocommerce-customer-details address p::before{
    position:static !important; flex:0 0 32px; display:flex; align-items:center; justify-content:center;
    width:32px; height:32px; margin:0 !important; border-radius:50%;
    background:var(--s-red-soft); color:var(--s-red); font-size:13px; line-height:32px; text-align:center;
}

/* Desktop: address on the left, phone + email on the right */
@media (min-width:768px){
    {A} .woocommerce-customer-details address,
    {A} .woocommerce .woocommerce-customer-details address{
        position:relative; max-width:none; min-height:124px;
        padding:20px 24px !important; padding-right:calc(50% + 24px) !important;
    }
    {A} .woocommerce-customer-details address::after{
        content:""; position:absolute; top:20px; bottom:20px; left:50%;
        border-left:1px solid var(--s-border);
    }
    {A} .woocommerce-customer-details address p,
    {A} .woocommerce .woocommerce-customer-details address p{
        position:absolute !important; top:20px; left:calc(50% + 24px); right:24px;
        margin:0 !important; padding:0 !important; border-top:0;
        overflow-wrap:anywhere;
    }
    {A} .woocommerce-customer-details address p + p,
    {A} .woocommerce .woocommerce-customer-details address p + p{ top:64px; margin:0 !important; }
}

/* ---------- Mobile ---------- */
@media (max-width:900px){
    {A}.is-member > .woocommerce{ grid-template-columns:1fr; }
    {A} .woocommerce-MyAccount-navigation{ position:static; }
    {A} .woocommerce-MyAccount-navigation ul{ flex-direction:row; flex-wrap:wrap; }
    {A} .woocommerce-MyAccount-navigation li.woocommerce-MyAccount-navigation-link--customer-logout{ margin:0; padding:0; border:0; }
}
@media (max-width:600px){
    {A} .soharon-auth-card,{A} .woocommerce-MyAccount-content{ padding:22px; border-radius:20px; }
    {A} .woocommerce-Addresses,{A} .woocommerce-customer-details .col2-set{ grid-template-columns:1fr; }
    {A} table.order_details th,{A} table.order_details td{ padding:12px 14px !important; font-size:13px; }
    {A} .woocommerce-customer-details address{ max-width:none; padding:18px !important; }
}

/* Orders list on phones: each order becomes a card */
@media (max-width:768px){
    {A} table.my_account_orders{ border:0 !important; border-radius:0; overflow:visible; background:transparent; }
    {A} table.my_account_orders thead{ display:none; }
    {A} table.my_account_orders tbody tr{
        display:block; margin:0 0 12px; background:#fff;
        border:1px solid var(--s-border); border-radius:16px; overflow:hidden;
    }
    {A} table.my_account_orders tbody th,{A} table.my_account_orders tbody td{
        display:flex !important; justify-content:space-between; align-items:center; gap:12px;
        padding:11px 16px !important; text-align:right !important;
    }
    {A} table.my_account_orders tbody tr > :first-child{ border-top:0 !important; background:var(--s-bg) !important; }
    {A} table.my_account_orders tbody th::before,{A} table.my_account_orders tbody td::before{
        content:attr(data-title); float:none !important; font-size:13px; font-weight:500; color:var(--s-muted);
    }
    {A} table.my_account_orders .woocommerce-orders-table__cell-order-actions::before{ content:""; }
    {A} table.my_account_orders .button{ margin:0; }
}
CSS;
    return soharon_brand_vars( $css, array( '{A}' => 'body .soharon-account' ) ) . soharon_track_status_pill_css( 'body .soharon-account' );
}
