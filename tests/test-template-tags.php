<?php
/**
 * Tests for the public template tags and the visitor classification.
 *
 * @package WP-UserOnline
 */

/**
 * These names are the documented API themes call, so they are the contract.
 */
class WP_UserOnline_Template_Tags_Test extends WP_UserOnline_TestCase {

	/**
	 * These tags classify the visitor, so the user agent starts unset.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		unset( $_SERVER['HTTP_USER_AGENT'] );
	}

	/**
	 * Clear cookies between tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		$_COOKIE = array();

		parent::tear_down();
	}

	/**
	 * The count reflects what is recorded.
	 */
	public function test_users_online_count() {
		$this->assertSame( 0, get_users_online_count(), 'The table starts empty.' );

		WP_UserOnline_Recorder::record( '/one', 'one' );

		$this->assertSame( 1, get_users_online_count(), 'One recorded visit is one visitor online.' );
	}

	/**
	 * %PAGE_URL% is substituted with the configured URL, escaped.
	 */
	public function test_get_users_online_substitutes_page_url() {
		WP_UserOnline_Options::update(
			WP_UserOnline_Options::sanitize( array( 'url' => 'https://example.com/who/' ) )
		);

		$this->assertStringContainsString( 'https://example.com/who/', get_users_online(), 'The configured URL is substituted into the template.' );
		$this->assertStringNotContainsString( '%PAGE_URL%', get_users_online(), 'No %PAGE_URL% token survives substitution.' );
	}

	/**
	 * The naming convention switches on the count.
	 */
	public function test_format_count_pluralises() {
		$this->assertSame( '1 Member', WP_UserOnline_Template::format_count( 1, 'member' ), 'One takes the singular naming convention.' );
		$this->assertSame( '3 Members', WP_UserOnline_Template::format_count( 3, 'member' ), 'More than one takes the plural.' );
		$this->assertSame( '0 Members', WP_UserOnline_Template::format_count( 0, 'member' ), 'Zero takes the plural too, not the singular.' );
	}

	/**
	 * Counts are grouped per user type, with a total.
	 */
	public function test_get_counts_totals_every_bucket() {
		$counts = WP_UserOnline_Template::get_counts(
			array(
				'member' => array( 1, 2 ),
				'bot'    => array( 1 ),
			)
		);

		$this->assertSame( 2, $counts['member'], 'Both member rows are counted.' );
		$this->assertSame( 1, $counts['bot'], 'The single bot row is counted.' );
		$this->assertSame( 0, $counts['guest'], 'A type nobody is online under counts zero rather than being absent.' );
		$this->assertSame( 3, $counts['user'], 'The user total is every bucket added together.' );
	}

	/**
	 * The is_user_online() tag reports on the recorded rows.
	 */
	public function test_is_user_online() {
		$user_id = self::factory()->user->create();

		$this->assertFalse( is_user_online( $user_id ), 'A user who has not been recorded is not online.' );

		wp_set_current_user( $user_id );
		WP_UserOnline_Recorder::record( '/member', 'member' );

		$this->assertTrue( is_user_online( $user_id ), 'A user who has just been recorded is online.' );
	}

