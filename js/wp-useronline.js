/**
 * WP-UserOnline periodic refresh.
 *
 * Vanilla ES2017 with no build step and no library: the script ships to users
 * exactly as it is here. It polls admin-ajax.php for whichever of the four
 * containers the page actually rendered, and replaces their contents.
 */

( function() {
	'use strict';

	const MODES = [ 'count', 'browsing-site', 'browsing-page', 'details' ];

	function refresh( mode, target ) {
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
				target.innerHTML = html;
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
			const target = document.getElementById( 'useronline-' + mode );

			if ( target ) {
				setInterval( function() {
					refresh( mode, target );
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
