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

	return UserOnline_Template::format_count($template, get_users_online_count());
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

	$users_online = $wpdb->get_results("SELECT displayname, type FROM $wpdb->useronline ORDER BY type");

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

	$users_online = $wpdb->get_results($wpdb->prepare("SELECT displayname, type FROM $wpdb->useronline WHERE url = %s ORDER BY type", $page_url));

	return UserOnline_Template::compact_list('page', $users_online);
}

### Function: UserOnline Page
function users_online_page() {
	global $wpdb;

	$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline ORDER BY type");

	$user_buckets = array();
	foreach ( $usersonline as $useronline )
		$user_buckets[$useronline->type][] = (array) $useronline;

	$counts = UserOnline_Template::get_counts($user_buckets);

	$texts = array(
		'user' => array(__('User', 'wp-useronline'), __('Users', 'wp-useronline')),
		'member' => array(__('Member', 'wp-useronline'), __('Members', 'wp-useronline')),
		'guest' => array(__('Guest', 'wp-useronline'), __('Guests', 'wp-useronline')),
		'bot' => array(__('Bot', 'wp-useronline'), __('Bots', 'wp-useronline')),
	);
	foreach ( $texts as $type => $strings ) {
		$i = ($counts[$type] == 1) ? 0 : 1;
		$nicetexts[$type] = number_format_i18n($counts[$type]).' '.$strings[$i];
	}

	$text = _n(
		'There is <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		'There are a total of <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		$counts['user'], 'wp-useronline'
	);

	$output = 
	 html('p', sprintf($text, $nicetexts['user'], $nicetexts['member'], $nicetexts['guest'], $nicetexts['bot']))
	.html('p', UserOnline_Template::format_most_users())
	.UserOnline_Template::detailed_list($counts, $user_buckets, $nicetexts);

	return apply_filters('useronline_page', $output);
}

### Function Check If User Is Online
function is_online($user_login) {
	global $wpdb;

	return (bool) $wpdb->get_var($wpdb-prepare("SELECT COUNT(*) FROM $wpdb->useronline WHERE username = %s LIMIT 1", $user_login));
}

