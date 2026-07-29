<?php
/**
 * WP-UserOnline template tags.
 *
 * The public API for themes. These names and their behaviour are stable across
 * the 3.0.0 restructure.
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Display the users online template.
 *
 * @return void
 */
function users_online() {
	echo wp_kses_post( get_users_online() );
}

/**
 * Get the rendered users online template.
 *
 * @return string
 */
function get_users_online() {
	$template = WP_UserOnline_Options::get( 'templates' )['useronline'];

	$template = str_ireplace( '%PAGE_URL%', esc_url( WP_UserOnline_Options::get( 'url' ) ), $template );
	$template = str_ireplace( '%MOSTONLINE_COUNT%', number_format_i18n( get_most_users_online() ), $template );
	$template = str_ireplace( '%MOSTONLINE_DATE%', get_most_users_online_date(), $template );

	return WP_UserOnline_Template::format_count( get_users_online_count(), 'user', $template );
}

/**
 * Display the number of users online.
 *
 * @return void
 */
function users_online_count() {
	echo esc_html( number_format_i18n( get_users_online_count() ) );
}

/**
 * Get the number of users online.
 *
 * @return int
 */
function get_users_online_count() {
	return WP_UserOnline_Recorder::count();
}

/**
 * Display the highest recorded number of users online.
 *
 * @return void
 */
function most_users_online() {
	echo esc_html( number_format_i18n( get_most_users_online() ) );
}

/**
 * Get the highest recorded number of users online.
 *
 * @return int
 */
function get_most_users_online() {
	return (int) WP_UserOnline_Options::most( 'count' );
}

/**
 * Display the date the record was set.
 *
 * @return void
 */
function most_users_online_date() {
	echo esc_html( get_most_users_online_date() );
}

/**
 * Get the date the record was set.
 *
 * @return string
 */
function get_most_users_online_date() {
	return WP_UserOnline_Template::format_date( WP_UserOnline_Options::most( 'date' ) );
}

/**
 * Display who is browsing the site.
 *
 * @return void
 */
function users_browsing_site() {
	echo wp_kses_post( get_users_browsing_site() );
}

/**
 * Get who is browsing the site.
 *
 * @return string
 */
function get_users_browsing_site() {
	return WP_UserOnline_Template::compact_list( 'site' );
}

/**
 * Display who is browsing a page.
 *
 * @param string $page_url Site-relative URL. Defaults to the current request.
 *
 * @return void
 */
function users_browsing_page( $page_url = '' ) {
	echo wp_kses_post( get_users_browsing_page( $page_url ) );
}

/**
 * Get who is browsing a page.
 *
 * @param string $page_url Site-relative URL. Defaults to the current request.
 *
 * @return string
 */
function get_users_browsing_page( $page_url = '' ) {
	return WP_UserOnline_Template::compact_list( 'page', 'html', $page_url );
}

/**
 * Build the full users online page.
 *
 * @return string
 */
function users_online_page() {
	$users = WP_UserOnline_Template::compact_list( 'site', 'list' );

	$buckets = array();
	foreach ( $users as $user ) {
		$buckets[ $user->user_type ][] = $user;
	}

	/**
	 * Filter the users online grouped by type.
	 *
	 * Renamed from useronline_buckets in 4.0.0; the old name is gone.
	 *
	 * @since 4.0.0
	 *
	 * @param array $buckets Users grouped by user_type.
	 */
	$buckets = apply_filters( 'wp_useronline_buckets', $buckets );

	$counts = WP_UserOnline_Template::get_counts( $buckets );

	$nicetexts = array();
	foreach ( array( 'user', 'member', 'guest', 'bot' ) as $user_type ) {
		$nicetexts[ $user_type ] = WP_UserOnline_Template::format_count( $counts[ $user_type ], $user_type );
	}

	$text = vsprintf(
		/* translators: 1: total online, 2: members, 3: guests, 4: bots. */
		_n(
			'There is <strong>%1$s</strong> online now: <strong>%2$s</strong>, <strong>%3$s</strong> and <strong>%4$s</strong>.',
			'There are a total of <strong>%1$s</strong> online now: <strong>%2$s</strong>, <strong>%3$s</strong> and <strong>%4$s</strong>.',
			$counts['user'],
			'wp-useronline'
		),
		$nicetexts
	);

	$output = '<div id="useronline-details">'
		. '<p>' . $text . '</p>'
		. '<p>' . WP_UserOnline_Template::format_most_users() . '</p>'
		. WP_UserOnline_Template::detailed_list( $counts, $buckets, $nicetexts )
		. '</div>';

	/**
	 * Filter the complete users online page markup.
	 *
	 * Renamed from useronline_page in 4.0.0; the old name is gone.
	 *
	 * @since 4.0.0
	 *
	 * @param string $output Escaped markup.
	 */
	return apply_filters( 'wp_useronline_page', $output );
}

/**
 * Check whether a user is currently online.
 *
 * @param int $user_id User ID.
 *
 * @return bool
 */
function is_user_online( $user_id ) {
	global $wpdb;

	return (bool) $wpdb->get_var(
		$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->useronline} WHERE user_id = %d LIMIT 1", $user_id )
	);
}

/**
 * Get the users online list in the requested shape.
 *
 * @param string $output One of 'html', 'list', 'buckets' or 'counts'.
 * @param string $type   Either 'site' or 'page'.
 *
 * @return mixed
 */
function get_useronline_( $output, $type = 'site' ) {
	return WP_UserOnline_Template::compact_list( $type, $output );
}
