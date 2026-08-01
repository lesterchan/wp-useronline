/**
 * Who gets written into the useronline table, and what the row says.
 *
 * The far end here is the row, not anything on the page: the lists, the counts,
 * the widget and the WP-Stats section all read it, so a recorder that
 * misidentifies a visitor is wrong everywhere at once and right nowhere.
 *
 * The table is truncated before every test because the browser driving these
 * tests is itself recorded on every page it opens -- including wp-admin, which
 * records on admin_head. Finding exactly one row afterwards, and it being the
 * request that was just made, is the assertion.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	BOT_USER_AGENT,
	HUMAN_USER_AGENT,
	ONLINE_URL,
	asGuest,
	cookieHash,
	insertOnline,
	most,
	onlineRows,
	resetOptions,
	setMost,
	setOptions,
	truncateOnline,
	uniqueTitle,
} = require( './helpers.js' );

test.describe( 'Recording who is online', () => {
	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Recorded post' ),
			content: 'Something to look at.',
			status: 'publish',
		} );
	} );

	test.beforeEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test.afterEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test( 'the fixture really is an empty table that one visit fills', async ( { page } ) => {
		// Everything in this file counts rows, so the starting point has to be
		// nothing and one page load has to be exactly one row. If the recorder
		// ever stopped running, this is the test that says so plainly.
		expect( onlineRows() ).toEqual( [] );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		expect( onlineRows() ).toHaveLength( 1 );
	} );

	test( 'a logged out visitor is recorded as a guest', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		const [ row ] = onlineRows();

		expect( row.user_type ).toBe( 'guest' );
		expect( row.user_id ).toBe( '0' );
		expect( row.user_name ).toBe( 'Guest' );
		expect( row.user_agent ).toBe( HUMAN_USER_AGENT );
	} );

	test( 'a logged in user is recorded as a member, under their display name', async ( {
		page,
	} ) => {
		await page.goto( post.link );

		const [ row ] = onlineRows();

		expect( row.user_type ).toBe( 'member' );
		expect( parseInt( row.user_id, 10 ) ).toBeGreaterThan( 0 );
		expect( row.user_name ).toBe( 'admin' );
	} );

	test( 'a robot is recorded as a bot, named after the list entry it matched', async ( {
		page,
	} ) => {
		await asGuest( page, { userAgent: BOT_USER_AGENT }, async ( bot ) => {
			await bot.goto( post.link );
		} );

		const [ row ] = onlineRows();

		// The name comes from the key of the bot list, not from the user agent,
		// which is what makes the users online screen readable at all.
		expect( row.user_type ).toBe( 'bot' );
		expect( row.user_name ).toBe( 'Google' );
	} );

	test( 'a returning commenter is recorded under the name they left', async ( { page } ) => {
		// The comment_author cookie is the only name a logged out visitor has,
		// and it is the visitor's own text -- which is why the security spec
		// puts a payload through this same route.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			const hash = await guest.evaluate( () =>
				document.cookie
					.split( ';' )
					.map( ( part ) => part.trim() )
					.find( ( part ) => part.startsWith( 'comment_author_' ) ),
			);
			expect( hash ).toBeUndefined();

			// Set it the way core does, then reload so wp_head sees it.
			await guest.context().addCookies( [
				{
					name: `comment_author_${ cookieHash() }`,
					value: 'Frequent Commenter',
					url: guest.url(),
				},
			] );

			await guest.reload();
		} );

		const rows = onlineRows();

		expect( rows.some( ( row ) => 'Frequent Commenter' === row.user_name ) ).toBe( true );
	} );

	test( 'a second visit by the same visitor replaces their row rather than adding one', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await guest.goto( '/' );
		} );

		const rows = onlineRows();

		// One person browsing two pages is one user online. The delete in
		// record() is what keeps it that way.
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].page_url ).toBe( '/' );
	} );

	test( 'two different visitors both stay in the table', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		// A different user agent, because record() identifies a logged out
		// visitor by user agent and IP -- two contexts of the same browser look
		// like one person coming back.
		await asGuest( page, { userAgent: BOT_USER_AGENT }, async ( bot ) => {
			await bot.goto( post.link );
		} );

		const rows = onlineRows();

		expect( rows ).toHaveLength( 2 );
		expect( rows.map( ( row ) => row.user_type ).sort() ).toEqual( [ 'bot', 'guest' ] );
	} );

	test( 'the row carries the page the visitor is on', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		const [ row ] = onlineRows();

		expect( row.page_url ).toContain( `p=${ post.id }` );
		expect( row.page_title ).toContain( 'Recorded post' );
	} );

	test( 'the referral is recorded when the visitor arrived from somewhere', async ( { page } ) => {
		await asGuest(
			page,
			{ extraHTTPHeaders: { Referer: 'https://example.com/somewhere' } },
			async ( guest ) => {
				await guest.goto( post.link );
			},
		);

		const [ row ] = onlineRows();

		expect( row.referral ).toBe( 'https://example.com/somewhere' );
	} );

	test( 'a visit inside wp-admin is recorded too, with an admin page title', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		const [ row ] = onlineRows();

		// admin_head, not just wp_head. Without it an administrator working in
		// wp-admin would silently drop off the list of who is online.
		expect( row.page_url ).toContain( 'wp-admin' );
		expect( row.page_title ).toContain( 'Admin' );
	} );

	test( 'the timeout is what decides when a row is dropped', async ( { page } ) => {
		// Two stale rows and one fresh one, with the timeout between them, so
		// the sweep has something it must remove and something it must keep.
		setOptions( { timeout: 60 } );
		insertOnline( { userName: 'Long gone', userIp: '203.0.113.10', secondsAgo: 3600 } );
		insertOnline( { userName: 'Still here', userIp: '203.0.113.11', secondsAgo: 5 } );

		expect( onlineRows() ).toHaveLength( 2 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		const names = onlineRows().map( ( row ) => row.user_name );

		expect( names ).toContain( 'Still here' );
		expect( names ).not.toContain( 'Long gone' );
	} );

	test( 'raising the timeout keeps a row the shorter one would have dropped', async ( {
		page,
	} ) => {
		// The same fixture as above with only the setting changed, which is
		// what makes the previous test about the timeout rather than about a
		// sweep that happens to run.
		setOptions( { timeout: 7200 } );
		insertOnline( { userName: 'Long gone', userIp: '203.0.113.10', secondsAgo: 3600 } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		expect( onlineRows().map( ( row ) => row.user_name ) ).toContain( 'Long gone' );
	} );

	test( 'a new high water mark is recorded, and a lower count does not lower it', async ( {
		page,
	} ) => {
		setMost( 1, 86400 );
		setOptions( { timeout: 3600 } );

		// Four fixtures plus the visitor is five, which beats the recorded one.
		for ( let index = 0; index < 4; index++ ) {
			insertOnline( { userName: `Crowd ${ index }`, userIp: `203.0.113.2${ index }` } );
		}

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		expect( most().count ).toBe( 5 );

		// Now with nobody else about. The record is the highest ever, so a
		// quieter moment must leave it alone.
		truncateOnline();
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
		} );

		expect( most().count ).toBe( 5 );
	} );

	test( 'the recorded address is a real one, which is what de-duplicates a guest', async ( {
		page,
	} ) => {
		// Two visits, one address: record() finds a logged out visitor's
		// previous row by user agent *and* IP, so an address that came out
		// empty or different each time would make one person look like a crowd.
		//
		// Whether X-Forwarded-For may be trusted cannot be tested here: the
		// Apache in front of wp-env consumes the header itself and rewrites
		// REMOTE_ADDR from it, so PHP never sees one. tests/test-recorder.php
		// covers get_ip() and the wp_useronline_trust_proxy filter directly.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await guest.goto( '/' );
		} );

		const rows = onlineRows();

		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ].user_ip ).not.toBe( '' );
		expect( rows[ 0 ].user_ip ).toMatch( /^[0-9a-f.:]+$/i );
	} );

	test( 'the users online screen counts the administrator looking at it', async ( { page } ) => {
		setOptions( { timeout: 3600 } );
		insertOnline( { userName: 'Someone else', userIp: '203.0.113.30' } );

		await page.goto( ONLINE_URL );

		// Two: the fixture and the administrator, who was recorded by
		// admin_head on the way into this very screen.
		await expect( page.locator( '#useronline-details' ) ).toContainText( '2 Users' );
		expect( onlineRows() ).toHaveLength( 2 );
	} );
} );
