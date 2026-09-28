<?php
/**
 * ============================================================================
 * SOHARON — WooCommerce email design + order tracking in emails
 * ============================================================================
 * 1. New branded layout for EVERY WooCommerce email (header, card, footer, tables).
 * 2. Customer order emails (processing, completed, on-hold, invoice, customer note)
 *    show a tracking panel: progress steps, courier, tracking number,
 *    estimated delivery, latest update and a "Track your order" button.
 * 3. New email "Order status update" (WooCommerce → Settings → Emails):
 *    sent to the customer when an order moves to a custom status
 *    (Ready for Dispatch, Shipped, Out for Delivery, Delivered …).
 * 4. Order screen → Order actions → "Send tracking update to customer".
 *
 * Install: put this file in the child theme folder (next to functions.php) and add
 *     require_once get_stylesheet_directory() . '/soharon-emails.php';
 * to the end of functions.php.
 * The tracking panel uses the order tracking functions in functions.php;
 * without them the new layout still works, just without the tracking panel.
 * ============================================================================
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Colours & fonts (brand red comes from functions.php when available) */
function sohe_c( $key ) {
    $c = array(
        'red'    => defined( 'SOHARON_RED' ) ? SOHARON_RED : '#DB141D',
        'text'   => '#1A1A1A',
        'body'   => '#374151',
        'muted'  => '#6B7280',
        'border' => '#ECECEC',
        'bg'     => '#F4F5F7',
        'soft'   => '#F7F7F7',
        'green'  => '#17804A',
        'font'   => "'Poppins', Arial, Helvetica, sans-serif",
    );
    return $c[ $key ];
}

function sohe_has_tracking() {
    return function_exists( 'soharon_track_delivery' ) && function_exists( 'soharon_track_status_info' );
}

/* ----------------------------------------------------------------------------
 * 1. Layout: replace WooCommerce's email header + footer with ours
 * ------------------------------------------------------------------------- */
add_action( 'woocommerce_email', function ( $mailer ) {
    remove_action( 'woocommerce_email_header', array( $mailer, 'email_header' ) );
    remove_action( 'woocommerce_email_footer', array( $mailer, 'email_footer' ) );
    add_action( 'woocommerce_email_header', 'sohe_email_header', 10, 2 );
    add_action( 'woocommerce_email_footer', 'sohe_email_footer', 10, 1 );
} );

function sohe_email_header( $email_heading, $email = null ) {
    $site = get_bloginfo( 'name', 'display' );
    $logo = get_option( 'woocommerce_email_header_image' );
    if ( ! $logo && defined( 'FSH_LOGO' ) ) {
        $logo = FSH_LOGO;
    }
    ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title><?php echo esc_html( $site ); ?></title>
</head>
<body class="sohe-body" <?php echo is_rtl() ? 'dir="rtl"' : 'dir="ltr"'; ?> style="margin:0;padding:0;background-color:<?php echo esc_attr( sohe_c( 'bg' ) ); ?>;">
<table role="presentation" id="sohe-wrapper" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="<?php echo esc_attr( sohe_c( 'bg' ) ); ?>">
    <tr>
        <td align="center" class="sohe-outer">
            <table role="presentation" class="sohe-container" width="600" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="sohe-logo" align="center">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">
                            <?php if ( $logo ) : ?>
                                <img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $site ); ?>" height="46">
                            <?php else : ?>
                                <span class="sohe-logo-text"><?php echo esc_html( $site ); ?></span>
                            <?php endif; ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td class="sohe-card" bgcolor="#ffffff">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr><td class="sohe-topbar" height="5" bgcolor="<?php echo esc_attr( sohe_c( 'red' ) ); ?>"></td></tr>
                            <?php if ( $email_heading ) : ?>
                                <tr><td class="sohe-hero"><h1><?php echo esc_html( $email_heading ); ?></h1></td></tr>
                            <?php endif; ?>
                            <tr>
                                <td id="body_content" class="sohe-body-cell">
                                    <div id="body_content_inner">
    <?php
}

