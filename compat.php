<?php

function useronline_page() {
	users_online_page();
}

function get_useronline() {
	users_online();
}

function get_most_useronline() {
	return get_most_users_online();
}

function get_most_useronline_date() {
	return get_most_users_online_date();
}

function get_useronline_count($display = false) {
	if ( !$display )
		return get_users_online_count();

	users_online_count();
}


