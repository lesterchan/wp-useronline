<?php
/**
 * Plugin Name: WP-UserOnline
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Enable you to display how many users are online on your WordPress site.
 * Version: 4.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-useronline
 * Domain Path: /languages
 *
 * @package WP-UserOnline
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-UserOnline version. Compared against the 'plugin' marker in wp_useronline_version.
 */
define( 'WP_USERONLINE_VERSION', '4.0.0' );

/**
 * Schema counter. Compared against the 'db' marker in wp_useronline_version.
 */
define( 'WP_USERONLINE_DB_VERSION', '1' );

/**
 * Plugin slug, text domain and menu slug.
 */
define( 'WP_USERONLINE_SLUG', 'wp-useronline' );

/**
 * WP-UserOnline main file.
 */
define( 'WP_USERONLINE_MAIN_FILE', __FILE__ );

/**
 * Filesystem path of the plugin directory, with a trailing slash.
 */
define( 'WP_USERONLINE_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 */
define( 'WP_USERONLINE_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/includes/class-wp-useronline-options.php';
require_once __DIR__ . '/includes/class-wp-useronline-template.php';
require_once __DIR__ . '/includes/class-wp-useronline-recorder.php';
require_once __DIR__ . '/includes/class-wp-useronline.php';
require_once __DIR__ . '/includes/template-tags.php';
require_once __DIR__ . '/includes/deprecated.php';

WP_UserOnline::get_instance();
