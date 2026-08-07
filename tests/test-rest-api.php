<?php
/**
 * Tests for the `useronline/v1` REST routes.
 *
 * @package WP-UserOnline
 */

/**
 * Two routes: a read that records nobody, and a heartbeat that records the
 * caller.
 *
 * **The absence of a nonce is asserted here, not just relied on.** The sibling
 * plugins' voting and rating routes check one; this one must not, because a
 * nonce cannot authenticate a logged-out visitor -- anonymous nonces come from
 * a session every such caller shares. A test that only exercised the happy path
 * would let somebody "restore consistency" later by adding a nonce check, and
 * the first thing to break would be every logged-out visitor on the site.
 */
class WP_UserOnline_REST_API_Test extends WP_UserOnline_TestCase {

	/**
	 * Boots the REST server the way core's own REST tests do.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tears the REST server back down so it cannot leak into another test.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch a request against the routes under test.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Route below the namespace.
	 * @param array  $params Body or query parameters.
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, '/' . WP_UserOnline_API::REST_NAMESPACE . $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	// --- registration ----------------------------------------------------

	/**
	 * The routes register under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_namespace_is_the_bare_noun() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/useronline/v1', $routes, 'The namespace is useronline/v1.' );
		$this->assertArrayNotHasKey( '/wp-useronline/v1', $routes, 'The plugin slug is not also claimed as a namespace.' );
		$this->assertSame( 'useronline/v1', WP_UserOnline_API::REST_NAMESPACE, 'And the constant agrees with what was registered.' );
	}

	/**
	 * Both routes are registered.
	 *
	 * @return void
	 */
	public function test_every_route_is_registered() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/useronline/v1/count', $routes, 'Reading the count is routed.' );
		$this->assertArrayHasKey( '/useronline/v1/visit', $routes, 'And the heartbeat is routed.' );
	}

	// --- count -----------------------------------------------------------

	/**
	 * Counting reports the recorded visitors and the record.
	 *
	 * @return void
	 */
	public function test_count_reports_who_is_online() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		$this->record_row( array( 'user_ip' => '198.51.100.8' ) );

		$response = $this->request( 'GET', '/count' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'Reading the count succeeds.' );
		$this->assertSame( 2, $data['online'], 'Both visitors are counted.' );
		$this->assertArrayHasKey( 'most', $data, 'The record is reported beside it.' );
		$this->assertNotSame( '', $data['html'], 'And the rendered sentence a theme would show.' );
	}

	/**
	 * Reading the count records nobody.
	 *
	 * @return void
	 */
	public function test_count_does_not_record_the_caller() {
		$this->record_row();

		$before = $this->rows();

		$this->request( 'GET', '/count' );

		$this->assertSame( $before, $this->rows(), 'A poller does not appear in the figures it is reading.' );
	}

	// --- visit -----------------------------------------------------------

	/**
	 * A heartbeat records the caller and answers with the view asked for.
	 *
	 * @return void
	 */
	public function test_a_visit_records_the_caller() {
		$before = $this->rows();

		$response = $this->request(
			'POST',
			'/visit',
			array(
				'mode'       => 'count',
				'page_url'   => home_url( '/hello-world/' ),
				'page_title' => 'Hello world',
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The heartbeat is accepted.' );
		$this->assertTrue( $response->get_data()['recorded'], 'It says it recorded the visitor.' );
		$this->assertSame( $before + 1, $this->rows(), 'And a row appeared.' );
	}

	/**
	 * A URL that is not on this site records nothing.
	 *
	 * @return void
	 */
	public function test_a_visit_to_another_site_records_nothing() {
		$before = $this->rows();

		$response = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'count',
				'page_url' => 'https://example.org/somewhere-else/',
			)
		);

		$this->assertSame( 200, $response->get_status(), 'The request is still answered.' );
		$this->assertFalse( $response->get_data()['recorded'], 'But it says it recorded nothing.' );
		$this->assertSame( $before, $this->rows(), 'And the table did not grow.' );
	}

	/**
	 * A mode the endpoint does not render is refused before anything is written.
	 *
	 * @return void
	 */
	public function test_an_unknown_mode_is_refused_and_records_nothing() {
		$before = $this->rows();

		$response = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'not-a-mode',
				'page_url' => home_url( '/hello-world/' ),
			)
		);

		$this->assertSame( 400, $response->get_status(), 'A mode outside the fixed list is a malformed request.' );
		$this->assertSame( $before, $this->rows(), 'And nothing was recorded on the way in.' );
	}

	/**
	 * Every mode the AJAX endpoint renders is rendered here too.
	 *
	 * @return void
	 */
	public function test_every_mode_answers_with_markup() {
		foreach ( WP_UserOnline_API::MODES as $mode ) {
			$response = $this->request(
				'POST',
				'/visit',
				array(
					'mode'     => $mode,
					'page_url' => home_url( '/hello-world/' ),
				)
			);

			$this->assertSame( 200, $response->get_status(), 'The ' . $mode . ' mode is accepted.' );
			$this->assertNotSame( '', $response->get_data()['html'], 'The ' . $mode . ' mode renders something.' );
		}
	}

	/**
	 * The details mode is the only one carrying its own container.
	 *
	 * The other three return bare content for a container the theme or the
	 * widget already wrote. An answer for details without the wrapper would be
	 * a different thing from what the page first rendered, and the script
	 * replaces the element rather than writing inside it.
	 *
	 * @return void
	 */
	public function test_the_details_mode_carries_its_own_container() {
		$this->record_row();

		$details = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'details',
				'page_url' => home_url( '/hello-world/' ),
			)
		)->get_data()['html'];

		$count = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'count',
				'page_url' => home_url( '/hello-world/' ),
			)
		)->get_data()['html'];

		$this->assertStringContainsString( 'useronline-details', $details, 'The details mode answers with its own element.' );
		$this->assertStringNotContainsString( 'useronline-details', $count, 'The other modes answer with bare content.' );
	}

	// --- the deliberate absence of a nonce -------------------------------

	/**
	 * The heartbeat takes no nonce, and must not start taking one.
	 *
	 * A nonce cannot authenticate a logged-out visitor: anonymous nonces are
	 * derived from one session every such caller shares, so verifying one
	 * proves only that the caller can compute a value everybody already has.
	 * Adding the check "for consistency" with the sibling plugins would break
	 * every logged-out visitor while protecting nobody.
	 *
	 * @return void
	 */
	public function test_the_heartbeat_needs_no_nonce() {
		$before = $this->rows();

		$response = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'count',
				'page_url' => home_url( '/hello-world/' ),
			)
		);

		$this->assertSame( 200, $response->get_status(), 'A request carrying no nonce at all is accepted.' );
		$this->assertSame( $before + 1, $this->rows(), 'And the visitor was recorded.' );
	}

	/**
	 * A wrong nonce is ignored rather than refused, for the same reason.
	 *
	 * @return void
	 */
	public function test_a_bad_nonce_is_ignored_rather_than_refused() {
		$response = $this->request(
			'POST',
			'/visit',
			array(
				'mode'     => 'count',
				'page_url' => home_url( '/hello-world/' ),
				'nonce'    => 'not-a-nonce',
			)
		);

		$this->assertSame( 200, $response->get_status(), 'An unexpected nonce parameter changes nothing.' );
	}

	// --- the AJAX endpoint it sits beside --------------------------------

	/**
	 * The AJAX action stays registered, because sites are still calling it.
	 *
	 * @return void
	 */
	public function test_the_ajax_endpoint_is_still_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_wp_useronline' ), 'The logged-in AJAX action survives the REST routes.' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_wp_useronline' ), 'And so does the logged-out one.' );
	}
}
