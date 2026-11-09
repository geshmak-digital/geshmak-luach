/* בס״ד Luach settings: Hebcal city search -> GeoNames ID, timezone, elevation. */
( function () {
	'use strict';

	var cfg = window.geshmakLuachLocation;
	var box = document.getElementById( 'geshmak-luach-lookup' );
	if ( ! cfg || ! box ) {
		return;
	}

	var input = box.querySelector( '.geshmak-luach-lookup__q' );
	var list = box.querySelector( '.geshmak-luach-lookup__results' );
	var status = box.querySelector( '.geshmak-luach-lookup__status' );
	var idField = document.querySelector( '[name$="[geonameid]"]' );
	var nameField = document.querySelector( '[name$="[location_name]"]' );
	var tzField = document.querySelector( '[name$="[tzid]"]' );
	var elevField = document.querySelector( '[name$="[elevation]"]' );
	var timer = null;
	var seq = 0;

	function get( params ) {
		var url = cfg.ajaxUrl + '?' + new URLSearchParams( Object.assign( { nonce: cfg.nonce }, params ) ).toString();
		return fetch( url, { credentials: 'same-origin' } ).then( function ( r ) {
			return r.json();
		} );
	}

	function say( msg ) {
		status.textContent = msg || '';
	}

	function clearList() {
		list.innerHTML = '';
		list.hidden = true;
	}

	function pick( row ) {
		clearList();
		input.value = row.label;
		if ( idField ) {
			idField.value = row.id;
		}
		if ( nameField ) {
			nameField.value = row.label;
		}
		say( '' );
		get( { action: 'geshmak_luach_location_details', id: row.id } ).then( function ( res ) {
			if ( ! res || ! res.success ) {
				return;
			}
			if ( tzField && res.data.tzid ) {
				tzField.value = res.data.tzid;
			}
			if ( elevField && res.data.elevation ) {
				elevField.value = res.data.elevation;
			}
			say( cfg.i18n.filled );
		} );
	}

	function render( rows ) {
		list.innerHTML = '';
		if ( ! rows.length ) {
			say( cfg.i18n.noResults );
			list.hidden = true;
			return;
		}
		rows.forEach( function ( row ) {
			var li = document.createElement( 'li' );
			li.setAttribute( 'role', 'option' );
			li.tabIndex = 0;
			li.textContent = ( row.flag ? row.flag + ' ' : '' ) + row.label;
			li.addEventListener( 'click', function () {
				pick( row );
			} );
			li.addEventListener( 'keydown', function ( e ) {
				if ( 'Enter' === e.key ) {
					e.preventDefault();
					pick( row );
				} else if ( 'ArrowDown' === e.key && li.nextSibling ) {
					e.preventDefault();
					li.nextSibling.focus();
				} else if ( 'ArrowUp' === e.key ) {
					e.preventDefault();
					( li.previousSibling || input ).focus();
				}
			} );
			list.appendChild( li );
		} );
		list.hidden = false;
	}

	input.addEventListener( 'keydown', function ( e ) {
		if ( 'Enter' === e.key ) {
			e.preventDefault(); // never submit the settings form from the search box.
		} else if ( 'ArrowDown' === e.key && list.firstChild ) {
			e.preventDefault();
			list.firstChild.focus();
		}
	} );

	input.addEventListener( 'input', function () {
		clearTimeout( timer );
		var q = input.value.trim();
		if ( q.length < 2 ) {
			clearList();
			say( '' );
			return;
		}
		timer = setTimeout( function () {
			var mine = ++seq;
			get( { action: 'geshmak_luach_location_search', q: q } )
				.then( function ( res ) {
					if ( mine !== seq ) {
						return;
					}
					if ( res && res.success ) {
						say( '' );
						render( res.data );
					} else {
						clearList();
						say( res && res.data && res.data.message ? res.data.message : cfg.i18n.failed );
					}
				} )
				.catch( function () {
					clearList();
					say( cfg.i18n.failed );
				} );
		}, 250 );
	} );

	// A hand-typed ID no longer matches the remembered place name.
	if ( idField ) {
		idField.addEventListener( 'input', function () {
			if ( nameField ) {
				nameField.value = '';
			}
			input.value = '';
		} );
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! box.contains( e.target ) ) {
			clearList();
		}
	} );
}() );
