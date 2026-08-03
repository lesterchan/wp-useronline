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

	/**
	 * The templates are on the Templates tab and nothing else is.
	 *
	 * Each tab is a Settings API page of its own, which is the whole of what
	 * keeps one tab from drawing the other's fields -- so this asserts both
	 * halves: every section is on the tab it belongs to, and on no other.
	 *
	 * @return void
	 */
	public function test_each_section_is_registered_on_the_tab_it_belongs_to_and_no_other() {
		global $wp_settings_sections, $wp_settings_fields;

		$expected = array(
			WP_UserOnline_Admin::TAB_SETTINGS  => array(
				WP_UserOnline_Settings::SECTION_GENERAL,
				WP_UserOnline_Settings::SECTION_NAMING,
				WP_UserOnline_Settings::SECTION_WPSTATS,
			),
			WP_UserOnline_Admin::TAB_TEMPLATES => array(
				WP_UserOnline_Settings::SECTION_TEMPLATES,
			),
		);

		foreach ( $expected as $tab => $sections ) {
			$bucket = WP_UserOnline_Settings::tab_bucket( $tab );

			$this->assertArrayHasKey( $bucket, $wp_settings_sections, 'nothing was registered on the ' . $tab . ' tab' );
			$this->assertSame( $sections, array_keys( $wp_settings_sections[ $bucket ] ), 'the ' . $tab . ' tab holds the wrong sections' );

			foreach ( $sections as $section ) {
				$this->assertArrayHasKey( $section, $wp_settings_fields[ $bucket ], $section . ' has no fields' );
			}
		}
	}

	public function test_the_three_template_fields_are_all_on_the_templates_tab() {
		global $wp_settings_fields;

		$bucket = WP_UserOnline_Settings::tab_bucket( WP_UserOnline_Admin::TAB_TEMPLATES );

		$this->assertSame(
			array( 'template_useronline', 'template_browsingsite', 'template_browsingpage' ),
			array_keys( $wp_settings_fields[ $bucket ][ WP_UserOnline_Settings::SECTION_TEMPLATES ] ),
			'a template field was left behind on the settings tab'
		);
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

	public function test_no_field_emits_an_inline_style_attribute() {
		$html = $this->render( 'field_template_browsingsite' ) . $this->render( 'field_naming' ) . $this->render( 'field_names' );

		$this->assertStringNotContainsString( 'style=', $html, 'an inline style attribute reached the screen' );
	}

	public function test_rendering_the_form_requires_the_settings_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_SETTINGS;

		$this->expectException( 'WPDieException' );

		WP_UserOnline_Admin::render_page();
	}

	/**
	 * The option group and its nonce are what make the Settings API save at all.
	 */
	public function test_both_settings_tabs_post_to_options_php_with_the_group_and_a_nonce() {
		wp_set_current_user( $this->create_admin() );

		foreach ( array( WP_UserOnline_Admin::TAB_SETTINGS, WP_UserOnline_Admin::TAB_TEMPLATES ) as $tab ) {
			$_GET['tab'] = $tab;

			$html = $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) );

			$this->assertStringContainsString( 'action="options.php"', $html, $tab . ' does not post to options.php' );
			$this->assertStringContainsString( "value='" . WP_UserOnline_Settings::GROUP . "'", $html, $tab . ' posts under no option group' );
			$this->assertStringContainsString( '_wpnonce', $html, $tab . ' has no nonce' );
		}
	}

	/**
	 * One register_setting(), one option row, whichever tab is saving.
	 *
	 * Two groups against one row is what made wp-postratings' two halves
	 * reachable by different URLs; a tab is a rendering decision and must not
	 * become a storage one.
	 */
	public function test_there_is_one_registered_setting_for_all_three_tabs() {
		$registered = get_registered_settings();

		$ours = array_filter(
			array_keys( $registered ),
			static function ( $name ) {
				return 0 === strpos( $name, 'wp_useronline' );
			}
		);

		$this->assertSame( array( WP_UserOnline_Options::OPTION ), array_values( $ours ), 'the tabs must share one registered setting' );
	}

	/**
	 * Saving one tab must not blank what the others own.
	 *
	 * The Settings API hands the sanitize_callback only the fields the
	 * submitting form posted, so a sanitiser that returned just what it was
	 * given would wipe the other two tabs on every save -- silently, which is
	 * why this is a test rather than a comment.
	 */
	public function test_saving_the_settings_tab_leaves_the_templates_alone() {
		WP_UserOnline_Options::update(
			WP_UserOnline_Options::sanitize(
				array(
					'templates' => array(
						'useronline'   => 'Customised: %USERS%',
						'browsingsite' => array(
							'text'       => 'Customised site: %USERS%',
							'separators' => array( 'members' => ' | ' ),
						),
					),
				)
			)
		);

		// The path options.php takes, and exactly what the Settings tab posts:
		// no templates key at all.
		update_option(
			WP_UserOnline_Options::OPTION,
			array(
				'timeout'       => '900',
				'url'           => 'https://example.com/online',
				'names'         => '1',
				'stats_display' => '0',
				'naming'        => array( 'user' => 'One soul' ),
			)
		);

		$stored = WP_UserOnline_Options::get();

		$this->assertSame( 900, $stored['timeout'], 'the tab that saved did not save' );
		$this->assertSame( 'Customised: %USERS%', $stored['templates']['useronline'], 'saving the settings tab wiped a template' );
		$this->assertSame( 'Customised site: %USERS%', $stored['templates']['browsingsite']['text'], 'saving the settings tab wiped a template' );
		$this->assertSame( ' | ', $stored['templates']['browsingsite']['separators']['members'], 'saving the settings tab wiped a separator' );
	}

	public function test_saving_the_templates_tab_leaves_the_settings_alone() {
		$this->set_option( 'timeout', 900 );
		$this->set_option( 'url', 'https://example.com/online' );
		$this->set_option( 'names', 1 );
		$this->set_option( 'stats_display', false );
		$this->set_option( 'naming', array_merge( WP_UserOnline_Options::get( 'naming' ), array( 'user' => 'One soul' ) ) );

		// Exactly what the Templates tab posts: templates, and nothing else.
		update_option(
			WP_UserOnline_Options::OPTION,
			array( 'templates' => array( 'useronline' => 'Customised: %USERS%' ) )
		);

		$stored = WP_UserOnline_Options::get();

		$this->assertSame( 'Customised: %USERS%', $stored['templates']['useronline'], 'the tab that saved did not save' );
		$this->assertSame( 900, $stored['timeout'], 'saving the templates tab reset the timeout' );
		$this->assertSame( 'https://example.com/online', $stored['url'], 'saving the templates tab reset the URL' );
		$this->assertSame( 1, $stored['names'], 'saving the templates tab reset the name linking' );
		$this->assertFalse( $stored['stats_display'], 'saving the templates tab turned the WP-Stats section back on' );
		$this->assertSame( 'One soul', $stored['naming']['user'], 'saving the templates tab reset a naming convention' );
	}

	/**
	 * The other half of the merge: a checkbox that posts nothing when off would
	 * be read as "not submitted" and could never be turned off again.
	 */
	public function test_the_wp_stats_checkbox_can_still_be_turned_off_under_the_merge() {
		$this->set_option( 'stats_display', true );

		$html = $this->render( 'field_stats_display' );

		$this->assertStringContainsString(
			'<input type="hidden" name="wp_useronline_options[stats_display]" value="0" />',
			$html,
			'without the hidden zero an unticked box posts nothing and the merge keeps the old value'
		);

		update_option( WP_UserOnline_Options::OPTION, array( 'stats_display' => '0' ) );

		$this->assertFalse( WP_UserOnline_Options::get( 'stats_display' ), 'the box could be ticked and never unticked' );
	}

	public function test_a_settings_tab_carries_the_active_tab_through_the_save() {
		wp_set_current_user( $this->create_admin() );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_TEMPLATES;

		$html = $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) );

		// options.php redirects to the referer, and the one settings_fields()
		// writes is whatever URL this request came in on -- which carries no tab
		// when the tab was reached as the page's default.
		$this->assertStringContainsString(
			'name="_wp_http_referer" value="' . esc_url( WP_UserOnline_Admin::tab_url( WP_UserOnline_Admin::TAB_TEMPLATES ) ) . '"',
			$html,
			'the save would come back on a different tab'
		);
	}

	/**
	 * The confirmation notice belongs to the page rather than to a tab.
	 *
	 * It is printed above the tab strip, so whichever tab the save redirect
	 * comes back to shows it.
	 */
	public function test_the_saved_notice_prints_on_all_three_tabs() {
		wp_set_current_user( $this->create_admin() );

		foreach ( array_keys( WP_UserOnline_Admin::tabs() ) as $tab ) {
			add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'success' );

			$_GET['tab'] = $tab;

			$this->assertStringContainsString(
				'Settings saved.',
				$this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) ),
				'the ' . $tab . ' tab swallowed the confirmation notice'
			);

			$GLOBALS['wp_settings_errors'] = array();
		}
	}

	public function test_the_screen_carries_no_inline_script_block_any_more() {
		wp_set_current_user( $this->create_admin() );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_SETTINGS;

		$this->assertStringNotContainsString( '<script', $this->capture( array( 'WP_UserOnline_Admin', 'render_page' ) ), 'the screen still prints an inline script' );
	}

	public function test_the_restore_defaults_script_loads_on_the_settings_tabs_only() {
		// WP_Dependencies remembers registrations and enqueues for the whole
		// process, so "is it enqueued" answers for every test that ran before
		// this one too. Without a fresh instance the negative half of this test
		// passes or fails on execution order rather than on the code.
		$GLOBALS['wp_scripts'] = new WP_Scripts();

		wp_set_current_user( $this->create_admin() );

		WP_UserOnline_Admin::add_page();

		$hook = WP_UserOnline_Admin::screen_hook();

		WP_UserOnline_Settings::enqueue_scripts( 'index.php' );
		$this->assertFalse( wp_script_is( 'wp-useronline-admin', 'enqueued' ), 'the script loaded on another screen entirely' );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_USERONLINE;
		WP_UserOnline_Settings::enqueue_scripts( $hook );
		$this->assertFalse( wp_script_is( 'wp-useronline-admin', 'enqueued' ), 'the script loaded on the report tab, which has no field to restore' );

		$_GET['tab'] = WP_UserOnline_Admin::TAB_TEMPLATES;
		WP_UserOnline_Settings::enqueue_scripts( $hook );
		$this->assertTrue( wp_script_is( 'wp-useronline-admin', 'enqueued' ), 'the script did not load on the templates tab' );
	}
}
