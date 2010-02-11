<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: http://lesterchan.net/portfolio/programming/php/
Description: Enable you to display how many users are online on your Wordpress blog with detailed statistics of where they are and who there are(Members/Guests/Search Bots).
Version: 2.60a2
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
		add_action('template_redirect', array(__CLASS__, 'scripts'));

		add_action('admin_head', array(__CLASS__, 'record'));
		add_action('wp_head', array(__CLASS__, 'record'));

		add_action('wp_ajax_useronline', array(__CLASS__, 'ajax'));
		add_action('wp_ajax_nopriv_useronline', array(__CLASS__, 'ajax'));

		register_activation_hook(__FILE__, array(__CLASS__, 'install'));
		register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
	}

	function useronline_install() {
		$bots = array('Google Bot' => 'googlebot', 'Google Bot' => 'google', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'pubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot', 'Become.com' => 'become.com');

		// Add In Options
		add_option('useronline_most_users', 1);
		add_option('useronline_most_timestamp', current_time('timestamp'));
		add_option('useronline_timeout', 300);
		add_option('useronline_bots', $bots);

		// Database Upgrade For WP-UserOnline 2.05
		add_option('useronline_url', '');

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
			_c('Users|Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
		));

		add_option('useronline_template_browsingpage', array(
			__(',', 'wp-useronline').' ',
			__(',', 'wp-useronline').' ',
			__(',', 'wp-useronline').' ', 
			'<strong>%USERONLINE_USERS%</strong> '.__('Browsing This Page.', 'wp-useronline').'<br />'._c('Users|Template Element', 'wp-useronline').': <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>'
		));
	}

	function useronline_uninstall() {
		$useronline_settings = array('useronline_most_users', 'useronline_most_timestamp', 'useronline_timeout', 'useronline_bots', 'useronline_url', 'useronline_naming', 'useronline_template_useronline', 'useronline_template_browsingsite', 'useronline_template_browsingpage', 'widget_useronline');

		foreach ( $useronline_settings as $setting )
			delete_option($setting);
	}

	function scripts() {
		wp_enqueue_script('wp-useronline', plugins_url('useronline-js.js', __FILE__), array('jquery'), '2.60', true);
		wp_localize_script('wp-useronline', 'useronlineL10n', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'timeout' => (get_option('useronline_timeout')*1000)
		));
	}

	function record() {
		global $wpdb, $useronline;

		$timeoutseconds = get_option('useronline_timeout');
		$timestamp = current_time('timestamp');
		$timeout = $timestamp - $timeoutseconds;

		$ip = useronline_get_ipaddress();
		$url = $_SERVER['REQUEST_URI'];

		$referral = '';
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$current_user = wp_get_current_user();
		if ( !empty($_SERVER['HTTP_REFERER'] ))
			$referral = strip_tags($_SERVER['HTTP_REFERER']);

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
		if ( is_admin() ) {
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
// DEBUG
#$wpdb->query("DELETE FROM $wpdb->useronline");

		$delete_users = $wpdb->query("DELETE FROM $wpdb->useronline $where OR (timestamp < $timeout)");

		// Insert Users
		$data = compact('timestamp', 'userid', 'username', 'displayname', 'useragent', 'ip', 'location', 'url', 'type', 'referral');
		$data = stripslashes_deep($data);
		$insert_user = $wpdb->insert($wpdb->useronline, $data);

		// Count Users Online
		$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));

		// Get Most User Online
		$most_useronline = intval(get_option('useronline_most_users'));

		// Check Whether Current Users Online Is More Than Most Users Online
		if ( $useronline > $most_useronline ) {
			update_option('useronline_most_users', $useronline);
			update_option('useronline_most_timestamp', current_time('timestamp'));
		}
	}
	
	function ajax() {
		$mode = trim($_POST['mode']);

		if ( empty($mode) )
			return;

		switch($mode) {
			case 'count':
				get_useronline();
				break;
			case 'browsingsite':
				get_users_browsing_site();				
				break;
			case 'browsingpage':
				get_users_browsing_page();
				break;
		}

		die();
	}
}

### Function: Display UserOnline
function get_useronline($display = true) {
	$template = get_option('useronline_template_useronline');
	$template = str_replace('%USERONLINE_PAGE_URL%', get_option('useronline_url'), $template);
	$template = str_replace('%USERONLINE_MOSTONLINE_COUNT%', number_format_i18n(get_most_useronline()), $template);
	$template = str_replace('%USERONLINE_MOSTONLINE_DATE%', get_most_useronline_date(), $template);

	$template = _useronline_template_users($template, get_useronline_count());

	if ( !$display )
		return $template;

	echo $template;
}

function _useronline_template_users($template, $count) {
	$naming = get_option('useronline_naming');

	if ( $count == 1 )
		$naming_users = $naming['user'];
	else
		$naming_users = str_ireplace('%USERONLINE_COUNT%', number_format_i18n($count), $naming['users']);

	return str_ireplace('%USERONLINE_USERS%', $naming_users, $template);
}


