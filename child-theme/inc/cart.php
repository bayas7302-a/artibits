<?php
/**
 * Custom WooCommerce cart – [soharon_custom_cart].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ============================================
 * SOHARON CUSTOM WOOCOMMERCE CART
 * Place [soharon_custom_cart] on your Cart page
 * ============================================
 */

/**
 * 1. Assets — only on the Cart page
 */
add_action( 'wp_enqueue_scripts', 'soharon_cart_assets' );
function soharon_cart_assets() {
    if ( ! is_cart() ) return;

    soharon_enqueue_poppins();

    wp_register_style( 'soharon-custom-cart-css', false );
    wp_enqueue_style( 'soharon-custom-cart-css' );
    wp_add_inline_style( 'soharon-custom-cart-css', soharon_custom_cart_css() );

    wp_register_script( 'soharon-custom-cart-js', false, array( 'jquery' ), null, true );
    wp_enqueue_script( 'soharon-custom-cart-js' );
    wp_localize_script( 'soharon-custom-cart-js', 'soharonCart', array(
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'soharon_cart_nonce' ),
    ) );
    wp_add_inline_script( 'soharon-custom-cart-js', soharon_custom_cart_js() );
}

/**
 * 2. Shortcode
 */
add_shortcode( 'soharon_custom_cart', 'soharon_render_custom_cart' );
function soharon_render_custom_cart() {
    ob_start();
    ?>
    <div id="soharon-cart-wrapper" class="soharon-cart-wrapper">
        <?php echo soharon_get_cart_table_html(); ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 3. Renderer — used on page load and by every AJAX refresh
 */
function soharon_get_cart_table_html() {
    if ( WC()->cart->is_empty() ) {
        ob_start();
        ?>
        <div class="soharon-empty-cart">
            <p>Your cart is empty.</p>
            <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="soharon-checkout-btn soharon-shop-btn">Browse products</a>
        </div>
        <?php
        return ob_get_clean();
    }

    ob_start();
    ?>
    <div class="soharon-cart-head">
        <span class="col-product">Product</span>
        <span class="col-price">Price</span>
        <span class="col-discount">Discount</span>
        <span class="col-qty">Quantity</span>
        <span class="col-subtotal">Subtotal</span>
        <span class="col-remove"></span>
    </div>

    <div class="soharon-cart-items">
        <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
            $product = $cart_item['data'];
            if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 ) continue;

            $qty       = $cart_item['quantity'];
            $sku       = $product->get_sku();
            $name      = $product->get_name();
            $unit_raw  = wc_get_price_to_display( $product );
            $in_stock  = $product->is_in_stock();
            ?>
            <div class="soharon-cart-row"
                 data-cart-key="<?php echo esc_attr( $cart_item_key ); ?>"
                 data-sku="<?php echo esc_attr( $sku ); ?>"
                 data-name="<?php echo esc_attr( $name ); ?>"
                 data-price="<?php echo esc_attr( wc_format_decimal( $unit_raw, wc_get_price_decimals() ) ); ?>"
                 data-qty="<?php echo esc_attr( $qty ); ?>"
                 data-subtotal="<?php echo esc_attr( wc_format_decimal( $unit_raw * $qty, wc_get_price_decimals() ) ); ?>">

                <div class="col-product">
                    <div class="soharon-product-img"><?php echo $product->get_image( array( 80, 80 ) ); ?></div>
                    <div class="soharon-product-info">
                        <?php if ( $sku ) : ?>
                            <span class="soharon-sku"><?php echo esc_html( $sku ); ?></span>
                        <?php endif; ?>
                        <span class="soharon-name"><?php echo esc_html( $name ); ?></span>
                        <span class="soharon-stock">
                            <span class="soharon-dot <?php echo $in_stock ? 'in' : 'out'; ?>"></span>
                            <?php echo $in_stock ? 'In stock' : 'Out of stock'; ?>
                        </span>
                    </div>
                </div>

                <div class="col-price"><?php echo WC()->cart->get_product_price( $product ); ?></div>

                <div class="col-discount">—</div>

                <div class="col-qty">
                    <div class="soharon-qty">
                        <button type="button" class="soharon-qty-btn soharon-qty-minus" aria-label="Decrease quantity"><?php echo soharon_icon( 'minus' ); ?></button>
                        <span class="soharon-qty-value"><?php echo esc_html( $qty ); ?></span>
                        <button type="button" class="soharon-qty-btn soharon-qty-plus" aria-label="Increase quantity"><?php echo soharon_icon( 'plus' ); ?></button>
                    </div>
                </div>

                <div class="col-subtotal"><?php echo WC()->cart->get_product_subtotal( $product, $qty ); ?></div>

                <div class="col-remove">
                    <button type="button" class="soharon-remove-btn" aria-label="Remove item"><?php echo soharon_icon( 'close' ); ?></button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="soharon-cart-footer">
        <div class="soharon-cart-actions">
            <button type="button" id="soharon-refresh-cart" class="soharon-action-btn"><?php echo soharon_icon( 'refresh' ); ?><span>Refresh</span></button>
            <button type="button" id="soharon-export-cart" class="soharon-action-btn"><?php echo soharon_icon( 'download' ); ?><span>Export cart</span></button>
            <button type="button" id="soharon-clear-cart" class="soharon-action-btn"><?php echo soharon_icon( 'trash' ); ?><span>Clear cart</span></button>
        </div>

        <div class="soharon-cart-summary">
            <div class="soharon-summary-line">
                <span>Subtotal</span>
                <span><?php echo wc_price( WC()->cart->get_subtotal() ); ?></span>
            </div>
            <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                <div class="soharon-summary-line soharon-discount-line">
                    <span>Discount <strong class="soharon-coupon-code"><?php echo esc_html( strtoupper( $code ) ); ?></strong>
                        <button type="button" class="soharon-coupon-remove" data-code="<?php echo esc_attr( $code ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Remove code %s', strtoupper( $code ) ) ); ?>">&times;</button></span>
                    <span>&minus;<?php echo wc_price( WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax ) ); ?></span>
                </div>
            <?php endforeach; ?>
            <div class="soharon-summary-line">
                <span>VAT</span>
                <span><?php echo wc_price( WC()->cart->get_total_tax() ); ?></span>
            </div>
            <div class="soharon-summary-line soharon-total-line">
                <span>Total</span>
                <span><?php echo wc_price( WC()->cart->get_total( 'edit' ) ); ?></span>
            </div>
            <?php if ( function_exists( 'soharon_offers_bar' ) ) echo soharon_offers_bar( wc_get_cart_url() ); // phpcs:ignore ?>
            <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="soharon-checkout-btn">Check out</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * 4. AJAX: update quantity
 */
