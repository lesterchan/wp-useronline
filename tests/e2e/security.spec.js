/**
 * The stored XSS regressions, in a real browser.
 *
 * Every interesting column of the useronline table is visitor-supplied. The
 * name comes from a comment cookie the visitor set, the page title and the URL
 * come from the refresh script's own POST, the referral and the user agent come
 * from request headers. So the row a compromised site already has is one where
 * all of them hold markup, and the payloads go in through insertOnline()
 * unsanitised -- sanitising on the way in is the assumption under test rather
 * than a step to reproduce.
 *
 * Every assertion has two halves: the sentinel the payload would set must never
 * become defined, and the payload's text must survive on the page. Escaping
 * that ate the value entirely passes the first half and is its own bug -- a
 * users online list that silently drops whoever has an angle bracket in their
 * name is not a fixed list.
 *
 * The last test in this file is the one PHPUnit cannot reach. users_online_page()
 * is rebuilt on the server every few seconds and written into the page with
 * innerHTML by js/wp-useronline.js, so markup that is clean on first render and
 * hostile in the refresh would never show up anywhere but here.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ONLINE_URL,
	asGuest,
	field,
	insertOnline,
	installProbe,
	openSettings,
	openTemplates,
	removeProbe,
	resetOptions,
	setOptions,
	truncateOnline,
	uniqueTitle,
	wpEval,
	wpEvalJson,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/** A password for the account the refresh test logs in as. */
const PASSWORD = 'correct-horse-battery-staple';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} target Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( target ) {
	return target.evaluate( () => window.__pwned === 1 );
}

/**
 * Assert that a container holds no payload that could ever fire.
 *
 * A bare <img> is not by itself a finding -- wp_kses_post keeps one, and the
 * plugin's own escaping turns the whole tag into text -- so the assertion is
 * about handlers and script elements, which is the property that matters.
 *
 * @param {import('@playwright/test').Locator} container Where to look.
 * @return {Promise<void>} Resolves once every check has passed.
 */
async function expectNothingArmed( container ) {
	await expect( container.locator( 'script' ) ).toHaveCount( 0 );
	await expect( container.locator( '[onerror]' ) ).toHaveCount( 0 );
	await expect( container.locator( '[onmouseover]' ) ).toHaveCount( 0 );
}

/**
 * One hostile row, with a payload in every column a visitor controls.
 *
 * @param {Object} overrides Anything to change about it.
 * @return {void}
 */
function insertHostile( overrides = {} ) {
	insertOnline( {
		userType: 'member',
		userId: 4242,
		userName: `Hostile ${ SCRIPT_PAYLOAD }`,
		userIp: '203.0.113.99',
		userAgent: `Agent ${ ATTR_PAYLOAD }`,
		pageTitle: `Title ${ IMG_PAYLOAD }`,
		pageUrl: `/hostile${ ATTR_PAYLOAD }`,
		referral: `https://example.com/from${ ATTR_PAYLOAD }`,
		...overrides,
	} );
}

