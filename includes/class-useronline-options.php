<?php
/**
 * WP-UserOnline class-useronline-options.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads, writes and sanitizes the plugin's options.
 *
 * Replaces the scbOptions wrapper the plugin used before 3.0.0. The option
 * keys are unchanged, so existing installs keep their settings.
 *
 * @since 3.0.0
 */
class UserOnline_Options {

	/**
	 * Option holding the plugin settings.
	 */
	const OPTION = 'useronline';

	/**
	 * Option holding the most-ever-online record.
	 */
	const MOST_OPTION = 'useronline_most';

	/**
	 * Reserved key inside the settings holding internal version markers.
	 *
	 * Bookkeeping rather than a user setting, so it is deliberately absent from
	 * defaults() and never rendered by the settings screen. Keeping it in the
	 * main option means the plugin owns two autoloaded rows instead of four.
	 */
	const VERSIONS_KEY = 'versions';

	/**
	 * Bumped when sanitize() gets stricter, so stored values are re-run once.
	 */
	const SANITIZE_VERSION = 1;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'timeout'   => 300,
			'url'       => trailingslashit( home_url() ) . 'useronline',
			'names'     => 0,
			'naming'    => array(
				'user'    => __( '1 User', 'wp-useronline' ),
				'users'   => __( '%COUNT% Users', 'wp-useronline' ),
				'member'  => __( '1 Member', 'wp-useronline' ),
				'members' => __( '%COUNT% Members', 'wp-useronline' ),
				'guest'   => __( '1 Guest', 'wp-useronline' ),
				'guests'  => __( '%COUNT% Guests', 'wp-useronline' ),
				'bot'     => __( '1 Bot', 'wp-useronline' ),
				'bots'    => __( '%COUNT% Bots', 'wp-useronline' ),
			),
			'templates' => array(
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
	 * Read an internal version marker.
	 *
	 * @param string $which Marker name, e.g. 'sanitize' or 'db'.
	 *
	 * @return string Stored value, or an empty string when never set.
	 */
	public static function get_version( $which ) {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) || ! isset( $stored[ self::VERSIONS_KEY ][ $which ] ) ) {
			return '';
		}

		return (string) $stored[ self::VERSIONS_KEY ][ $which ];
	}

	/**
	 * Write an internal version marker.
	 *
	 * Reads and writes the raw option rather than going through get(), so the
	 * defaults are not baked into storage as a side effect.
	 *
	 * @param string $which Marker name, e.g. 'sanitize' or 'db'.
	 * @param string $value Value to store.
	 *
	 * @return void
	 */
	public static function set_version( $which, $value ) {
		$stored = get_option( self::OPTION, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		if ( ! isset( $stored[ self::VERSIONS_KEY ] ) || ! is_array( $stored[ self::VERSIONS_KEY ] ) ) {
			$stored[ self::VERSIONS_KEY ] = array();
		}

		$stored[ self::VERSIONS_KEY ][ $which ] = (string) $value;

		update_option( self::OPTION, $stored );
	}

	/**
	 * Get the most-ever-online record.
	 *
	 * @param string|null $key Either 'count', 'date', or null for both.
	 *
	 * @return mixed
	 */
	public static function most( $key = null ) {
		$stored = get_option( self::MOST_OPTION, array() );
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
	 * @param int $count Number of users online.
	 * @param int $date  Unix timestamp the record was set.
	 *
	 * @return void
	 */
	public static function update_most( $count, $date ) {
		update_option(
			self::MOST_OPTION,
			array(
				'count' => (int) $count,
				'date'  => (int) $date,
			)
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
	 * @param mixed $options Submitted settings.
	 *
	 * @return array
	 */
	public static function sanitize( $options ) {
		if ( ! is_array( $options ) ) {
			$options = array();
		}

		$defaults = self::defaults();

		$clean            = array();
		$clean['timeout'] = isset( $options['timeout'] ) ? absint( $options['timeout'] ) : $defaults['timeout'];
		$clean['url']     = ! empty( $options['url'] ) ? esc_url_raw( trim( $options['url'] ) ) : '';
		$clean['names']   = empty( $options['names'] ) ? 0 : 1;

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

		// Carry the internal version markers across. The settings form never
		// posts them, and $options is only what was submitted, so without this
		// every save would drop them and make the next request look like a
		// fresh install -- re-running the migration and the table install.
		$stored = get_option( self::OPTION, array() );

		if ( is_array( $stored ) && ! empty( $stored[ self::VERSIONS_KEY ] ) && is_array( $stored[ self::VERSIONS_KEY ] ) ) {
			$clean[ self::VERSIONS_KEY ] = array_map( 'strval', $stored[ self::VERSIONS_KEY ] );
		}

		return $clean;
	}

	/**
	 * Re-sanitize settings stored before the escaping rules were tightened.
	 *
	 * Sanitizing only on save means an install that was already compromised
	 * stays compromised after it updates, because the bad value is never
	 * resubmitted through the settings form.
	 *
	 * @return void
	 */
	public static function maybe_migrate() {
		if ( (int) self::get_version( 'sanitize' ) >= self::SANITIZE_VERSION ) {
			return;
		}

		self::update( self::sanitize( self::get() ) );

		self::set_version( 'sanitize', self::SANITIZE_VERSION );
	}
}
