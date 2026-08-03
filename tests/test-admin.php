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
		wp_set_current_user( $this->create_admin() );
	}

	public function test_there_is_one_top_level_menu_rather_than_two_scattered_entries() {
		global $menu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		$this->assertContains( WP_UserOnline_Admin::PAGE, wp_list_pluck( $menu, 2 ), 'there is no top-level WP-UserOnline menu' );
	}

	/**
	 * The plugin has one page, not a submenu per screen.
	 *
	 * Core only creates a submenu of its own once a second entry is added to
	 * add_menu_page()'s menu, so "no submenu at all" is the shape to assert.
	 *
	 * @return void
	 */
	public function test_the_menu_has_one_page_and_no_submenu_entries() {
		global $submenu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		$slugs = isset( $submenu[ WP_UserOnline_Admin::PAGE ] )
			? array_values( wp_list_pluck( $submenu[ WP_UserOnline_Admin::PAGE ], 2 ) )
			: array();

		$this->assertSame( array(), $slugs, 'the report, the settings and the templates are tabs, not submenu entries' );
	}

	public function test_the_report_settings_and_templates_are_three_flat_tabs_in_that_order() {
		$this->login_as_admin();

		$this->assertSame(
			array( 'useronline', 'settings', 'templates' ),
			array_keys( WP_UserOnline_Admin::tabs() ),
			'the tabs are in the wrong order'
		);
		$this->assertSame(
			array( 'Users Online', 'Settings', 'Templates' ),
			array_values( WP_UserOnline_Admin::tabs() ),
			'the tabs must be named for what they are, with no plugin name in them'
		);
	}

	public function test_the_tab_strip_marks_the_active_tab_and_links_the_others() {
		$this->login_as_admin();

		$_GET['tab'] = WP_UserOnline_Admin::TAB_TEMPLATES;

		$html = $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) );

		$this->assertStringContainsString( 'nav-tab-wrapper', $html, 'there is no tab strip' );
		$this->assertMatchesRegularExpression(
			'/tab=templates" class="nav-tab nav-tab-active"/',
			$html,
			'the tab asked for is not the active one'
		);
		$this->assertStringContainsString( 'tab=settings" class="nav-tab"', $html, 'the settings tab is not linked' );
		$this->assertStringContainsString( 'tab=useronline" class="nav-tab"', $html, 'the report tab is not linked' );
	}

	public function test_an_unknown_tab_falls_back_to_the_report() {
		$this->login_as_admin();

		$_GET['tab'] = 'no-such-tab';

		$this->assertSame( WP_UserOnline_Admin::TAB_USERONLINE, WP_UserOnline_Admin::current_tab(), 'an unknown tab was accepted' );
	}

	public function test_nothing_is_added_under_dashboard_or_settings_any_more() {
		global $submenu;

		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		foreach ( array( 'index.php', 'options-general.php' ) as $parent ) {
			$slugs = isset( $submenu[ $parent ] ) ? wp_list_pluck( $submenu[ $parent ], 2 ) : array();

			$this->assertNotContains( WP_UserOnline_Admin::PAGE, $slugs, 'the plugin is still under ' . $parent );
		}
	}

	/**
	 * The hook is recorded, never spelled out.
	 *
	 * A top-level page's hook is toplevel_page_{slug} and a submenu's is
	 * {parent}_page_{slug}, so anything enqueued against a hand-built string
	 * stops loading the moment the menu is reshaped -- which is exactly what
	 * this change to the menu did.
	 *
	 * @return void
	 */
	public function test_the_screen_hook_is_the_one_add_menu_page_handed_back() {
		$this->login_as_admin();

		WP_UserOnline_Admin::add_page();

		$this->assertSame(
			get_plugin_page_hookname( WP_UserOnline_Admin::PAGE, '' ),
			WP_UserOnline_Admin::screen_hook(),
			'the recorded hook is not the hook the screen was registered under'
		);
	}

	/**
	 * The menu is gated by the capability recorded against it.
	 *
	 * Not by its absence from $menu: add_menu_page() does not consult the
	 * current user at all. It records the required capability as element 1 of
	 * the menu item and WordPress enforces it later, in _wp_menu_output() and
	 * user_can_access_admin_page(), when the sidebar is drawn. So the entry is
	 * registered for everybody and the thing worth asserting is that it demands
	 * a capability a subscriber does not have.
	 *
	 * @return void
	 */
	public function test_a_user_without_the_capability_never_gets_the_menu() {
		global $menu, $submenu;

		// Plain globals; no transaction rolls them back between tests.
		$menu    = array();
		$submenu = array();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		WP_UserOnline_Admin::add_page();

		$required = null;

		foreach ( (array) $menu as $item ) {
			if ( isset( $item[2] ) && WP_UserOnline_Admin::PAGE === $item[2] ) {
				$required = $item[1];
			}
		}

		$this->assertSame(
			WP_UserOnline_Admin::capability(),
			$required,
			'the menu must demand the plugin capability'
		);
		$this->assertFalse(
			current_user_can( (string) $required ),
			'a subscriber must not hold the capability the menu demands'
		);
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

	/**
	 * The report and the settings share a page now, so the page's capability
	 * cannot be the only gate.
	 *
	 * The menu carries the report's capability, which is the lower of the two:
	 * widening 'useronline' is how a site hands the read-only listing to a role
	 * it would never trust with the settings form. If the tabs did not check
	 * their own context, doing that would hand them the settings form as well --
	 * a privilege escalation dressed up as a menu change.
	 *
	 * @return void
	 */
	public function test_widening_the_report_does_not_hand_over_the_settings_tab() {
		add_filter(
			'wp_useronline_capability',
			static function ( $capability, $context ) {
				return 'useronline' === $context ? 'edit_posts' : $capability;
			},
			10,
			2
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_USERONLINE;

		$html = $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) );

		$this->assertStringContainsString( 'useronline-details', $html, 'the widened report did not render' );
		$this->assertStringNotContainsString( 'tab=settings', $html, 'the tab strip offered a tab this user may not open' );
		$this->assertStringNotContainsString( 'tab=templates', $html, 'the tab strip offered a tab this user may not open' );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_SETTINGS;

		$this->expectException( 'WPDieException' );

		WP_UserOnline_Admin::render_page();
	}

	public function test_the_templates_tab_is_gated_by_the_settings_capability_too() {
		add_filter(
			'wp_useronline_capability',
			static function ( $capability, $context ) {
				return 'useronline' === $context ? 'edit_posts' : $capability;
			},
			10,
			2
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_TEMPLATES;

		$this->expectException( 'WPDieException' );

		WP_UserOnline_Admin::render_page();
	}

	/**
	 * A site can narrow the report instead of widening it, and the page still
	 * has to open on something.
	 *
	 * @return void
	 */
	public function test_the_page_opens_on_the_first_tab_the_user_may_actually_see() {
		add_filter(
			'wp_useronline_capability',
			static function ( $capability, $context ) {
				return 'useronline' === $context ? 'do_not_allow' : $capability;
			},
			10,
			2
		);

		$this->login_as_admin();

		$this->assertSame( WP_UserOnline_Admin::TAB_SETTINGS, WP_UserOnline_Admin::current_tab(), 'the page opened on a tab this user cannot see' );
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
