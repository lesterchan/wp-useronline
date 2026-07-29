<?php
/**
 * WP-UserOnline class-wp-useronline-admin.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard screen listing everyone currently online.
 *
 * @since 3.0.0
 */
class WP_UserOnline_Admin {

	/**
	 * Menu slug, and the slug of the users online screen itself.
	 */
	const PAGE = 'wp-useronline';

	/**
	 * Capability every WP-UserOnline screen requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The capability a screen requires, filtered.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants to hand the read-only users online screen to editors has one place
	 * to say so and cannot open the settings screen by accident at the same
	 * time.
	 *
	 * @since 4.0.0
	 *
	 * @param string $context Which screen is asking: 'useronline' or 'settings'.
	 *
	 * @return string
	 */
	public static function capability( $context = 'useronline' ) {
		/**
		 * Filter the capability a WP-UserOnline screen requires.
		 *
		 * @since 4.0.0
		 *
		 * @param string $capability Capability name.
		 * @param string $context    Which screen is asking.
		 */
		return (string) apply_filters( 'wp_useronline_capability', self::CAPABILITY, $context );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'rightnow_end', array( $this, 'right_now' ) );
	}

	/**
	 * Add the Users Online page under the Dashboard menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_dashboard_page(
			__( 'Users Online Now', 'wp-useronline' ),
			__( 'WP-UserOnline', 'wp-useronline' ),
			self::capability( 'useronline' ),
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the Users Online page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::capability( 'useronline' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-useronline' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Users Online Now', 'wp-useronline' ) . '</h1>';
		echo users_online_page(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
	}

	/**
	 * Print the users online summary in the At a Glance panel.
	 *
	 * @return void
	 */
	public function right_now() {
		if ( ! current_user_can( self::capability( 'useronline' ) ) ) {
			return;
		}

		$total = get_users_online_count();

		$text = sprintf(
			/* translators: 1: users online page URL, 2: number of users online. */
			_n(
				'There is <strong><a href="%1$s">%2$s user</a></strong> online now.',
				'There are a total of <strong><a href="%1$s">%2$s users</a></strong> online now.',
				$total,
				'wp-useronline'
			),
			esc_url( admin_url( 'index.php?page=' . self::PAGE ) ),
			esc_html( number_format_i18n( $total ) )
		);

		$browsing = get_users_browsing_site();

		echo '<p>' . wp_kses_post( $text ) . '<br />';

		if ( $browsing ) {
			echo wp_kses_post( $browsing ) . '<br />';
		}

		echo wp_kses_post( WP_UserOnline_Template::format_most_users() ) . '</p>';
	}
}
