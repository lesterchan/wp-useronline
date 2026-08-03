<?php
/**
 * WP-UserOnline class-wp-useronline-install.php
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates the useronline table and keeps the option rows in step.
 *
 * Install and uninstall sit side by side here so the two cannot drift apart,
 * and so the test suite can drive either directly rather than asserting on the
 * shape of a root file with a regular expression. uninstall.php is four lines
 * that call into this class.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Install {

	/**
	 * Run on activation, for one site or every site on the network.
	 *
	 * @param bool $network_wide Whether the plugin is being network activated.
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 lifts WP_Site_Query's default cap of 100, which
			// would otherwise skip the install on every site past the
			// hundredth.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}

			return;
		}

		self::install();
	}

	/**
	 * Install or upgrade the current site.
	 *
	 * @return void
	 */
	public static function install() {
		self::install_table();

		WP_UserOnline_Options::maybe_migrate();

		// Last, and in one write. A run that fails part way through leaves the
		// markers where they were, so the next request tries again rather than
		// believing the upgrade finished.
		WP_UserOnline_Options::update_markers();
	}

	/**
	 * Run the install on a normal request when something is out of date.
	 *
	 * Activation does not fire on a plugin *update*, which is the single most
	 * common reason a migration never runs. Gated on one autoloaded row, so an
	 * install that is already current pays a lookup and nothing else.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$markers = WP_UserOnline_Options::markers();

		if ( WP_USERONLINE_VERSION === $markers['plugin'] && WP_USERONLINE_DB_VERSION === $markers['db'] ) {
			return;
		}

		self::install();
	}

	/**
	 * Create or update the useronline table.
	 *
	 * @return void
	 */
	public static function install_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$wpdb->useronline} (
				timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
				user_type varchar(20) NOT NULL default 'guest',
				user_id bigint(20) NOT NULL default 0,
				user_name varchar(250) NOT NULL default '',
				user_ip varchar(39) NOT NULL default '',
				user_agent text NOT NULL,
				page_title text NOT NULL,
				page_url varchar(255) NOT NULL default '',
				referral varchar(255) NOT NULL default '',
				UNIQUE KEY useronline_id (timestamp, user_type, user_ip)
			) {$charset_collate};"
		);
	}

	/**
	 * Remove everything the plugin created, for one site or the whole network.
	 *
	 * @return void
	 */
	public static function uninstall() {
		if ( ! is_multisite() ) {
			self::uninstall_site();

			return;
		}

		// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
		// otherwise leave the options and tables behind on every site past the
		// hundredth while uninstall still reported success.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		// restore_current_blog() sits inside the loop on purpose: switching
		// pushes onto a stack, so switching many times and restoring once
		// leaves the stack unwound by exactly one.
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			self::uninstall_site();
			restore_current_blog();
		}
	}

	/**
	 * Remove the plugin's options and table for one site.
	 *
	 * @return void
	 */
	public static function uninstall_site() {
		global $wpdb;

		foreach ( WP_UserOnline_Options::all_option_names() as $option_name ) {
			delete_option( $option_name );
		}

		$wpdb->query( "DROP TABLE IF EXISTS `{$wpdb->prefix}useronline`" );
	}
}