function sohe_email_footer( $email = null ) {
    $footer = apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text', '{site_title}' ) );
    if ( $email && is_callable( array( $email, 'format_string' ) ) ) {
        $footer = $email->format_string( $footer );
    }
    $footer = str_replace( array( '{site_title}', '{site_url}' ), array( get_bloginfo( 'name', 'display' ), home_url() ), $footer );
    ?>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td class="sohe-footer" align="center">
                        <?php echo wp_kses_post( wpautop( wptexturize( $footer ) ) ); ?>
                        <p class="sohe-footer-links">
                            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Shop</a>
                            <?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
                                &nbsp;·&nbsp; <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">My account</a>
                            <?php endif; ?>
                            <?php if ( function_exists( 'soharon_track_page_url' ) && soharon_track_page_url() ) : ?>
                                &nbsp;·&nbsp; <a href="<?php echo esc_url( soharon_track_page_url() ); ?>">Track an order</a>
                            <?php endif; ?>
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
    <?php
}

/* Styles: added after WooCommerce's own, then inlined by WooCommerce */
add_filter( 'woocommerce_email_styles', function ( $css, $email = null ) {
    return $css . sohe_email_css();
}, 99, 2 );

function sohe_email_css() {
    $r = sohe_c( 'red' ); $t = sohe_c( 'text' ); $b = sohe_c( 'body' ); $m = sohe_c( 'muted' );
    $l = sohe_c( 'border' ); $bg = sohe_c( 'bg' ); $s = sohe_c( 'soft' ); $g = sohe_c( 'green' ); $f = sohe_c( 'font' );
    return "
body, .sohe-body { margin:0; padding:0; background-color:{$bg}; -webkit-text-size-adjust:none; }
#sohe-wrapper { background-color:{$bg}; width:100%; }
.sohe-outer { padding:32px 12px; }
.sohe-container { width:600px; max-width:600px; }
.sohe-logo { padding:0 0 22px; text-align:center; }
.sohe-logo img { height:46px; width:auto; max-width:220px; border:0; outline:none; text-decoration:none; display:inline-block; }
.sohe-logo-text { font-family:{$f}; font-size:22px; font-weight:700; color:{$t}; }
.sohe-card { background-color:#ffffff; border:1px solid {$l}; border-radius:20px; overflow:hidden; }
.sohe-topbar { font-size:0; line-height:0; background-color:{$r}; }
.sohe-hero { padding:34px 40px 6px; }
h1 { margin:0; padding:0; font-family:{$f}; font-size:26px; font-weight:700; line-height:1.3; color:{$t}; text-align:left; text-shadow:none; }
.sohe-body-cell { padding:14px 40px 36px; }
#body_content_inner { font-family:{$f}; font-size:15px; line-height:1.65; color:{$b}; text-align:left; }
#body_content_inner p { margin:0 0 16px; }
h2 { margin:30px 0 12px; padding:0; font-family:{$f}; font-size:18px; font-weight:600; line-height:1.35; color:{$t}; text-align:left; }
h3 { margin:18px 0 8px; font-family:{$f}; font-size:15px; font-weight:600; color:{$t}; }
a { color:{$r}; font-weight:600; }
.link { color:{$r}; }

/* Order items table */
table.td { width:100%; border:1px solid {$l} !important; border-collapse:separate !important; border-spacing:0; border-radius:14px; overflow:hidden; font-family:{$f}; }
th.td, td.td { padding:12px 14px !important; border:0 !important; border-top:1px solid {$l} !important; color:{$t}; font-family:{$f}; font-size:14px; vertical-align:middle; }
thead th.td { border-top:0 !important; background-color:{$s}; color:{$m}; font-size:13px; font-weight:600; }
tfoot th.td { color:{$m}; font-weight:500; }
tfoot td.td { font-weight:600; }
tfoot tr:last-child th.td, tfoot tr:last-child td.td { font-size:16px; font-weight:700; color:{$t}; }
tfoot tr:last-child td.td .amount { color:{$r}; }
.wc-item-meta { margin:4px 0 0; padding:0; color:{$m}; font-size:12px; }
.wc-item-meta p { margin:0; }

/* Addresses */
.address { margin:0 0 18px; padding:16px 18px !important; border:1px solid {$l} !important; border-radius:14px; background-color:{$s}; color:{$b}; font-style:normal; font-size:14px; line-height:1.7; }
#addresses td { padding:0 6px 0 0; }

/* Buttons */
.sohe-btn-wrap { margin:6px 0 4px; }
.sohe-btn { border-radius:999px; background-color:{$r}; }
.sohe-btn a { display:inline-block; padding:13px 30px; border-radius:999px; background-color:{$r}; color:#ffffff !important; font-family:{$f}; font-size:15px; font-weight:600; text-decoration:none; }

/* Tracking panel */
.sohe-track { width:100%; margin:4px 0 26px; border:1px solid {$l}; border-radius:16px; }
.sohe-track-inner { padding:20px 22px; }
.sohe-track-title { font-family:{$f}; font-size:16px; font-weight:700; color:{$t}; }
.sohe-pill { display:inline-block; padding:4px 12px; border-radius:999px; font-family:{$f}; font-size:12px; font-weight:600; }
.sohe-track-msg { margin:10px 0 18px !important; color:{$b}; font-size:14px; }
.sohe-track-msg.is-stopped { color:{$r}; font-weight:600; }
.sohe-bar { width:100%; border-radius:4px; }
.sohe-bar td { height:6px; font-size:0; line-height:0; }
.sohe-bar-done { background-color:{$g}; border-radius:4px; }
.sohe-bar-todo { background-color:{$l}; border-radius:4px; }
.sohe-steps { width:100%; margin-top:10px; }
.sohe-step { padding:0 2px; text-align:center; vertical-align:top; font-family:{$f}; font-size:11px; line-height:1.3; color:{$m}; }
.sohe-dot { display:inline-block; width:22px; height:22px; line-height:22px; border-radius:50%; background-color:{$l}; color:{$m}; font-size:11px; font-weight:700; text-align:center; }
.sohe-dot-done { background-color:{$g}; color:#ffffff; }
.sohe-dot-current { background-color:{$r}; color:#ffffff; }
.sohe-step-done, .sohe-step-current { color:{$t}; font-weight:600; }
.sohe-step-date { display:block; color:{$m}; font-weight:400; font-size:10px; }
.sohe-details { width:100%; margin-top:18px; border-top:1px solid {$l}; }
.sohe-details th, .sohe-details td { padding:10px 0 0; font-family:{$f}; font-size:14px; text-align:left; vertical-align:top; }
.sohe-details th { width:42%; color:{$m}; font-weight:500; }
.sohe-details td { color:{$t}; font-weight:600; }
.sohe-details td a { font-size:13px; }

/* Footer */
.sohe-footer { padding:24px 20px 8px; font-family:{$f}; font-size:12px; line-height:1.6; color:#9CA3AF; text-align:center; }
.sohe-footer p { margin:0 0 8px; }
.sohe-footer a { color:{$m}; font-weight:500; text-decoration:none; }

@media screen and (max-width:620px) {
    .sohe-outer { padding:16px 8px !important; }
    .sohe-container { width:100% !important; }
    .sohe-hero { padding:26px 22px 4px !important; }
    .sohe-body-cell { padding:10px 22px 28px !important; }
    h1 { font-size:22px !important; }
    .sohe-track-inner { padding:16px !important; }
    .sohe-step { font-size:9px !important; }
    .sohe-step-date { display:none !important; }
}
";
}

/* ----------------------------------------------------------------------------
 * 2. Tracking panel in customer order emails
 * ------------------------------------------------------------------------- */
function sohe_tracking_email_ids() {
    return apply_filters( 'sohe_tracking_email_ids', array(
        'customer_processing_order',
        'customer_completed_order',
        'customer_on_hold_order',
        'customer_invoice',
        'customer_note',
        'soharon_status_update',
    ) );
}

add_action( 'woocommerce_email_order_details', function ( $order, $sent_to_admin, $plain_text, $email = null ) {
    if ( $sent_to_admin || ! sohe_has_tracking() || ! $order instanceof WC_Order ) return;
    if ( ! $email || ! in_array( $email->id, sohe_tracking_email_ids(), true ) ) return;

    if ( $plain_text ) {
        echo sohe_tracking_plain( $order ); // phpcs:ignore
    } else {
        echo sohe_tracking_html( $order ); // phpcs:ignore
    }
}, 5, 4 );

/* Link to the tracking page (full details via the order key), otherwise the account order page */
function sohe_tracking_url( $order ) {
    $url = function_exists( 'soharon_track_page_url' ) ? soharon_track_page_url( $order ) : '';
    if ( ! $url && $order->get_customer_id() ) {
        $url = $order->get_view_order_url();
    }
    return $url;
}

function sohe_tracking_html( $order ) {
    list( $step, $message ) = soharon_track_status_info( $order );
    $steps    = function_exists( 'soharon_track_steps' ) ? soharon_track_steps() : array();
    $count    = count( $steps );
    $last     = $count - 1;
    $dates    = function_exists( 'soharon_track_step_dates' ) ? soharon_track_step_dates( $order ) : array();
    $d        = soharon_track_delivery( $order );
    $url      = sohe_tracking_url( $order );
    $status   = $order->get_status();
    $colour   = sohe_status_colour( $status );

    ob_start();
    ?>
    <table role="presentation" class="sohe-track" cellpadding="0" cellspacing="0" border="0">
        <tr><td class="sohe-track-inner">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    <td class="sohe-track-title">Order #<?php echo esc_html( $order->get_order_number() ); ?></td>
                    <td align="right"><span class="sohe-pill" style="background-color:<?php echo esc_attr( sohe_soft( $colour ) ); ?>;color:<?php echo esc_attr( $colour ); ?>;"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span></td>
                </tr>
            </table>
            <p class="sohe-track-msg<?php echo $step < 0 ? ' is-stopped' : ''; ?>"><?php echo esc_html( $message ); ?></p>

            <?php if ( $step >= 0 && $count > 1 ) :
                $pct = $step === $last ? 100 : (int) round( ( $step + 0.5 ) / $count * 100 ); // ends at the current step's dot ?>
                <table role="presentation" class="sohe-bar" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <?php if ( $pct > 0 ) : ?><td class="sohe-bar-done" width="<?php echo $pct; ?>%" bgcolor="<?php echo esc_attr( sohe_c( 'green' ) ); ?>">&nbsp;</td><?php endif; ?>
                        <?php if ( $pct < 100 ) : ?><td class="sohe-bar-todo" bgcolor="<?php echo esc_attr( sohe_c( 'border' ) ); ?>">&nbsp;</td><?php endif; ?>
                    </tr>
                </table>
                <table role="presentation" class="sohe-steps" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <?php foreach ( $steps as $i => $label ) :
                            $state = ( $i < $step || $step === $last ) ? 'done' : ( $i === $step ? 'current' : 'todo' ); ?>
                            <td class="sohe-step sohe-step-<?php echo esc_attr( $state ); ?>" width="<?php echo (int) floor( 100 / $count ); ?>%">
                                <span class="sohe-dot sohe-dot-<?php echo esc_attr( $state ); ?>"><?php echo 'done' === $state ? '&#10003;' : (int) ( $i + 1 ); ?></span><br>
                                <?php echo esc_html( $label ); ?>
                                <?php if ( $i <= $step && ! empty( $dates[ $i ] ) ) : ?>
                                    <span class="sohe-step-date"><?php echo esc_html( date_i18n( 'j M', $dates[ $i ] ) ); ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if ( $d['courier'] || $d['number'] || $d['eta'] || $d['update'] ) : ?>
                <table role="presentation" class="sohe-details" cellpadding="0" cellspacing="0" border="0">
                    <?php if ( $d['courier'] ) : ?>
                        <tr><th>Courier</th><td><?php echo esc_html( $d['courier'] ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $d['number'] ) : ?>
                        <tr><th>Tracking number</th><td><?php echo esc_html( $d['number'] ); ?>
                            <?php if ( $d['link'] ) : ?><br><a href="<?php echo esc_url( $d['link'] ); ?>">Track on courier website &rarr;</a><?php endif; ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $d['eta'] && $step !== $last ) : ?>
                        <tr><th>Estimated delivery</th><td><?php echo esc_html( date_i18n( 'l, ' . wc_date_format(), strtotime( $d['eta'] . ' 12:00:00' ) ) ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $d['update'] ) : ?>
                        <tr><th>Latest update</th><td><?php echo nl2br( esc_html( $d['update'] ) ); ?></td></tr>
                    <?php endif; ?>
                </table>
            <?php endif; ?>

            <?php if ( $url ) : ?>
                <table role="presentation" class="sohe-btn-wrap" cellpadding="0" cellspacing="0" border="0" style="margin-top:20px;">
                    <tr><td class="sohe-btn" bgcolor="<?php echo esc_attr( sohe_c( 'red' ) ); ?>"><a href="<?php echo esc_url( $url ); ?>" target="_blank">Track your order</a></td></tr>
                </table>
            <?php endif; ?>
        </td></tr>
    </table>
    <?php
    return ob_get_clean();
}

function sohe_tracking_plain( $order ) {
    list( $step, $message ) = soharon_track_status_info( $order );
    $d   = soharon_track_delivery( $order );
    $url = sohe_tracking_url( $order );
    $out = "\n==== ORDER TRACKING ====\n\n";
    $out .= 'Status: ' . wc_get_order_status_name( $order->get_status() ) . "\n" . $message . "\n";
    if ( $d['courier'] ) $out .= 'Courier: ' . $d['courier'] . "\n";
    if ( $d['number'] ) $out .= 'Tracking number: ' . $d['number'] . "\n";
    if ( $d['link'] ) $out .= 'Courier tracking: ' . $d['link'] . "\n";
    if ( $d['eta'] ) $out .= 'Estimated delivery: ' . date_i18n( wc_date_format(), strtotime( $d['eta'] . ' 12:00:00' ) ) . "\n";
    if ( $d['update'] ) $out .= 'Latest update: ' . $d['update'] . "\n";
    if ( $url ) $out .= 'Track your order: ' . $url . "\n";
    return $out . "\n";
}

/* #RRGGBB → light rgba() background (8-digit hex isn't supported by all email apps) */
function sohe_soft( $hex, $alpha = 0.12 ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) return '#F1F2F4';
    return sprintf( 'rgba(%d,%d,%d,%s)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), $alpha );
}

/* Status colour for the pill (custom statuses use the colour set in WooCommerce → Order Tracking) */
function sohe_status_colour( $status ) {
    $core = array(
        'pending'    => '#B45309', 'on-hold' => '#B45309', 'processing' => '#B45309',
        'completed'  => '#17804A',
        'cancelled'  => sohe_c( 'red' ), 'failed' => sohe_c( 'red' ), 'refunded' => sohe_c( 'red' ),
    );
    if ( isset( $core[ $status ] ) ) return $core[ $status ];
    if ( function_exists( 'soharon_track_custom_statuses' ) ) {
        $custom = soharon_track_custom_statuses();
        if ( isset( $custom[ $status ]['color'] ) && sanitize_hex_color( $custom[ $status ]['color'] ) ) {
            return $custom[ $status ]['color'];
        }
    }
    return '#4B5563';
}

/* ----------------------------------------------------------------------------
 * 3. "Order status update" email for the custom statuses
 * ------------------------------------------------------------------------- */

/* Let WooCommerce send emails for the custom status changes (also works with deferred emails) */
add_filter( 'woocommerce_email_actions', function ( $actions ) {
    if ( function_exists( 'soharon_track_custom_statuses' ) ) {
        foreach ( array_keys( soharon_track_custom_statuses() ) as $slug ) {
            $actions[] = 'woocommerce_order_status_' . $slug;
        }
    }
    return $actions;
} );

add_filter( 'woocommerce_email_classes', function ( $emails ) {
    if ( ! class_exists( 'Sohe_Email_Status_Update' ) ) {
        sohe_define_status_email();
    }
    $emails['Sohe_Email_Status_Update'] = new Sohe_Email_Status_Update();
    return $emails;
} );

function sohe_define_status_email() {
    class Sohe_Email_Status_Update extends WC_Email {

        public function __construct() {
            $this->id             = 'soharon_status_update';
            $this->customer_email = true;
            $this->title          = 'Order status update';
            $this->description    = 'Sent to the customer when an order moves to one of the custom statuses from WooCommerce → Order Tracking (e.g. Ready for Dispatch, Shipped, Out for Delivery, Delivered), and from Order actions → "Send tracking update to customer". Includes the tracking panel.';
            $this->placeholders   = array(
                '{order_date}'   => '',
                '{order_number}' => '',
                '{order_status}' => '',
            );

            if ( function_exists( 'soharon_track_custom_statuses' ) ) {
                foreach ( array_keys( soharon_track_custom_statuses() ) as $slug ) {
                    add_action( 'woocommerce_order_status_' . $slug . '_notification', array( $this, 'trigger' ), 10, 2 );
                }
            }
            parent::__construct();
        }

        public function get_default_subject() {
            return 'Your {site_title} order #{order_number} is now {order_status}';
        }

        public function get_default_heading() {
            return 'Your order is {order_status}';
        }

        public function get_default_additional_content() {
            return 'Thank you for shopping with us.';
        }

        public function trigger( $order_id, $order = false, $force = false ) {
            $this->setup_locale();

            if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
                $order = wc_get_order( $order_id );
            }
            if ( is_a( $order, 'WC_Order' ) ) {
                $this->object                         = $order;
                $this->recipient                      = $order->get_billing_email();
                $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
                $this->placeholders['{order_number}'] = $order->get_order_number();
                $this->placeholders['{order_status}'] = wc_get_order_status_name( $order->get_status() );
            }

            if ( $this->object && $this->get_recipient() && ( $force || $this->is_enabled() ) ) {
                $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
            }

            $this->restore_locale();
        }

        public function get_content_html() {
            $order = $this->object;
            ob_start();
            do_action( 'woocommerce_email_header', $this->get_heading(), $this );
            printf( '<p>Hi %s,</p>', esc_html( $order->get_billing_first_name() ? $order->get_billing_first_name() : 'there' ) );
            printf( '<p>Here\'s the latest on your order <strong>#%s</strong>.</p>', esc_html( $order->get_order_number() ) );
            do_action( 'woocommerce_email_order_details', $order, false, false, $this );
            do_action( 'woocommerce_email_order_meta', $order, false, false, $this );
            do_action( 'woocommerce_email_customer_details', $order, false, false, $this );
            if ( $this->get_additional_content() ) {
                echo wp_kses_post( wpautop( wptexturize( $this->get_additional_content() ) ) );
            }
            do_action( 'woocommerce_email_footer', $this );
            return ob_get_clean();
        }

        public function get_content_plain() {
            $order = $this->object;
            $out   = '= ' . $this->get_heading() . " =\n\n";
            $out  .= sprintf( "Hi %s,\n\nHere's the latest on your order #%s.\n", $order->get_billing_first_name() ? $order->get_billing_first_name() : 'there', $order->get_order_number() );
            ob_start();
            do_action( 'woocommerce_email_order_details', $order, false, true, $this );
            do_action( 'woocommerce_email_order_meta', $order, false, true, $this );
            do_action( 'woocommerce_email_customer_details', $order, false, true, $this );
            $out .= ob_get_clean();
            if ( $this->get_additional_content() ) {
                $out .= "\n" . wp_strip_all_tags( wptexturize( $this->get_additional_content() ) ) . "\n";
            }
            return $out;
        }
    }
}

/* ----------------------------------------------------------------------------
 * 4. Order screen → Order actions → "Send tracking update to customer"
 * ------------------------------------------------------------------------- */
add_filter( 'woocommerce_order_actions', function ( $actions ) {
    $actions['sohe_send_tracking'] = 'Send tracking update to customer';
    return $actions;
} );

add_action( 'woocommerce_order_action_sohe_send_tracking', function ( $order ) {
    $emails = WC()->mailer()->get_emails();
    if ( isset( $emails['Sohe_Email_Status_Update'] ) ) {
        $emails['Sohe_Email_Status_Update']->trigger( $order->get_id(), $order, true );
        $order->add_order_note( 'Tracking update email sent to the customer.', false, true );
    }
} );
