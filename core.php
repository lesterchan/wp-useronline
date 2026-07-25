<?php

class UserOnline_Core {

	/**
	 * Bumped whenever sanitize_options() gets stricter, so that values already
	 * in the database are put through the new rules once.
	 */
	const SANITIZE_VERSION = 1;

	const SANITIZE_VERSION_OPTION = 'useronline_sanitize_version';

	static $add_script = false;

	static $options;
	static $most;

	private static $useronline;

	static function get_user_online_count() {
		global $wpdb;

		if ( is_null( self::$useronline ) ) {
			self::$useronline = intval( $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->useronline" ) );
		}

		return self::$useronline;
	}

	static function init( $options, $most ) {
		self::$options = $options;
		self::$most    = $most;

		add_action( 'plugins_loaded', array( __CLASS__, 'wp_stats_integration' ) );

		add_action( 'admin_head', array( __CLASS__, 'record' ) );
		add_action( 'wp_head', array( __CLASS__, 'record' ) );

		add_action( 'wp_footer', array( __CLASS__, 'scripts' ) );

		add_action( 'wp_ajax_useronline', array( __CLASS__, 'ajax' ) );
		add_action( 'wp_ajax_nopriv_useronline', array( __CLASS__, 'ajax' ) );

		add_shortcode( 'page_useronline', 'users_online_page' );

		self::maybe_sanitize_stored_options();

		if ( self::$options->names ) {
			add_filter( 'useronline_display_user', array( __CLASS__, 'linked_names' ), 10, 2 );
		}
	}

	/**
	 * Sanitize a set of plugin options.
	 *
	 * The naming conventions and templates are echoed verbatim by
	 * get_users_online() and UserOnline_Template::compact_list(), so they are
	 * sanitized on the way in rather than on the way out. Missing or malformed
	 * keys fall back to the defaults instead of warning: scbOptions::get()
	 * merges only the top level of the array, so a partial save can otherwise
	 * leave nested template keys absent.
	 *
	 * @param array $options
	 *
	 * @return array
	 */
	static function sanitize_options( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = isset( self::$options ) ? self::$options->get_defaults() : array();

		$options['timeout'] = isset( $options['timeout'] ) ? absint( $options['timeout'] ) : 0;
		$options['url']     = ! empty( $options['url'] ) ? esc_url_raw( trim( $options['url'] ) ) : '';
		$options['names']   = ! empty( $options['names'] ) ? (int) $options['names'] : 0;

		// Naming conventions: fill any gaps from the defaults, then sanitize
		// every entry, including keys the defaults don't know about.
		$default_naming = isset( $defaults['naming'] ) && is_array( $defaults['naming'] ) ? $defaults['naming'] : array();
		$naming         = isset( $options['naming'] ) && is_array( $options['naming'] ) ? $options['naming'] : array();
		$naming         = array_merge( $default_naming, $naming );

		foreach ( $naming as $key => $template ) {
			$naming[ $key ] = wp_kses_post( trim( (string) $template ) );
		}
		$options['naming'] = $naming;

		// Templates: rebuilt from the defaults so the shape is guaranteed for
		// compact_list(), which indexes into ['text'] and ['separators'].
		$default_templates = isset( $defaults['templates'] ) && is_array( $defaults['templates'] ) ? $defaults['templates'] : array();
		$templates         = isset( $options['templates'] ) && is_array( $options['templates'] ) ? $options['templates'] : array();

		$clean = array();
		foreach ( $default_templates as $key => $default_template ) {
			if ( is_array( $default_template ) ) {
				$stored = isset( $templates[ $key ] ) && is_array( $templates[ $key ] ) ? $templates[ $key ] : array();

				$text = isset( $stored['text'] ) && ! is_array( $stored['text'] ) ? $stored['text'] : $default_template['text'];

				$clean[ $key ] = array(
					'text'       => wp_kses_post( trim( (string) $text ) ),
					'separators' => array(),
				);

				$stored_separators = isset( $stored['separators'] ) && is_array( $stored['separators'] ) ? $stored['separators'] : array();

				foreach ( $default_template['separators'] as $separator_key => $separator_default ) {
					$separator = isset( $stored_separators[ $separator_key ] ) && ! is_array( $stored_separators[ $separator_key ] )
						? $stored_separators[ $separator_key ]
						: $separator_default;

					// Not trimmed: the defaults are ", " and the trailing space
					// is what keeps names apart in the rendered list.
					$clean[ $key ]['separators'][ $separator_key ] = wp_kses_post( (string) $separator );
				}
			} else {
				$stored = isset( $templates[ $key ] ) && ! is_array( $templates[ $key ] ) ? $templates[ $key ] : $default_template;

				$clean[ $key ] = wp_kses_post( trim( (string) $stored ) );
			}
		}
		$options['templates'] = $clean;

		return $options;
	}

	/**
	 * Re-sanitize options that were stored before the escaping rules were
	 * tightened. Sanitizing only on save means an install that was already
	 * compromised stays compromised after it updates, because the bad value is
	 * never resubmitted through the settings form.
	 */
	private static function maybe_sanitize_stored_options() {
		if ( (int) get_option( self::SANITIZE_VERSION_OPTION ) >= self::SANITIZE_VERSION ) {
			return;
		}

		self::$options->update( self::sanitize_options( self::$options->get() ) );

		update_option( self::SANITIZE_VERSION_OPTION, self::SANITIZE_VERSION );
	}

	static function linked_names( $name, $user ) {
		if ( ! $user->user_id ) {
			return $name;
		}

		return html_link( get_author_posts_url( $user->user_id ), $name );
	}

	static function scripts() {
		if ( ! self::$add_script ) {
			return;
		}

		$js_dev = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '.dev' : '';

		wp_enqueue_script( 'wp-useronline', plugins_url( "useronline$js_dev.js", __FILE__ ), array( 'jquery' ), '2.80', true );
		wp_localize_script(
			'wp-useronline',
			'useronlineL10n',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'timeout'  => self::$options->timeout * 1000,
			)
		);

		scbUtil::do_scripts( 'wp-useronline' );
	}

