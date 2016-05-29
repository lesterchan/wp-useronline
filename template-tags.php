<?php

### Function: Display UserOnline
function users_online() {
	echo get_users_online();
}

function get_users_online() {
	$template = UserOnline_Core::$options->templates['useronline'];
	$template = str_ireplace( '%PAGE_URL%', UserOnline_Core::$options->url, $template );
	$template = str_ireplace( '%MOSTONLINE_COUNT%', get_most_users_online(), $template );
	$template = str_ireplace( '%MOSTONLINE_DATE%', get_most_users_online_date(), $template );

	return UserOnline_Template::format_count( get_users_online_count(), 'user', $template );
}

### Function: Display UserOnline Count
function users_online_count() {
	echo number_format_i18n( get_useronline_count() );
}

function get_users_online_count() {
	return UserOnline_Core::get_user_online_count();
}

### Function: Display Max UserOnline
function most_users_online() {
	echo number_format_i18n( get_most_users_online() );
}

function get_most_users_online() {
	return intval( UserOnline_Core::$most->count );
}

### Function: Display Max UserOnline Date
function most_users_online_date() {
	echo get_most_users_online_date();
}

function get_most_users_online_date() {
	return UserOnline_Template::format_date( UserOnline_Core::$most->date );
}

### Function: Display Users Browsing The Site
function users_browsing_site() {
	echo get_users_browsing_site();
}

function get_users_browsing_site() {
	return UserOnline_Template::compact_list( 'site' );
}

### Function: Display Users Browsing The ( Current ) Page
function users_browsing_page( $page_url = '' ) {
	echo get_users_browsing_page( $page_url );
}

function get_users_browsing_page( $page_url = '' ) {
	return UserOnline_Template::compact_list( 'page', 'html', $page_url );
}

### Function: UserOnline Page
function users_online_page() {
	global $wpdb;

	$usersonline = $wpdb->get_results( "SELECT * FROM $wpdb->useronline ORDER BY timestamp DESC" );

	$user_buckets = array();
	foreach ( $usersonline as $useronline )
		$user_buckets[$useronline->user_type][] = $useronline;

	$user_buckets = apply_filters( 'useronline_buckets', $user_buckets );

	$counts = UserOnline_Template::get_counts( $user_buckets );

	$nicetexts = array();
	foreach ( array( 'user', 'member', 'guest', 'bot' ) as $user_type )
		$nicetexts[$user_type] = UserOnline_Template::format_count( $counts[$user_type], $user_type );

	$text = _n(
		'There is <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		'There are a total of <strong>%s</strong> online now: <strong>%s</strong>, <strong>%s</strong> and <strong>%s</strong>.',
		$counts['user'], 'wp-useronline'
	);

	$output =
	html( 'div id="useronline-details"',
		 html( 'p', vsprintf( $text, $nicetexts ) )
		.html( 'p', UserOnline_Template::format_most_users() )
		.UserOnline_Template::detailed_list( $counts, $user_buckets, $nicetexts )
	);

	return apply_filters( 'useronline_page', $output );
}

### Function Check If User Is Online
function is_user_online( $user_id ) {
	global $wpdb;

	return (bool) $wpdb->get_var( $wpdb-prepare( "SELECT COUNT( * ) FROM $wpdb->useronline WHERE user_id = %d LIMIT 1", $user_id ) );
}

function get_useronline_( $output, $type = 'site' ) {
	return UserOnline_Template::compact_list( $type, $output );
}

class UserOnline_Template {

	private static $cache = array();

	static function compact_list( $type, $output = 'html', $page_url = '') {
		UserOnline_Core::$add_script = true;

		if ( !isset( self::$cache[$type] ) ) {
			global $wpdb;

			if ( 'site' == $type ) {
				$where = '';
			} elseif ( 'page' == $type ) {
				if ( empty($page_url) )
					$page_url = $_SERVER['REQUEST_URI'];
				$where = $wpdb->prepare( 'WHERE page_url = %s', $page_url );
			}

			self::$cache[$type . $page_url] = $wpdb->get_results( "SELECT * FROM $wpdb->useronline $where ORDER BY timestamp DESC" );
		}

		$users = self::$cache[$type . $page_url];

		if ( 'list' == $output )
			return $users;

		$buckets = array();
		foreach ( $users as $user )
			$buckets[$user->user_type][] = $user;

		if ( 'buckets' == $output )
			return $buckets;

		$counts = self::get_counts( $buckets );

		if ( 'counts' == $output )
			return $counts;

		// Template - Naming Conventions
		$naming = UserOnline_Core::$options->naming;

		// Template - User(s) Browsing Site
		$template = UserOnline_Core::$options->templates["browsing$type"];

		// Nice Text For Users
		$output = self::format_count( $counts['user'], 'user', $template['text'] );

		// Print Member Name
		$temp_member = '';
		$members = @$buckets['member'];
		if ( $members ) {
			$temp_member = array();
			foreach ( $members as $member )
				$temp_member[] = self::format_name( $member );
			$temp_member = implode( $template['separators']['members'], $temp_member );
		}
		$output = str_ireplace( '%MEMBER_NAMES%', $temp_member, $output );

		// Counts
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( $counts[$user_type] > 1 )
				$number = str_ireplace( '%COUNT%', number_format_i18n( $counts[$user_type] ), $naming[$user_type . 's'] );
			elseif ( $counts[$user_type] == 1 )
				$number = $naming[$user_type];
			else
				$number = '';
			$output = str_ireplace( "%{$user_type}S%", $number, $output );
		}

