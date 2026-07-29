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

	// --- the table ------------------------------------------------------

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
	}

	// --- the markers ----------------------------------------------------

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

	// --- the hooks ------------------------------------------------------

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

	// --- the script -----------------------------------------------------

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
}
