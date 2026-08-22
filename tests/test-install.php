<?php
/**
 * Tests for the table install, the upgrade gate and the plugin's hooks.
 *
 * @package WP-UserOnline
 */

/**
 * This code only runs on activation or a version bump, so it is easy to break
 * without noticing.
 */
class WP_UserOnline_Install_Test extends WP_UserOnline_TestCase {

	/**
	 * No translation is asked for in add_hooks(), which runs too early for one.
	 *
	 * Hooks are registered on plugins_loaded, which is before after_setup_theme,
	 * and core's _load_textdomain_just_in_time() raises _doing_it_wrong for any
	 * translation requested before that. Reading a setting there was enough to
	 * trip it, because the defaults carry __() strings for the naming and
	 * template fields -- so on any site with a language pack for this plugin
	 * every single page load carried the notice.
	 *
	 * Asserted by watching the gettext filters rather than by reading the
	 * source: the fix is "do not read options this early", and a source scan
	 * cannot tell that a call reaches a translated default three frames down.
	 */
	public function test_add_hooks_translates_nothing() {
		$translated = array();

		$watch = static function ( $translation, $text, $domain ) use ( &$translated ) {
			if ( 'wp-useronline' === $domain ) {
				$translated[] = $text;
			}

			return $translation;
		};

		add_filter( 'gettext', $watch, 1, 3 );
		add_filter( 'gettext_with_context', $watch, 1, 3 );

		WP_UserOnline::get_instance()->add_hooks();

		remove_filter( 'gettext', $watch, 1 );
		remove_filter( 'gettext_with_context', $watch, 1 );

		$this->assertSame(
			array(),
			$translated,
			'add_hooks() asked for a translation before after_setup_theme: ' . implode( ', ', $translated )
		);
	}

	public function test_the_table_name_is_registered_on_wpdb() {
		global $wpdb;

		$this->assertNotEmpty( $wpdb->useronline, 'the table name is not on $wpdb' );
		$this->assertSame( $wpdb->prefix . 'useronline', $wpdb->useronline, 'the table name is not prefixed' );
	}

	/**
	 * Registering it in $wpdb->tables is what keeps it correct across
	 * switch_to_blog() on multisite, since WordPress re-prefixes those.
	 */
	public function test_the_table_is_in_the_blog_table_list() {
		global $wpdb;

		$this->assertContains( 'useronline', $wpdb->tables, 'the table is not registered for switch_to_blog()' );
	}

	public function test_the_table_carries_every_column_the_recorder_writes() {
		global $wpdb;

		$columns = $wpdb->get_col( "DESC {$wpdb->useronline}", 0 );

		foreach ( array( 'timestamp', 'user_type', 'user_id', 'user_name', 'user_ip', 'user_agent', 'page_title', 'page_url', 'referral' ) as $column ) {
			$this->assertContains( $column, $columns, 'missing column ' . $column );
		}
	}

	public function test_installing_twice_is_harmless_because_reactivation_does_it() {
		global $wpdb;

		WP_UserOnline_Install::install();
		WP_UserOnline_Install::install();

		$this->assertSame( $wpdb->useronline, $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->useronline}'" ), 'the table did not survive a second install' );
		$this->assertNotContains( 'user_ip_2', $this->index_names(), 'dbDelta re-added the user_ip key on a second install' );
		$this->assertNotContains( 'user_id_2', $this->index_names(), 'dbDelta re-added the user_id key on a second install' );
	}

	/**
	 * The recorder deletes by user_id and counts by user_ip on every request,
	 * and the table's only other key starts with timestamp, so without these
	 * two keys both statements read the whole table.
	 */
	public function test_a_fresh_install_indexes_the_columns_the_recorder_filters_on() {
		global $wpdb;

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->useronline}" );

		WP_UserOnline_Install::install_table();

		$indexes = $this->index_names();

