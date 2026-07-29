<?php
/**
 * Tests for the dashboard screens.
 *
 * @package WP-UserOnline
 */

/**
 * Menu registration, capability gates and the At a Glance panel.
 */
class Test_UserOnline_Admin extends WP_UnitTestCase {

	use WP_UserOnline_Reset_Statics;

	/**
	 * Admin instance under test.
	 *
	 * @var WP_UserOnline_Admin
	 */
	private $admin;

	/**
	 * Start from an empty table with the admin class loaded.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		require_once dirname( __DIR__ ) . '/includes/class-wp-useronline-admin.php';

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		$this->reset_useronline_statics();

		$this->admin = new WP_UserOnline_Admin();
	}

	/**
	 * Reset the menu globals between tests.
	 */
	public function tear_down() {
		global $menu, $submenu;

		$menu    = array();
		$submenu = array();

		parent::tear_down();
	}

	/**
	 * Capture the screen output.
	 *
	 * @return string
	 */
	private function render_page() {
		ob_start();
		$this->admin->render_page();
		return ob_get_clean();
	}

	/**
	 * Capture the At a Glance output.
	 *
	 * @return string
	 */
	private function render_right_now() {
		ob_start();
		$this->admin->right_now();
		return ob_get_clean();
	}

	/**
	 * The page hangs off the Dashboard menu for anyone who may list users.
	 */
	public function test_page_is_added_under_the_dashboard() {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->admin->add_page();

		$this->assertArrayHasKey( 'index.php', $submenu );

		$slugs = wp_list_pluck( $submenu['index.php'], 2 );

		$this->assertContains( WP_UserOnline_Admin::PAGE, $slugs );
	}

	/**
	 * A visitor without the capability never gets the menu entry.
	 */
	public function test_page_is_not_added_without_the_capability() {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->admin->add_page();

		$slugs = isset( $submenu['index.php'] ) ? wp_list_pluck( $submenu['index.php'], 2 ) : array();

		$this->assertNotContains( WP_UserOnline_Admin::PAGE, $slugs );
	}

	/**
	 * Rendering is gated on the capability, not merely the menu.
	 */
	public function test_render_page_requires_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( 'WPDieException' );

		$this->admin->render_page();
	}

	/**
	 * A permitted user gets the detailed list.
	 */
	public function test_render_page_outputs_the_list() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		WP_UserOnline_Recorder::record( '/somewhere', 'Somewhere' );

		$html = $this->render_page();

		$this->assertStringContainsString( 'useronline-details', $html );
		$this->assertStringContainsString( '<div class="wrap">', $html );
	}

	/**
	 * At a Glance is for administrators only.
	 */
	public function test_right_now_is_silent_without_manage_options() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( '', $this->render_right_now() );
	}

	/**
	 * An administrator sees the count, linked to the full screen.
	 */
	public function test_right_now_reports_the_count() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		WP_UserOnline_Recorder::record( '/somewhere', 'Somewhere' );

		$html = $this->render_right_now();

		$this->assertStringContainsString( 'index.php?page=' . WP_UserOnline_Admin::PAGE, $html );
		$this->assertStringContainsString( 'online now', $html );
	}

	/**
	 * The most-ever figure is included.
	 */
	public function test_right_now_reports_the_record() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		WP_UserOnline_Options::update_most( 99, time() );

		$this->assertStringContainsString( '99', $this->render_right_now() );
	}

	/**
	 * Nothing in the panel escapes as raw markup from a visitor-controlled name.
	 */
	public function test_right_now_escapes_visitor_names() {
		global $wpdb;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$wpdb->insert(
			$wpdb->useronline,
			array(
				'timestamp'  => current_time( 'mysql' ),
				'user_type'  => 'guest',
				'user_id'    => 0,
				'user_name'  => '<script>alert(1)</script>',
				'user_ip'    => '198.51.100.1',
				'user_agent' => 'Mozilla/5.0',
				'page_title' => 'A page',
				'page_url'   => '/a-page/',
				'referral'   => '',
			)
		);
		$this->reset_useronline_statics();

		$this->assertStringNotContainsString( '<script', $this->render_right_now() );
	}
}
