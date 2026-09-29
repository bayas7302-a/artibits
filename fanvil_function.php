<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

if ( !function_exists( 'child_theme_configurator_css' ) ):
    function child_theme_configurator_css() {
        wp_enqueue_style( 'chld_thm_cfg_child', trailingslashit( get_stylesheet_directory_uri() ) . 'style.css', array( 'hello-elementor','hello-elementor-theme-style','hello-elementor-header-footer' ) );
    }
endif;
add_action( 'wp_enqueue_scripts', 'child_theme_configurator_css', 10 );

// END ENQUEUE PARENT ACTION

/* Show "AED" instead of the dirham symbol (د.إ) */
add_filter( 'woocommerce_currency_symbol', function ( $symbol, $currency ) {
	return 'AED' === $currency ? 'AED' : $symbol;
}, 10, 2 );


/**
 * ============================================
 * SOHARON — BRAND SETTINGS (change colours here only)
 * ============================================
 */
define( 'SOHARON_RED', '#DB141D' );
define( 'SOHARON_RED_DARK', '#B30F17' );

function soharon_brand_vars( $css, $extra = array() ) {
    return strtr( $css, array_merge( array(
        '{RED}'      => SOHARON_RED,
        '{RED_DARK}' => SOHARON_RED_DARK,
    ), $extra ) );
}

/**
 * Inline SVG icons (no icon font needed, always render)
 */
function soharon_icon( $name ) {
    $paths = array(
        'refresh'  => '<polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'trash'    => '<polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>',
        'plus'     => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'minus'    => '<line x1="5" y1="12" x2="19" y2="12"/>',
        'close'    => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
    );
    if ( ! isset( $paths[ $name ] ) ) return '';
    return '<svg class="soharon-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/* Poppins font (cart, checkout, account and order tracking) */
function soharon_enqueue_poppins() {
    wp_enqueue_style(
        'soharon-poppins-font',
        'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap',
        array(),
        null
    );
}


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
            <div class="soharon-summary-line">
                <span>VAT</span>
                <span><?php echo wc_price( WC()->cart->get_total_tax() ); ?></span>
            </div>
            <div class="soharon-summary-line soharon-total-line">
                <span>Total</span>
                <span><?php echo wc_price( WC()->cart->get_total( 'edit' ) ); ?></span>
            </div>
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
    margin:0 0 10px; padding:14px 16px; border:1px solid var(--s-border);
    border-radius:14px; background:var(--s-bg); font-size:14px; line-height:1.5;
}
{S} #payment ul.payment_methods li:has(input:checked){ border-color:var(--s-red); background:#fff; }
{S} #payment ul.payment_methods li > label{ display:inline; font-weight:600; cursor:pointer; margin-left:8px; }
{S} #payment ul.payment_methods li img{ max-height:24px; vertical-align:middle; margin:0 4px; }
{S} #payment ul.payment_methods li.woocommerce-info{ padding-left:48px; border:0; border-left:4px solid var(--s-red); background:var(--s-red-soft); }
{S} #payment div.payment_box{ margin:10px 0 0; padding:12px 14px; border-radius:12px; background:var(--s-bg); font-size:13px; color:#555; }
{S} #payment div.payment_box::before{ display:none; }
{S} #payment div.payment_box p:last-child{ margin-bottom:0; }
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

/* ---------- Mobile ---------- */
@media (max-width:900px){
    {S} form.checkout{
        grid-template-columns:1fr; grid-template-rows:auto;
        grid-template-areas:"notice" "details" "heading" "review";
    }
    {S} #customer_details{ margin-bottom:20px; }
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

/**
 * ============================================
 * SOHARON ORDER TRACKING
 * - WooCommerce → Order Tracking: custom order statuses (used by every order),
 *   couriers, and which page holds the tracking shortcode
 * - Order edit screen → "Shipping & tracking" box: courier, tracking number,
 *   estimated delivery date and latest update
 * - My Account → "Track order" tab: pick one of your orders and see its progress
 * - [soharon_track_order] shortcode: enter an order number to see its delivery status.
 *   Link format (fills the field): <tracking page>?order_number=1234
 * Steps: Order Confirmed → Processing → Ready for Dispatch → Shipped → Out for Delivery → Delivered
 * ============================================
 */
define( 'SOHARON_TRACK_ENDPOINT', 'track-order' );
define( 'SOHARON_TRACK_OPTION', 'soharon_track_settings' );

/* The progress steps shown to customers */
function soharon_track_steps() {
    return apply_filters( 'soharon_track_steps', array(
        'Order Confirmed',
        'Processing',
        'Ready for Dispatch',
        'Shipped',
        'Out for Delivery',
        'Delivered',
    ) );
}

/* ----- Settings (one option, created with sensible defaults) ----- */
function soharon_track_settings() {
    static $settings = null;
    if ( null !== $settings ) return $settings;

    $settings = get_option( SOHARON_TRACK_OPTION );
    if ( ! is_array( $settings ) ) {
        $settings = array(
            'page_id'  => 0,
            'statuses' => array(
                'ready-dispatch'   => array( 'label' => 'Ready for Dispatch', 'color' => '#7C3AED', 'step' => 2 ),
                'shipped'          => array( 'label' => 'Shipped', 'color' => '#2563EB', 'step' => 3 ),
                'out-for-delivery' => array( 'label' => 'Out for Delivery', 'color' => '#0891B2', 'step' => 4 ),
                'delivered'        => array( 'label' => 'Delivered', 'color' => '#17804A', 'step' => 5 ),
            ),
            'couriers' => array(),
        );
        update_option( SOHARON_TRACK_OPTION, $settings );
    }
    $settings = wp_parse_args( $settings, array( 'page_id' => 0, 'statuses' => array(), 'couriers' => array() ) );
    return $settings;
}

function soharon_track_custom_statuses() {
    return soharon_track_settings()['statuses'];
}

/* WooCommerce's own statuses (can't be added or removed here) */
function soharon_track_core_statuses() {
    return array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed', 'checkout-draft' );
}

/* ----- Custom order statuses: registered for every order ----- */
add_action( 'init', function () {
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        $count = $s['label'] . ' <span class="count">(%s)</span>';
        register_post_status( 'wc-' . $slug, array(
            'label'                     => $s['label'],
            'public'                    => false,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => array( 0 => $count, 1 => $count, 'singular' => $count, 'plural' => $count, 'context' => null, 'domain' => null ),
        ) );
    }
} );

/* Add them to the Status dropdown, right after "Processing" */
add_filter( 'wc_order_statuses', function ( $statuses ) {
    $custom = array();
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        $custom[ 'wc-' . $slug ] = $s['label'];
    }
    if ( ! $custom ) return $statuses;

    $out = array();
    foreach ( $statuses as $key => $label ) {
        $out[ $key ] = $label;
        if ( 'wc-processing' === $key ) {
            $out   += $custom;
            $custom = array();
        }
    }
    return $out + $custom;
} );

/* Statuses after "Processing" count as paid (reports, stock, downloads) */
add_filter( 'woocommerce_order_is_paid_statuses', function ( $statuses ) {
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        if ( (int) $s['step'] >= 1 ) $statuses[] = $slug;
    }
    return array_unique( $statuses );
} );

/* Orders list: "Change status to …" bulk actions (WooCommerce handles the rest) */
function soharon_track_bulk_actions( $actions ) {
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        $actions[ 'mark_' . $slug ] = 'Change status to ' . strtolower( $s['label'] );
    }
    return $actions;
}
add_filter( 'bulk_actions-edit-shop_order', 'soharon_track_bulk_actions', 30 );
add_filter( 'bulk_actions-woocommerce_page_wc-orders', 'soharon_track_bulk_actions', 30 );

/* Status colours in the admin orders list */
add_action( 'admin_head', function () {
    $css = '';
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        $c    = sanitize_hex_color( $s['color'] ) ?: '#6B7280';
        $css .= '.order-status.status-' . sanitize_html_class( $slug ) . '{background:' . $c . '26;color:' . $c . '}';
    }
    if ( $css ) echo '<style id="soharon-status-colours">' . $css . '</style>'; // phpcs:ignore
} );

/* Status pill colours on the front end, for a given CSS scope */
function soharon_track_status_pill_css( $scope ) {
    $css = '';
    foreach ( soharon_track_custom_statuses() as $slug => $s ) {
        $c    = sanitize_hex_color( $s['color'] ) ?: '#6B7280';
        $css .= $scope . ' .soharon-status--' . sanitize_html_class( $slug ) . '{background:' . $c . '1F;color:' . $c . '}';
    }
    return $css;
}

/* Remember when an order reached each status (for the dates under each step) */
add_action( 'woocommerce_order_status_changed', function ( $order_id, $from, $to, $order ) {
    if ( ! $order instanceof WC_Order ) return;
    $times = $order->get_meta( '_soharon_status_times' );
    $times = is_array( $times ) ? $times : array();
    if ( empty( $times[ $to ] ) ) {
        $times[ $to ] = time();
        $order->update_meta_data( '_soharon_status_times', $times );
        $order->save_meta_data();
    }
}, 10, 4 );

/* ----- Tracking page URL (set in settings, or found automatically) ----- */
function soharon_track_page_url( $order = null ) {
    $page_id = (int) soharon_track_settings()['page_id'];
    if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
        $page_id = (int) get_transient( 'soharon_track_page_auto' );
        if ( ! $page_id || 'publish' !== get_post_status( $page_id ) ) {
            global $wpdb;
            $like    = '%' . $wpdb->esc_like( '[soharon_track_order' ) . '%';
            $page_id = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT p.ID FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_elementor_data'
                 WHERE p.post_type = 'page' AND p.post_status = 'publish' AND ( p.post_content LIKE %s OR m.meta_value LIKE %s )
                 ORDER BY p.ID ASC LIMIT 1",
                $like,
                $like
            ) );
            set_transient( 'soharon_track_page_auto', $page_id, DAY_IN_SECONDS );
        }
    }
    if ( ! $page_id ) return '';

    $url = get_permalink( $page_id );
    if ( $url && $order instanceof WC_Order ) {
        $url = add_query_arg( array( 'order_number' => $order->get_order_number(), 'key' => $order->get_order_key() ), $url );
    }
    return $url;
}

