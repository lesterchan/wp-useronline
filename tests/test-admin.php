<?php
/**
 * Tests for the admin menu and the users online screen.
 *
 * @package WP-UserOnline
 */

/**
 * Menu registration, capability gates and the At a Glance panel.
 */
class WP_UserOnline_Admin_Test extends WP_UserOnline_TestCase {

	/**
	 * Become an administrator.
	 *
	 * @return void
	 */
	private function login_as_admin() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function test_there_is_one_top_level_menu_rather_than_two_scattered_entries() {
		global $menu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		$this->assertContains( WP_UserOnline_Admin::PAGE, wp_list_pluck( $menu, 2 ), 'there is no top-level WP-UserOnline menu' );
	}

	public function test_the_report_comes_first_and_settings_last() {
		global $submenu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		$slugs = wp_list_pluck( $submenu[ WP_UserOnline_Admin::PAGE ], 2 );

		$this->assertSame(
			array( WP_UserOnline_Admin::PAGE, WP_UserOnline_Settings::PAGE ),
			array_values( $slugs ),
			'the report should be the first entry and Settings the last'
		);
	}

	public function test_nothing_is_added_under_dashboard_or_settings_any_more() {
		global $submenu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		foreach ( array( 'index.php', 'options-general.php' ) as $parent ) {
			$slugs = isset( $submenu[ $parent ] ) ? wp_list_pluck( $submenu[ $parent ], 2 ) : array();

			$this->assertNotContains( WP_UserOnline_Admin::PAGE, $slugs, 'the report is still under ' . $parent );
			$this->assertNotContains( WP_UserOnline_Settings::PAGE, $slugs, 'the settings are still under ' . $parent );
		}
	}

	public function test_a_user_without_the_capability_never_gets_the_menu() {
		global $menu;

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		WP_UserOnline_Admin::add_page();

		$this->assertNotContains( WP_UserOnline_Admin::PAGE, wp_list_pluck( (array) $menu, 2 ), 'a subscriber was offered the menu' );
	}

	public function test_both_screens_require_manage_options_by_default() {
		$this->assertSame( 'manage_options', WP_UserOnline_Admin::capability( 'useronline' ), 'the report should require manage_options' );
		$this->assertSame( 'manage_options', WP_UserOnline_Admin::capability( 'settings' ), 'the settings should require manage_options' );
	}

	public function test_the_capability_filter_can_widen_one_screen_without_widening_the_other() {
		add_filter(
			'wp_useronline_capability',
			static function ( $capability, $context ) {
				return 'useronline' === $context ? 'list_users' : $capability;
			},
			10,
			2
		);

		$this->assertSame( 'list_users', WP_UserOnline_Admin::capability( 'useronline' ), 'the report capability was not filtered' );
		$this->assertSame( 'manage_options', WP_UserOnline_Admin::capability( 'settings' ), 'the settings capability should not have moved' );
	}

	public function test_rendering_is_gated_on_the_capability_and_not_merely_the_menu() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->expectException( 'WPDieException' );

		WP_UserOnline_Admin::render_page();
	}

	public function test_a_permitted_user_gets_the_detailed_list() {
		$this->login_as_admin();

		WP_UserOnline_Recorder::record( '/somewhere', 'Somewhere' );

		$html = $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) );

		$this->assertStringContainsString( 'useronline-details', $html, 'the detailed list is missing' );
		$this->assertStringContainsString( '<div class="wrap">', $html, 'the screen is not in a wrap' );
		$this->assertStringContainsString( '<h1>', $html, 'the screen has no heading' );
	}

	public function test_at_a_glance_is_silent_for_a_user_without_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->assertSame( '', $this->capture( array( 'WP_UserOnline_Admin', 'right_now' ) ), 'the panel spoke to a user who may not see it' );
	}

	public function test_at_a_glance_links_to_the_screen_at_its_new_address() {
		$this->login_as_admin();

		WP_UserOnline_Recorder::record( '/somewhere', 'Somewhere' );

		$html = $this->capture( array( 'WP_UserOnline_Admin', 'right_now' ) );

		$this->assertStringContainsString( 'admin.php?page=' . WP_UserOnline_Admin::PAGE, $html, 'the link still points at the old Dashboard address' );
		$this->assertStringContainsString( 'online now', $html, 'the count is missing' );
	}

	public function test_at_a_glance_reports_the_most_ever_figure() {
		$this->login_as_admin();

		WP_UserOnline_Options::update_most( 99, time() );

		$this->assertStringContainsString( '99', $this->capture( array( 'WP_UserOnline_Admin', 'right_now' ) ), 'the record is missing' );
	}

	public function test_nothing_in_the_panel_escapes_as_markup_from_a_visitor_controlled_name() {
		$this->login_as_admin();

		$this->record_row( array( 'user_name' => '<script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script', $this->capture( array( 'WP_UserOnline_Admin', 'right_now' ) ), 'a script tag reached the Dashboard' );
	}
}