### Function: Display UserOnline Count
function get_useronline_count($display = false) {
	global $wpdb, $useronline;

	if ( ! isset($useronline) )
		$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));

	if ( !$display )
		return $useronline;

	echo number_format_i18n($useronline);
}

function _useronline_most_users() {
	return sprintf(__('Most users ever online were <strong>%s</strong>, on <strong>%s</strong>', 'wp-useronline'), number_format_i18n(get_most_useronline()), get_most_useronline_date());
}

### Function: Display Max UserOnline
function get_most_useronline($display = false) {
	$most_useronline_users = intval(get_option('useronline_most_users'));
	if ( $display ) {
		echo number_format_i18n($most_useronline_users);
	} else {
		return $most_useronline_users;
	}
}

### Function: Display Max UserOnline Date
function get_most_useronline_date($display = false) {
	$most_useronline_date = useronline_get_date(get_option('useronline_most_timestamp'));

	if ( !$display )
		return $most_useronline_date;

	echo $most_useronline_date;
}

function useronline_get_date($timestamp) {
	return date_i18n(sprintf(__('%s @ %s', 'wp-useronline'), get_option('date_format'), get_option('time_format')), $timestamp, true);
}

### Function Check If User Is Online
function is_online($user_login) {
	global $wpdb;
	return intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline WHERE username = '$user_login' LIMIT 1"));
}



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
	return useronline_get_date(get_user_option('member_last_login', $user_id));
}


### Function: Display Users Browsing The Site
function get_users_browsing_site($display = true) {
	global $wpdb;

	$users_online = $wpdb->get_results("SELECT displayname, type FROM $wpdb->useronline ORDER BY type");

	if ( !$users_online )
		return;

	return _useronline_template_list('site', $users_online, $display);
}

### Function: Display Users Browsing The Page
function get_users_browsing_page($display = true) {
	global $wpdb;

	$page_url = esc_sql(urlencode($_SERVER['REQUEST_URI']));
	$users_online = $wpdb->get_results("SELECT displayname, type FROM $wpdb->useronline WHERE url = '$page_url' ORDER BY type");

	if ( !$users_online )
		return;

	return _useronline_template_list('page', $users_online, $display);
}

