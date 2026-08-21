<?php
/**
 * Tests that only mean anything on a network.
 *
 * They skip themselves on a single site run, so bin/test-multisite.sh is the
 * only way they execute.
 *
 * @package WP-UserOnline
 */

/**
 * Network activation: the install has to reach every site.
 *
 * @group ms-required
 *
 * @covers WP_UserOnline_Install::activate
 */
class WP_UserOnline_Multisite_Test extends WP_UserOnline_TestCase {

	/**
	 * Skip the whole class unless this is a network.
	 *
	 * @return void
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Runs only against a multisite network. Run bin/test-multisite.sh.' );
		}

		parent::set_up();
	}

	/**
	 * Create extra sites with the plugin's artifacts torn down.
	 *
	 * Torn down so activation has something to do: a leftover table would let
	 * a loop that never reaches the site pass anyway.
	 *
	 * @param int $count How many sites to create.
	 * @return int[] Blog ids.
	 */
	private function make_torn_down_sites( $count = 2 ) {
		global $wpdb;

		$site_ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$site_ids[] = self::factory()->blog->create();
		}

		foreach ( $site_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->useronline}" );
			delete_option( WP_UserOnline_Options::VERSION );
			restore_current_blog();
		}

		return $site_ids;
	}

	/**
	 * Network activation creates the table on every site, not just this one.
	 *
	 * @return void
	 */
	public function test_network_activation_installs_on_every_site() {
		global $wpdb;

		$site_ids = $this->make_torn_down_sites( 2 );

		WP_UserOnline_Install::activate( true );

		foreach ( $site_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			// Both sides have to be read inside the switch: $wpdb->useronline
			// is re-prefixed on the way back out.
			$expected = $wpdb->useronline;
			$exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $expected ) );
			restore_current_blog();

			$this->assertSame( $expected, $exists, "Site {$blog_id} did not get its useronline table." );
		}
	}

	/**
	 * Activating for one site only touches that site.
	 *
	 * @return void
	 */
	public function test_single_site_activation_leaves_other_sites_alone() {
		global $wpdb;

		$site_ids = $this->make_torn_down_sites( 1 );
		$other    = $site_ids[0];

		WP_UserOnline_Install::activate( false );

		switch_to_blog( $other );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->useronline ) );
		restore_current_blog();

		$this->assertNull( $exists, 'A single site activation installed across the network.' );
	}

	/**
	 * The site query is uncapped and asks only for IDs.
	 *
	 * Asserted by reading the arguments the query was given rather than by
	 * building a 101 site fixture: get_sites() defaults to 100, so a larger
	 * network would silently skip every site past the hundredth.
	 *
	 * @return void
	 */
	public function test_network_activation_queries_sites_without_a_cap() {
		$this->make_torn_down_sites( 2 );

		$captured = array();
		add_action(
			'pre_get_sites',
			function ( $query ) use ( &$captured ) {
				$captured[] = $query->query_vars;
			}
		);

		WP_UserOnline_Install::activate( true );

		$this->assertNotEmpty( $captured, 'Activation never queried the site list.' );
		$this->assertSame( 0, (int) $captured[0]['number'], 'get_sites() was left at its default cap of 100 sites.' );
		$this->assertSame( 'ids', $captured[0]['fields'], 'Only the site IDs are needed.' );
	}

	/**
	 * The switch stack is left balanced.
	 *
	 * Switching pushes onto a stack; restoring once after many switches leaves
	 * it unwound by exactly one, and every later switch is then off by one
	 * site.
	 *
	 * @return void
	 */
	public function test_network_activation_leaves_the_switch_stack_balanced() {
		$this->make_torn_down_sites( 2 );

		$before = get_current_blog_id();
		$depth  = count( $GLOBALS['_wp_switched_stack'] );

		WP_UserOnline_Install::activate( true );

		$this->assertSame( $before, get_current_blog_id(), 'Activation left the wrong site current.' );
		$this->assertCount( $depth, $GLOBALS['_wp_switched_stack'], 'The switch stack was left unbalanced.' );
	}
}
