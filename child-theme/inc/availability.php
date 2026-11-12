<?php
/**
 * Out-of-stock products – "Typically available within 2 weeks" and "Check Availability".
 * Loaded by functions.php – do not load this file on its own.
 *
 * Product → Product data → Inventory → "When out of stock, show":
 *   both (default) / only "Typically available within 2 weeks" / only "Check Availability".
 * "Check Availability" opens a popup (name, email, phone, quantity). The request is
 * emailed to the shop with the product name, SKU and link; Reply goes to the customer.
 * Recipient: WooCommerce → Settings → Products → Inventory → "Notification recipient"
 * (falls back to the site admin email).
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! defined( 'FVA_LEAD_TEXT' ) ) {
	define( 'FVA_LEAD_TEXT', 'Typically available within 2 weeks' );
}

function fva_modes() {
	return array(
		'both'  => 'Both: "Typically available within 2 weeks" + Check Availability',
		'lead'  => 'Only "Typically available within 2 weeks"',
		'check' => 'Only "Check Availability" button',
	);
}
function fva_mode( $product ) {
	$mode = $product->get_meta( '_fva_oos_mode' );
	return isset( fva_modes()[ $mode ] ) ? $mode : 'both';
}
function fva_shows_lead( $product ) {
	return ! $product->is_in_stock() && in_array( fva_mode( $product ), array( 'both', 'lead' ), true );
}
function fva_shows_check( $product ) {
	return ! $product->is_in_stock() && in_array( fva_mode( $product ), array( 'both', 'check' ), true );
}

/* Admin: Product data → Inventory */
add_action( 'woocommerce_product_options_stock_status', function () {
	woocommerce_wp_select( array(
		'id'          => '_fva_oos_mode',
		'label'       => 'When out of stock, show',
		'options'     => fva_modes(),
		'desc_tip'    => true,
		'description' => 'What customers see on the product card and page while this product is out of stock.',
	) );
} );
add_action( 'woocommerce_admin_process_product_object', function ( $product ) {
	if ( isset( $_POST['_fva_oos_mode'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- WooCommerce checks the product form nonce
		$mode = sanitize_key( wp_unslash( $_POST['_fva_oos_mode'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$product->update_meta_data( '_fva_oos_mode', isset( fva_modes()[ $mode ] ) ? $mode : 'both' );
	}
} );

/* "Check Availability" button (card + product page) */
function fva_button( $product, $extra_class = '' ) {
	if ( ! fva_shows_check( $product ) ) return '';
	fva_needs_popup( true );
	$sku = $product->get_sku();
	return sprintf(
		'<button type="button" class="fvs-btn fva-btn %s" data-fva-open data-id="%d" data-name="%s" data-sku="%s">Check Availability</button>',
		esc_attr( $extra_class ),
		(int) $product->get_id(),
		esc_attr( $product->get_name() ),
		esc_attr( $sku )
	);
}
function fva_needs_popup( $set = false ) {
	static $needed = false;
	if ( $set ) $needed = true;
	return $needed;
}

/* Popup + script, once per page, only where a button was shown */
add_action( 'wp_footer', function () {
	if ( ! fva_needs_popup() ) return;
	$red = defined( 'SOHARON_RED' ) ? SOHARON_RED : '#DB141D';
	?>
	<dialog class="fva-pop" id="fva-pop" aria-labelledby="fva-title">
		<form class="fva-form" novalidate>
			<div class="fva-head">
				<h2 id="fva-title">Check availability</h2>
				<button type="button" class="fva-close" data-fva-close aria-label="Close">&times;</button>
			</div>
			<p class="fva-product"><span>Product</span><strong data-fva-product></strong></p>
			<div class="fva-fields">
				<label>Name *<input type="text" name="name" autocomplete="name" required maxlength="100"></label>
				<label>Email *<input type="email" name="email" autocomplete="email" required maxlength="150"></label>
				<label>Phone *<input type="tel" name="phone" autocomplete="tel" required maxlength="40"></label>
				<label>Quantity *<input type="number" name="qty" min="1" max="99999" value="1" required inputmode="numeric"></label>
			</div>
			<input type="text" name="company_website" class="fva-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
			<input type="hidden" name="product_id" value="">
			<p class="fva-msg" role="status" aria-live="polite"></p>
			<button type="submit" class="fva-submit">Send request</button>
			<p class="fva-note">We’ll get back to you about availability and delivery time.</p>
		</form>
	</dialog>
	<style>
	.fva-pop{width:min(460px,calc(100vw - 32px));max-height:calc(100vh - 32px);padding:0;border:0;border-radius:20px;box-shadow:0 24px 60px rgba(0,0,0,.25);font-family:"Poppins",Arial,sans-serif;color:#1A1D21}
	.fva-pop::backdrop{background:rgba(0,0,0,.5)}
	.fva-form{padding:22px 24px 24px}
	.fva-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:12px}
	.fva-head h2{margin:0;font-size:20px;font-weight:600}
	.fva-pop .fva-close{width:36px;height:36px;padding:0;border:0;border-radius:50%;background:#F3F4F6;color:#1A1D21;font-size:22px;line-height:1;cursor:pointer}
	.fva-product{display:flex;flex-direction:column;gap:2px;margin:0 0 16px;padding:12px 14px;border-radius:12px;background:#F7F7F7;font-size:14px}
	.fva-product span{font-size:12px;color:#6B7280}
	.fva-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px}
	.fva-fields label{display:flex;flex-direction:column;gap:6px;font-size:13px;font-weight:500}
	.fva-fields label:first-child,.fva-fields label:nth-child(2){grid-column:1 / -1}
	.fva-fields input{width:100%;height:46px;padding:0 14px;border:1px solid #E3E3E3;border-radius:12px;background:#F7F7F7;font:inherit;font-size:15px;box-sizing:border-box}
	.fva-fields input:focus{outline:none;border-color:<?php echo esc_attr( $red ); ?>;background:#fff}
	.fva-hp{position:absolute !important;left:-9999px !important;width:1px;height:1px;opacity:0}
	.fva-msg{min-height:20px;margin:12px 0 0;font-size:14px}
	.fva-msg.is-error{color:<?php echo esc_attr( $red ); ?>}
	.fva-msg.is-ok{color:#17804A;font-weight:500}
	.fva-pop .fva-submit{width:100%;height:48px;margin-top:6px;border:0;border-radius:999px;background:<?php echo esc_attr( $red ); ?>;color:#fff;font:inherit;font-size:15px;font-weight:600;cursor:pointer}
	.fva-pop .fva-submit[disabled]{opacity:.7;cursor:progress}
	.fva-note{margin:10px 0 0;font-size:12px;color:#6B7280;text-align:center}
	@media (max-width:420px){.fva-fields{grid-template-columns:1fr}.fva-form{padding:18px}}
	</style>
	<script>
	(function () {
		var pop = document.getElementById('fva-pop'); if (!pop) return;
		var form = pop.querySelector('form'), msg = pop.querySelector('.fva-msg'), btn = pop.querySelector('.fva-submit');
		var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( wp_create_nonce( 'fva_request' ) ); ?>;
		function open(t) {
			form.reset(); msg.textContent = ''; msg.className = 'fva-msg'; btn.disabled = false; btn.textContent = 'Send request';
			form.product_id.value = t.getAttribute('data-id');
			var sku = t.getAttribute('data-sku');
			pop.querySelector('[data-fva-product]').textContent = (sku ? sku + ' – ' : '') + t.getAttribute('data-name');
			if (pop.showModal) pop.showModal(); else pop.setAttribute('open', '');
			setTimeout(function () { form.name.focus(); }, 50);
		}
		function close() { if (pop.close) pop.close(); else pop.removeAttribute('open'); }
		document.addEventListener('click', function (e) {
			var t = e.target.closest('[data-fva-open]');
			if (t) { e.preventDefault(); open(t); return; }
			if (e.target.closest('[data-fva-close]') || e.target === pop) close();
		});
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var bad = Array.prototype.find.call(form.querySelectorAll('input[required]'), function (i) { return !i.checkValidity(); });
			if (bad) { msg.className = 'fva-msg is-error'; msg.textContent = 'Please fill in all fields with valid details.'; bad.focus(); return; }
			var body = new FormData(form); body.append('action', 'fva_request'); body.append('nonce', nonce);
			btn.disabled = true; btn.textContent = 'Sending…'; msg.textContent = '';
			fetch(ajax, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						msg.className = 'fva-msg is-ok'; msg.textContent = res.data.message; btn.textContent = 'Sent ✓';
						setTimeout(close, 2500);
					} else {
						btn.disabled = false; btn.textContent = 'Send request';
						msg.className = 'fva-msg is-error'; msg.textContent = (res && res.data && res.data.message) || 'Something went wrong. Please try again.';
					}
				})
				.catch(function () { btn.disabled = false; btn.textContent = 'Send request'; msg.className = 'fva-msg is-error'; msg.textContent = 'Connection problem. Please try again.'; });
		});
	})();
	</script>
	<?php
}, 30 );

/* Send the request */
add_action( 'wp_ajax_fva_request', 'fva_handle_request' );
add_action( 'wp_ajax_nopriv_fva_request', 'fva_handle_request' );
function fva_handle_request() {
	check_ajax_referer( 'fva_request', 'nonce' );
	if ( ! empty( $_POST['company_website'] ) ) { // honeypot: bots fill it in
		wp_send_json_success( array( 'message' => 'Thank you! We’ll contact you soon.' ) );
	}

	$name  = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$phone = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$qty   = absint( $_POST['qty'] ?? 0 );
	$prod  = wc_get_product( absint( $_POST['product_id'] ?? 0 ) );

	if ( '' === $name || ! is_email( $email ) || strlen( preg_replace( '/\D/', '', $phone ) ) < 7 || $qty < 1 ) {
		wp_send_json_error( array( 'message' => 'Please fill in all fields with valid details.' ) );
	}
	if ( ! $prod || 'publish' !== $prod->get_status() ) {
		wp_send_json_error( array( 'message' => 'This product is no longer available.' ) );
	}

	// At most 5 requests per hour from one visitor
	$ip  = class_exists( 'WC_Geolocation' ) ? WC_Geolocation::get_ip_address() : ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore
	$key = 'fva_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 5 ) {
		wp_send_json_error( array( 'message' => 'Too many requests. Please try again later.' ) );
	}
	set_transient( $key, $n + 1, HOUR_IN_SECONDS );

	$to = get_option( 'woocommerce_stock_email_recipient' );
	if ( ! is_email( $to ) ) $to = get_option( 'admin_email' );
	$sku     = $prod->get_sku();
	$subject = sprintf( 'Availability request: %s – qty %d', $sku ? $sku : $prod->get_name(), $qty );
	$rows    = array(
		'Product'  => '<a href="' . esc_url( get_permalink( $prod->get_id() ) ) . '">' . esc_html( $prod->get_name() ) . '</a>',
		'SKU'      => esc_html( $sku ? $sku : '—' ),
		'Quantity' => (int) $qty,
		'Name'     => esc_html( $name ),
		'Email'    => '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>',
		'Phone'    => '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>',
	);
	$body = '<p>A customer asked about the availability of an out-of-stock product:</p><table cellpadding="8" cellspacing="0" border="0" style="border-collapse:collapse;font-size:14px">';
	foreach ( $rows as $label => $value ) {
		$body .= '<tr><td style="border-bottom:1px solid #eee;color:#6B7280;width:110px">' . esc_html( $label ) . '</td><td style="border-bottom:1px solid #eee"><strong>' . $value . '</strong></td></tr>';
	}
	$body .= '</table><p style="color:#6B7280;font-size:12px">Reply to this email to answer the customer directly.</p>';

	$headers = array( 'Reply-To: ' . $name . ' <' . $email . '>' );
	if ( function_exists( 'WC' ) && WC()->mailer() ) {
		$mailer = WC()->mailer(); // WooCommerce branded email layout
		$sent   = $mailer->send( $to, $subject, $mailer->wrap_message( 'Availability request', $body ), $headers, array() );
	} else {
		$headers[] = 'Content-Type: text/html; charset=UTF-8';
		$sent      = wp_mail( $to, $subject, $body, $headers );
	}
	if ( ! $sent ) {
		wp_send_json_error( array( 'message' => 'We couldn’t send your request. Please try again or contact us directly.' ) );
	}
	wp_send_json_success( array( 'message' => 'Thank you! We’ll contact you soon about this product.' ) );
}