		// SEPARATORs
		$separator = ( $counts['member'] && $counts['guest'] ) ? $template['separators']['guests'] : '';
		$output = str_ireplace( '%GUESTS_SEPARATOR%', $separator, $output );

		$separator = ( ( $counts['guest'] || $counts['member'] ) && $counts['bot'] ) ? $template['separators']['bots'] : '';
		$output = str_ireplace( '%BOTS_SEPARATOR%', $separator, $output );

		return $output;
	}

	static function detailed_list( $counts, $user_buckets, $nicetexts ) {
		UserOnline_Core::$add_script = true;

		if ( $counts['user'] == 0 )
			return html( 'h2', __( 'No one is online now.', 'wp-useronline' ) );

		$_on = __( 'on', 'wp-useronline' );
		$_url = __( 'url', 'wp-useronline' );
		$_referral = __( 'referral', 'wp-useronline' );

		$output = '';
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( !$counts[$user_type] )
				continue;

			$count = $counts[$user_type];
			$users = $user_buckets[$user_type];
			$nicetext = $nicetexts[$user_type];

			$output .= html( 'h2', $nicetext . ' ' . __( 'Online Now', 'wp-useronline' ) );

			$i=1;
			foreach ( $users as $user ) {
				$nr = number_format_i18n( $i++ );
				$name = self::format_name( $user );
				$user_ip = self::format_ip( $user );
				$date = self::format_date( $user->timestamp, true );

				if ( current_user_can( 'edit_users' ) || false === strpos( $user->page_url, 'wp-admin' ) ) {
					$page_title = esc_html( $user->page_title );
					$current_link = self::format_link( $user->page_url, $_url );
					$referral_link = self::format_link( $user->referral, $_referral );
				}

				$output .= apply_filters("useronline_custom_template", "<p><strong>#$nr - $name</strong> $user_ip $_on $date<br/>$page_title $current_link $referral_link</p>\n", $nr, $user);
			}
		}

		return $output;
	}

	static function format_link($url, $title) {
		if ( !empty($url) )
			return '[' . html_link( $url, $title ) . ']';

		return '';
	}

	static function format_ip( $user ) {
		$ip = $user->user_ip;

		if ( current_user_can( 'edit_users' ) && !empty( $ip ) && $ip != 'unknown' ) {
			return
			html( 'span', array('dir' => 'ltr'),
				html( 'a', array(
					'href' => 'http://whois.domaintools.com/' . $ip,
					'title' => $user->user_agent
				), $ip )
			);
		}
	}

	static function format_date( $date, $mysql = false ) {
		if ( $mysql )
			return mysql2date( sprintf( __( '%s @ %s', 'wp-useronline' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $date, true );

		return date_i18n( sprintf( __( '%s @ %s', 'wp-useronline' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $date );
	}

	static function format_name( $user ) {
		return apply_filters( 'useronline_display_user', esc_html( $user->user_name ), $user );
	}

	static function format_count( $count, $user_type, $template = false ) {
		$i = ( $count == 1 ) ? '' : 's';
		$string = UserOnline_Core::$options->naming[$user_type . $i];

		$output = str_ireplace( '%COUNT%', number_format_i18n( $count ), $string );

		if ( false === $template )
			return $output;

		return str_ireplace( '%USERS%', $output, $template );
	}

	static function format_most_users() {
		return sprintf( __( 'Most users ever online were <strong>%s</strong>, on <strong>%s</strong>', 'wp-useronline' ),
			number_format_i18n( get_most_users_online() ),
			get_most_users_online_date()
		);
	}

	static function get_counts( $buckets ) {
		$counts = array();
		$total = 0;
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			$count = isset( $buckets[$user_type] ) ? count( @$buckets[$user_type] ) : 0;
			$total += $counts[$user_type] = $count;
		}

		$counts['user'] = $total;

		return $counts;
	}
}

