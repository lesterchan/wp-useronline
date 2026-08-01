/**
 * What the plugin puts on a page: the template tags, the widget, the
 * [page_useronline] shortcode and the refresh script.
 *
 * The tags come through the probe mu-plugin, which stands in for the theme call
 * a real site would make -- twentytwentyone calls none of them. The widget and
 * the shortcode are real surfaces and are driven as themselves.
 *
 * The crowd in these tests is inserted rather than browsed in. Two visitors
 * cannot be two contexts of one browser (record() removes a guest's previous
 * row by user agent and IP, so the second visit deletes the first), and the
 * lists are about several people at once. The rows carry their own TEST-NET-3
 * addresses so they cannot collide with the container's on the table's unique
 * key.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	asGuest,
	addUserOnlineWidget,
	insertOnline,
	installProbe,
	probeUser,
	removeProbe,
	removeUserOnlineWidget,
	resetOptions,
	setMost,
	setOptions,
	truncateOnline,
	uniqueTitle,
} = require( './helpers.js' );

/**
 * Two members, three guests and one bot, all on the front page.
 *
 * The counts are all different from each other so that a template substituting
 * the wrong one is visible rather than coincidentally right.
 *
 * The two members carry explicit timestamps, fifty seconds apart. The lists are
 * ordered newest first, and two rows written in the same second break the tie
 * on whichever insert the database reached first -- so "Ada, Grace" would be a
 * coin toss. The gap has to be large because each row goes in through its own
 * WP-CLI call: the offsets are measured from each call's own "now", which is
 * several seconds later than the last one's, so a one-second gap reverses.
 *
 * @return {void}
 */
function insertCrowd() {
	insertOnline( {
		userType: 'member',
		userId: 5,
		userName: 'Ada',
		userIp: '203.0.113.11',
		secondsAgo: 10,
	} );
	insertOnline( {
		userType: 'member',
		userId: 6,
		userName: 'Grace',
		userIp: '203.0.113.12',
		secondsAgo: 60,
	} );
	insertOnline( { userType: 'guest', userName: 'Guest', userIp: '203.0.113.13' } );
	insertOnline( { userType: 'guest', userName: 'Guest', userIp: '203.0.113.14' } );
	insertOnline( { userType: 'guest', userName: 'Guest', userIp: '203.0.113.15' } );
	insertOnline( { userType: 'bot', userName: 'Google', userIp: '203.0.113.16' } );
}