/* ----- Delivery details for an order (our fields, or a Shipment Tracking plugin) ----- */
function soharon_track_courier_link( $courier, $number ) {
    if ( ! $courier || ! $number ) return '';
    foreach ( soharon_track_settings()['couriers'] as $c ) {
        if ( ! empty( $c['url'] ) && 0 === strcasecmp( $c['name'], $courier ) ) {
            return str_replace( '{tracking_number}', rawurlencode( $number ), $c['url'] );
        }
    }
    return '';
}

function soharon_track_delivery( $order ) {
    $d = array(
        'courier'     => (string) $order->get_meta( '_soharon_courier' ),
        'number'      => (string) $order->get_meta( '_soharon_tracking_number' ),
        'link'        => (string) $order->get_meta( '_soharon_tracking_url' ),
        'eta'         => (string) $order->get_meta( '_soharon_eta' ),
        'update'      => (string) $order->get_meta( '_soharon_latest_update' ),
        'update_time' => (int) $order->get_meta( '_soharon_latest_update_time' ),
    );

    // Fallback: WooCommerce Shipment Tracking / Advanced Shipment Tracking
    if ( ! $d['number'] ) {
        $items = $order->get_meta( '_wc_shipment_tracking_items' );
        if ( is_array( $items ) && $items ) {
            $item        = end( $items );
            $d['number'] = isset( $item['tracking_number'] ) ? (string) $item['tracking_number'] : '';
            $d['link']   = $d['link'] ?: ( isset( $item['custom_tracking_link'] ) ? (string) $item['custom_tracking_link'] : '' );
            if ( ! $d['courier'] ) {
                $d['courier'] = ! empty( $item['custom_tracking_provider'] ) ? $item['custom_tracking_provider']
                    : ( ! empty( $item['tracking_provider'] ) ? ucwords( str_replace( array( '-', '_' ), ' ', $item['tracking_provider'] ) ) : '' );
            }
        }
    }
    if ( ! $d['link'] ) {
        $d['link'] = soharon_track_courier_link( $d['courier'], $d['number'] );
    }
    return apply_filters( 'soharon_track_delivery', $d, $order );
}

/* ============================================
 * ADMIN: WooCommerce → Order Tracking
 * ============================================ */
add_action( 'admin_menu', function () {
    add_submenu_page( 'woocommerce', 'Order Tracking', 'Order Tracking', 'manage_woocommerce', 'soharon-order-tracking', 'soharon_track_settings_page' );
}, 60 );

add_action( 'admin_init', 'soharon_track_save_settings' );
function soharon_track_save_settings() {
    if ( empty( $_POST['soharon_track_save'] ) || ! current_user_can( 'manage_woocommerce' ) ) return;
    check_admin_referer( 'soharon_track_settings' );

    $old      = soharon_track_settings();
    $errors   = array();
    $statuses = array();
    $core     = soharon_track_core_statuses();
    $rows     = isset( $_POST['statuses'] ) && is_array( $_POST['statuses'] ) ? wp_unslash( $_POST['statuses'] ) : array();

    foreach ( $rows as $row ) {
        $label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
        $slug  = isset( $row['slug'] ) ? sanitize_key( $row['slug'] ) : '';
        $is_existing = $slug && isset( $old['statuses'][ $slug ] );

        // Remove: only when no orders use the status
        if ( $is_existing && ! empty( $row['delete'] ) ) {
            $in_use = function_exists( 'wc_orders_count' ) ? (int) wc_orders_count( $slug ) : 0;
            if ( $in_use ) {
                $errors[] = sprintf( '"%s" was not removed: %d order(s) still have this status. Move them to another status first.', $old['statuses'][ $slug ]['label'], $in_use );
            } else {
                continue;
            }
        }
        if ( '' === $label ) {
            if ( $is_existing ) $statuses[ $slug ] = $old['statuses'][ $slug ]; // keep, label can't be blank
            continue;
        }

        if ( ! $is_existing ) {
            // New status: slug from the label, max 17 characters (WooCommerce stores "wc-" + slug in 20)
            $full = sanitize_title( $label );
            $base = substr( $full, 0, 17 );
            if ( strlen( $full ) > 17 && '-' !== substr( $full, 17, 1 ) && false !== strrpos( $base, '-' ) ) {
                $base = substr( $base, 0, strrpos( $base, '-' ) ); // don't cut a word in half
            }
            $base = trim( $base, '-' );
            $slug = $base;
            $n    = 2;
            while ( '' === $slug || in_array( $slug, $core, true ) || isset( $statuses[ $slug ] ) || isset( $old['statuses'][ $slug ] ) ) {
                $slug = substr( $base ?: 'status', 0, 15 ) . '-' . $n++;
            }
        }

        $step = isset( $row['step'] ) ? (int) $row['step'] : -1;
        $statuses[ $slug ] = array(
            'label' => $label,
            'color' => sanitize_hex_color( isset( $row['color'] ) ? $row['color'] : '' ) ?: '#6B7280',
            'step'  => max( -1, min( count( soharon_track_steps() ) - 1, $step ) ),
        );
    }

    // Couriers: one per line, "Name | https://tracking-url/{tracking_number}"
    $couriers = array();
    $lines    = isset( $_POST['couriers'] ) ? explode( "\n", sanitize_textarea_field( wp_unslash( $_POST['couriers'] ) ) ) : array();
    foreach ( $lines as $line ) {
        $parts = array_map( 'trim', explode( '|', $line, 2 ) );
        if ( '' === $parts[0] ) continue;
        $couriers[] = array(
            'name' => $parts[0],
            'url'  => isset( $parts[1] ) ? esc_url_raw( $parts[1] ) : '',
        );
    }

    update_option( SOHARON_TRACK_OPTION, array(
        'page_id'  => isset( $_POST['page_id'] ) ? absint( $_POST['page_id'] ) : 0,
        'statuses' => $statuses,
        'couriers' => $couriers,
    ) );
    delete_transient( 'soharon_track_page_auto' );
    if ( $errors ) set_transient( 'soharon_track_errors_' . get_current_user_id(), $errors, 60 );

    wp_safe_redirect( add_query_arg( array( 'page' => 'soharon-order-tracking', 'updated' => 1 ), admin_url( 'admin.php' ) ) );
    exit;
}

function soharon_track_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    $s      = soharon_track_settings();
    $steps  = soharon_track_steps();
    $errors = get_transient( 'soharon_track_errors_' . get_current_user_id() );
    delete_transient( 'soharon_track_errors_' . get_current_user_id() );

    $step_select = function ( $name, $current ) use ( $steps ) {
        $html = '<select name="' . esc_attr( $name ) . '">';
        foreach ( $steps as $i => $label ) {
            $html .= '<option value="' . (int) $i . '"' . selected( (int) $current, $i, false ) . '>' . esc_html( ( $i + 1 ) . '. ' . $label ) . '</option>';
        }
        $html .= '<option value="-1"' . selected( (int) $current, -1, false ) . '>Not on the tracker (e.g. Returned)</option>';
        return $html . '</select>';
    };
    $courier_text = implode( "\n", array_map( function ( $c ) {
        return $c['name'] . ( $c['url'] ? ' | ' . $c['url'] : '' );
    }, $s['couriers'] ) );
    $page_url = soharon_track_page_url();
    ?>
    <div class="wrap">
        <h1>Order Tracking</h1>
        <?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore ?>
            <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
        <?php endif; ?>
        <?php if ( $errors ) : foreach ( (array) $errors as $e ) : ?>
            <div class="notice notice-error"><p><?php echo esc_html( $e ); ?></p></div>
        <?php endforeach; endif; ?>

        <form method="post">
            <?php wp_nonce_field( 'soharon_track_settings' ); ?>

            <h2>Tracking page</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="soharon-page">Page with the tracking form</label></th>
                    <td>
                        <?php wp_dropdown_pages( array(
                            'name'              => 'page_id',
                            'id'                => 'soharon-page',
                            'selected'          => (int) $s['page_id'],
                            'show_option_none'  => '— Find automatically —',
                            'option_none_value' => 0,
                        ) ); ?>
                        <p class="description">
                            The page that contains <code>[soharon_track_order]</code>. Links always use this page's current address, so they keep working if the site moves.
                            <?php if ( $page_url ) : ?>
                                <br>Link format: <code><?php echo esc_html( add_query_arg( 'order_number', '1234', $page_url ) ); ?></code> (fills in the order number)
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h2>Order statuses</h2>
            <p class="description">These appear in the Status dropdown and bulk actions for every order. "Tracker step" is the step customers see highlighted.<br>
            WooCommerce's own statuses stay as they are: Pending / On hold → Order Confirmed, Processing → Processing, Completed → Delivered.</p>
            <table class="widefat striped" style="max-width:900px;margin-top:12px">
                <thead><tr><th>Name</th><th>Slug</th><th>Colour</th><th>Tracker step</th><th>Remove</th></tr></thead>
                <tbody>
                <?php
                $i = 0;
                foreach ( $s['statuses'] as $slug => $st ) : ?>
                    <tr>
                        <td><input type="text" class="regular-text" name="statuses[<?php echo $i; ?>][label]" value="<?php echo esc_attr( $st['label'] ); ?>" required>
                            <input type="hidden" name="statuses[<?php echo $i; ?>][slug]" value="<?php echo esc_attr( $slug ); ?>"></td>
                        <td><code>wc-<?php echo esc_html( $slug ); ?></code></td>
                        <td><input type="color" name="statuses[<?php echo $i; ?>][color]" value="<?php echo esc_attr( $st['color'] ); ?>"></td>
                        <td><?php echo $step_select( "statuses[$i][step]", $st['step'] ); // phpcs:ignore ?></td>
                        <td><label><input type="checkbox" name="statuses[<?php echo $i; ?>][delete]" value="1"> Remove</label></td>
                    </tr>
                <?php $i++; endforeach;
                for ( $n = 0; $n < 2; $n++, $i++ ) : ?>
                    <tr>
                        <td><input type="text" class="regular-text" name="statuses[<?php echo $i; ?>][label]" value="" placeholder="Add a new status…"></td>
                        <td><span class="description">created from the name</span></td>
                        <td><input type="color" name="statuses[<?php echo $i; ?>][color]" value="#6B7280"></td>
                        <td><?php echo $step_select( "statuses[$i][step]", 1 ); // phpcs:ignore ?></td>
                        <td></td>
                    </tr>
                <?php endfor; ?>
                </tbody>
            </table>

            <h2 style="margin-top:28px">Couriers</h2>
            <p class="description">One per line: <code>Courier name | tracking link</code>. Put <code>{tracking_number}</code> where the number goes, and the "Track shipment" link is built automatically.<br>
            Example: <code>Aramex | https://www.aramex.com/track/results?ShipmentNumber={tracking_number}</code></p>
            <textarea name="couriers" rows="6" class="large-text code" style="max-width:900px"><?php echo esc_textarea( $courier_text ); ?></textarea>

            <?php submit_button( 'Save changes', 'primary', 'soharon_track_save' ); ?>
        </form>
    </div>
    <?php
}

