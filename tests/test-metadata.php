<?php
/**
 * WP-UserOnline's half of the metadata contract.
 *
 * The contract itself is Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php that every one of the
 * nineteen plugins carries. Everything shared lives there - including the two
 * WP-Stats family assertions, one of which was written here first. What is left
 * is what a machine cannot derive from the directory, plus the two assertions
 * that are genuinely about this plugin: that the release is the major its
 * filter renames require, and that only two of its three rows are autoloaded.
 *
 * @package WP-UserOnline
 */

/**
 * The shared contract, plus what only WP-UserOnline can answer.
 */
class WP_UserOnline_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from WP_USERONLINE_VERSION, so a bump has to
	 * be made here as well and cannot happen by accident.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '4.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_UserOnline';
	}

	/**
	 * Everything a site owner updating from the released version would notice.
	 *
	 * Six filters renamed with no shim behind them, the renamed constant, the
	 * screens that moved, the capability that was raised, the option rows that
	 * were folded up, the shared stats_display row, the class renames, and the
	 * script, localised object and ajax action that were all renamed with it.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'useronline_bots',
			'useronline_buckets',
			'useronline_custom_template',
			'useronline_page',
			'useronline_display_user',
			'useronline_trust_proxy',
			'USERONLINE_TRUST_PROXY',
			'WP_USERONLINE_TRUST_PROXY',
			'index.php?page=useronline',
			'options-general.php?page=useronline-settings',
			'admin.php?page=wp-useronline',
			'manage_options',
			'list_users',
			'wp_useronline_capability',
			'wp_useronline_options',
			'wp_useronline_most',
			'wp_useronline_version',
			'stats_display',
			'WP_UserOnline_',
			'useronline.js',
			'js/wp-useronline.js',
			'useronlineL10n',
			'wpUserOnlineL10n',
		);
	}

	/**
	 * WP-UserOnline is one of the seven sharing the WP-Stats surface.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * The one unprefixed WP-Stats row WP-UserOnline reads but does not own.
	 *
	 * The other shared row, stats_mostlimit, is not on the list: WP-UserOnline
	 * never read it.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array( 'stats_display' );
	}

	/**
	 * Write the rows uninstall is expected to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_UserOnline_Options::update( WP_UserOnline_Options::defaults() );
		WP_UserOnline_Options::update_markers();
		WP_UserOnline_Options::update_most( 5, time() );
	}

	/**
	 * Write the wp_useronline_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_UserOnline_Options::update_markers();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_UserOnline_Options::sanitize( $input );
	}

	/**
	 * A real settings key to send through the sanitiser beside the poison.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array( 'timeout' => 120 );
	}

	/**
	 * Register the front-end and admin assets.
	 *
	 * The front-end script is only enqueued once a list has actually rendered,
	 * and the admin one only on the plugin's own screen, so both have to be
	 * driven rather than merely called.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		WP_UserOnline_Template::compact_list( 'site' );
		WP_UserOnline::get_instance()->enqueue_scripts();

		WP_UserOnline_Admin::add_page();

		$_GET['tab'] = WP_UserOnline_Admin::TAB_SETTINGS;

		WP_UserOnline_Settings::enqueue_scripts( WP_UserOnline_Admin::screen_hook() );
	}

	/**
	 * 4.0.0, not 3.0.1.
	 *
	 * 3.0.0 is live on wordpress.org and this release renames six filters it
	 * promised were stable, so it cannot ship as a patch. §14 records this
	 * plugin and wp-dbmanager as the two that take a new major rather than
	 * folding into an unreleased one.
	 *
	 * @return void
	 */
	public function test_the_version_is_the_major_the_filter_renames_require() {
		$this->assertSame(
			'4.0.0',
			$this->expected_version(),
			'This release renames public filters and must be a major.'
		);
	}

	/**
	 * Two rows are autoloaded and the third is not.
	 *
	 * The record row is written on every new high and read only by the template
	 * tag that prints it, so autoloading wp_useronline_most would put a row on
	 * every request of every page to serve one shortcode.
	 *
	 * @return void
	 */
	public function test_the_plugin_owns_two_autoloaded_rows_and_no_more() {
		global $wpdb;

		$this->seed_option_rows();

		$autoloaded = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND autoload IN ( 'yes', 'on', 'auto', 'auto-on' )",
				$wpdb->esc_like( $this->option_prefix() ) . '%'
			)
		);

		sort( $autoloaded );

		$this->assertSame(
			array( WP_UserOnline_Options::OPTION, WP_UserOnline_Options::VERSION ),
			$autoloaded,
			'The settings and the markers are the only rows that may be autoloaded.'
		);
	}

	/**
	 * Donations is the last h3 of the Description, with the agreed wording.
	 *
	 * @return void
	 */
	public function test_donations_is_the_last_h3_of_the_description() {
		$readme      = $this->readme();
		$start       = (int) strpos( $readme, '## Description' );
		$description = substr( $readme, $start, (int) strpos( $readme, '## Usage' ) - $start );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertNotEmpty( $matches[0], 'The Description carries no h3 at all.' );
		$this->assertSame( '### Donations', rtrim( end( $matches[0] ) ), 'Donations must be the last h3 of the Description.' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins',
			$description,
			'The Donations wording has drifted.'
		);
	}
}
