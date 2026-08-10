<?php
/**
 * Tests for WP_UserOnline_Options.
 *
 * @package WP-UserOnline
 */

/**
 * Sanitization, defaults, the upgrade markers and the 4.0.0 migration.
 */
class WP_UserOnline_Options_Test extends WP_UserOnline_TestCase {

	public function test_a_missing_key_falls_back_to_its_default_rather_than_warning() {
		WP_UserOnline_Options::update( array( 'timeout' => 42 ) );

		$this->assertSame( 42, WP_UserOnline_Options::get( 'timeout' ), 'the stored value should win' );
		$this->assertSame( '1 Member', WP_UserOnline_Options::get( 'naming' )['member'], 'the default should fill the gap' );
	}

	public function test_an_unknown_key_returns_null_instead_of_a_notice() {
		$this->assertNull( WP_UserOnline_Options::get( 'no_such_setting' ), 'an unknown key should be null' );
	}

	public function test_a_corrupt_option_row_does_not_take_the_defaults_with_it() {
		update_option( WP_UserOnline_Options::OPTION, 'not an array' );

		$this->assertSame( 300, WP_UserOnline_Options::get( 'timeout' ), 'the defaults should stand in' );
	}

	public function test_script_tags_are_stripped_from_the_naming_conventions() {
		$clean = WP_UserOnline_Options::sanitize(
			array( 'naming' => array( 'user' => '1 User<script>alert(1)</script>' ) )
		);

		$this->assertStringNotContainsString( '<script', $clean['naming']['user'], 'a script tag survived the sanitizer' );
		$this->assertStringStartsWith( '1 User', $clean['naming']['user'], 'the rest of the value should be kept' );
	}

	public function test_a_javascript_url_is_rejected_outright() {
		$clean = WP_UserOnline_Options::sanitize( array( 'url' => 'javascript:alert(1)' ) );

		$this->assertSame( '', $clean['url'], 'a javascript: URL should not be stored' );
	}

	public function test_a_partial_submission_cannot_leave_the_renderer_without_its_nested_keys() {
		$clean = WP_UserOnline_Options::sanitize( array( 'templates' => array( 'browsingsite' => 'not an array' ) ) );

		$this->assertArrayHasKey( 'text', $clean['templates']['browsingsite'], 'the template text key is missing' );
		$this->assertArrayHasKey( 'bots', $clean['templates']['browsingsite']['separators'], 'a separator key is missing' );
		$this->assertArrayHasKey( 'text', $clean['templates']['browsingpage'], 'the sibling template was dropped' );
	}

	public function test_separators_keep_their_whitespace_because_that_is_what_parts_the_names() {
		$clean = WP_UserOnline_Options::sanitize( array() );

		$this->assertSame( ', ', $clean['templates']['browsingsite']['separators']['members'], 'the trailing space was trimmed away' );
	}

	public function test_a_zero_timeout_is_refused_because_it_would_purge_every_row() {
		$clean = WP_UserOnline_Options::sanitize( array( 'timeout' => '0' ) );

		$this->assertSame( 300, $clean['timeout'], 'a zero timeout should fall back to the default' );
	}

	public function test_running_the_sanitizer_twice_does_not_keep_changing_the_value() {
		$once  = WP_UserOnline_Options::sanitize( array() );
		$twice = WP_UserOnline_Options::sanitize( $once );

		$this->assertSame( $once, $twice, 'the sanitizer is not idempotent' );
	}

	public function test_anything_that_is_not_an_array_sanitizes_to_the_defaults() {
		$clean = WP_UserOnline_Options::sanitize( 'nonsense' );

		$this->assertSame( 300, $clean['timeout'], 'a non-array submission should give the defaults' );
	}

	/**
	 * A bool either way, and the zero the screen posts alongside the box is
	 * what carries "off" -- absence means "this tab did not post it".
	 *
	 * @return void
	 */
	public function test_the_wp_stats_toggle_is_a_bool_the_hidden_zero_can_turn_off() {
		$this->assertTrue( WP_UserOnline_Options::sanitize( array( 'stats_display' => '1' ) )['stats_display'], 'a checked box should store true' );
		$this->assertFalse( WP_UserOnline_Options::sanitize( array( 'stats_display' => '0' ) )['stats_display'], 'the hidden zero should store false' );
	}

