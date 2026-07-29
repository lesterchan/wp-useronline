/**
 * Tests for the front end refresh script.
 *
 * The script sets up its timers once, as it evaluates, so each assertion about
 * what it polls has to be made against a page that was already in place at
 * that moment. Fake timers stand in for the interval.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { containerMarkup, l10nFixture, loadScript } from './helper-dom.js';

/**
 * Put the page and the l10n object in place, then evaluate the script.
 *
 * @param {string[]} modes Containers to render.
 * @param {Object}   l10n  Overrides for the l10n fixture.
 */
function boot( modes, l10n = {} ) {
	document.body.innerHTML = containerMarkup( modes );
	window.wpUserOnlineL10n = { ...l10nFixture(), ...l10n };

	loadScript( 'js/wp-useronline.js' );
}

describe( 'wp-useronline front end', () => {
	beforeEach( () => {
		vi.useFakeTimers();

		window.fetch = vi.fn( () =>
			Promise.resolve( { ok: true, text: () => Promise.resolve( '<strong>7</strong> Online' ) } ),
		);
	} );

	afterEach( () => {
		vi.useRealTimers();
		vi.restoreAllMocks();
		delete window.wpUserOnlineL10n;
	} );

	// --- what it polls ----------------------------------------------------

	it( 'polls nothing until the interval elapses', () => {
		boot( [ 'count' ] );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'refreshes a container that is on the page', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'ignores containers that are not on the page', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'mode' ) ).toBe( 'count' );
	} );

	it( 'polls every container the page rendered', async () => {
		boot( [ 'count', 'browsing-site', 'browsing-page', 'details' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		const modes = window.fetch.mock.calls.map( ( call ) =>
			new URLSearchParams( call[ 1 ].body ).get( 'mode' ),
		);

		expect( modes.sort() ).toEqual( [ 'browsing-page', 'browsing-site', 'count', 'details' ] );
	} );

	it( 'keeps polling on every interval, not just the first', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 90000 );

		expect( window.fetch ).toHaveBeenCalledTimes( 3 );
	} );

	// --- the request itself -----------------------------------------------

	it( 'posts to the localised admin-ajax URL with the plugin action', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		const [ url, options ] = window.fetch.mock.calls[ 0 ];
		const body = new URLSearchParams( options.body );

		expect( url ).toBe( 'https://example.com/wp-admin/admin-ajax.php' );
		expect( options.method ).toBe( 'POST' );
		expect( options.credentials ).toBe( 'same-origin' );
		expect( body.get( 'action' ) ).toBe( 'wp_useronline' );
	} );

	it( 'sends the page URL and title the server records against', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		const body = new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body );

		expect( body.get( 'page_url' ) ).toBe(
			location.protocol + '//' + location.host + location.pathname + location.search,
		);
		expect( body.get( 'page_title' ) ).toBe( document.title );
	} );

	// --- what it does with the answer -------------------------------------

	it( 'replaces the container contents with the response', async () => {
		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		expect( document.getElementById( 'useronline-count' ).innerHTML ).toBe(
			'<strong>7</strong> Online',
		);
	} );

	it( 'leaves the last known values in place when the request fails', async () => {
		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		expect( document.getElementById( 'useronline-count' ).innerHTML ).toBe( 'old' );
	} );

	it( 'leaves them in place on an error status too', async () => {
		window.fetch = vi.fn( () =>
			Promise.resolve( { ok: false, status: 500, text: () => Promise.resolve( 'nope' ) } ),
		);

		boot( [ 'count' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		expect( document.getElementById( 'useronline-count' ).innerHTML ).toBe( 'old' );
	} );

	// --- the timeout guard ------------------------------------------------

	it( 'does not poll at all when the timeout is missing', async () => {
		boot( [ 'count' ], { timeout: '' } );

		await vi.advanceTimersByTimeAsync( 300000 );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );

	it( 'does not poll faster than once a second', async () => {
		boot( [ 'count' ], { timeout: '500' } );

		await vi.advanceTimersByTimeAsync( 300000 );

		expect( window.fetch ).not.toHaveBeenCalled();
	} );
} );
