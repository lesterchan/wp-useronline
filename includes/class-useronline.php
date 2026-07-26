<?php
/**
 * WP-UserOnline class-useronline.php
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
class UserOnline {

	/**
	 * Static instance.
	 *
	 * @var UserOnline|null
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

		register_activation_hook( WP_USERONLINE_MAIN_FILE, array( $this, 'activate' ) );

		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );
	}

	/**
	 * Initializes the plugin object and returns its instance.
	 *
	 * @return UserOnline
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
		UserOnline_Options::maybe_migrate();
		$this->maybe_upgrade_table();

		add_action( 'wp_head', array( $this, 'record' ) );
		add_action( 'admin_head', array( $this, 'record' ) );
		add_action( 'wp_footer', array( $this, 'enqueue_scripts' ) );

		add_action( 'wp_ajax_useronline', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_useronline', array( $this, 'ajax' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		add_shortcode( 'page_useronline', 'users_online_page' );

		if ( UserOnline_Options::get( 'names' ) ) {
			add_filter( 'useronline_display_user', array( $this, 'linked_names' ), 10, 2 );
		}

		if ( is_admin() ) {
			require_once __DIR__ . '/class-useronline-admin.php';
			require_once __DIR__ . '/class-useronline-settings.php';

			new UserOnline_Admin();
			new UserOnline_Settings();
		}

		if ( function_exists( 'stats_page' ) ) {
			require_once __DIR__ . '/class-useronline-wpstats.php';

			new UserOnline_WpStats();
		}
	}

	/**
	 * Record the current visitor.
	 *
	 * @return void
	 */
	public function record() {
		UserOnline_Recorder::record();
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
		require_once __DIR__ . '/class-useronline-widget.php';

		register_widget( 'UserOnline_Widget' );
	}

	/**
	 * Enqueue the refresh script, if anything on this page needs it.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( ! UserOnline_Template::needs_script() ) {
			return;
		}

		// Shipped unminified and served as-is: the file is under a kilobyte
		// gzipped, and with no build step a separate minified copy would only
		// drift out of sync with this one.
		wp_enqueue_script(
			'wp-useronline',
			plugins_url( 'useronline.js', WP_USERONLINE_MAIN_FILE ),
			array(),
			WP_USERONLINE_VERSION,
			true
		);

		wp_localize_script(
			'wp-useronline',
			'useronlineL10n',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'timeout'  => (int) UserOnline_Options::get( 'timeout' ) * 1000,
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

		$page_url = UserOnline_Recorder::local_url(
			isset( $_POST['page_url'] ) ? wp_unslash( $_POST['page_url'] ) : ''
		);

		if ( null !== $page_url ) {
			$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( wp_unslash( $_POST['page_title'] ) ) : '';

			UserOnline_Recorder::record( $page_url, mb_substr( $page_title, 0, 250 ) );
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

	/**
	 * Create the table on activation.
	 *
	 * @return void
	 */
	public function activate() {
		$this->install_table();
	}

	/**
	 * Run the table install when the stored schema version is behind.
	 *
	 * Activation alone is not enough: the hook does not fire on a plugin
	 * update, so the check runs on load too.
	 *
	 * @return void
	 */
	private function maybe_upgrade_table() {
		if ( UserOnline_Options::get_version( 'db' ) === WP_USERONLINE_DB_VERSION ) {
			return;
		}

		$this->install_table();
	}

	/**
	 * Create or update the useronline table.
	 *
	 * @return void
	 */
	private function install_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->useronline} (
				timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				user_type varchar(20) NOT NULL default 'guest',
				user_id bigint(20) NOT NULL default 0,
				user_name varchar(250) NOT NULL default '',
				user_ip varchar(39) NOT NULL default '',
				user_agent text NOT NULL,
				page_title text NOT NULL,
				page_url varchar(255) NOT NULL default '',
				referral varchar(255) NOT NULL default '',
				UNIQUE KEY useronline_id (timestamp, user_type, user_ip)
			) {$charset_collate};"
		);

		UserOnline_Options::set_version( 'db', WP_USERONLINE_DB_VERSION );
	}
}
