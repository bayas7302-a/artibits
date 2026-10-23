( function () {
	function closeAll( except ) {
		document.querySelectorAll( '.est-switcher--dropdown.is-open' ).forEach( function ( el ) {
			if ( el !== except ) {
				el.classList.remove( 'is-open' );
				var btn = el.querySelector( '.est-switcher__current' );
				if ( btn ) {
					btn.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '.est-switcher__current' ) : null;
		if ( ! btn ) {
			closeAll();
			return;
		}
		e.preventDefault();
		var box = btn.closest( '.est-switcher--dropdown' );
		var open = ! box.classList.contains( 'is-open' );
		closeAll( box );
		box.classList.toggle( 'is-open', open );
		btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closeAll();
		}
	} );
} )();
