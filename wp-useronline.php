<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://wordpress.org/extend/plugins/wp-useronline/
Description: Enable you to display how many users are online on your Wordpress blog with detailed statistics of where they are and who there are(Members/Guests/Search Bots).
Version: 2.70-alpha3 (very buggy)
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

class UserOnline_Core {
	static $options;
	static $most;
	static $naming;
	static $templates;

	function init() {
		add_action('plugins_loaded', array(__CLASS__, 'wp_stats_integration'));

		add_action('template_redirect', array(__CLASS__, 'scripts'));

		add_action('admin_head', array(__CLASS__, 'record'));
		add_action('wp_head', array(__CLASS__, 'record'));

		add_action('wp_ajax_useronline', array(__CLASS__, 'ajax'));
		add_action('wp_ajax_nopriv_useronline', array(__CLASS__, 'ajax'));

		add_shortcode('page_useronline', 'users_online_page');

		register_activation_hook(__FILE__, array(__CLASS__, 'upgrade'));

		// Settings
		self::$options = new scbOptions('useronline', __FILE__, array(
			'timeout' => 300,
			'url' => trailingslashit(get_bloginfo('url')) . 'useronline'
		));

		self::$most = new scbOptions('useronline_most', __FILE__, array(
			'count' => 1,
			'timestamp' => current_time('timestamp')
		));

		self::$naming = new scbOptions('useronline_naming', __FILE__, array(
			'user'		=> __('1 User', 'wp-useronline'), 
			'users'		=> __('%USERONLINE_COUNT% Users', 'wp-useronline'), 
			'member'	=> __('1 Member', 'wp-useronline'), 
			'members'	=> __('%USERONLINE_COUNT% Members', 'wp-useronline'), 
			'guest' 	=> __('1 Guest', 'wp-useronline'),
			'guests'	=> __('%USERONLINE_COUNT% Guests', 'wp-useronline'),
			'bot'		=> __('1 Bot', 'wp-useronline'),
			'bots'		=> __('%USERONLINE_COUNT% Bots', 'wp-useronline')
		));

		self::$templates = new scbOptions('useronline_templates', __FILE__, array(
			'useronline' => '<a href="%USERONLINE_PAGE_URL%" title="%USERONLINE_USERS%"><strong>%USERONLINE_USERS%</strong> '.__('Online', 'wp-useronline').'</a>',
			'browsingsite' => array(
				__(',', 'wp-useronline').' ',
				__(',', 'wp-useronline').' ', 
				__(',', 'wp-useronline').' ', 
				_x('Users', 'Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
			),
			'browsingpage' => array(
				__(',', 'wp-useronline').' ',
				__(',', 'wp-useronline').' ',
				__(',', 'wp-useronline').' ', 
				'<strong>%USERONLINE_USERS%</strong> '.__('Browsing This Page.', 'wp-useronline').'<br />'._x('Users', 'Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
			)
		));
	}

	function upgrade() {
		self::clear_table();
		//todo
	}

	function wp_stats_integration() {
		if ( function_exists('stats_page') )
			require_once dirname(__FILE__) . '/wp-stats.php';
	}

	function scripts() {
		$js_dev = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '.dev' : '';

		wp_enqueue_script('wp-useronline', plugins_url("useronline$js_dev.js", __FILE__), array('jquery'), '2.70', true);
		wp_localize_script('wp-useronline', 'useronlineL10n', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'timeout' => get_option('useronline_timeout')*1000
		));
	}

	function record() {
		global $wpdb, $useronline;

		$timeoutseconds = get_option('useronline_timeout');
		$timestamp = current_time('timestamp');
		$timeout = $timestamp - $timeoutseconds;

		$ip = self::get_ip();
		$url = $_SERVER['REQUEST_URI'];

		$referral = '';
		if ( !empty($_SERVER['HTTP_REFERER']) )
			$referral = strip_tags($_SERVER['HTTP_REFERER']);

		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$current_user = wp_get_current_user();

		// Check For Bot
		$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'pubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com' => 'become.com', 'Baidu' => 'baidu');

		$bot_found = false;
		foreach ($bots as $name => $lookfor) {
			if ( stristr($useragent, $lookfor) === false )
				continue;

			$userid = 0;
			$displayname = $name;
			$username = $lookfor;
			$type = 'bot';
			$where = "WHERE ip = '$ip'";
			$bot_found = true;

			break;
		}

		// If No Bot Is Found, Then We Check Members And Guests
		if ( !$bot_found ) {
			// Check For Member
			if ( $current_user->ID > 0 ) {
				$userid = $current_user->ID;
				$displayname = $current_user->display_name;
				$username = $current_user->user_login;
				$type = 'member';
				$where = "WHERE userid = '$userid'";
			// Check For Comment Author (Guest)
			} elseif ( !empty($_COOKIE['comment_author_'.COOKIEHASH] )) {
				$userid = 0;
				$displayname = trim($_COOKIE['comment_author_'.COOKIEHASH]);
				$username = __('guest', 'wp-useronline').'_'.$displayname;	
				$type = 'guest';
				$where = "WHERE ip = '$ip'";
			// Check For Guest
			} else {
				$userid = 0;
				$displayname = __('Guest', 'wp-useronline');
				$username = "guest";
				$type = 'guest';
				$where = "WHERE ip = '$ip'";
			}
		}

		// Check For Page Title
		if ( is_admin() && function_exists('get_admin_page_title') ) {
			$location = ' &raquo; '.__('Admin', 'wp-useronline').' &raquo; '.get_admin_page_title();
		} else {
			$location = wp_title('&raquo;', false);
			if ( empty($location) ) {
				$location = ' &raquo; '.$_SERVER['REQUEST_URI']; 
			} elseif ( is_singular() ) {
				$location = ' &raquo; '.__('Archive', 'wp-useronline').' '.$location;
			}
		}
		$location = get_bloginfo('name').$location;

		// Delete Users
		$delete_users = $wpdb->query("DELETE FROM $wpdb->useronline $where OR (timestamp < $timeout)");

		// Insert Users
		$data = compact('timestamp', 'userid', 'username', 'displayname', 'useragent', 'ip', 'location', 'url', 'type', 'referral');
		$data = stripslashes_deep($data);
		$insert_user = $wpdb->insert($wpdb->useronline, $data);

		// Count Users Online
		$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));

		// Maybe Update Most User Online
		if ( $useronline > self::$most->count )
			self::$most->update(array(
				'count' => $useronline,
				'timestamp' => current_time('timestamp')
			));
	}

	function ajax() {
		$mode = trim($_POST['mode']);

		switch($mode) {
			case 'count':
				users_online();
				break;
			case 'browsingsite':
				users_browsing_site();				
				break;
			case 'browsingpage':
				users_browsing_page();
				break;
		}

		die();
	}
	
	private function get_ip() {
		if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) )
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		else
			$ip_address = $_SERVER["REMOTE_ADDR"];

		list($ip_address) = explode(',', $ip_address);

		return $ip_address;
	}

	private function clear_table() {
		global $wpdb;

		$wpdb->query("DELETE FROM $wpdb->useronline");
	}
}