add_action( 'wp_ajax_soharon_update_qty', 'soharon_ajax_update_qty' );
add_action( 'wp_ajax_nopriv_soharon_update_qty', 'soharon_ajax_update_qty' );
function soharon_ajax_update_qty() {
    check_ajax_referer( 'soharon_cart_nonce', 'nonce' );

    $cart_key = sanitize_text_field( wp_unslash( $_POST['cart_key'] ?? '' ) );
    $qty      = max( 0, intval( $_POST['qty'] ?? 0 ) );

    if ( $qty === 0 ) {
        WC()->cart->remove_cart_item( $cart_key );
    } else {
        WC()->cart->set_quantity( $cart_key, $qty );
    }
    WC()->cart->calculate_totals();

    wp_send_json_success( array( 'html' => soharon_get_cart_table_html() ) );
}

/**
 * 5. AJAX: remove item
 */
add_action( 'wp_ajax_soharon_remove_item', 'soharon_ajax_remove_item' );
add_action( 'wp_ajax_nopriv_soharon_remove_item', 'soharon_ajax_remove_item' );
function soharon_ajax_remove_item() {
    check_ajax_referer( 'soharon_cart_nonce', 'nonce' );

    $cart_key = sanitize_text_field( wp_unslash( $_POST['cart_key'] ?? '' ) );
    WC()->cart->remove_cart_item( $cart_key );
    WC()->cart->calculate_totals();

    wp_send_json_success( array( 'html' => soharon_get_cart_table_html() ) );
}

/**
 * 6. AJAX: clear cart
 */
