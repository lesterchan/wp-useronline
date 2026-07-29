<?php
/**
 * WP-UserOnline class-wp-useronline-template.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Formatting helpers behind the users online template tags.
 *
 * @since 3.0.0
 */
class WP_UserOnline_Template {

	/**
	 * Per-request cache of query results, keyed by type and page URL.
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Whether anything on this request needs the refresh script.
	 *
	 * @var bool
	 */
	private static $needs_script = false;

	/**
	 * Whether the refresh script should be enqueued.
	 *
	 * @return bool
	 */
	public static function needs_script() {
		return self::$needs_script;
	}

	/**
	 * Ask for the refresh script on this request.
	 *
	 * Anything emitting a container the script targets must call this, not just
	 * the list builders below. A widget showing only the count renders
	 * #useronline-count without going through compact_list(), and before 3.0.0
	 * that meant the script was never enqueued and the number never updated.
	 *
	 * @return void
	 */
	public static function request_script() {
		self::$needs_script = true;
	}

	/**
	 * Drop the per-request query cache.
	 *
	 * Called whenever the table is written to, so that anything rendered later
	 * in the same request reflects the change. Before 3.0.0 the detailed page
	 * ran its own query and could not go stale; it now shares this cache with
	 * the compact lists, so the invalidation has to be explicit.
	 *
	 * @return void
	 */
	public static function flush_cache() {
		self::$cache = array();
	}

