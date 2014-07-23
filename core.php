<?php

class UserOnline_Core {

	static $add_script = false;

	static $options;
	static $most;

	private static $useronline;

	static function get_user_online_count() {
		global $wpdb;

		if ( is_null( self::$useronline ) )
			self::$useronline = intval( $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->useronline" ) );

		return self::$useronline;
	}

	static function init( $options, $most ) {
		self::$options = $options;
		self::$most = $most;

		add_action( 'plugins_loaded', array( __CLASS__, 'wp_stats_integration' ) );

		add_action( 'admin_head', array( __CLASS__, 'record' ) );
		add_action( 'wp_head', array( __CLASS__, 'record' ) );

		add_action( 'wp_footer', array( __CLASS__, 'scripts' ) );

		add_action( 'wp_ajax_useronline', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_useronline', array( __CLASS__, 'ajax' ) );

		add_shortcode( 'page_useronline', 'users_online_page' );

		if ( self::$options->names )
			add_filter( 'useronline_display_user', array( __CLASS__, 'linked_names' ), 10, 2 );
	}

	static function linked_names( $name, $user ) {
		if ( !$user->user_id )
			return $name;

		return html_link( get_author_posts_url( $user->user_id ), $name );
	}

	static function scripts() {
		if ( !self::$add_script )
			return;

		$js_dev = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		wp_enqueue_script( 'wp-useronline', plugins_url( "useronline$js_dev.js", __FILE__ ), array( 'jquery' ), '2.80', true );
		wp_localize_script( 'wp-useronline', 'useronlineL10n', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'timeout' => self::$options->timeout * 1000
		) );

		scbUtil::do_scripts('wp-useronline');
	}

	static function record( $page_url = '', $page_title = '' ) {
		require_once dirname(__FILE__) . '/bots.php';

		global $wpdb;

		if ( empty( $page_url ) )
			$page_url = $_SERVER['REQUEST_URI'];

		if ( empty( $page_title ) )
			$page_title = self::get_title();

		if ( isset( $_SERVER['HTTP_REFERER'] ) )
			$referral = strip_tags( $_SERVER['HTTP_REFERER'] );
		else
			$referral = '';

		$user_ip = self::get_ip();

		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) )
			$user_agent = strip_tags( $_SERVER['HTTP_USER_AGENT'] );
		else
			$user_agent = '';

		$current_user = wp_get_current_user();

		// Check For Bot
		$bots = useronline_get_bots();

		$bot_found = false;
		foreach ( $bots as $name => $lookfor ) {
			if ( stristr( $user_agent, $lookfor ) !== false ) {
				$user_id = 0;
				$user_name = $name;
				$username = $lookfor;
				$user_type = 'bot';
				$bot_found = true;

				break;
			}
		}

		// If No Bot Is Found, Then We Check Members And Guests
		if ( !$bot_found ) {
			if ( $current_user->ID ) {
				// Check For Member
				$user_id = $current_user->ID;
				$user_name = $current_user->display_name;
				$user_type = 'member';
				$where = $wpdb->prepare( "WHERE user_id = %d", $user_id );
			} elseif ( !empty( $_COOKIE['comment_author_'.COOKIEHASH] ) ) {
				// Check For Comment Author ( Guest )
				$user_id = 0;
				$user_name = trim( strip_tags( $_COOKIE['comment_author_'.COOKIEHASH] ) );
				$user_type = 'guest';
			} else {
				// Check For Guest
				$user_id = 0;
				$user_name = __( 'Guest', 'wp-useronline' );
				$user_type = 'guest';
			}
		}

		// Current GMT Timestamp
		$timestamp = current_time( 'mysql' );

		// Purge table
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->useronline WHERE user_ip = %s OR timestamp < DATE_SUB(%s, INTERVAL %d SECOND)", $user_ip, $timestamp, self::$options->timeout ) );

		// Insert Users
		$data = compact( 'timestamp', 'user_type', 'user_id', 'user_name', 'user_ip', 'user_agent', 'page_title', 'page_url', 'referral' );
		$data = stripslashes_deep( $data );
		$wpdb->replace( $wpdb->useronline, $data );

		// Count Users Online
		self::$useronline = intval( $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->useronline" ) );

		// Maybe Update Most User Online
		if ( self::$useronline > self::$most->count ) {
			self::$most->update( array(
				'count' => self::$useronline,
				'date' => current_time( 'timestamp' )
			) );
		}
	}

	private function clear_table() {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->useronline" );
	}

	static function ajax() {
		$mode = trim( $_POST['mode'] );

		$page_title = strip_tags( $_POST['page_title'] );

		$page_url = str_replace( get_bloginfo( 'url' ), '', $_POST['page_url'] );

		if ( $page_url != $_POST['page_url'] )
			self::record( $page_url, $page_title );

		switch( $mode ) {
			case 'count':
				users_online();
				break;
			case 'browsing-site':
				users_browsing_site();
				break;
			case 'browsing-page':
				users_browsing_page($page_url);
				break;
			case 'details':
				echo users_online_page();
				break;
		}

		die;
	}

	static function wp_stats_integration() {
		if ( function_exists( 'stats_page' ) )
			require_once dirname( __FILE__ ) . '/wp-stats.php';
	}

	private static function get_title() {
		if ( is_admin() && function_exists( 'get_admin_page_title' ) ) {
			$page_title = ' &raquo; ' . __( 'Admin', 'wp-useronline' ) . ' &raquo; ' . get_admin_page_title();
		} else {
			$page_title = wp_title( '&raquo;', false );
			if ( empty( $page_title ) )
				$page_title = ' &raquo; ' . strip_tags( $_SERVER['REQUEST_URI'] );
			elseif ( is_singular() )
				$page_title = ' &raquo; ' . __( 'Archive', 'wp-useronline' ) . ' ' . $page_title;
		}
		$page_title = get_bloginfo( 'name' ) . $page_title;

		return $page_title;
	}

	private static function get_ip() {
		if ( isset( $_SERVER["HTTP_X_FORWARDED_FOR"] ) )
			$ip_address = $_SERVER["HTTP_X_FORWARDED_FOR"];
		else
			$ip_address = $_SERVER["REMOTE_ADDR"];

		list( $ip_address ) = explode( ',', $ip_address );

		return $ip_address;
	}
}

