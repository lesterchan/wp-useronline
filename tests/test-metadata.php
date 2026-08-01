<?php
/**
 * The checks every one of the nineteen plugins carries.
 *
 * They are about the things a release gets wrong quietly: a readme header that
 * lost its line breaks and renders as one run-on paragraph on wordpress.org, a
 * version that agrees with itself in two places out of three, an option row
 * that outlives the plugin that made it. None of them need a browser and none
 * of them need judgement, so they belong in the suite rather than in a
 * pre-release checklist nobody reads.
 *
 * @package WP-UserOnline
 */

/**
 * Plugin metadata, layout and the option rows.
 */
class WP_UserOnline_Metadata_Test extends WP_UserOnline_TestCase {

	/**
	 * The nine README header fields, in order.
	 *
	 * @var array
	 */
	private static $header_fields = array(
		'Contributors:',
		'Donate link:',
		'Tags:',
		'Requires at least:',
		'Tested up to:',
		'Stable tag:',
		'Requires PHP:',
		'License:',
		'License URI:',
	);

	/**
	 * The h2 headings a README may carry, in the order they must appear.
	 *
	 * @var array
	 */
	private static $sections = array(
		'## Description',
		'## Usage',
		'## Frequently Asked Questions',
		'## Screenshots',
		'## Changelog',
		'## Upgrade Notice',
	);

