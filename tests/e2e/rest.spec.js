/**
 * The REST routes.
 *
 * Two routes -- read the counts, and the visitor heartbeat -- under
 * `useronline/v1`, a bare noun rather than the plugin slug.
 *
 * The PHPUnit suite already dispatches these through WP_REST_Server, so what is
 * worth testing here is only what the HTTP layer decides: that the namespace is
 * the one that got registered, that a visitor who is not logged in can be
 * recorded by it -- which is most of this plugin's traffic, and who a dispatcher
 * test cannot impersonate -- and that the AJAX endpoint these sit beside still
 * answers, because it was kept on purpose.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { onlineRows, truncateOnline, uniqueTitle } = require( './helpers.js' );

/** Every route lives under this namespace. */
const NAMESPACE = '/useronline/v1';

test.describe( 'The REST routes', () => {
	test.beforeEach( async () => {
		truncateOnline();
	} );

	test( 'the fixture really is the namespace this plugin registered', async ( {
		requestUtils,
	} ) => {
		// Every call below is under one namespace. If it were ever renamed, all
		// of them would 404 and the "refused" test would pass for the wrong
		// reason.
		const index = await requestUtils.rest( { path: '/' } );

		expect( index.namespaces ).toContain( 'useronline/v1' );
		expect( index.namespaces ).not.toContain( 'wp-useronline/v1' );
	} );

	test( 'reading the count records nobody', async ( { requestUtils } ) => {
		const before = onlineRows().length;

		const counts = await requestUtils.rest( { path: `${ NAMESPACE }/count` } );

		expect( counts ).toHaveProperty( 'online' );
		// A monitoring script polling this must not appear in the figure it is
		// reading, which is the whole reason the read and the heartbeat are two
		// routes rather than one.
		expect( onlineRows().length ).toBe( before );
	} );

	test( 'a mode the endpoint does not render is refused', async ( { request } ) => {
		const response = await request.post(
			`/index.php?rest_route=${ NAMESPACE }/visit`,
			{ form: { mode: 'not-a-mode', page_url: '/' } },
		);

		expect( response.status() ).toBe( 400 );
		expect( onlineRows() ).toHaveLength( 0 );
	} );

	// Everything below runs without the administrator's cookies, because
	// playwright.config.js sets `use.storageState` for the whole suite and the
	// `request` fixture inherits it like any other. This plugin's traffic is
	// mostly logged out, so the guest path is the one that matters.
	test.describe( 'as a visitor who is not logged in', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'the fixture really is logged out', async ( { request } ) => {
			// Without this the test below proves nothing on the day somebody
			// changes the storage state: it would pass as the administrator too.
			const me = await request.get( '/index.php?rest_route=/wp/v2/users/me' );

			expect( me.status() ).toBe( 401 );
		} );

		test( 'a heartbeat records a logged-out visitor, with no nonce at all', async ( {
			request,
			baseURL,
		} ) => {
			// No nonce is sent, and none is expected. An anonymous nonce comes
			// from a session every logged-out caller shares, so requiring one
			// would authenticate nobody while breaking every such visitor.
			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/visit`,
				{
					form: {
						mode: 'count',
						// Absolute, because local_url() requires a host and
						// answers null without one -- which is exactly what the
						// plugin's own script sends. A relative path here records
						// nothing and the test would fail against working code.
						page_url: `${ baseURL }/hello-world/`,
						page_title: 'Hello world',
					},
				},
			);

			expect( response.status() ).toBe( 200 );

			const rows = onlineRows();

			expect( rows ).toHaveLength( 1 );
			expect( rows[ 0 ].user_type ).toBe( 'guest' );
			expect( rows[ 0 ].page_title ).toBe( 'Hello world' );
		} );

		test( 'a page on another site is not recorded', async ( { request } ) => {
			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/visit`,
				{
					form: {
						mode: 'count',
						page_url: 'https://example.org/elsewhere/',
					},
				},
			);

			expect( response.status() ).toBe( 200 );
			expect( onlineRows() ).toHaveLength( 0 );
		} );
	} );

	// Kept on purpose: a theme or a cached script may still be calling it. If
	// this ever 404s, the routes above stopped being an addition and became a
	// replacement.
	test.describe( 'the AJAX endpoint these sit beside', () => {
		test.describe( 'as a visitor who is not logged in', () => {
			test.use( { storageState: { cookies: [], origins: [] } } );

			test( 'still answers, with no nonce at all', async ( { request } ) => {
				// Which is most of this endpoint's traffic. An anonymous nonce
				// comes from a session every logged-out caller shares, so
				// requiring one would authenticate nobody and break everybody.
				const response = await request.post( '/wp-admin/admin-ajax.php', {
					form: {
						action: 'wp_useronline',
						mode: 'count',
						page_url: '/hello-world/',
					},
				} );

				expect( response.status() ).toBe( 200 );
			} );
		} );

		test( 'refuses a signed-in caller who sends no nonce', async ( { request } ) => {
			// admin-ajax authenticates by cookie alone, so this shape of
			// request is what a cross-site POST looks like: the browser sends
			// the session and the page it came from supplies everything else.
			// It used to be served, and the visitor's own row was overwritten
			// with the attacker's page and title.
			const response = await request.post( '/wp-admin/admin-ajax.php', {
				form: {
					action: 'wp_useronline',
					mode: 'count',
					page_url: '/hello-world/',
				},
			} );

			expect( response.status() ).toBe( 403 );
		} );

		test( 'answers a signed-in caller who sends the nonce the page gave its script', async ( {
			page,
			request,
			requestUtils,
			baseURL,
		} ) => {
			// The nonce is minted against the browser's session, so it has to
			// be read off a rendered page rather than made in the container:
			// wp_create_nonce() under WP-CLI has no session token to bind to
			// and produces one this endpoint would refuse.
			//
			// A post carrying the shortcode, because the script is enqueued
			// only where something on the page needs it.
			const shortcodePost = await requestUtils.createPost( {
				title: uniqueTitle( 'Who is online nonce' ),
				content: '[page_useronline]',
				status: 'publish',
			} );

			await page.goto( shortcodePost.link );

			const nonce = await page.evaluate(
				() => window.wpUserOnlineL10n && window.wpUserOnlineL10n.nonce,
			);

			expect( nonce, 'The script was enqueued and localized.' ).toBeTruthy();

			const response = await request.post( '/wp-admin/admin-ajax.php', {
				form: {
					action: 'wp_useronline',
					mode: 'count',
					page_url: `${ baseURL }/hello-world/`,
					_ajax_nonce: nonce,
				},
			} );

			expect( response.status() ).toBe( 200 );
		} );
	} );
} );
