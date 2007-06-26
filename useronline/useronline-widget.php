<?php
/*
Plugin Name: WP-UserOnline Widget
Plugin URI: http://www.lesterchan.net/portfolio/programming.php
Description: Adds a UserOnline Widget to display users online from WP-UserOnline Plugin. You need to activate WP-UserOnline first.
Version: 2.20
Author: GaMerZ
Author URI: http://www.lesterchan.net
*/


/*  
	Copyright 2007  Lester Chan  (email : gamerz84@hotmail.com)

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
		$title = htmlspecialchars($options['title']);
		echo $before_widget.$before_title.$title.$after_title;
		if (function_exists('useronline')) {
			echo '<ul>'."\n";
			echo '<li><div id="useronline-count">';
			get_useronline();
			echo '</div></li>'."\n";
			if(intval($options['display_usersbrowsingsite']) == 1) {
				echo '<li><div id="useronline-browsing-site">';
				get_users_browsing_site();
				echo '</div></li>'."\n";
			}
			echo '</ul>'."\n";
		}
		echo $after_widget;
	}

	### Function: WP-UserOnline Widget Options
	function widget_useronline_options() {
		$options = get_option('widget_useronline');
		if (!is_array($options)) {
			$options = array('display_usersbrowsingsite' => '0', 'title' => __('UserOnline', 'wp-useronline'));
		}
		if ($_POST['useronline-submit']) {
			$options['display_usersbrowsingsite'] = intval($_POST['useronline-usersbrowsingsite']);
			$options['title'] = strip_tags(stripslashes($_POST['useronline-title']));
			update_option('widget_useronline', $options);
		}
		echo '<p style="text-align: left;"><label for="useronline-title">'.__('Widget Title', 'wp-useronline').':</label>&nbsp;&nbsp;&nbsp;<input type="text" id="useronline-title" name="useronline-title" value="'.htmlspecialchars($options['title']).'" />';
		echo '<p style="text-align: center;"><label for="useronline-usersbrowsingsite">'.__('Display Users Browsing Site Under Users Online Count?', 'wp-useronline').'</label></p>'."\n";
		echo '<p style="text-align: center;"><input type="radio" id="useronline-usersbrowsingsite" name="useronline-usersbrowsingsite" value="1"';
		checked(1, intval($options['display_usersbrowsingsite']));
		echo ' />&nbsp;'.__('Yes', 'wp-useronline').'&nbsp;&nbsp;&nbsp;<input type="radio" id="useronline-usersbrowsingsite" name="useronline-usersbrowsingsite" value="0"';
		checked(0, intval($options['display_usersbrowsingsite']));
		echo ' />&nbsp;'.__('No', 'wp-useronline').'</p>'."\n";
		echo '<input type="hidden" id="useronline-submit" name="useronline-submit" value="1" />'."\n";
	}

	// Register Widgets
	register_sidebar_widget('UserOnline', 'widget_useronline');
	register_widget_control('UserOnline', 'widget_useronline_options', 350, 120);
}


### Function: Load The WP-UserOnline Widget
add_action('plugins_loaded', 'widget_useronline_init');
?>