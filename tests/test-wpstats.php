<?php
/**
 * Tests for the WP-Stats integration.
 *
 * @package WP-UserOnline
 */

/**
 * The contract with WP-Stats, driven directly rather than by installing it.
 *
 * The whole point of the arrangement is that this class answers a filter and
 * reads nothing but its own settings, so everything it promises can be checked
 * without the other plugin being present.
 */
class WP_UserOnline_WPStats_Test extends WP_UserOnline_TestCase {

	/**
	 * The entry this plugin contributes, or null when it opted out.
	 *
	 * @return array|null
	 */
	private function section() {
		$sections = WP_UserOnline_WPStats::register_section( array() );

		return isset( $sections['wp_useronline'] ) ? $sections['wp_useronline'] : null;
	}

	public function test_the_class_hooks_wp_stats_sections_and_nothing_else() {
		$this->assertNotFalse( has_filter( 'wp_stats_sections', array( 'WP_UserOnline_WPStats', 'register_section' ) ), 'the section is not offered' );

		foreach ( array( 'wp_stats_display_defaults', 'wp_stats_page_admin_plugins', 'wp_stats_page_plugins' ) as $retired ) {
			$this->assertFalse( has_filter( $retired ), $retired . ' is a pre-4.0.0 hook and must not be used' );
		}
	}

	public function test_the_entry_is_keyed_by_this_plugins_slug_with_underscores() {
		$sections = WP_UserOnline_WPStats::register_section( array() );

		$this->assertSame( array( 'wp_useronline' ), array_keys( $sections ), 'the entry must be keyed wp_useronline and add nothing else' );
	}

	public function test_the_entry_keeps_whatever_a_sibling_already_contributed() {
		$sections = WP_UserOnline_WPStats::register_section( array( 'wp_polls' => array( 'title' => 'Polls' ) ) );

		$this->assertArrayHasKey( 'wp_polls', $sections, 'a sibling entry was dropped' );
		$this->assertArrayHasKey( 'wp_useronline', $sections, 'this plugin did not contribute' );
	}

	public function test_a_non_array_filter_value_does_not_fatal() {
		$sections = WP_UserOnline_WPStats::register_section( null );

		$this->assertArrayHasKey( 'wp_useronline', $sections, 'a null filter value should still yield this entry' );
	}

	public function test_the_title_is_a_non_empty_translated_string_because_wp_stats_echoes_it() {
		$section = $this->section();

		$this->assertIsString( $section['title'], 'the title must be a string' );
		$this->assertNotSame( '', $section['title'], 'an empty title makes WP-Stats skip the whole entry' );
	}

	public function test_the_render_callback_is_callable_and_takes_no_arguments() {
		$section = $this->section();

		$this->assertTrue( is_callable( $section['render'] ), 'the render callback must be callable' );

		$reflection = new ReflectionMethod( 'WP_UserOnline_WPStats', 'render' );

		$this->assertSame( 0, $reflection->getNumberOfParameters(), 'render() is called with no arguments' );
	}

	public function test_the_priority_is_sent_explicitly_as_an_integer() {
		$section = $this->section();

		$this->assertArrayHasKey( 'priority', $section, 'the priority should be sent explicitly' );
		$this->assertIsInt( $section['priority'], 'the priority must be an int' );
	}

	public function test_opting_out_returns_the_sections_untouched() {
		$this->set_options( array( 'stats_display' => false ) );

		$sections = WP_UserOnline_WPStats::register_section( array( 'wp_polls' => array( 'title' => 'Polls' ) ) );

		$this->assertSame( array( 'wp_polls' => array( 'title' => 'Polls' ) ), $sections, 'an opted out plugin must contribute nothing at all' );
	}

	public function test_a_fresh_install_contributes_because_the_default_is_on() {
		delete_option( WP_UserOnline_Options::OPTION );

		$this->assertNotNull( $this->section(), 'the section should be on by default' );
	}

	public function test_the_decision_is_made_from_this_plugins_own_row_alone() {
		$this->set_options( array( 'stats_display' => false ) );
		update_option( 'stats_display', array( 'useronline' => 1 ) );

		$this->assertNull( $this->section(), 'the shared legacy row must not be consulted any more' );
	}

	public function test_render_echoes_rather_than_returns_because_wp_stats_buffers() {
		$this->record_row();

		ob_start();
		$returned = WP_UserOnline_WPStats::render();
		$echoed   = ob_get_clean();

		$this->assertNull( $returned, 'render() must not return markup; WP-Stats would drop it' );
		$this->assertNotSame( '', $echoed, 'render() echoed nothing' );
	}

	public function test_render_reports_the_count_and_the_record() {
		$this->record_row();
		WP_UserOnline_Options::update_most( 42, time() );

		$html = $this->capture( array( 'WP_UserOnline_WPStats', 'render' ) );

		$this->assertStringContainsString( 'online now', $html, 'the count line is missing' );
		$this->assertStringContainsString( '42', $html, 'the most-ever figure is missing' );
	}

	public function test_render_does_not_echo_its_own_heading_because_wp_stats_does_that() {
		$this->record_row();

		$html  = $this->capture( array( 'WP_UserOnline_WPStats', 'render' ) );
		$title = $this->section()['title'];

		$this->assertStringNotContainsString( '<h2', $html, 'the contributor must not print a heading' );
		$this->assertStringNotContainsString( $title, $html, 'the title is WP-Stats\' to echo, not this plugin\'s' );
	}

	public function test_a_visitor_controlled_name_cannot_smuggle_markup_onto_the_stats_page() {
		$this->record_row( array( 'user_name' => '<script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script', $this->capture( array( 'WP_UserOnline_WPStats', 'render' ) ), 'a script tag reached the stats page' );
	}
}
