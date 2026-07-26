<?php
/**
 * Tests for the public AJAX endpoint.
 *
 * @package WP-UserOnline
 */

/**
 * UserOnline::ajax() is reachable by logged-out visitors and checks no nonce,
 * so every input is hostile by assumption. These cover that contract.
 *
 * @group ajax
 */
class Test_UserOnline_Ajax extends WP_Ajax_UnitTestCase {

	use UserOnline_Reset_Statics;

	/**
	 * Start from an empty table, logged out, with a known request.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		$this->reset_useronline_statics();

		$this->logout();

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	}

	/**
	 * Clear request state between tests.
	 */
	public function tear_down() {
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Fire the endpoint and return whatever it echoed.
	 *
	 * The handler always finishes with wp_die(), which the AJAX test case turns
	 * into one of two exceptions depending on whether anything was echoed.
	 *
	 * @param array $post Request body.
	 *
	 * @return string
	 */
	private function do_ajax( array $post ) {
		$_POST           = $post;
		$_POST['action'] = 'useronline';

		$this->_last_response = '';

		try {
			$this->_handleAjax( 'useronline' );
		} catch ( WPAjaxDieContinueException $e ) {
			unset( $e );
		} catch ( WPAjaxDieStopException $e ) {
			unset( $e );
		}

		return $this->_last_response;
	}

	/**
	 * Count rows currently recorded.
	 *
	 * @return int
	 */
	private function rows() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->useronline}" );
	}

	/**
	 * An unrecognised mode must record nothing. It used to fall through the
	 * switch having already written a row on the way in.
	 */
	public function test_invalid_mode_records_nothing() {
		$this->do_ajax(
			array(
				'mode'       => 'garbage',
				'page_url'   => home_url( '/hello/' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * A recognised mode records the visitor.
	 */
	public function test_valid_mode_records_a_row() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/hello/' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 1, $this->rows() );
	}

	/**
	 * A URL on another host is rejected outright.
	 */
	public function test_foreign_host_records_nothing() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => 'http://evil.example.net/x/',
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * The old check was a str_replace, so a foreign URL merely containing the
	 * site URL satisfied it.
	 */
	public function test_embedded_site_url_records_nothing() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => 'http://evil.example.net/?q=' . home_url(),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * Missing parameters must not warn or fatal.
	 */
	public function test_missing_parameters_are_harmless() {
		$this->do_ajax( array( 'mode' => 'count' ) );

		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * No parameters at all is the same.
	 */
	public function test_no_parameters_at_all() {
		$this->do_ajax( array() );

		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * Every advertised mode answers.
	 *
	 * @dataProvider data_modes
	 *
	 * @param string $mode Mode name.
	 */
	public function test_each_mode_responds( $mode ) {
		$response = $this->do_ajax(
			array(
				'mode'       => $mode,
				'page_url'   => home_url( '/hello/' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertNotSame( '', $response, "mode {$mode} produced no output" );
	}

	/**
	 * Modes the endpoint accepts.
	 *
	 * @return array
	 */
	public function data_modes() {
		return array(
			'count'         => array( 'count' ),
			'browsing-site' => array( 'browsing-site' ),
			'browsing-page' => array( 'browsing-page' ),
			'details'       => array( 'details' ),
		);
	}

	/**
	 * The recorded path matches what wp_head would have stored, so the
	 * browsing-page lookup can find it.
	 */
	public function test_recorded_path_keeps_query_string() {
		global $wpdb;

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/some/page/?x=1' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( '/some/page/?x=1', $wpdb->get_var( "SELECT page_url FROM {$wpdb->useronline}" ) );
	}

	/**
	 * Oversized input is capped to the column widths.
	 */
	public function test_oversized_input_is_capped() {
		global $wpdb;

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/' . str_repeat( 'a', 600 ) ),
				'page_title' => str_repeat( 'b', 600 ),
			)
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 255, strlen( $row['page_url'] ) );
		$this->assertSame( 250, strlen( $row['page_title'] ) );
	}

	/**
	 * A title with quotes and backslashes survives the round trip.
	 */
	public function test_title_is_not_mangled() {
		global $wpdb;

		$title = 'It\'s a "quoted" back\\slash title';

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/quoted/' ),
				'page_title' => wp_slash( $title ),
			)
		);

		$this->assertSame( $title, $wpdb->get_var( "SELECT page_title FROM {$wpdb->useronline}" ) );
	}

	/**
	 * The details mode must not leak a wp-admin location to a logged-out caller.
	 */
	public function test_details_hides_admin_locations_from_anonymous_callers() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->useronline,
			array(
				'timestamp'  => current_time( 'mysql' ),
				'user_type'  => 'guest',
				'user_id'    => 0,
				'user_name'  => 'AdminUser',
				'user_ip'    => '198.51.100.2',
				'user_agent' => 'Mozilla/5.0',
				'page_title' => 'SECRET-ADMIN-PAGE',
				'page_url'   => '/wp-admin/options-general.php',
				'referral'   => '',
			)
		);
		$this->reset_useronline_statics();

		$response = $this->do_ajax( array( 'mode' => 'details' ) );

		$this->assertStringNotContainsString( 'SECRET-ADMIN-PAGE', $response );
		$this->assertStringNotContainsString( 'options-general', $response );
	}
}
