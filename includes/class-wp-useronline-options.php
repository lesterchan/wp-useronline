<?php
/**
 * WP-UserOnline class-wp-useronline-options.php
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, writes, sanitizes and migrates the plugin's settings.
 *
 * Three rows, each with one job: wp_useronline_options for everything a site
 * owner can change, wp_useronline_version for the two upgrade markers, and
 * wp_useronline_most for the accumulating most-ever-online record, which is
 * data rather than a setting and is deliberately not autoloaded.
 *
 * The markers used to live inside the settings array, under a reserved
 * 'versions' key. The settings form never posts them, so every save had to
 * rescue them from the stored value by hand -- fourteen lines of plumbing whose
 * only purpose was to undo the damage the arrangement caused, and which still
 * shipped the bug recorded in the 3.0.0 changelog: the marker could not be
 * saved at all once the settings screen had been loaded, so the migration and
 * the table check re-ran on every single request. With the markers in a row of
 * their own that failure is impossible by construction and the plumbing is
 * gone: nothing in this row is hidden from the screen any more.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Options {

	/**
	 * Option holding the plugin settings.
	 */
	const OPTION = 'wp_useronline_options';

	/**
	 * Option holding the 'plugin' and 'db' upgrade markers, and nothing else.
	 */
	const VERSION = 'wp_useronline_version';

	/**
	 * Option holding the most-ever-online record. Not autoloaded.
	 */
	const MOST = 'wp_useronline_most';

	/**
	 * The row the settings lived in before they were renamed.
	 *
	 * @var string
	 */
	private static $renamed_from = 'useronline';

	/**
	 * The row the most-ever-online record lived in before it was renamed.
	 *
	 * @var string
	 */
	private static $most_renamed_from = 'useronline_most';

	/**
	 * The unprefixed row this plugin shared with WP-Stats and five others.
	 *
	 * It was never any one plugin's to own: whichever of the seven saved the
	 * WP-Stats screen last wrote the whole row. Each plugin keeps its own copy
	 * now, so the migration folds it in and deletes it -- and, unlike the rows
	 * above, it is deliberately absent from all_option_names(), because
	 * uninstalling one plugin must not clear a setting that a sibling which has
	 * not upgraded yet is still reading.
	 *
	 * @var string
	 */
	private static $shared_option = 'stats_display';

	/**
	 * The rows the 4.0.0 migration renames and then deletes.
	 *
	 * @return array
	 */
	private static function legacy_options() {
		return array( self::$renamed_from, self::$most_renamed_from );
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'timeout'       => 300,
			// Blank means REMOTE_ADDR, which is the only address the web server
			// vouches for. See WP_UserOnline_Recorder::get_ip().
			'ip_header'     => '',
			'url'           => trailingslashit( home_url() ) . 'useronline',
			'names'         => 0,
			// The plugin's half of the WP-Stats contract. Its own setting now,
			// not a slice of a row seven plugins wrote to at once.
			'stats_display' => true,
			'naming'        => array(
				'user'    => __( '1 User', 'wp-useronline' ),
				'users'   => __( '%COUNT% Users', 'wp-useronline' ),
				'member'  => __( '1 Member', 'wp-useronline' ),
				'members' => __( '%COUNT% Members', 'wp-useronline' ),
				'guest'   => __( '1 Guest', 'wp-useronline' ),
				'guests'  => __( '%COUNT% Guests', 'wp-useronline' ),
				'bot'     => __( '1 Bot', 'wp-useronline' ),
				'bots'    => __( '%COUNT% Bots', 'wp-useronline' ),
			),
			'templates'     => array(
				'useronline'   => '<a href="%PAGE_URL%"><strong>%USERS%</strong> ' . __( 'Online', 'wp-useronline' ) . '</a>',
				'browsingsite' => array(
					'separators' => array(
						'members' => __( ',', 'wp-useronline' ) . ' ',
						'guests'  => __( ',', 'wp-useronline' ) . ' ',
						'bots'    => __( ',', 'wp-useronline' ) . ' ',
					),
					'text'       => _x( 'Users', 'Template Element', 'wp-useronline' ) . ': <strong>%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%</strong>',
				),
				'browsingpage' => array(
					'separators' => array(
						'members' => __( ',', 'wp-useronline' ) . ' ',
						'guests'  => __( ',', 'wp-useronline' ) . ' ',
						'bots'    => __( ',', 'wp-useronline' ) . ' ',
					),
					'text'       => '<strong>%USERS%</strong> ' . __( 'Browsing This Page.', 'wp-useronline' ) . '<br />' . _x( 'Users', 'Template Element', 'wp-useronline' ) . ': <strong>%MEMBER_NAMES%%GUESTS_SEPARATOR%%GUESTS%%BOTS_SEPARATOR%%BOTS%</strong>',
				),
			),
		);
	}

	/**
	 * Get the settings, or a single top level setting.
	 *
	 * Merges over the defaults so a missing key never has to be guarded at the
	 * call site.
	 *
	 * @param string|null $key Setting to return, or null for all of them.
	 *
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$stored = get_option( self::OPTION, array() );
		$data   = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		if ( null === $key ) {
			return $data;
		}

		return isset( $data[ $key ] ) ? $data[ $key ] : null;
	}

	/**
	 * Store the settings.
	 *
	 * @param array $options Settings to store.
	 *
	 * @return void
	 */
	public static function update( array $options ) {
		update_option( self::OPTION, $options );
	}

	/**
	 * The two upgrade markers, normalised.
	 *
	 * Always exactly 'plugin' and 'db', whatever is actually in the row, so a
	 * caller never has to guard either key.
	 *
	 * @return array
	 */
	public static function markers() {
		$stored = get_option( self::VERSION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Record that this version's upgrade has finished.
	 *
	 * One write, both markers, at the end of the upgrade routine, so a
	 * half-finished upgrade never records itself as complete.
	 *
	 * @return void
	 */
	public static function update_markers() {
		update_option(
			self::VERSION,
			array(
				'plugin' => WP_USERONLINE_VERSION,
				'db'     => WP_USERONLINE_DB_VERSION,
			)
		);
	}

	/**
	 * Get the most-ever-online record.
	 *
	 * @param string|null $key Either 'count', 'date', or null for both.
	 *
	 * @return mixed
	 */
	public static function most( $key = null ) {
		$stored = get_option( self::MOST, array() );
		$data   = array_merge(
			array(
				'count' => 1,
				'date'  => time(),
			),
			is_array( $stored ) ? $stored : array()
		);

		if ( null === $key ) {
			return $data;
		}

		return isset( $data[ $key ] ) ? $data[ $key ] : null;
	}

	/**
	 * Store the most-ever-online record.
	 *
	 * Written with autoload off: it is accumulating data rather than a setting,
	 * it changes on any request that sets a new record, and nothing outside the
	 * template tags ever reads it.
	 *
	 * @param int $count Number of users online.
	 * @param int $date  Unix timestamp the record was set.
	 *
	 * @return void
	 */
	public static function update_most( $count, $date ) {
		update_option(
			self::MOST,
			array(
				'count' => (int) $count,
				'date'  => (int) $date,
			),
			false
		);
	}

	/**
	 * Sanitize a set of settings.
	 *
	 * Naming conventions and templates are echoed verbatim by the template
	 * tags, so they are sanitized on the way in rather than on the way out.
	 * Missing or malformed keys fall back to the defaults instead of warning,
	 * which also means a partial submission cannot leave nested template keys
	 * absent for the renderer.
	 *
	 * Registered as the sanitize_callback for the setting, so the Settings API
	 * runs it on every save.
	 *
	 * **The submitted subset is merged over the stored value first, and that is
	 * load bearing.** register_setting() hands a sanitize_callback only the
	 * fields the submitting form actually posted, and this row is written by
	 * three tabs of one screen. A sanitiser that returned just what it was given
	 * would blank whatever the other tabs own the moment any one of them saved:
	 * change the timeout, lose six customised templates, with no error and
	 * nothing to say it happened. So the stored value is the starting point and
	 * the submission is laid over it.
	 *
	 * Merged rather than patched in afterwards, so everything that survives is
	 * re-sanitised too -- which is what lets the 4.0.0 migration hand its whole
	 * legacy array to this same function and get clean settings back.
	 *
	 * @param mixed $options Submitted settings, which may be one tab's worth.
	 *
	 * @return array
	 */
	public static function sanitize( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = self::defaults();

		$stored  = get_option( self::OPTION, array() );
		$options = array_replace_recursive( is_array( $stored ) ? $stored : array(), $options );

		$clean                  = array();
		$clean['timeout']       = isset( $options['timeout'] ) ? absint( $options['timeout'] ) : $defaults['timeout'];
		$clean['url']           = ! empty( $options['url'] ) ? esc_url_raw( trim( $options['url'] ) ) : '';
		$clean['names']         = empty( $options['names'] ) ? 0 : 1;
		$clean['stats_display'] = ! empty( $options['stats_display'] );

		/*
		 * A header name, not a value: uppercased and restricted to the character
		 * set PHP builds $_SERVER keys from, so nothing that could not be a key
		 * can be stored and every lookup is a plain array read. Anything else
		 * becomes blank, which means "use REMOTE_ADDR" rather than "look up a key
		 * that cannot exist" -- a typo should fall back to the trustworthy
		 * address, not to no address at all.
		 */
		$header             = isset( $options['ip_header'] ) ? sanitize_text_field( (string) $options['ip_header'] ) : '';
		$clean['ip_header'] = preg_match( '/^[A-Za-z0-9_]+$/', $header ) ? strtoupper( $header ) : '';

		// A timeout of zero would purge every row on the next request.
		if ( 0 === $clean['timeout'] ) {
			$clean['timeout'] = $defaults['timeout'];
		}

		// Naming: fill gaps from the defaults, then sanitize every entry.
		$naming = isset( $options['naming'] ) && is_array( $options['naming'] ) ? $options['naming'] : array();
		$naming = array_merge( $defaults['naming'], $naming );

		foreach ( $naming as $key => $value ) {
			$naming[ $key ] = wp_kses_post( trim( (string) $value ) );
		}
		$clean['naming'] = $naming;

		// Templates: rebuilt from the defaults so the shape is guaranteed.
		$templates          = isset( $options['templates'] ) && is_array( $options['templates'] ) ? $options['templates'] : array();
		$clean['templates'] = array();

		foreach ( $defaults['templates'] as $key => $default_template ) {
			if ( ! is_array( $default_template ) ) {
				$stored                     = isset( $templates[ $key ] ) && ! is_array( $templates[ $key ] ) ? $templates[ $key ] : $default_template;
				$clean['templates'][ $key ] = wp_kses_post( trim( (string) $stored ) );
				continue;
			}

			$stored = isset( $templates[ $key ] ) && is_array( $templates[ $key ] ) ? $templates[ $key ] : array();
			$text   = isset( $stored['text'] ) && ! is_array( $stored['text'] ) ? $stored['text'] : $default_template['text'];

			$clean['templates'][ $key ] = array(
				'text'       => wp_kses_post( trim( (string) $text ) ),
				'separators' => array(),
			);

			$stored_separators = isset( $stored['separators'] ) && is_array( $stored['separators'] ) ? $stored['separators'] : array();

			foreach ( $default_template['separators'] as $separator_key => $separator_default ) {
				$separator = isset( $stored_separators[ $separator_key ] ) && ! is_array( $stored_separators[ $separator_key ] )
					? $stored_separators[ $separator_key ]
					: $separator_default;

				// Not trimmed: the defaults are ", " and that trailing space is
				// what keeps names apart in the rendered list.
				$clean['templates'][ $key ]['separators'][ $separator_key ] = wp_kses_post( (string) $separator );
			}
		}

		return $clean;
	}

	/**
	 * Fold every pre-4.0.0 option row into the three rows the plugin now owns.
	 *
	 * The settings row is renamed from 'useronline', the record row from
	 * 'useronline_most', and the reserved 'versions' key inside the settings is
	 * dropped -- the markers live in wp_useronline_version now. The shared,
	 * unprefixed 'stats_display' row is folded in and deleted with the rest.
	 *
	 * Idempotent: after the first run there are no legacy rows left to read,
	 * and re-sanitizing settings that are already clean changes nothing. That
	 * re-sanitize is deliberate -- an install carrying values stored before the
	 * escaping rules were tightened never resubmits them through the form, so
	 * the upgrade is the only chance to clean them.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		$stored = get_option( self::OPTION, null );

		// The old row is the starting point when the new one does not exist
		// yet, so an install upgrading from 3.0.0 keeps every setting it had.
		if ( null === $stored ) {
			$stored = get_option( self::$renamed_from, array() );
		}

		$merged = is_array( $stored ) ? $stored : array();

		// The markers' old home. sanitize() would drop it anyway; unsetting it
		// here says so out loud.
		unset( $merged['versions'] );

		$merged = self::migrate_stats_display( $merged );

		self::update( self::sanitize( $merged ) );

		self::migrate_most();

		foreach ( self::legacy_options() as $legacy_name ) {
			delete_option( $legacy_name );
		}

		delete_option( self::$shared_option );
	}

	/**
	 * Carry the most-ever-online record over to its renamed row.
	 *
	 * Renamed rather than rebuilt: it is the site's highest ever count and
	 * there is nowhere else to recover it from.
	 *
	 * @return void
	 */
	private static function migrate_most() {
		if ( null !== get_option( self::MOST, null ) ) {
			return;
		}

		$most = get_option( self::$most_renamed_from, null );

		if ( ! is_array( $most ) ) {
			return;
		}

		self::update_most(
			isset( $most['count'] ) ? $most['count'] : 1,
			isset( $most['date'] ) ? $most['date'] : time()
		);
	}

	/**
	 * Take this plugin's share of the row it used to hold jointly.
	 *
	 * The old stats_display row was an array of checkbox keys written by
	 * whichever of the seven contributing plugins saved the WP-Stats screen
	 * last, and this plugin owned one of those keys.
	 *
	 * An absent row means "on", never "off". All seven plugins fold it in and
	 * each deletes it afterwards, so whichever one the site updates first takes
	 * it away and the other six find nothing left to read. Treating that as a
	 * deliberate opt-out would make the users online block disappear from the
	 * stats page of any site that happened to update a sibling first, silently
	 * and with nothing in any log to explain it. The worst case the other way
	 * round is a block the owner switches off again.
	 *
	 * @param array $merged Settings assembled so far.
	 *
	 * @return array
	 */
	private static function migrate_stats_display( $merged ) {
		if ( isset( $merged['stats_display'] ) ) {
			return $merged;
		}

		$legacy = get_option( self::$shared_option, null );

		if ( null === $legacy ) {
			// A sibling has already migrated it away. Not "switched off".
			$merged['stats_display'] = true;

			return $merged;
		}

		$merged['stats_display'] = is_array( $legacy )
			? ! empty( $legacy['useronline'] )
			: (bool) $legacy;

		return $merged;
	}

	/**
	 * Every option row the plugin owns, for uninstall.
	 *
	 * The legacy names are included so uninstalling after an upgrade that never
	 * ran still clears them. The shared stats_display row is deliberately not
	 * here: it is not this plugin's to take away on the way out, and a sibling
	 * that has not upgraded yet is still reading it.
	 *
	 * @return array
	 */
	public static function all_option_names() {
		return array_merge(
			self::legacy_options(),
			array(
				self::OPTION,
				self::VERSION,
				self::MOST,
				'widget_useronline',
			)
		);
	}
}