test.describe( 'Hostile rows in the users online table', () => {
	let shortcodePost;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		installProbe();

		shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Hostile who is online' ),
			content: '[page_useronline]',
			status: 'publish',
		} );
	} );

	test.afterAll( async () => {
		removeProbe();
		resetOptions();
		truncateOnline();
	} );

	test.beforeEach( async () => {
		resetOptions();
		// An hour, so the fixture is never swept by the request reading it.
		setOptions( { timeout: 3600 } );
		truncateOnline();
		insertHostile();
	} );

	test.afterEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test( 'the fixture really is an unsanitised row in the table', () => {
		// Straight out of the row rather than through a template tag, so this
		// is the byte-for-byte payload and not something a filter has already
		// tidied up on the way in.
		const stored = wpEval(
			`global $wpdb;
			$row = $wpdb->get_row( "SELECT user_name, page_title, referral, user_agent FROM {$wpdb->useronline} LIMIT 1", ARRAY_A );
			echo '<<<' . wp_json_encode( $row ) . '>>>';`,
		);

		const row = JSON.parse( stored );

		expect( row.user_name ).toContain( '<script>window.__pwned = 1;</script>' );
		expect( row.page_title ).toContain( 'onerror' );
		expect( row.user_agent ).toContain( 'onmouseover' );
	} );

	test( 'the users online screen shows the row without running any of it', async ( { page } ) => {
		await page.goto( ONLINE_URL );

		const details = page.locator( '#useronline-details' );

		expect( await pwned( page ) ).toBe( false );
		await expectNothingArmed( details );

		// As text, both of them: an administrator cleaning up a compromised
		// site has to be able to see what is in the row.
		await expect( details ).toContainText( 'window.__pwned' );
		await expect( details ).toContainText( 'Title <img src=x onerror=' );
	} );

	test( 'hovering the whois link does not fire an attribute breakout in the user agent', async ( {
		page,
	} ) => {
		// The user agent is written into the link's title attribute, which is
		// the one place on this screen where a stored value lands inside an
		// attribute rather than between tags.
		await page.goto( ONLINE_URL );

		// The fixture's own address, not any whois link: the administrator
		// reading this screen was recorded on the way in and has one too.
		const link = page.locator(
			'#useronline-details a[href*="whois.domaintools.com/203.0.113.99"]',
		);
		await expect( link ).toBeAttached();
		await expect( link ).toHaveAttribute( 'title', /Agent " onmouseover=/ );

		// Hovered, because a breakout that survived would only fire on the
		// event it named.
		await link.hover();
		expect( await pwned( page ) ).toBe( false );
	} );

	test( 'the [page_useronline] shortcode renders the row safely for a visitor', async ( {
		page,
	} ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			const details = guest.locator( '#useronline-details' );

			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( details );
			await expect( details ).toContainText( 'window.__pwned' );
		} );
	} );

	test( 'the compact browsing list renders a hostile member name as text', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			const list = guest.locator( '#uo-browsing-site' );

			expect( await pwned( guest ) ).toBe( false );
			await expectNothingArmed( list );
			await expect( list ).toContainText( 'window.__pwned' );
		} );
	} );

	test( 'a linked hostile name stays text inside its anchor', async ( { page } ) => {
		// With names linking switched on the escaped name is wrapped in an
		// anchor built from the row's user_id, so the escaping has to survive a
		// second pass through a filter that concatenates markup around it.
		setOptions( { names: 1 } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			const anchor = guest.locator( '#uo-browsing-site a' ).first();

			expect( await pwned( guest ) ).toBe( false );
			await expect( anchor ).toContainText( 'window.__pwned' );
			await expect( guest.locator( '#uo-browsing-site script' ) ).toHaveCount( 0 );
		} );
	} );

	test( 'the At a Glance panel renders the row without running it', async ( { page } ) => {
		await page.goto( '/wp-admin/index.php' );

		const panel = page.locator( '#dashboard_right_now' );

		expect( await pwned( page ) ).toBe( false );
		await expectNothingArmed( panel );
		await expect( panel ).toContainText( 'window.__pwned' );
	} );

	test( 'a hostile naming convention stored straight into the row still edits as text', async ( {
		page,
	} ) => {
		// The row a compromised install has: written past the sanitiser
		// entirely. The settings screen has to show an administrator what is in
		// it without running it, or cleaning up means hand-editing the database.
		//
		// Both tabs, because the payloads are split across them now: the naming
		// conventions are on Settings and the templates on Templates, and each
		// tab renders its own fields.
		setOptions( {
			naming: { user: `1 ${ SCRIPT_PAYLOAD }`, users: `%COUNT% ${ IMG_PAYLOAD }` },
			templates: { useronline: `<span>%USERS%</span> ${ ATTR_PAYLOAD }` },
		} );

		await openSettings( page );

		expect( await pwned( page ) ).toBe( false );
		await expectNothingArmed( page.locator( '#wpbody' ) );

		// And the fields hold the stored values themselves, so pressing Save
		// does not silently rewrite what the row says.
		await expect( page.locator( field( 'naming', 'user' ) ) ).toHaveValue(
			`1 ${ SCRIPT_PAYLOAD }`,
		);

		await openTemplates( page );

		expect( await pwned( page ) ).toBe( false );
		await expectNothingArmed( page.locator( '#wpbody' ) );

		await expect( page.locator( field( 'templates', 'useronline' ) ) ).toHaveValue(
			`<span>%USERS%</span> ${ ATTR_PAYLOAD }`,
		);
	} );

	test( 'a hostile naming convention rendered on the front end never runs', async ( { page } ) => {
		setOptions( { naming: { users: `%COUNT% ${ IMG_PAYLOAD }` } } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			// wp_kses_post() is the defence for the naming and template rows,
			// and it legitimately keeps a bare <img>. The property that matters
			// is that the handler is gone and nothing ran.
			expect( await pwned( guest ) ).toBe( false );
			await expect( guest.locator( '#uo-online img[onerror]' ) ).toHaveCount( 0 );
		} );
	} );
} );

