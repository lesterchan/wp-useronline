<?php
/**
 * WP-UserOnline class-wp-useronline.php
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap: registers the table, hooks and admin screens.
 *
 * @since 3.0.0
 */
class WP_UserOnline {

	/**
	 * Nonce action for the refresh endpoint.
	 *
	 * Checked only for a signed-in caller; see ajax().
	 *
	 * @since 4.0.0
	 */
	const AJAX_NONCE = 'wp_useronline_refresh';

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

		add_action( 'wp_ajax_wp_useronline', array( $this, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_wp_useronline', array( $this, 'ajax' ) );

		add_action( 'widgets_init', array( $this, 'register_widget' ) );

		add_shortcode( 'page_useronline', 'users_online_page' );

		/*
		 * The block is a sibling of the shortcode above, not a replacement for
		 * it and not a caller of it: both end up at users_online_page(). The
		 * shortcode is not going anywhere -- it sits in published pages this
		 * plugin cannot survey -- so the two are registered side by side, which
		 * is why they are written side by side here.
		 */
		WP_UserOnline_Blocks::init();

		new WP_UserOnline_API();

		self::register_command();

		/*
		 * Registered unconditionally, with the setting consulted inside the
		 * callback instead.
		 *
		 * add_hooks() runs on plugins_loaded, which is before after_setup_theme,
		 * and reading a setting here meant merging the defaults -- which carry
		 * __() strings for the naming and template fields. Core's
		 * _load_textdomain_just_in_time() treats any translation requested before
		 * after_setup_theme as too early and raises _doing_it_wrong, so on any
		 * site with a language pack installed for this plugin every page load
		 * carried that notice.
		 *
		 * Moving add_hooks() to init would not do: wp_widgets_init() runs on init
		 * at priority 1, so widgets_init would already have fired and the widget
		 * would never register.
		 */
		add_filter( 'wp_useronline_display_user', array( $this, 'linked_names' ), 10, 2 );

		// Inert without WP-Stats -- nothing fires wp_stats_sections, so nothing
		// in it runs. No class_exists() or function_exists() probing between
		// plugins: whether a section appears is this plugin's own setting to
		// answer, and whether anyone asks is WP-Stats' business.
		WP_UserOnline_WPStats::init();

		if ( is_admin() ) {
			WP_UserOnline_Admin::init();
			WP_UserOnline_Settings::init();
		}
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * The class file is required here rather than at plugin load because it
	 * extends WP_CLI_Command, which only exists when WP-CLI is the one running
	 * WordPress. Requiring it unconditionally is a fatal error on every web
	 * request.
	 *
	 * @return void
	 */
	public static function register_command() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once WP_USERONLINE_DIR . 'includes/class-wp-useronline-command.php';

		WP_CLI::add_command( 'useronline', 'WP_UserOnline_Command' );
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
		if ( ! WP_UserOnline_Options::get( 'names' ) || empty( $user->user_id ) ) {
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
			WP_USERONLINE_URL . 'js/wp-useronline.js',
			array(),
			WP_USERONLINE_VERSION,
			true
		);

		wp_localize_script(
			'wp-useronline',
			'wpUserOnlineL10n',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'timeout' => (int) WP_UserOnline_Options::get( 'timeout' ) * 1000,

				/*
				 * Only meaningful for a signed-in visitor, and only checked for
				 * one. For everybody else this is the shared anonymous token,
				 * which the endpoint ignores -- it is sent regardless so the
				 * script has one code path and a page cached for logged-out
				 * readers never carries a token that means anything.
				 */
				'nonce'   => is_user_logged_in() ? wp_create_nonce( self::AJAX_NONCE ) : '',
			)
		);
	}

	/**
	 * Serve the periodic refresh for the on-page counters and lists.
	 *
	 * No nonce is checked for a logged-out visitor, on purpose: their nonces come
	 * from a session shared by every anonymous caller, so verifying one proves
	 * nothing. Every input is treated as hostile instead.
	 *
	 * A *logged-in* caller is a different question, and it was not being asked.
	 * admin-ajax authenticates by cookie alone, so a cross-site POST ran this as
	 * whoever was signed in: their real row was deleted and replaced with the
	 * attacker's page and title, which the listing then published under their
	 * name. Their nonce is session-bound and does prove something, so it is
	 * checked -- and only for them, which leaves the anonymous path exactly as
	 * it was.
	 *
	 * @return void
	 */
	public function ajax() {
		if ( is_user_logged_in() && ! check_ajax_referer( self::AJAX_NONCE, '_ajax_nonce', false ) ) {
			wp_die( '', '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- A nonce cannot authenticate a logged-out visitor: anonymous nonces come from one session shared by every such caller, so verifying one proves nothing. Each field below is validated instead, and the endpoint changes nothing but this visitor's own row. A logged-in caller is checked above.
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
		// phpcs:enable WordPress.Security.NonceVerification.Missing

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
				/*
				 * The only mode that answers with its own container. The three
				 * above return bare content for a container the theme or the
				 * widget already wrote, but users_online_page() carries
				 * #useronline-details in what it returns -- [page_useronline]
				 * has no theme markup to sit inside -- and the wp_useronline_page
				 * filter is applied to the whole element, so an answer without
				 * the wrapper would be a different thing from what the page
				 * first rendered. The script therefore replaces the element
				 * with this rather than writing it inside; see WRAPPED_MODES in
				 * js/wp-useronline.js. Exactly one wrapper in this response is
				 * the half of that contract this file owns.
				 */
				echo wp_kses_post( users_online_page() );
				break;
		}

		wp_die( '', '', array( 'response' => 200 ) );
	}
}
