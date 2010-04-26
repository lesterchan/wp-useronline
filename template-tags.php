<?php

### Function: Display UserOnline
function users_online() {
	echo get_users_online();
}

function get_users_online() {
	$template = get_option('useronline_template_useronline');
	$template = str_ireplace('%USERONLINE_PAGE_URL%', get_option('useronline_url'), $template);
	$template = str_ireplace('%USERONLINE_MOSTONLINE_COUNT%', get_most_users_online(), $template);
	$template = str_ireplace('%USERONLINE_MOSTONLINE_DATE%', get_most_users_online_date(), $template);

	return UserOnline_Template::format_count(get_users_online_count(), 'user', $template);
}

### Function: Display UserOnline Count
function users_online_count() {
	echo number_format_i18n(get_useronline_count());
}

function get_users_online_count() {
	global $wpdb, $useronline;

	if ( ! isset($useronline) )
		$useronline = intval($wpdb->get_var("SELECT COUNT(*) FROM $wpdb->useronline"));

	return $useronline;
}

### Function: Display Max UserOnline
function most_users_online() {
	echo number_format_i18n(get_most_users_online());
}

function get_most_users_online() {
	return intval(get_option('useronline_most_users'));
}

### Function: Display Max UserOnline Date
function most_users_online_date() {
	echo get_most_users_online_date();
}

function get_most_users_online_date() {
	return UserOnline_Template::format_date(get_option('useronline_most_timestamp'));
}

### Function: Display Users Browsing The Site
function users_browsing_site() {
	echo get_users_browsing_site();
}

function get_users_browsing_site() {
	global $wpdb;

	$users_online = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	return UserOnline_Template::compact_list('site', $users_online);
}

### Function: Display Users Browsing The (Current) Page
function users_browsing_page($page_url = '') {
	echo get_users_browsing_page($page_url);
}

function get_users_browsing_page($page_url = '') {
	global $wpdb;

	if ( empty($page_url) )
		$page_url = $_SERVER['REQUEST_URI'];

	$users_online = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->useronline WHERE url = %s ORDER BY type", $page_url));

	return UserOnline_Template::compact_list('page', $users_online);
}

### Function: UserOnline Page
function users_online_page() {
	global $wpdb;

	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	$user_buckets = array();
	foreach ( $usersonline as $useronline )
		$user_buckets[$useronline->type][] = $useronline;

	$counts = UserOnline_Template::get_counts($user_buckets);

	$nicetexts = array();
	foreach ( array('user', 'member', 'guest', 'bot') as $type )
		$nicetexts[$type] = UserOnline_Template::format_count($counts[$type], $type);

	$text = _n(
		'There is <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		'There are a total of <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		$counts['user'], 'wp-useronline'
	);

	$output = 
	 html('p', vsprintf($text, $nicetexts))
	.html('p', UserOnline_Template::format_most_users())
	.UserOnline_Template::detailed_list($counts, $user_buckets, $nicetexts);

	return apply_filters('useronline_page', $output);
}

### Function Check If User Is Online
function is_online($user_login) {
	global $wpdb;

	return (bool) $wpdb->get_var($wpdb-prepare("SELECT COUNT(*) FROM $wpdb->useronline WHERE username = %s LIMIT 1", $user_login));
}


class UserOnline_Template {

	function compact_list($type, $users) {
		if ( empty($users) )
			return '';

		$buckets = array();
		foreach ( $users as $user )
			$buckets[$user->type][] = $user;

		$counts = self::get_counts($buckets);

		// Template - Naming Conventions
		$naming = get_option('useronline_naming');

		// Template - User(s) Browsing Site
		list($separator_members, $separator_guests, $separator_bots, $template) = get_option("useronline_template_browsing$type");

		// Nice Text For Users
		$template = self::format_count($counts['user'], 'user', $template);

		// Print Member Name
		$temp_member = '';
		$members = $buckets['member'];
		if ( $members ) {
			$temp_member = array();
			foreach ( $members as $member )
				$temp_member[] = self::format_name($member);
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

		return $template;
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
				$name = self::format_name($user);
				$ip = self::format_ip($user->ip);
				$date = self::format_date($user->timestamp);
				$location = $user->location;
				$current_link = '[' . html_link(esc_url($user->url), $url) .']';

				$referral_link = '';
				if ( !empty($user->referral) )
					$referral_link = '[' . html_link(esc_url($user->referral), $referral) . ']';

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

	function format_name($user) {
		return apply_filters('useronline_display_name', $user->displayname, $user);
	}

	function format_count($count, $type, $template = '') {
		$i = ($count == 1) ? '' : 's';
		$string = UserOnline_Core::$naming->get($type . $i);

		$output = str_ireplace('%USERONLINE_COUNT%', number_format_i18n($count), $string);

		if ( empty($template) )
			return $output;

		return str_ireplace('%USERONLINE_USERS%', $output, $template);
	}

	function format_most_users() {
		return sprintf(__('Most users ever online were <strong>%s</strong>, on <strong>%s</strong>', 'wp-useronline'),
			number_format_i18n(get_most_users_online()),
			get_most_users_online_date()
		);
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

