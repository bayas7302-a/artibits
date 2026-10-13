( function () {
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}

	function init() {
	var C = window.ccbConfig || { cookie: 'ccb_consent', days: 182, version: 1, path: '/' };
	var bar = document.getElementById( 'ccb' );
	var modal = document.getElementById( 'ccb-modal' );
	if ( ! bar || ! modal ) {
		return;
	}

	function read() {
		var m = document.cookie.match( '(?:^|; )' + C.cookie + '=([^;]*)' );
		if ( ! m ) {
			return null;
		}
		try {
			var c = JSON.parse( decodeURIComponent( m[ 1 ] ) );
			return c && c.v === C.version ? c : null;
		} catch ( e ) {
			return null;
		}
	}

	function write( c ) {
		c.v = C.version;
		c.t = Date.now();
		document.cookie = C.cookie + '=' + encodeURIComponent( JSON.stringify( c ) ) +
			'; path=' + C.path + '; max-age=' + ( C.days * 86400 ) + '; SameSite=Lax' +
			( 'https:' === location.protocol ? '; Secure' : '' );
	}

	// Google Consent Mode v2.
	function consentUpdate( c ) {
		if ( typeof window.gtag !== 'function' ) {
			return;
		}
		window.gtag( 'consent', 'update', {
			analytics_storage: c.analytics ? 'granted' : 'denied',
			ad_storage: c.marketing ? 'granted' : 'denied',
			ad_user_data: c.marketing ? 'granted' : 'denied',
			ad_personalization: c.marketing ? 'granted' : 'denied'
		} );
	}

	// Turn blocked scripts of the allowed categories back into real ones, in page order.
	function unblock( c ) {
		var blocked = document.querySelectorAll( 'script[type="text/plain"][data-ccb]' );
		Array.prototype.forEach.call( blocked, function ( old ) {
			if ( ! c[ old.getAttribute( 'data-ccb' ) ] ) {
				return;
			}
			var s = document.createElement( 'script' );
			Array.prototype.forEach.call( old.attributes, function ( a ) {
				if ( 'type' === a.name || 'data-ccb' === a.name || 'data-ccb-src' === a.name ) {
					return;
				}
				s.setAttribute( a.name, a.value );
			} );
			if ( old.getAttribute( 'data-ccb-src' ) ) {
				s.src = old.getAttribute( 'data-ccb-src' );
				if ( ! old.hasAttribute( 'async' ) ) {
					s.async = false; // Keep the original order.
				}
			} else {
				s.text = old.text;
			}
			old.parentNode.replaceChild( s, old );
		} );
	}

	function apply( c ) {
		consentUpdate( c );
		unblock( c );
	}

	function show( el ) {
		el.hidden = false;
		requestAnimationFrame( function () {
			el.classList.add( 'is-visible' );
		} );
	}

	function hide( el ) {
		el.classList.remove( 'is-visible' );
		el.hidden = true;
	}

	function choose( c ) {
		var before = read();
		write( c );
		hide( bar );
		hide( modal );
		apply( c );
		// Withdrawing consent: reload so already-running trackers stop.
		if ( before && ( ( before.analytics && ! c.analytics ) || ( before.marketing && ! c.marketing ) ) ) {
			location.reload();
		}
	}

	function openSettings() {
		var c = read() || {};
		Array.prototype.forEach.call( modal.querySelectorAll( '[data-ccb-cat]' ), function ( box ) {
			box.checked = !! c[ box.getAttribute( 'data-ccb-cat' ) ];
		} );
		show( modal );
		var first = modal.querySelector( 'input, button' );
		if ( first ) {
			first.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var t = e.target.closest ? e.target.closest( '[data-ccb-action], .ccb-open' ) : null;
		if ( ! t ) {
			return;
		}
		if ( t.classList.contains( 'ccb-open' ) ) {
			e.preventDefault();
			openSettings();
			return;
		}
		var action = t.getAttribute( 'data-ccb-action' );
		if ( 'accept' === action ) {
			choose( { analytics: true, marketing: true } );
		} else if ( 'reject' === action ) {
			choose( { analytics: false, marketing: false } );
		} else if ( 'settings' === action ) {
			openSettings();
		} else if ( 'save' === action ) {
			var c = {};
			Array.prototype.forEach.call( modal.querySelectorAll( '[data-ccb-cat]' ), function ( box ) {
				c[ box.getAttribute( 'data-ccb-cat' ) ] = box.checked;
			} );
			choose( c );
		} else if ( 'close' === action ) {
			hide( modal );
			if ( ! read() ) {
				show( bar );
			}
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key && ! modal.hidden ) {
			hide( modal );
			if ( ! read() ) {
				show( bar );
			}
		}
	} );

	var saved = read();
	if ( saved ) {
		apply( saved );
	} else {
		show( bar );
	}

	// Allow other code to reopen the preferences: window.ccbOpen().
	window.ccbOpen = openSettings;
	}
} )();
