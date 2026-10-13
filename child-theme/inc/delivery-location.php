<?php
/**
 * ============================================
 * DELIVERY LOCATION – Google Maps pin
 * - Checkout: "Pin your delivery location" map under the delivery address
 *   (search box, "Use my current location", draggable pin)
 * - Saved on the order; shown in admin, My Account → Orders → View,
 *   the thank-you page and the customer + admin emails
 * - My Account → Addresses → Delivery address: same map, saved for next time
 *
 * Settings: WooCommerce → Settings → General → "Delivery location map"
 * (API key with Maps JavaScript API, Places API (New) and Geocoding API,
 * restricted to this website). No key = no map, everything else unchanged.
 * ============================================
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* Settings ------------------------------------------------------------- */
add_filter( 'woocommerce_general_settings', function ( $settings ) {
	return array_merge( $settings, array(
		array(
			'title' => 'Delivery location map',
			'type'  => 'title',
			'desc'  => 'Google Maps pin on the checkout and in My Account → Addresses. The API key needs Maps JavaScript API, Places API (New) and Geocoding API, and should be restricted to this website.',
			'id'    => 'soharon_loc_section',
		),
		array(
			'title'   => 'Google Maps API key',
			'id'      => 'soharon_gmaps_key',
			'type'    => 'text',
			'default' => '',
			'css'     => 'min-width:360px',
		),
		array(
			'title'    => 'Map ID (optional)',
			'id'       => 'soharon_gmaps_map_id',
			'type'     => 'text',
			'default'  => '',
			'desc_tip' => 'Google Maps Platform → Map Management. Gives the newer pin marker.',
		),
		array(
			'title'   => 'Pin at checkout',
			'desc'    => 'Customers must pin their delivery location before placing an order',
			'id'      => 'soharon_loc_required',
			'type'    => 'checkbox',
			'default' => 'no',
		),
		array( 'type' => 'sectionend', 'id' => 'soharon_loc_section' ),
	) );
} );

function soharon_loc_key() {
	return trim( (string) get_option( 'soharon_gmaps_key', '' ) );
}
function soharon_loc_required() {
	return 'yes' === get_option( 'soharon_loc_required', 'no' );
}

/* Data helpers --------------------------------------------------------- */
function soharon_loc_clean( $lat, $lng, $address = '' ) {
	if ( ! is_numeric( $lat ) || ! is_numeric( $lng ) ) return null;
	$lat = (float) $lat;
	$lng = (float) $lng;
	if ( abs( $lat ) > 90 || abs( $lng ) > 180 || ( 0.0 === $lat && 0.0 === $lng ) ) return null;
	return array(
		'lat'     => round( $lat, 6 ),
		'lng'     => round( $lng, 6 ),
		'address' => substr( sanitize_text_field( (string) $address ), 0, 255 ),
	);
}

function soharon_loc_from_post() {
	// phpcs:disable WordPress.Security.NonceVerification -- checked by WooCommerce (checkout / edit address)
	if ( ! isset( $_POST['soharon_loc_lat'], $_POST['soharon_loc_lng'] ) ) return null;
	return soharon_loc_clean(
		wp_unslash( $_POST['soharon_loc_lat'] ),
		wp_unslash( $_POST['soharon_loc_lng'] ),
		isset( $_POST['soharon_loc_address'] ) ? wp_unslash( $_POST['soharon_loc_address'] ) : ''
	);
	// phpcs:enable
}

function soharon_loc_from_order( $order ) {
	if ( ! $order instanceof WC_Order ) return null;
	return soharon_loc_clean( $order->get_meta( '_soharon_loc_lat' ), $order->get_meta( '_soharon_loc_lng' ), $order->get_meta( '_soharon_loc_address' ) );
}

function soharon_loc_from_user( $user_id ) {
	if ( ! $user_id ) return null;
	return soharon_loc_clean( get_user_meta( $user_id, 'soharon_loc_lat', true ), get_user_meta( $user_id, 'soharon_loc_lng', true ), get_user_meta( $user_id, 'soharon_loc_address', true ) );
}

