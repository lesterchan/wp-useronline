<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://wordpress.org/extend/plugins/wp-useronline/
Description: Enable you to display how many users are online on your Wordpress site
Version: 2.80-alpha
Author: Lester 'GaMerZ' Chan & scribu


Copyright 2009  Lester Chan  (email : lesterchan@gmail.com)

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

require dirname(__FILE__) . '/scb/load.php';

function _useronline_init() {
	require dirname(__FILE__) . '/core.php';
	require dirname(__FILE__) . '/template-tags.php';
	require dirname(__FILE__) . '/deprecated.php';

	load_plugin_textdomain('wp-useronline', '', dirname(plugin_basename(__FILE__)) . '/lang');

	UserOnline_Core::init();

	require_once dirname(__FILE__) . '/widget.php';
	scbWidget::init('UserOnline_Widget', __FILE__, 'useronline');

	if ( function_exists('stats_page') )
		require dirname(__FILE__) . '/wp-stats.php';

	if ( is_admin() ) {
		require dirname(__FILE__) . '/admin.php';
		scbAdminPage::register('UserOnline_Admin_Integration', __FILE__);
		scbAdminPage::register('UserOnline_Options', __FILE__, UserOnline_Core::$options);
	}
}
scb_init('_useronline_init');

