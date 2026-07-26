<?php
/**
 * Plugin Name: WP-UserOnline
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Enable you to display how many users are online on your WordPress site.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-useronline
 * Domain Path: /languages
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-UserOnline version.
 */
define( 'WP_USERONLINE_VERSION', '3.0.0' );

/**
 * Database schema version. Bump when the table definition changes.
 */
define( 'WP_USERONLINE_DB_VERSION', '1' );

/**
 * WP-UserOnline main file.
 */
define( 'WP_USERONLINE_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-useronline-options.php';
require_once __DIR__ . '/includes/class-useronline-template.php';
require_once __DIR__ . '/includes/class-useronline-recorder.php';
require_once __DIR__ . '/includes/class-useronline.php';
require_once __DIR__ . '/template-tags.php';
require_once __DIR__ . '/deprecated.php';

UserOnline::get_instance();