	/**
	 * A logged-in visitor is recorded as a member under their display name.
	 */
	public function test_logged_in_visitor_is_a_member() {
		global $wpdb;

		$user_id = self::factory()->user->create( array( 'display_name' => 'Alice' ) );
		wp_set_current_user( $user_id );

		WP_UserOnline_Recorder::record( '/member', 'member' );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 'member', $row['user_type'], 'A logged-in visitor is recorded as a member.' );
		$this->assertSame( 'Alice', $row['user_name'], 'The member is recorded under their display name.' );
		$this->assertSame( (string) $user_id, $row['user_id'], 'The member row carries the user ID.' );
	}

	/**
	 * A known crawler is recorded as a bot, named from the bots table.
	 */
	public function test_known_crawler_is_a_bot() {
		global $wpdb;

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

		WP_UserOnline_Recorder::record( '/bot', 'bot' );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 'bot', $row['user_type'], 'A known crawler is recorded as a bot.' );
		$this->assertNotSame( '', $row['user_name'], 'The bot is named from the bots table rather than left blank.' );
	}

	/**
	 * An anonymous visitor is a guest.
	 */
	public function test_anonymous_visitor_is_a_guest() {
		global $wpdb;

		wp_set_current_user( 0 );

		WP_UserOnline_Recorder::record( '/guest', 'guest' );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 'guest', $row['user_type'], 'An anonymous visitor is recorded as a guest.' );
		$this->assertSame( '0', $row['user_id'], 'A guest has no user ID.' );
	}

	/**
	 * A returning commenter is a guest carrying their name.
	 */
	public function test_comment_author_cookie_names_the_guest() {
		global $wpdb;

		wp_set_current_user( 0 );
		$_COOKIE[ 'comment_author_' . COOKIEHASH ] = 'Bob';

		WP_UserOnline_Recorder::record( '/commenter', 'commenter' );

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 'guest', $row['user_type'], 'A returning commenter is still a guest.' );
		$this->assertSame( 'Bob', $row['user_name'], 'The comment author cookie names the guest.' );
	}

	/**
	 * With the names option on, members link to their author archive.
	 */
	public function test_linked_names_filter_wraps_members() {
		$this->set_options( array( 'names' => 1 ) );

		$user_id = self::factory()->user->create( array( 'display_name' => 'Alice' ) );

		$plugin = WP_UserOnline::get_instance();
		$user   = (object) array(
			'user_id'   => $user_id,
			'user_name' => 'Alice',
		);

		$linked = $plugin->linked_names( 'Alice', $user );

		$this->assertStringContainsString( '<a href="', $linked, 'a member was not linked to their archive' );
		$this->assertStringContainsString( 'Alice', $linked, 'the name was lost' );
	}

	/**
	 * With the names option off, the name is returned untouched.
	 *
	 * The filter is registered either way -- the setting is read inside the
	 * callback rather than at plugins_loaded, because reading it that early
	 * merged the translated defaults and tripped core's just-in-time textdomain
	 * warning. So the off case has to be answered here.
	 */
	public function test_linked_names_leaves_members_alone_when_the_option_is_off() {
		$this->set_options( array( 'names' => 0 ) );

		$user_id = self::factory()->user->create( array( 'display_name' => 'Alice' ) );

		$plugin = WP_UserOnline::get_instance();
		$user   = (object) array(
			'user_id'   => $user_id,
			'user_name' => 'Alice',
		);

		$this->assertSame( 'Alice', $plugin->linked_names( 'Alice', $user ), 'the name was linked with the option off' );
	}

	/**
	 * A guest has no author archive, so the name is left alone.
	 */
	public function test_linked_names_leaves_guests_alone() {
		$plugin = WP_UserOnline::get_instance();
		$user   = (object) array(
			'user_id'   => 0,
			'user_name' => 'Guest',
		);

		$this->assertSame( 'Guest', $plugin->linked_names( 'Guest', $user ), 'A guest has no author archive, so the name comes back unchanged.' );
	}

	/**
	 * The most-online sentence carries the recorded figures.
	 */
	public function test_format_most_users_reports_the_record() {
		WP_UserOnline_Options::update_most( 42, time() );

		$this->assertStringContainsString( '42', WP_UserOnline_Template::format_most_users(), 'The sentence carries the recorded figure.' );
		$this->assertSame( 42, get_most_users_online(), 'The tag reports the stored record.' );
	}

	/**
	 * The echoing tags print what their get_ counterparts return.
	 */
	public function test_echo_tags_print_their_values() {
		WP_UserOnline_Recorder::record( '/one', 'one' );

		ob_start();
		users_online_count();
		$this->assertSame( (string) get_users_online_count(), ob_get_clean(), 'users_online_count() prints what get_users_online_count() returns.' );

		ob_start();
		users_online();
		$this->assertSame( get_users_online(), ob_get_clean(), 'users_online() prints what get_users_online() returns.' );
	}

	/**
	 * The shortcode is registered and renders the detailed page.
	 */
	public function test_shortcode_renders_the_page() {
		WP_UserOnline_Recorder::record( '/one', 'one' );

		$this->assertTrue( shortcode_exists( 'page_useronline' ), 'The page_useronline shortcode is registered.' );
		$this->assertStringContainsString( 'useronline-details', do_shortcode( '[page_useronline]' ), 'The shortcode renders the detailed list.' );
	}

	/**
	 * The tag owns the container, and owns exactly one of it.
	 *
	 * Everything downstream is built on this: the shortcode has no theme markup
	 * to sit inside, the AJAX endpoint answers with the whole element, and the
	 * refresh script replaces #useronline-details with what it gets back. A
	 * second wrapper in here would be a duplicate id on the page, and the
	 * script would then carry it forward on every poll.
	 */
	public function test_the_page_tag_returns_exactly_one_container() {
		WP_UserOnline_Recorder::record( '/one', 'one' );

		$output = users_online_page();

		$this->assertSame( 1, substr_count( $output, 'id="useronline-details"' ), 'the page tag returned more than one container' );
		$this->assertStringStartsWith( '<div id="useronline-details">', $output, 'the page tag does not open with its container' );
		$this->assertStringEndsWith( '</div>', $output, 'the page tag does not close its container last' );
	}
}
