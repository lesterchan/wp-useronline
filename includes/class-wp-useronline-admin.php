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
 * The WP-UserOnline menu, and the one screen underneath it.
 *
 * One top-level menu with one page on it, and three tabs on that page: the
 * users online report, Settings, and Templates. Until 4.0.0 the report and the
 * settings lived in different menus entirely -- the report under Dashboard, the
 * settings under Settings -- which is the scattering the house rule exists to
 * stop. They then spent a while as two submenu entries; they are three flat
 * tabs of one page now, the same shape WP-Ban wears.
 *
 * Flat, deliberately: one strip of three tabs rather than a Settings tab with a
 * tab strip of its own inside it.
 *
 * This class owns the menu, the tab strip and the report; WP_UserOnline_Settings
 * owns register_setting(), the sections, the fields, the two settings tabs'
 * forms and the sanitiser.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Admin {

	/**
	 * Menu slug, and the slug of the plugin's only screen.
	 */
	const PAGE = 'wp-useronline';

	/**
	 * The users online report.
	 */
	const TAB_USERONLINE = 'useronline';

	/**
	 * Everything that is not a template.
	 */
	const TAB_SETTINGS = 'settings';

	/**
	 * The %TOKEN% templates.
	 */
	const TAB_TEMPLATES = 'templates';

	/**
	 * Capability every WP-UserOnline screen requires.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * The admin hook add_menu_page() registered the screen under.
	 *
	 * Recorded rather than spelled out or derived: a top-level page's hook is
	 * toplevel_page_{slug} and a submenu's is {parent}_page_{slug}, so anything
	 * enqueued against a hand-built string stops loading the moment the menu is
	 * reshaped -- silently, because the screen still renders and only its script
	 * goes missing.
	 *
	 * @var string
	 */
	private static $screen_hook = '';

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
	 * The hook the plugin's screen was registered under.
	 *
	 * @return string Empty until add_page() has run.
	 */
	public static function screen_hook() {
		return self::$screen_hook;
	}

	/**
	 * The capability a screen requires, filtered.
	 *
	 * Every capability check in the plugin goes through here, so a site that
	 * wants to hand the read-only users online screen to editors has one place
	 * to say so and cannot open the settings screen by accident at the same
	 * time.
	 *
	 * The 'details' context is not a screen. It gates the two pieces of the
	 * listing that are nobody else's business — the location of a user who is
	 * inside wp-admin, and every visitor's IP address — on the screen and in the
	 * front-end shortcode alike. It used to be a hardcoded `edit_users` check
	 * sitting outside this filter, which put it beyond a site's reach and, under
	 * multisite, beyond a site administrator's: core gates edit_users behind
	 * manage_network_users there, so the administrator of a site could not see
	 * the visitors to their own site. Sites that want the old, stricter rule back
	 * can return 'edit_users' for this context.
	 *
	 * @since 4.0.0
	 *
	 * @param string $context Which tab is asking: 'useronline', 'settings' or
	 *                        'details'.
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
	 * Add the menu, and the single page hanging off it.
	 *
	 * One entry, no submenus: the report, the settings and the templates are
	 * tabs of this page rather than screens of their own.
	 *
	 * The menu carries the *report's* capability, which is the lowest of the
	 * two by construction -- a site widens 'useronline' to hand the read-only
	 * listing to a role it would never trust with the settings form. Each tab
	 * then checks its own capability in render_page(), because a page-level
	 * check on its own would mean widening the report also opened the settings.
	 *
	 * @return void
	 */
	public static function add_page() {
		self::$screen_hook = (string) add_menu_page(
			__( 'Users Online', 'wp-useronline' ),
			__( 'WP-UserOnline', 'wp-useronline' ),
			self::capability( 'useronline' ),
			self::PAGE,
			array( __CLASS__, 'render_page' ),
			'dashicons-groups'
		);
	}

	/**
	 * The tabs of the plugin's screen, in order, as slug => label.
	 *
	 * Named for what they are and nothing else: the heading above them already
	 * says which plugin this is, so "UserOnline Settings" as a tab label would
	 * only repeat it.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			self::TAB_USERONLINE => __( 'Users Online', 'wp-useronline' ),
			self::TAB_SETTINGS   => __( 'Settings', 'wp-useronline' ),
			self::TAB_TEMPLATES  => __( 'Templates', 'wp-useronline' ),
		);
	}

	/**
	 * The capability context a tab belongs to.
	 *
	 * Templates are settings, so both settings tabs ask the same question. Only
	 * the report is separable, which is the whole point of the filter.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	public static function tab_context( $tab ) {
		return self::TAB_USERONLINE === $tab ? 'useronline' : 'settings';
	}

	/**
	 * Whether the current user may open a tab.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return bool
	 */
	public static function can_view_tab( $tab ) {
		return current_user_can( self::capability( self::tab_context( $tab ) ) );
	}

	/**
	 * The tab this request asked for.
	 *
	 * An unknown or absent tab falls back to the first one the current user may
	 * actually open, so a site that has widened only the settings context does
	 * not land its editors on a tab that dies.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tabs = self::tabs();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reads ?tab= to decide which tab to draw. Nothing is written on this request, the value is checked against self::tabs(), and the tab is capability checked either way.
		$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';

		if ( isset( $tabs[ $requested ] ) ) {
			return $requested;
		}

		foreach ( array_keys( $tabs ) as $tab ) {
			if ( self::can_view_tab( $tab ) ) {
				return $tab;
			}
		}

		return self::TAB_USERONLINE;
	}

	/**
	 * The URL of one tab.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	public static function tab_url( $tab ) {
		return add_query_arg(
			array(
				'page' => self::PAGE,
				'tab'  => $tab,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * The heading a tab sits under.
	 *
	 * One <h1> per screen, and a tab is the screen here: the report and the
	 * settings are different things to be looking at, so they say so. The two
	 * settings tabs share a heading because Templates is a part of the settings
	 * rather than a third subject.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	private static function heading( $tab ) {
		return self::TAB_USERONLINE === $tab
			? __( 'Users Online Now', 'wp-useronline' )
			: __( 'UserOnline Settings', 'wp-useronline' );
	}

	/**
	 * Render the plugin's screen: heading, notices, tab strip, then the tab.
	 *
	 * @return void
	 */
	public static function render_page() {
		$tab = self::current_tab();

		if ( ! self::can_view_tab( $tab ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-useronline' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html( self::heading( $tab ) ) . '</h1>';

		/*
		 * Core only calls this from wp-admin/options-head.php, which runs for
		 * its own settings screens. A plugin page is dispatched by admin.php
		 * instead, so without this the save redirect lands back here with no
		 * confirmation at all.
		 *
		 * Above the tab strip rather than inside a tab, so it prints whichever
		 * tab the save came back to.
		 */
		settings_errors();

		self::render_tab_nav( $tab );

		if ( self::TAB_USERONLINE === $tab ) {
			// The page is assembled as a string for the [page_useronline]
			// shortcode's sake, and it carries the wp_useronline_page filter's
			// output, so it goes out through wp_kses_post() rather than being
			// trusted wholesale.
			echo wp_kses_post( users_online_page() );
		} else {
			WP_UserOnline_Settings::render_tab( $tab );
		}

		echo '</div>';
	}

	/**
	 * Print the tab strip.
	 *
	 * Only the tabs the current user may open: a link that dies on arrival is
	 * worse than no link.
	 *
	 * @param string $current Slug of the active tab.
	 *
	 * @return void
	 */
	private static function render_tab_nav( $current ) {
		echo '<nav class="nav-tab-wrapper">';

		foreach ( self::tabs() as $tab => $label ) {
			if ( ! self::can_view_tab( $tab ) ) {
				continue;
			}

			printf(
				'<a href="%1$s" class="%2$s">%3$s</a>',
				esc_url( self::tab_url( $tab ) ),
				esc_attr( $tab === $current ? 'nav-tab nav-tab-active' : 'nav-tab' ),
				esc_html( $label )
			);
		}

		echo '</nav>';
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
