<?php
/*
Plugin Name: WP-UserOnline Widget
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds a UserOnline Widget To Display Users Online From WP-UserOnline Plugin
Version: 2.04
Author: GaMerZ
Author URI: http://www.lesterchan.net
*/


/*  Copyright 2006  Lester Chan  (email : gamerz84@hotmail.com)

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


### Function: Init WP-UserOnline Widget
function widget_useronline_init() {
	if (!function_exists('register_sidebar_widget')) {
		return;
	}

	### Function: WP-UserOnline Widget
	function widget_useronline($args) {
		extract($args);
		$options = get_option('widget_useronline');
		$title = __('UserOnline');
		echo $before_widget.$before_title.$title.$after_title;
		if (function_exists('useronline')) {
			echo '<ul>'."\n";
			echo '<li><a href="'.get_settings('home').'/wp-content/plugins/useronline/wp-useronline.php">';
			get_useronline();
			echo '</a></li>'."\n";
			if(intval($options['display_usersbrowsingsite']) == 1) {
				echo '<li>';
				get_users_browsing_site();
				echo '</li>'."\n";
			}
			echo '</ul>'."\n";
		}
		echo $after_widget;
	}

	### Function: WP-UserOnline Widget Options
	function widget_useronline_options() {
		$options = get_option('widget_useronline');
		if (!is_array($options)) {
			$options = array('display_usersbrowsingsite' => '0');
		}
		if ($_POST['useronline-submit']) {
			$options['display_usersbrowsingsite'] = intval($_POST['useronline-usersbrowsingsite']);
			update_option('widget_useronline', $options);
		}
		echo '<p style="text-align: center;"><label for="useronline-usersbrowsingsite">Display Users Browsing Site Under Users Online Count?</label></p>';
		echo '<p style="text-align: center;"><input type="radio" id="useronline-usersbrowsingsite" name="useronline-usersbrowsingsite" value="1"';
		checked(1, intval($options['display_usersbrowsingsite']));
		echo ' />&nbsp;Yes&nbsp;&nbsp;&nbsp;<input type="radio" id="useronline-usersbrowsingsite" name="useronline-usersbrowsingsite" value="0"';
		checked(0, intval($options['display_usersbrowsingsite']));
		echo ' />&nbsp;No</p>';
		echo '<input type="hidden" id="useronline-submit" name="useronline-submit" value="1" />';
	}

	// Register Widgets
	register_sidebar_widget('UserOnline', 'widget_useronline');
	register_widget_control('UserOnline', 'widget_useronline_options', 350, 100);
}


### Function: Load The WP-UserOnline Widget
add_action('plugins_loaded', 'widget_useronline_init');
?>