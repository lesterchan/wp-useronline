/**
 * Shared steps for the WP-UserOnline end-to-end suite.
 *
 * Four things about this plugin shape everything below, and they are answered
 * here rather than being rediscovered in every spec.
 *
 * **Every request records the requester.** wp_head and admin_head both call the
 * recorder, so the browser driving these tests is itself one of the users
 * online, on every page it opens. A test that counts anything therefore
 * truncates the table first and then expects to find *itself* in it.
 *
 * **Two visitors cannot be two contexts of one browser.** record() removes a
 * guest's previous row by user agent and IP, and both contexts share those, so
 * the second visit deletes the first rather than joining it. Where a test needs
 * a crowd, the crowd goes in through insertOnline() with explicit timestamps
 * and IPs of its own -- addresses from the TEST-NET-3 range, which cannot
 * collide with the container's.
 *
 * **The table has no REST route**, so all of that goes through wpEval().
 *
 * **The front end is template tags.** users_online(), users_browsing_site() and
 * the rest are what a theme calls, and twentytwentyone calls none of them, so
 * installProbe() drops a mu-plugin that does -- standing in for the theme. The
 * widget and the [page_useronline] shortcode are real surfaces and are driven
 * as themselves.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const ONLINE_URL = '/wp-admin/admin.php?page=wp-useronline';
const SETTINGS_URL = '/wp-admin/admin.php?page=wp-useronline-settings';

/** A user agent from the plugin's own bot list, matched as 'googlebot'. */
const BOT_USER_AGENT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/** A user agent that is on no list, for a visitor who must be seen as a guest. */
const HUMAN_USER_AGENT =
	'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36';

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: the security spec stores
 * quotes, angle brackets and a script tag in the table, and a fixture that is
 * not the payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Run PHP and read back a JSON value, so types survive the round trip.
 *
 * @param {string} expression PHP expression to encode and return.
 * @return {*} The decoded value.
 */
function wpEvalJson( expression ) {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( ${ expression } ) . '>>>';` ) );
}

/**
 * Empty the useronline table.
 *
 * The first line of nearly every test: the suite's own browser is recorded on
 * every page it opens, so without this a count is a count of this run's history
 * rather than of the fixture.
 *
 * @return {void}
 */
function truncateOnline() {
	wpEval(
		`global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->useronline}" );
		echo '<<<done>>>';`,
	);
}

/**
 * Every row of the useronline table, newest first.
 *
 * @return {Array<Object>} The rows as stored.
 */
function onlineRows() {
	return wpEvalJson(
		'$GLOBALS["wpdb"]->get_results( "SELECT * FROM {$GLOBALS[\'wpdb\']->useronline} ORDER BY timestamp DESC, user_name ASC" )',
	);
}

/**
 * Put one row into the useronline table exactly as given.
 *
 * Nothing is sanitised on the way in, which is the point for the security spec:
 * page_title, page_url, referral, user_agent and a guest's name are all
 * visitor-supplied, so this is the row a real site already has.
 *
 * @param {Object} spec              What to insert.
 * @param {string} [spec.userType]   'member', 'guest' or 'bot'.
 * @param {number} [spec.userId]     User id, 0 for anyone not logged in.
 * @param {string} [spec.userName]   Displayed name.
 * @param {string} [spec.userIp]     IP address.
 * @param {string} [spec.userAgent]  User agent string.
 * @param {string} [spec.pageTitle]  Title of the page they are on.
 * @param {string} [spec.pageUrl]    Site-relative URL they are on.
 * @param {string} [spec.referral]   Where they came from.
 * @param {number} [spec.secondsAgo] How long ago the row was written.
 * @return {void}
 */
function insertOnline( spec ) {
	const data = Buffer.from(
		JSON.stringify( {
			userType: 'guest',
			userId: 0,
			userName: 'Guest',
			userIp: '203.0.113.1',
			userAgent: 'Fixture/1.0',
			pageTitle: 'Fixture page',
			pageUrl: '/',
			referral: '',
			secondsAgo: 0,
			...spec,
		} ),
		'utf8',
	).toString( 'base64' );

	wpEval(
		`global $wpdb;
		$data = json_decode( base64_decode( '${ data }' ), true );
		$wpdb->replace(
			$wpdb->useronline,
			array(
				'timestamp'  => gmdate( 'Y-m-d H:i:s', strtotime( current_time( 'mysql' ) ) - (int) $data['secondsAgo'] ),
				'user_type'  => $data['userType'],
				'user_id'    => (int) $data['userId'],
				'user_name'  => $data['userName'],
				'user_ip'    => $data['userIp'],
				'user_agent' => $data['userAgent'],
				'page_title' => $data['pageTitle'],
				'page_url'   => $data['pageUrl'],
				'referral'   => $data['referral'],
			)
		);
		echo '<<<done>>>';`,
	);
}

/**
 * The COOKIEHASH this install uses, which names the comment author cookie.
 *
 * The recorder reads a logged out visitor's name out of comment_author_<hash>,
 * and the hash is derived from the site URL, so a test that wants to be a
 * returning commenter has to ask the install rather than guess.
 *
 * @return {string} The hash.
 */
function cookieHash() {
	return wpEval( "echo '<<<' . COOKIEHASH . '>>>';" );
}

/**
 * One setting, as the plugin's own code reads it.
 *
 * @param {string} key Top level setting name.
 * @return {*} The stored value.
 */
function option( key ) {
	return wpEvalJson( `WP_UserOnline_Options::get( '${ key }' )` );
}

/**
 * Overwrite settings, leaving everything else alone.
 *
 * For arranging a precondition, never for asserting one: a setting a test is
 * actually about goes in through the settings screen, so the sanitiser between
 * the form and the row is exercised by the test that depends on it.
 *
 * Recursive, because naming and templates are nested and a test usually wants
 * to change one leaf of them.
 *
 * @param {Object} values Keys to overwrite.
 * @return {void}
 */
function setOptions( values ) {
	const encoded = Buffer.from( JSON.stringify( values ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$values = json_decode( base64_decode( '${ encoded }' ), true );
		WP_UserOnline_Options::update( array_replace_recursive( WP_UserOnline_Options::get(), $values ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Put every setting, and the most-ever-online record, back to a fresh install.
 *
 * @return {void}
 */
function resetOptions() {
	wpEval(
		`WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );
		delete_option( WP_UserOnline_Options::MOST );
		echo '<<<done>>>';`,
	);
}

/**
 * The most-ever-online record.
 *
 * @return {Object} count and date, as stored.
 */
function most() {
	return wpEvalJson( 'WP_UserOnline_Options::most()' );
}

/**
 * Set the most-ever-online record.
 *
 * @param {number} count      Users online.
 * @param {number} secondsAgo How long ago the record was set.
 * @return {void}
 */
function setMost( count, secondsAgo = 0 ) {
	wpEval(
		`WP_UserOnline_Options::update_most( ${ count }, time() - ${ secondsAgo } );
		echo '<<<done>>>';`,
	);
}

/**
 * The mu-plugin that stands in for a theme's calls to the template tags.
 *
 * Every one of the plugin's front-end tags is here, each in a container of its
 * own, so a spec can assert on one without the others' output getting in the
 * way. The ids are deliberately *not* the useronline-* ones the refresh script
 * looks for: those belong to the widget and to users_online_page(), and a probe
 * that claimed them would have the script polling the wrong element.
 */
const PROBE_SOURCE = `<?php
/**
 * Plugin Name: WP-UserOnline E2E probe
 * Description: Calls the template tags from wp_footer, standing in for a theme.
 */