function soharon_loc_save_user( $user_id, $loc ) {
	if ( $loc ) {
		update_user_meta( $user_id, 'soharon_loc_lat', $loc['lat'] );
		update_user_meta( $user_id, 'soharon_loc_lng', $loc['lng'] );
		update_user_meta( $user_id, 'soharon_loc_address', $loc['address'] );
	} else {
		delete_user_meta( $user_id, 'soharon_loc_lat' );
		delete_user_meta( $user_id, 'soharon_loc_lng' );
		delete_user_meta( $user_id, 'soharon_loc_address' );
	}
}

function soharon_loc_maps_url( $loc ) {
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $loc['lat'] . ',' . $loc['lng'] );
}

/* Small map without the API (no key, no cost) */
function soharon_loc_embed( $loc, $height = 200 ) {
	return sprintf(
		'<iframe class="soharon-loc-embed" src="%s" width="100%%" height="%d" style="border:0;border-radius:12px;display:block" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Delivery location"></iframe>',
		esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( $loc['lat'] . ',' . $loc['lng'] ) . '&z=17&output=embed' ),
		(int) $height
	);
}

/* "Delivery location" block: thank-you page, My Account order view, addresses */
function soharon_loc_block( $loc, $title = 'Delivery location', $map = true ) {
	if ( ! $loc ) return '';
	$pin = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>';
	$out  = '<div class="soharon-loc-view">';
	if ( $title ) $out .= '<h3 class="soharon-loc-view__title">' . esc_html( $title ) . '</h3>';
	if ( $map ) $out .= soharon_loc_embed( $loc );
	if ( $loc['address'] ) $out .= '<p class="soharon-loc-view__addr">' . esc_html( $loc['address'] ) . '</p>';
	$out .= '<a class="soharon-loc-view__link" href="' . esc_url( soharon_loc_maps_url( $loc ) ) . '" target="_blank" rel="noopener">' . $pin . ' Open in Google Maps</a>';
	$out .= '</div>';
	return $out;
}

/* Checkout ------------------------------------------------------------- */
function soharon_loc_on_checkout() {
	return '' !== soharon_loc_key() && function_exists( 'is_checkout' ) && is_checkout()
		&& ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' );
}
function soharon_loc_on_edit_shipping() {
	return '' !== soharon_loc_key() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' )
		&& 'shipping' === get_query_var( 'edit-address' );
}

/* Between the delivery address and Payment (payment is at 99) */
add_action( 'woocommerce_checkout_shipping', function () {
	if ( '' === soharon_loc_key() || ! WC()->cart || ! WC()->cart->needs_shipping_address() ) return;
	soharon_loc_picker( soharon_loc_from_user( get_current_user_id() ), soharon_loc_required() );
}, 50 );

/* The map itself – shared by checkout and My Account → Addresses */
function soharon_loc_picker( $loc, $required = false ) {
	?>
	<div class="soharon-loc" data-soharon-loc>
		<h3 class="soharon-loc__title">Pin your delivery location <?php echo $required ? '<abbr class="required" title="required">*</abbr>' : '<span class="optional">(optional)</span>'; ?></h3>
		<p class="soharon-loc__hint">Search for your building or drag the pin to the exact spot. It helps our courier find you.</p>
		<div class="soharon-loc__tools">
			<div class="soharon-loc__search">
				<input type="text" class="input-text soharon-loc__input" placeholder="Search address, building or area" autocomplete="off" aria-label="Search address" role="combobox" aria-expanded="false" aria-autocomplete="list">
				<ul class="soharon-loc__list" role="listbox" hidden></ul>
			</div>
			<button type="button" class="soharon-loc__me">Use my location</button>
		</div>
		<div class="soharon-loc__map" role="application" aria-label="Map – click or drag the pin to set your delivery location"></div>
		<p class="soharon-loc__status" aria-live="polite"><?php echo $loc ? 'Pinned: <span>' . esc_html( $loc['address'] ? $loc['address'] : $loc['lat'] . ', ' . $loc['lng'] ) . '</span>' : 'No pin yet – click the map or search above.'; ?>
			<button type="button" class="soharon-loc__clear"<?php echo $loc ? '' : ' hidden'; ?>>Remove pin</button></p>
		<input type="hidden" name="soharon_loc_lat" value="<?php echo esc_attr( $loc ? $loc['lat'] : '' ); ?>">
		<input type="hidden" name="soharon_loc_lng" value="<?php echo esc_attr( $loc ? $loc['lng'] : '' ); ?>">
		<input type="hidden" name="soharon_loc_address" value="<?php echo esc_attr( $loc ? $loc['address'] : '' ); ?>">
		<input type="hidden" name="soharon_loc_unavailable" value="">
	</div>
	<?php
}

