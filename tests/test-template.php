<?php
/**
 * Tests for WP_UserOnline_Template rendering.
 *
 * @package WP-UserOnline
 */

/**
 * Access control and escaping on the users online output.
 */
class WP_UserOnline_Template_Test extends WP_UserOnline_TestCase {

	/**
	 * Seed one visible row followed by one in wp-admin.
	 *
	 * @return void
	 */
	private function seed_visible_then_hidden() {
		$now   = current_time( 'mysql' );
		$older = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 10 );

		$this->record_row(
			array(
				'timestamp'  => $now,
				'user_ip'    => '198.51.100.1',
				'user_name'  => 'VisibleUser',
				'page_title' => 'SECRET-NORMAL-PAGE',
				'page_url'   => '/normal-page/',
				'referral'   => 'http://example.com/ref-normal',
			)
		);
		$this->record_row(
			array(
				'timestamp'  => $older,
				'user_ip'    => '198.51.100.2',
				'user_name'  => 'AdminUser',
				'page_title' => 'SECRET-ADMIN-PAGE',
				'page_url'   => '/wp-admin/options-general.php',
				'referral'   => 'http://example.com/ref-admin',
			)
		);
	}

	/**
	 * A hidden row must not inherit the previous row's details, which is what
	 * the loop used to do.
	 */
	public function test_a_row_in_wp_admin_is_hidden_without_inheriting_the_previous_rows_details() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( 0 );

		$output = users_online_page();

		$this->assertSame( 1, substr_count( $output, 'SECRET-NORMAL-PAGE' ), 'the visible page title was repeated' );
		$this->assertSame( 1, substr_count( $output, 'ref-normal' ), 'the visible referrer was repeated' );
		$this->assertStringNotContainsString( 'SECRET-ADMIN-PAGE', $output, 'an admin page title leaked' );
		$this->assertStringNotContainsString( 'options-general', $output, 'an admin page URL leaked' );
		$this->assertStringNotContainsString( 'ref-admin', $output, 'an admin referrer leaked' );
	}

	public function test_both_users_are_still_listed_and_only_the_location_is_withheld() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( 0 );

		$output = users_online_page();

		$this->assertStringContainsString( 'VisibleUser', $output, 'the visible user is missing' );
		$this->assertStringContainsString( 'AdminUser', $output, 'the hidden user should still be counted and named' );
	}

	public function test_a_viewer_with_edit_users_sees_everything() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = users_online_page();

		$this->assertStringContainsString( 'SECRET-ADMIN-PAGE', $output, 'a privileged viewer was denied the page title' );
		$this->assertStringContainsString( 'ref-admin', $output, 'a privileged viewer was denied the referrer' );
	}

	public function test_the_ip_is_hidden_from_unprivileged_viewers() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		wp_set_current_user( 0 );

		$this->assertStringNotContainsString( '198.51.100.7', users_online_page(), 'an address was shown to a visitor' );
	}

	public function test_the_user_agent_is_escaped_exactly_once_in_the_title_attribute() {
		$this->record_row(
			array(
				'user_ip'    => '198.51.100.7',
				'user_agent' => 'Mozilla/5.0 "quoted" <b>bold</b>',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = users_online_page();

		$this->assertStringContainsString( '&quot;quoted&quot;', $output, 'the user agent was not escaped' );
		$this->assertStringNotContainsString( '&amp;quot;', $output, 'the user agent was double escaped' );
	}

	/**
	 * The detail gate is reachable from the filter, not hardcoded.
	 *
	 * It used to be a bare current_user_can( 'edit_users' ) that no site could
	 * reach. If it ever goes back to one, this test still passes on the
	 * administrator half and fails here.
	 */
	public function test_the_detail_gate_can_be_tightened_through_the_capability_filter() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter(
			'wp_useronline_capability',
			static function ( $capability, $context ) {
				return 'details' === $context ? 'do_not_allow' : $capability;
			},
			10,
			2
		);

		$output = users_online_page();

		$this->assertStringNotContainsString( 'SECRET-ADMIN-PAGE', $output, 'the filter did not tighten the detail gate' );
		$this->assertStringContainsString( 'AdminUser', $output, 'tightening the gate should hide the location, not the user' );
	}

	public function test_a_stored_template_can_never_smuggle_a_script_through_the_renderer() {
		WP_UserOnline_Options::update(
			WP_UserOnline_Options::sanitize(
				array( 'templates' => array( 'useronline' => '<a href="%PAGE_URL%"><script>alert(1)</script>%USERS%</a>' ) )
			)
		);

		$this->assertStringNotContainsString( '<script', get_users_online(), 'a script tag reached the front end' );
	}

	public function test_the_counts_add_up_across_the_three_visitor_types() {
		$this->record_row(
			array(
				'user_type' => 'member',
				'user_ip'   => '198.51.100.10',
			)
		);
		$this->record_row(
			array(
				'user_type' => 'guest',
				'user_ip'   => '198.51.100.11',
			)
		);
		$this->record_row(
			array(
				'user_type' => 'bot',
				'user_ip'   => '198.51.100.12',
			)
		);

		$counts = WP_UserOnline_Template::compact_list( 'site', 'counts' );

		$this->assertSame( 1, $counts['member'], 'the member count is wrong' );
		$this->assertSame( 1, $counts['guest'], 'the guest count is wrong' );
		$this->assertSame( 1, $counts['bot'], 'the bot count is wrong' );
		$this->assertSame( 3, $counts['user'], 'the total should be the sum of the three' );
	}

	public function test_the_browsing_page_list_only_counts_rows_on_that_page() {
		$this->record_row(
			array(
				'page_url' => '/here/',
				'user_ip'  => '198.51.100.20',
			)
		);
		$this->record_row(
			array(
				'page_url' => '/elsewhere/',
				'user_ip'  => '198.51.100.21',
			)
		);

		$rows = WP_UserOnline_Template::compact_list( 'page', 'list', '/here/' );

		$this->assertCount( 1, $rows, 'the page list counted a visitor on another page' );
	}

	/**
	 * A widget showing only the count renders its container without going
	 * through compact_list(), and before 3.0.0 that meant the script was never
	 * enqueued and the number never updated.
	 */
	public function test_rendering_a_list_asks_for_the_refresh_script() {
		$this->assertFalse( WP_UserOnline_Template::needs_script(), 'nothing has rendered yet' );

		WP_UserOnline_Template::compact_list( 'site' );

		$this->assertTrue( WP_UserOnline_Template::needs_script(), 'a rendered list did not ask for the script' );
	}

	public function test_an_empty_site_says_so_rather_than_rendering_an_empty_list() {
		$this->assertStringContainsString( 'No one is online now.', users_online_page(), 'an empty site should say so' );
	}
}
