<?php
/**
 * Tests for WP_UserOnline_Options.
 *
 * @package WP-UserOnline
 */

/**
 * Sanitization, defaults and the internal version markers.
 */
class Test_UserOnline_Options extends WP_UnitTestCase {

	/**
	 * Reset stored settings between tests.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( WP_UserOnline_Options::OPTION );
	}

	/**
	 * A missing key falls back to the default rather than warning.
	 */
	public function test_get_merges_defaults() {
		update_option( WP_UserOnline_Options::OPTION, array( 'timeout' => 42 ) );

		$this->assertSame( 42, WP_UserOnline_Options::get( 'timeout' ) );
		$this->assertSame( '1 Member', WP_UserOnline_Options::get( 'naming' )['member'] );
	}

	/**
	 * Script tags are stripped from the naming conventions.
	 */
	public function test_sanitize_strips_scripts() {
		$clean = WP_UserOnline_Options::sanitize(
			array( 'naming' => array( 'user' => '1 User<script>alert(1)</script>' ) )
		);

		$this->assertStringNotContainsString( '<script', $clean['naming']['user'] );
		$this->assertStringStartsWith( '1 User', $clean['naming']['user'] );
	}

	/**
	 * A javascript: URL is rejected outright.
	 */
	public function test_sanitize_rejects_javascript_url() {
		$clean = WP_UserOnline_Options::sanitize( array( 'url' => 'javascript:alert(1)' ) );

		$this->assertSame( '', $clean['url'] );
	}

	/**
	 * A partial submission cannot leave the renderer without its nested keys.
	 */
	public function test_sanitize_rebuilds_template_shape() {
		$clean = WP_UserOnline_Options::sanitize( array( 'templates' => array( 'browsingsite' => 'not an array' ) ) );

		$this->assertArrayHasKey( 'text', $clean['templates']['browsingsite'] );
		$this->assertArrayHasKey( 'bots', $clean['templates']['browsingsite']['separators'] );
		$this->assertArrayHasKey( 'text', $clean['templates']['browsingpage'] );
	}

	/**
	 * Separators keep their whitespace; that trailing space separates names.
	 */
	public function test_sanitize_does_not_trim_separators() {
		$clean = WP_UserOnline_Options::sanitize( array() );

		$this->assertSame( ', ', $clean['templates']['browsingsite']['separators']['members'] );
	}

	/**
	 * A zero timeout would purge every row on the next request.
	 */
	public function test_sanitize_rejects_zero_timeout() {
		$clean = WP_UserOnline_Options::sanitize( array( 'timeout' => '0' ) );

		$this->assertSame( 300, $clean['timeout'] );
	}

	/**
	 * Running the sanitizer twice must not keep changing the value.
	 */
	public function test_sanitize_is_idempotent() {
		$once  = WP_UserOnline_Options::sanitize( array() );
		$twice = WP_UserOnline_Options::sanitize( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * The settings form never posts the version markers, so sanitize() has to
	 * carry them over. Without this every save re-triggers the migrations.
	 */
	public function test_sanitize_preserves_version_markers() {
		WP_UserOnline_Options::set_version( 'sanitize', 1 );
		WP_UserOnline_Options::set_version( 'db', '1' );

		$clean = WP_UserOnline_Options::sanitize( array( 'timeout' => '120' ) );

		$this->assertSame( '1', $clean['versions']['sanitize'] );
		$this->assertSame( '1', $clean['versions']['db'] );
	}

	/**
	 * Markers live inside the main option, not in rows of their own.
	 */
	public function test_version_markers_use_no_extra_option_rows() {
		WP_UserOnline_Options::set_version( 'db', '1' );

		$this->assertSame( '1', WP_UserOnline_Options::get_version( 'db' ) );
		$this->assertFalse( get_option( 'useronline_db_version' ) );
		$this->assertFalse( get_option( 'useronline_sanitize_version' ) );
	}

	/**
	 * An install carrying a compromised option is cleaned on upgrade.
	 */
	public function test_maybe_migrate_cleans_stored_settings() {
		update_option(
			WP_UserOnline_Options::OPTION,
			array( 'templates' => array( 'useronline' => '<a href="%PAGE_URL%"><script>alert(1)</script></a>' ) )
		);

		WP_UserOnline_Options::maybe_migrate();

		$stored = get_option( WP_UserOnline_Options::OPTION );

		$this->assertStringNotContainsString( '<script', $stored['templates']['useronline'] );
		$this->assertSame( '1', WP_UserOnline_Options::get_version( 'sanitize' ) );
	}
}
