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
 * The WP-UserOnline menu, and the users online screen under it.
 *
 * One top-level menu with two entries: the users online report itself, and
 * Settings last. Until 4.0.0 the two screens lived in different menus entirely
 * -- the report under Dashboard, the settings under Settings -- which is the
 * scattering the house rule exists to stop. The report is the plugin's own
 * surface rather than a settings page, so it is not a submenu of Settings
 * either.
 *
 * This class owns the menu and the screens; WP_UserOnline_Settings owns
 * register_setting(), the sections, the fields and the sanitiser.
 *
 * @since 4.0.0
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
	 * Hook the screens up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'rightnow_end', array( __CLASS__, 'right_now' ) );
	}

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
	 * Add the menu and its two entries.
	 *
	 * @return void
	 */
	public static function add_page() {
		add_menu_page(
			__( 'WP-UserOnline', 'wp-useronline' ),
			__( 'WP-UserOnline', 'wp-useronline' ),
			self::capability( 'useronline' ),
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-groups'
		);

		// add_menu_page() creates a first submenu labelled after the menu, so
		// this one only exists to call it what it is.
		add_submenu_page(
			self::PAGE,
			__( 'Users Online Now', 'wp-useronline' ),
			__( 'Users Online', 'wp-useronline' ),
			self::capability( 'useronline' ),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);

		add_submenu_page(
			self::PAGE,
			__( 'UserOnline Options', 'wp-useronline' ),
			__( 'Settings', 'wp-useronline' ),
			self::capability( 'settings' ),
			WP_UserOnline_Settings::PAGE,
			array( 'WP_UserOnline_Settings', 'render_page' )
		);
	}

	/**
	 * Render the users online screen.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability( 'useronline' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-useronline' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Users Online Now', 'wp-useronline' ) . '</h1>';

		// The page is assembled as a string for the [page_useronline] shortcode's
		// sake, and it carries the wp_useronline_page filter's output, so it goes
		// out through wp_kses_post() rather than being trusted wholesale.
		echo wp_kses_post( users_online_page() );
		echo '</div>';
	}

	/**
	 * Print the users online summary in the At a Glance panel.
	 *
	 * @return void
	 */
	public static function right_now() {
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
			esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ),
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
