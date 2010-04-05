<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://wordpress.org/extend/plugins/wp-useronline/
Description: Enable you to display how many users are online on your Wordpress blog with detailed statistics of where they are and who there are(Members/Guests/Search Bots).
Version: 2.70a
Author: Lester 'GaMerZ' Chan
Author URI: http://lesterchan.net
*/


/*
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

	function init() {
		add_action('plugins_loaded', array(__CLASS__, 'wp_stats_integration'));

		add_action('template_redirect', array(__CLASS__, 'scripts'));

		add_action('admin_head', array(__CLASS__, 'record'));
		add_action('wp_head', array(__CLASS__, 'record'));

		add_action('wp_ajax_useronline', array(__CLASS__, 'ajax'));
		add_action('wp_ajax_nopriv_useronline', array(__CLASS__, 'ajax'));

		add_shortcode('page_useronline', 'users_online_page');

		register_activation_hook(__FILE__, array(__CLASS__, 'install'));
		scbUtil::add_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
	}

	function wp_stats_integration() {
		if ( function_exists('stats_page') )
			require_once dirname(__FILE__) . '/wp-stats.php';
	}

	function install() {
		self::clear_table();

		$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'pubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com' => 'become.com', 'Baidu' => 'baidu');

		// Add In Options
		add_option('useronline_most_users', 1);
		add_option('useronline_most_timestamp', current_time('timestamp'));
		add_option('useronline_timeout', 300);
		add_option('useronline_bots', $bots);

		// Database Upgrade For WP-UserOnline 2.05
		add_option('useronline_url', user_trailingslashit(trailingslashit(get_bloginfo('url')) . 'useronline'));

		// Database Upgrade For WP-UserOnline 2.20
		add_option('useronline_naming', array(
			'user' => __('1 User', 'wp-useronline'), 
			'users' => __('%USERONLINE_COUNT% Users', 'wp-useronline'), 
			'member' => __('1 Member', 'wp-useronline'), 
			'members' => __('%USERONLINE_COUNT% Members', 'wp-useronline'), 
			'guest' => __('1 Guest', 'wp-useronline'),
			'guests' => __('%USERONLINE_COUNT% Guests', 'wp-useronline'),
			'bot' => __('1 Bot', 'wp-useronline'),
			'bots' => __('%USERONLINE_COUNT% Bots', 'wp-useronline')
		));

		add_option('useronline_template_useronline', '<a href="%USERONLINE_PAGE_URL%" title="%USERONLINE_USERS%"><strong>%USERONLINE_USERS%</strong> '.__('Online', 'wp-useronline').'</a>');

		add_option('useronline_template_browsingsite', array(
			__(',', 'wp-useronline').' ',
			__(',', 'wp-useronline').' ', 
			__(',', 'wp-useronline').' ', 
			_x('Users', 'Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
		));

		add_option('useronline_template_browsingpage', array(
			__(',', 'wp-useronline').' ',
			__(',', 'wp-useronline').' ',
			__(',', 'wp-useronline').' ', 
			'<strong>%USERONLINE_USERS%</strong> '.__('Browsing This Page.', 'wp-useronline').'<br />'._x('Users', 'Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
		));
	}

	function uninstall() {
		$useronline_settings = array('useronline_most_users', 'useronline_most_timestamp', 'useronline_timeout', 'useronline_bots', 'useronline_url', 'useronline_naming', 'useronline_template_useronline', 'useronline_template_browsingsite', 'useronline_template_browsingpage', 'widget_useronline');

		foreach ( $useronline_settings as $setting )
			delete_option($setting);
	}

	function scripts() {
		wp_enqueue_script('wp-useronline', plugins_url('useronline-js.js', __FILE__), array('jquery'), '2.60', true);
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
		$bots = get_option('useronline_bots');
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
		$most_useronline = intval(get_option('useronline_most_users'));

		if ( $useronline > $most_useronline ) {
			update_option('useronline_most_users', $useronline);
			update_option('useronline_most_timestamp', current_time('timestamp'));
		}
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

class UserOnline_Template {

	function compact_list($type, $users) {
		if ( empty($users) )
			return '';

		$buckets = array();
		foreach ( $users as $user )
			$buckets[$user->type][] = $user->displayname;

		$counts = self::get_counts($buckets);

		// Template - Naming Conventions
		$naming = get_option('useronline_naming');

		// Template - User(s) Browsing Site
		list($separator_members, $separator_guests, $separator_bots, $template) = get_option("useronline_template_browsing$type");

		// Nice Text For Users
		$template = self::format_count($template, $counts['user']);

		// Print Member Name
		$temp_member = '';
		$members = $buckets['member'];
		if ( $members ) {
			$temp_member = array();
			foreach ( $members as $member )
				$temp_member[] = self::format_name($member, 'member');
			$temp_member = implode($separator_members, $temp_member);
		}
		$template = str_ireplace('%USERONLINE_MEMBER_NAMES%', $temp_member, $template);

		// Counts
		foreach ( array('member', 'guest', 'bot') as $type ) {
			if ( $counts[$type] > 1 )
				$number = str_ireplace('%USERONLINE_COUNT%', number_format_i18n($counts[$type]), $naming[$type . 's']);
			elseif ( $counts[$type] == 1 )
				$number = $naming[$type];
			else
				$number = '';
			$template = str_ireplace('%USERONLINE_' . $type . 'S%', $number, $template);
		}

		// Seperators
		if ( $counts['member'] > 0 && $counts['guest'] > 0 )
			$separator = $separator_guests;
		else
			$separator = '';
		$template = str_ireplace('%USERONLINE_GUESTS_SEPERATOR%', $separator, $template);

		if ( ($counts['guest'] > 0 || $counts['member'] > 0 ) && $counts['bot'] > 0)
			$separator = $separator_bots;
		else
			$separator = '';
		$template = str_ireplace('%USERONLINE_BOTS_SEPERATOR%', $separator, $template);

		echo $template;
	}

	function detailed_list($counts, $user_buckets, $nicetexts) {
		if ( $counts['user'] == 0 )
			return html('h2', __('No One Is Online Now', 'wp-useronline'));

		$on = __('on', 'wp-useronline');
		$url = __('url', 'wp-useronline');
		$referral = __('referral', 'wp-useronline');

		$output = '';
		foreach ( array('member', 'guest', 'bot') as $type ) {
			if ( !$counts[$type] )
				continue;

			$count = $counts[$type];
			$users = $user_buckets[$type];
			$nicetext = $nicetexts[$type];

			$output .= html('h2', "$nicetext ".__('Online Now', 'wp-useronline'));

			$i=1;
			foreach ( $users as $user ) {
				$nr = number_format_i18n($i++);
				$name = self::format_name($user['displayname'], $type);
				$ip = self::format_ip($user['ip']);
				$date = self::format_date($user['timestamp']);
				$location = $user['location'];
				$current_link = '[' . html_link(esc_url($user['url']), $url) .']';

				$referral_link = '';
				if ( !empty($user['referral']) )
					$referral_link = '[' . html_link(esc_url($user['referral']), $referral) . ']';

				$output .= html('p', "<strong>#$nr - $name</strong> $ip $on $date<br/>$location $current_link $referral_link") . "\n";
			}
		}

		return $output;
	}


	function format_ip($ip) {
		if ( ! current_user_can('administrator') || empty($ip) || $ip == 'unknown' )
			return;

		return '<span dir="ltr">(<a href="http://whois.domaintools.com/' . $ip . '" title="'.gethostbyaddr($ip).'">' . $ip . '</a>)</span>';
	}

	function format_date($timestamp) {
		return date_i18n(sprintf(__('%s @ %s', 'wp-useronline'), get_option('date_format'), get_option('time_format')), $timestamp, true);
	}

	function format_name($user, $type) {
		return apply_filters('useronline_display_name', $user, $type);
	}
	
	function format_count($template, $count) {
		$naming = get_option('useronline_naming');

		if ( $count == 1 )
			$naming_users = $naming['user'];
		else
			$naming_users = str_ireplace('%USERONLINE_COUNT%', number_format_i18n($count), $naming['users']);

		return str_ireplace('%USERONLINE_USERS%', $naming_users, $template);
	}
	
	function format_most_users() {
		return sprintf(__('Most users ever online were <strong>%s</strong>, on <strong>%s</strong>', 'wp-useronline'), number_format_i18n(get_most_users_online()), get_most_users_online_date());
	}

	function get_counts($buckets) {
		$counts = array();
		$total = 0;
		foreach ( array('member', 'guest', 'bot') as $type )
			$total += $counts[$type] = count(@$buckets[$type]);

		$counts['user'] = $total;

		return $counts;
	}
}

function _useronline_init() {
	require_once dirname(__FILE__) . '/scb/load.php';

	load_plugin_textdomain('wp-useronline', false, basename(dirname(__FILE__)));

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

	require_once dirname(__FILE__) . '/template-tags.php';
	require_once dirname(__FILE__) . '/compat.php';

	require_once dirname(__FILE__) . '/widget.php';
	scbWidget::init('UserOnline_Widget', __FILE__);

	if ( function_exists('stats_page') )
		require_once dirname(__FILE__) . '/wp-stats.php';

	if ( is_admin() ) {
		require_once dirname(__FILE__) . '/admin.php';
		scbAdminPage::register('UserOnline_Options', __FILE__);
		scbAdminPage::register('UserOnline_Admin_Page', __FILE__);
	}
}
_useronline_init();

