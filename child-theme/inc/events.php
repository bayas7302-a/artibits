<?php
/**
 * Events post type, /events/ page and popups – [fanvil_events].
 * Loaded by functions.php – do not load this file on its own.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

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

	if ( $end === $start || ! $end ) return date_i18n( 'F j, Y', $s ); // one-day event: always with the year
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
					<div class="fve-pop__media" style="--fve-bg:url('<?php echo esc_url( wp_get_attachment_image_url( $img, 'medium' ) ); ?>')"><?php echo wp_get_attachment_image( $img, 'large', false, array( 'alt' => $title ) ); ?></div>
				<?php endif; ?>
				<div class="fve-pop__body">
					<h2 class="fve-pop__title"><?php echo esc_html( $title ); ?></h2>
					<ul class="fve-pop__meta">
						<?php if ( $date ) : ?><li><span>Date</span><strong><?php echo esc_html( $date ); ?></strong></li><?php endif; ?>
						<?php if ( $time ) : ?><li><span>Time</span><strong><?php echo esc_html( $time ); ?></strong></li><?php endif; ?>
						<?php if ( $location ) : ?><li><span>Location</span><strong><?php echo esc_html( $location ); ?></strong></li><?php endif; ?>
					</ul>
					<?php if ( trim( $content ) ) : ?>
						<div class="fve-pop__desc"><?php echo wp_kses_post( wpautop( make_clickable( do_shortcode( $content ) ) ) ); // web addresses become links ?></div>
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
.fve .fve-card{position:relative;display:flex;flex-direction:column;align-items:center;gap:4px;width:100%;height:100%;margin:0;padding:26px 24px 20px;border:0;border-radius:18px;background:var(--fve-card);color:var(--fve-ink);font:inherit;text-align:center;text-transform:none;letter-spacing:normal;cursor:pointer;box-shadow:none;transition:transform .2s,box-shadow .2s;white-space:normal;overflow:hidden}
.fve .fve-card:hover,.fve .fve-card:focus-visible{background:var(--fve-card);color:var(--fve-ink);transform:translateY(-3px);box-shadow:0 14px 30px rgba(0,0,0,.08)}
.fve .fve-card:focus-visible{outline:3px solid var(--fve-red);outline-offset:3px}
.fve-card__title,.fve-card__loc,.fve-card__date{display:block;width:100%;max-width:100%;white-space:normal;overflow-wrap:anywhere}
.fve-card__title{font-size:17px;font-weight:700;line-height:1.3;text-transform:uppercase}
.fve-card__loc{margin-top:2px;font-size:14px;line-height:1.45;color:#4B5563}
.fve-card__date{font-size:14px;font-weight:600;color:var(--fve-red)}
.fve-card__media{display:block;width:100%;height:auto;aspect-ratio:4/5;margin:16px 0 20px;border-radius:12px;overflow:hidden;background:#E6E8EB}
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
.fve-pop__inner{display:grid;grid-template-columns:minmax(0,5fr) minmax(0,6fr);height:min(calc(100vh - 48px),640px)}
.fve-pop__media{position:relative;height:100%;min-height:0;background:#1A1D21;overflow:hidden}
.fve-pop__media::before{content:"";position:absolute;inset:-40px;background:var(--fve-bg) center/cover no-repeat;filter:blur(28px) brightness(.75);transform:scale(1.1)}
.fve-pop__media img{position:relative;z-index:1;display:block;width:100% !important;height:100% !important;max-width:none;margin:0;object-fit:contain;object-position:center}
.fve-pop__body{min-height:0;padding:36px 32px 32px;overflow-y:auto;overscroll-behavior:contain}
.fve-pop .fve-pop__title{margin:0 0 18px;padding-right:32px;font-size:20px;font-weight:700;line-height:1.25;color:#1A1D21;text-transform:uppercase}
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
	.fve-card__title{font-size:16px}
	.fve-card__loc,.fve-card__date{font-size:14px}
	.fve-card__media{aspect-ratio:4/5}
	.fve-pop{width:calc(100vw - 20px);max-height:calc(100vh - 20px)}
	.fve-pop__inner{grid-template-columns:1fr}
	.fve-pop__inner{height:auto}
	.fve-pop__media{height:min(55vh,420px)}
	.fve-pop__body{overflow:visible}
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