/* ============================================
 * ADMIN: order edit screen → "Shipping & tracking" box
 * ============================================ */
add_action( 'add_meta_boxes', function () {
    $screens = array( 'shop_order' );
    if ( function_exists( 'wc_get_page_screen_id' ) ) $screens[] = wc_get_page_screen_id( 'shop-order' ); // HPOS screen
    foreach ( array_unique( $screens ) as $screen ) {
        add_meta_box( 'soharon-tracking', 'Shipping & tracking', 'soharon_track_meta_box', $screen, 'side', 'high' );
    }
} );

function soharon_track_meta_box( $post_or_order ) {
    $order = $post_or_order instanceof WP_Post ? wc_get_order( $post_or_order->ID ) : $post_or_order;
    if ( ! $order instanceof WC_Order ) return;
    $d        = soharon_track_delivery( $order );
    $couriers = soharon_track_settings()['couriers'];
    $link     = soharon_track_page_url( $order );
    $field    = 'display:block;width:100%;margin:2px 0 10px';
    ?>
    <p style="margin-top:0">
        <label for="soharon-courier"><strong>Courier</strong></label>
        <input type="text" id="soharon-courier" name="soharon_courier" list="soharon-couriers" value="<?php echo esc_attr( $order->get_meta( '_soharon_courier' ) ); ?>" style="<?php echo $field; ?>" placeholder="e.g. Aramex">
        <datalist id="soharon-couriers"><?php foreach ( $couriers as $c ) echo '<option value="' . esc_attr( $c['name'] ) . '">'; ?></datalist>

        <label for="soharon-number"><strong>Tracking number</strong></label>
        <input type="text" id="soharon-number" name="soharon_tracking_number" value="<?php echo esc_attr( $order->get_meta( '_soharon_tracking_number' ) ); ?>" style="<?php echo $field; ?>">

        <label for="soharon-url"><strong>Tracking link</strong> <span class="description">(optional)</span></label>
        <input type="url" id="soharon-url" name="soharon_tracking_url" value="<?php echo esc_attr( $order->get_meta( '_soharon_tracking_url' ) ); ?>" style="<?php echo $field; ?>" placeholder="Built from the courier if empty">

        <label for="soharon-eta"><strong>Estimated delivery date</strong></label>
        <input type="date" id="soharon-eta" name="soharon_eta" value="<?php echo esc_attr( $order->get_meta( '_soharon_eta' ) ); ?>" style="<?php echo $field; ?>">

        <label for="soharon-update"><strong>Latest update</strong></label>
        <textarea id="soharon-update" name="soharon_latest_update" rows="3" style="<?php echo $field; ?>" placeholder="e.g. Arrived at Dubai hub"><?php echo esc_textarea( $order->get_meta( '_soharon_latest_update' ) ); ?></textarea>
        <?php if ( $d['update_time'] ) : ?>
            <span class="description">Updated <?php echo esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), $d['update_time'] ) ); ?></span>
        <?php endif; ?>
    </p>
    <?php if ( $link ) : ?>
        <p>
            <label for="soharon-link"><strong>Customer tracking link</strong></label>
            <input type="text" id="soharon-link" readonly value="<?php echo esc_attr( $link ); ?>" onclick="this.select()" style="<?php echo $field; ?>">
            <span class="description">Opens this order's tracking with full details (items and address).</span>
        </p>
    <?php endif; ?>
    <p class="description">Shown on the tracking page and in My Account → Track order.
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=soharon-order-tracking' ) ); ?>">Statuses &amp; couriers</a></p>
    <?php
}

/* Saved together with the order (WooCommerce checks its own nonce before this runs).
   Priority 5: before WooCommerce saves the status (40), so status emails already include the tracking details. */
add_action( 'woocommerce_process_shop_order_meta', function ( $order_id ) {
    if ( ! isset( $_POST['soharon_tracking_number'] ) ) return; // phpcs:ignore
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // phpcs:disable WordPress.Security.NonceVerification
    $update = sanitize_textarea_field( wp_unslash( $_POST['soharon_latest_update'] ?? '' ) );
    $eta    = sanitize_text_field( wp_unslash( $_POST['soharon_eta'] ?? '' ) );
    $order->update_meta_data( '_soharon_courier', sanitize_text_field( wp_unslash( $_POST['soharon_courier'] ?? '' ) ) );
    $order->update_meta_data( '_soharon_tracking_number', sanitize_text_field( wp_unslash( $_POST['soharon_tracking_number'] ?? '' ) ) );
    $order->update_meta_data( '_soharon_tracking_url', esc_url_raw( wp_unslash( $_POST['soharon_tracking_url'] ?? '' ) ) );
    $order->update_meta_data( '_soharon_eta', preg_match( '/^\d{4}-\d{2}-\d{2}$/', $eta ) ? $eta : '' );
    // phpcs:enable
    if ( $update !== (string) $order->get_meta( '_soharon_latest_update' ) ) {
        $order->update_meta_data( '_soharon_latest_update', $update );
        $order->update_meta_data( '_soharon_latest_update_time', $update ? time() : 0 );
    }
    $order->save();
}, 5 );

/* ============================================
 * FRONT END: My Account → Track order
 * ============================================ */

/* Register /my-account/track-order/ as a WooCommerce account endpoint */
add_filter( 'woocommerce_get_query_vars', function ( $vars ) {
    $vars[ SOHARON_TRACK_ENDPOINT ] = SOHARON_TRACK_ENDPOINT;
    return $vars;
} );
add_action( 'init', function () {
    if ( get_option( 'soharon_track_endpoint' ) !== SOHARON_TRACK_ENDPOINT ) {
        flush_rewrite_rules( false ); // one time only, same as re-saving Settings → Permalinks
        update_option( 'soharon_track_endpoint', SOHARON_TRACK_ENDPOINT );
    }
}, 99 );

/* Menu tab, placed right after "Orders" */
add_filter( 'woocommerce_account_menu_items', 'soharon_track_menu_item', 100 );
function soharon_track_menu_item( $items ) {
    $out = array();
    foreach ( $items as $key => $label ) {
        if ( 'customer-logout' === $key && ! isset( $out[ SOHARON_TRACK_ENDPOINT ] ) ) {
            $out[ SOHARON_TRACK_ENDPOINT ] = 'Track order';
        }
        $out[ $key ] = $label;
        if ( 'orders' === $key ) {
            $out[ SOHARON_TRACK_ENDPOINT ] = 'Track order';
        }
    }
    if ( ! isset( $out[ SOHARON_TRACK_ENDPOINT ] ) ) {
        $out[ SOHARON_TRACK_ENDPOINT ] = 'Track order';
    }
    return $out;
}
add_filter( 'woocommerce_endpoint_' . SOHARON_TRACK_ENDPOINT . '_title', function () {
    return 'Track order';
} );

/* "Track" button next to "View" in the Orders list */
add_filter( 'woocommerce_my_account_my_orders_actions', function ( $actions, $order ) {
    $actions['track'] = array(
        'url'  => soharon_track_tab_url( $order->get_id() ),
        'name' => 'Track',
    );
    return $actions;
}, 10, 2 );

function soharon_track_tab_url( $order_id = 0 ) {
    $url = wc_get_account_endpoint_url( SOHARON_TRACK_ENDPOINT );
    return $order_id ? add_query_arg( 'order_id', absint( $order_id ), $url ) : $url;
}

add_action( 'woocommerce_account_' . SOHARON_TRACK_ENDPOINT . '_endpoint', 'soharon_account_track_order_tab' );
function soharon_account_track_order_tab() {
    $user_id = get_current_user_id();
    $orders  = wc_get_orders( array(
        'customer' => $user_id,
        'limit'    => 50,
        'orderby'  => 'date',
        'order'    => 'DESC',
        'type'     => 'shop_order',
        'status'   => array_diff( array_keys( wc_get_order_statuses() ), array( 'wc-checkout-draft' ) ),
    ) );

    echo '<div class="soharon-track">';
    soharon_track_print_css();

    if ( ! $orders ) {
        echo '<div class="soharon-track-empty"><p>You have no orders to track yet.</p>';
        echo '<a class="soharon-track-btn" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Browse products</a></div></div>';
        return;
    }

    // Selected order: ?order_id=… (only if it belongs to this customer), otherwise the newest one
    $selected = $orders[0];
    $wanted   = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
    if ( $wanted ) {
        foreach ( $orders as $o ) {
            if ( $o->get_id() === $wanted ) {
                $selected = $o;
                break;
            }
        }
        if ( $selected->get_id() !== $wanted ) {
            $o = wc_get_order( $wanted );
            if ( $o && (int) $o->get_customer_id() === $user_id ) {
                $selected = $o; // older than the 50 listed
                array_unshift( $orders, $o );
            }
        }
    }

    $action = soharon_track_tab_url();
    ?>
    <form class="soharon-track-form" method="get" action="<?php echo esc_url( $action ); ?>">
        <?php soharon_track_hidden_query_fields( $action ); ?>
        <label for="soharon-track-select">Select an order</label>
        <div class="soharon-track-form__row">
            <select id="soharon-track-select" name="order_id" onchange="this.form.submit()">
                <?php foreach ( $orders as $o ) :
                    $date = $o->get_date_created();
                    printf(
                        '<option value="%d"%s>#%s — %s — %s — %s</option>',
                        $o->get_id(),
                        selected( $o->get_id(), $selected->get_id(), false ),
                        esc_html( $o->get_order_number() ),
                        esc_html( $date ? wc_format_datetime( $date ) : '' ),
                        esc_html( wc_get_order_status_name( $o->get_status() ) ),
                        esc_html( wp_strip_all_tags( wc_price( $o->get_total(), array( 'currency' => $o->get_currency() ) ) ) )
                    );
                endforeach; ?>
            </select>
            <button type="submit" class="soharon-track-btn">Show</button>
        </div>
    </form>
    <?php
    soharon_render_order_tracking( $selected, true );
    echo '</div>';
}

