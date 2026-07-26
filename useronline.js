/**
 * WP-UserOnline periodic refresh.
 *
 * Vanilla JS: as of 3.0.0 this no longer depends on jQuery.
 *
 * @package WP-UserOnline
 */

( function () {
	'use strict';

	var MODES = [ 'count', 'browsing-site', 'browsing-page', 'details' ];

	function refresh( mode, target ) {
		var body = new URLSearchParams(
			{
				action: 'useronline',
				mode: mode,
				page_url: location.protocol + '//' + location.host + location.pathname + location.search,
				page_title: document.title
			}
		);

		fetch(
			useronlineL10n.ajax_url,
			{
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			}
		)
			.then(
				function ( response ) {
					return response.ok ? response.text() : Promise.reject( response.status );
				}
			)
			.then(
				function ( html ) {
					target.innerHTML = html;
				}
			)
			.catch(
				function () {
					// A failed refresh just leaves the last known values in place.
				}
			);
	}

	function init() {
		var timeout = parseInt( useronlineL10n.timeout, 10 );

		if ( ! timeout || timeout < 1000 ) {
			return;
		}

		MODES.forEach(
			function ( mode ) {
				var target = document.getElementById( 'useronline-' + mode );

				if ( target ) {
						setInterval(
							function () {
								refresh( mode, target );
							},
							timeout
						);
				}
			}
		);
	}

	// The script is enqueued in the footer, but stay safe if it is ever loaded
	// after the document has finished parsing.
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
