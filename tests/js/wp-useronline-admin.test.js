/**
 * Tests for the settings screen script.
 *
 * The listener lives on document, so the script is evaluated once for the file
 * and each test only replaces the markup underneath it.
 */
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { loadScript, settingsMarkup } from './helpers.js';

/**
 * Click the Restore Defaults button.
 */
function restore() {
	document
		.querySelector( '.wp-useronline-restore' )
		.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );
}

describe( 'wp-useronline settings screen', () => {
	beforeAll( () => {
		loadScript( 'js/wp-useronline-admin.js' );
	} );

	beforeEach( () => {
		document.body.innerHTML = settingsMarkup();
	} );

	it( 'puts every field in the group back to its own default', () => {
		restore();

		const fields = document.querySelectorAll( '#wp-useronline-naming input' );

		expect( fields[ 0 ].value ).toBe( '1 User' );
		expect( fields[ 1 ].value ).toBe( '%COUNT% Users' );
	} );

	it( 'leaves fields outside the button target alone', () => {
		restore();

		expect( document.getElementById( 'outside' ).value ).toBe( 'untouched' );
	} );

	it( 'restores when the click lands on something inside the button', () => {
		const button = document.querySelector( '.wp-useronline-restore' );
		button.innerHTML = '<span>Restore Defaults</span>';

		button.querySelector( 'span' ).dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } ),
		);

		expect( document.querySelector( '#wp-useronline-naming input' ).value ).toBe( '1 User' );
	} );

	it( 'ignores a click anywhere else on the screen', () => {
		document.getElementById( 'outside' ).dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true } ),
		);

		expect( document.querySelector( '#wp-useronline-naming input' ).value ).toBe( 'changed' );
	} );

	it( 'does nothing when the button points at a group that is not there', () => {
		const button = document.querySelector( '.wp-useronline-restore' );
		button.dataset.target = '#wp-useronline-nothing';

		expect( () => restore() ).not.toThrow();
		expect( document.querySelector( '#wp-useronline-naming input' ).value ).toBe( 'changed' );
	} );
} );
