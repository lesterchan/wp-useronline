/**
 * The admin surface: the menu, the one page under it and its three tabs, the At
 * a Glance summary, the capability that gates the lot, and the admin-ajax.php
 * endpoint the refresh script talks to.
 *
 * Two capabilities are at work and they are not the same thing. 'useronline'
 * gates the report tab and 'settings' gates the other two -- and since all three
 * are one page now, the page is registered under the *lower* of the two and each
 * tab checks its own. Widening the report must not hand over the settings form,
 * which is the whole reason the split exists. 'details' gates two pieces of the
 * listing
 * that are nobody else's business -- every visitor's IP address, and the
 * location of anyone who is inside wp-admin -- on the screen and in the
 * front-end shortcode alike, which is why it is tested through both.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ONLINE_URL,
	SETTINGS_URL,
	TEMPLATES_URL,
	asGuest,
	ensureUser,
	field,
	insertOnline,
	loginAs,
	onlineRows,
	resetOptions,
	restoreCapabilities,
	setMost,
	setOptions,
	truncateOnline,
	uniqueTitle,
	widenCapability,
} = require( './helpers.js' );

/** A password for the throwaway accounts the capability tests log in as. */
const PASSWORD = 'correct-horse-battery-staple';

test.describe( 'The admin screens', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async () => {
		resetOptions();
		// An hour, so a row inserted a moment ago is never swept by the very
		// request that is about to read it.
		setOptions( { timeout: 3600 } );
		truncateOnline();
	} );

	test.afterEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test( 'the fixture really is one menu entry with no submenu of its own', async ( { page } ) => {
		// Every navigation test below leans on the menu existing at all, and on
		// the plugin having exactly one page: the report, the settings and the
		// templates are tabs, not submenu entries, and before 4.0.0 two of them
		// were scattered between Dashboard and Settings entirely.
		await page.goto( '/wp-admin/index.php' );

		const menu = page.locator( '#adminmenu li', { hasText: 'WP-UserOnline' } ).first();

		await expect( menu.getByRole( 'link', { name: 'WP-UserOnline', exact: true } ) ).toBeAttached();
		// Scoped to this plugin's own menu item: core's Settings menu is in
		// #adminmenu too, and an unscoped role query matches both.
		await expect( menu.getByRole( 'link', { name: 'Settings', exact: true } ) ).toHaveCount( 0 );
		await expect( menu.locator( '.wp-submenu li' ) ).toHaveCount( 0 );
	} );

	test( 'the menu entry opens the page on the report tab', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		// Through the menu, the way a person reaches it.
		await page
			.locator( '#adminmenu li', { hasText: 'WP-UserOnline' } )
			.first()
			.getByRole( 'link', { name: 'WP-UserOnline', exact: true } )
			.click();

		await expect( page.getByRole( 'heading', { name: 'Users Online Now' } ) ).toBeVisible();
		expect( page.url() ).toContain( 'admin.php?page=wp-useronline' );
		await expect( page.locator( '#useronline-details' ) ).toBeAttached();
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( 'Users Online' );
	} );

	test( 'the three tabs are the report, the settings and the templates, in that order', async ( {
		page,
	} ) => {
		await page.goto( ONLINE_URL );

		await expect( page.locator( '.nav-tab-wrapper .nav-tab' ) ).toHaveText( [
			'Users Online',
			'Settings',
			'Templates',
		] );

		// Scoped to the tab strip: core's own Settings menu is a link in
		// #adminmenu, and an unscoped role query matches both.
		await page
			.locator( '.nav-tab-wrapper' )
			.getByRole( 'link', { name: 'Settings', exact: true } )
			.click();

		await expect( page.getByRole( 'heading', { name: 'UserOnline Settings' } ) ).toBeVisible();
		expect( page.url() ).toContain( 'page=wp-useronline&tab=settings' );
	} );

	test( 'a subscriber gets no tab at all, and an administrator gets all three', async ( {
		page,
	} ) => {
		// Both directions in one test on purpose. "The subscriber sees nothing"
		// passes just as well with the plugin deactivated; the administrator
		// half is what proves the gate is the capability rather than a missing
		// page.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-UserOnline' );

		await page.goto( ONLINE_URL );
		await expect( page.getByRole( 'heading', { name: 'Users Online Now' } ) ).toBeVisible();
		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { name: 'UserOnline Settings' } ) ).toBeVisible();
		await page.goto( TEMPLATES_URL );
		await expect( page.getByRole( 'heading', { name: 'UserOnline Settings' } ) ).toBeVisible();

		ensureUser( 'useronline_subscriber', 'subscriber', PASSWORD );
		const other = await loginAs( page, 'useronline_subscriber', PASSWORD );

		await other.goto( '/wp-admin/index.php' );
		await expect( other.locator( '#adminmenu' ).getByText( 'WP-UserOnline' ) ).toHaveCount( 0 );

		await other.goto( ONLINE_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await other.goto( SETTINGS_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await other.context().close();
	} );

	test( 'widening the report tab does not hand over the settings tabs', async ( { page } ) => {
		// The one that would be a privilege escalation rather than a bug. All
		// three tabs are one admin page, and that page has to be registered
		// under the report's capability -- the lower of the two -- or widening
		// the report would not reach it at all. So each tab checks its own
		// context, and this is what says so: an editor handed the report must
		// not be handed the settings form with it.
		widenCapability( 'useronline', 'edit_posts' );

		try {
			ensureUser( 'useronline_editor', 'editor', PASSWORD );
			const editor = await loginAs( page, 'useronline_editor', PASSWORD );

			await editor.goto( ONLINE_URL );
			await expect( editor.getByRole( 'heading', { name: 'Users Online Now' } ) ).toBeVisible();

			// And the tab strip does not even offer the other two, because a
			// link that dies on arrival is worse than no link.
			await expect( editor.locator( '.nav-tab-wrapper .nav-tab' ) ).toHaveText( [
				'Users Online',
			] );

			for ( const url of [ SETTINGS_URL, TEMPLATES_URL ] ) {
				await editor.goto( url );
				await expect( editor.locator( 'body' ) ).toContainText(
					/do not have permission to access this page/,
				);
				await expect( editor.locator( field( 'timeout' ) ) ).toHaveCount( 0 );
				await expect(
					editor.locator( field( 'templates', 'useronline' ) ),
				).toHaveCount( 0 );
			}

			await editor.context().close();
		} finally {
			restoreCapabilities();
		}
	} );

	test( 'the At a Glance panel carries the count, the browsing list and the record', async ( {
		page,
	} ) => {
		setMost( 4321, 86400 );
		insertOnline( { userType: 'member', userId: 7, userName: 'Ada', userIp: '203.0.113.41' } );

		await page.goto( '/wp-admin/index.php' );

		const panel = page.locator( '#dashboard_right_now' );

		// Two rows: the fixture and the administrator, whom admin_head recorded
		// on the way into this very screen.
		await expect( panel ).toContainText( '2 users' );
		await expect( panel ).toContainText( 'Ada' );
		await expect( panel ).toContainText( 'Most users ever online were' );
		await expect( panel ).toContainText( '4,321' );

		// The count is a link into the report screen, which is the only route
		// to it the dashboard offers.
		await expect( panel.locator( 'a[href*="page=wp-useronline"]' ) ).toBeAttached();
	} );

	test( 'the IP address is shown to a privileged viewer and to nobody else', async ( {
		page,
		requestUtils,
	} ) => {
		insertOnline( {
			userType: 'guest',
			userName: 'Passer-by',
			userIp: '203.0.113.55',
			userAgent: 'Fixture/1.0',
			pageUrl: '/',
		} );

		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Who is online' ),
			content: '[page_useronline]',
			status: 'publish',
		} );

		// The administrator, who has the 'details' capability. The fixture's own
		// address rather than any lookup link, because the administrator reading
		// the screen was recorded on the way in and has an address of their own.
		await page.goto( ONLINE_URL );
		await expect( page.locator( '#useronline-details' ) ).toContainText( '203.0.113.55' );
		await expect(
			page.locator( '#useronline-details a[href*="ipinfo.io/203.0.113.55"]' ),
		).toBeAttached();

		// The same list, rendered by the shortcode for a logged out visitor.
		// The row is still there -- the guest can see that somebody is online
		// -- but not the address.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			await expect( guest.locator( '#useronline-details' ) ).toContainText( 'Passer-by' );
			await expect( guest.locator( '#useronline-details' ) ).not.toContainText( '203.0.113.55' );
			await expect(
				guest.locator( '#useronline-details a[href*="ipinfo.io"]' ),
			).toHaveCount( 0 );
		} );
	} );

	test( 'where somebody is inside wp-admin is shown to a privileged viewer only', async ( {
		page,
		requestUtils,
	} ) => {
		insertOnline( {
			userType: 'member',
			userId: 9,
			userName: 'Grace',
			userIp: '203.0.113.56',
			pageTitle: 'Secret admin screen',
			pageUrl: '/wp-admin/options-general.php',
			referral: 'https://example.com/came-from-here',
		} );

		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Who is online admin' ),
			content: '[page_useronline]',
			status: 'publish',
		} );

		await page.goto( ONLINE_URL );
		await expect( page.locator( '#useronline-details' ) ).toContainText( 'Secret admin screen' );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			// The person is still listed by name; only their location and the
			// links to it are withheld.
			await expect( guest.locator( '#useronline-details' ) ).toContainText( 'Grace' );
			await expect( guest.locator( '#useronline-details' ) ).not.toContainText(
				'Secret admin screen',
			);
			await expect(
				guest.locator( '#useronline-details a[href*="options-general.php"]' ),
			).toHaveCount( 0 );
			await expect(
				guest.locator( '#useronline-details a[href*="came-from-here"]' ),
			).toHaveCount( 0 );
		} );
	} );

	test( 'a front-end row is still shown in full to a logged out visitor', async ( {
		page,
		requestUtils,
	} ) => {
		// The other half of the test above: only wp-admin locations are hidden,
		// so a listing that showed nothing at all to a guest would pass that
		// test and be broken.
		insertOnline( {
			userType: 'guest',
			userName: 'Reader',
			userIp: '203.0.113.57',
			pageTitle: 'A public post',
			pageUrl: '/?p=123',
		} );

		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Who is online public' ),
			content: '[page_useronline]',
			status: 'publish',
		} );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			await expect( guest.locator( '#useronline-details' ) ).toContainText( 'A public post' );
			await expect( guest.locator( '#useronline-details a[href*="p=123"]' ) ).toBeAttached();
		} );
	} );
} );

