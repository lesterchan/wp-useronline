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

	/**
	 * The gate looked at page_url only, and published referral from inside the
	 * same branch without examining it. A user who clicks a front-end link
	 * *from* an admin screen produces a row whose location passes and whose
	 * referrer is the whole admin URL -- WordPress sends
	 * Referrer-Policy: strict-origin-when-cross-origin, and a same-origin
	 * navigation under that policy carries the full URL, `_wpnonce` included.
	 */
	public function test_an_admin_referrer_is_withheld_even_when_the_location_is_public() {
		$this->record_row(
			array(
				'user_ip'    => '198.51.100.7',
				'user_name'  => 'EditorUser',
				'page_title' => 'A public post',
				'page_url'   => '/a-public-post/',
				'referral'   => home_url( '/wp-admin/post.php?post=42&action=edit&_wpnonce=abc123secret' ),
			)
		);

		wp_set_current_user( 0 );

		$output = users_online_page();

		$this->assertStringContainsString( 'a-public-post', $output, 'The public location is still shown.' );
		$this->assertStringNotContainsString( 'abc123secret', $output, 'The nonce in the referrer is not published.' );
		$this->assertStringNotContainsString( 'post.php', $output, 'Nor the admin screen it came from.' );
	}

	public function test_a_privileged_viewer_still_sees_an_admin_referrer() {
		$this->record_row(
			array(
				'user_ip'   => '198.51.100.7',
				'user_name' => 'EditorUser',
				'page_url'  => '/a-public-post/',
				'referral'  => home_url( '/wp-admin/post.php?post=42' ),
			)
		);

		wp_set_current_user( $this->create_admin() );

		$this->assertStringContainsString( 'post.php', users_online_page(), 'Somebody entitled to the detail still gets it.' );
	}

	/**
	 * The old test was `strpos( $url, 'wp-admin' )`, which never matches on a
	 * site that has moved or aliased its admin directory -- so every admin
	 * location was published there.
	 */
	public function test_the_admin_location_test_follows_the_sites_own_admin_path() {
		$this->assertTrue( WP_UserOnline_Template::is_admin_location( '/wp-admin/options-general.php' ), 'The ordinary case.' );
		$this->assertTrue( WP_UserOnline_Template::is_admin_location( home_url( '/wp-admin/post.php?post=1' ) ), 'And the absolute form a referrer arrives in.' );
		$this->assertTrue( WP_UserOnline_Template::is_admin_location( 'https://other.example/wp-login.php' ), 'A sign-in screen anywhere is worth withholding.' );
		$this->assertFalse( WP_UserOnline_Template::is_admin_location( '/a-public-post/' ), 'An ordinary page is not an admin location.' );
		$this->assertFalse( WP_UserOnline_Template::is_admin_location( '/articles/wp-admin-tips/' ), 'And neither is a post that merely talks about one.' );
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
		wp_set_current_user( $this->create_admin() );

		$output = users_online_page();

		$this->assertStringContainsString( 'SECRET-ADMIN-PAGE', $output, 'a privileged viewer was denied the page title' );
		$this->assertStringContainsString( 'ref-admin', $output, 'a privileged viewer was denied the referrer' );
	}

	public function test_the_ip_is_hidden_from_unprivileged_viewers() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		wp_set_current_user( 0 );

		$this->assertStringNotContainsString( '198.51.100.7', users_online_page(), 'an address was shown to a visitor' );
	}

	/**
	 * The lookup has to survive a colon, which is the whole of the IPv6 bug.
	 *
	 * The address is percent-encoded on the way into the URL, so the assertion
	 * is on what esc_url() finally emits rather than on the literal address:
	 * the failure being pinned is a link that cannot resolve the visitor, and
	 * that is decided by the href, not by the text beside it.
	 */
	public function test_an_ipv6_address_gets_a_lookup_link_it_can_resolve() {
		$this->record_row( array( 'user_ip' => '2001:db8::1' ) );
		wp_set_current_user( $this->create_admin() );

		$output = users_online_page();

		$this->assertStringContainsString(
			'href="https://ipinfo.io/' . rawurlencode( '2001:db8::1' ) . '"',
			$output,
			'an IPv6 address did not reach the lookup intact'
		);
		$this->assertStringContainsString( '2001:db8::1', $output, 'the address itself was not shown' );
	}

	public function test_the_lookup_service_can_be_replaced_through_the_filter() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		wp_set_current_user( $this->create_admin() );

		add_filter(
			'wp_useronline_ip_lookup_url',
			static function ( $url, $ip ) {
				return 'https://example.org/lookup/' . rawurlencode( $ip );
			},
			10,
			2
		);

		$output = users_online_page();

		$this->assertStringContainsString( 'href="https://example.org/lookup/198.51.100.7"', $output, 'the filter did not choose the service' );
		$this->assertStringNotContainsString( 'ipinfo.io', $output, 'the default service was linked anyway' );
	}

	/**
	 * Blanking the URL drops the link rather than pointing it at nothing.
	 *
	 * An empty href resolves to the current page, so the obvious shape --
	 * filter the URL, print it -- turns "do not link this" into "link this to
	 * the screen you are already on", which looks like it worked.
	 */
	public function test_an_empty_lookup_url_leaves_the_address_as_plain_text() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		wp_set_current_user( $this->create_admin() );

		add_filter( 'wp_useronline_ip_lookup_url', '__return_empty_string' );

		$output = users_online_page();

		$this->assertStringContainsString( '198.51.100.7', $output, 'the address itself was withheld' );
		$this->assertStringNotContainsString( 'href=""', $output, 'the address was linked to the current page' );
		$this->assertStringNotContainsString( 'ipinfo.io', $output, 'the address was linked anyway' );
	}

	public function test_the_user_agent_is_escaped_exactly_once_in_the_title_attribute() {
		$this->record_row(
			array(
				'user_ip'    => '198.51.100.7',
				'user_agent' => 'Mozilla/5.0 "quoted" <b>bold</b>',
			)
		);
		wp_set_current_user( $this->create_admin() );

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
		wp_set_current_user( $this->create_admin() );

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