	static function record( $page_url = '', $page_title = '' ) {
		require_once __DIR__ . '/bots.php';

		global $wpdb;

		if ( empty( $page_url ) ) {
			$page_url = wp_strip_all_tags( $_SERVER['REQUEST_URI'] );
		}

		if ( empty( $page_title ) ) {
			$page_title = wp_strip_all_tags( self::get_title() );
		}

		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$referral = wp_strip_all_tags( $_SERVER['HTTP_REFERER'] );
		} else {
			$referral = '';
		}

		$user_ip = wp_strip_all_tags( self::get_ip() );

		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$user_agent = wp_strip_all_tags( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$user_agent = '';
		}

		$current_user = wp_get_current_user();

		// Check For Bot
		$bots = useronline_get_bots();

		$bot_found = false;
		foreach ( $bots as $name => $lookfor ) {
			if ( stristr( $user_agent, $lookfor ) !== false ) {
				$user_id   = 0;
				$user_name = $name;
				$username  = $lookfor;
				$user_type = 'bot';
				$bot_found = true;

				break;
			}
		}

		// If No Bot Is Found, Then We Check Members And Guests
		if ( ! $bot_found ) {
			if ( $current_user->ID ) {
				// Check For Member
				$user_id   = $current_user->ID;
				$user_name = $current_user->display_name;
				$user_type = 'member';
				$where     = $wpdb->prepare( 'WHERE user_id = %d', $user_id );
			} elseif ( ! empty( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) ) {
				// Check For Comment Author ( Guest )
				$user_id   = 0;
				$user_name = trim( wp_strip_all_tags( $_COOKIE[ 'comment_author_' . COOKIEHASH ] ) );
				$user_type = 'guest';
			} else {
				// Check For Guest
				$user_id   = 0;
				$user_name = __( 'Guest', 'wp-useronline' );
				$user_type = 'guest';
			}
		}

		// Current GMT Timestamp
		$timestamp = current_time( 'mysql' );

		// Purge table
		$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->useronline WHERE (user_id <> 0 AND user_id = %d) OR (user_id = 0 AND user_agent = %s AND user_ip = %s) OR (timestamp < DATE_SUB(%s, INTERVAL %d SECOND))", $user_id, $user_agent, $user_ip, $timestamp, self::$options->timeout ) );

		// Insert Users
		$data = compact( 'timestamp', 'user_type', 'user_id', 'user_name', 'user_ip', 'user_agent', 'page_title', 'page_url', 'referral' );
		$data = stripslashes_deep( $data );
		$wpdb->replace( $wpdb->useronline, $data );

		// Count Users Online
		self::$useronline = intval( $wpdb->get_var( "SELECT COUNT( * ) FROM $wpdb->useronline" ) );