test.describe( 'The refresh endpoint', () => {
	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Endpoint post' ),
			content: 'Body text.',
			status: 'publish',
		} );
	} );

	test.beforeEach( async () => {
		resetOptions();
		setOptions( { timeout: 3600 } );
		truncateOnline();
	} );

	test.afterEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	/**
	 * Call the endpoint the way js/wp-useronline.js does.
	 *
	 * @param {import('@playwright/test').Page} target Page whose session and origin to use.
	 * @param {Object}                          form   The posted fields.
	 * @return {Promise<string>} The response body.
	 */
	async function refresh( target, form ) {
		const response = await target.request.post( '/wp-admin/admin-ajax.php', {
			form: { action: 'wp_useronline', ...form },
		} );

		expect( response.status() ).toBe( 200 );

		return response.text();
	}

	test( 'the fixture really is an endpoint a logged out caller can reach', async ( { page } ) => {
		// There is no nonce on this endpoint on purpose -- an anonymous nonce
		// comes from a session shared by every such caller and proves nothing --
		// so the rest of this file leans on a guest getting a real answer.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			const body = await refresh( guest, {
				mode: 'count',
				page_url: `${ new URL( post.link ).origin }/`,
				page_title: 'Home',
			} );

			expect( body ).toContain( 'Online' );
		} );
	} );

	test( 'each mode answers with the markup its own container expects', async ( { page } ) => {
		insertOnline( { userType: 'member', userId: 11, userName: 'Ada', userIp: '203.0.113.61' } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			const origin = new URL( post.link ).origin;

			// Every mode is asked for on the same request state, so the three
			// answers differing is the modes rather than the table changing
			// underneath them.
			expect( await refresh( guest, { mode: 'count', page_url: `${ origin }/` } ) ).toContain(
				'Online',
			);

			const site = await refresh( guest, { mode: 'browsing-site', page_url: `${ origin }/` } );
			expect( site ).toContain( 'Ada' );

			const details = await refresh( guest, { mode: 'details', page_url: `${ origin }/` } );
			expect( details ).toContain( 'useronline-details' );
			expect( details ).toContain( 'Most users ever online were' );
		} );
	} );

	test( 'browsing-page answers about the submitted page rather than the whole site', async ( {
		page,
	} ) => {
		insertOnline( {
			userType: 'member',
			userId: 12,
			userName: 'Grace',
			userIp: '203.0.113.62',
			pageUrl: '/somewhere-else',
		} );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			const origin = new URL( post.link ).origin;

			const here = await refresh( guest, {
				mode: 'browsing-page',
				page_url: `${ origin }/only-here`,
				page_title: 'Only here',
			} );

			// The caller has just been recorded on /only-here, so it is the one
			// row there; Grace is on another page and must not be counted in.
			expect( here ).not.toContain( 'Grace' );

			const everywhere = await refresh( guest, {
				mode: 'browsing-site',
				page_url: `${ origin }/only-here`,
			} );
			expect( everywhere ).toContain( 'Grace' );
		} );
	} );

	test( 'a call records the caller under the page it says it is on', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			truncateOnline();

			await refresh( guest, {
				mode: 'count',
				page_url: `${ new URL( post.link ).origin }/some/path?with=query`,
				page_title: 'A page title from the client',
			} );
		} );

		const rows = onlineRows();

		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].page_url ).toBe( '/some/path?with=query' );
		expect( rows[ 0 ].page_title ).toBe( 'A page title from the client' );
	} );

	test( 'an unrecognised mode records nothing and answers with nothing', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			truncateOnline();

			const body = await refresh( guest, {
				mode: 'not-a-mode',
				page_url: `${ new URL( post.link ).origin }/`,
				page_title: 'Home',
			} );

			expect( body.trim() ).toBe( '' );
		} );

		// The mode is validated before anything is written. It used to fall
		// through the switch having already recorded a row on the way in, so a
		// caller could keep itself online for ever without ever asking for
		// anything.
		expect( onlineRows() ).toEqual( [] );
	} );

	test( 'a page_url belonging to another site is not recorded', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			truncateOnline();

			await refresh( guest, {
				mode: 'count',
				page_url: 'https://example.com/somebody-elses-site',
				page_title: 'Elsewhere',
			} );
		} );

		// The URL is the caller's to choose, so a foreign one is rejected
		// outright rather than stored and later rendered as a link.
		expect( onlineRows() ).toEqual( [] );
	} );
} );