		$this->assertContains( 'user_ip', $indexes, 'a fresh install does not index user_ip' );
		$this->assertContains( 'user_id', $indexes, 'a fresh install does not index user_id' );
	}

	/**
	 * A site already on 4.0.0 gains the keys through the upgrade gate, and
	 * loses nothing on the way.
	 *
	 * The fixture is the CREATE TABLE transcribed from the 4.0.0 release on
	 * wordpress.org rather than from the install code, so this meets the table
	 * a real site has instead of asserting the code agrees with itself. The
	 * DDL commits the wrapping transaction either way, and maybe_upgrade()
	 * leaves the table in its current shape for the tests that follow.
	 */
	public function test_upgrading_the_released_schema_adds_the_keys_and_keeps_the_rows() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->useronline}" );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Building the legacy fixture, verbatim, is the test.
		$wpdb->query(
			"CREATE TABLE {$wpdb->useronline} (
				timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				user_type varchar(20) NOT NULL default 'guest',
				user_id bigint(20) NOT NULL default 0,
				user_name varchar(250) NOT NULL default '',
				user_ip varchar(39) NOT NULL default '',
				user_agent text NOT NULL,
				page_title text NOT NULL,
				page_url varchar(255) NOT NULL default '',
				referral varchar(255) NOT NULL default '',
				UNIQUE KEY useronline_id (timestamp, user_type, user_ip)
			) {$charset_collate}"
		);
		// phpcs:enable

		$user_name = 'Sévère & "quoted" 訪客';

		$this->record_row( array( 'user_name' => $user_name ) );

		update_option(
			WP_UserOnline_Options::VERSION,
			array(
				'plugin' => '4.0.0',
				'db'     => '1',
			)
		);

		WP_UserOnline_Install::maybe_upgrade();

		$indexes = $this->index_names();

		$this->assertContains( 'user_ip', $indexes, 'the upgrade did not add the user_ip key' );
		$this->assertContains( 'user_id', $indexes, 'the upgrade did not add the user_id key' );
		$this->assertSame( WP_USERONLINE_DB_VERSION, WP_UserOnline_Options::markers()['db'], 'the upgrade did not stamp the db marker' );
		$this->assertSame( $user_name, $wpdb->get_var( "SELECT user_name FROM {$wpdb->useronline}" ), 'the row recorded before the upgrade did not survive it' );
	}

	/**
	 * Every index name on the useronline table, each once.
	 *
	 * @return string[]
	 */
	private function index_names() {
		global $wpdb;

		return array_values( array_unique( $wpdb->get_col( "SHOW INDEX FROM {$wpdb->useronline}", 2 ) ) );
	}

	public function test_installing_stamps_both_markers_in_one_row() {
		delete_option( WP_UserOnline_Options::VERSION );

		WP_UserOnline_Install::install();

		$this->assertSame(
			array(
				'plugin' => WP_USERONLINE_VERSION,
				'db'     => WP_USERONLINE_DB_VERSION,
			),
			get_option( WP_UserOnline_Options::VERSION ),
			'the marker row should hold both markers after an install'
		);
	}

	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_UserOnline_Options::update_markers();

		$markers = get_option( WP_UserOnline_Options::VERSION );

		$this->assertIsArray( $markers, 'the marker row should be an array' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'the marker row carries exactly two keys' );
	}

	public function test_the_markers_live_in_their_own_row_and_nowhere_else() {
		WP_UserOnline_Install::install();

		$this->assertArrayNotHasKey( 'versions', (array) get_option( WP_UserOnline_Options::OPTION ), 'a marker is back inside the settings row' );
		$this->assertFalse( get_option( 'useronline_db_version' ), 'a standalone db version row was created' );
		$this->assertFalse( get_option( 'useronline_sanitize_version' ), 'a standalone sanitize version row was created' );
	}

	public function test_an_up_to_date_install_does_not_run_the_upgrade_again() {
		WP_UserOnline_Options::update( array( 'timeout' => 111 ) );
		WP_UserOnline_Options::update_markers();

		WP_UserOnline_Install::maybe_upgrade();

		$this->assertSame( 111, WP_UserOnline_Options::get( 'timeout' ), 'the upgrade ran on an install that was already current' );
	}

	public function test_a_stale_marker_makes_the_upgrade_run() {
		update_option(
			WP_UserOnline_Options::VERSION,
			array(
				'plugin' => '3.0.0',
				'db'     => '0',
			)
		);

		WP_UserOnline_Install::maybe_upgrade();

		$this->assertSame( WP_USERONLINE_VERSION, WP_UserOnline_Options::markers()['plugin'], 'the upgrade did not run on a stale marker' );
	}

	public function test_an_install_that_never_recorded_a_marker_runs_the_upgrade() {
		delete_option( WP_UserOnline_Options::VERSION );

		WP_UserOnline_Install::maybe_upgrade();

		$this->assertSame( WP_USERONLINE_DB_VERSION, WP_UserOnline_Options::markers()['db'], 'a fresh install did not get stamped' );
	}

	public function test_the_plugin_registers_the_hooks_it_needs_to_do_anything_at_all() {
		$plugin = WP_UserOnline::get_instance();

		$this->assertNotFalse( has_action( 'wp_head', array( $plugin, 'record' ) ), 'nothing records on the front end' );
		$this->assertNotFalse( has_action( 'admin_head', array( $plugin, 'record' ) ), 'nothing records in wp-admin' );
		$this->assertNotFalse( has_action( 'wp_footer', array( $plugin, 'enqueue_scripts' ) ), 'the script is never enqueued' );
		$this->assertNotFalse( has_action( 'wp_ajax_wp_useronline', array( $plugin, 'ajax' ) ), 'the endpoint is unreachable when logged in' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_wp_useronline', array( $plugin, 'ajax' ) ), 'the endpoint is unreachable when logged out' );
	}

	public function test_the_wp_stats_section_is_offered_without_probing_for_wp_stats() {
		$this->assertNotFalse(
			has_filter( 'wp_stats_sections', array( 'WP_UserOnline_WPStats', 'register_section' ) ),
			'the section is not offered; it must be hooked unconditionally'
		);
	}

	public function test_the_shortcode_is_registered_under_its_documented_name() {
		$this->assertTrue( shortcode_exists( 'page_useronline' ), 'the documented shortcode is gone' );
	}

	public function test_the_script_is_not_enqueued_when_nothing_on_the_page_needs_it() {
		$this->reset_statics();

		WP_UserOnline::get_instance()->enqueue_scripts();

		$this->assertFalse( wp_script_is( 'wp-useronline', 'enqueued' ), 'the script was enqueued for a page with no counters on it' );
	}

	public function test_the_script_is_enqueued_once_a_list_has_rendered() {
		WP_UserOnline_Template::compact_list( 'site' );

		WP_UserOnline::get_instance()->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wp-useronline', 'enqueued' ), 'the script was not enqueued for a page that needs it' );
	}

	public function test_the_script_carries_the_localised_object_the_standard_names() {
		WP_UserOnline_Template::compact_list( 'site' );

		WP_UserOnline::get_instance()->enqueue_scripts();

		$data = wp_scripts()->get_data( 'wp-useronline', 'data' );

		$this->assertStringContainsString( 'wpUserOnlineL10n', (string) $data, 'the l10n object is not named wpUserOnlineL10n' );
		$this->assertStringContainsString( 'ajaxUrl', (string) $data, 'the ajax URL is not localised' );
	}
	/**
	 * The uninstaller names the same rows the suite deletes, and drops the table.
	 *
	 * The suite's run_uninstall() performs the option deletions rather than
	 * requiring uninstall.php, because that file drops the useronline table --
	 * DDL, which MySQL commits, so one test would take the table away from
	 * every test after it. That indirection is only honest if what the file
	 * reaches is checked to do the same work, which is what this does:
	 * uninstall.php hands everything to the installer, and the installer's
	 * per-site half deletes over the same all_option_names() list the suite
	 * uses and drops the table.
	 */
	public function test_uninstall_php_names_the_same_rows_and_drops_the_table() {
		$uninstall = (string) file_get_contents( dirname( __DIR__ ) . '/uninstall.php' );

		$this->assertStringContainsString( 'WP_UserOnline_Install::uninstall()', $uninstall, 'uninstall.php does not delegate to the installer.' );
		$this->assertStringContainsString( 'class-wp-useronline-options.php', $uninstall, 'uninstall.php does not load the options class the row list lives on.' );

		$install = (string) php_strip_whitespace( dirname( __DIR__ ) . '/includes/class-wp-useronline-install.php' );

		$this->assertStringContainsString( 'WP_UserOnline_Options::all_option_names()', $install, 'The uninstaller does not delete over the same row list the suite does.' );
		$this->assertStringContainsString( 'DROP TABLE IF EXISTS', $install, 'The uninstaller does not drop the useronline table.' );
	}

	/**
	 * The uninstaller walks the whole network, not the first hundred sites.
	 *
	 * A source guard because the thing it guards cannot be exercised: building
	 * a 101-site network to prove the hundred-and-first is reached is not on.
	 * php_strip_whitespace() rather than file_get_contents(), so a comment
	 * merely describing the old wp_get_sites() call cannot fail the assertion
	 * that the code no longer makes it.
	 */
	public function test_uninstall_walks_the_whole_network() {
		$install = (string) php_strip_whitespace( dirname( __DIR__ ) . '/includes/class-wp-useronline-install.php' );

		$this->assertStringContainsString( 'is_multisite()', $install, 'The uninstaller does not branch on multisite.' );
		$this->assertStringContainsString( "'number' => 0", $install, 'The uninstaller stops at the default hundred sites.' );
		$this->assertStringContainsString( "'fields' => 'ids'", $install, 'The uninstaller hydrates whole site objects to read one column.' );
		$this->assertStringNotContainsString( 'wp_get_sites', $install, 'wp_get_sites() is capped at 100 sites, so a larger network uninstalls in part.' );
		$this->assertMatchesRegularExpression(
			'/switch_to_blog\([^}]*restore_current_blog\(\)/s',
			$install,
			'The uninstaller does not close a block between switch_to_blog() and restore_current_blog().'
		);
	}
}
