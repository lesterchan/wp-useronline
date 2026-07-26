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
		add_filter( 'wp_stats_page_admin_plugins', array( $this, 'admin_stats' ) );
		add_filter( 'wp_stats_page_plugins', array( $this, 'stats' ) );
	}

	/**
	 * Add the WP-UserOnline checkbox to the WP-Stats options.
	 *
	 * @param string $content Existing options markup.
	 *
	 * @return string
	 */
	public function admin_stats( $content ) {
		$stats_display = get_option( 'stats_display' );
		$checked       = isset( $stats_display['useronline'] ) ? $stats_display['useronline'] : 0;

		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline"'
			. checked( $checked, 1, false ) . ' />&nbsp;&nbsp;'
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
		$stats_display = get_option( 'stats_display' );

		if ( 1 !== (int) ( isset( $stats_display['useronline'] ) ? $stats_display['useronline'] : 0 ) ) {
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
