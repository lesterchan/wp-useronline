<?php
/**
 * Tests for the WP-Stats integration.
 *
 * @package WP-UserOnline
 */

/**
 * The integration only loads when WP-Stats is present, so these drive the class
 * directly rather than relying on that plugin being installed.
 */
class Test_UserOnline_WpStats extends WP_UnitTestCase {

	use UserOnline_Reset_Statics;

	/**
	 * Integration instance under test.
	 *
	 * @var UserOnline_WpStats
	 */
	private $stats;

	/**
	 * Load the class and start from a clean table.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		require_once dirname( __DIR__ ) . '/includes/class-useronline-wpstats.php';

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		$this->reset_useronline_statics();
		delete_option( 'stats_display' );

		$this->stats = new UserOnline_WpStats();
	}

	/**
	 * Remove the option again.
	 */
	public function tear_down() {
		delete_option( 'stats_display' );
		parent::tear_down();
	}

	/**
	 * The constructor hooks both WP-Stats filters.
	 */
	public function test_filters_are_registered() {
		$this->assertNotFalse( has_filter( 'wp_stats_page_admin_plugins', array( $this->stats, 'admin_stats' ) ) );
		$this->assertNotFalse( has_filter( 'wp_stats_page_plugins', array( $this->stats, 'stats' ) ) );
	}

	/**
	 * The options screen gains a checkbox, appended to what came before.
	 */
	public function test_admin_stats_appends_a_checkbox() {
		$html = $this->stats->admin_stats( 'EXISTING' );

		$this->assertStringStartsWith( 'EXISTING', $html );
		$this->assertStringContainsString( 'name="stats_display[]"', $html );
		$this->assertStringContainsString( 'value="useronline"', $html );
	}

	/**
	 * The checkbox reflects the stored preference.
	 */
	public function test_admin_stats_checkbox_reflects_the_option() {
		$this->assertStringNotContainsString( 'checked', $this->stats->admin_stats( '' ) );

		update_option( 'stats_display', array( 'useronline' => 1 ) );

		$this->assertStringContainsString( 'checked', $this->stats->admin_stats( '' ) );
	}

	/**
	 * A missing option must not warn.
	 */
	public function test_admin_stats_handles_a_missing_option() {
		delete_option( 'stats_display' );

		$this->assertStringContainsString( 'wpstats_useronline', $this->stats->admin_stats( '' ) );
	}

	/**
	 * With the preference off, the stats page is left untouched.
	 */
	public function test_stats_returns_content_unchanged_when_disabled() {
		update_option( 'stats_display', array( 'useronline' => 0 ) );

		$this->assertSame( 'EXISTING', $this->stats->stats( 'EXISTING' ) );
	}

	/**
	 * And is untouched when the option was never saved.
	 */
	public function test_stats_returns_content_unchanged_when_absent() {
		delete_option( 'stats_display' );

		$this->assertSame( 'EXISTING', $this->stats->stats( 'EXISTING' ) );
	}

	/**
	 * With it on, the figures are appended.
	 */
	public function test_stats_appends_the_figures_when_enabled() {
		update_option( 'stats_display', array( 'useronline' => 1 ) );

		UserOnline_Recorder::record( '/somewhere', 'Somewhere' );
		UserOnline_Options::update_most( 42, time() );

		$html = $this->stats->stats( 'EXISTING' );

		$this->assertStringStartsWith( 'EXISTING', $html );
		$this->assertStringContainsString( 'WP-UserOnline', $html );
		$this->assertStringContainsString( 'online now', $html );
		$this->assertStringContainsString( '42', $html );
	}

	/**
	 * Visitor-controlled names cannot smuggle markup onto the stats page.
	 */
	public function test_stats_output_is_escaped() {
		global $wpdb;

		update_option( 'stats_display', array( 'useronline' => 1 ) );

		$wpdb->insert(
			$wpdb->useronline,
			array(
				'timestamp'  => current_time( 'mysql' ),
				'user_type'  => 'guest',
				'user_id'    => 0,
				'user_name'  => '<script>alert(1)</script>',
				'user_ip'    => '198.51.100.1',
				'user_agent' => 'Mozilla/5.0',
				'page_title' => 'A page',
				'page_url'   => '/a-page/',
				'referral'   => '',
			)
		);
		$this->reset_useronline_statics();

		$this->assertStringNotContainsString( '<script', $this->stats->stats( '' ) );
	}
}