add_action(
	'wp_footer',
	function () {
		echo '<div id="uo-online">';
		users_online();
		echo '</div>';

		echo '<div id="uo-count">';
		users_online_count();
		echo '</div>';

		echo '<div id="uo-most">';
		most_users_online();
		echo '</div>';

		echo '<div id="uo-most-date">';
		most_users_online_date();
		echo '</div>';

		echo '<div id="uo-browsing-site">';
		users_browsing_site();
		echo '</div>';

		echo '<div id="uo-browsing-page">';
		users_browsing_page();
		echo '</div>';

		printf(
			'<div id="uo-is-online" data-online="%s"></div>',
			esc_attr( is_user_online( (int) get_option( 'uo_e2e_user', 0 ) ) ? '1' : '0' )
		);

		printf(
			'<div id="uo-counts" data-json="%s"></div>',
			esc_attr( (string) wp_json_encode( get_useronline_( 'counts' ) ) )
		);
	},
	5
);
`;

/**
 * Install the probe mu-plugin.
 *
 * @return {void}
 */
function installProbe() {
	const encoded = Buffer.from( PROBE_SOURCE, 'utf8' ).toString( 'base64' );

	wpEval(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		file_put_contents( WPMU_PLUGIN_DIR . '/wp-useronline-e2e-probe.php', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Remove the probe mu-plugin and the option it reads.
 *
 * @return {void}
 */
function removeProbe() {
	wpEval(
		`$file = WPMU_PLUGIN_DIR . '/wp-useronline-e2e-probe.php';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		delete_option( 'uo_e2e_user' );
		echo '<<<done>>>';`,
	);
}

/**
 * Tell the probe which user id to ask is_user_online() about.
 *
 * @param {number} userId User id.
 * @return {void}
 */
function probeUser( userId ) {
	wpEval( `update_option( 'uo_e2e_user', ${ userId } ); echo '<<<done>>>';` );
}

/**
 * Put one UserOnline widget in twentytwentyone's sidebar.
 *
 * Straight into widget_useronline and sidebars_widgets rather than through the
 * widgets screen: that screen is the block editor's Legacy Widget wrapper, and
 * driving it would test Gutenberg rather than this plugin. The instance is
 * exactly what the screen would have written, and the widget's own form is
 * covered by tests/test-widget.php.
 *
 * @param {Object} instance Widget settings: title and type.
 * @return {void}
 */
function addUserOnlineWidget( instance ) {
	const encoded = Buffer.from( JSON.stringify( instance ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$instance = json_decode( base64_decode( '${ encoded }' ), true );
		update_option( 'widget_useronline', array( 9 => $instance, '_multiwidget' => 1 ) );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array( 'useronline-9' );
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Empty the sidebar again.
 *
 * @return {void}
 */
function removeUserOnlineWidget() {
	wpEval(
		`delete_option( 'widget_useronline' );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array();
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Do something in a browser nobody is logged in to.
 *
 * @param {import('@playwright/test').Page} page    Page under test, for its browser.
 * @param {Object}                          options Extra newContext options, e.g. a user agent.
 * @param {Function}                        run     Called with the guest page.
 * @return {Promise<void>} Resolves once the guest context has been closed.
 */
