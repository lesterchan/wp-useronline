<?php
/**
 * Shared fixture helpers.
 *
 * The plugin owns the useronline table, which WP_UnitTestCase's transaction
 * does not know about, so it is cleared explicitly. So are the per-request
 * static caches: they are request-scoped by design and correct in production,
 * where each request starts fresh and record() invalidates them, but a PHPUnit
 * run is one long process and they would otherwise leak between tests.
 *
 * @package WP-UserOnline
 */

/**
 * Base class every WP-UserOnline test extends.
 */
abstract class WP_UserOnline_TestCase extends WP_UnitTestCase {

	/**
	 * Creates a user who may actually reach the plugin's screens.
	 *
	 * The screen takes `manage_options` and no grant_super_admin() is wanted:
	 * core's map_meta_cap() does not touch that capability, so a site
	 * administrator holds it on a network exactly as on a single site. This is
	 * the plugin the rule was written for. It used to hardcode `edit_users`,
	 * which multisite *does* remap — to `manage_network_users` — so a site
	 * administrator could not see the visitors to their own site. The fix was
	 * the capability, not a grant; granting here would put that bug back out of
	 * reach of the tests.
	 *
	 * Every administrator the suite creates goes through this, so the network
	 * question is answered in one place rather than at each call site. Tests
	 * that assert the *unprivileged* path set their own subscriber or editor
	 * explicitly and must not be routed through here.
	 *
	 * @return int The new user's ID.
	 */
	protected function create_admin() {
		return self::factory()->user->create( array( 'role' => 'administrator' ) );
	}

	/**
	 * Reset the table, the settings and the static caches before each test.
	 *
	 * @return void
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );

		delete_option( WP_UserOnline_Options::OPTION );
		delete_option( WP_UserOnline_Options::VERSION );
		delete_option( WP_UserOnline_Options::MOST );
		delete_option( 'stats_display' );

		WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );
		WP_UserOnline_Options::update_markers();

		$this->reset_statics();

		/*
		 * $_SERVER is process-wide and no transaction rolls it back (§7.2.1), so
		 * a request header one test sets is still there for every test after it.
		 * That matters more than it looks: get_ip() consults REMOTE_ADDR, the
		 * usual forwarding headers, and whichever header the ip_header setting
		 * names -- which a test may make up. A leftover one makes a later test
		 * pass or fail for a reason that is nowhere in the test.
		 *
		 * Cleared by derivation rather than by name. This used to unset
		 * HTTP_X_FORWARDED_FOR alone, which was every header the suite happened
		 * to use at the time, and the first test to reach for a different one
		 * broke a test three cases further down.
		 */
		foreach ( array_keys( $_SERVER ) as $key ) {
			if ( str_starts_with( (string) $key, 'HTTP_' ) ) {
				unset( $_SERVER[ $key ] );
			}
		}

		$_SERVER['REMOTE_ADDR']     = '203.0.113.1';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (phpunit)';
	}

	/**
	 * Clear anything a test left behind.
	 *
	 * @return void
	 */
	public function tear_down() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();

		delete_option( 'stats_display' );

		parent::tear_down();
	}

	/**
	 * Clear every static cache the plugin keeps.
	 *
	 * @return void
	 */
	protected function reset_statics() {
		$targets = array(
			'WP_UserOnline_Recorder' => array( 'count' => null ),
			'WP_UserOnline_Template' => array(
				'cache'        => array(),
				'needs_script' => false,
			),
		);

		foreach ( $targets as $class => $properties ) {
			foreach ( $properties as $name => $value ) {
				$property = new ReflectionProperty( $class, $name );
				$property->setValue( null, $value );
			}
		}
	}

	/**
	 * Run the uninstaller's option work, repeatably.
	 *
	 * Deliberately not a require of uninstall.php. That file reaches
	 * WP_UserOnline_Install::uninstall_site(), which drops the useronline
	 * table - DDL, which MySQL commits, so it survives the transaction wrapping
	 * the rest of the suite and one test would take the table away from every
	 * test after it. The deletions are performed here instead, over the same
	 * WP_UserOnline_Options::all_option_names() list the uninstaller loops
	 * over, so a row missing from that list still fails the shared uninstall
	 * test - and a shared WP-Stats row wrongly added to it still fails the
	 * shared §13.2 one. That uninstall.php delegates to the installer and loops
	 * the network is asserted against the source in test-install.php.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		foreach ( WP_UserOnline_Options::all_option_names() as $option_name ) {
			delete_option( $option_name );
		}
	}

	/**
	 * Set one plugin setting.
	 *
	 * @param string $key   Top level setting name.
	 * @param mixed  $value Value to store.
	 *
	 * @return void
	 */
	protected function set_option( $key, $value ) {
		$options         = WP_UserOnline_Options::get();
		$options[ $key ] = $value;
		WP_UserOnline_Options::update( $options );
	}

	/**
	 * Insert a row into the useronline table directly.
	 *
	 * @param array $row Column overrides.
	 *
	 * @return void
	 */
	protected function record_row( array $row = array() ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->useronline,
			array_merge(
				array(
					'timestamp'  => current_time( 'mysql' ),
					'user_type'  => 'guest',
					'user_id'    => 0,
					'user_name'  => 'Guest',
					'user_ip'    => '198.51.100.1',
					'user_agent' => 'Mozilla/5.0',
					'page_title' => 'A page',
					'page_url'   => '/a-page/',
					'referral'   => '',
				),
				$row
			)
		);

		$this->reset_statics();
	}

	/**
	 * Count rows currently recorded.
	 *
	 * @return int
	 */
	protected function rows() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->useronline}" );
	}

	/**
	 * Capture whatever a callback echoes.
	 *
	 * @param callable $callback Renderer to invoke.
	 *
	 * @return string
	 */
	protected function capture( $callback ) {
		ob_start();
		call_user_func( $callback );

		return ob_get_clean();
	}
}
