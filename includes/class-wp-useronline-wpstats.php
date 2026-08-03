<?php
/**
 * WP-UserOnline class-wp-useronline-wpstats.php
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contributes this plugin's section to the WP-Stats page.
 *
 * Before 4.0.0 the two plugins met in a bare stats_display option row that
 * seven plugins wrote to at once, and WP-Stats had to know the names of every
 * panel its siblings owned. Now WP-Stats asks -- it fires wp_stats_sections and
 * each plugin answers for itself -- so WP-Stats cannot render a section for a
 * plugin that is not installed, and this class decides whether to appear at all
 * by reading nothing but its own settings.
 *
 * Loaded unconditionally and inert without WP-Stats: nothing fires the filter,
 * so nothing here runs. There is no class_exists() probing between plugins.
 *
 * @since 4.0.0
 */
class WP_UserOnline_WPStats {

	/**
	 * Offer the section to WP-Stats.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_sections', array( __CLASS__, 'register_section' ) );
	}

	/**
	 * Add this plugin's entry to the section list.
	 *
	 * @param array $sections Sections keyed by plugin slug with underscores.
	 *
	 * @return array
	 */
	public static function register_section( $sections ) {
		$sections = (array) $sections;

		if ( ! WP_UserOnline_Options::get( 'stats_display' ) ) {
			// Opted out: contribute nothing, rather than an entry with an empty
			// body that WP-Stats would still draw a heading for.
			return $sections;
		}

		$sections['wp_useronline'] = array(
			'title'    => __( 'Users Online', 'wp-useronline' ),
			'priority' => 10,
			'render'   => array( __CLASS__, 'render' ),
		);

		return $sections;
	}

	/**
	 * Echo the section body.
	 *
	 * Takes no arguments and echoes rather than returns, per the contract:
	 * WP-Stats assembles its page under ob_start(), so anything returned here
	 * would be dropped without a word. The section's own heading is echoed by
	 * WP-Stats before this runs.
	 *
	 * @return void
	 */
	public static function render() {
		$total = get_users_online_count();

		$text = sprintf(
			/* translators: %s: number of users currently online. */
			_n( '<strong>%s</strong> user online now.', '<strong>%s</strong> users online now.', $total, 'wp-useronline' ),
			esc_html( number_format_i18n( $total ) )
		);

		echo '<ul>' . "\n";
		echo '<li>' . wp_kses_post( $text ) . '</li>' . "\n";
		echo '<li>' . wp_kses_post( WP_UserOnline_Template::format_most_users() ) . '</li>' . "\n";
		echo '</ul>' . "\n";
	}
}