/* Keep ?page_id=… etc. when permalinks are "Plain" (a GET form drops the action's query string) */
function soharon_track_hidden_query_fields( $url ) {
    $query = wp_parse_url( $url, PHP_URL_QUERY );
    if ( ! $query ) return;
    parse_str( $query, $args );
    foreach ( $args as $key => $value ) {
        if ( 'order_id' === $key || is_array( $value ) ) continue;
        printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $key ), esc_attr( $value ) );
    }
}

/* ============================================
 * FRONT END: [soharon_track_order]
 * Only the order number is asked for.
 * ?order_number=1234            → fills in the order number
 * ?order_number=1234&key=wc_…   → shows the full order straight away (link from the order screen)
 * Privacy: with the order number alone, visitors see the delivery status only
 * (tracker, courier, tracking number, estimated delivery, latest update).
 * Items, prices, addresses and notes are shown only to the customer who owns
 * the order (signed in) or when the link contains the order key.
 * ============================================ */
add_shortcode( 'soharon_track_order', 'soharon_track_order_shortcode' );
function soharon_track_order_shortcode( $atts ) {
    if ( ! function_exists( 'WC' ) ) return '';
    $atts = shortcode_atts( array( 'title' => 'Track your order' ), $atts, 'soharon_track_order' );

    soharon_enqueue_poppins();

    // phpcs:disable WordPress.Security.NonceVerification -- read-only lookup, limited details + rate limit
    $posted = isset( $_POST['soharon_track_submit'] );
    $number = '';
    if ( isset( $_POST['soharon_track_number'] ) ) {
        $number = wc_clean( wp_unslash( $_POST['soharon_track_number'] ) );
    } elseif ( isset( $_GET['order_number'] ) ) {
        $number = wc_clean( wp_unslash( $_GET['order_number'] ) );
    }
    $key = ! $posted && isset( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
    // phpcs:enable

    $order = false;
    $error = '';
    $full  = false;
    if ( $posted ) {
        list( $order, $error, $full ) = soharon_track_lookup( $number );
    } elseif ( '' !== $number && ( $key || is_user_logged_in() ) ) {
        // Opened from a link with the order key, or by the signed-in owner: show it straight away
        list( $order, $error, $full ) = soharon_track_lookup( $number, $key );
        if ( ! $full ) {
            $order = false; // otherwise just pre-fill the field
            if ( ! $key ) $error = '';
        }
    }

    ob_start();
    echo '<div class="soharon-track soharon-track--public">';
    soharon_track_print_css();
    ?>
    <form class="soharon-track-lookup" method="post" action="<?php echo esc_url( remove_query_arg( 'key' ) ); ?>#soharon-track-result">
        <?php if ( '' !== trim( $atts['title'] ) ) : ?>
            <h2 class="soharon-track-lookup__title"><?php echo esc_html( $atts['title'] ); ?></h2>
        <?php endif; ?>
        <p class="soharon-track-lookup__sub">Enter your order number to see its status.</p>
        <div class="soharon-track-lookup__fields">
            <p>
                <label for="soharon-track-number">Order number <span class="soharon-track-req" aria-hidden="true">*</span></label>
                <input type="text" id="soharon-track-number" name="soharon_track_number" value="<?php echo esc_attr( $number ); ?>" placeholder="e.g. 1234" inputmode="numeric" autocomplete="off" required>
            </p>
        </div>
        <button type="submit" name="soharon_track_submit" value="1" class="soharon-track-btn">Track order</button>
    </form>
    <div id="soharon-track-result" aria-live="polite">
        <?php
        if ( $error ) {
            echo '<div class="soharon-track-alert">' . esc_html( $error ) . '</div>';
        } elseif ( $order ) {
            $own = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
            soharon_render_order_tracking( $order, $own, $full );
        }
        ?>
    </div>
    <?php
    echo '</div>';
    return ob_get_clean();
}

/**
 * Find an order for the shortcode by its number.
 * Returns array( order|false, error message, full details allowed ).
 * Full details only for the signed-in owner or a matching order key.
 * Lookups are limited per visitor (10 wrong numbers or 30 lookups per 15 minutes)
 * so order numbers can't be scanned in bulk.
 */
function soharon_track_lookup( $number, $key = '' ) {
    $number = ltrim( trim( (string) $number ), '#' );
    if ( '' === $number ) {
        return array( false, 'Please enter your order number.', false );
    }

    $ip       = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
    $rate_key = 'soharon_track_' . md5( $ip );
    $rate     = get_transient( $rate_key );
    $rate     = is_array( $rate ) ? $rate : array( 'fails' => 0, 'total' => 0 );
    if ( $rate['fails'] >= 10 || $rate['total'] >= 30 ) {
        return array( false, 'Too many attempts. Please wait 15 minutes and try again, or contact us for help.', false );
    }

    // Same filter WooCommerce's own tracking form uses, so sequential order number plugins keep working
    $order_id = apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $number );
    $order    = ctype_digit( (string) $order_id ) ? wc_get_order( absint( $order_id ) ) : false;
    $found    = $order && 'shop_order' === $order->get_type() && ! $order->has_status( 'checkout-draft' );

    $rate['total']++;
    if ( ! $found ) $rate['fails']++;
    set_transient( $rate_key, $rate, 15 * MINUTE_IN_SECONDS );

    if ( ! $found ) {
        return array( false, 'We couldn\'t find an order with that number. Please check it and try again.', false );
    }

    $own_order = is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
    $key_match = $key && hash_equals( $order->get_order_key(), $key );
    return array( $order, '', $own_order || $key_match );
}

/* Status → tracker step (-1 = not on the tracker) + friendly message */
function soharon_track_status_info( $order ) {
    $status   = $order->get_status();
    $last     = count( soharon_track_steps() ) - 1;
    $custom   = soharon_track_custom_statuses();
    $messages = array(
        0 => 'Your order is confirmed.',
        1 => 'We\'re preparing your order.',
        2 => 'Your order is packed and ready for dispatch.',
        3 => 'Your order has been shipped.',
        4 => 'Your order is out for delivery.',
        5 => 'Your order has been delivered.',
    );
    $core = array(
        'pending'    => array( 0, 'We\'re waiting for your payment.' ),
        'on-hold'    => array( 0, 'Your order is on hold until we confirm your payment.' ),
        'processing' => array( 1, $messages[1] ),
        'completed'  => array( $last, $messages[5] ),
        'cancelled'  => array( -1, 'This order was cancelled.' ),
        'refunded'   => array( -1, 'This order has been refunded.' ),
        'failed'     => array( -1, 'The payment for this order failed.' ),
    );

    if ( isset( $core[ $status ] ) ) {
        $info = $core[ $status ];
    } elseif ( isset( $custom[ $status ] ) ) {
        $step = (int) $custom[ $status ]['step'];
        $info = array( $step, $step >= 0 && isset( $messages[ $step ] ) ? $messages[ $step ] : sprintf( 'Current status: %s.', $custom[ $status ]['label'] ) );
    } else {
        $info = array( 1, sprintf( 'Current status: %s.', wc_get_order_status_name( $status ) ) );
    }
    return apply_filters( 'soharon_track_status_info', $info, $order );
}

/* Date each step was reached (from recorded status changes) */
function soharon_track_step_dates( $order ) {
    $dates  = array();
    $custom = soharon_track_custom_statuses();
    $last   = count( soharon_track_steps() ) - 1;
    $map    = array( 'pending' => 0, 'on-hold' => 0, 'processing' => 1, 'completed' => $last );
    foreach ( $custom as $slug => $s ) $map[ $slug ] = (int) $s['step'];

    $times = $order->get_meta( '_soharon_status_times' );
    if ( is_array( $times ) ) {
        foreach ( $times as $status => $ts ) {
            if ( ! isset( $map[ $status ] ) || $map[ $status ] < 0 ) continue;
            $step = $map[ $status ];
            if ( empty( $dates[ $step ] ) || $ts < $dates[ $step ] ) $dates[ $step ] = (int) $ts;
        }
    }
    if ( $order->get_date_created() ) $dates[0] = $order->get_date_created()->getTimestamp();
    if ( empty( $dates[1] ) && $order->get_date_paid() ) $dates[1] = $order->get_date_paid()->getTimestamp();
    if ( empty( $dates[ $last ] ) && $order->get_date_completed() ) $dates[ $last ] = $order->get_date_completed()->getTimestamp();
    return $dates;
}

/* The tracking card (used by the account tab and the shortcode).
   $full = false → delivery status only (no items, prices, addresses or notes). */