function _useronline_template_list($type, $users_online, $display) {
	// Get Users Information
	$buckets = get_useronline_buckets($users_online);
	$counts = get_useronline_counts($buckets);

	if ( !$display )
		return $counts;

	// Template - Naming Conventions
	$naming = get_option('useronline_naming');

	// Template - User(s) Browsing Site
	list($separator_members, $separator_guests, $separator_bots, $template) = get_option("useronline_template_browsing$type");

	// Nice Text For Users
	$template = _useronline_template_users($template, $counts['user']);

	// Print Member Name
	$temp_member = '';
	$members = $buckets['member'];
	if ( $members ) {
		$temp_member = array();
		foreach ( $members as $member )
			$temp_member[] = get_useronline_display_name($member);
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

	// Output The Template
	echo $template;
}

function get_useronline_buckets($users) {
	$buckets = array();

	foreach ( $users as $user )
		$buckets[$user->type][] = $user->displayname;

	return $buckets;
}

function get_useronline_counts($buckets) {
	$counts = array();

	$total = 0;
	foreach ( array('member', 'guest', 'bot') as $type )
		$total += $counts[$type] = count(@$buckets[$type]);

	$counts['user'] = $total;

	return $counts;
}

### Function: Get IP Address
function useronline_get_ipaddress() {
	if ( isset($_SERVER["HTTP_X_FORWARDED_FOR"]) )
		$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
	else
		$ip_address = $_SERVER["REMOTE_ADDR"];

	list($ip_address) = explode(',', $ip_address);

	return $ip_address;
}


### Function: Check IP
function check_ip($ip) {
	if ( ! current_user_can('administrator') || empty($ip) || $ip == 'unknown' )
		return;

	return '<span dir="ltr">(<a href="http://whois.domaintools.com/' . $ip . '" title="'.gethostbyaddr($ip).'">' . $ip . '</a>)</span>';
}

### Function: Short Code For Inserting Users Online Into Page
add_shortcode('page_useronline', 'useronline_page');

### Function: UserOnline Page
function useronline_page() {
	global $wpdb;

	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	$user_buckets = array();
	foreach ( $usersonline as $useronline )
		$user_buckets[$useronline->type][] = (array) $useronline;

	$counts = get_useronline_counts($user_buckets);

	$texts = array(
		'user' => array(__('User', 'wp-useronline'), __('Users', 'wp-useronline')),
		'member' => array(__('Member', 'wp-useronline'), __('Members', 'wp-useronline')),
		'guest' => array(__('Guest', 'wp-useronline'), __('Guests', 'wp-useronline')),
		'bot' => array(__('Bot', 'wp-useronline'), __('Bots', 'wp-useronline')),
	);

	foreach ( $texts as $type => $strings )
		$nicetexts[$type] = number_format_i18n($counts[$type]).' '._n($strings[0], $strings[1], $counts[$type]);

	$text = _n(
		__('There is <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.', 'wp-useronline'),
		__('There are a total of <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.', 'wp-useronline'),
		$counts['user']
	);

	$output = 
	html('p', sprintf($text, $nicetexts['user'], $nicetexts['member'], $nicetexts['guest'], $nicetexts['bot']))
	.html('p', _useronline_most_users());

	if ( $counts['user'] == 0 )
		$output .= html('h2', __('No One Is Online Now', 'wp-useronline'));
	else
		foreach ( array('member', 'guest', 'bot') as $type )
			if ( $counts[$type] )
				$output .= _useronline_print_list($counts[$type], $user_buckets[$type], $nicetexts[$type]);

	// Output UserOnline Page
	return apply_filters('useronline_page', $output);
}

function _useronline_print_list($count, $users, $nicetext) {
	$output = html('h2', "$nicetext ".__('Online Now', 'wp-useronline'));

	$on = __('on', 'wp-useronline');
	$url = __('url', 'wp-useronline');
	$referral = __('referral', 'wp-useronline');

	$i=1;
	foreach ( $users as $user ) {
		$nr = number_format_i18n($i++);
		$name = get_useronline_display_name($user['displayname']);
		$ip = check_ip($user['ip']);
		$date = useronline_get_date($user['timestamp']);
		$location = $user['location'];
		$current_link = '[' . html_link(esc_url($user['url']), $url) .']';

		$referral_link = '';
		if ( !empty($user['referral']) )
			$referral_link = '[' . html_link(esc_url($user['referral']), $referral) . ']';

		$output .= html('p', "<strong>#$nr - $name</strong> $ip $on $date<br/>$location $current_link $referral_link") . "\n";
	}

	return $output;
}

function get_useronline_display_name($user) {
	return apply_filters('useronline_display_name', $user);
}

class UserOnline_Widget extends scbWidget {
	function UserOnline_Widget() {
		$widget_ops = array('description' => __('WP-UserOnline users online statistics', 'wp-useronline'));
		$this->WP_Widget('useronline', __('UserOnline', 'wp-useronline'), $widget_ops);
	}

	function content($instance) {
		$type = esc_attr($instance['type']);

		echo '<ul>'."\n";
		switch($type) {
			case 'users_online':
				echo '<li><div id="useronline-count">';
				get_useronline();
				echo '</div></li>'."\n";
				break;
			case 'users_browsing_page':
				echo '<li><div id="useronline-browsing-page">';
				get_users_browsing_page();
				echo '</div></li>'."\n";
				break;
			case 'users_browsing_site':
				echo '<li><div id="useronline-browsing-site">';
				get_users_browsing_site();
				echo '</div></li>'."\n";
				break;
			case 'users_online_browsing_page':
				echo '<li><div id="useronline-count">';
				get_useronline();
				echo '</div></li>'."\n";
				echo '<li><div id="useronline-browsing-page">';
				get_users_browsing_page();
				echo '</div></li>'."\n";
				break;
			case 'users_online_browsing_site':
				echo '<li><div id="useronline-count">';
				get_useronline();
				echo '</div></li>'."\n";
				echo '<li><div id="useronline-browsing-site">';
				get_users_browsing_site();
				echo '</div></li>'."\n";
				break;
		}
		echo "</ul>\n";
	}

	function update($new_instance, $old_instance) {
		if ( !isset($new_instance['submit']) )
			return false;

		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['type'] = strip_tags($new_instance['type']);

		return $instance;
	}

	function form($instance) {
		global $wpdb;
		$instance = wp_parse_args((array) $instance, array(
			'title' => __('UserOnline', 'wp-useronline'), 
			'type' => 'users_online'
		));
		$title = esc_attr($instance['title']);
		$type = esc_attr($instance['type']);
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-useronline'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Statistics Type:', 'wp-useronline'); ?>
				<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
					<option value="users_online"<?php selected('users_online', $type); ?>><?php _e('Users Online Count', 'wp-useronline'); ?></option>
					<option value="users_browsing_page"<?php selected('users_browsing_page', $type); ?>><?php _e('Users Browsing Current Page', 'wp-useronline'); ?></option>
					<option value="users_browsing_site"<?php selected('users_browsing_site', $type); ?>><?php _e('Users Browsing Site', 'wp-useronline'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="users_online_browsing_page"<?php selected('users_online_browsing_page', $type); ?>><?php _e('Users Online Count & Users Browsing Current Page', 'wp-useronline'); ?></option>
					<option value="users_online_browsing_site"<?php selected('users_online_browsing_site', $type); ?>><?php _e('Users Online Count & Users Browsing Site', 'wp-useronline'); ?></option>
				</select>
			</label>
		</p>
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
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

	scbWidget::init('UserOnline_Widget', __FILE__);

	if ( function_exists('stats_page') )
		require_once dirname(__FILE__) . '/wp-stats.php';

	if ( is_admin() ) {
		require_once dirname(__FILE__) . '/admin.php';
		new UserOnline_Options(__FILE__);
	}
}
_useronline_init();

