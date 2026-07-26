<?php
/**
 * Tests for UserOnline_Template rendering.
 *
 * @package WP-UserOnline
 */

/**
 * Access control and escaping on the users online output.
 */
class Test_UserOnline_Template extends WP_UnitTestCase {

	/**
	 * Start each test from an empty table.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		UserOnline_Template::flush_cache();
	}

	/**
	 * Insert a row directly, bypassing record().
	 *
	 * @param array $args Column overrides.
	 *
	 * @return void
	 */
	private function add_row( array $args ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->useronline,
			array_merge(
				array(
					'timestamp'  => current_time( 'mysql' ),
					'user_type'  => 'guest',
					'user_id'    => 0,
					'user_name'  => 'Somebody',
					'user_ip'    => '198.51.100.1',
					'user_agent' => 'Mozilla/5.0',
					'page_title' => 'A page',
					'page_url'   => '/a-page/',
					'referral'   => '',
				),
				$args
			)
		);

		UserOnline_Template::flush_cache();
	}

	/**
	 * Seed one visible row followed by one in wp-admin.
	 *
	 * @return void
	 */
	private function seed_visible_then_hidden() {
		$now   = current_time( 'mysql' );
		$older = gmdate( 'Y-m-d H:i:s', strtotime( $now ) - 10 );

		$this->add_row(
			array(
				'timestamp'  => $now,
				'user_ip'    => '198.51.100.1',
				'user_name'  => 'VisibleUser',
				'page_title' => 'SECRET-NORMAL-PAGE',
				'page_url'   => '/normal-page/',
				'referral'   => 'http://example.com/ref-normal',
			)
		);
		$this->add_row(
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
	 * A row in wp-admin is hidden from an unprivileged viewer -- and must not
	 * inherit the previous row's details, which is what the loop used to do.
	 */
	public function test_hidden_row_does_not_inherit_previous_details() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( 0 );

		$output = users_online_page();

		$this->assertSame( 1, substr_count( $output, 'SECRET-NORMAL-PAGE' ) );
		$this->assertSame( 1, substr_count( $output, 'ref-normal' ) );
		$this->assertStringNotContainsString( 'SECRET-ADMIN-PAGE', $output );
		$this->assertStringNotContainsString( 'options-general', $output );
		$this->assertStringNotContainsString( 'ref-admin', $output );
	}

	/**
	 * Both users are still listed; only the location is withheld.
	 */
	public function test_hidden_row_user_is_still_listed() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( 0 );

		$output = users_online_page();

		$this->assertStringContainsString( 'VisibleUser', $output );
		$this->assertStringContainsString( 'AdminUser', $output );
	}

	/**
	 * A viewer with edit_users sees everything.
	 */
	public function test_privileged_viewer_sees_admin_rows() {
		$this->seed_visible_then_hidden();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = users_online_page();

		$this->assertStringContainsString( 'SECRET-ADMIN-PAGE', $output );
		$this->assertStringContainsString( 'ref-admin', $output );
	}

	/**
	 * The user agent is escaped once, in the title attribute.
	 */
	public function test_user_agent_escaped_exactly_once() {
		$this->add_row(
			array(
				'user_ip'    => '198.51.100.7',
				'user_agent' => 'Mozilla/5.0 "quoted" <b>bold</b>',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$output = users_online_page();

		$this->assertStringContainsString( '&quot;quoted&quot;', $output );
		$this->assertStringNotContainsString( '&amp;quot;', $output );
	}

	/**
	 * The IP is only shown to viewers allowed to see it.
	 */
	public function test_ip_hidden_from_unprivileged_viewers() {
		$this->add_row( array( 'user_ip' => '198.51.100.7' ) );
		wp_set_current_user( 0 );

		$this->assertStringNotContainsString( '198.51.100.7', users_online_page() );
	}

	/**
	 * A stored template can never smuggle a script through the renderer.
	 */
	public function test_rendered_output_carries_no_script() {
		UserOnline_Options::update(
			UserOnline_Options::sanitize(
				array( 'templates' => array( 'useronline' => '<a href="%PAGE_URL%"><script>alert(1)</script>%USERS%</a>' ) )
			)
		);

		$this->assertStringNotContainsString( '<script', get_users_online() );
	}
}