async function asGuest( page, options, run ) {
	const context = await page.context().browser().newContext( {
		userAgent: HUMAN_USER_AGENT,
		...options,
		storageState: undefined,
	} );

	try {
		await run( await context.newPage() );
	} finally {
		await context.close();
	}
}

/**
 * Open the settings screen.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page ) {
	await page.goto( SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'UserOnline Settings' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for the screen to come back.
 *
 * Deliberately *not* waiting for "Settings saved." here. This screen lives
 * under admin.php rather than options-general.php, and core only prints that
 * notice from wp-admin/options-head.php, which admin.php does not include -- so
 * a page that does not call settings_errors() itself shows nothing at all. Its
 * presence is a single dedicated test in settings.spec.js rather than a
 * precondition of every save, because every assertion in this suite is about
 * what was stored, and a missing notice must not hide all of them.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once options.php has sent the browser back.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	await page.waitForURL( /settings-updated=true/ );
	await expect( page.getByRole( 'heading', { name: 'UserOnline Settings' } ) ).toBeVisible();
}

/**
 * Log a second browser context in as a named user.
 *
 * wp-login.php focuses and *selects* #user_login on a 200ms timer so a visitor
 * can start typing. Filling across that moment puts the password into the
 * username box -- Playwright focuses #user_pass, the timer takes focus back and
 * selects what is there, and the typed text replaces the selection. Waiting for
 * the timer's own effect is the signal that it has already fired; a
 * waitForTimeout only makes the race less likely.
 *
 * @param {import('@playwright/test').Page} page     Page under test, for its browser.
 * @param {string}                          username Username to log in as.
 * @param {string}                          password That user's password.
 * @return {Promise<import('@playwright/test').Page>} A page carrying that session.
 */
async function loginAs( page, username, password ) {
	const context = await page.context().browser().newContext( { storageState: undefined } );
	const other = await context.newPage();

	await other.goto( '/wp-login.php' );
	await expect( other.locator( '#user_login' ) ).toBeFocused();

	await other.locator( '#user_login' ).fill( username );
	await other.locator( '#user_pass' ).fill( password );
	await other.locator( '#wp-submit' ).click();
	await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

	return other;
}

/**
 * Create a user, or reset the one an earlier run already created.
 *
 * Through WP-CLI rather than REST, because REST answers "that login is taken"
 * with an error and there is no second call that reliably finds the account
 * again -- the suite is run more than once against the same database, so the
 * second run has to be able to log in as the account the first one made.
 *
 * @param {string} username Username.
 * @param {string} role     Role slug.
 * @param {string} password Password.
 * @return {number} The user id.
 */
function ensureUser( username, role, password ) {
	return parseInt(
		wpEval(
			`$login = '${ username }';
			$user = get_user_by( 'login', $login );

			if ( $user ) {
				$id = (int) $user->ID;
				wp_set_password( '${ password }', $id );
				$user = new WP_User( $id );
				$user->set_role( '${ role }' );
			} else {
				$id = (int) wp_insert_user( array(
					'user_login' => $login,
					'user_pass'  => '${ password }',
					'user_email' => $login . '@example.com',
					'role'       => '${ role }',
				) );
			}

			echo '<<<' . $id . '>>>';`,
		),
		10,
	);
}

/**
 * The name attribute the settings screen gives a nested option key.
 *
 * @param {...string} keys Nested keys.
 * @return {string} A CSS selector for that field.
 */
function field( ...keys ) {
	return `[name="wp_useronline_options[${ keys.join( '][' ) }]"]`;
}

/**
 * A title no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

module.exports = {
	BOT_USER_AGENT,
	HUMAN_USER_AGENT,
	ONLINE_URL,
	SETTINGS_URL,
	addUserOnlineWidget,
	asGuest,
	cookieHash,
	ensureUser,
	field,
	insertOnline,
	installProbe,
	loginAs,
	most,
	onlineRows,
	openSettings,
	option,
	probeUser,
	removeProbe,
	removeUserOnlineWidget,
	resetOptions,
	saveSettings,
	setMost,
	setOptions,
	truncateOnline,
	uniqueTitle,
	wpEval,
	wpEvalJson,
};
