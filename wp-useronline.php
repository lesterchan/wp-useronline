<?php
/*
Plugin Name: WP-UserOnline
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Enable you to display how many users are online on your Wordpress site
Version: 2.87.2
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-useronline
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

include __DIR__ . '/scb/load.php';

function _useronline_init() {
	load_plugin_textdomain( 'wp-useronline' );

	require_once dirname( __FILE__ ) . '/core.php';
	require_once dirname( __FILE__ ) . '/template-tags.php';
	require_once dirname( __FILE__ ) . '/deprecated.php';
	require_once dirname( __FILE__ ) . '/widget.php';

	new scbTable( 'useronline', __FILE__, "
		timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
		user_type varchar( 20 ) NOT NULL default 'guest',
		user_id bigint( 20 ) NOT NULL default 0,
		user_name varchar( 250 ) NOT NULL default '',
		user_ip varchar( 39 ) NOT NULL default '',
		user_agent text NOT NULL,
		page_title text NOT NULL,
		page_url varchar( 255 ) NOT NULL default '',
		referral varchar( 255 ) NOT NULL default '',
		UNIQUE KEY useronline_id ( timestamp, user_type, user_ip )
	", 'delete_first' );

	$most = new scbOptions( 'useronline_most', __FILE__, array(
		'count' => 1,
		'date' => current_time( 'timestamp' )
	) );

	$options = new scbOptions( 'useronline', __FILE__, array(
		'timeout' => 300,
		'url' => trailingslashit( get_bloginfo( 'url' ) ) . 'useronline',
		'names' => false,

		'naming' => array(
			'user'		=> __( '1 User', 'wp-useronline' ),
			'users'		=> __( '%COUNT% Users', 'wp-useronline' ),
			'member'	=> __( '1 Member', 'wp-useronline' ),
			'members'	=> __( '%COUNT% Members', 'wp-useronline' ),
			'guest' 	=> __( '1 Guest', 'wp-useronline' ),
			'guests'	=> __( '%COUNT% Guests', 'wp-useronline' ),
			'bot'		=> __( '1 Bot', 'wp-useronline' ),
			'bots'		=> __( '%COUNT% Bots', 'wp-useronline' )
		),

		'templates' => array(
			'useronline' => '<a href="%PAGE_URL%"><strong>%USERS%</strong> '.__( 'Online', 'wp-useronline' ).'</a>',

			'browsingsite' => array(
				'separators' => array(
					'members' => __( ',', 'wp-useronline' ).' ',
					'guests' => __( ',', 'wp-useronline' ).' ',
					'bots' => __( ',', 'wp-useronline' ).' ',
				),
				'text' => _x( 'Users', 'Template Element', 'wp-useronline' ).': <strong>%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%</strong>'
			),

			'browsingpage' => array(
				'separators' => array(
					'members' => __( ',', 'wp-useronline' ).' ',
					'guests' => __( ',', 'wp-useronline' ).' ',
					'bots' => __( ',', 'wp-useronline' ).' ',
				),
				'text' => '<strong>%USERS%</strong> '.__( 'Browsing This Page.', 'wp-useronline' ).'<br />'._x( 'Users', 'Template Element', 'wp-useronline' ).': <strong>%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%</strong>'
			)
		)
	) );

	UserOnline_Core::init( $options, $most );

	scbWidget::init( 'UserOnline_Widget', __FILE__, 'useronline' );

	if ( is_admin() ) {
		require_once __DIR__ . '/admin.php';
		scbAdminPage::register( 'UserOnline_Admin_Integration', __FILE__ );
		scbAdminPage::register( 'UserOnline_Options', __FILE__, UserOnline_Core::$options );
	}

	if ( function_exists( 'stats_page' ) )
		require_once __DIR__ . '/wp-stats.php';

#	scbUtil::do_uninstall( __FILE__ );
#	scbUtil::do_activation( __FILE__ );
}
scb_init( '_useronline_init' );

