/**
 * Tests for the front end refresh script.
 *
 * The script sets up its timers once, as it evaluates, so each assertion about
 * what it polls has to be made against a page that was already in place at
 * that moment. Fake timers stand in for the interval.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { containerMarkup, detailsMarkup, l10nFixture, loadScript } from './helper-dom.js';

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

	// --- the answer that carries its own container ------------------------

	/**
	 * Answer every poll the way the details mode of the endpoint does.
	 *
	 * The markup is the one the plugin's PHP emits, container included, with a
	 * counter in the body so one refresh can be told from the next.
	 */
	function serveDetails() {
		let poll = 0;

		window.fetch = vi.fn( () => {
			poll += 1;

			return Promise.resolve( {
				ok: true,
				text: () => Promise.resolve( detailsMarkup( '<p>poll ' + poll + '</p>' ) ),
			} );
		} );
	}

	it( 'replaces the details container rather than writing a second one inside it', async () => {
		serveDetails();

		boot( [ 'details' ] );

		await vi.advanceTimersByTimeAsync( 30000 );

		expect( document.querySelectorAll( '#useronline-details' ) ).toHaveLength( 1 );
		expect( document.getElementById( 'useronline-details' ).innerHTML ).toBe( '<p>poll 1</p>' );
	} );

	it( 'still holds exactly one details container after several refreshes', async () => {
		serveDetails();

		boot( [ 'details' ] );

		await vi.advanceTimersByTimeAsync( 150000 );

		// Five polls, one container. Nesting was silent -- nothing threw, the
		// figures kept updating, and the page simply grew a level deeper every
		// timeout for as long as it stayed open.
		expect( window.fetch ).toHaveBeenCalledTimes( 5 );
		expect( document.querySelectorAll( '#useronline-details' ) ).toHaveLength( 1 );
		expect( document.getElementById( 'useronline-details' ).innerHTML ).toBe( '<p>poll 5</p>' );
	} );

	it( 'keeps refreshing the details container it has already replaced', async () => {
		serveDetails();

		boot( [ 'details' ] );

		await vi.advanceTimersByTimeAsync( 60000 );

		// The element the first refresh wrote is not the one init() found, so a
		// held reference would leave this stuck at the first answer while the
		// requests carried on.
		expect( document.body.contains( document.getElementById( 'useronline-details' ) ) ).toBe(
			true,
		);
		expect( document.getElementById( 'useronline-details' ).innerHTML ).toBe( '<p>poll 2</p>' );
	} );

	it( 'leaves the details container alone when the answer is empty', async () => {
		window.fetch = vi.fn( () =>
			Promise.resolve( { ok: true, text: () => Promise.resolve( '' ) } ),
		);

		boot( [ 'details' ] );

		await vi.advanceTimersByTimeAsync( 60000 );

		// Swapping an empty answer in would take the container with it, and the
		// next poll would find nothing left to write to.
		expect( document.querySelectorAll( '#useronline-details' ) ).toHaveLength( 1 );
		expect( document.getElementById( 'useronline-details' ).innerHTML ).toBe( 'old' );
	} );

	it( 'fills the other containers instead of replacing them', async () => {
		boot( [ 'count' ] );

		const before = document.getElementById( 'useronline-count' );

		await vi.advanceTimersByTimeAsync( 30000 );

		// The three unwrapped modes answer with bare content for a container a
		// theme wrote, so the element a theme styled has to survive the poll.
		expect( document.getElementById( 'useronline-count' ) ).toBe( before );
		expect( document.querySelectorAll( '#useronline-count' ) ).toHaveLength( 1 );
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
