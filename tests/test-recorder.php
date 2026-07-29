<?php
/**
 * Tests for WP_UserOnline_Recorder.
 *
 * @package WP-UserOnline
 */

/**
 * URL validation, IP handling and what actually lands in the table.
 */
class WP_UserOnline_Recorder_Test extends WP_UserOnline_TestCase {

	/**
	 * Read the most recently recorded row.
	 *
	 * @return array|null
	 */
	private function last_row() {
		global $wpdb;

		return $wpdb->get_row( "SELECT * FROM {$wpdb->useronline} ORDER BY timestamp DESC LIMIT 1", ARRAY_A );
	}

	// --- the client-supplied URL ----------------------------------------

	public function test_a_url_on_this_site_is_reduced_to_its_path_and_query() {
		$this->assertSame( '/some/page/?x=1', WP_UserOnline_Recorder::local_url( home_url( '/some/page/?x=1' ) ), 'the path or query was lost' );
	}

	public function test_a_bare_host_becomes_the_site_root() {
		$this->assertSame( '/', WP_UserOnline_Recorder::local_url( home_url() ), 'a bare host should reduce to /' );
	}

	public function test_anything_on_another_host_is_rejected() {
		$this->assertNull( WP_UserOnline_Recorder::local_url( 'http://evil.example.net/x/' ), 'a foreign host was accepted' );
	}

	/**
	 * The old check was a str_replace, so a foreign URL merely containing the
	 * site URL passed it.
	 */
	public function test_a_foreign_url_that_merely_contains_the_site_url_is_rejected() {
		$this->assertNull( WP_UserOnline_Recorder::local_url( 'http://evil.example.net/?q=' . home_url() ), 'an embedded site URL satisfied the host check' );
	}

	public function test_an_empty_submission_records_nothing() {
		$this->assertNull( WP_UserOnline_Recorder::local_url( '' ), 'an empty URL should be rejected' );
	}

	public function test_the_path_is_capped_to_the_column_width() {
		$long = home_url( '/' . str_repeat( 'a', 600 ) );

		$this->assertSame( 255, strlen( WP_UserOnline_Recorder::local_url( $long ) ), 'page_url is a varchar(255)' );
	}

	// --- the visitor's address ------------------------------------------

	public function test_a_forged_forwarded_for_header_is_ignored_unless_the_site_opts_in() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

		WP_UserOnline_Recorder::record( '/ip-default', 'probe' );

		$this->assertSame( '203.0.113.1', $this->last_row()['user_ip'], 'an unverified X-Forwarded-For was trusted' );
	}

	public function test_the_forwarded_for_header_is_honoured_once_the_site_does_opt_in() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
		add_filter( 'wp_useronline_trust_proxy', '__return_true' );

		WP_UserOnline_Recorder::record( '/ip-trusted', 'probe' );

		remove_filter( 'wp_useronline_trust_proxy', '__return_true' );

		$this->assertSame( '1.2.3.4', $this->last_row()['user_ip'], 'the trusted proxy header was not used' );
	}

	public function test_a_garbage_forwarded_for_header_falls_back_rather_than_storing_junk() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '<script>alert(1)</script>';
		add_filter( 'wp_useronline_trust_proxy', '__return_true' );

		WP_UserOnline_Recorder::record( '/ip-garbage', 'probe' );

		remove_filter( 'wp_useronline_trust_proxy', '__return_true' );

		$this->assertSame( '203.0.113.1', $this->last_row()['user_ip'], 'an invalid address was stored' );
	}

	public function test_no_remote_addr_at_all_still_records_without_fatalling() {
		unset( $_SERVER['REMOTE_ADDR'] );

		WP_UserOnline_Recorder::record( '/ip-none', 'probe' );

		$row = $this->last_row();

		$this->assertSame( '/ip-none', $row['page_url'], 'nothing was recorded' );
		$this->assertSame( '', $row['user_ip'], 'an address was invented' );
	}

	// --- what lands in the table ----------------------------------------

	/**
	 * The old code unslashed the whole row a second time and ate them.
	 */
	public function test_quotes_and_backslashes_in_a_title_survive() {
		$title = 'It\'s a "quoted" back\\slash title';

		WP_UserOnline_Recorder::record( '/slashes', $title );

		$this->assertSame( $title, $this->last_row()['page_title'], 'the title was mangled on the way in' );
	}

	/**
	 * The row count cannot be used to detect this: record() deletes the same
	 * visitor's previous row before inserting, so it stays at one either way.
	 * What goes stale is the content -- without the flush the second read still
	 * reports the first page.
	 */
	public function test_recording_invalidates_the_render_cache() {
		WP_UserOnline_Recorder::record( '/first', 'first' );
		$before = WP_UserOnline_Template::compact_list( 'site', 'list' );
		$this->assertSame( '/first', $before[0]->page_url, 'the first page was not recorded' );

		WP_UserOnline_Recorder::record( '/second', 'second' );
		$after = WP_UserOnline_Template::compact_list( 'site', 'list' );
		$this->assertSame( '/second', $after[0]->page_url, 'the cache still reports the first page' );
	}

	public function test_recording_updates_the_most_ever_online_record() {
		WP_UserOnline_Options::update_most( 0, 1 );

		WP_UserOnline_Recorder::record( '/somewhere', 'Somewhere' );

		$this->assertSame( 1, WP_UserOnline_Options::most( 'count' ), 'the record was not raised' );
	}

	public function test_a_known_bot_user_agent_is_recorded_as_a_bot() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; bingbot/2.0)';

		WP_UserOnline_Recorder::record( '/crawled', 'Crawled' );

		$row = $this->last_row();

		$this->assertSame( 'bot', $row['user_type'], 'a known crawler was not identified as a bot' );
		$this->assertSame( 'Bing', $row['user_name'], 'the bot was not named' );
	}

	public function test_the_bot_list_can_be_extended_through_the_renamed_filter() {
		add_filter(
			'wp_useronline_bots',
			static function ( $bots ) {
				$bots['My Crawler'] = 'mycrawler';

				return $bots;
			}
		);

		$_SERVER['HTTP_USER_AGENT'] = 'MyCrawler/1.0';

		WP_UserOnline_Recorder::record( '/crawled', 'Crawled' );

		$this->assertSame( 'My Crawler', $this->last_row()['user_name'], 'the filter did not reach the bot list' );
	}

	public function test_a_logged_in_visitor_is_recorded_as_a_member() {
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Wanda' ) ) );

		WP_UserOnline_Recorder::record( '/member', 'Member' );

		$row = $this->last_row();

		$this->assertSame( 'member', $row['user_type'], 'a logged in visitor was not a member' );
		$this->assertSame( 'Wanda', $row['user_name'], 'the display name was not recorded' );
	}

	public function test_counting_is_cached_for_the_request_and_reset_by_a_write() {
		WP_UserOnline_Recorder::record( '/one', 'One' );

		$this->assertSame( 1, WP_UserOnline_Recorder::count(), 'the count is wrong after one visitor' );

		$this->record_row( array( 'user_ip' => '198.51.100.55' ) );

		$this->assertSame( 2, WP_UserOnline_Recorder::count(), 'the count did not pick up the second row' );
	}
}
