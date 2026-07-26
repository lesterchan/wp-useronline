<?php
/**
 * Tests for UserOnline_Recorder.
 *
 * @package WP-UserOnline
 */

/**
 * URL validation, IP handling and what actually lands in the table.
 */
class Test_UserOnline_Recorder extends WP_UnitTestCase {

	/**
	 * Start each test from an empty table and a known request.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		UserOnline_Template::flush_cache();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Read the most recently recorded row.
	 *
	 * @return array|null
	 */
	private function last_row() {
		global $wpdb;

		return $wpdb->get_row( "SELECT * FROM {$wpdb->useronline} ORDER BY timestamp DESC LIMIT 1", ARRAY_A );
	}

	/**
	 * A URL on this site is reduced to its path.
	 */
	public function test_local_url_keeps_path_and_query() {
		$this->assertSame( '/some/page/?x=1', UserOnline_Recorder::local_url( home_url( '/some/page/?x=1' ) ) );
	}

	/**
	 * A bare host becomes the site root.
	 */
	public function test_local_url_bare_host_becomes_root() {
		$this->assertSame( '/', UserOnline_Recorder::local_url( home_url() ) );
	}

	/**
	 * Anything on another host is rejected.
	 */
	public function test_local_url_rejects_foreign_host() {
		$this->assertNull( UserOnline_Recorder::local_url( 'http://evil.example.net/x/' ) );
	}

	/**
	 * The old check was a str_replace, so a foreign URL merely containing the
	 * site URL passed it.
	 */
	public function test_local_url_rejects_embedded_site_url() {
		$this->assertNull( UserOnline_Recorder::local_url( 'http://evil.example.net/?q=' . home_url() ) );
	}

	/**
	 * An empty submission records nothing.
	 */
	public function test_local_url_rejects_empty() {
		$this->assertNull( UserOnline_Recorder::local_url( '' ) );
	}

	/**
	 * The page_url column is a varchar( 255 ).
	 */
	public function test_local_url_is_capped() {
		$long = home_url( '/' . str_repeat( 'a', 600 ) );

		$this->assertSame( 255, strlen( UserOnline_Recorder::local_url( $long ) ) );
	}

	/**
	 * A forged X-Forwarded-For is ignored unless the site opts in.
	 */
	public function test_forwarded_for_ignored_by_default() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

		UserOnline_Recorder::record( '/ip-default', 'probe' );

		$this->assertSame( '203.0.113.9', $this->last_row()['user_ip'] );
	}

	/**
	 * ...and honoured once it does.
	 */
	public function test_forwarded_for_honoured_when_trusted() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
		add_filter( 'useronline_trust_proxy', '__return_true' );

		UserOnline_Recorder::record( '/ip-trusted', 'probe' );

		remove_filter( 'useronline_trust_proxy', '__return_true' );

		$this->assertSame( '1.2.3.4', $this->last_row()['user_ip'] );
	}

	/**
	 * A garbage header falls back rather than storing junk.
	 */
	public function test_garbage_forwarded_for_falls_back() {
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '<script>alert(1)</script>';
		add_filter( 'useronline_trust_proxy', '__return_true' );

		UserOnline_Recorder::record( '/ip-garbage', 'probe' );

		remove_filter( 'useronline_trust_proxy', '__return_true' );

		$this->assertSame( '203.0.113.9', $this->last_row()['user_ip'] );
	}

	/**
	 * No REMOTE_ADDR at all must not fatal.
	 */
	public function test_missing_remote_addr_still_records() {
		unset( $_SERVER['REMOTE_ADDR'] );

		UserOnline_Recorder::record( '/ip-none', 'probe' );

		$row = $this->last_row();

		$this->assertSame( '/ip-none', $row['page_url'] );
		$this->assertSame( '', $row['user_ip'] );
	}

	/**
	 * Quotes and backslashes survive. The old code unslashed the whole row a
	 * second time and ate them.
	 */
	public function test_titles_are_not_mangled() {
		$title = 'It\'s a "quoted" back\\slash title';

		UserOnline_Recorder::record( '/slashes', $title );

		$this->assertSame( $title, $this->last_row()['page_title'] );
	}

	/**
	 * Writing to the table invalidates the render cache.
	 *
	 * The row count cannot be used to detect this: record() deletes the same
	 * visitor's previous row before inserting, so it stays at one either way.
	 * What goes stale is the content -- without the flush the second read still
	 * reports the first page.
	 */
	public function test_record_flushes_the_template_cache() {
		UserOnline_Recorder::record( '/first', 'first' );
		$before = UserOnline_Template::compact_list( 'site', 'list' );
		$this->assertSame( '/first', $before[0]->page_url );

		UserOnline_Recorder::record( '/second', 'second' );
		$after = UserOnline_Template::compact_list( 'site', 'list' );
		$this->assertSame( '/second', $after[0]->page_url );
	}
}
