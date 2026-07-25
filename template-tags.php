<?php
/**
 * Template tags and the rendering helpers behind them.
 *
 * @package WP-UserOnline
 */


// Function: Display UserOnline.
/**
 * Display the users online template.
 *
 * @return void
 */
function users_online() {
	echo get_users_online();
}

/**
 * Get the rendered users online template.
 *
 * @return string
 */
function get_users_online() {
	$template = UserOnline_Core::$options->templates['useronline'];
	$template = str_ireplace( '%PAGE_URL%', UserOnline_Core::$options->url, $template );
	$template = str_ireplace( '%MOSTONLINE_COUNT%', get_most_users_online(), $template );
	$template = str_ireplace( '%MOSTONLINE_DATE%', get_most_users_online_date(), $template );

	return UserOnline_Template::format_count( get_users_online_count(), 'user', $template );
}

// Function: Display UserOnline Count.
/**
 * Display the number of users online.
 *
 * @return void
 */
function users_online_count() {
	echo number_format_i18n( get_useronline_count() );
}

/**
 * Get the number of users online.
 *
 * @return int
 */
function get_users_online_count() {
	return UserOnline_Core::get_user_online_count();
}

// Function: Display Max UserOnline.
/**
 * Display the highest recorded number of users online.
 *
 * @return void
 */
function most_users_online() {
	echo number_format_i18n( get_most_users_online() );
}

/**
 * Get the highest recorded number of users online.
 *
 * @return int
 */
function get_most_users_online() {
	return intval( UserOnline_Core::$most->count );
}

// Function: Display Max UserOnline Date.
/**
 * Display the date the record was set.
 *
 * @return void
 */
function most_users_online_date() {
	echo get_most_users_online_date();
}

/**
 * Get the date the record was set.
 *
 * @return string
 */
function get_most_users_online_date() {
	return UserOnline_Template::format_date( UserOnline_Core::$most->date );
}

// Function: Display Users Browsing The Site.
/**
 * Display who is browsing the site.
 *
 * @return void
 */
function users_browsing_site() {
	echo get_users_browsing_site();
}

/**
 * Get who is browsing the site.
 *
 * @return string
 */
function get_users_browsing_site() {
	return UserOnline_Template::compact_list( 'site' );
}

// Function: Display Users Browsing The ( Current ) Page.
/**
 * Display who is browsing a page.
 *
 * @param string $page_url Site-relative URL. Defaults to the current request.
 *
 * @return void
 */
function users_browsing_page( $page_url = '' ) {
	echo get_users_browsing_page( $page_url );
}

/**
 * Get who is browsing a page.
 *
 * @param string $page_url Site-relative URL. Defaults to the current request.
 *
 * @return string
 */
function get_users_browsing_page( $page_url = '' ) {
	return UserOnline_Template::compact_list( 'page', 'html', $page_url );
}

// Function: UserOnline Page.
/**
 * Build the full users online page.
 *
 * @return string
 */
function users_online_page() {
	global $wpdb;

	$usersonline = $wpdb->get_results( "SELECT * FROM $wpdb->useronline ORDER BY timestamp DESC" );

	$user_buckets = array();
	foreach ( $usersonline as $useronline ) {
		$user_buckets[ $useronline->user_type ][] = $useronline;
	}

	$user_buckets = apply_filters( 'useronline_buckets', $user_buckets );

	$counts = UserOnline_Template::get_counts( $user_buckets );

	$nicetexts = array();
	foreach ( array( 'user', 'member', 'guest', 'bot' ) as $user_type ) {
		$nicetexts[ $user_type ] = UserOnline_Template::format_count( $counts[ $user_type ], $user_type );
	}

	$text = _n(
		'There is <strong>%1$s</strong> online now: <strong>%2$s</strong>, <strong>%3$s</strong> and <strong>%4$s</strong>.',
		'There are a total of <strong>%1$s</strong> online now: <strong>%2$s</strong>, <strong>%3$s</strong> and <strong>%4$s</strong>.',
		$counts['user'],
		'wp-useronline'
	);

	$output =
	html(
		'div id="useronline-details"',
		html( 'p', vsprintf( $text, $nicetexts ) )
		. html( 'p', UserOnline_Template::format_most_users() )
		. UserOnline_Template::detailed_list( $counts, $user_buckets, $nicetexts )
	);

	return apply_filters( 'useronline_page', $output );
}

// Function Check If User Is Online.
/**
 * Check whether a user is currently online.
 *
 * @param int $user_id User ID.
 *
 * @return bool
 */
function is_user_online( $user_id ) {
	global $wpdb;

	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT( * ) FROM $wpdb->useronline WHERE user_id = %d LIMIT 1", $user_id ) );
}

/**
 * Get the users online list in the requested shape.
 *
 * @param string $output One of 'html', 'list', 'buckets' or 'counts'.
 * @param string $type Either 'site' or 'page'.
 *
 * @return mixed
 */
function get_useronline_( $output, $type = 'site' ) {
	return UserOnline_Template::compact_list( $type, $output );
}
