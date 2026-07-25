<?php
/**
 * Formatting helpers behind the users online template tags.
 *
 * @package WP-UserOnline
 */

/**
 * Formatting helpers for the users online output.
 */
class UserOnline_Template {

	/**
	 * Per-request cache of query results, keyed by type and page URL.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Build the compact browsing list for a type.
	 *
	 * @param string $type Either 'site' or 'page'.
	 * @param string $output Output shape.
	 * @param string $page_url Page URL when $type is 'page'.
	 *
	 * @return mixed
	 */
	public static function compact_list( $type, $output = 'html', $page_url = '' ) {
		UserOnline_Core::$add_script = true;

		if ( ! isset( self::$cache[ $type ] ) ) {
			global $wpdb;

			if ( 'site' == $type ) {
				$where = '';
			} elseif ( 'page' == $type ) {
				if ( empty( $page_url ) ) {
					$page_url = $_SERVER['REQUEST_URI'];
				}
				$where = $wpdb->prepare( 'WHERE page_url = %s', $page_url );
			}

			self::$cache[ $type . $page_url ] = $wpdb->get_results( "SELECT * FROM $wpdb->useronline $where ORDER BY timestamp DESC" );
		}

		$users = self::$cache[ $type . $page_url ];

		if ( 'list' == $output ) {
			return $users;
		}

		$buckets = array();
		foreach ( $users as $user ) {
			$buckets[ $user->user_type ][] = $user;
		}

		if ( 'buckets' == $output ) {
			return $buckets;
		}

		$counts = self::get_counts( $buckets );

		if ( 'counts' == $output ) {
			return $counts;
		}

		// Template - Naming Conventions.
		$naming = UserOnline_Core::$options->naming;

		// Template - User(s) Browsing Site.
		$template = UserOnline_Core::$options->templates[ "browsing$type" ];

		// Nice Text For Users.
		$output = self::format_count( $counts['user'], 'user', $template['text'] );

		// Print Member Name.
		$temp_member = '';
		$members     = isset( $buckets['member'] ) ? $buckets['member'] : array();
		if ( $members ) {
			$temp_member = array();
			foreach ( $members as $member ) {
				$temp_member[] = self::format_name( $member );
			}
			$temp_member = implode( $template['separators']['members'], $temp_member );
		}
		$output = str_ireplace( '%MEMBER_NAMES%', $temp_member, $output );

		// Counts.
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( $counts[ $user_type ] > 1 ) {
				$number = str_ireplace( '%COUNT%', number_format_i18n( $counts[ $user_type ] ), $naming[ $user_type . 's' ] );
			} elseif ( $counts[ $user_type ] == 1 ) {
				$number = $naming[ $user_type ];
			} else {
				$number = '';
			}
			$output = str_ireplace( "%{$user_type}S%", $number, $output );
		}

		// SEPARATORs.
		$separator = ( $counts['member'] && $counts['guest'] ) ? $template['separators']['guests'] : '';
		$output    = str_ireplace( '%GUESTS_SEPARATOR%', $separator, $output );

		$separator = ( ( $counts['guest'] || $counts['member'] ) && $counts['bot'] ) ? $template['separators']['bots'] : '';
		$output    = str_ireplace( '%BOTS_SEPARATOR%', $separator, $output );

