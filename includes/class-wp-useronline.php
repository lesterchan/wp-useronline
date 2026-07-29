<?php
/**
 * WP-UserOnline class-wp-useronline.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap: registers the table, hooks and admin screens.
 *
 * @since 3.0.0
 */
class WP_UserOnline {

	/**
	 * Static instance.
	 *
	 * @var WP_UserOnline|null
	 */
	private static $instance;

	/**
	 * Constructor.
	 *
	 * Activation and deactivation hooks are registered here rather than on a
	 * later hook: this runs while the main plugin file is being loaded, which
	 * is where WordPress requires them to be registered.
	 */
	public function __construct() {
		$this->register_table();

		register_activation_hook( WP_USERONLINE_MAIN_FILE, array( 'WP_UserOnline_Install', 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );
	}

	/**
	 * Initializes the plugin object and returns its instance.
	 *
	 * @return WP_UserOnline
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Make $wpdb->useronline available.
	 *
	 * Adding the name to $wpdb->tables is what keeps it correct across
	 * switch_to_blog() on multisite, since WordPress re-prefixes those.
	 *
	 * @return void
	 */
	private function register_table() {
		global $wpdb;

		$wpdb->tables[]   = 'useronline';
		$wpdb->useronline = $wpdb->prefix . 'useronline';
	}

	/**
	 * Register the plugin's hooks.
	 *
	 * @return void
	 */
	public function add_hooks() {
		WP_UserOnline_Install::maybe_upgrade();

		add_action( 'wp_head', array( $this, 'record' ) );
		add_action( 'admin_head', array( $this, 'record' ) );
		add_action( 'wp_footer', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_useronline', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_useronline', array( $this, 'ajax' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		add_shortcode( 'page_useronline', 'users_online_page' );

		if ( WP_UserOnline_Options::get( 'names' ) ) {
			add_filter( 'useronline_display_user', array( $this, 'linked_names' ), 10, 2 );
		}

		if ( is_admin() ) {
			require_once __DIR__ . '/class-wp-useronline-admin.php';
			require_once __DIR__ . '/class-wp-useronline-settings.php';

			new WP_UserOnline_Admin();
			new WP_UserOnline_Settings();
		}

		if ( function_exists( 'stats_page' ) ) {
			require_once __DIR__ . '/class-wp-useronline-wpstats.php';

			new WP_UserOnline_WPStats();
		}
	}

	/**
	 * Record the current visitor.
	 *
	 * @return void
	 */
	public function record() {
		WP_UserOnline_Recorder::record();
	}

	/**
	 * Link a member's name to their author archive.
	 *
	 * @param string $name Escaped display name.
	 * @param object $user Useronline row.
	 *
	 * @return string
	 */
	public function linked_names( $name, $user ) {
		if ( empty( $user->user_id ) ) {
			return $name;
		}

		return '<a href="' . esc_url( get_author_posts_url( $user->user_id ) ) . '">' . $name . '</a>';
	}

	/**
	 * Register the users online widget.
	 *
	 * @return void
	 */
	public function register_widget() {
		require_once __DIR__ . '/class-wp-useronline-widget.php';

		register_widget( 'WP_UserOnline_Widget' );
	}

	/**
	 * Enqueue the refresh script, if anything on this page needs it.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! WP_UserOnline_Template::needs_script() ) {
			return;
		}

		// Shipped unminified and served as-is: the file is under a kilobyte
		// gzipped, and with no build step a separate minified copy would only
		// drift out of sync with this one.
		wp_enqueue_script(
			'wp-useronline',
			plugins_url( 'js/wp-useronline.js', WP_USERONLINE_MAIN_FILE ),
			array(),
			WP_USERONLINE_VERSION,
			true
		);

		wp_localize_script(
			'wp-useronline',
			'useronlineL10n',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'timeout'  => (int) WP_UserOnline_Options::get( 'timeout' ) * 1000,
			)
		);
	}

	/**
	 * Serve the periodic refresh for the on-page counters and lists.
	 *
	 * No nonce is checked here on purpose: the endpoint has to answer
	 * logged-out visitors, whose nonces come from a session shared by every
	 * anonymous caller and so prove nothing. Every input is treated as hostile
	 * instead.
	 *
	 * @return void
	 */
	public function ajax() {
		$modes = array( 'count', 'browsing-site', 'browsing-page', 'details' );

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

		// Validated before anything is written: an unrecognised mode used to
		// fall straight through while still having recorded a row on the way in.
		if ( ! in_array( $mode, $modes, true ) ) {
			wp_die( '', '', array( 'response' => 200 ) );
		}

		$page_url = WP_UserOnline_Recorder::local_url(
			isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : ''
		);

		if ( null !== $page_url ) {
			$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';

			WP_UserOnline_Recorder::record( $page_url, mb_substr( $page_title, 0, 250 ) );
		}

		switch ( $mode ) {
			case 'count':
				users_online();
				break;
			case 'browsing-site':
				users_browsing_site();
				break;
			case 'browsing-page':
				users_browsing_page( (string) $page_url );
				break;
			case 'details':
				echo users_online_page(); // phpcs:ignore WordPress.Security.EscapeOutput
				break;
		}

		wp_die( '', '', array( 'response' => 200 ) );
	}

}
