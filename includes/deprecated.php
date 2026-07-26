<?php
/**
 * Deprecated template tags, kept so themes written against the pre-2.70 API
 * keep working. Each one forwards to its replacement.
 *
 * @package WP-UserOnline
 */

/**
 * Display the users online page.
 *
 * @deprecated 2.70 Use users_online_page() instead.
 *
 * @return void
 */
function useronline_page() {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online_page()' );

	users_online_page();
}

/**
 * Display the users online count.
 *
 * @deprecated 2.70 Use users_online() instead.
 *
 * @return void
 */
function get_useronline() {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online()' );

	users_online();
}

/**
 * Get the highest recorded number of users online.
 *
 * @deprecated 2.70 Use get_most_users_online() instead.
 *
 * @return int
 */
function get_most_useronline() {
	_deprecated_function( __FUNCTION__, '2.70', 'get_most_users_online()' );

	return get_most_users_online();
}

/**
 * Get the date the highest number of users online was recorded.
 *
 * @deprecated 2.70 Use get_most_users_online_date() instead.
 *
 * @return string
 */
function get_most_useronline_date() {
	_deprecated_function( __FUNCTION__, '2.70', 'get_most_users_online_date()' );

	return get_most_users_online_date();
}

/**
 * Get or display the number of users currently online.
 *
 * @deprecated 2.70 Use users_online_count() instead.
 *
 * @param bool $display Whether to echo the count instead of returning it.
 *
 * @return int|void Count when $display is false, nothing otherwise.
 */
function get_useronline_count( $display = false ) {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online_count()' );

	if ( ! $display ) {
		return get_users_online_count();
	}

	users_online_count();
}
