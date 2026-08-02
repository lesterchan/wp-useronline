/**
 * WP-UserOnline periodic refresh.
 *
 * Vanilla ES2017 with no build step and no library: the script ships to users
 * exactly as it is here. It polls admin-ajax.php for whichever of the four
 * containers the page actually rendered, and writes each answer back into the
 * page -- filling the container, or replacing it outright where the answer
 * carries a container of its own. See WRAPPED_MODES.
 */

( function() {
	'use strict';

	const MODES = [ 'count', 'browsing-site', 'browsing-page', 'details' ];

	// The modes whose answer arrives already wrapped in the container it belongs
	// to. users_online_page() bakes #useronline-details into what it returns,
	// because [page_useronline] has no theme markup of its own to sit inside;
	// the other three answer with bare content that a theme or the widget has
	// already put a container around. Writing a wrapped answer into innerHTML
	// puts a second element carrying that id inside the first, and a third
	// inside that on the next poll, for as long as the page stays open -- so a
	// wrapped answer replaces the element rather than filling it.
	const WRAPPED_MODES = [ 'details' ];

	function swap( mode, target, html ) {
		if ( ! WRAPPED_MODES.includes( mode ) ) {
			target.innerHTML = html;

			return;
		}

		// An empty answer is dropped rather than swapped in: outerHTML would
		// take the container with it, and the next poll would find nothing left
		// to write to, so the refresh would stop for good without erroring.
		if ( html.trim() ) {
			target.outerHTML = html;
		}
	}

	function refresh( mode ) {
		// Looked up on every poll rather than held from init(), because a
		// wrapped answer replaces the element it was written to and a held
		// reference would be pointing at a node that is no longer in the page.
		const target = document.getElementById( 'useronline-' + mode );

		if ( ! target ) {
			return;
		}

		const body = new URLSearchParams( {
			action: 'wp_useronline',
			mode,
			page_url: location.protocol + '//' + location.host + location.pathname + location.search,
			page_title: document.title,
		} );

		fetch( wpUserOnlineL10n.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString(),
		} )
			.then( function( response ) {
				return response.ok ? response.text() : Promise.reject( response.status );
			} )
			.then( function( html ) {
				swap( mode, target, html );
			} )
			.catch( function() {
				// A failed refresh just leaves the last known values in place.
			} );
	}

	function init() {
		const timeout = parseInt( wpUserOnlineL10n.timeout, 10 );

		if ( ! timeout || timeout < 1000 ) {
			return;
		}

		MODES.forEach( function( mode ) {
			if ( document.getElementById( 'useronline-' + mode ) ) {
				setInterval( function() {
					refresh( mode );
				}, timeout );
			}
		} );
	}

	// The script is enqueued in the footer, but stay safe if it is ever loaded
	// after the document has finished parsing.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
