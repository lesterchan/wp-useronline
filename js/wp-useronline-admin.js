/**
 * WP-UserOnline settings screen.
 *
 * One delegated listener on document, so the Restore Defaults buttons keep
 * working whatever the Settings API renders around them. Every field carries
 * its own default in a data attribute; there is nothing to look up and nothing
 * localised into the page.
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
