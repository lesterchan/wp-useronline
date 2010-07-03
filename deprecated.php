<?php

function useronline_page() {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online_page()' );

	users_online_page();
}

function get_useronline() {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online()' );

	users_online();
}

function get_most_useronline() {
	_deprecated_function( __FUNCTION__, '2.70', 'get_most_users_online()' );

	return get_most_users_online();
}

function get_most_useronline_date() {
	_deprecated_function( __FUNCTION__, '2.70', 'get_most_users_online()' );

	return get_most_users_online_date();
}

function get_useronline_count( $display = false ) {
	_deprecated_function( __FUNCTION__, '2.70', 'users_online_count()' );

	if ( !$display )
		return get_users_online_count();

	users_online_count();
}