/* Required pin (only if the setting is on and the map actually loaded) */
add_action( 'woocommerce_after_checkout_validation', function ( $data, $errors ) {
	if ( '' === soharon_loc_key() || ! soharon_loc_required() || ! WC()->cart || ! WC()->cart->needs_shipping_address() ) return;
	if ( ! empty( $_POST['soharon_loc_unavailable'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification -- map failed to load: don't block the order
	if ( ! soharon_loc_from_post() ) {
		$errors->add( 'soharon_loc_required', 'Please <strong>pin your delivery location</strong> on the map.' );
	}
}, 10, 2 );

/* Save on the order … */
add_action( 'woocommerce_checkout_create_order', function ( $order ) {
	$loc = soharon_loc_from_post();
	if ( ! $loc ) return;
	$order->update_meta_data( '_soharon_loc_lat', $loc['lat'] );
	$order->update_meta_data( '_soharon_loc_lng', $loc['lng'] );
	$order->update_meta_data( '_soharon_loc_address', $loc['address'] );
} );
/* … and on the customer's account for next time (like WooCommerce does with addresses) */
add_action( 'woocommerce_checkout_update_user_meta', function ( $customer_id ) {
	$loc = soharon_loc_from_post();
	if ( $customer_id && $loc ) soharon_loc_save_user( $customer_id, $loc );
} );

/* My Account → Addresses ---------------------------------------------- */
add_action( 'woocommerce_after_edit_address_form_shipping', function () {
	if ( '' === soharon_loc_key() ) return;
	soharon_loc_picker( soharon_loc_from_user( get_current_user_id() ) );
} );
add_action( 'woocommerce_customer_save_address', function ( $user_id, $load_address ) {
	if ( 'shipping' !== $load_address || ! isset( $_POST['soharon_loc_lat'] ) ) return; // phpcs:ignore WordPress.Security.NonceVerification
	soharon_loc_save_user( $user_id, soharon_loc_from_post() ); // empty = pin removed
}, 10, 2 );
add_action( 'woocommerce_my_account_after_my_address', function ( $name ) {
	if ( 'shipping' !== $name ) return;
	$loc = soharon_loc_from_user( get_current_user_id() );
	if ( $loc ) echo '<span class="soharon-loc-pinned"><a href="' . esc_url( soharon_loc_maps_url( $loc ) ) . '" target="_blank" rel="noopener">📍 Location pinned – view on map</a></span>';
} );

/* Order views ---------------------------------------------------------- */
/* My Account → Orders → View (and any page using WooCommerce's order details) */
add_action( 'woocommerce_order_details_after_customer_details', function ( $order ) {
	echo soharon_loc_block( soharon_loc_from_order( $order ) ); // phpcs:ignore
} );

/* Admin order screen, under the shipping address */
add_action( 'woocommerce_admin_order_data_after_shipping_address', function ( $order ) {
	$loc = soharon_loc_from_order( $order );
	if ( ! $loc ) return;
	echo '<div class="soharon-loc-admin" style="clear:both;padding-top:8px"><h3>Delivery location</h3>';
	echo soharon_loc_embed( $loc, 180 ); // phpcs:ignore
	if ( $loc['address'] ) echo '<p style="margin:8px 0 4px">' . esc_html( $loc['address'] ) . '</p>';
	echo '<p style="margin:4px 0"><a href="' . esc_url( soharon_loc_maps_url( $loc ) ) . '" target="_blank" rel="noopener">Open in Google Maps</a> · <code>' . esc_html( $loc['lat'] . ', ' . $loc['lng'] ) . '</code></p></div>';
} );

/* Emails (customer + admin), after the addresses -------------------- */
add_action( 'woocommerce_email_customer_details', function ( $order, $sent_to_admin, $plain_text ) {
	$loc = soharon_loc_from_order( $order );
	if ( ! $loc ) return;
	$url = soharon_loc_maps_url( $loc );
	if ( $plain_text ) {
		echo "\n" . esc_html__( 'Delivery location', 'woocommerce' ) . ': ' . ( $loc['address'] ? esc_html( $loc['address'] ) . ' – ' : '' ) . esc_url_raw( $url ) . "\n";
		return;
	}
	$red    = function_exists( 'sohe_c' ) ? sohe_c( 'red' ) : '#DB141D';
	$border = function_exists( 'sohe_c' ) ? sohe_c( 'border' ) : '#ECECEC';
	$muted  = function_exists( 'sohe_c' ) ? sohe_c( 'muted' ) : '#6B7280';
	?>
	<table cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;border:1px solid <?php echo esc_attr( $border ); ?>;border-radius:14px">
		<tr><td style="padding:16px 18px">
			<p style="margin:0 0 4px;font-size:15px;font-weight:600">Delivery location</p>
			<?php if ( $loc['address'] ) : ?><p style="margin:0 0 12px;font-size:14px;color:<?php echo esc_attr( $muted ); ?>"><?php echo esc_html( $loc['address'] ); ?></p><?php endif; ?>
			<a href="<?php echo esc_url( $url ); ?>" style="display:inline-block;padding:10px 18px;border-radius:999px;background:<?php echo esc_attr( $red ); ?>;color:#ffffff;font-size:14px;font-weight:600;text-decoration:none">View on Google Maps</a>
		</td></tr>
	</table>
	<?php
}, 30, 3 );

/* Map script + styles (checkout and My Account → edit delivery address) */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! soharon_loc_on_checkout() && ! soharon_loc_on_edit_shipping() ) return;
	soharon_enqueue_inline_css( 'soharon-loc', soharon_brand_vars( soharon_loc_css() ) );
	wp_register_script( 'soharon-loc', false, array(), null, true );
	wp_enqueue_script( 'soharon-loc' );
	$centres = array( // where the map opens when there is no pin yet
		'AE' => array( 25.2048, 55.2708, 11 ),
		'GB' => array( 51.5072, -0.1276, 10 ),
		'SA' => array( 24.7136, 46.6753, 11 ),
	);
	$base = WC()->countries ? WC()->countries->get_base_country() : 'AE';
	$c    = isset( $centres[ $base ] ) ? $centres[ $base ] : $centres['AE'];
	wp_add_inline_script( 'soharon-loc', 'window.soharonLoc=' . wp_json_encode( array(
		'key'    => soharon_loc_key(),
		'mapId'  => trim( (string) get_option( 'soharon_gmaps_map_id', '' ) ),
		'centre' => array( 'lat' => $c[0], 'lng' => $c[1], 'zoom' => $c[2] ),
		'region' => strtolower( $base ),
	) ) . ';' . soharon_loc_js() );
}, 30 );

