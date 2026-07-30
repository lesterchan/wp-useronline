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

		$_SERVER['REMOTE_ADDR']     = '203.0.113.1';
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (phpunit)';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
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
