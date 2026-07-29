<?php
/**
 * Tests for the Settings API screen.
 *
 * @package WP-UserOnline
 */

/**
 * Registration wiring and field rendering.
 */
class WP_UserOnline_Settings_Test extends WP_UserOnline_TestCase {

	/**
	 * Register the settings as admin_init would.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		WP_UserOnline_Settings::register_settings();
	}

	/**
	 * Capture a field callback's output.
	 *
	 * @param string $method Public field callback name.
	 *
	 * @return string
	 */
	private function render( $method ) {
		return $this->capture( array( 'WP_UserOnline_Settings', $method ) );
	}

	// --- registration ---------------------------------------------------

	public function test_the_setting_is_registered_against_the_group_the_form_posts_to() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( WP_UserOnline_Options::OPTION, $registered, 'the setting is not registered' );
		$this->assertSame( WP_UserOnline_Options::OPTION, WP_UserOnline_Settings::GROUP, 'the settings group must be the settings row name' );
	}

	/**
	 * This is the path a real save takes: register_setting() hooks the callback
	 * onto sanitize_option_{$option}.
	 */
	public function test_the_sanitize_callback_is_actually_wired_so_options_php_cleans_input() {
		$clean = sanitize_option(
			WP_UserOnline_Options::OPTION,
			array(
				'timeout' => '120',
				'naming'  => array( 'user' => '1 User<script>alert(1)</script>' ),
			)
		);

		$this->assertStringNotContainsString( '<script', $clean['naming']['user'], 'the sanitizer did not run on save' );
		$this->assertSame( 120, $clean['timeout'], 'the timeout was not cleaned' );
	}

	public function test_a_save_through_the_registered_callback_leaves_the_markers_alone() {
		sanitize_option( WP_UserOnline_Options::OPTION, array( 'timeout' => '60' ) );

		$this->assertSame( WP_USERONLINE_VERSION, WP_UserOnline_Options::markers()['plugin'], 'a save disturbed the marker row' );
	}

	public function test_all_four_sections_and_their_fields_are_registered_on_one_page() {
		global $wp_settings_sections, $wp_settings_fields;

		$page = WP_UserOnline_Settings::PAGE;

		$this->assertArrayHasKey( $page, $wp_settings_sections, 'nothing was registered on the settings page' );

		foreach ( array(
			WP_UserOnline_Settings::SECTION_GENERAL,
			WP_UserOnline_Settings::SECTION_NAMING,
			WP_UserOnline_Settings::SECTION_TEMPLATES,
			WP_UserOnline_Settings::SECTION_WPSTATS,
		) as $section ) {
			$this->assertArrayHasKey( $section, $wp_settings_sections[ $page ], $section . ' is missing' );
			$this->assertArrayHasKey( $section, $wp_settings_fields[ $page ], $section . ' has no fields' );
		}
	}

	public function test_every_section_id_carries_the_plugin_prefix() {
		foreach ( array(
			WP_UserOnline_Settings::SECTION_GENERAL,
			WP_UserOnline_Settings::SECTION_NAMING,
			WP_UserOnline_Settings::SECTION_TEMPLATES,
			WP_UserOnline_Settings::SECTION_WPSTATS,
		) as $section ) {
			$this->assertStringStartsWith( 'wp_useronline_', $section, $section . ' is not prefixed' );
		}
	}

	// --- the fields -----------------------------------------------------

	public function test_fields_carry_the_renamed_nested_option_name() {
		$html = $this->render( 'field_naming' );

		$this->assertStringContainsString( 'name="wp_useronline_options[naming][user]"', $html, 'the singular field is misnamed' );
		$this->assertStringContainsString( 'name="wp_useronline_options[naming][users]"', $html, 'the plural field is misnamed' );
	}

	public function test_separator_inputs_keep_their_trailing_space() {
		$html = $this->render( 'field_template_browsingsite' );

		$this->assertStringContainsString(
			'name="wp_useronline_options[templates][browsingsite][separators][members]" value=", "',
			$html,
			'the separator lost the space that parts the names'
		);
	}

	/**
	 * The formatter once read these as printf placeholders and numbered them,
	 * so the screen displayed "%1$GUESTS_SEPARATOR%" to users. Keeping them out
	 * of the translatable strings fixed it; this makes sure they stay literal.
	 */
	public function test_template_tokens_render_literally_rather_than_as_placeholders() {
		$html = $this->render( 'field_template_browsingsite' );

		$this->assertStringContainsString( '<code>%GUESTS_SEPARATOR%</code>', $html, 'a token was not rendered literally' );
		$this->assertStringContainsString( '<code>%GUESTS%</code>', $html, 'a token was not rendered literally' );
		$this->assertStringNotContainsString( '%1$', $html, 'a token was numbered as a placeholder' );
		$this->assertStringNotContainsString( '%2$', $html, 'a token was numbered as a placeholder' );
	}

	public function test_the_users_online_template_tokens_render_literally_too() {
		$html = $this->render( 'field_template_useronline' );

		$this->assertStringContainsString( '<code>%PAGE_URL%</code>', $html, 'a token was not rendered literally' );
		$this->assertStringNotContainsString( '%1$', $html, 'a token was numbered as a placeholder' );
	}

	public function test_every_field_exposes_the_default_the_restore_button_reads() {
		$html = $this->render( 'field_naming' );

		$this->assertStringContainsString( 'data-wp-useronline-default="1 User"', $html, 'the field carries no default' );
		$this->assertStringContainsString( 'wp-useronline-restore', $html, 'there is no restore button' );
	}

	public function test_the_link_names_radio_reflects_the_stored_value() {
		$this->set_option( 'names', 1 );

		$this->assertMatchesRegularExpression( '/value="1"\s+checked=/', $this->render( 'field_names' ), 'the stored choice is not selected' );
	}

	public function test_the_timeout_field_carries_the_stored_value_and_its_default() {
		$this->set_option( 'timeout', 120 );

		$html = $this->render( 'field_timeout' );

		$this->assertStringContainsString( 'name="wp_useronline_options[timeout]"', $html, 'the field is misnamed' );
		$this->assertStringContainsString( 'value="120"', $html, 'the stored value is missing' );
		$this->assertStringContainsString( 'data-wp-useronline-default="300"', $html, 'the default is missing' );
	}

	public function test_the_url_field_is_a_url_input_and_escapes_what_it_renders() {
		$this->set_option( 'url', 'https://example.com/who/?a=1&b=2' );

		$html = $this->render( 'field_url' );

		$this->assertStringContainsString( 'type="url"', $html, 'the field is not a url input' );
		$this->assertStringContainsString( 'name="wp_useronline_options[url]"', $html, 'the field is misnamed' );
		$this->assertStringNotContainsString( '?a=1&b=2"', $html, 'the ampersand was not escaped' );
	}

	public function test_the_naming_section_explains_the_one_token_it_accepts_literally() {
		$html = $this->capture( array( 'WP_UserOnline_Settings', 'section_naming' ) );

		$this->assertStringContainsString( '<code>%COUNT%</code>', $html, 'the token is not documented' );
		$this->assertStringNotContainsString( '%1$', $html, 'the token was numbered as a placeholder' );
	}

	public function test_the_browsing_page_template_renders_like_its_sibling() {
		$html = $this->render( 'field_template_browsingpage' );

		$this->assertStringContainsString( 'name="wp_useronline_options[templates][browsingpage][text]"', $html, 'the textarea is misnamed' );
		$this->assertStringContainsString( 'name="wp_useronline_options[templates][browsingpage][separators][bots]"', $html, 'a separator is missing' );
		$this->assertStringContainsString( '<code>%MEMBER_NAMES%</code>', $html, 'the tokens are not documented' );
	}

	public function test_stored_markup_is_escaped_into_the_textarea_rather_than_rendered() {
		WP_UserOnline_Options::update(
			WP_UserOnline_Options::sanitize(
				array( 'templates' => array( 'useronline' => '<a href="%PAGE_URL%">%USERS%</a>' ) )
			)
		);

		$this->assertStringContainsString( '&lt;a href=&quot;%PAGE_URL%&quot;&gt;', $this->render( 'field_template_useronline' ), 'stored markup was rendered instead of shown' );
	}

	public function test_the_wp_stats_toggle_reflects_the_stored_setting() {
		$this->set_option( 'stats_display', true );
		$this->assertStringContainsString( 'checked', $this->render( 'field_stats_display' ), 'the toggle should be on' );

		$this->set_option( 'stats_display', false );
		$this->assertStringNotContainsString( 'checked', $this->render( 'field_stats_display' ), 'the toggle should be off' );
	}

	public function test_the_wp_stats_toggle_posts_into_this_plugins_own_row() {
		$this->assertStringContainsString( 'name="wp_useronline_options[stats_display]"', $this->render( 'field_stats_display' ), 'the toggle does not write to this plugin\'s settings' );
	}

	// --- the screen -----------------------------------------------------

	public function test_no_field_emits_an_inline_style_attribute() {
		$html = $this->render( 'field_template_browsingsite' ) . $this->render( 'field_naming' ) . $this->render( 'field_names' );

		$this->assertStringNotContainsString( 'style=', $html, 'an inline style attribute reached the screen' );
	}

	public function test_rendering_the_form_requires_the_settings_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->expectException( 'WPDieException' );

		WP_UserOnline_Settings::render_page();
	}

	/**
	 * The option group and its nonce are what make the Settings API save at all.
	 */
	public function test_the_form_posts_to_options_php_with_the_group_and_a_nonce() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture( array( 'WP_UserOnline_Settings', 'render_page' ) );

		$this->assertStringContainsString( 'action="options.php"', $html, 'the form does not post to options.php' );
		$this->assertStringContainsString( "value='" . WP_UserOnline_Settings::GROUP . "'", $html, 'the option group is missing' );
		$this->assertStringContainsString( '_wpnonce', $html, 'the nonce is missing' );
	}

	public function test_the_screen_carries_no_inline_script_block_any_more() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringNotContainsString( '<script', $this->capture( array( 'WP_UserOnline_Settings', 'render_page' ) ), 'the screen still prints an inline script' );
	}

	public function test_the_restore_defaults_script_loads_on_the_settings_screen_only() {
		WP_UserOnline_Settings::enqueue_scripts( 'toplevel_page_' . WP_UserOnline_Admin::PAGE );
		$this->assertFalse( wp_script_is( 'wp-useronline-admin', 'enqueued' ), 'the script loaded on the wrong screen' );

		WP_UserOnline_Settings::enqueue_scripts( 'wp-useronline_page_' . WP_UserOnline_Settings::PAGE );
		$this->assertTrue( wp_script_is( 'wp-useronline-admin', 'enqueued' ), 'the script did not load on the settings screen' );
	}
}
