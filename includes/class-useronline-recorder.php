<?php
/**
 * WP-UserOnline class-useronline-recorder.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes the current visitor into the useronline table.
 *
 * @since 3.0.0
 */
class UserOnline_Recorder {

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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
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

		require_once __DIR__ . '/../bots.php';

		if ( '' === $page_url ) {
			$page_url = isset( $_SERVER['REQUEST_URI'] )
				? wp_strip_all_tags( wp_unslash( $_SERVER['REQUEST_URI'] ) )
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

		$timeout   = (int) UserOnline_Options::get( 'timeout' );
		$timestamp = current_time( 'mysql' );

		// Drop this visitor's previous row, plus anything that has timed out.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
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
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
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

		// The table just changed, so anything already fetched this request is
		// stale.
		self::$count = null;
		UserOnline_Template::flush_cache();

		$online = self::count();

		if ( $online > (int) UserOnline_Options::most( 'count' ) ) {
			UserOnline_Options::update_most( $online, time() );
		}
	}

	/**
	 * Work out who the current visitor is.
	 *
	 * @param string $user_agent The visitor's user agent.
	 *
	 * @return array{user_id:int,user_name:string,user_type:string}
	 */
	private static function identify( $user_agent ) {
		foreach ( useronline_get_bots() as $name => $needle ) {
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
				$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
				$page_title  = ' &raquo; ' . wp_strip_all_tags( $request_uri );
			} else {
				return $page_title;
			}
		}

		return get_bloginfo( 'name' ) . $page_title;
	}

	/**
	 * Determine the visitor's IP address.
	 *
	 * X-Forwarded-For is set by the client and can say anything at all, so it
	 * is only consulted when the site declares that it sits behind a trusted
	 * proxy. Otherwise a visitor could forge an address on every request, which
	 * both falsifies the log and defeats the de-duplication in record().
	 *
	 * @return string Validated IP, or an empty string when none can be trusted.
	 */
	private static function get_ip() {
		$headers = array( 'REMOTE_ADDR' );

		/**
		 * Filter whether X-Forwarded-For may be trusted.
		 *
		 * @param bool $trust Defaults to the USERONLINE_TRUST_PROXY constant.
		 */
		$trust_proxy = apply_filters(
			'useronline_trust_proxy',
			defined( 'USERONLINE_TRUST_PROXY' ) && USERONLINE_TRUST_PROXY
		);

		if ( $trust_proxy ) {
			array_unshift( $headers, 'HTTP_X_FORWARDED_FOR' );
		}

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			list( $ip_address ) = explode( ',', wp_unslash( $_SERVER[ $header ] ) );

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
		return mb_substr( wp_strip_all_tags( $path ), 0, 255 );
	}
}