	/**
	 * The README, as shipped.
	 *
	 * @return string
	 */
	private function readme() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/README.md' );
	}

	/**
	 * The main plugin file, as shipped.
	 *
	 * @return string
	 */
	private function plugin_file() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/wp-useronline.php' );
	}

	/**
	 * One value from the README header.
	 *
	 * @param string $field Field name, including the colon.
	 *
	 * @return string
	 */
	private function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One value from the plugin file header.
	 *
	 * @param string $field Field name, including the colon.
	 *
	 * @return string
	 */
	private function header_field( $field ) {
		preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->plugin_file(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * Markdown turns a line ending in two spaces into a line break. Without
	 * them wordpress.org renders the whole header as one paragraph, which is
	 * the single most common thing to get wrong in a plugin readme.
	 *
	 * @return void
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$lines  = explode( "\n", $this->readme() );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}

			$header[] = $line;
		}

		$this->assertCount( 9, $header, 'the README header should be exactly nine fields' );

		foreach ( self::$header_fields as $index => $field ) {
			$this->assertStringStartsWith( $field, trim( $header[ $index ] ), 'header field ' . ( $index + 1 ) . ' is out of order' );
		}

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith( '  ', $line, trim( $line ) . ' has lost its two trailing spaces' );
		}

		$last = $header[8];
		$this->assertSame( rtrim( $last ), $last, 'the last header line must not have trailing spaces' );
	}

	public function test_canonical_lesterchan_urls() {
		$this->assertSame( 'https://lesterchan.net/portfolio/programming/php/', $this->header_field( 'Plugin URI:' ), 'the Plugin URI is wrong' );
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI:' ), 'the Author URI is wrong' );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link:' ), 'the Donate link is wrong' );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI:' ), 'the README License URI is wrong' );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->header_field( 'License URI:' ), 'the header License URI is wrong' );
	}

	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors:' ), 'Contributors names somebody else' );
	}

	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-useronline', $this->header_field( 'Text Domain:' ), 'the text domain is not the slug' );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path:' ), 'the domain path is wrong' );
		$this->assertSame( 'wp-useronline', WP_USERONLINE_SLUG, 'the slug constant is wrong' );
	}

	public function test_version_matches_everywhere() {
		$this->assertSame( WP_USERONLINE_VERSION, $this->header_field( 'Version:' ), 'the plugin header disagrees with the constant' );
		$this->assertSame( WP_USERONLINE_VERSION, $this->readme_field( 'Stable tag:' ), 'the README Stable tag disagrees with the constant' );
	}

	/**
	 * 3.0.0 is live on wordpress.org, and this release renames four filters it
	 * promised were stable, so it cannot ship as a patch.
	 *
	 * @return void
	 */
	public function test_the_version_is_the_major_the_filter_renames_require() {
		$this->assertSame( '4.0.0', WP_USERONLINE_VERSION, 'this release renames public filters and must be a major' );
	}

	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->header_field( 'Requires at least:' ), 'the header WordPress floor is wrong' );
		$this->assertSame( '6.8', $this->readme_field( 'Requires at least:' ), 'the README WordPress floor is wrong' );
		$this->assertSame( '8.2', $this->header_field( 'Requires PHP:' ), 'the header PHP floor is wrong' );
		$this->assertSame( '8.2', $this->readme_field( 'Requires PHP:' ), 'the README PHP floor is wrong' );
	}

	/**
	 * Level three headings are free; this is only about the h2s.
	 *
	 * @return void
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## .+$/m', $this->readme(), $matches );

		$found = array_map( 'rtrim', $matches[0] );

		foreach ( $found as $heading ) {
			$this->assertContains( $heading, self::$sections, $heading . ' is not one of the canonical headings' );
		}

		$expected = array_values(
			array_filter(
				self::$sections,
				static function ( $section ) use ( $found ) {
					return in_array( $section, $found, true );
				}
			)
		);

		$this->assertSame( $expected, $found, 'the sections are out of order' );

		foreach ( array( '## Description', '## Changelog', '## Upgrade Notice' ) as $required ) {
			$this->assertContains( $required, $found, $required . ' is missing' );
		}
	}

	/**
	 * Donations is mandated as the last h3 of Description.
	 *
	 * @return void
	 */
	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = $this->readme();
		$description = substr( $readme, strpos( $readme, '## Description' ) );
		$description = substr( $description, 0, strpos( $description, '## Usage' ) );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertNotEmpty( $matches[0], 'the Description carries no h3 at all' );
		$this->assertSame( '### Donations', rtrim( end( $matches[0] ) ), 'Donations must be the last h3 of the Description' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins',
			$description,
			'the Donations wording has drifted'
		);
	}

	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, strpos( $readme, '## Changelog' ) );
		$end       = strpos( $changelog, '## Upgrade Notice' );
		$changelog = false === $end ? $changelog : substr( $changelog, 0, $end );

		preg_match_all( '/^\* (.*)$/m', $changelog, $matches );

		$this->assertNotEmpty( $matches[1], 'the changelog has no entries at all' );

		foreach ( $matches[1] as $entry ) {
			$this->assertMatchesRegularExpression(
				'/^(BREAKING|NEW|CHANGED|FIXED|NOTE): /',
				$entry,
				'changelog entry does not start with an allowed prefix: ' . $entry
			);
		}
	}

	/**
	 * Every hook this release renamed has to be named in the Upgrade Notice,
	 * because the old names were dropped with no shim and a site owner has no
	 * other way to find out.
	 *
	 * @return void
	 */
	public function test_every_dropped_hook_name_appears_in_the_upgrade_notice() {
		$readme = $this->readme();
		$notice = substr( $readme, strpos( $readme, '## Upgrade Notice' ) );

		foreach ( array(
			'useronline_bots',
			'useronline_buckets',
			'useronline_custom_template',
			'useronline_page',
			'useronline_display_user',
			'useronline_trust_proxy',
			'USERONLINE_TRUST_PROXY',
		) as $dropped ) {
			$this->assertStringContainsString( $dropped, $notice, $dropped . ' was dropped without telling anybody' );
		}
	}

	/**
	 * Not just a source grep: a dependency array built at runtime would pass
	 * that on its own, so the registered handle is checked as well.
	 *
	 * @return void
	 */
	public function test_no_jquery_is_enqueued() {
		WP_UserOnline_Template::compact_list( 'site' );
		WP_UserOnline::get_instance()->enqueue_scripts();
		WP_UserOnline_Admin::add_page();
		$_GET['tab'] = WP_UserOnline_Admin::TAB_SETTINGS;
		WP_UserOnline_Settings::enqueue_scripts( WP_UserOnline_Admin::screen_hook() );

		$scripts = wp_scripts();

		foreach ( array( 'wp-useronline', 'wp-useronline-admin' ) as $handle ) {
			$this->assertArrayHasKey( $handle, $scripts->registered, $handle . ' was not registered' );
			$this->assertSame( array(), $scripts->registered[ $handle ]->deps, $handle . ' declares a dependency; it must declare none' );
		}

		foreach ( glob( dirname( __DIR__ ) . '/js/*.js' ) as $file ) {
			$this->assertDoesNotMatchRegularExpression( '/\bjQuery\b|\$\(/', (string) file_get_contents( $file ), basename( $file ) . ' uses jQuery' );
		}
	}

	public function test_every_directory_has_an_index_php() {
		$root = dirname( __DIR__ );

		$directories = array( '', '/bin', '/includes', '/js', '/tests', '/tests/e2e', '/tests/js' );

		foreach ( $directories as $directory ) {
			$this->assertFileExists( $root . $directory . '/index.php', ( '' === $directory ? '/' : $directory ) . ' has no index.php' );
		}

		// The list above is asserted to be complete rather than trusted. A
		// pruning filter, not one applied afterwards: a plain filter descends
		// into node_modules and vendor before discarding them, which is slow
		// enough to look like a hang.
		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				static function ( $current ) {
					if ( ! $current->isDir() ) {
						return false;
					}

					$name = $current->getFilename();

					// artifacts/ is Playwright's: traces, screenshots and the
					// stored admin session from a local run. It is gitignored
					// and never shipped, so it has no index.php and no business
					// being walked here.
					return ! in_array( $name, array( 'node_modules', 'vendor', 'languages', 'artifacts' ), true ) && '.' !== $name[0];
				}
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $directory ) {
			$this->assertFileExists( $directory->getPathname() . '/index.php', $directory->getPathname() . ' has no index.php' );
		}
	}

	/**
	 * A second sheet is a second thing to keep in step, and it only ever
	 * existed because the first one used physical properties. This plugin ships
	 * no stylesheet at all, so it must not start registering rtl data either.
	 *
	 * @return void
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$this->assertEmpty( glob( dirname( __DIR__ ) . '/css/*-rtl.css' ), 'an RTL stylesheet is still shipped' );

		WP_UserOnline_Template::compact_list( 'site' );
		WP_UserOnline::get_instance()->enqueue_scripts();

		foreach ( array( 'wp-useronline', 'wp-useronline-admin' ) as $handle ) {
			$this->assertFalse( wp_styles()->get_data( $handle, 'rtl' ), $handle . ' declares rtl style data' );
		}
	}

	/**
	 * Runs on a network too, where uninstall.php loops over every site and the
	 * single-site path is never taken.
	 *
	 * This is the only test file in the plugin that loads uninstall.php.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );
		WP_UserOnline_Options::update_markers();
		WP_UserOnline_Options::update_most( 5, time() );

		$second = 0;

		if ( is_multisite() ) {
			$second = self::factory()->blog->create();

			switch_to_blog( $second );
			WP_UserOnline_Install::install();
			restore_current_blog();
		}

		$this->run_uninstall();

		$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_useronline\\_%'" );

		$this->assertSame( array(), $surviving, 'uninstall left rows behind: ' . implode( ', ', (array) $surviving ) );

		if ( is_multisite() ) {
			switch_to_blog( $second );
			$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_useronline\\_%'" );
			restore_current_blog();

			$this->assertSame( array(), $surviving, 'uninstall stopped at the first site of the network' );
		}
	}

	/**
	 * The shared row is six other plugins' too, and some of them may not have
	 * upgraded yet.
	 *
	 * @return void
	 */
	public function test_uninstall_leaves_the_shared_wp_stats_row_alone() {
		update_option( 'stats_display', array( 'polls' => 1 ) );

		$this->run_uninstall();

		$this->assertSame( array( 'polls' => 1 ), get_option( 'stats_display' ), 'uninstalling took a sibling plugin\'s setting with it' );
	}

	/**
	 * Run uninstall.php the way WordPress does.
	 *
	 * Safe to call more than once: uninstall.php declares no functions of its
	 * own any more, it requires the installer and calls it.
	 *
	 * @return void
	 */
	private function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-useronline/wp-useronline.php' );
		}

		require dirname( __DIR__ ) . '/uninstall.php';

		// The table comes back for whatever runs next; the suite's transaction
		// does not roll a DROP TABLE back.
		WP_UserOnline_Install::install_table();
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_UserOnline_Options::update_markers();

		$markers = get_option( WP_UserOnline_Options::VERSION );

		$this->assertIsArray( $markers, 'the marker row should be an array' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'the marker row carries exactly two keys' );
		$this->assertSame( WP_USERONLINE_VERSION, $markers['plugin'], 'the plugin marker is wrong' );
		$this->assertSame( WP_USERONLINE_DB_VERSION, $markers['db'], 'the db marker is wrong' );
	}

	/**
	 * This plugin is the one the rule was written from: with the markers inside
	 * the settings array, every save had to rescue them by hand, and the one
	 * that forgot made the upgrade re-run on every request. It fails the moment
	 * somebody moves a marker back in.
	 *
	 * @return void
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_UserOnline_Options::sanitize(
			array(
				'timeout'    => 120,
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, $key . ' reached the settings row' );
		}

		WP_UserOnline_Options::update( $clean );

		$this->assertSame( array( 'plugin', 'db' ), array_keys( (array) get_option( WP_UserOnline_Options::VERSION ) ), 'the marker row was disturbed by a save' );
	}

	/**
	 * Exactly the two autoloaded rows the standard allows, plus the record row
	 * which must not be autoloaded.
	 *
	 * @return void
	 */
	public function test_the_plugin_owns_two_autoloaded_rows_and_no_more() {
		global $wpdb;

		WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );
		WP_UserOnline_Options::update_markers();
		WP_UserOnline_Options::update_most( 5, time() );

		$autoloaded = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options}
			WHERE option_name LIKE 'wp\\_useronline\\_%'
			AND autoload IN ( 'yes', 'on', 'auto', 'auto-on' )"
		);

		sort( $autoloaded );

		$this->assertSame(
			array( WP_UserOnline_Options::OPTION, WP_UserOnline_Options::VERSION ),
			$autoloaded,
			'the settings and the markers are the only rows that may be autoloaded'
		);
	}
	/**
	 * The plugin root, whatever the checkout is called.
	 *
	 * @return string
	 */
	protected function metadata_root() {
		return dirname( __DIR__ );
	}

	/**
	 * Every PHP file the plugin ships, concatenated.
	 *
	 * @return string
	 */
	protected function metadata_source() {
		$source = '';

		foreach ( (array) glob( $this->metadata_root() . '/*.php' ) as $file ) {
			$source .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		}

		foreach ( (array) glob( $this->metadata_root() . '/includes/*.php' ) as $file ) {
			$source .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		}

		return $source;
	}

	/**
	 * The GPL text ships with the plugin.
	 *
	 * @return void
	 */
	public function test_the_gpl_licence_is_shipped() {
		$licence = (string) file_get_contents( $this->metadata_root() . '/LICENSE' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own licence in a test.

		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $licence, 'The GPL text must ship with the plugin.' );
		$this->assertStringContainsString( 'Version 2, June 1991', $licence, 'The licence must be GPLv2, matching the plugin header.' );
	}

	/**
	 * The plugin header fields appear in the canonical order.
	 *
	 * @return void
	 */
	public function test_the_plugin_header_fields_are_in_the_canonical_order() {
		$expected = array(
			'Plugin Name',
			'Plugin URI',
			'Description',
			'Version',
			'Requires at least',
			'Requires PHP',
			'Author',
			'Author URI',
			'License',
			'License URI',
			'Text Domain',
			'Domain Path',
		);

		// The main file is named for the directory, which is what wordpress.org
		// installs it as.
		$main = $this->metadata_root() . '/' . basename( $this->metadata_root() ) . '.php';
		$this->assertFileExists( $main, 'The main plugin file is named after the plugin directory.' );

		preg_match( '#^<\?php\s*/\*\*(.+?)\*/#s', (string) file_get_contents( $main ), $matches ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		$this->assertNotEmpty( $matches, 'The plugin file must open with a docblock header.' );

		preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]*?):\s/m', $matches[1], $fields );

		$this->assertSame( $expected, $fields[1], 'Plugin header fields must appear in the canonical order.' );
	}

	/**
	 * The plugin leaves loading its translations to WordPress.
	 *
	 * @return void
	 */
	public function test_the_plugin_does_not_load_its_own_textdomain() {
		// WordPress has loaded translations for wordpress.org plugins itself
		// since 4.6, so a load_plugin_textdomain() call is dead weight that
		// also fires before the plugin is on the translation server.
		$this->assertStringNotContainsString(
			'load_plugin_textdomain',
			$this->metadata_source(),
			'WordPress loads the textdomain itself since 4.6.'
		);
	}

	/**
	 * No build, editor or translation artefacts ship.
	 *
	 * @return void
	 */
	public function test_no_abandoned_build_or_translation_artefacts_ship() {
		$root = $this->metadata_root();

		$this->assertFileDoesNotExist( $root . '/.travis.yml', 'CI is GitHub Actions.' );
		$this->assertFileDoesNotExist( $root . '/.wp-env.override.json', 'A personal wp-env override must not ship.' );
		$this->assertDirectoryDoesNotExist( $root . '/languages', 'translate.wordpress.org builds the catalogue.' );
		$this->assertDirectoryDoesNotExist( $root . '/.idea', 'Editor settings must not ship.' );

		foreach ( array( 'pot', 'po', 'mo' ) as $extension ) {
			$this->assertSame(
				array(),
				(array) glob( $root . '/*.' . $extension ),
				"No .{$extension} files: translate.wordpress.org builds the catalogue."
			);
		}
	}
}