	/**
	 * The regression guard for the bug this plugin is the cautionary tale for.
	 *
	 * The markers used to live in the settings array, so every save had to
	 * rescue them by hand from the stored value. This fails the moment somebody
	 * moves one back in.
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_UserOnline_Options::sanitize(
			array(
				'timeout'    => '120',
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array(
					'plugin' => '9.9.9',
					'db'     => '99',
				),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, $key . ' reached the settings row' );
		}

		WP_UserOnline_Options::update( $clean );

		$this->assertSame( array( 'plugin', 'db' ), array_keys( (array) get_option( WP_UserOnline_Options::VERSION ) ), 'the marker row should be untouched by a save' );
	}

	/**
	 * The other half of the same guard, and the one a stored value cannot fake.
	 *
	 * The sanitiser does read storage -- it has to, because three tabs post
	 * disjoint slices of one row and whatever a submission leaves out has to
	 * survive it. What it must never read is another row: rescuing the markers
	 * out of the settings on every save is the arrangement that shipped the
	 * 3.0.0 bug, and the markers live in a row of their own now precisely so
	 * that this function has no business with them.
	 */
	public function test_the_sanitizer_reads_its_own_row_and_no_other() {
		$source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-wp-useronline-options.php' );
		$start  = strpos( $source, 'public static function sanitize(' );
		$end    = strpos( $source, 'public static function maybe_migrate(', $start );
		$body   = substr( $source, $start, $end - $start );

		preg_match_all( '/get_option\(\s*([^,)]+)/', $body, $matches );

		$this->assertSame(
			array( 'self::OPTION' ),
			array_unique( array_map( 'trim', $matches[1] ) ),
			'the sanitize callback reaches into a row that is not its own'
		);
	}

	public function test_a_save_cannot_disturb_the_marker_row() {
		WP_UserOnline_Options::update( WP_UserOnline_Options::sanitize( array( 'timeout' => '60' ) ) );

		$markers = WP_UserOnline_Options::markers();

		$this->assertSame( WP_USERONLINE_VERSION, $markers['plugin'], 'saving the settings lost the plugin marker' );
		$this->assertSame( WP_USERONLINE_DB_VERSION, $markers['db'], 'saving the settings lost the db marker' );
	}

	public function test_the_markers_are_always_two_keys_however_damaged_the_row_is() {
		update_option( WP_UserOnline_Options::VERSION, 'not an array' );

		$this->assertSame(
			array( 'plugin', 'db' ),
			array_keys( WP_UserOnline_Options::markers() ),
			'a caller should never have to guard either key'
		);
	}

	public function test_an_unwritten_marker_reads_as_an_empty_string() {
		delete_option( WP_UserOnline_Options::VERSION );

		$this->assertSame( '', WP_UserOnline_Options::markers()['plugin'], 'a missing marker should be an empty string' );
	}

	public function test_the_most_ever_online_record_round_trips() {
		WP_UserOnline_Options::update_most( 42, 1767225600 );

		$this->assertSame( 42, WP_UserOnline_Options::most( 'count' ), 'the count did not survive' );
		$this->assertSame( 1767225600, WP_UserOnline_Options::most( 'date' ), 'the date did not survive' );
	}

	public function test_the_record_row_is_not_autoloaded_because_it_is_data_not_a_setting() {
		global $wpdb;

		WP_UserOnline_Options::update_most( 7, time() );

		$autoload = $wpdb->get_var(
			$wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", WP_UserOnline_Options::MOST )
		);

		$this->assertNotContains( $autoload, array( 'yes', 'on', 'auto', 'auto-on' ), 'the record row should not be autoloaded' );
	}

	public function test_the_migration_renames_the_settings_row_and_deletes_the_old_one() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option( 'useronline', array( 'timeout' => 111 ) );

		WP_UserOnline_Options::maybe_migrate();