/*
### Function: Update Member last Visit
//add_action('wp_head', 'update_memberlastvisit');
function update_memberlastvisit() {
	global $current_user, $user_ID;
	if ( !empty($current_user ) && is_user_logged_in()) {
		update_user_option($user_ID, 'member_last_login', current_time('timestamp'));   
	}
}


### Function: Get Member last Visit
function get_memberlastvisit($user_id = 0) {
	return UserOnline_Template::format_date(get_user_option('member_last_login', $user_id));
}
*/

function _useronline_init() {
	require_once dirname(__FILE__) . '/scb/load.php';

	require_once dirname(__FILE__) . '/template-tags.php';
	require_once dirname(__FILE__) . '/deprecated.php';

	load_plugin_textdomain('wp-useronline', '', basename(dirname(__FILE__)));

	new scbTable('useronline', __FILE__, "
		timestamp int(15) NOT NULL default '0',
		userid int(10) NOT NULL default '0',
		username varchar(20) NOT NULL default '',
		displayname varchar(255) NOT NULL default '',
		useragent varchar(255) NOT NULL default '',
		ip varchar(40) NOT NULL default '',				 
		location varchar(255) NOT NULL default '',
		url varchar(255) NOT NULL default '',
		type enum('member','guest','bot') NOT NULL default 'guest',
		referral varchar(255) NOT NULL default '',
		UNIQUE KEY useronline_id (timestamp,username,ip,useragent)
	");

	UserOnline_Core::init();

	require_once dirname(__FILE__) . '/widget.php';
	scbWidget::init('UserOnline_Widget', __FILE__, 'useronline');

	if ( function_exists('stats_page') )
		require_once dirname(__FILE__) . '/wp-stats.php';

	if ( is_admin() ) {
		require_once dirname(__FILE__) . '/admin.php';
		scbAdminPage::register('UserOnline_Options', __FILE__);
		scbAdminPage::register('UserOnline_Admin_Page', __FILE__);
	}
}
_useronline_init();

function wpu_linked_names($name, $user) {
#debug_print_backtrace();
	if ( !$user->userid )
		return $name;

	return html_link(get_author_posts_url($user->userid), $name);
}
add_filter('useronline_display_name', 'wpu_linked_names', 10, 2);

