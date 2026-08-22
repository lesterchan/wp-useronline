<?php
/**
 * WP-UserOnline class-wp-useronline-recorder.php
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * Writes the current visitor into the useronline table.
 *
 * @since 3.0.0
 */
class WP_UserOnline_Recorder {

	/**
	 * Cached count of users online for this request.
	 *
	 * @var int|null
	 */
	private static $count;

	/**
	 * Get the number of users currently online.
	 *
	 * @return int
	 */
	public static function count() {
		global $wpdb;

		if ( null === self::$count ) {
			self::$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->useronline}" );
		}

		return self::$count;
	}

	/**
	 * Record the current visitor as online.
	 *
	 * @param string $page_url   Site-relative URL. Defaults to REQUEST_URI.
	 * @param string $page_title Page title. Defaults to the generated title.
	 *
	 * @return void
	 */
	public static function record( $page_url = '', $page_title = '' ) {
		global $wpdb;

		require_once __DIR__ . '/bots.php';

		if ( '' === $page_url ) {
			// Through local_path() for the same reason local_url() is: a request
			// for "//evil.com/" arrives here in REQUEST_URI verbatim, and the
			// column it lands in is rendered as an href.
			// sanitize_text_field() inline so the sniff can see it; local_path()
			// then does the part that matters, which is making the path unable to
			// leave the site.
			$page_url = isset( $_SERVER['REQUEST_URI'] )
				? self::local_path( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) )
				: '';
		}

		if ( '' === $page_title ) {
			$page_title = wp_strip_all_tags( self::get_title() );
		}

		$referral = isset( $_SERVER['HTTP_REFERER'] )
			? wp_strip_all_tags( wp_unslash( $_SERVER['HTTP_REFERER'] ) )
			: '';

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? wp_strip_all_tags( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$user_ip = self::get_ip();

		$user = self::identify( $user_agent );

		$timeout   = (int) WP_UserOnline_Options::get( 'timeout' );
		$timestamp = current_time( 'mysql' );

		// Drop this visitor's previous row, plus anything that has timed out.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->useronline}
				WHERE ( user_id <> 0 AND user_id = %d )
				OR ( user_id = 0 AND user_agent = %s AND user_ip = %s )
				OR ( timestamp < DATE_SUB( %s, INTERVAL %d SECOND ) )",
				$user['user_id'],
				$user_agent,
				$user_ip,
				$timestamp,
				$timeout
			)
		);

		// Every value taken from a superglobal was unslashed where it was read,
		// so there is deliberately no stripslashes_deep() over the row here.
		$wpdb->replace(
			$wpdb->useronline,
			array(
				'timestamp'  => $timestamp,
				'user_type'  => $user['user_type'],
				'user_id'    => $user['user_id'],
				'user_name'  => $user['user_name'],
				'user_ip'    => $user_ip,
				'user_agent' => $user_agent,
				'page_title' => $page_title,
				'page_url'   => $page_url,
				'referral'   => $referral,
			)
		);

		self::prune_address( $user_ip );

		// The table just changed, so anything already fetched this request is
		// stale.
		self::$count = null;
		WP_UserOnline_Template::flush_cache();

		$online = self::count();

		if ( $online > (int) WP_UserOnline_Options::most( 'count' ) ) {
			WP_UserOnline_Options::update_most( $online, time() );
		}
	}

	/**
	 * Keep one address from filling the table.
	 *
	 * A guest's identity is their user agent *and* their address, so the
	 * delete-then-insert above collapses repeat visits only while the user agent
	 * stays the same. Varying it on each request defeats that, and the table's
	 * unique key on ( timestamp, user_type, user_ip ) is then the only thing
	 * left -- one row per second per type, which over a five-minute timeout is
	 * six hundred rows from a single host, each of them counted as somebody
	 * online and each able to push the all-time figure up. That figure has no
	 * reset anywhere: no screen, no command, no filter.
	 *
	 * The identity is left alone. Collapsing guests onto the address instead
	 * would bound this too, and would also count an office or a mobile carrier
	 * behind one address as a single visitor, which is the wrong answer to a
	 * question this plugin exists to answer. A ceiling per address keeps honest
	 * sharing working and takes the flood away.
	 *
	 * @since 4.0.0
	 *
	 * @param string $user_ip The address that has just been recorded.
	 *
	 * @return void
	 */
	private static function prune_address( $user_ip ) {
		global $wpdb;

		if ( '' === $user_ip || 'unknown' === $user_ip ) {
			return;
		}

		/**
		 * Filters how many simultaneous visitors one address may account for.
		 *
		 * Raise it for a site whose audience really does arrive from behind one
		 * address in numbers; zero switches the ceiling off.
		 *
		 * @since 4.0.0
		 *
		 * @param int    $max     Rows kept per address.
		 * @param string $user_ip The address being recorded.
		 */
		$max = (int) apply_filters( 'wp_useronline_max_per_address', 20, $user_ip );

		if ( $max <= 0 ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- The plugin's own table, and a count that has to be of the rows as they are this instant rather than as a cache last saw them.
		$rows = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->useronline} WHERE user_ip = %s", $user_ip ) );

		if ( $rows <= $max ) {
			return;
		}

		/*
		 * Oldest first, and never the row just written -- the visitor doing the
		 * recording is the one entitled to be in the list.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- As above; deleting rows is the point and there is nothing to cache.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->useronline} WHERE user_ip = %s ORDER BY timestamp ASC LIMIT %d",
				$user_ip,
				$rows - $max
			)
		);
	}

	/**
	 * Work out who the current visitor is.
	 *
	 * @param string $user_agent The visitor's user agent.
	 *
	 * @return array{user_id:int,user_name:string,user_type:string}
	 */
	private static function identify( $user_agent ) {
		foreach ( wp_useronline_get_bots() as $name => $needle ) {
			if ( false !== stristr( $user_agent, $needle ) ) {
				return array(
					'user_id'   => 0,
					'user_name' => $name,
					'user_type' => 'bot',
				);
			}
		}

		$current_user = wp_get_current_user();

		if ( $current_user->ID ) {
			return array(
				'user_id'   => (int) $current_user->ID,
				'user_name' => $current_user->display_name,
				'user_type' => 'member',
			);
		}

		$cookie = 'comment_author_' . COOKIEHASH;

		if ( ! empty( $_COOKIE[ $cookie ] ) ) {
			return array(
				'user_id'   => 0,
				'user_name' => trim( wp_strip_all_tags( wp_unslash( $_COOKIE[ $cookie ] ) ) ),
				'user_type' => 'guest',
			);
		}

		return array(
			'user_id'   => 0,
			'user_name' => __( 'Guest', 'wp-useronline' ),
			'user_type' => 'guest',
		);
	}

	/**
	 * Build the title recorded for the current request.
	 *
	 * @return string
	 */
	private static function get_title() {
		if ( is_admin() && function_exists( 'get_admin_page_title' ) ) {
			$page_title = ' &raquo; ' . __( 'Admin', 'wp-useronline' ) . ' &raquo; ' . get_admin_page_title();
		} else {
			$page_title = wp_get_document_title();

			if ( '' === $page_title ) {
				$request_uri = isset( $_SERVER['REQUEST_URI'] )
					? wp_strip_all_tags( wp_unslash( $_SERVER['REQUEST_URI'] ) )
					: '';
				$page_title  = ' &raquo; ' . $request_uri;
			} else {
				return $page_title;
			}
		}

		return get_bloginfo( 'name' ) . $page_title;
	}

	/**
	 * The forwarding headers the trust_proxy opt-in consults, in order.
	 *
	 * The same list, in the same order, as wp-ban, wp-polls, wp-postratings and
	 * wp-email. This plugin used to trust HTTP_X_FORWARDED_FOR alone, so a
	 * Cloudflare site that opted in got the proxy's own address recorded for
	 * every visitor while its four siblings resolved the visitor correctly --
	 * one setting, described in the same words on five screens, doing something
	 * different on one of them.
	 *
	 * Trusting whichever of seven happens to be present is the blunt instrument
	 * and the settings screen says so: naming the one header your proxy sets and
	 * overwrites is the answer, and this is the fallback for owners who do not
	 * know which that is.
	 *
	 * @var string[]
	 */
	private const PROXY_HEADERS = array(
		'HTTP_CF_CONNECTING_IP',
		'HTTP_CLIENT_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_FORWARDED',
		'HTTP_X_CLUSTER_CLIENT_IP',
		'HTTP_FORWARDED_FOR',
		'HTTP_FORWARDED',
	);

	/**
	 * Determine the visitor's IP address.
	 *
	 * Forwarding headers are set by the client and can say anything at all, so
	 * they are only consulted when the site says which one to read, or declares
	 * that it sits behind a trusted proxy. Otherwise a visitor could forge an
	 * address on every request, which both falsifies the log and defeats the
	 * de-duplication in record().
	 *
	 * @return string Validated IP, or an empty string when none can be trusted.
	 */
	private static function get_ip() {
		$headers = array( 'REMOTE_ADDR' );

		/**
		 * Filter whether the usual proxy headers may be trusted.
		 *
		 * Renamed from useronline_trust_proxy in 4.0.0; the old name is gone.
		 *
		 * @since 4.0.0
		 *
		 * @param bool $trust Defaults to the WP_USERONLINE_TRUST_PROXY constant.
		 */
		$trust_proxy = apply_filters(
			'wp_useronline_trust_proxy',
			defined( 'WP_USERONLINE_TRUST_PROXY' ) && WP_USERONLINE_TRUST_PROXY
		);

		if ( $trust_proxy ) {
			$headers = array_merge( self::PROXY_HEADERS, $headers );
		}

		/*
		 * The named header goes on last, so it ends up first and outranks both
		 * of the above. Order is the whole of this: a site that names the header
		 * its own proxy sets and overwrites has given the precise answer, and
		 * the trust_proxy list is the blunt one that takes whichever of seven
		 * headers is present, whoever set it. Unshifting the name before the
		 * trust_proxy block -- which reads as the natural place for it --
		 * silently demotes the precise answer beneath the blunt one on every
		 * site that has both.
		 *
		 * Same field, same label and same description as wp-ban, wp-polls,
		 * wp-postratings and wp-email, so a site owner meets one setting rather
		 * than five.
		 */
		$named = (string) WP_UserOnline_Options::get( 'ip_header' );

		if ( '' !== $named ) {
			array_unshift( $headers, $named );
		}

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$forwarded = wp_strip_all_tags( wp_unslash( $_SERVER[ $header ] ) );

			list( $ip_address ) = explode( ',', $forwarded );

			$ip_address = filter_var( trim( $ip_address ), FILTER_VALIDATE_IP );

			if ( false !== $ip_address ) {
				return $ip_address;
			}
		}

		return '';
	}

	/**
	 * Reduce a client-submitted absolute URL to a site-relative path.
	 *
	 * The caller chooses this value, so anything not belonging to this site is
	 * rejected outright. The path is kept whole rather than having the site URL
	 * cut off it, so that it matches what record() stores from REQUEST_URI on a
	 * subdirectory install and the browsing-page lookup can find those rows.
	 *
	 * @param string $url Absolute URL submitted by the client.
	 *
	 * @return string|null Site-relative path, or null when the URL is foreign.
	 */
	public static function local_url( $url ) {
		$url = trim( (string) $url );

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

		$path = ! empty( $parts['path'] ) ? $parts['path'] : '/';

		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		// page_url is a varchar( 255 ).
		return mb_substr( self::local_path( $path ), 0, 255 );
	}

	/**
	 * Reduce a site-relative path to one that cannot leave the site.
	 *
	 * The host check above is not enough on its own, and the gap is small and
	 * complete: `https://example.com//evil.com/` parses with the *site's* host
	 * and a path of `//evil.com/`. That passed the host test, was stored, and
	 * reached `esc_url()` -- which short-circuits its entire protocol check on a
	 * leading slash, so `<a href="//evil.com/">` was emitted intact. A
	 * protocol-relative URL is an absolute one; only the scheme is borrowed.
	 *
	 * Collapsing the run of leading slashes to a single one turns it back into
	 * what it claimed to be, a path on this site, and leaves every ordinary path
	 * untouched.
	 *
	 * Applied to REQUEST_URI as well as to a submitted URL, because a request
	 * for `https://example.com//evil.com/` reaches the 404 template with exactly
	 * that in REQUEST_URI and records the same row with no endpoint involved.
	 *
	 * @since 4.0.0
	 *
	 * @param string $path Site-relative path, possibly with a query string.
	 *
	 * @return string
	 */
	public static function local_path( $path ) {
		$path = wp_strip_all_tags( (string) $path );

		// Backslashes first: some clients and some servers treat them as
		// separators, so "/\evil.com" is the same trick in another spelling.
		$path = str_replace( '\\', '/', $path );

		if ( '' === $path ) {
			return '/';
		}

		if ( '/' === $path[0] ) {
			return '/' . ltrim( $path, '/' );
		}

		return $path;
	}
}
