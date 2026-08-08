<?php
/**
 * The REST API routes.
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the online counts and the visitor heartbeat over the REST API.
 *
 * The namespace is the bare noun `useronline/v1` rather than the plugin slug. A
 * `wp-` prefix is a wordpress.org directory convention for keeping one plugin's
 * download page apart from another's; it says nothing about what the plugin
 * should call the things it registers. Another plugin can claim the same bare
 * noun and WordPress will not detect it; that is the accepted trade.
 *
 * **These routes are added beside `wp_ajax_wp_useronline`, not in place of
 * it.** That action stays registered and stays supported: a theme or a cached
 * script may be calling it, and nobody here can survey them.
 *
 * ## There is no nonce here, and that is deliberate
 *
 * The sibling plugins' voting and rating routes check a per-item nonce, because
 * the AJAX endpoints they were ported from check one. **This endpoint never
 * did, and the reason is written into `WP_UserOnline::ajax()`: a nonce cannot
 * authenticate a logged-out visitor.** Anonymous nonces are derived from one
 * session shared by every such caller, so verifying one proves only that the
 * caller can compute a value every visitor already has.
 *
 * Copying the nonce check across for consistency would therefore add a
 * ceremony that authenticates nobody, while implying to the next reader that
 * this route is protected in a way it is not. What actually holds is narrower
 * and is enforced below: every field is validated, the URL has to resolve to
 * this site, and the write touches nothing but the calling visitor's own row.
 */
class WP_UserOnline_API {

	/**
	 * The REST namespace every route is registered under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'useronline/v1';

	/**
	 * The views the heartbeat can ask for, matching the AJAX endpoint's modes.
	 *
	 * @var array
	 */
	const MODES = array( 'count', 'browsing-site', 'browsing-page', 'details' );

	/**
	 * Register the routes once the REST API is up.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'count',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_count' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'visit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'visit' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'mode'       => array(
						'required'          => true,
						'validate_callback' => array( $this, 'is_mode_valid' ),
					),
					'page_url'   => array(
						'required' => false,
					),
					'page_title' => array(
						'required' => false,
					),
				),
			)
		);
	}

	/**
	 * Whether a mode is one this endpoint renders.
	 *
	 * A fixed list, so an unknown value really is a malformed request and a 400
	 * is the right answer -- unlike a missing poll or post, which is a 404.
	 *
	 * @param mixed $value Value submitted for the mode.
	 * @return bool
	 */
	public function is_mode_valid( $value ) {
		return in_array( $value, self::MODES, true );
	}

	/**
	 * Return the current counts without recording the caller.
	 *
	 * A plain read, so it records nothing: a monitoring script polling this
	 * every minute would otherwise appear in the figures it is reading.
	 *
	 * @return WP_REST_Response
	 */
	public function get_count() {
		return rest_ensure_response(
			array(
				'online'    => WP_UserOnline_Recorder::count(),
				'most'      => (int) WP_UserOnline_Options::most( 'count' ),
				'most_date' => get_most_users_online_date(),
				'html'      => get_users_online(),
			)
		);
	}

	/**
	 * Record the caller as online and return the view they asked for.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function visit( $request ) {
		$mode = $request['mode'];

		// local_url() answers null for anything that is not a URL on this site,
		// which is what stops the table filling with somebody else's pages.
		$page_url = WP_UserOnline_Recorder::local_url( (string) $request['page_url'] );

		if ( null !== $page_url ) {
			$page_title = sanitize_text_field( (string) $request['page_title'] );

			WP_UserOnline_Recorder::record( $page_url, mb_substr( $page_title, 0, 250 ) );
		}

		return rest_ensure_response(
			array(
				'mode'     => $mode,
				'online'   => WP_UserOnline_Recorder::count(),
				'recorded' => null !== $page_url,
				'html'     => $this->render( $mode, $page_url ),
			)
		);
	}

	/**
	 * The markup for one mode.
	 *
	 * Returned as well as the numbers because the templates are the site's to
	 * edit: a client rebuilding the sentence from the count would ignore every
	 * one of them.
	 *
	 * **`details` is not like the other three.** They return bare content for a
	 * container the theme or the widget already wrote; `users_online_page()`
	 * carries `#useronline-details` in what it returns, because
	 * `[page_useronline]` has no theme markup to sit inside, and the
	 * `wp_useronline_page` filter is applied to that whole element. An answer
	 * without the wrapper would be a different thing from what the page first
	 * rendered, so this returns exactly what the AJAX endpoint returns.
	 *
	 * @param string      $mode     View to render.
	 * @param string|null $page_url Page the visitor is on, when it resolved.
	 * @return string
	 */
	private function render( $mode, $page_url ) {
		switch ( $mode ) {
			case 'browsing-site':
				$html = get_users_browsing_site();
				break;
			case 'browsing-page':
				$html = get_users_browsing_page( null === $page_url ? '' : $page_url );
				break;
			case 'details':
				$html = users_online_page();
				break;
			case 'count':
			default:
				$html = get_users_online();
				break;
		}

		/*
		 * Through kses on the way out, the way the AJAX endpoint and the admin
		 * screen already do it. Everything here is escaped where it is built, so
		 * this changes nothing about the plugin's own output -- but
		 * `wp_useronline_page` and `wp_useronline_custom_template` are handed the
		 * raw, visitor-controlled row and are documented as such, and those two
		 * filters were reaching REST clients through a sink with no net under it
		 * while reaching everybody else through one that had.
		 */
		return wp_kses_post( $html );
	}
}
