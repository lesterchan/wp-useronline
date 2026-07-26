<?php
/**
 * Tests for the Settings API screen.
 *
 * @package WP-UserOnline
 */

/**
 * Registration wiring and field rendering.
 */
class Test_UserOnline_Settings extends WP_UnitTestCase {

	/**
	 * Settings instance under test.
	 *
	 * @var UserOnline_Settings
	 */
	private $settings;

	/**
	 * Register the settings as admin_init would.
	 */
	public function set_up() {
		parent::set_up();

		require_once dirname( __DIR__ ) . '/includes/class-useronline-settings.php';

		delete_option( UserOnline_Options::OPTION );

		$this->settings = new UserOnline_Settings();
		$this->settings->register_settings();
	}

	/**
	 * Capture a field callback's output.
	 *
	 * @param string $method Public field callback name.
	 *
	 * @return string
	 */
	private function render( $method ) {
		ob_start();
		$this->settings->$method();
		return ob_get_clean();
	}

	/**
	 * The setting is registered against the group the form posts to.
	 */
	public function test_setting_is_registered() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( UserOnline_Options::OPTION, $registered );
	}

	/**
	 * The sanitize_callback is actually wired, so options.php cleans input.
	 *
	 * This is the path a real save takes: register_setting() hooks the callback
	 * onto sanitize_option_{$option}.
	 */
	public function test_sanitize_callback_runs_on_save() {
		$dirty = array(
			'timeout' => '120',
			'naming'  => array( 'user' => '1 User<script>alert(1)</script>' ),
		);

		$clean = sanitize_option( UserOnline_Options::OPTION, $dirty );

		$this->assertStringNotContainsString( '<script', $clean['naming']['user'] );
		$this->assertSame( 120, $clean['timeout'] );
	}

	/**
	 * Saving through the registered callback must not drop the version markers.
	 */
	public function test_save_preserves_version_markers() {
		UserOnline_Options::set_version( 'sanitize', 1 );

		$clean = sanitize_option( UserOnline_Options::OPTION, array( 'timeout' => '60' ) );

		$this->assertSame( '1', $clean['versions']['sanitize'] );
	}

	/**
	 * Sections and fields are registered on the page the form renders.
	 */
	public function test_sections_and_fields_are_registered() {
		global $wp_settings_sections, $wp_settings_fields;

		$page = UserOnline_Settings::PAGE;

		$this->assertArrayHasKey( $page, $wp_settings_sections );
		$this->assertArrayHasKey( 'useronline_general', $wp_settings_sections[ $page ] );
		$this->assertArrayHasKey( 'useronline_naming', $wp_settings_sections[ $page ] );
		$this->assertArrayHasKey( 'useronline_templates', $wp_settings_sections[ $page ] );

		$this->assertArrayHasKey( $page, $wp_settings_fields );
	}

	/**
	 * Fields carry the nested option name, so a save lands in the right key.
	 */
	public function test_fields_use_nested_option_names() {
		$html = $this->render( 'field_naming' );

		$this->assertStringContainsString( 'name="useronline[naming][user]"', $html );
		$this->assertStringContainsString( 'name="useronline[naming][users]"', $html );
	}

	/**
	 * Separator inputs keep their whitespace, which is what parts names.
	 */
	public function test_separator_field_keeps_trailing_space() {
		$html = $this->render( 'field_template_browsingsite' );

		$this->assertStringContainsString(
			'name="useronline[templates][browsingsite][separators][members]" value=", "',
			$html
		);
	}

	/**
	 * Template tokens render literally.
	 *
	 * The formatter once read these as printf placeholders and numbered them, so the
	 * screen displayed "%1$GUESTS_SEPARATOR%" to users. Keeping them out of the
	 * translatable strings fixed it; this makes sure they stay literal.
	 */
	public function test_template_tokens_render_literally() {
		$html = $this->render( 'field_template_browsingsite' );

		$this->assertStringContainsString( '<code>%GUESTS_SEPARATOR%</code>', $html );
		$this->assertStringContainsString( '<code>%GUESTS%</code>', $html );
		$this->assertStringNotContainsString( '%1$', $html );
		$this->assertStringNotContainsString( '%2$', $html );
	}

	/**
	 * The same for the users online template.
	 */
	public function test_useronline_template_tokens_render_literally() {
		$html = $this->render( 'field_template_useronline' );

		$this->assertStringContainsString( '<code>%PAGE_URL%</code>', $html );
		$this->assertStringNotContainsString( '%1$', $html );
	}

	/**
	 * Every field exposes its default, which is what Restore Defaults reads.
	 */
	public function test_fields_expose_defaults_for_the_restore_button() {
		$html = $this->render( 'field_naming' );

		$this->assertStringContainsString( 'data-useronline-default="1 User"', $html );
		$this->assertStringContainsString( 'useronline-restore', $html );
	}

	/**
	 * The radio reflects the stored value.
	 */
	public function test_names_field_reflects_stored_value() {
		UserOnline_Options::update( UserOnline_Options::sanitize( array( 'names' => 1 ) ) );

		$html = $this->render( 'field_names' );

		$this->assertMatchesRegularExpression( '/value="1"\s+checked=/', $html );
	}
}