function soharon_render_order_tracking( $order, $is_owner = false, $full = true ) {
    list( $step, $message ) = soharon_track_status_info( $order );
    $steps    = soharon_track_steps();
    $last     = count( $steps ) - 1;
    $dates    = soharon_track_step_dates( $order );
    $status   = $order->get_status();
    $created  = $order->get_date_created();
    $delivery = soharon_track_delivery( $order );
    $notes    = $full ? $order->get_customer_order_notes() : array();
    $check    = '<svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    $has_delivery = $delivery['courier'] || $delivery['number'] || $delivery['eta'] || $delivery['update'];
    ?>
    <div class="soharon-track-card">
        <div class="soharon-track-head">
            <div>
                <h3>Order #<?php echo esc_html( $order->get_order_number() ); ?></h3>
                <?php if ( $created ) : ?>
                    <p>Placed on <?php echo esc_html( wc_format_datetime( $created ) ); ?></p>
                <?php endif; ?>
            </div>
            <span class="soharon-status soharon-status--<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
        </div>

        <p class="soharon-track-message<?php echo $step < 0 ? ' is-stopped' : ''; ?>"><?php echo esc_html( $message ); ?></p>

        <?php if ( $step >= 0 ) : ?>
            <ol class="soharon-track-steps" style="--soharon-steps:<?php echo (int) count( $steps ); ?>;--soharon-progress:<?php echo (int) $step; ?>">
                <?php foreach ( $steps as $i => $label ) :
                    $state = ( $i < $step || $step === $last ) ? 'is-done' : ( $i === $step ? 'is-current' : '' );
                    ?>
                    <li class="<?php echo esc_attr( $state ); ?>"<?php echo $i === $step ? ' aria-current="step"' : ''; ?>>
                        <span class="soharon-track-dot"><?php echo 'is-done' === $state ? $check : (int) ( $i + 1 ); // phpcs:ignore ?></span>
                        <span class="soharon-track-step-text">
                            <strong><?php echo esc_html( $label ); ?></strong>
                            <?php if ( $i <= $step && ! empty( $dates[ $i ] ) ) : ?>
                                <small><?php echo esc_html( date_i18n( wc_date_format(), $dates[ $i ] ) ); ?></small>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>

        <?php if ( $has_delivery ) : ?>
            <div class="soharon-track-delivery">
                <?php if ( $delivery['courier'] ) : ?>
                    <div><span>Courier</span><strong><?php echo esc_html( $delivery['courier'] ); ?></strong></div>
                <?php endif; ?>
                <?php if ( $delivery['number'] ) : ?>
                    <div><span>Tracking number</span><strong><?php echo esc_html( $delivery['number'] ); ?></strong>
                        <?php if ( $delivery['link'] ) : ?><a href="<?php echo esc_url( $delivery['link'] ); ?>" target="_blank" rel="noopener">Track shipment &rarr;</a><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ( $delivery['eta'] && $step !== $last ) : ?>
                    <div><span>Estimated delivery</span><strong><?php echo esc_html( date_i18n( 'l, ' . wc_date_format(), strtotime( $delivery['eta'] . ' 12:00:00' ) ) ); ?></strong></div>
                <?php endif; ?>
                <?php if ( $delivery['update'] ) : ?>
                    <div class="soharon-track-delivery__update"><span>Latest update<?php echo $delivery['update_time'] ? ' · ' . esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), $delivery['update_time'] ) ) : ''; ?></span>
                        <strong><?php echo nl2br( esc_html( $delivery['update'] ) ); ?></strong></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ( ! $full ) : ?>
            <p class="soharon-track-private">For your privacy, order items and addresses are only shown after you
                <a href="<?php echo esc_url( soharon_track_tab_url( $order->get_id() ) ); ?>">sign in</a>.</p>
        <?php else : ?>
        <dl class="soharon-track-meta">
            <div><dt>Total</dt><dd><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></dd></div>
            <div><dt>Items</dt><dd><?php echo (int) $order->get_item_count(); ?></dd></div>
            <?php if ( $order->get_payment_method_title() ) : ?>
                <div><dt>Payment</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div>
            <?php endif; ?>
            <?php if ( $order->get_shipping_method() ) : ?>
                <div><dt>Delivery</dt><dd><?php echo esc_html( $order->get_shipping_method() ); ?></dd></div>
            <?php endif; ?>
        </dl>

        <?php if ( $notes ) : ?>
            <div class="soharon-track-section">
                <h4>Order updates</h4>
                <ul class="soharon-track-updates">
                    <?php foreach ( $notes as $note ) : ?>
                        <li>
                            <small><?php echo esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $note->comment_date ) ) ); ?></small>
                            <div><?php echo wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ); ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="soharon-track-section">
            <h4>Items</h4>
            <ul class="soharon-track-items">
                <?php foreach ( $order->get_items() as $item ) :
                    if ( ! apply_filters( 'woocommerce_order_item_visible', true, $item ) ) continue;
                    $product = $item->get_product();
                    $image   = $product ? $product->get_image( array( 56, 56 ) ) : wc_placeholder_img( array( 56, 56 ) );
                    ?>
                    <li>
                        <span class="soharon-track-thumb"><?php echo $image; // phpcs:ignore ?></span>
                        <span class="soharon-track-item-name"><?php echo esc_html( $item->get_name() ); ?> <em>&times; <?php echo (int) $item->get_quantity(); ?></em></span>
                        <span class="soharon-track-item-total"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="soharon-track-totals">
                <?php foreach ( $order->get_order_item_totals() as $key => $total ) :
                    if ( 'payment_method' === $key ) continue; ?>
                    <div class="<?php echo 'order_total' === $key ? 'is-grand' : ''; ?>">
                        <span><?php echo wp_kses_post( rtrim( $total['label'], ':' ) ); ?></span>
                        <span><?php echo wp_kses_post( $total['value'] ); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $address = $order->has_shipping_address() ? $order->get_formatted_shipping_address() : $order->get_formatted_billing_address();
        if ( $address ) : ?>
            <div class="soharon-track-section">
                <h4><?php echo $order->has_shipping_address() ? 'Delivery address' : 'Billing address'; ?></h4>
                <address><?php echo wp_kses_post( $address ); ?></address>
            </div>
        <?php endif; ?>

        <?php endif; // $full ?>

        <?php if ( $is_owner ) : ?>
            <div class="soharon-track-actions">
                <a class="soharon-track-btn soharon-track-btn--ghost" href="<?php echo esc_url( $order->get_view_order_url() ); ?>">View full order</a>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/* Tracking styles: self-contained, so the shortcode looks right on any page */
function soharon_track_print_css() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    $css  = <<<'CSS'
.soharon-track{
    --t-red:{RED}; --t-red-dark:{RED_DARK}; --t-red-soft:#FDECEC; --t-green:#17804A; --t-green-soft:#E8F6EE;
    --t-text:#1A1A1A; --t-muted:#8A8A8A; --t-border:#ECECEC; --t-bg:#F7F7F7;
    font-family:'Poppins',sans-serif; color:var(--t-text); font-size:14px; line-height:1.6;
}
.soharon-track *,.soharon-track *::before,.soharon-track *::after{ box-sizing:border-box; }
.soharon-track h2,.soharon-track h3,.soharon-track h4{ margin:0; color:var(--t-text); text-transform:none; line-height:1.3; }
.soharon-track a{ color:var(--t-red); }