add_action( 'wp_ajax_soharon_remove_coupon', 'soharon_ajax_remove_coupon' );
add_action( 'wp_ajax_nopriv_soharon_remove_coupon', 'soharon_ajax_remove_coupon' );
function soharon_ajax_remove_coupon() {
    check_ajax_referer( 'soharon_cart_nonce', 'nonce' );
    WC()->cart->remove_coupon( wc_format_coupon_code( wp_unslash( $_POST['code'] ?? '' ) ) );
    WC()->cart->calculate_totals();
    wc_clear_notices();
    wp_send_json_success( array( 'html' => soharon_get_cart_table_html() ) );
}

add_action( 'wp_ajax_soharon_clear_cart', 'soharon_ajax_clear_cart' );
add_action( 'wp_ajax_nopriv_soharon_clear_cart', 'soharon_ajax_clear_cart' );
function soharon_ajax_clear_cart() {
    check_ajax_referer( 'soharon_cart_nonce', 'nonce' );
    WC()->cart->empty_cart();
    wp_send_json_success( array( 'html' => soharon_get_cart_table_html() ) );
}

/**
 * 7. AJAX: refresh totals
 */
add_action( 'wp_ajax_soharon_refresh_cart', 'soharon_ajax_refresh_cart' );
add_action( 'wp_ajax_nopriv_soharon_refresh_cart', 'soharon_ajax_refresh_cart' );
function soharon_ajax_refresh_cart() {
    check_ajax_referer( 'soharon_cart_nonce', 'nonce' );
    WC()->cart->calculate_totals();
    wp_send_json_success( array( 'html' => soharon_get_cart_table_html() ) );
}

/**
 * 8. Cart CSS
 */
