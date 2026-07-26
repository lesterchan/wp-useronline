<?php
/**
 * Uninstall WP-UserOnline.
 *
 * Runs with the plugin inactive, so nothing here may depend on the plugin's
 * own classes or functions being loaded.
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

/**
 * Delete the plugin's options for the current site.
 *
 * @return void
 */
function useronline_delete_options() {
	$option_names = array(
		'useronline',
		'useronline_most',
		'useronline_sanitize_version',
		'useronline_db_version',
		'widget_useronline',
	);

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}
}

/**
 * Drop the plugin's table for the current site.
 *
 * @return void
 */
function useronline_drop_table() {
	global $wpdb;

	// The table name is built entirely from $wpdb->prefix and a literal, so
	// there is no user input to prepare. Identifiers cannot be bound anyway.
	$table = $wpdb->prefix . 'useronline';

	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

if ( is_multisite() ) {
	// 'number' => 0 lifts the default cap, which would otherwise leave data
	// behind on networks with more than 100 sites.
	$ms_sites = get_sites( array( 'number' => 0 ) );

	foreach ( $ms_sites as $ms_site ) {
		switch_to_blog( $ms_site->blog_id );

		useronline_delete_options();
		useronline_drop_table();

		restore_current_blog();
	}
} else {
	useronline_delete_options();
	useronline_drop_table();
}