test.describe( 'Hostile markup in the refresh response', () => {
	let shortcodePost;
	let userId;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();

		// Comments closed, for the same reason the admin bar is switched off
		// below and it is the same mistake caught twice. This test's sentinel is
		// page-global, so it is only about this plugin if nothing *else* on the
		// page prints the display name raw. Core's comment form does: the
		// "logged-in-as" line is `Logged in as <user_identity>` with no
		// escaping, so twentytwentyone rendered the payload out of core's markup
		// and the assertion at the top of the test failed while WP-UserOnline's
		// own output was, and is, correctly escaped -- the same request showed
		// `Hostile &lt;script&gt;window.__pwned = 1;&lt;/script&gt;` inside
		// #useronline-details. Closing comments removes the form.
		shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Refreshed who is online' ),
			content: '[page_useronline]',
			status: 'publish',
			comment_status: 'closed',
		} );

		// A real account whose display name is the payload, because the
		// refreshed list has to be about a row the *poller itself* keeps
		// rewriting: the poll re-records the caller every time, so a row
		// inserted by hand would be swept by the short timeout this test needs.
		//
		// The name goes into wp_users directly. wp_insert_user() sanitises a
		// display name, and the point here is the row a compromised install has.
		userId = parseInt(
			wpEval(
				`$user = get_user_by( 'login', 'uo_hostile' );
				if ( ! $user ) {
					$id = wp_insert_user( array( 'user_login' => 'uo_hostile', 'user_pass' => '${ PASSWORD }', 'role' => 'subscriber' ) );
				} else {
					$id = $user->ID;
				}
				global $wpdb;
				$wpdb->update( $wpdb->users, array( 'display_name' => base64_decode( '${ Buffer.from(
		`Hostile ${ SCRIPT_PAYLOAD }`,
		'utf8',
	).toString( 'base64' ) }' ) ), array( 'ID' => $id ), array( '%s' ), array( '%d' ) );
				// The admin bar is switched off for this account on purpose.
				// Core builds its "Howdy, %s" out of the display name without
				// escaping it, so with the bar on the payload would run from
				// core's markup and this would stop being a test of this plugin.
				update_user_meta( $id, 'show_admin_bar_front', 'false' );
				clean_user_cache( $id );
				echo '<<<' . $id . '>>>';`,
			),
			10,
		);
	} );

	test.afterAll( async () => {
		resetOptions();
		truncateOnline();
	} );

	test.beforeEach( async () => {
		resetOptions();
		truncateOnline();
	} );

	test( 'the fixture really is a user whose display name is the payload', () => {
		// Through JSON rather than as bare text. wpEval() reads back whatever
		// sits between <<< and the first >>>, and this payload ends in a ">" --
		// so printed raw it would run into the closing marker and come back a
		// character short. Inside a JSON string the quote separates them.
		const stored = wpEvalJson(
			`$GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT display_name FROM {$GLOBALS['wpdb']->users} WHERE ID = %d", ${ userId } ) )`,
		);

		expect( stored ).toContain( '<script>window.__pwned = 1;</script>' );
	} );

	test( 'the markup the endpoint returns is still text once the script swaps it in', async ( {
		page,
	} ) => {
		// Two seconds, so the script's own interval fires inside a test rather
		// than in five minutes. Each poll re-records the poller, so their row is
		// never the one the timeout sweeps.
		setOptions( { timeout: 2 } );

		const context = await page.context().browser().newContext( { storageState: undefined } );
		const other = await context.newPage();

		try {
			await other.goto( '/wp-login.php' );
			// wp-login.php focuses and selects #user_login on a 200ms timer;
			// filling across that moment puts the password in the username box.
			await expect( other.locator( '#user_login' ) ).toBeFocused();
			await other.locator( '#user_login' ).fill( 'uo_hostile' );
			await other.locator( '#user_pass' ).fill( PASSWORD );
			await other.locator( '#wp-submit' ).click();
			await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

			await other.goto( shortcodePost.link );

			const details = other.locator( '#useronline-details' );
			await expect( details ).toContainText( 'window.__pwned' );
			expect( await pwned( other ) ).toBe( false );

			// A marker inside the container the script replaces wholesale. Its
			// disappearance is the signal that the refresh has landed -- and
			// only then is the assertion below about the AJAX markup rather
			// than about the server-rendered page.
			await other.evaluate( () => {
				const node = document.createElement( 'span' );
				node.id = 'uo-before-refresh';
				document.getElementById( 'useronline-details' ).appendChild( node );
			} );

			await expect( other.locator( '#uo-before-refresh' ) ).toHaveCount( 0, {
				timeout: 30_000,
			} );

			expect( await pwned( other ) ).toBe( false );
			await expectNothingArmed( details );
			await expect( details ).toContainText( 'window.__pwned' );
		} finally {
			await context.close();
		}
	} );
} );
