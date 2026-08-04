/**
 * The pre-4.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so maybe_upgrade()
 * runs from add_hooks() on every request instead. Loading a page in a browser
 * is the only way to reach it, and it is the path every real update takes.
 *
 * Three rows move here and each fails differently:
 *
 *   * `useronline` becomes `wp_useronline_options`, re-sanitised on the way, and
 *     the `versions` key 3.0.0 kept *inside* the settings is dropped -- this
 *     plugin is the reason §2.1 forbids that arrangement.
 *   * `useronline_most` becomes `wp_useronline_most` by rename and never by
 *     rebuild: it is the site's highest ever count and there is nowhere else to
 *     recover it from. Losing it is silent and permanent.
 *   * `stats_display` was shared with six sibling plugins. Its absence means
 *     "on", because whichever sibling upgraded first deleted it.
 *
 * Every row is read *raw*. WP_UserOnline_Options::get() merges over the
 * defaults, so it answers identically for a row holding the defaults and for no
 * row at all -- which is the §7.6.1 failure exactly. Ask the database.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	ONLINE_URL,
	SETTINGS_URL,
	defaultOptions,
	field,
	installLegacyRows,
	rawMost,
	rawOptions,
	resetOptions,
	runningVersions,
	setVersionRow,
	survivingLegacyRows,
	versionRow,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

/**
 * The settings row a stock 3.0.0 install carried.
 *
 * Built from the running defaults rather than transcribed, minus the two keys
 * 4.0.0 added -- `ip_header`, and this plugin's own copy of the WP-Stats toggle,
 * neither of which a 3.0.0 row can hold -- and with the `versions` key 3.0.0
 * kept inside the settings array put back, because dropping that is one of the
 * things the migration is for.
 *
 * @param {Object} overrides Anything this particular site had changed.
 * @return {Object} A legacy settings row.
 */
function stockLegacySettings( overrides = {} ) {
	const legacy = defaultOptions();

	delete legacy.ip_header;
	delete legacy.stats_display;

	return {
		...legacy,
		names: false,
		versions: { plugin: '3.0.0', db: '1' },
		...overrides,
	};
}

test.describe( 'The pre-4.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install. Every other spec in this suite starts from
		// one, and this is the only file that takes it apart.
		wpEval(
			`delete_option( 'useronline' );
			delete_option( 'useronline_most' );
			delete_option( 'stats_display' );
			echo '<<<done>>>';`,
		);
		setVersionRow( runningVersions() );
		resetOptions();
	} );

	test( 'a stock 3.0.0 row is renamed, written, cleaned of its markers and stamped', async ( {
		page,
	} ) => {
		// The fixture is asserted from what the seeding call itself saw, not
		// from a second one. maybe_upgrade() runs on plugins_loaded, so a WP-CLI
		// request performs the upgrade too -- ask again through another
		// `wp eval` and the rows have already moved, the browser request below
		// has nothing left to do, and the test quietly becomes a test of WP-CLI.
		const before = installLegacyRows( stockLegacySettings() );

		expect( before.legacy ).toContain( 'useronline' );
		expect( before.options ).toBe( false );
		expect( before.version ).toBe( false );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		// Written, not merely readable through the defaults. This is the shape
		// §7.6.1 is about: the settings survived even though the result of the
		// migration is what the defaults would have answered anyway.
		expect( stored ).not.toBe( false );
		expect( stored.timeout ).toBe( defaultOptions().timeout );

		// The markers are out of the settings array and in a row of their own.
		// A marker inside the settings has to be rescued from the stored value
		// on every save, which is what §2.1 was written about and what this
		// plugin's 3.0.0 spent fourteen lines of plumbing on.
		expect( stored.versions ).toBeUndefined();
		expect( versionRow() ).toEqual( runningVersions() );

		// And the old row is gone rather than left to rot.
		expect( survivingLegacyRows() ).not.toContain( 'useronline' );
	} );

	test( 'the most-ever-online record is carried across by rename', async ( { page } ) => {
		// A record from a site that has been running for years. It is renamed
		// rather than rebuilt because there is nowhere else to recover it from:
		// the table holds who is online now, never the high-water mark.
		const when = 1600000000;

		const before = installLegacyRows( stockLegacySettings(), {
			most: { count: 4242, date: when },
		} );

		expect( before.most ).toBe( false );
		expect( before.legacy ).toContain( 'useronline_most' );

		await page.goto( DASHBOARD_URL );

		const most = rawMost();

		expect( most ).not.toBe( false );
		expect( parseInt( most.count, 10 ) ).toBe( 4242 );
		expect( parseInt( most.date, 10 ) ).toBe( when );
		expect( survivingLegacyRows() ).not.toContain( 'useronline_most' );

		// Present is not alive: the record has to be the one the screen shows.
		await page.goto( ONLINE_URL );

		await expect( page.locator( '#wpbody-content' ) ).toContainText( '4,242' );
	} );

	test( "this plugin's share of the WP-Stats row is folded in and the row deleted", async ( {
		page,
	} ) => {
		// The shared row as the last of the seven plugins to save that screen
		// left it: a flag per plugin, and this one switched off.
		installLegacyRows( stockLegacySettings(), {
			statsDisplay: { useronline: 0, polls: 1 },
		} );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( false );

		// Deleted by the migration that folded it in -- and by nothing else.
		// §13.2 splits the two jobs: uninstall must leave this row alone,
		// because up to six siblings that have not upgraded are still reading it.
		expect( survivingLegacyRows() ).not.toContain( 'stats_display' );
	} );

	test( 'an absent shared row means on, not off', async ( { page } ) => {
		// A sibling upgraded first and took the row with it. Reading that as a
		// deliberate opt-out would make the users online block disappear from
		// the stats page of any site that happened to update a sibling first,
		// silently and with nothing in any log to explain it.
		const before = installLegacyRows( stockLegacySettings() );

		expect( before.legacy ).not.toContain( 'stats_display' );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( true );
	} );

	test( 'settings the owner changed survive, and reach the settings screen', async ( {
		page,
	} ) => {
		installLegacyRows(
			stockLegacySettings( {
				timeout: 900,
				names: true,
				naming: { ...defaultOptions().naming, guest: 'One visitor' },
			} ),
		);

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.timeout ).toBe( 900 );
		expect( stored.naming.guest ).toBe( 'One visitor' );

		// Present is not alive. A row in the right place that the screen does
		// not read is a migration that passed and a plugin that broke.
		await page.goto( SETTINGS_URL );

		await expect( page.locator( field( 'timeout' ) ) ).toHaveValue( '900' );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A legacy row that should never be read, alongside markers saying the
		// upgrade has already happened. maybe_upgrade() returning early is what
		// keeps every request from being an option write, and the proof it
		// returned early is that this row survives untouched.
		// Stamped first, in the same breath as the row is written: with the
		// markers already current, the WP-CLI request that writes this cannot
		// migrate it on the way in.
		wpEval(
			`update_option( 'wp_useronline_version', array(
				'plugin' => WP_USERONLINE_VERSION,
				'db'     => WP_USERONLINE_DB_VERSION,
			) );
			update_option( 'useronline', array( 'timeout' => 1234 ) );
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		expect( survivingLegacyRows() ).toContain( 'useronline' );
		expect( rawOptions().timeout ).not.toBe( 1234 );
	} );
} );