/* Thank-you + order view blocks need their styles too */
add_action( 'wp_enqueue_scripts', function () {
	if ( function_exists( 'is_checkout' ) && ( is_checkout() || is_account_page() ) ) {
		soharon_enqueue_inline_css( 'soharon-loc-view', soharon_brand_vars( '
.soharon-loc-view{margin:18px 0 0}
.soharon-loc-view__title{margin:0 0 10px;font-size:16px;font-weight:600}
.soharon-loc-view__addr{margin:10px 0 4px;font-size:14px;color:#6B7280}
.soharon-loc-view__link{display:inline-flex;align-items:center;gap:6px;margin-top:6px;font-size:14px;font-weight:600;color:{RED};text-decoration:none}
.soharon-loc-view__link:hover{text-decoration:underline}
.soharon-loc-pinned{display:block;margin-top:8px;font-size:13px}
.soharon-loc-pinned a{color:{RED};text-decoration:none;font-weight:600}
' ) );
	}
}, 25 );

function soharon_loc_css() {
	return '
.soharon-loc{margin:26px 0 8px;padding-top:22px;border-top:1px solid #ECECEC}
.soharon-loc__title{margin:0 0 4px;font-size:18px;font-weight:600}
.soharon-loc__title .optional{font-size:14px;font-weight:400;color:#6B7280}
.soharon-loc__title .required{color:{RED};text-decoration:none;border:0}
.soharon-loc__hint{margin:0 0 14px;font-size:14px;color:#6B7280}
.soharon-loc__tools{display:flex;gap:10px;margin-bottom:12px}
.soharon-loc__search{position:relative;flex:1;min-width:0}
.soharon-loc__search .soharon-loc__input{width:100%;margin:0}
.soharon-loc__list{position:absolute;z-index:20;top:calc(100% + 6px);left:0;right:0;margin:0;padding:6px;list-style:none;background:#fff;border:1px solid #ECECEC;border-radius:14px;box-shadow:0 12px 30px rgba(0,0,0,.12);max-height:280px;overflow:auto}
.soharon-loc__list li{padding:10px 12px;border-radius:10px;font-size:14px;line-height:1.35;cursor:pointer}
.soharon-loc__list li small{display:block;color:#6B7280;font-size:12px}
.soharon-loc__list li[aria-selected="true"],.soharon-loc__list li:hover{background:#F4F5F7}
.soharon-loc__me{flex:0 0 auto;padding:0 18px;min-height:46px;border:1px solid {RED};border-radius:999px;background:#fff;color:{RED};font:inherit;font-size:14px;font-weight:600;cursor:pointer}
.soharon-loc__me:hover{background:{RED};color:#fff}
.soharon-loc__map{height:320px;border-radius:14px;background:#F4F5F7;overflow:hidden}
.soharon-loc__status{display:flex;flex-wrap:wrap;align-items:center;gap:4px 12px;margin:10px 0 0;font-size:14px;color:#6B7280}
.soharon-loc__status span{color:#1A1A1A}
.soharon-loc__clear{padding:0;border:0;background:none;color:{RED};font:inherit;font-size:13px;font-weight:600;cursor:pointer;text-decoration:underline}
.soharon-loc.is-unavailable .soharon-loc__tools,.soharon-loc.is-unavailable .soharon-loc__map{display:none}
@media (max-width:600px){.soharon-loc__tools{flex-direction:column}.soharon-loc__me{min-height:44px}.soharon-loc__map{height:260px}}
';
}

function soharon_loc_js() {
	return <<<'JS'
(function () {
	var cfg = window.soharonLoc, box = document.querySelector('[data-soharon-loc]');
	if (!cfg || !box) return;
	var $ = function (s) { return box.querySelector(s); };
	var lat = $('[name="soharon_loc_lat"]'), lng = $('[name="soharon_loc_lng"]'), addr = $('[name="soharon_loc_address"]');
	var status = $('.soharon-loc__status'), clearBtn = $('.soharon-loc__clear');
	var input = $('.soharon-loc__input'), list = $('.soharon-loc__list');
	var map, marker, geocoder, places, token, timer, items = [], active = -1;

	function unavailable(msg) {
		box.classList.add('is-unavailable');
		$('[name="soharon_loc_unavailable"]').value = '1';
		status.firstChild.textContent = msg || 'The map could not be loaded. Please make sure your address above is complete.';
	}
	window.gm_authFailure = function () { unavailable(); };

	function setStatus(text) {
		status.firstChild.textContent = text ? 'Pinned: ' : 'No pin yet – click the map or search above.';
		var span = status.querySelector('span');
		if (text) {
			if (!span) { span = document.createElement('span'); status.insertBefore(span, clearBtn); }
			span.textContent = text;
		} else if (span) { span.remove(); }
		clearBtn.hidden = !text;
	}
	function getPos() { var p = marker.position || (marker.getPosition && marker.getPosition()); return p && (typeof p.lat === 'function' ? { lat: p.lat(), lng: p.lng() } : p); }
	function showMarker(pos) {
		if (marker.setPosition) { marker.setPosition(pos); marker.setVisible(true); }
		else { marker.position = pos; marker.map = map; }
	}
	function hideMarker() { if (marker.setVisible) marker.setVisible(false); else marker.map = null; }

	/* Pin moved: save the spot and show the address under the map (fields are not changed) */
	function pin(pos, text) {
		showMarker(pos);
		lat.value = (+pos.lat).toFixed(6); lng.value = (+pos.lng).toFixed(6);
		if (text) { addr.value = text; setStatus(text); return; }
		addr.value = ''; setStatus(lat.value + ', ' + lng.value);
		geocoder.geocode({ location: pos }).then(function (r) {
			if (r.results && r.results[0] && lat.value === (+pos.lat).toFixed(6)) {
				addr.value = r.results[0].formatted_address; setStatus(addr.value);
			}
		}).catch(function () {});
	}

	/* Search result picked: also fill the address fields */
	function prefix() {
		if (!document.querySelector('form.checkout')) return 'shipping_';
		var diff = document.getElementById('ship-to-different-address-checkbox');
		return diff && diff.checked ? 'shipping_' : 'billing_';
	}
	function setField(id, value) {
		var el = document.getElementById(id);
		if (!el || value == null) return;
		if (el.tagName === 'SELECT') {
			var opt = Array.prototype.find.call(el.options, function (o) {
				return o.value.toLowerCase() === String(value).toLowerCase() || o.text.toLowerCase() === String(value).toLowerCase();
			});
			if (!opt) return;
			value = opt.value;
		}
		if (el.value === value) return;
		el.value = value;
		if (window.jQuery) jQuery(el).trigger('change'); else el.dispatchEvent(new Event('change', { bubbles: true }));
	}
	function fill(place) {
		var c = {};
		(place.addressComponents || []).forEach(function (a) { a.types.forEach(function (t) { if (!c[t]) c[t] = a; }); });
		var t = function (k, s) { return c[k] ? (s ? c[k].shortText : c[k].longText) : ''; };
		var street = [t('street_number'), t('route')].filter(Boolean).join(' ');
		var named = (place.types || []).some(function (x) { return ['establishment', 'point_of_interest', 'premise', 'subpremise'].indexOf(x) > -1; });
		var name = named && place.displayName && (!street || place.displayName.indexOf(street) < 0) ? place.displayName : '';
		var line1 = [name, street || t('neighborhood') || t('sublocality')].filter(Boolean).join(', ');
		var p = prefix();
		setField(p + 'country', t('country', true));
		// the state list is rebuilt after a country change, so fill the rest a moment later
		setTimeout(function () {
			if (line1) setField(p + 'address_1', line1);
			setField(p + 'city', t('locality') || t('postal_town') || t('administrative_area_level_2') || t('administrative_area_level_1'));
			setField(p + 'state', t('administrative_area_level_1', true));
			setField(p + 'state', t('administrative_area_level_1'));
			if (t('postal_code')) setField(p + 'postcode', t('postal_code'));
			if (window.jQuery) jQuery(document.body).trigger('update_checkout');
		}, 300);
	}

	/* Search box (Places API – New) */
	function closeList() { list.hidden = true; list.innerHTML = ''; items = []; active = -1; input.setAttribute('aria-expanded', 'false'); }
	function search() {
		var q = input.value.trim();
		if (q.length < 3) { closeList(); return; }
		if (!token) token = new places.AutocompleteSessionToken();
		places.AutocompleteSuggestion.fetchAutocompleteSuggestions({
			input: q, sessionToken: token, locationBias: map.getBounds() || map.getCenter(), region: cfg.region
		}).then(function (res) {
			if (input.value.trim() !== q) return;
			list.innerHTML = ''; active = -1;
			items = (res.suggestions || []).filter(function (s) { return s.placePrediction; }).slice(0, 5);
			items.forEach(function (s, i) {
				var p = s.placePrediction, li = document.createElement('li');
				li.setAttribute('role', 'option'); li.id = 'soharon-loc-opt-' + i;
				li.textContent = p.mainText ? p.mainText.toString() : p.text.toString();
				if (p.secondaryText) { var sm = document.createElement('small'); sm.textContent = p.secondaryText.toString(); li.appendChild(sm); }
				li.addEventListener('mousedown', function (e) { e.preventDefault(); choose(i); });
				list.appendChild(li);
			});
			list.hidden = !items.length;
			input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
		}).catch(function () { closeList(); });
	}
	function choose(i) {
		var s = items[i]; if (!s) return;
		var place = s.placePrediction.toPlace();
		input.value = s.placePrediction.text.toString();
		closeList(); token = null;
		place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location', 'addressComponents', 'types', 'viewport'] }).then(function () {
			if (!place.location) return;
			if (place.viewport) map.fitBounds(place.viewport); else map.setCenter(place.location);
			if (map.getZoom() > 18 || map.getZoom() < 15) map.setZoom(17);
			pin(place.location.toJSON ? place.location.toJSON() : place.location, place.formattedAddress);
			fill(place);
		}).catch(function () {});
	}
	function mark(i) {
		Array.prototype.forEach.call(list.children, function (li, k) { li.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
		active = i; input.setAttribute('aria-activedescendant', i >= 0 ? 'soharon-loc-opt-' + i : '');
	}

	function start() {
		var hasPin = lat.value !== '' && lng.value !== '';
		var centre = hasPin ? { lat: +lat.value, lng: +lng.value } : { lat: cfg.centre.lat, lng: cfg.centre.lng };
		Promise.all([
			google.maps.importLibrary('maps'), google.maps.importLibrary('marker'),
			google.maps.importLibrary('places'), google.maps.importLibrary('geocoding')
		]).then(function (libs) {
			places = libs[2]; geocoder = new libs[3].Geocoder();
			var opts = { center: centre, zoom: hasPin ? 17 : cfg.centre.zoom, mapTypeControl: false, streetViewControl: false, fullscreenControl: true, clickableIcons: false, gestureHandling: 'cooperative' };
			if (cfg.mapId) opts.mapId = cfg.mapId;
			map = new libs[0].Map($('.soharon-loc__map'), opts);
			if (cfg.mapId && libs[1].AdvancedMarkerElement) {
				marker = new libs[1].AdvancedMarkerElement({ gmpDraggable: true, title: 'Delivery location' });
			} else {
				marker = new google.maps.Marker({ map: map, draggable: true, visible: false, title: 'Delivery location' });
			}
			if (hasPin) showMarker(centre);
			marker.addListener('dragend', function () { pin(getPos()); });
			map.addListener('click', function (e) { pin(e.latLng.toJSON()); });

			input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(search, 250); });
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') { e.preventDefault(); if (items.length) choose(active < 0 ? 0 : active); return; }
				if (list.hidden) return;
				if (e.key === 'ArrowDown') { e.preventDefault(); mark(Math.min(items.length - 1, active + 1)); }
				else if (e.key === 'ArrowUp') { e.preventDefault(); mark(Math.max(0, active - 1)); }
				else if (e.key === 'Escape') closeList();
			});
			input.addEventListener('blur', function () { setTimeout(closeList, 150); });
		}).catch(function () { unavailable(); });
	}

	$('.soharon-loc__me').addEventListener('click', function () {
		var btn = this;
		if (!navigator.geolocation) { btn.textContent = 'Location not available'; return; }
		btn.textContent = 'Finding you…';
		navigator.geolocation.getCurrentPosition(function (p) {
			btn.textContent = 'Use my location';
			if (!map) return;
			var pos = { lat: p.coords.latitude, lng: p.coords.longitude };
			map.setCenter(pos); map.setZoom(18); pin(pos);
		}, function () { btn.textContent = 'Use my location'; alert('We could not get your location. Please allow location access, or search / click the map instead.'); }, { enableHighAccuracy: true, timeout: 10000 });
	});
	clearBtn.addEventListener('click', function () {
		lat.value = lng.value = addr.value = '';
		if (marker) hideMarker();
		setStatus('');
	});

	/* Load Google Maps only when the map is about to be seen */
	function load() {
		if (window.google && google.maps && google.maps.importLibrary) { start(); return; }
		window.soharonLocReady = start;
		var s = document.createElement('script');
		s.src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(cfg.key) + '&v=weekly&loading=async&callback=soharonLocReady';
		s.async = true;
		s.onerror = function () { unavailable(); };
		document.head.appendChild(s);
	}
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (en) { if (en[0].isIntersecting) { io.disconnect(); load(); } }, { rootMargin: '300px' });
		io.observe(box);
	} else { load(); }
})();
JS;
}