		return $output;
	}

	/**
	 * Build the detailed per-user list.
	 *
	 * @param array $counts Counts per user type.
	 * @param array $user_buckets Users grouped by type.
	 * @param array $nicetexts Formatted counts per type.
	 *
	 * @return string
	 */
	public static function detailed_list( $counts, $user_buckets, $nicetexts ) {
		UserOnline_Core::$add_script = true;

		if ( $counts['user'] == 0 ) {
			return html( 'h2', __( 'No one is online now.', 'wp-useronline' ) );
		}

		$_on       = __( 'on', 'wp-useronline' );
		$_url      = __( 'url', 'wp-useronline' );
		$_referral = __( 'referral', 'wp-useronline' );

		$output = '';
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( ! $counts[ $user_type ] ) {
				continue;
			}

			$count    = $counts[ $user_type ];
			$users    = $user_buckets[ $user_type ];
			$nicetext = $nicetexts[ $user_type ];

			$output .= html( 'h2', $nicetext . ' ' . __( 'Online Now', 'wp-useronline' ) );

			$i = 1;
			foreach ( $users as $user ) {
				$nr      = number_format_i18n( $i++ );
				$name    = self::format_name( $user );
				$user_ip = self::format_ip( $user );
				$date    = self::format_date( $user->timestamp, true );

				// Reset per user, so that a user whose location is hidden below
				// doesn't inherit the previous user's page details.
				$page_title    = '';
				$current_link  = '';
				$referral_link = '';

				if ( current_user_can( 'edit_users' ) || false === strpos( $user->page_url, 'wp-admin' ) ) {
					$page_title    = esc_html( $user->page_title );
					$current_link  = self::format_link( $user->page_url, $_url );
					$referral_link = self::format_link( $user->referral, $_referral );
				}

				/**
				 * Filter the markup for a single user on the detailed list.
				 *
				 * The default value is already escaped. $user carries the raw,
				 * visitor-controlled database row ( page_title, page_url,
				 * referral and user_agent are all attacker-supplied ), so
				 * anything rebuilt from it must be escaped again.
				 *
				 * @param string $markup Escaped markup for this user.
				 * @param string $nr     Formatted position in the list.
				 * @param object $user   Raw useronline row. Unescaped.
				 */
				$output .= apply_filters( 'useronline_custom_template', "<p><strong>#$nr - $name</strong> $user_ip $_on $date<br/>$page_title $current_link $referral_link</p>\n", $nr, $user );
			}
		}

		return $output;
	}

	/**
	 * Wrap a URL in a bracketed link.
	 *
	 * @param string $url Target URL.
	 * @param string $title Link text.
	 *
	 * @return string
	 */
	public static function format_link( $url, $title ) {
		if ( ! empty( $url ) ) {
			return '[' . html_link( $url, $title ) . ']';
		}

		return '';
	}

	/**
	 * Format a user's IP as a whois link, for users who may see it.
	 *
	 * @param object $user Useronline row.
	 *
	 * @return string
	 */
	public static function format_ip( $user ) {
		$ip = $user->user_ip;

		if ( current_user_can( 'edit_users' ) && ! empty( $ip ) && $ip != 'unknown' ) {
			// html() escapes attributes, so $ip and $user_agent are passed raw
			// and escaped once, at the point they are rendered.
			return html(
				'span',
				array( 'dir' => 'ltr' ),
				html(
					'a',
					array(
						'href'  => 'http://whois.domaintools.com/' . rawurlencode( $ip ),
						'title' => $user->user_agent,
					),
					esc_html( $ip )
				)
			);
		}

		return '';
	}

	/**
	 * Format a timestamp for display.
	 *
	 * @param string|int $date Timestamp or MySQL datetime.
	 * @param bool       $mysql Whether $date is a MySQL datetime.
	 *
	 * @return string
	 */
	public static function format_date( $date, $mysql = false ) {
		if ( $mysql ) {
			return mysql2date( sprintf( __( '%1$s @ %2$s', 'wp-useronline' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $date, true );
		}

		return date_i18n( sprintf( __( '%1$s @ %2$s', 'wp-useronline' ), get_option( 'date_format' ), get_option( 'time_format' ) ), $date );
	}

	/**
	 * Format a user's display name.
	 *
	 * @param object $user Useronline row.
	 *
	 * @return string
	 */
	public static function format_name( $user ) {
		return apply_filters( 'useronline_display_user', esc_html( $user->user_name ), $user );
	}

	/**
	 * Apply the naming convention for a count.
	 *
	 * @param int          $count How many.
	 * @param string       $user_type One of user, member, guest or bot.
	 * @param string|false $template Optional template to substitute into.
	 *
	 * @return string
	 */
	public static function format_count( $count, $user_type, $template = false ) {
		$i      = ( $count == 1 ) ? '' : 's';
		$string = UserOnline_Core::$options->naming[ $user_type . $i ];

		$output = str_ireplace( '%COUNT%', number_format_i18n( $count ), $string );

		if ( false === $template ) {
			return $output;
		}

		return str_ireplace( '%USERS%', $output, $template );
	}

	/**
	 * Build the most-ever-online sentence.
	 *
	 * @return string
	 */
	public static function format_most_users() {
		return sprintf(
			__( 'Most users ever online were <strong>%1$s</strong>, on <strong>%2$s</strong>', 'wp-useronline' ),
			number_format_i18n( get_most_users_online() ),
			get_most_users_online_date()
		);
	}

	/**
	 * Count users in each bucket.
	 *
	 * @param array $buckets Users grouped by type.
	 *
	 * @return array
	 */
	public static function get_counts( $buckets ) {
		$counts = array();
		$total  = 0;
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			$count  = isset( $buckets[ $user_type ] ) ? count( $buckets[ $user_type ] ) : 0;
			$total += $counts[ $user_type ] = $count;
		}

		$counts['user'] = $total;

		return $counts;
	}
}
