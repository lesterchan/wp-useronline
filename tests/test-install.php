<?php
/**
 * Tests for the table install and schema upgrade path.
 *
 * @package WP-UserOnline
 */

/**
 * This code only runs on activation or a version bump, so it is easy to break
 * without noticing.
 */
class Test_UserOnline_Install extends WP_UnitTestCase {

	use WP_UserOnline_Reset_Statics;

	/**
	 * Clear per-request statics that leak across a PHPUnit process.
	 */
	public function set_up() {
		parent::set_up();

		$this->reset_useronline_statics();
	}

	/**
	 * The table name is registered on $wpdb.
	 */
	public function test_table_is_registered_on_wpdb() {
		global $wpdb;

		$this->assertNotEmpty( $wpdb->useronline );
		$this->assertSame( $wpdb->prefix . 'useronline', $wpdb->useronline );
	}

	/**
	 * Registering it in $wpdb->tables is what keeps it correct across
	 * switch_to_blog() on multisite.
	 */
	public function test_table_is_in_the_blog_table_list() {
		global $wpdb;

		$this->assertContains( 'useronline', $wpdb->tables );
	}

	/**
	 * The table exists and carries every column the recorder writes.
	 */
	public function test_table_has_the_expected_columns() {
		global $wpdb;

		$columns = $wpdb->get_col( "DESC {$wpdb->useronline}", 0 );

		foreach ( array( 'timestamp', 'user_type', 'user_id', 'user_name', 'user_ip', 'user_agent', 'page_title', 'page_url', 'referral' ) as $column ) {
			$this->assertContains( $column, $columns, "missing column {$column}" );
		}
	}

	/**
	 * Activation is idempotent -- it runs on every reactivation.
	 */
	public function test_activation_can_run_twice() {
		global $wpdb;

		$plugin = WP_UserOnline::get_instance();

		$plugin->activate();
		$plugin->activate();

		$this->assertSame( $wpdb->useronline, $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->useronline}'" ) );
	}

	/**
	 * Activation stamps the schema version, so the on-load check stays quiet.
	 */
	public function test_activation_stamps_the_db_version() {
		WP_UserOnline::get_instance()->activate();

		$this->assertSame( WP_USERONLINE_DB_VERSION, WP_UserOnline_Options::get_version( 'db' ) );
	}

	/**
	 * The marker lives inside the main option, not a row of its own.
	 */
	public function test_no_standalone_version_rows() {
		WP_UserOnline::get_instance()->activate();

		$this->assertFalse( get_option( 'useronline_db_version' ) );
		$this->assertFalse( get_option( 'useronline_sanitize_version' ) );
	}

	/**
	 * The plugin registers the hooks it needs to do anything at all.
	 */
	public function test_core_hooks_are_registered() {
		$plugin = WP_UserOnline::get_instance();

		$this->assertNotFalse( has_action( 'wp_head', array( $plugin, 'record' ) ) );
		$this->assertNotFalse( has_action( 'admin_head', array( $plugin, 'record' ) ) );
		$this->assertNotFalse( has_action( 'wp_footer', array( $plugin, 'enqueue_scripts' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_useronline', array( $plugin, 'ajax' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_useronline', array( $plugin, 'ajax' ) ) );
	}

	/**
	 * The refresh script is only enqueued when something on the page needs it.
	 */
	public function test_script_is_not_enqueued_when_unused() {
		$this->reset_useronline_statics();

		WP_UserOnline::get_instance()->enqueue_scripts();

		$this->assertFalse( wp_script_is( 'wp-useronline', 'enqueued' ) );
	}

	/**
	 * ...and is once a list has rendered, with no jQuery dependency.
	 */
	public function test_script_is_enqueued_once_needed() {
		WP_UserOnline_Template::compact_list( 'site' );

		WP_UserOnline::get_instance()->enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wp-useronline', 'enqueued' ) );

		$script = wp_scripts()->registered['wp-useronline'];

		$this->assertSame( array(), $script->deps, 'the script must not depend on jQuery' );
	}
}
