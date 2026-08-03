<?php
/**
 * WP-UserOnline uninstall.php
 *
 * WordPress loads this file, and nothing else of the plugin, when the plugin is
 * deleted. Everything it does lives beside the installer it undoes, so the two
 * cannot drift apart and the work is reachable from the test suite.
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-wp-useronline-options.php';
require_once __DIR__ . '/includes/class-wp-useronline-install.php';

WP_UserOnline_Install::uninstall();