function soharon_custom_cart_css() {
    $css = <<<'CSS'
.soharon-cart-wrapper{
    --s-red:{RED}; --s-red-dark:{RED_DARK}; --s-red-soft:#FDECEC;
    --s-text:#1A1A1A; --s-muted:#8A8A8A; --s-border:#ECECEC; --s-bg:#F7F7F7;
    font-family:'Poppins',sans-serif; color:var(--s-text); transition:opacity .2s;
}
.soharon-cart-wrapper *,.soharon-cart-wrapper *::before,.soharon-cart-wrapper *::after{ box-sizing:border-box; }
.soharon-cart-wrapper button,.soharon-cart-wrapper a{ font-family:inherit; }
.soharon-cart-wrapper button:focus{ outline:none; }
.soharon-cart-wrapper.is-loading{ opacity:.5; pointer-events:none; }
.soharon-cart-wrapper .soharon-icon{ display:block; width:16px; height:16px; flex-shrink:0; }

/* ---------- Table grid (header + rows share the same columns) ---------- */
.soharon-cart-head,.soharon-cart-row{
    display:grid;
    grid-template-columns:minmax(0,2.4fr) 1fr 1fr 1.2fr 1fr 44px;
    align-items:center; column-gap:16px;
}
.soharon-cart-head{ padding:0 24px 12px; }
.soharon-cart-head span{ font-size:13px; font-weight:500; color:var(--s-muted); }

.soharon-cart-items{ display:flex; flex-direction:column; gap:14px; }
.soharon-cart-row{
    background:#fff; border:1px solid var(--s-border); border-radius:20px;
    padding:18px 24px; transition:border-color .2s, box-shadow .2s;
}
.soharon-cart-row:hover{ border-color:#E2E2E2; box-shadow:0 8px 24px rgba(0,0,0,.05); }

/* ---------- Product cell ---------- */
.soharon-cart-row .col-product{ display:flex; align-items:center; gap:16px; min-width:0; }
.soharon-product-img{
    flex:0 0 72px; width:72px; height:72px; border-radius:14px; background:var(--s-bg);
    overflow:hidden; display:flex; align-items:center; justify-content:center;
}
.soharon-product-img img{ width:100%; height:100%; object-fit:contain; margin:0; border-radius:0; }
.soharon-product-info{ display:flex; flex-direction:column; gap:3px; min-width:0; }
.soharon-sku{ font-size:12px; color:var(--s-muted); }
.soharon-name{ font-size:15px; font-weight:600; line-height:1.35; }
.soharon-stock{ display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#6B6B6B; }
.soharon-dot{ width:8px; height:8px; border-radius:50%; display:inline-block; }
.soharon-dot.in{ background:var(--s-red); }
.soharon-dot.out{ background:#BBB; }

.soharon-cart-row .col-price,.soharon-cart-row .col-subtotal{ font-size:15px; font-weight:600; }
.soharon-cart-row .col-discount{ color:var(--s-muted); }

/* ---------- Quantity stepper ---------- */
.soharon-qty{
    display:inline-flex; align-items:center; gap:4px; padding:4px;
    border:1px solid var(--s-border); border-radius:999px; background:#fff;
}
.soharon-cart-wrapper .soharon-qty-btn,
.soharon-cart-wrapper .soharon-qty-btn:focus{
    display:inline-flex; align-items:center; justify-content:center;
    width:32px; height:32px; padding:0; margin:0; border:0; border-radius:50%;
    background:var(--s-bg); color:var(--s-text); line-height:1; cursor:pointer;
    box-shadow:none; transition:background .2s, color .2s;
}
.soharon-cart-wrapper .soharon-qty-btn .soharon-icon{ width:14px; height:14px; }
.soharon-cart-wrapper .soharon-qty-btn:hover,
.soharon-cart-wrapper .soharon-qty-btn:focus-visible{ background:var(--s-red); color:#fff; }
.soharon-qty-value{ min-width:28px; text-align:center; font-size:15px; font-weight:600; }

/* ---------- Remove ---------- */
.soharon-cart-row .col-remove{ display:flex; justify-content:flex-end; }
.soharon-cart-wrapper .soharon-remove-btn,
.soharon-cart-wrapper .soharon-remove-btn:focus{
    display:inline-flex; align-items:center; justify-content:center;
    width:36px; height:36px; padding:0; margin:0; border:0; border-radius:50%;
    background:transparent; color:var(--s-muted); cursor:pointer; box-shadow:none;
    transition:background .2s, color .2s;
}
.soharon-cart-wrapper .soharon-remove-btn:hover,
.soharon-cart-wrapper .soharon-remove-btn:focus-visible{ background:var(--s-red-soft); color:var(--s-red); }

/* ---------- Footer: actions + summary ---------- */
.soharon-cart-footer{
    display:flex; justify-content:space-between; align-items:flex-start;
    flex-wrap:wrap; gap:24px; margin-top:28px;
}
.soharon-cart-actions{ display:flex; flex-wrap:wrap; gap:12px; }
.soharon-cart-wrapper .soharon-action-btn,
.soharon-cart-wrapper .soharon-action-btn:focus{
    display:inline-flex; align-items:center; gap:8px; padding:12px 22px; margin:0;
    border:1px solid var(--s-border); border-radius:999px; background:#fff;
    color:var(--s-text); font-size:14px; font-weight:500; line-height:1;
    cursor:pointer; box-shadow:none; text-transform:none;
    transition:border-color .2s, background .2s, color .2s;
}
.soharon-cart-wrapper .soharon-action-btn:hover,
.soharon-cart-wrapper .soharon-action-btn:focus-visible{
    border-color:var(--s-red); background:var(--s-red-soft); color:var(--s-red);
}

.soharon-cart-summary{
    flex:0 1 380px; min-width:320px; background:#fff;
    border:1px solid var(--s-border); border-radius:24px; padding:24px;
}
.soharon-summary-line{ display:flex; justify-content:space-between; align-items:center; padding:7px 0; font-size:15px; }
.soharon-summary-line span:first-child{ color:#555; }
.soharon-discount-line span:last-child{ color:#17804A; font-weight:600; }
.soharon-coupon-code{ margin-left:4px; padding:2px 8px; border:1px dashed #BFC3C8; border-radius:6px; font-size:12px; font-weight:600; letter-spacing:.04em; color:#1A1A1A; }
.soharon-coupon-remove{ margin-left:6px; padding:0 6px !important; border:0 !important; background:none !important; color:#8A8A8A !important; font-size:18px; line-height:1; cursor:pointer; box-shadow:none !important; }
.soharon-coupon-remove:hover{ color:{RED} !important; }
.soharon-total-line{ margin-top:8px; padding-top:16px; border-top:1px solid var(--s-border); font-size:18px; font-weight:700; }
.soharon-total-line span:first-child{ color:var(--s-text); }

.soharon-cart-wrapper a.soharon-checkout-btn,
.soharon-cart-wrapper a.soharon-checkout-btn:visited,
.soharon-cart-wrapper a.soharon-checkout-btn:focus{
    display:flex; align-items:center; justify-content:center; margin-top:20px;
    padding:16px 24px; border-radius:999px; background:var(--s-red) !important;
    color:#fff !important; font-size:16px; font-weight:600; text-decoration:none !important;
    transition:background .2s;
}
.soharon-cart-wrapper a.soharon-checkout-btn:hover{ background:var(--s-red-dark) !important; color:#fff !important; }

/* ---------- Empty cart ---------- */
.soharon-empty-cart{
    text-align:center; padding:48px 24px; background:#fff;
    border:1px solid var(--s-border); border-radius:24px;
}
.soharon-empty-cart p{ font-size:16px; color:#6B6B6B; margin:0; }
.soharon-cart-wrapper a.soharon-shop-btn{ display:inline-flex; padding:14px 32px; }

/* ---------- Mobile ---------- */
@media (max-width:768px){
    .soharon-cart-head{ display:none; }
    .soharon-cart-row{ grid-template-columns:1fr auto; row-gap:14px; position:relative; padding:16px; }
    .soharon-cart-row .col-product{ grid-column:1 / -1; padding-right:40px; }
    .soharon-cart-row .col-price,.soharon-cart-row .col-discount{ display:none; }
    .soharon-cart-row .col-subtotal{ text-align:right; }
    .soharon-cart-row .col-remove{ position:absolute; top:12px; right:12px; }
    .soharon-cart-summary{ flex:1 1 100%; min-width:0; }
}
CSS;
    return soharon_brand_vars( $css );
}

/**
 * 9. Cart JS
 */
function soharon_custom_cart_js() {
    return <<<'JS'
jQuery(function($){
    var $wrap = $('#soharon-cart-wrapper');
    if (!$wrap.length) return;

    function ajaxCall(action, data) {
        $wrap.addClass('is-loading');
        $.post(soharonCart.ajax_url, $.extend({ action: action, nonce: soharonCart.nonce }, data))
            .done(function(res){
                if (res && res.success) {
                    $wrap.html(res.data.html);
                    $(document.body).trigger('wc_fragment_refresh'); // updates header mini-cart
                }
            })
            .always(function(){ $wrap.removeClass('is-loading'); });
    }

    $wrap.on('click', '.soharon-qty-plus, .soharon-qty-minus', function(){
        var $row = $(this).closest('.soharon-cart-row');
        var qty  = parseInt($row.find('.soharon-qty-value').text(), 10) || 0;
        qty = $(this).hasClass('soharon-qty-plus') ? qty + 1 : Math.max(0, qty - 1);
        ajaxCall('soharon_update_qty', { cart_key: $row.data('cart-key'), qty: qty });
    });

    $wrap.on('click', '.soharon-remove-btn', function(){
        ajaxCall('soharon_remove_item', { cart_key: $(this).closest('.soharon-cart-row').data('cart-key') });
    });

    $wrap.on('click', '#soharon-clear-cart', function(){
        if (confirm('Remove all items from your cart?')) ajaxCall('soharon_clear_cart', {});
    });

    $wrap.on('click', '.soharon-coupon-remove', function(){
        ajaxCall('soharon_remove_coupon', { code: $(this).data('code') });
    });

    $wrap.on('click', '#soharon-refresh-cart', function(){
        ajaxCall('soharon_refresh_cart', {});
    });

    $wrap.on('click', '#soharon-export-cart', function(){
        function cell(v){ v = (v === undefined || v === null) ? '' : String(v); return '"' + v.replace(/"/g, '""') + '"'; }
        var rows = [['SKU', 'Product', 'Unit price', 'Quantity', 'Subtotal']];
        $wrap.find('.soharon-cart-row').each(function(){
            var $r = $(this);
            rows.push([$r.attr('data-sku'), $r.attr('data-name'), $r.attr('data-price'), $r.attr('data-qty'), $r.attr('data-subtotal')]);
        });
        var csv  = '\ufeff' + rows.map(function(r){ return r.map(cell).join(','); }).join('\r\n');
        var blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
        var url  = URL.createObjectURL(blob);
        var link = document.createElement('a');
        link.href = url;
        link.download = 'cart-export.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        setTimeout(function(){ URL.revokeObjectURL(url); }, 1000);
    });
});
JS;
}
