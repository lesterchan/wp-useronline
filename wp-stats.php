<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UserOnline_WpStats {

	public static function init() {
		add_filter( 'wp_stats_page_admin_plugins', array( __CLASS__, 'page_admin_general_stats' ) );
		add_filter( 'wp_stats_page_plugins', array( __CLASS__, 'page_general_stats' ) );
	}

	// Add WP-UserOnline General Stats To WP-Stats Page Options
	public function page_admin_general_stats( $content ) {
		$stats_display = get_option( 'stats_display' );

		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline"' . checked( $stats_display['useronline'], 1, false ) . '/>&nbsp;&nbsp;<label for="wpstats_useronline">'.__( 'WP-UserOnline', 'wp-useronline' ).'</label><br />'."\n";

		return $content;
	}

	// Add WP-UserOnline General Stats To WP-Stats Page
	public function page_general_stats( $content ) {
		$stats_display = get_option( 'stats_display' );

		$str = _n(
			'<strong>%s</strong> user online now.',
			'<strong>%s</strong> users online now.',
			get_users_online_count(), 'wp-useronline'
		);

		if ( $stats_display['useronline'] === 1 )
			$content .=
			 html( 'p', html( 'strong', __( 'WP-UserOnline', 'wp-useronline' ) ) )
			.html( 'ul',
				 html( 'li', sprintf( $str, number_format_i18n( get_users_online_count() ) ) )
				.html( 'li', UserOnline_Template::format_most_users() )
			);

		return $content;
	}
}
UserOnline_WpStats::init();

