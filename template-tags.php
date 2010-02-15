<?php

### Function: Display UserOnline
function users_online() {
	echo get_users_online();
}

function get_users_online() {
	$template = get_option('useronline_template_useronline');
	$template = str_replace('%USERONLINE_PAGE_URL%', get_option('useronline_url'), $template);
	$template = str_replace('%USERONLINE_MOSTONLINE_COUNT%', get_most_users_online(), $template);
	$template = str_replace('%USERONLINE_MOSTONLINE_DATE%', get_most_users_online_date(), $template);

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

### Function Check If User Is Online
function is_online($user_login) {
	global $wpdb;

	return (bool) $wpdb->get_var($wpdb-prepare("SELECT COUNT(*) FROM $wpdb->useronline WHERE username = %s LIMIT 1", $user_login));
}

