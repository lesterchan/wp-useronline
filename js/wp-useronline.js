/**
 * WP-UserOnline periodic refresh. Polls admin-ajax.php for whichever containers
 * the page rendered, filling each -- or replacing it where the answer carries a
 * container of its own. See WRAPPED_MODES.
 */

( function() {
	'use strict';

	const MODES = [ 'count', 'browsing-site', 'browsing-page', 'details' ];

	// Modes whose answer already carries its own container. Writing one into
	// innerHTML would nest a second element with that id inside the first, and
	// another on every poll, so these replace the element instead.
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
			// Signed-in visitors only: the cookie alone would let a cross-site
			// post record them as reading a page of someone else's choosing.
			_ajax_nonce: wpUserOnlineL10n.nonce || '',
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