	/**
	 * Fetch the users online rows for a type.
	 *
	 * @param string $type     Either 'site' or 'page'.
	 * @param string $page_url Page URL when $type is 'page'.
	 *
	 * @return array
	 */
	private static function query( $type, $page_url ) {
		global $wpdb;

		$key = $type . '|' . $page_url;

		if ( isset( self::$cache[ $key ] ) ) {
			return self::$cache[ $key ];
		}

		if ( 'page' === $type ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->useronline} WHERE page_url = %s ORDER BY timestamp DESC",
					$page_url
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->useronline} ORDER BY timestamp DESC" );
		}

		self::$cache[ $key ] = $rows;

		return $rows;
	}

	/**
	 * Build the compact browsing list for a type.
	 *
	 * @param string $type     Either 'site' or 'page'.
	 * @param string $output   One of 'html', 'list', 'buckets' or 'counts'.
	 * @param string $page_url Page URL when $type is 'page'.
	 *
	 * @return mixed
	 */
	public static function compact_list( $type, $output = 'html', $page_url = '' ) {
		self::request_script();

		if ( 'page' === $type && '' === $page_url ) {
			$page_url = isset( $_SERVER['REQUEST_URI'] )
				? wp_strip_all_tags( wp_unslash( $_SERVER['REQUEST_URI'] ) )
				: '';
		}

		$users = self::query( $type, $page_url );

		if ( 'list' === $output ) {
			return $users;
		}

		$buckets = array();
		foreach ( $users as $user ) {
			$buckets[ $user->user_type ][] = $user;
		}

		if ( 'buckets' === $output ) {
			return $buckets;
		}

		$counts = self::get_counts( $buckets );

		if ( 'counts' === $output ) {
			return $counts;
		}

		$naming   = WP_UserOnline_Options::get( 'naming' );
		$template = WP_UserOnline_Options::get( 'templates' )[ 'browsing' . $type ];

		$out = self::format_count( $counts['user'], 'user', $template['text'] );

		// Member names.
		$member_names = '';
		if ( ! empty( $buckets['member'] ) ) {
			$names = array();
			foreach ( $buckets['member'] as $member ) {
				$names[] = self::format_name( $member );
			}
			$member_names = implode( $template['separators']['members'], $names );
		}
		$out = str_ireplace( '%MEMBER_NAMES%', $member_names, $out );

		// Per type counts.
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( $counts[ $user_type ] > 1 ) {
				$number = str_ireplace( '%COUNT%', number_format_i18n( $counts[ $user_type ] ), $naming[ $user_type . 's' ] );
			} elseif ( 1 === $counts[ $user_type ] ) {
				$number = $naming[ $user_type ];
			} else {
				$number = '';
			}

			$out = str_ireplace( "%{$user_type}S%", $number, $out );
		}

		// Separators.
		$separator = ( $counts['member'] && $counts['guest'] ) ? $template['separators']['guests'] : '';
		$out       = str_ireplace( '%GUESTS_SEPARATOR%', $separator, $out );

		$separator = ( ( $counts['guest'] || $counts['member'] ) && $counts['bot'] ) ? $template['separators']['bots'] : '';
		$out       = str_ireplace( '%BOTS_SEPARATOR%', $separator, $out );

		return $out;
	}

	/**
	 * Build the detailed per-user list.
	 *
	 * @param array $counts       Counts per user type.
	 * @param array $user_buckets Users grouped by type.
	 * @param array $nicetexts    Formatted counts per type.
	 *
	 * @return string
	 */
	public static function detailed_list( $counts, $user_buckets, $nicetexts ) {
		self::request_script();

		if ( 0 === $counts['user'] ) {
			return '<h2>' . esc_html__( 'No one is online now.', 'wp-useronline' ) . '</h2>';
		}

		$label_on       = __( 'on', 'wp-useronline' );
		$label_url      = __( 'url', 'wp-useronline' );
		$label_referral = __( 'referral', 'wp-useronline' );

		$can_see_all = current_user_can( 'edit_users' );

		$out = '';
		foreach ( array( 'member', 'guest', 'bot' ) as $user_type ) {
			if ( empty( $counts[ $user_type ] ) ) {
				continue;
			}

			$out .= '<h2>' . $nicetexts[ $user_type ] . ' ' . esc_html__( 'Online Now', 'wp-useronline' ) . '</h2>';

			$i = 1;
			foreach ( $user_buckets[ $user_type ] as $user ) {
				$nr      = number_format_i18n( $i++ );
				$name    = self::format_name( $user );
				$user_ip = self::format_ip( $user );
				$date    = self::format_date( $user->timestamp, true );

				// Reset per user, so that a user whose location is hidden below
				// doesn't inherit the previous user's page details.
				$page_title    = '';
				$current_link  = '';
				$referral_link = '';

				if ( $can_see_all || false === strpos( $user->page_url, 'wp-admin' ) ) {
					$page_title    = esc_html( $user->page_title );
					$current_link  = self::format_link( $user->page_url, $label_url );
					$referral_link = self::format_link( $user->referral, $label_referral );
				}

				$markup = sprintf(
					'<p><strong>#%1$s - %2$s</strong> %3$s %4$s %5$s<br/>%6$s %7$s %8$s</p>' . "\n",
					$nr,
					$name,
					$user_ip,
					esc_html( $label_on ),
					esc_html( $date ),
					$page_title,
					$current_link,
					$referral_link
				);

				/**
				 * Filter the markup for a single user on the detailed list.
				 *
				 * The default value is already escaped. $user carries the raw,
				 * visitor-controlled database row ( page_title, page_url,
				 * referral and user_agent are all attacker-supplied ), so
				 * anything rebuilt from it must be escaped again.
				 *
				 * Renamed from useronline_custom_template in 4.0.0; the old
				 * name is gone.
				 *
				 * @since 4.0.0
				 *
				 * @param string $markup Escaped markup for this user.
				 * @param string $nr     Formatted position in the list.
				 * @param object $user   Raw useronline row. Unescaped.
				 */
				$out .= apply_filters( 'wp_useronline_custom_template', $markup, $nr, $user );
			}
		}

		return $out;
	}

	/**
	 * Wrap a URL in a bracketed link.
	 *
	 * @param string $url   Target URL.
	 * @param string $title Link text.
	 *
	 * @return string
	 */
	public static function format_link( $url, $title ) {
		if ( empty( $url ) ) {
			return '';
		}

		return '[<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>]';
	}

	/**
	 * Format a user's IP as a whois link, for viewers allowed to see it.
	 *
	 * @param object $user Useronline row.
	 *
	 * @return string
	 */
	public static function format_ip( $user ) {
		$ip = $user->user_ip;

		if ( '' === $ip || 'unknown' === $ip || ! current_user_can( 'edit_users' ) ) {
			return '';
		}

		return sprintf(
			'<span dir="ltr"><a href="%1$s" title="%2$s">%3$s</a></span>',
			esc_url( 'https://whois.domaintools.com/' . rawurlencode( $ip ) ),
			esc_attr( $user->user_agent ),
			esc_html( $ip )
		);
	}

	/**
	 * Format a timestamp for display.
	 *
	 * @param string|int $date  Timestamp or MySQL datetime.
	 * @param bool       $mysql Whether $date is a MySQL datetime.
	 *
	 * @return string
	 */
	public static function format_date( $date, $mysql = false ) {
		/* translators: 1: date format, 2: time format. */
		$format = sprintf( __( '%1$s @ %2$s', 'wp-useronline' ), get_option( 'date_format' ), get_option( 'time_format' ) );

		if ( $mysql ) {
			return mysql2date( $format, $date, true );
		}

		return date_i18n( $format, $date );
	}

	/**
	 * Format a user's display name.
	 *
	 * @param object $user Useronline row.
	 *
	 * @return string
	 */
	public static function format_name( $user ) {
		/**
		 * Filter a user's displayed name on the users online lists.
		 *
		 * Renamed from useronline_display_user in 4.0.0; the old name is gone.
		 *
		 * @since 4.0.0
		 *
		 * @param string $name Escaped display name.
		 * @param object $user Raw useronline row. Unescaped.
		 */
		return apply_filters( 'wp_useronline_display_user', esc_html( $user->user_name ), $user );
	}

	/**
	 * Apply the naming convention for a count.
	 *
	 * @param int          $count     How many.
	 * @param string       $user_type One of user, member, guest or bot.
	 * @param string|false $template  Optional template to substitute into.
	 *
	 * @return string
	 */
	public static function format_count( $count, $user_type, $template = false ) {
		$naming = WP_UserOnline_Options::get( 'naming' );
		$key    = ( 1 === (int) $count ) ? $user_type : $user_type . 's';

		$out = str_ireplace( '%COUNT%', number_format_i18n( $count ), $naming[ $key ] );

		if ( false === $template ) {
			return $out;
		}

		return str_ireplace( '%USERS%', $out, $template );
	}

	/**
	 * Build the most-ever-online sentence.
	 *
	 * @return string
	 */
	public static function format_most_users() {
		return sprintf(
			/* translators: 1: number of users, 2: date. */
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
			$count                = isset( $buckets[ $user_type ] ) ? count( $buckets[ $user_type ] ) : 0;
			$counts[ $user_type ] = $count;
			$total               += $count;
		}

		$counts['user'] = $total;

		return $counts;
	}
}
