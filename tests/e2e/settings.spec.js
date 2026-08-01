/**
 * WP-UserOnline > Settings, and what each row stores.
 *
 * A setting that saves but does nothing, and a setting that does something but
 * will not save, are the two failures a screenshot cannot tell apart. The
 * effect half of most rows is proved in display.spec.js and recording.spec.js
 * -- the timeout sweeping stale rows, the naming conventions reaching the
 * rendered lists, "link user names" turning a member into an anchor -- so this
 * file is about the other half: that what the form posted is what the option
 * row ends up holding, through the sanitiser, and that reloading the screen
 * shows it back.
 *
 * Every field on this screen is nested inside one option array, and the
 * sanitiser rebuilds that array from the defaults on every save rather than
 * merging into the stored value. So a test that types into one field and saves
 * is also asserting that the other twenty came back off the form intact.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	asGuest,
	field,
	installProbe,
	openSettings,
	option,
	removeProbe,
	resetOptions,
	saveSettings,
	setOptions,
	truncateOnline,
	uniqueTitle,
	wpEvalJson,
} = require( './helpers.js' );

test.describe( 'The settings screen', () => {
	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		installProbe();

		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Settings post' ),
			content: 'Body text.',
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
		truncateOnline();
	} );

	test.afterEach( async () => {
		// Everything this file changes lives in one option row, so one reset
		// puts the whole screen back for the next test.
		resetOptions();
	} );

	test( 'the fixture really is the four sections, each with its fields on screen', async ( {
		page,
	} ) => {
		// Every test below fills a field by name. If do_settings_sections()
		// ever stopped drawing one of these sections the fills would silently
		// find nothing and the saves would post the defaults back, which looks
		// exactly like a passing test.
		await openSettings( page );

		await expect( page.getByRole( 'heading', { name: 'General' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Naming Conventions' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'Templates' } ) ).toBeVisible();
		await expect( page.getByRole( 'heading', { name: 'WP-Stats' } ) ).toBeVisible();

		await expect( page.locator( field( 'timeout' ) ) ).toHaveValue( '300' );
		await expect( page.locator( field( 'url' ) ) ).toBeVisible();
		await expect( page.locator( '#wp-useronline-naming input' ) ).toHaveCount( 8 );
		await expect( page.locator( field( 'templates', 'useronline' ) ) ).toBeVisible();
		await expect( page.locator( field( 'stats_display' ) ) ).toBeChecked();
	} );

	test( 'the Time Out field stores a number of seconds', async ( { page } ) => {
		await openSettings( page );

		await page.locator( field( 'timeout' ) ).fill( '900' );
		await saveSettings( page );

		// The recorder reads this as an int when it sweeps stale rows, so the
		// stored value is what decides behaviour.
		expect( option( 'timeout' ) ).toBe( 900 );

		await openSettings( page );
		await expect( page.locator( field( 'timeout' ) ) ).toHaveValue( '900' );
	} );

	test( 'a Time Out of zero falls back to the default rather than being stored', async ( {
		page,
	} ) => {
		await openSettings( page );

		// A stored zero would make the delete in record() sweep every row on
		// the very next request, so the site would permanently show one user
		// online however many were there.
		await page.locator( field( 'timeout' ) ).fill( '0' );
		await saveSettings( page );

		expect( option( 'timeout' ) ).toBe( 300 );
	} );

	test( 'the UserOnline URL saves and reaches %PAGE_URL% on the front end', async ( { page } ) => {
		await openSettings( page );

		await page.locator( field( 'url' ) ).fill( 'https://example.com/who-is-here' );
		await saveSettings( page );

		expect( option( 'url' ) ).toBe( 'https://example.com/who-is-here' );

		// The far end, not the row: the setting exists only to be substituted
		// into the users online template a visitor sees.
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#uo-online a' ) ).toHaveAttribute(
				'href',
				'https://example.com/who-is-here',
			);
		} );
	} );

	test( 'the "Link user names" radios store both answers', async ( { page } ) => {
		await openSettings( page );
		await page.locator( `${ field( 'names' ) }[value="1"]` ).check();
		await saveSettings( page );
		expect( option( 'names' ) ).toBe( 1 );

		await openSettings( page );
		await expect( page.locator( `${ field( 'names' ) }[value="1"]` ) ).toBeChecked();

		await page.locator( `${ field( 'names' ) }[value="0"]` ).check();
		await saveSettings( page );
		expect( option( 'names' ) ).toBe( 0 );
	} );

	test( 'every naming convention cell stores what is typed into it', async ( { page } ) => {
		// All eight in one test rather than eight near-identical ones: they are
		// one loop over one array in the sanitiser, and the thing worth proving
		// is that no cell is dropped or written over its neighbour.
		const typed = {
			user: 'One person',
			users: '%COUNT% people',
			member: 'One subscriber',
			members: '%COUNT% subscribers',
			guest: 'One passer-by',
			guests: '%COUNT% passers-by',
			bot: 'One robot',
			bots: '%COUNT% robots',
		};

		await openSettings( page );

		for ( const [ key, value ] of Object.entries( typed ) ) {
			await page.locator( field( 'naming', key ) ).fill( value );
		}

		await saveSettings( page );

		expect( option( 'naming' ) ).toEqual( typed );
	} );

	test( 'a naming convention reaches the count a visitor reads', async ( { page } ) => {
		await openSettings( page );
		await page.locator( field( 'naming', 'user' ) ).fill( 'Exactly one soul' );
		await saveSettings( page );

		// Emptied first: opening the settings screen recorded the administrator,
		// and the singular naming is only reached when exactly one person is
		// online -- which is the whole point of having a singular cell.
		truncateOnline();

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#uo-online' ) ).toContainText( 'Exactly one soul' );
		} );
	} );

	test( 'the users online template stores multi-line markup', async ( { page } ) => {
		const markup = '<a href="%PAGE_URL%">\n\t<strong>%USERS%</strong> here\n</a>';

		await openSettings( page );
		await page.locator( field( 'templates', 'useronline' ) ).fill( markup );
		await saveSettings( page );

		// trim() on the outside is all the sanitiser does to the shape of the
		// value, so the inner newlines and the tab have to survive.
		//
		// The line endings are normalised on the way out of the comparison, not
		// on the way in: a textarea posts CRLF whatever was typed into it --
		// that is the HTML form specification, not this plugin -- so asserting
		// on LF would be asserting that WordPress rewrites what the browser
		// sent, which it does not and should not.
		expect( option( 'templates' ).useronline.replace( /\r\n/g, '\n' ) ).toBe( markup );
	} );

	for ( const key of [ 'browsingsite', 'browsingpage' ] ) {
		test( `the ${ key } template and its three separators all store`, async ( { page } ) => {
			await openSettings( page );

			await page
				.locator( field( 'templates', key, 'text' ) )
				.fill( '%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%' );
			await page.locator( field( 'templates', key, 'separators', 'members' ) ).fill( ' + ' );
			// Not an ampersand: wp_kses_post() encodes a bare "&" to "&amp;",
			// which is correct and is core's doing rather than this plugin's --
			// asserting on it would be asserting that WordPress stops escaping.
			await page.locator( field( 'templates', key, 'separators', 'guests' ) ).fill( ' / ' );
			await page.locator( field( 'templates', key, 'separators', 'bots' ) ).fill( ' ~ ' );

			await saveSettings( page );

			const stored = option( 'templates' )[ key ];

			expect( stored.text ).toBe(
				'%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%',
			);
			// Not trimmed, unlike the template above: the surrounding spaces are
			// the whole point of a separator, and trimming them would run two
			// names together.
			expect( stored.separators ).toEqual( { members: ' + ', guests: ' / ', bots: ' ~ ' } );
		} );
	}

	test( 'a template is stored unslashed, so an apostrophe stays an apostrophe', async ( {
		page,
	} ) => {
		await openSettings( page );
		await page.locator( field( 'templates', 'useronline' ) ).fill( "%USERS% reader's view" );
		await saveSettings( page );

		expect( option( 'templates' ).useronline ).toBe( "%USERS% reader's view" );
	} );

	test( 'Restore Defaults puts the shipped naming back in the fields, and saves it', async ( {
		page,
	} ) => {
		setOptions( { naming: { user: 'something else', users: 'something else plural' } } );

		await openSettings( page );
		await expect( page.locator( field( 'naming', 'user' ) ) ).toHaveValue( 'something else' );

		await page.locator( '.wp-useronline-restore[data-target="#wp-useronline-naming"]' ).click();

		await expect( page.locator( field( 'naming', 'user' ) ) ).toHaveValue( '1 User' );
		await expect( page.locator( field( 'naming', 'users' ) ) ).toHaveValue( '%COUNT% Users' );

		// The button only rewrites the fields, so the far end is reached only
		// once the form is saved -- which is the difference between a control
		// that looks right and one that does something.
		await saveSettings( page );
		expect( option( 'naming' ).user ).toBe( '1 User' );
	} );

	test( 'each Restore Defaults button is scoped to its own group of fields', async ( { page } ) => {
		setOptions( {
			naming: { user: 'kept as is' },
			templates: {
				useronline: 'replaced',
				browsingsite: { text: 'kept as is too' },
			},
		} );

		await openSettings( page );
		await page
			.locator( '.wp-useronline-restore[data-target="#wp-useronline-template-useronline"]' )
			.click();

		await expect( page.locator( field( 'templates', 'useronline' ) ) ).toHaveValue( /%USERS%/ );
		// Neither of the other two scopes moved, which is what makes the button
		// bound to its own group rather than to the whole screen.
		await expect( page.locator( field( 'naming', 'user' ) ) ).toHaveValue( 'kept as is' );
		await expect( page.locator( field( 'templates', 'browsingsite', 'text' ) ) ).toHaveValue(
			'kept as is too',
		);
	} );

	test( 'the timeout is restored to its number rather than being cleared', async ( { page } ) => {
		// The timeout default is an int, and the lookup that feeds the data
		// attribute used to return '' for anything that was not a string -- so
		// Restore Defaults emptied this field instead of putting 300 back, and
		// the sanitiser then read the empty value as zero.
		setOptions( { timeout: 45 } );

		await openSettings( page );
		await expect( page.locator( field( 'timeout' ) ) ).toHaveValue( '45' );

		await page.locator( '.wp-useronline-restore[data-target="#wp-useronline-naming"]' ).click();
		// The timeout is not in that scope, so it must not have moved.
		await expect( page.locator( field( 'timeout' ) ) ).toHaveValue( '45' );

		expect( wpEvalJson( 'WP_UserOnline_Options::defaults()["timeout"]' ) ).toBe( 300 );
	} );

	test( 'the WP-Stats checkbox turns off and back on', async ( { page } ) => {
		await openSettings( page );
		await expect( page.locator( field( 'stats_display' ) ) ).toBeChecked();

		await page.locator( field( 'stats_display' ) ).uncheck();
		await saveSettings( page );

		// An unticked checkbox posts nothing at all, so this is the row the
		// sanitiser has to read as absent-means-off. Getting it wrong gives a
		// checkbox that can be ticked and never unticked.
		expect( option( 'stats_display' ) ).toBe( false );

		await openSettings( page );
		await expect( page.locator( field( 'stats_display' ) ) ).not.toBeChecked();

		await page.locator( field( 'stats_display' ) ).check();
		await saveSettings( page );
		expect( option( 'stats_display' ) ).toBe( true );
	} );

	test( 'the WP-Stats toggle decides whether a section is offered to the stats page', async () => {
		// WP-Stats is not installed here, so the far end is the filter it fires:
		// an entry keyed wp_useronline appears when the toggle is on and is
		// absent when it is off. Both directions in one test, because "no entry"
		// on its own passes with the plugin deactivated.
		setOptions( { stats_display: true } );
		expect(
			Object.keys( wpEvalJson( 'apply_filters( "wp_stats_sections", array() )' ) ),
		).toContain( 'wp_useronline' );

		setOptions( { stats_display: false } );
		expect(
			Object.keys( wpEvalJson( 'apply_filters( "wp_stats_sections", array() )' ) ),
		).not.toContain( 'wp_useronline' );
	} );

	test( 'the offered section renders the count and the record', async () => {
		setOptions( { stats_display: true, timeout: 3600 } );

		// WP-Stats calls the entry's render callback and expects it to *echo*,
		// because it assembles its page inside an output buffer -- a section
		// that returned its markup would be dropped without a word.
		const body = wpEvalJson(
			'( function () { ob_start(); WP_UserOnline_WPStats::render(); return ob_get_clean(); } )()',
		);

		expect( body ).toContain( '<li>' );
		expect( body ).toContain( 'Most users ever online were' );
	} );

	test( 'the screen lists the tokens each template accepts', async ( { page } ) => {
		await openSettings( page );

		// The token lists are the only documentation a site owner has for what
		// may go in these fields, and they are markup inside a field callback,
		// which is the sort of thing that quietly disappears.
		await expect(
			page.locator( '#wp-useronline-template-useronline code' ),
		).toHaveText( [ '%USERS%', '%PAGE_URL%', '%MOSTONLINE_COUNT%', '%MOSTONLINE_DATE%' ] );
		await expect(
			page.locator( '#wp-useronline-template-browsingsite code' ),
		).toContainText( [ '%MEMBER_NAMES%' ] );
	} );

	test( 'saving shows the confirmation notice', async ( { page } ) => {
		await openSettings( page );

		// options.php registers "Settings saved." and redirects back here. Core
		// only prints it from wp-admin/options-head.php, which options-*.php
		// includes and admin.php does not -- so a plugin screen under a menu of
		// its own has to call settings_errors() itself, as WP-Stats' settings
		// screen does. Without it the form saves and says nothing at all.
		await page.getByRole( 'button', { name: 'Save Changes' } ).click();

		await expect( page.locator( '.settings-error, .notice-success' ).first() ).toContainText(
			'Settings saved.',
		);
	} );
} );
