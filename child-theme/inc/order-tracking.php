<?php
/**
 * Order tracking: statuses + courier box (admin), My Account → Track order, [soharon_track_order].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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
