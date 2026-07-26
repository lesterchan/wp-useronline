<?php
/**
 * WP-UserOnline class-useronline-wpstats.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds WP-UserOnline figures to the WP-Stats plugin's pages.
 *
 * @since 3.0.0
 */
class UserOnline_WpStats {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_stats_display_defaults', array( $this, 'display_defaults' ) );
		add_filter( 'wp_stats_page_admin_plugins', array( $this, 'admin_stats' ) );
		add_filter( 'wp_stats_page_plugins', array( $this, 'stats' ) );
	}

	/**
	 * Tell WP-Stats about the toggle this plugin owns, and its default.
	 *
	 * Without this WP-Stats only learns the key exists once its checkbox has
	 * been submitted, so the panel would start out off on a fresh install.
	 *
	 * @param array $defaults Registered toggles.
	 *
	 * @return array
	 */
	public function display_defaults( $defaults ) {
		// WP-Stats' own defaults win, so this only ever adds.
		return array_merge( array( 'useronline' => 1 ), (array) $defaults );
	}

	/**
	 * Whether the WP-UserOnline toggle is on.
	 *
	 * @return bool
	 */
	private function is_displayed() {
		if ( function_exists( 'wp_stats_display_enabled' ) ) {
			return wp_stats_display_enabled( 'useronline' );
		}

		// WP-Stats before 3.0.0 kept the toggles in their own option row.
		$stats_display = get_option( 'stats_display' );

		return is_array( $stats_display ) && 1 === (int) ( $stats_display['useronline'] ?? 0 );
	}

	/**
	 * Add the WP-UserOnline checkbox to the WP-Stats options.
	 *
	 * @param string $content Existing options markup.
	 *
	 * @return string
	 */
	public function admin_stats( $content ) {
		// WP-Stats 3.0.0 owns the field name, which changed when it consolidated
		// its option rows.
		if ( function_exists( 'wp_stats_checkbox' ) ) {
			return $content . wp_stats_checkbox( 'useronline', __( 'WP-UserOnline', 'wp-useronline' ) );
		}

		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline"'
			. checked( $this->is_displayed(), true, false ) . ' />&nbsp;&nbsp;'
			. '<label for="wpstats_useronline">' . esc_html__( 'WP-UserOnline', 'wp-useronline' ) . '</label><br />' . "\n";

		return $content;
	}

	/**
	 * Add the WP-UserOnline figures to the WP-Stats page.
	 *
	 * @param string $content Existing stats markup.
	 *
	 * @return string
	 */
	public function stats( $content ) {
		if ( ! $this->is_displayed() ) {
			return $content;
		}

		$total = get_users_online_count();

		$text = sprintf(
			/* translators: %s: number of users currently online. */
			_n( '<strong>%s</strong> user online now.', '<strong>%s</strong> users online now.', $total, 'wp-useronline' ),
			esc_html( number_format_i18n( $total ) )
		);

		$content .= '<p><strong>' . esc_html__( 'WP-UserOnline', 'wp-useronline' ) . '</strong></p>'
			. '<ul>'
			. '<li>' . wp_kses_post( $text ) . '</li>'
			. '<li>' . wp_kses_post( UserOnline_Template::format_most_users() ) . '</li>'
			. '</ul>';

		return $content;
	}
}
