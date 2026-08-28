/**
 * WP-UserOnline settings screen. One delegated listener; every field carries
 * its own default in a data attribute, so nothing is localised into the page.
 */

( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		const button = event.target.closest( '.wp-useronline-restore' );

		if ( ! button ) {
			return;
		}

		const scope = document.querySelector( button.dataset.target );

		if ( ! scope ) {
			return;
		}

		scope
			.querySelectorAll( '[data-wp-useronline-default]' )
			.forEach( function( field ) {
				field.value = field.dataset.wpUseronlineDefault;
			} );
	} );
}() );