/* Buttons */
.soharon-track .soharon-track-btn,.soharon-track button.soharon-track-btn{
    display:inline-flex; align-items:center; justify-content:center; gap:8px; min-height:48px;
    padding:12px 26px; margin:0; border:1px solid var(--t-red); border-radius:999px;
    background:var(--t-red); color:#fff; font:600 14px/1.2 'Poppins',sans-serif;
    text-decoration:none; text-transform:none; box-shadow:none; cursor:pointer; transition:background .2s, color .2s;
}
.soharon-track .soharon-track-btn:hover,.soharon-track .soharon-track-btn:focus-visible{ background:var(--t-red-dark); border-color:var(--t-red-dark); color:#fff; }
.soharon-track .soharon-track-btn--ghost{ background:#fff; color:var(--t-red); }
.soharon-track .soharon-track-btn--ghost:hover{ background:var(--t-red); color:#fff; }

/* Fields */
.soharon-track label{ display:block; margin:0 0 6px; font-size:13px; font-weight:500; color:#555; }
.soharon-track select,.soharon-track input[type=text],.soharon-track input[type=email]{
    width:100%; min-height:48px; padding:12px 16px; margin:0; border:1px solid #E3E3E3; border-radius:14px;
    background:var(--t-bg); color:var(--t-text); font:400 14px/1.4 'Poppins',sans-serif; box-shadow:none;
}
.soharon-track select:focus,.soharon-track input:focus{ outline:none; border-color:var(--t-red); background:#fff; box-shadow:0 0 0 4px rgba(219,20,29,.10); }
.soharon-track-req{ color:var(--t-red); }

/* Account tab: order picker */
.soharon-track-form{ margin:0 0 22px; }
.soharon-track-form__row{ display:flex; gap:10px; }
.soharon-track-form__row select{ flex:1; min-width:0; }

/* Shortcode: lookup form */
.soharon-track-lookup{ max-width:640px; margin:0 auto 24px; padding:28px; background:#fff; border:1px solid var(--t-border); border-radius:24px; }
.soharon-track-lookup__title{ margin:0 0 6px !important; font-size:22px; font-weight:600; }
.soharon-track-lookup__sub{ margin:0 0 20px; color:var(--t-muted); }
.soharon-track-lookup__fields{ display:grid; grid-template-columns:1fr; gap:14px; margin-bottom:18px; }
.soharon-track-lookup__fields p{ margin:0; }
.soharon-track--public .soharon-track-card,.soharon-track--public .soharon-track-alert{ max-width:880px; margin-left:auto; margin-right:auto; }
.soharon-track-alert{ margin:0 0 20px; padding:14px 18px; border-left:4px solid var(--t-red); border-radius:14px; background:var(--t-red-soft); }
.soharon-track-empty{ padding:28px; text-align:center; border:1px dashed var(--t-border); border-radius:20px; }
.soharon-track-empty p{ margin:0 0 14px; color:var(--t-muted); }

/* Card */
.soharon-track-card{ padding:24px; background:#fff; border:1px solid var(--t-border); border-radius:22px; }
.soharon-track-head{ display:flex; justify-content:space-between; align-items:flex-start; gap:12px; }
.soharon-track .soharon-track-head h3{ margin:0; font-size:19px; font-weight:600; }
.soharon-track-head p{ margin:2px 0 0; color:var(--t-muted); font-size:13px; }
.soharon-track-message{ margin:16px 0 0; padding:12px 16px; border-radius:14px; background:var(--t-bg); }
.soharon-track-message.is-stopped{ background:var(--t-red-soft); color:var(--t-red-dark); font-weight:500; }

/* Status pill */
.soharon-track .soharon-status{
    display:inline-flex; align-items:center; gap:6px; padding:4px 12px; border-radius:999px; white-space:nowrap;
    font-size:12px; font-weight:600; line-height:1.4; background:#F1F2F4; color:#4B5563;
}
.soharon-track .soharon-status::before{ content:""; width:6px; height:6px; border-radius:50%; background:currentColor; }
.soharon-track .soharon-status--processing,.soharon-track .soharon-status--on-hold,.soharon-track .soharon-status--pending{ background:#FEF3E2; color:#B45309; }
.soharon-track .soharon-status--completed{ background:var(--t-green-soft); color:var(--t-green); }
.soharon-track .soharon-status--cancelled,.soharon-track .soharon-status--failed,.soharon-track .soharon-status--refunded{ background:var(--t-red-soft); color:var(--t-red); }

/* Progress steps: horizontal on desktop, vertical on phones */
.soharon-track-steps{
    --n:var(--soharon-steps,6); --p:var(--soharon-progress,0);
    position:relative; display:grid; grid-template-columns:repeat(var(--n),minmax(0,1fr));
    margin:26px 0 8px; padding:0; list-style:none;
}
.soharon-track-steps::before,.soharon-track-steps::after{
    content:""; position:absolute; top:17px; left:calc(100% / var(--n) / 2); height:3px; border-radius:3px;
    width:calc(100% - 100% / var(--n)); background:var(--t-border);
}
.soharon-track-steps::after{ width:calc((100% - 100% / var(--n)) * var(--p) / (var(--n) - 1)); background:var(--t-green); }
.soharon-track-steps li{ position:relative; z-index:1; display:flex; flex-direction:column; align-items:center; gap:6px; margin:0; padding:0 4px; text-align:center; }
.soharon-track-step-text{ display:flex; flex-direction:column; }
.soharon-track-dot{
    display:flex; align-items:center; justify-content:center; flex:0 0 36px; width:36px; height:36px; border-radius:50%;
    border:3px solid var(--t-border); background:#fff; color:var(--t-muted); font-size:13px; font-weight:600;
}
.soharon-track-steps li.is-done .soharon-track-dot{ border-color:var(--t-green); background:var(--t-green); color:#fff; }
.soharon-track-steps li.is-current .soharon-track-dot{ border-color:var(--t-green); background:var(--t-green-soft); color:var(--t-green); box-shadow:0 0 0 5px rgba(23,128,74,.12); }
.soharon-track-steps li.is-current strong{ color:var(--t-green); }
.soharon-track-steps strong{ font-size:12.5px; font-weight:600; line-height:1.3; }
.soharon-track-steps li:not(.is-done):not(.is-current) strong{ color:var(--t-muted); font-weight:500; }
.soharon-track-steps small{ font-size:11.5px; color:var(--t-muted); }

/* Courier / tracking number / ETA / latest update */
.soharon-track-delivery{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; margin:20px 0 0; padding:16px; border:1px solid var(--t-border); border-radius:16px; }
.soharon-track-delivery > div{ display:flex; flex-direction:column; gap:2px; min-width:0; }
.soharon-track-delivery span{ font-size:12px; color:var(--t-muted); }
.soharon-track-delivery strong{ font-weight:600; overflow-wrap:anywhere; }
.soharon-track-delivery a{ font-size:13px; font-weight:600; }
.soharon-track-delivery__update{ grid-column:1 / -1; padding-top:10px; border-top:1px solid var(--t-border); }
.soharon-track-delivery__update strong{ font-weight:500; }

/* Summary strip */
.soharon-track-meta{ display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:10px; margin:20px 0 0; }
.soharon-track-meta div{ padding:12px 14px; border-radius:14px; background:var(--t-bg); }
.soharon-track-meta dt{ font-size:12px; color:var(--t-muted); }
.soharon-track-meta dd{ margin:2px 0 0; font-weight:600; }

/* Sections */
.soharon-track-section{ margin-top:22px; padding-top:20px; border-top:1px solid var(--t-border); }
.soharon-track-section h4{ margin:0 0 12px; font-size:15px; font-weight:600; }
.soharon-track ul.soharon-track-items,.soharon-track ul.soharon-track-updates{ list-style:none; margin:0; padding:0; }
.soharon-track-items li{ display:flex; align-items:center; gap:12px; margin:0; padding:10px 0; border-bottom:1px solid var(--t-border); }
.soharon-track-thumb img{ display:block; width:56px; height:56px; object-fit:contain; border-radius:12px; background:var(--t-bg); }
.soharon-track-item-name{ flex:1; min-width:0; font-weight:500; }
.soharon-track-item-name em{ font-style:normal; color:var(--t-muted); font-weight:400; }
.soharon-track-item-total{ font-weight:600; white-space:nowrap; }
.soharon-track-totals div{ display:flex; justify-content:space-between; gap:12px; padding:6px 0; color:#555; }
.soharon-track-totals div.is-grand{ padding-top:10px; font-size:16px; font-weight:700; color:var(--t-text); }
.soharon-track-totals div.is-grand .woocommerce-Price-amount{ color:var(--t-red); }
.soharon-track-updates li{ position:relative; margin:0; padding:0 0 14px 20px; border-left:2px solid var(--t-border); }
.soharon-track-updates li::before{ content:""; position:absolute; top:5px; left:-6px; width:10px; height:10px; border-radius:50%; background:var(--t-red); }
.soharon-track-updates li:last-child{ padding-bottom:0; }
.soharon-track-updates small{ display:block; color:var(--t-muted); font-size:12px; }
.soharon-track-updates p{ margin:2px 0 0; }
.soharon-track address{ margin:0; padding:0; border:0; font-style:normal; line-height:1.7; color:#555; }
.soharon-track-actions{ margin-top:22px; }
.soharon-track-private{ margin:20px 0 0; padding:12px 16px; border-radius:14px; background:var(--t-bg); color:#555; font-size:13px; }

@media (max-width:700px){
    .soharon-track-steps{ grid-template-columns:1fr; gap:0; }
    .soharon-track-steps::before,.soharon-track-steps::after{ top:26px; left:17px; width:3px; height:calc(100% - 52px); }
    .soharon-track-steps::after{ width:3px; height:calc((100% - 52px) * var(--p) / (var(--n) - 1)); }
    .soharon-track-steps li{ flex-direction:row; align-items:center; gap:14px; min-height:52px; padding:0; text-align:left; }
    .soharon-track-steps strong{ font-size:14px; }
    .soharon-track-steps small{ font-size:12px; }
}
@media (max-width:600px){
    .soharon-track-card,.soharon-track-lookup{ padding:18px; border-radius:18px; }
    .soharon-track-lookup__fields{ grid-template-columns:1fr; }
    .soharon-track-form__row{ flex-direction:column; }
    .soharon-track-head{ flex-direction:column; }
}
CSS;
    echo '<style id="soharon-track-css">' . soharon_brand_vars( $css ) . soharon_track_status_pill_css( '.soharon-track' ) . '</style>'; // phpcs:ignore
}


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
		$fvs_cat_cache = get_transient( 'fvs_cat_info' );
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
		'tax_query'      => array( array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => $term->term_id,
			'include_children' => true,
		) ),
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
		set_transient( 'fvs_cat_info', $fvs_cat_cache, 12 * HOUR_IN_SECONDS );
	}
}

/* Clear the category cache when products or categories change */
function fvs_clear_cat_cache() {
	global $fvs_cat_cache;
	$fvs_cat_cache = null;
	remove_action( 'shutdown', 'fvs_save_cat_cache' );
	delete_transient( 'fvs_cat_info' );
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

/* Promotions page -------------------------------------------------------- */
function fvs_render_promotion_archive( $show_title ) {
	$base    = fvs_promo_url();
	$current = fvs_promo_current_cat();
	$top     = $current ? fvs_top_ancestor( $current ) : null;
	$in      = fvs_promo_category_ids();
	$has     = function ( $term ) use ( $in ) {
		return in_array( (int) $term->term_id, $in, true );
	};

	echo '<div class="fvs fvs--promo">';

	/* Main categories: "All" + categories that have promoted products */
	$cats = array_values( array_filter( fvs_top_categories(), $has ) );
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
		$subs = array_values( array_filter( fvs_sub_categories( $top ), $has ) );
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
	if ( 'instock' === $status ) {
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
		),
	) ) );
}

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

/* ===========================================================================
 * EVENTS – post type "Events" + events page
 * - Admin: Events → Add New: title, image (Featured image), description (editor)
 *   and an "Event details" box: location, start date, end date, time.
 * - Page: /events/ (and the [fanvil_events] shortcode on any page, options title,
 *   subtitle, limit, past="no"): page title + subtitle, Upcoming / Past tabs,
 *   cards 3 per row on desktop, 2 on tablet, 1 on phones (soonest first).
 *   Only the title is required; empty date/time/location are not shown.
 *   Clicking a card opens a popup with the full description.
 * - An event's own link (/events/name/) opens the events page with its popup.
 * ======================================================================== */
define( 'FVE_TYPE', 'fanvil_event' );

add_action( 'init', function () {
	register_post_type( FVE_TYPE, array(
		'labels'        => array(
			'name'               => 'Events',
			'singular_name'      => 'Event',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Event',
			'edit_item'          => 'Edit Event',
			'new_item'           => 'New Event',
			'view_item'          => 'View Event',
			'search_items'       => 'Search Events',
			'not_found'          => 'No events found',
			'not_found_in_trash' => 'No events found in Trash',
			'all_items'          => 'All Events',
			'menu_name'          => 'Events',
		),
		'public'        => true,
		'has_archive'   => 'events',
		'rewrite'       => array( 'slug' => 'events', 'with_front' => false ),
		'menu_icon'     => 'dashicons-calendar-alt',
		'menu_position' => 25,
		'supports'      => array( 'title', 'editor', 'thumbnail' ),
		'show_in_rest'  => false, // classic editor: description + "Event details" box on one screen
	) );
} );

/* Register the /events/ address once (same as re-saving Settings → Permalinks) */
add_action( 'init', function () {
	if ( '1' === get_option( 'fve_setup' ) ) return;
	flush_rewrite_rules( false );
	update_option( 'fve_setup', '1' );
}, 99 );

/* Featured image is called "Event image" */
add_filter( 'admin_post_thumbnail_html', function ( $html, $post_id ) {
	return FVE_TYPE === get_post_type( $post_id ) ? str_replace( array( 'Set featured image', 'Remove featured image' ), array( 'Set event image', 'Remove event image' ), $html ) : $html;
}, 10, 2 );

/* ----- Event details box ----- */
function fve_fields() {
	return array( '_fve_location', '_fve_start', '_fve_end', '_fve_time' );
}

add_action( 'add_meta_boxes_' . FVE_TYPE, function () {
	add_meta_box( 'fve-details', 'Event details', 'fve_details_box', FVE_TYPE, 'normal', 'high' );
} );

function fve_details_box( $post ) {
	wp_nonce_field( 'fve_save', 'fve_nonce' );
	$v = array();
	foreach ( fve_fields() as $key ) {
		$v[ $key ] = get_post_meta( $post->ID, $key, true );
	}
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="fve-location">Location</label></th>
			<td><input type="text" id="fve-location" name="fve_location" class="large-text" value="<?php echo esc_attr( $v['_fve_location'] ); ?>" placeholder="e.g. Hall 5 | Booth 535 H, Dubai World Trade Centre">
				<p class="description">Optional.</p></td>
		</tr>
		<tr>
			<th scope="row"><label for="fve-start">Start date</label></th>
			<td><input type="date" id="fve-start" name="fve_start" value="<?php echo esc_attr( $v['_fve_start'] ); ?>">
				<p class="description">Optional.</p></td>
		</tr>
		<tr>
			<th scope="row"><label for="fve-end">End date</label></th>
			<td><input type="date" id="fve-end" name="fve_end" value="<?php echo esc_attr( $v['_fve_end'] ); ?>">
				<p class="description">Optional – leave empty for a one-day event.</p></td>
		</tr>
		<tr>
			<th scope="row"><label for="fve-time">Time</label></th>
			<td><input type="text" id="fve-time" name="fve_time" class="regular-text" value="<?php echo esc_attr( $v['_fve_time'] ); ?>" placeholder="e.g. 10:00 – 18:00">
				<p class="description">Optional.</p></td>
		</tr>
	</table>
	<p class="description">Only the <strong>title</strong> is needed. Empty fields are simply not shown. The <strong>event image</strong> (right-hand column) and <strong>description</strong> (editor above) appear on the events page; the description opens in a popup. Events without a date are listed after the dated upcoming events.</p>
	<?php
}

add_action( 'save_post_' . FVE_TYPE, function ( $post_id ) {
	if ( ! isset( $_POST['fve_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['fve_nonce'] ) ), 'fve_save' ) ) return;
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) return;

	$date  = function ( $key ) {
		$d = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ? $d : '';
	};
	$start = $date( 'fve_start' );
	$end   = $date( 'fve_end' );
	if ( $end && $start && $end < $start ) $end = ''; // end before start → one-day event

	update_post_meta( $post_id, '_fve_location', sanitize_text_field( wp_unslash( $_POST['fve_location'] ?? '' ) ) );
	update_post_meta( $post_id, '_fve_start', $start );
	update_post_meta( $post_id, '_fve_end', $end );
	update_post_meta( $post_id, '_fve_time', sanitize_text_field( wp_unslash( $_POST['fve_time'] ?? '' ) ) );
} );

/* Admin list: date and location columns, sorted by event date */
add_filter( 'manage_' . FVE_TYPE . '_posts_columns', function ( $cols ) {
	$out = array();
	foreach ( $cols as $k => $label ) {
		if ( 'date' === $k ) continue;
		$out[ $k ] = $label;
		if ( 'title' === $k ) {
			$out['fve_date']     = 'Event date';
			$out['fve_location'] = 'Location';
		}
	}
	return $out;
} );
add_action( 'manage_' . FVE_TYPE . '_posts_custom_column', function ( $col, $post_id ) {
	if ( 'fve_date' === $col ) echo esc_html( fve_date_label( $post_id, true ) );
	if ( 'fve_location' === $col ) echo esc_html( get_post_meta( $post_id, '_fve_location', true ) );
}, 10, 2 );
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() && $q->is_main_query() && FVE_TYPE === $q->get( 'post_type' ) && ! $q->get( 'orderby' ) ) {
		$q->set( 'meta_key', '_fve_start' );
		$q->set( 'orderby', 'meta_value' );
		$q->set( 'order', 'DESC' );
	}
} );

/* "December 7–11", "Nov 30 – Dec 2", "February 16" (+ year when not this year) */
function fve_date_label( $post_id, $always_year = false ) {
	$start = get_post_meta( $post_id, '_fve_start', true );
	$end   = get_post_meta( $post_id, '_fve_end', true );
	if ( ! $start ) return '';
	$s    = strtotime( $start . ' 12:00:00' );
	$e    = $end ? strtotime( $end . ' 12:00:00' ) : $s;
	$year = ( $always_year || wp_date( 'Y', $e ) !== wp_date( 'Y' ) ) ? ', ' . date_i18n( 'Y', $e ) : '';

	if ( $end === $start || ! $end ) return date_i18n( 'F j', $s ) . $year;
	if ( date_i18n( 'Y-m', $s ) === date_i18n( 'Y-m', $e ) ) return date_i18n( 'F j', $s ) . '–' . date_i18n( 'j', $e ) . $year;
	if ( date_i18n( 'Y', $s ) === date_i18n( 'Y', $e ) ) return date_i18n( 'M j', $s ) . ' – ' . date_i18n( 'M j', $e ) . $year;
	return date_i18n( 'M j, Y', $s ) . ' – ' . date_i18n( 'M j, Y', $e );
}

/* Upcoming (soonest first) then past (most recent first) */
function fve_get_events( $limit = -1, $show_past = true ) {
	$today = wp_date( 'Y-m-d' );
	$ids   = get_posts( array(
		'post_type'      => FVE_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
	) );
	$up = $past = array();
	foreach ( $ids as $id ) {
		$start = get_post_meta( $id, '_fve_start', true );
		$last  = get_post_meta( $id, '_fve_end', true ) ?: $start;
		if ( $last && $last < $today ) {
			$past[ $id ] = $start;
		} else {
			$up[ $id ] = $start ? $start : '9999-12-31'; // no date yet → last of the upcoming
		}
	}
	asort( $up );
	arsort( $past );
	$list = array_keys( $up );
	if ( $show_past ) $list = array_merge( $list, array_keys( $past ) );
	return $limit > 0 ? array_slice( $list, 0, $limit ) : $list;
}

/* ----- [fanvil_events title="Events" limit="" past="yes"] ----- */
add_shortcode( 'fanvil_events', function ( $atts ) {
	return fvs_safe_render( 'fve_render_events', $atts );
} );

function fve_render_events( $atts ) {
	$atts  = shortcode_atts( array(
		'title'    => 'Events',
		'subtitle' => 'Meet the Fanvil team at exhibitions, roadshows and partner events.',
		'limit'    => 0,
		'past'     => 'yes',
	), $atts, 'fanvil_events' );
	$ids   = fve_get_events( (int) $atts['limit'], 'no' !== strtolower( $atts['past'] ) );
	$today = wp_date( 'Y-m-d' );

	// Upcoming / past split (for the tabs)
	$past_ids = array();
	foreach ( $ids as $id ) {
		$last = get_post_meta( $id, '_fve_end', true ) ?: get_post_meta( $id, '_fve_start', true );
		if ( $last && $last < $today ) $past_ids[ $id ] = true;
	}
	$n_past = count( $past_ids );
	$n_up   = count( $ids ) - $n_past;
	$tabs   = $n_up && $n_past; // tabs only when there is something in both

	ob_start();
	fve_print_assets();
	echo '<div class="fve">';

	echo '<header class="fve-head' . ( $tabs ? ' has-tabs' : '' ) . '">';
	if ( '' !== trim( $atts['title'] ) ) {
		echo '<h1 class="fve-head__title">' . esc_html( $atts['title'] ) . '</h1>';
	}
	if ( '' !== trim( $atts['subtitle'] ) ) {
		echo '<p class="fve-head__sub">' . esc_html( $atts['subtitle'] ) . '</p>';
	}
	if ( $tabs ) {
		echo '<div class="fve-tabs" role="tablist" aria-label="Show events">';
		printf( '<button type="button" role="tab" class="fve-tab is-active" aria-selected="true" data-fve-filter="upcoming">Upcoming <span class="fve-tab__n">%d</span></button>', (int) $n_up );
		printf( '<button type="button" role="tab" class="fve-tab" aria-selected="false" data-fve-filter="past">Past <span class="fve-tab__n">%d</span></button>', (int) $n_past );
		echo '</div>';
	}
	echo '</header>';

	if ( ! $ids ) {
		echo '<p class="fve-empty">There are no events at the moment. Please check back soon.</p></div>';
		return ob_get_clean();
	}

	echo '<ul class="fve-grid">';
	foreach ( $ids as $id ) {
		$title    = get_post_field( 'post_title', $id );
		$location = get_post_meta( $id, '_fve_location', true );
		$time     = get_post_meta( $id, '_fve_time', true );
		$date     = fve_date_label( $id );
		$is_past  = isset( $past_ids[ $id ] );
		$slug     = get_post_field( 'post_name', $id );
		$img      = get_post_thumbnail_id( $id );
		$content  = get_post_field( 'post_content', $id );
		?>
		<li class="fve-item" data-fve-when="<?php echo $is_past ? 'past' : 'upcoming'; ?>"<?php echo ( $tabs && $is_past ) ? ' hidden' : ''; ?>>
			<button type="button" class="fve-card" data-fve-open="fve-<?php echo esc_attr( $slug ); ?>" aria-haspopup="dialog">
				<?php if ( $is_past && ! $tabs ) : ?><span class="fve-tag">Past event</span><?php endif; ?>
				<span class="fve-card__title"><?php echo esc_html( $title ); ?></span>
				<?php if ( $location ) : ?><span class="fve-card__loc"><?php echo esc_html( $location ); ?></span><?php endif; ?>
				<?php if ( $date ) : ?><span class="fve-card__date"><?php echo esc_html( $date ); ?></span><?php endif; ?>
				<?php if ( $img ) : ?>
					<span class="fve-card__media"><?php echo wp_get_attachment_image( $img, 'large', false, array( 'class' => 'fve-card__img', 'loading' => 'lazy', 'alt' => $title ) ); ?></span>
				<?php endif; ?>
				<span class="fve-card__more">View details <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
			</button>

			<template id="fve-<?php echo esc_attr( $slug ); ?>">
				<?php if ( $img ) : ?>
					<div class="fve-pop__media"><?php echo wp_get_attachment_image( $img, 'large', false, array( 'alt' => $title ) ); ?></div>
				<?php endif; ?>
				<div class="fve-pop__body">
					<h2 class="fve-pop__title"><?php echo esc_html( $title ); ?></h2>
					<ul class="fve-pop__meta">
						<?php if ( $date ) : ?><li><span>Date</span><strong><?php echo esc_html( $date ); ?></strong></li><?php endif; ?>
						<?php if ( $time ) : ?><li><span>Time</span><strong><?php echo esc_html( $time ); ?></strong></li><?php endif; ?>
						<?php if ( $location ) : ?><li><span>Location</span><strong><?php echo esc_html( $location ); ?></strong></li><?php endif; ?>
					</ul>
					<?php if ( trim( $content ) ) : ?>
						<div class="fve-pop__desc"><?php echo wp_kses_post( wpautop( do_shortcode( $content ) ) ); ?></div>
					<?php endif; ?>
				</div>
			</template>
		</li>
		<?php
	}
	echo '</ul>';
	?>
	<dialog class="fve-pop" aria-label="Event details">
		<button type="button" class="fve-pop__close" aria-label="Close">&times;</button>
		<div class="fve-pop__inner"></div>
	</dialog>
	<?php
	echo '</div>';
	return ob_get_clean();
}

/* /events/ uses the site template file; an event's own link opens its popup there */
add_filter( 'template_include', function ( $template ) {
	if ( ! is_post_type_archive( FVE_TYPE ) ) return $template;
	$file = get_stylesheet_directory() . '/fanvil-woocommerce.php';
	return file_exists( $file ) ? $file : $template;
}, 99 );
add_action( 'template_redirect', function () {
	if ( is_singular( FVE_TYPE ) ) {
		wp_safe_redirect( get_post_type_archive_link( FVE_TYPE ) . '#fve-' . get_post_field( 'post_name', get_queried_object_id() ), 301 );
		exit;
	}
} );
add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_post_type_archive( FVE_TYPE ) ) $parts['title'] = 'Events';
	return $parts;
} );

/* ----- Styles + popup script (printed once) ----- */
function fve_print_assets() {
	static $done = false;
	if ( $done ) return;
	$done = true;
	$red  = defined( 'SOHARON_RED' ) ? SOHARON_RED : '#DB141D';
	?>
<style id="fve-css">
.fve{--fve-red:<?php echo esc_attr( $red ); ?>;--fve-ink:#1A1D21;--fve-muted:#6B7280;--fve-card:#F1F2F4;font-family:'Poppins',sans-serif;color:var(--fve-ink);padding:10px 0 40px}
.fve *{box-sizing:border-box}
.fve-head{margin:0 0 30px;padding:6px 0 26px;border-bottom:1px solid #E6E8EB}
.fve-head.has-tabs{padding-bottom:0}
.fve .fve-head__title{position:relative;margin:0;padding:18px 0 0;font-size:34px;font-weight:700;line-height:1.2;color:var(--fve-ink);text-transform:none;letter-spacing:-.01em}
.fve .fve-head__title::before{content:"";position:absolute;top:0;left:0;width:44px;height:4px;border-radius:2px;background:var(--fve-red)}
.fve .fve-head__sub{margin:8px 0 0;max-width:640px;font-size:15px;line-height:1.6;color:var(--fve-muted)}
.fve-tabs{display:flex;gap:32px;margin-top:20px}
.fve .fve-tab,.fve .fve-tab:hover,.fve .fve-tab:focus{position:relative;display:inline-flex;align-items:center;gap:8px;margin:0;padding:14px 0;border:0;border-radius:0;background:transparent;color:var(--fve-muted);font:500 15px/1.2 'Poppins',sans-serif;text-transform:none;letter-spacing:normal;box-shadow:none;cursor:pointer}
.fve .fve-tab:hover{color:var(--fve-ink)}
.fve .fve-tab.is-active{color:var(--fve-ink);font-weight:600}
.fve .fve-tab.is-active::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:3px;border-radius:3px 3px 0 0;background:var(--fve-red)}
.fve .fve-tab:focus-visible{outline:2px solid var(--fve-red);outline-offset:2px}
.fve-tab__n{display:inline-flex;align-items:center;justify-content:center;min-width:22px;height:22px;padding:0 7px;border-radius:999px;background:#F1F2F4;color:var(--fve-muted);font-size:12px;font-weight:600}
.fve-tab.is-active .fve-tab__n{background:#FDECEC;color:var(--fve-red)}
.fve-item[hidden]{display:none}
.fve .fve-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:32px;margin:0;padding:0;list-style:none}
.fve-item{margin:0;min-width:0}
.fve .fve-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;width:100%;height:100%;margin:0;padding:26px 24px 20px;border:0;border-radius:18px;background:var(--fve-card);color:var(--fve-ink);font:inherit;text-align:center;text-transform:none;letter-spacing:normal;cursor:pointer;box-shadow:none;transition:transform .2s,box-shadow .2s}
.fve .fve-card:hover,.fve .fve-card:focus-visible{background:var(--fve-card);color:var(--fve-ink);transform:translateY(-3px);box-shadow:0 14px 30px rgba(0,0,0,.08)}
.fve .fve-card:focus-visible{outline:3px solid var(--fve-red);outline-offset:3px}
.fve-card__title{font-size:22px;font-weight:700;line-height:1.25;text-transform:uppercase}
.fve-card__loc{font-size:18px;line-height:1.35}
.fve-card__date{font-size:18px;font-weight:500;color:var(--fve-red)}
.fve-card__media{display:block;width:100%;height:320px;margin:16px 0 20px;border-radius:12px;overflow:hidden;background:#E6E8EB}
.fve .fve-card__img{display:block;width:100% !important;height:100% !important;max-width:none;max-height:none;margin:0;border-radius:0;object-fit:cover;object-position:center;transition:transform .35s ease}
.fve .fve-card:hover .fve-card__img{transform:scale(1.03)}
.fve-card__more{display:inline-flex;align-items:center;gap:8px;margin-top:auto;padding:11px 24px;border:1.5px solid var(--fve-red);border-radius:999px;background:#fff;color:var(--fve-red);font-size:14px;font-weight:600;line-height:1.2;transition:background .2s,color .2s}
.fve-card__more svg{display:block;transition:transform .2s}
.fve .fve-card:hover .fve-card__more,.fve .fve-card:focus-visible .fve-card__more{background:var(--fve-red);color:#fff}
.fve .fve-card:hover .fve-card__more svg{transform:translateX(3px)}
.fve-tag{position:absolute;top:14px;left:14px;padding:3px 10px;border-radius:999px;background:#fff;color:var(--fve-muted);font-size:12px;font-weight:600}
.fve-empty{padding:40px 16px;text-align:center;color:var(--fve-muted)}

/* Popup */
.fve-pop{width:min(860px,calc(100vw - 32px));max-height:calc(100vh - 48px);margin:auto;padding:0;border:0;border-radius:20px;background:#fff;color:#1A1D21;font-family:'Poppins',sans-serif;box-shadow:0 30px 80px rgba(0,0,0,.25);overflow:auto}
.fve-pop::backdrop{background:rgba(17,17,17,.6)}
.fve-pop__inner{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr)}
.fve-pop__media{position:relative;min-height:420px;background:#E6E8EB;overflow:hidden}
.fve-pop__media img{position:absolute;inset:0;display:block;width:100% !important;height:100% !important;max-width:none;margin:0;object-fit:cover;object-position:center}
.fve-pop__body{padding:36px 32px 32px}
.fve-pop .fve-pop__title{margin:0 0 18px;padding-right:32px;font-size:24px;font-weight:700;line-height:1.25;color:#1A1D21;text-transform:uppercase}
.fve-pop .fve-pop__meta{margin:0 0 20px;padding:0;list-style:none;border-top:1px solid #ECECEC}
.fve-pop__meta li{display:flex;gap:16px;margin:0;padding:10px 0;border-bottom:1px solid #ECECEC;font-size:14px}
.fve-pop__meta span{flex:0 0 80px;color:#6B7280}
.fve-pop__meta strong{font-weight:600}
.fve-pop__desc{font-size:15px;line-height:1.7;color:#374151}
.fve-pop__desc p{margin:0 0 12px}
.fve-pop__desc img{max-width:100%;height:auto}
.fve-pop .fve-pop__close{position:absolute;top:14px;right:14px;z-index:2;display:flex;align-items:center;justify-content:center;width:40px;height:40px;margin:0;padding:0;border:0;border-radius:50%;background:#fff;color:#1A1D21;font-size:26px;line-height:1;cursor:pointer;box-shadow:0 2px 10px rgba(0,0,0,.12)}
.fve-pop .fve-pop__close:hover{background:<?php echo esc_attr( $red ); ?>;color:#fff}
body.fve-lock{overflow:hidden}

@media (max-width:1024px){.fve .fve-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}}
@media (max-width:767px){
	.fve .fve-head__title{font-size:26px}
	.fve .fve-head__sub{font-size:14px}
	.fve-head{margin-bottom:22px}
	.fve .fve-grid{grid-template-columns:1fr;gap:18px}
	.fve-card__title{font-size:19px}
	.fve-card__loc,.fve-card__date{font-size:16px}
	.fve-card__media{height:260px}
	.fve-pop{width:calc(100vw - 20px);max-height:calc(100vh - 20px)}
	.fve-pop__inner{grid-template-columns:1fr}
	.fve-pop__media{min-height:0;height:240px}
	.fve-pop__body{padding:22px 20px 24px}
	.fve-pop .fve-pop__title{font-size:20px}
}
</style>
<script id="fve-js">
(function () {
	function init() {
		var dlg = document.querySelector('.fve-pop');
		if (!dlg || dlg.dataset.ready) return;
		dlg.dataset.ready = '1';
		var inner = dlg.querySelector('.fve-pop__inner'), last = null;

		function showTab(which) {
			document.querySelectorAll('.fve-tab').forEach(function (t) {
				var on = t.getAttribute('data-fve-filter') === which;
				t.classList.toggle('is-active', on);
				t.setAttribute('aria-selected', on ? 'true' : 'false');
			});
			document.querySelectorAll('.fve-item').forEach(function (li) {
				li.hidden = li.getAttribute('data-fve-when') !== which;
			});
		}
		document.addEventListener('click', function (e) {
			var tab = e.target.closest('.fve-tab');
			if (tab) showTab(tab.getAttribute('data-fve-filter'));
		});

		function open(id, trigger) {
			var tpl = document.getElementById(id);
			if (!tpl) return;
			var li = tpl.closest('.fve-item');
			if (li && li.hidden && document.querySelector('.fve-tab')) showTab(li.getAttribute('data-fve-when'));
			inner.innerHTML = '';
			inner.appendChild(tpl.content.cloneNode(true));
			last = trigger || null;
			if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
			document.body.classList.add('fve-lock');
			if (history.replaceState) history.replaceState(null, '', '#' + id);
		}
		function close() {
			if (dlg.open && typeof dlg.close === 'function') { dlg.close(); } else { dlg.removeAttribute('open'); }
		}
		dlg.addEventListener('close', function () {
			document.body.classList.remove('fve-lock');
			if (history.replaceState) history.replaceState(null, '', location.pathname + location.search);
			if (last) last.focus();
		});
		dlg.addEventListener('click', function (e) { if (e.target === dlg) close(); }); // click outside the box
		dlg.querySelector('.fve-pop__close').addEventListener('click', close);
		document.addEventListener('click', function (e) {
			var card = e.target.closest('[data-fve-open]');
			if (card) open(card.getAttribute('data-fve-open'), card);
		});
		if (location.hash && location.hash.indexOf('#fve-') === 0) open(location.hash.slice(1));
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
</script>
	<?php
}

/* Branded WooCommerce emails + order tracking in emails */
if ( file_exists( get_stylesheet_directory() . '/soharon-emails.php' ) ) {
	require_once get_stylesheet_directory() . '/soharon-emails.php';
}