		// Maybe Update Most User Online
		if ( self::$useronline > self::$most->count ) {
			self::$most->update(
				array(
					'count' => self::$useronline,
					'date'  => current_time( 'timestamp' ),
				)
			);
		}
	}

	private function clear_table() {
		global $wpdb;

		$wpdb->query( "DELETE FROM $wpdb->useronline" );
	}

	static function ajax() {
		$mode = isset( $_POST['mode'] ) ? trim( (string) $_POST['mode'] ) : '';

		// Validate the mode before anything is written. An unrecognised mode
		// used to fall straight through the switch below while still having
		// recorded a row on the way in.
		if ( ! in_array( $mode, array( 'count', 'browsing-site', 'browsing-page', 'details' ), true ) ) {
			die;
		}

		$page_url = self::local_url( isset( $_POST['page_url'] ) ? (string) $_POST['page_url'] : '' );

		if ( null !== $page_url ) {
			$page_title = isset( $_POST['page_title'] ) ? sanitize_text_field( (string) $_POST['page_title'] ) : '';

			self::record( $page_url, mb_substr( $page_title, 0, 250 ) );
		}

		switch ( $mode ) {
			case 'count':
				users_online();
				break;
			case 'browsing-site':
				users_browsing_site();
				break;
			case 'browsing-page':
				users_browsing_page( (string) $page_url );
				break;
			case 'details':
				echo users_online_page();
				break;
		}

		die;
	}

	/**
	 * Reduce a client-submitted absolute URL to a site-relative path.
	 *
	 * The caller chooses this value, so anything not belonging to this site is
	 * rejected outright by returning null. Replaces a str_replace() plus
	 * inequality test that accepted any URL merely containing the site URL.
	 *
	 * The path is kept whole rather than having the site URL cut off it, so
	 * that it matches what record() stores from REQUEST_URI on a subdirectory
	 * install and the browsing-page lookup can actually find those rows.
	 *
	 * @param string $url
	 *
	 * @return string|null Site-relative path, or null when the URL is foreign.
	 */
	private static function local_url( $url ) {
		$url = trim( $url );

		if ( '' === $url ) {
			return null;
		}

		$parts = wp_parse_url( $url );
		$home  = wp_parse_url( home_url() );

		if ( empty( $parts['host'] ) || empty( $home['host'] ) ) {
			return null;
		}

		if ( strtolower( $parts['host'] ) !== strtolower( $home['host'] ) ) {
			return null;
		}

		$path = isset( $parts['path'] ) && '' !== $parts['path'] ? $parts['path'] : '/';

		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		// page_url is a varchar( 255 ).
		return mb_substr( wp_strip_all_tags( $path ), 0, 255 );
	}

	static function wp_stats_integration() {
		if ( function_exists( 'stats_page' ) ) {
			require_once __DIR__ . '/wp-stats.php';
		}
	}

	private static function get_title() {
		if ( is_admin() && function_exists( 'get_admin_page_title' ) ) {
			$page_title = ' &raquo; ' . __( 'Admin', 'wp-useronline' ) . ' &raquo; ' . get_admin_page_title();
		} else {
			$page_title = wp_title( '&raquo;', false );
			if ( empty( $page_title ) ) {
				$page_title = ' &raquo; ' . strip_tags( $_SERVER['REQUEST_URI'] );
			} elseif ( is_singular() ) {
				$page_title = ' &raquo; ' . $page_title;
			}
		}
		$page_title = get_bloginfo( 'name' ) . $page_title;

		return $page_title;
	}

	private static function get_ip() {
		// X-Forwarded-For is set by the client and can say anything at all, so
		// it is only consulted when the site declares that it sits behind a
		// trusted proxy. Otherwise a visitor could forge an address on every
		// request, which both falsifies the log and defeats the de-duplication
		// in record().
		$headers = array( 'REMOTE_ADDR' );

		if ( apply_filters( 'useronline_trust_proxy', defined( 'USERONLINE_TRUST_PROXY' ) && USERONLINE_TRUST_PROXY ) ) {
			array_unshift( $headers, 'HTTP_X_FORWARDED_FOR' );
		}

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			list( $ip_address ) = explode( ',', $_SERVER[ $header ] );

			$ip_address = filter_var( trim( $ip_address ), FILTER_VALIDATE_IP );

			if ( false !== $ip_address ) {
				return $ip_address;
			}
		}

		return '';
	}
}
