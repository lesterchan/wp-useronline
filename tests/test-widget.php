<?php
/**
 * Tests for the users online widget.
 *
 * @package WP-UserOnline
 */

/**
 * Settings sanitization and front-end output.
 */
class Test_UserOnline_Widget extends WP_UnitTestCase {

	use WP_UserOnline_Reset_Statics;

	/**
	 * Widget instance under test.
	 *
	 * @var WP_UserOnline_Widget
	 */
	private $widget;

	/**
	 * Sidebar arguments a theme would pass.
	 *
	 * @var array
	 */
	private $args = array(
		'before_widget' => '<aside>',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2>',
		'after_title'   => '</h2>',
	);

	/**
	 * Set up a widget and an empty table.
	 */
	public function set_up() {
		global $wpdb;

		parent::set_up();

		require_once dirname( __DIR__ ) . '/includes/class-wp-useronline-widget.php';

		$wpdb->query( "DELETE FROM {$wpdb->useronline}" );
		$this->reset_useronline_statics();

		$this->widget = new WP_UserOnline_Widget();
	}

	/**
	 * Render the widget front end.
	 *
	 * @param array $instance Saved settings.
	 *
	 * @return string
	 */
	private function render( array $instance ) {
		ob_start();
		$this->widget->widget( $this->args, $instance );
		return ob_get_clean();
	}

	/**
	 * The count variant emits the container the refresh script targets.
	 */
	public function test_users_online_renders_count_container() {
		$html = $this->render( array( 'type' => 'users_online' ) );

		$this->assertStringContainsString( 'id="useronline-count"', $html );
		$this->assertStringContainsString( '<aside>', $html );
	}

	/**
	 * The combined variant emits both containers.
	 */
	public function test_combined_variant_renders_both_containers() {
		$html = $this->render( array( 'type' => 'users_online_browsing_site' ) );

		$this->assertStringContainsString( 'id="useronline-count"', $html );
		$this->assertStringContainsString( 'id="useronline-browsing-site"', $html );
	}

	/**
	 * An unknown type renders nothing at all, not an empty shell.
	 */
	public function test_unknown_type_renders_nothing() {
		$this->assertSame( '', $this->render( array( 'type' => 'nonsense' ) ) );
	}

	/**
	 * The title is escaped on output.
	 */
	public function test_title_is_escaped() {
		$html = $this->render(
			array(
				'type'  => 'users_online',
				'title' => 'Online <script>alert(1)</script>',
			)
		);

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringContainsString( '<h2>', $html );
	}

	/**
	 * No title means no heading markup.
	 */
	public function test_absent_title_emits_no_heading() {
		$html = $this->render( array( 'type' => 'users_online' ) );

		$this->assertStringNotContainsString( '<h2>', $html );
	}

	/**
	 * Rendering marks the request as needing the refresh script.
	 */
	public function test_render_requests_the_refresh_script() {
		$this->render( array( 'type' => 'users_online' ) );

		$this->assertTrue( WP_UserOnline_Template::needs_script() );
	}

	/**
	 * Submitted titles are sanitized.
	 */
	public function test_update_sanitizes_title() {
		$saved = $this->widget->update(
			array(
				'title' => 'Hello <script>alert(1)</script>',
				'type'  => 'users_online',
			),
			array()
		);

		$this->assertStringNotContainsString( '<script', $saved['title'] );
	}

	/**
	 * An unknown type falls back rather than being stored.
	 */
	public function test_update_rejects_unknown_type() {
		$saved = $this->widget->update(
			array(
				'title' => 'Hello',
				'type'  => 'nonsense',
			),
			array()
		);

		$this->assertSame( 'users_online', $saved['type'] );
	}

	/**
	 * A valid type is kept as-is.
	 */
	public function test_update_keeps_known_type() {
		$saved = $this->widget->update(
			array(
				'title' => 'Hello',
				'type'  => 'users_browsing_site',
			),
			array()
		);

		$this->assertSame( 'users_browsing_site', $saved['type'] );
	}

	/**
	 * The form offers every type and escapes its values.
	 */
	public function test_form_lists_every_type() {
		ob_start();
		$this->widget->form( array() );
		$html = ob_get_clean();

		foreach ( array( 'users_online', 'users_browsing_page', 'users_browsing_site', 'users_online_browsing_page', 'users_online_browsing_site' ) as $type ) {
			$this->assertStringContainsString( 'value="' . $type . '"', $html );
		}
	}
}