test.describe( 'Front-end output', () => {
	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		installProbe();

		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Display post' ),
			content: 'Body text.',
			status: 'publish',
		} );
	} );

	test.afterAll( async () => {
		removeProbe();
		removeUserOnlineWidget();
		resetOptions();
		truncateOnline();
	} );

	test.beforeEach( async () => {
		resetOptions();
		// An hour, so a fixture inserted a moment ago is never swept by the
		// very request that is about to read it.
		setOptions( { timeout: 3600 } );
		truncateOnline();
	} );

	test.afterEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test( 'the fixture really is the probe calling the tags, with the visitor counted in', async ( {
		page,
	} ) => {
		insertCrowd();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// Six fixtures plus the guest reading the page, who wp_head
			// recorded before the footer rendered any of this.
			await expect( guest.locator( '#uo-count' ) ).toHaveText( '7' );
			await expect( guest.locator( '#uo-online' ) ).toContainText( '7 Users' );
		} );
	} );

	test( 'users_online() renders the template, with the URL and the record in it', async ( {
		page,
	} ) => {
		setMost( 4321, 0 );
		setOptions( {
			url: 'https://example.com/who-is-here',
			templates: {
				useronline: '<a href="%PAGE_URL%">%USERS%</a> peak %MOSTONLINE_COUNT% on %MOSTONLINE_DATE%',
			},
		} );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			const link = guest.locator( '#uo-online a' );
			await expect( link ).toHaveAttribute( 'href', 'https://example.com/who-is-here' );
			await expect( link ).toHaveText( '1 User' );
			await expect( guest.locator( '#uo-online' ) ).toContainText( 'peak 4,321 on' );
		} );
	} );

	test( 'users_browsing_site() names the members and counts the rest', async ( { page } ) => {
		insertCrowd();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// Members by name, guests and bots by number: four guests, because
			// the visitor reading this is one of them.
			const list = guest.locator( '#uo-browsing-site' );
			await expect( list ).toContainText( 'Ada, Grace' );
			await expect( list ).toContainText( '4 Guests' );
			await expect( list ).toContainText( '1 Bot' );
		} );
	} );

	test( 'users_browsing_page() is scoped to the page rather than the site', async ( { page } ) => {
		// The crowd is on '/', and the visitor will be on the post, so the two
		// lists have to disagree -- which is the whole point of the tag.
		insertCrowd();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#uo-browsing-site' ) ).toContainText( 'Ada' );
			await expect( guest.locator( '#uo-browsing-page' ) ).not.toContainText( 'Ada' );
			await expect( guest.locator( '#uo-browsing-page' ) ).toContainText( '1 User' );
		} );
	} );

	test( 'the separators only appear between groups that are actually there', async ( { page } ) => {
		setOptions( {
			templates: {
				browsingsite: {
					separators: { members: ' + ', guests: ' & ', bots: ' ~ ' },
					text: '%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%',
				},
			},
		} );

		// Members only: no guests and no bots, so neither separator may print.
		// Explicit timestamps fifty seconds apart, so the order the two names
		// come out in is the fixture's rather than a race between two WP-CLI
		// calls -- see insertCrowd().
		insertOnline( {
			userType: 'member',
			userId: 5,
			userName: 'Ada',
			userIp: '203.0.113.11',
			secondsAgo: 10,
		} );
		insertOnline( {
			userType: 'member',
			userId: 6,
			userName: 'Grace',
			userIp: '203.0.113.12',
			secondsAgo: 60,
		} );

		// The administrator, so the reader is a member too and the visit does
		// not introduce a guest of its own.
		await page.goto( post.link );

		const list = page.locator( '#uo-browsing-site' );
		await expect( list ).toContainText( 'Ada + Grace' );
		await expect( list ).not.toContainText( '&' );
		await expect( list ).not.toContainText( '~' );

		// Now with a bot as well. The bots separator appears; the guests one
		// still must not, because there are no guests between the two.
		insertOnline( { userType: 'bot', userName: 'Google', userIp: '203.0.113.16' } );
		await page.goto( post.link );

		await expect( page.locator( '#uo-browsing-site' ) ).toContainText( '~ 1 Bot' );
		await expect( page.locator( '#uo-browsing-site' ) ).not.toContainText( '&' );
	} );

	test( 'most_users_online() and its date read the stored record', async ( { page } ) => {
		setMost( 1234, 0 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#uo-most' ) ).toHaveText( '1,234' );
			// Not the raw timestamp: the date goes through the site's own date
			// and time formats.
			await expect( guest.locator( '#uo-most-date' ) ).not.toHaveText( /^\d+$/ );
			await expect( guest.locator( '#uo-most-date' ) ).toContainText( '@' );
		} );
	} );

	test( 'is_user_online() answers for one particular user, both ways', async ( { page } ) => {
		insertOnline( { userType: 'member', userId: 4242, userName: 'Ada', userIp: '203.0.113.11' } );
		probeUser( 4242 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( guest.locator( '#uo-is-online' ) ).toHaveAttribute( 'data-online', '1' );
		} );

		// The same probe pointed at somebody who is not in the table, so the
		// answer above is about that user rather than about the tag returning
		// true for anyone.
		probeUser( 999999 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( guest.locator( '#uo-is-online' ) ).toHaveAttribute( 'data-online', '0' );
		} );
	} );

	test( 'get_useronline_() hands back the counts per user type', async ( { page } ) => {
		insertCrowd();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			const counts = JSON.parse(
				await guest.locator( '#uo-counts' ).getAttribute( 'data-json' ),
			);

			expect( counts ).toEqual( { member: 2, guest: 4, bot: 1, user: 7 } );
		} );
	} );

	test( 'the [page_useronline] shortcode renders the detailed list', async ( {
		page,
		requestUtils,
	} ) => {
		insertCrowd();

		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Who is online' ),
			content: '[page_useronline]',
			status: 'publish',
		} );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			const details = guest.locator( '#useronline-details' );
			await expect( details ).toContainText( '7 Users' );
			// A heading per populated type, and a numbered entry per person.
			await expect( details.getByRole( 'heading', { name: '2 Members Online Now' } ) ).toBeVisible();
			await expect( details.getByRole( 'heading', { name: /Guests Online Now/ } ) ).toBeVisible();
			await expect( details.getByRole( 'heading', { name: '1 Bot Online Now' } ) ).toBeVisible();
			await expect( details ).toContainText( '#1 - Ada' );
			await expect( details ).toContainText( 'Most users ever online were' );
		} );
	} );

	test( 'the detailed list says so plainly when nobody is online', async ( { page } ) => {
		// Reached through wp-admin, because any front-end request records the
		// requester and so can never find the table empty.
		truncateOnline();

		await page.goto( '/wp-admin/admin.php?page=wp-useronline' );

		// The administrator's own row is the only one, so the empty branch is
		// out of reach from a browser at all -- see the settings screen, which
		// is the one place the plugin renders without recording first. What is
		// reachable is the singular wording, which is the same branch's
		// neighbour.
		await expect( page.locator( '#useronline-details' ) ).toContainText( '1 User' );
	} );

	test( 'the "link user names" setting turns a member into a link to their archive', async ( {
		page,
		requestUtils,
	} ) => {
		const author = await requestUtils
			.rest( {
				method: 'POST',
				path: '/wp/v2/users',
				data: {
					username: 'useronline_author',
					email: 'useronline_author@example.com',
					password: 'correct-horse-battery-staple',
					roles: [ 'author' ],
				},
			} )
			.catch( () => requestUtils.rest( { path: '/wp/v2/users?search=useronline_author' } ).then( ( u ) => u[ 0 ] ) );

		insertOnline( {
			userType: 'member',
			userId: author.id,
			userName: 'useronline_author',
			userIp: '203.0.113.11',
		} );

		setOptions( { names: 0 } );
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( guest.locator( '#uo-browsing-site a' ) ).toHaveCount( 0 );
			await expect( guest.locator( '#uo-browsing-site' ) ).toContainText( 'useronline_author' );
		} );

		setOptions( { names: 1 } );
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			const link = guest.locator( '#uo-browsing-site a' );
			await expect( link ).toHaveText( 'useronline_author' );
			await expect( link ).toHaveAttribute( 'href', /author=/ );
		} );
	} );

	test( 'the widget renders the count and its title', async ( { page } ) => {
		insertCrowd();
		addUserOnlineWidget( { title: 'Who is here', type: 'users_online' } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#useronline-9' ) ).toContainText( 'Who is here' );
			await expect( guest.locator( '#useronline-count' ) ).toContainText( '7 Users' );
			// Only the container this type asked for.
			await expect( guest.locator( '#useronline-browsing-site' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'each widget statistics type renders exactly the containers it names', async ( {
		page,
	} ) => {
		insertCrowd();

		const expectations = [
			[ 'users_browsing_page', [ 'browsing-page' ] ],
			[ 'users_browsing_site', [ 'browsing-site' ] ],
			[ 'users_online_browsing_page', [ 'count', 'browsing-page' ] ],
			[ 'users_online_browsing_site', [ 'count', 'browsing-site' ] ],
		];

		for ( const [ type, containers ] of expectations ) {
			addUserOnlineWidget( { title: 'Widget', type } );

			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );

				for ( const container of [ 'count', 'browsing-page', 'browsing-site' ] ) {
					await expect(
						guest.locator( `#useronline-${ container }` ),
						`${ type } / ${ container }`,
					).toHaveCount( containers.includes( container ) ? 1 : 0 );
				}
			} );
		}
	} );

	test( 'a widget with an empty title renders the numbers and no heading', async ( { page } ) => {
		addUserOnlineWidget( { title: '', type: 'users_online' } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#useronline-9 .widget-title' ) ).toHaveCount( 0 );
			await expect( guest.locator( '#useronline-count' ) ).toContainText( '1 User' );
		} );
	} );

	test( 'the refresh script is enqueued only when something on the page needs it', async ( {
		page,
	} ) => {
		// The probe calls users_browsing_site(), which asks for the script, so
		// this pair has to be judged with the probe out of the way.
		removeProbe();
		removeUserOnlineWidget();

		try {
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );
				await expect( guest.locator( 'script[src*="wp-useronline.js"]' ) ).toHaveCount( 0 );
			} );

			// A count-only widget never reaches compact_list(), which is what
			// otherwise asks for the script -- and before 3.0.0 that meant its
			// number sat there and never updated.
			addUserOnlineWidget( { title: 'Count', type: 'users_online' } );

			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );
				await expect( guest.locator( 'script[src*="wp-useronline.js"]' ) ).toHaveCount( 1 );
			} );
		} finally {
			installProbe();
			removeUserOnlineWidget();
		}
	} );

	test( 'the widget refreshes itself from the server without the page reloading', async ( {
		page,
	} ) => {
		// Two seconds, so the script's own interval fires inside a test rather
		// than in five minutes' time. The value is also the row timeout, but
		// each poll re-records the poller, so the count stays at one.
		setOptions( { timeout: 2 } );
		addUserOnlineWidget( { title: 'Count', type: 'users_online' } );
		removeProbe();

		try {
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );

				await expect( guest.locator( '#useronline-count' ) ).toContainText( '1 User' );

				await guest.evaluate( () => {
					window.__uoNotReloaded = true;
				} );

				// Changed behind the page's back. Nothing on screen can know
				// about it except by asking the server again, which is exactly
				// what the refresh does.
				setOptions( { naming: { user: '1 Person' } } );

				await expect( guest.locator( '#useronline-count' ) ).toContainText( '1 Person', {
					timeout: 20_000,
				} );

				expect( await guest.evaluate( () => true === window.__uoNotReloaded ) ).toBe( true );
			} );
		} finally {
			installProbe();
			removeUserOnlineWidget();
		}
	} );
} );
