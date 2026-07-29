<?php
/**
 * Tests for the public AJAX endpoint.
 *
 * @package WP-UserOnline
 */

/**
 * WP_UserOnline::ajax() is reachable by logged-out visitors and checks no
 * nonce, so every input is hostile by assumption. These cover that contract.
 *
 * The handler is driven directly rather than through WP_Ajax_UnitTestCase, so
 * that every test in the suite shares one fixture base class. wp_die() is
 * routed through the AJAX die handler and replaced with one that throws, which
 * is what makes the unwind catchable.
 */
class WP_UserOnline_Ajax_Test extends WP_UserOnline_TestCase {

	/**
	 * Make the endpoint reachable from a test, logged out.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( 0 );

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter(
			'wp_die_ajax_handler',
			static function () {
				return static function ( $message ) {
					throw new WPDieException( is_scalar( $message ) ? (string) $message : '' );
				};
			}
		);

		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
	}

	/**
	 * Fire the endpoint and return whatever it echoed.
	 *
	 * @param array $post Request body.
	 *
	 * @return string
	 */
	private function do_ajax( array $post ) {
		$_POST           = $post;
		$_POST['action'] = 'wp_useronline';
		$_REQUEST        = $_POST;

		$depth  = ob_get_level();
		$output = '';

		try {
			ob_start();
			WP_UserOnline::get_instance()->ajax();
		} catch ( WPDieException $e ) {
			unset( $e );
		} finally {
			while ( ob_get_level() > $depth ) {
				$output = ob_get_clean() . $output;
			}

			$_POST    = array();
			$_REQUEST = array();
		}

		return $output;
	}

	// --- what it refuses to record --------------------------------------

	/**
	 * An unrecognised mode used to fall through the switch having already
	 * written a row on the way in.
	 */
	public function test_an_unrecognised_mode_records_nothing() {
		$this->do_ajax(
			array(
				'mode'       => 'garbage',
				'page_url'   => home_url( '/hello/' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows(), 'an invalid mode still recorded a row' );
	}

	public function test_a_url_on_another_host_is_rejected_outright() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => 'http://evil.example.net/x/',
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows(), 'a foreign URL was recorded' );
	}

	/**
	 * The old check was a str_replace, so a foreign URL merely containing the
	 * site URL satisfied it.
	 */
	public function test_a_foreign_url_that_merely_contains_the_site_url_is_rejected() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => 'http://evil.example.net/?q=' . home_url(),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 0, $this->rows(), 'an embedded site URL satisfied the host check' );
	}

	public function test_missing_parameters_are_harmless() {
		$this->do_ajax( array( 'mode' => 'count' ) );

		$this->assertSame( 0, $this->rows(), 'a request with no URL recorded something' );
	}

	public function test_no_parameters_at_all_is_the_same() {
		$this->do_ajax( array() );

		$this->assertSame( 0, $this->rows(), 'an empty request recorded something' );
	}

	// --- what it does record --------------------------------------------

	public function test_a_recognised_mode_records_the_visitor() {
		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/hello/' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( 1, $this->rows(), 'a valid request recorded nothing' );
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

		$this->assertNotSame( '', $response, 'mode ' . $mode . ' produced no output' );
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
	public function test_the_recorded_path_keeps_its_query_string() {
		global $wpdb;

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/some/page/?x=1' ),
				'page_title' => 'Hello',
			)
		);

		$this->assertSame( '/some/page/?x=1', $wpdb->get_var( "SELECT page_url FROM {$wpdb->useronline}" ), 'the query string was cut off' );
	}

	public function test_oversized_input_is_capped_to_the_column_widths() {
		global $wpdb;

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/' . str_repeat( 'a', 600 ) ),
				'page_title' => str_repeat( 'b', 600 ),
			)
		);

		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->useronline}", ARRAY_A );

		$this->assertSame( 255, strlen( $row['page_url'] ), 'the URL was not capped to the column width' );
		$this->assertSame( 250, strlen( $row['page_title'] ), 'the title was not capped to the column width' );
	}

	public function test_a_title_with_quotes_and_backslashes_survives_the_round_trip() {
		global $wpdb;

		$title = 'It\'s a "quoted" back\\slash title';

		$this->do_ajax(
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/quoted/' ),
				'page_title' => wp_slash( $title ),
			)
		);

		$this->assertSame( $title, $wpdb->get_var( "SELECT page_title FROM {$wpdb->useronline}" ), 'the title was mangled' );
	}

	// --- what it must not leak ------------------------------------------

	public function test_the_details_mode_hides_admin_locations_from_anonymous_callers() {
		$this->record_row(
			array(
				'user_name'  => 'AdminUser',
				'page_title' => 'SECRET-ADMIN-PAGE',
				'page_url'   => '/wp-admin/options-general.php',
			)
		);

		$response = $this->do_ajax( array( 'mode' => 'details' ) );

		$this->assertStringNotContainsString( 'SECRET-ADMIN-PAGE', $response, 'an admin page title leaked to a logged-out caller' );
		$this->assertStringNotContainsString( 'options-general', $response, 'an admin page URL leaked to a logged-out caller' );
	}
}