		$this->assertSame( 111, WP_UserOnline_Options::get( 'timeout' ), 'the old setting was not carried over' );
		$this->assertFalse( get_option( 'useronline' ), 'the old row should be gone' );
	}

	public function test_the_migration_renames_the_record_row_and_deletes_the_old_one() {
		delete_option( WP_UserOnline_Options::MOST );
		update_option(
			'useronline_most',
			array(
				'count' => 99,
				'date'  => 1767225600,
			)
		);

		WP_UserOnline_Options::maybe_migrate();

		$this->assertSame( 99, WP_UserOnline_Options::most( 'count' ), 'the record was lost' );
		$this->assertFalse( get_option( 'useronline_most' ), 'the old record row should be gone' );
	}

	public function test_the_migration_drops_the_reserved_versions_key_from_the_settings() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option(
			'useronline',
			array(
				'timeout'  => 120,
				'versions' => array(
					'sanitize' => '1',
					'db'       => '1',
				),
			)
		);

		WP_UserOnline_Options::maybe_migrate();

		$this->assertArrayNotHasKey( 'versions', get_option( WP_UserOnline_Options::OPTION ), 'the markers stayed in the settings row' );
	}

	public function test_the_migration_re_sanitizes_settings_stored_before_the_rules_tightened() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option(
			'useronline',
			array( 'templates' => array( 'useronline' => '<a href="%PAGE_URL%"><script>alert(1)</script></a>' ) )
		);

		WP_UserOnline_Options::maybe_migrate();

		$stored = get_option( WP_UserOnline_Options::OPTION, false );

		$this->assertStringNotContainsString( '<script', $stored['templates']['useronline'], 'a stored script tag survived the upgrade' );
	}

	public function test_the_migration_can_run_twice_without_changing_anything() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option( 'useronline', array( 'timeout' => 111 ) );

		WP_UserOnline_Options::maybe_migrate();
		$once = get_option( WP_UserOnline_Options::OPTION, false );

		WP_UserOnline_Options::maybe_migrate();

		$this->assertSame( $once, get_option( WP_UserOnline_Options::OPTION, false ), 'the migration is not idempotent' );
	}

	public function test_the_migration_leaves_an_already_migrated_record_alone() {
		WP_UserOnline_Options::update_most( 500, 1767225600 );
		update_option(
			'useronline_most',
			array(
				'count' => 3,
				'date'  => 1,
			)
		);

		WP_UserOnline_Options::maybe_migrate();

		$this->assertSame( 500, WP_UserOnline_Options::most( 'count' ), 'a stale legacy row overwrote the current record' );
	}

	public function test_an_absent_shared_stats_row_means_on_because_a_sibling_deleted_it() {
		delete_option( WP_UserOnline_Options::OPTION );
		delete_option( 'stats_display' );

		WP_UserOnline_Options::maybe_migrate();

		$this->assertTrue( WP_UserOnline_Options::get( 'stats_display' ), 'a missing shared row must not read as a deliberate opt-out' );
	}

	public function test_the_shared_stats_row_is_read_for_this_plugins_own_key() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option(
			'stats_display',
			array(
				'useronline' => 0,
				'polls'      => 1,
			)
		);

		WP_UserOnline_Options::maybe_migrate();

		$this->assertFalse( WP_UserOnline_Options::get( 'stats_display' ), 'a deliberate opt-out was not carried over' );
	}

	public function test_a_shared_stats_row_that_is_on_carries_over_as_on() {
		delete_option( WP_UserOnline_Options::OPTION );
		update_option( 'stats_display', array( 'useronline' => 1 ) );

		WP_UserOnline_Options::maybe_migrate();

		$this->assertTrue( WP_UserOnline_Options::get( 'stats_display' ), 'the toggle should have stayed on' );
	}

	public function test_the_migration_deletes_the_shared_row_as_the_standard_requires() {
		update_option( 'stats_display', array( 'useronline' => 1 ) );

		WP_UserOnline_Options::maybe_migrate();

		$this->assertNull( get_option( 'stats_display', null ), 'the shared row should be deleted once folded in' );
	}

	public function test_the_shared_row_is_never_deleted_on_uninstall() {
		$this->assertNotContains( 'stats_display', WP_UserOnline_Options::all_option_names(), 'uninstalling must not clear a row six siblings still read' );
	}

	/**
	 * The write path creates the row even when the value equals the default.
	 *
	 * Pinned at the door rather than through maybe_migrate(), so the guarantee
	 * belongs to update() rather than to whatever the migration happens to
	 * compute. The migration tests above can only see this while their fixtures
	 * keep producing a value that differs from the defaults; this one cannot stop
	 * seeing it.
	 *
	 * @return void
	 */
	public function test_update_creates_the_row_when_the_value_equals_the_registered_default() {
		delete_option( WP_UserOnline_Options::OPTION );

		WP_UserOnline_Settings::register_settings();

		// The precondition the defect needs: a bare read of an absent row answers
		// with the defaults, so update_option() alone compares equal and declines
		// to write. Core's add_option() fallback sits below that comparison.
		$this->assertSame(
			WP_UserOnline_Options::defaults(),
			get_option( WP_UserOnline_Options::OPTION ),
			'the registered default is what an absent row reads back as'
		);

		WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );

		$this->assertIsArray( get_option( WP_UserOnline_Options::OPTION, false ), 'the row is really there, read raw' );
	}

	/**
	 * The shipped defaults survive the sanitiser unchanged.
	 *
	 * The assertion whose absence would let a typo decide whether the test above
	 * means anything. A sanitiser that alters one character of the defaults -- a
	 * doubled space inside a template that kses collapses is enough -- makes the
	 * written value differ from them, so update_option() finds a difference and
	 * writes the row. The equal-value case then stops being exercised and the test
	 * above passes for a reason unrelated to the code.
	 *
	 * @return void
	 */
	public function test_the_shipped_defaults_survive_sanitisation_unchanged() {
		WP_UserOnline_Settings::register_settings();

		$defaults = WP_UserOnline_Options::defaults();

		$this->assertSame(
			$defaults,
			sanitize_option( WP_UserOnline_Options::OPTION, $defaults ),
			'the registered sanitize callback leaves the defaults alone'
		);
	}
}
