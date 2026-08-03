<?php
/**
 * WP-UserOnline class-wp-useronline-widget.php
 *
 * @package WP-UserOnline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sidebar widget rendering the users online count and browsing lists.
 *
 * Extends WP_Widget directly; before 3.0.0 this went through scbWidget.
 *
 * @since 3.0.0
 */
class WP_UserOnline_Widget extends WP_Widget {

	/**
	 * Register the widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'useronline',
			__( 'UserOnline', 'wp-useronline' ),
			array(
				'description'                 => __( 'WP-UserOnline users online statistics', 'wp-useronline' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * The statistics types this widget can show.
	 *
	 * @return array<string, string>
	 */
	private function types() {
		return array(
			'users_online'               => __( 'Users Online Count', 'wp-useronline' ),
			'users_browsing_page'        => __( 'Users Browsing Current Page', 'wp-useronline' ),
			'users_browsing_site'        => __( 'Users Browsing Site', 'wp-useronline' ),
			'users_online_browsing_page' => __( 'Users Online Count & Users Browsing Current Page', 'wp-useronline' ),
			'users_online_browsing_site' => __( 'Users Online Count & Users Browsing Site', 'wp-useronline' ),
		);
	}

	/**
	 * Render the widget on the front end.
	 *
	 * Assembled into one string and printed through wp_kses_post() at the end.
	 * The sidebar's own before_widget and after_widget are markup this widget
	 * did not write and cannot escape piecemeal -- before_widget opens tags that
	 * after_widget closes -- so they are balanced first and filtered together.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved widget settings.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$type = ! empty( $instance['type'] ) ? $instance['type'] : 'users_online';

		if ( ! isset( $this->types()[ $type ] ) ) {
			return;
		}

		$out = '';

		if ( in_array( $type, array( 'users_online', 'users_online_browsing_page', 'users_online_browsing_site' ), true ) ) {
			$out .= '<div id="useronline-count">' . get_users_online() . '</div>';
		}

		if ( in_array( $type, array( 'users_browsing_page', 'users_online_browsing_page' ), true ) ) {
			$out .= '<div id="useronline-browsing-page">' . get_users_browsing_page() . '</div>';
		}

		if ( in_array( $type, array( 'users_browsing_site', 'users_online_browsing_site' ), true ) ) {
			$out .= '<div id="useronline-browsing-site">' . get_users_browsing_site() . '</div>';
		}

		if ( '' === $out ) {
			return;
		}

		// Every container above is a target the refresh script polls, so ask
		// for it here. The count-only variants never reach compact_list(),
		// which is what otherwise requests it.
		WP_UserOnline_Template::request_script();

		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		$heading = '' === $title
			? ''
			: $args['before_title'] . esc_html( $title ) . $args['after_title'];

		echo wp_kses_post( $args['before_widget'] . $heading . $out . $args['after_widget'] );
	}

	/**
	 * Sanitize submitted widget settings.
	 *
	 * @param array $new_instance Submitted settings.
	 * @param array $old_instance Previously saved settings.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';

		$type             = isset( $new_instance['type'] ) ? sanitize_key( $new_instance['type'] ) : 'users_online';
		$instance['type'] = isset( $this->types()[ $type ] ) ? $type : 'users_online';

		return $instance;
	}

	/**
	 * Render the widget settings form.
	 *
	 * @param array $instance Saved widget settings.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		$instance = wp_parse_args(
			(array) $instance,
			array(
				'title' => __( 'UserOnline', 'wp-useronline' ),
				'type'  => 'users_online',
			)
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'wp-useronline' ); ?>
			</label>
			<input class="widefat" type="text"
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
				value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>">
				<?php esc_html_e( 'Statistics Type:', 'wp-useronline' ); ?>
			</label>
			<select class="widefat"
				id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"
				name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>">
				<?php foreach ( $this->types() as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $value, $instance['type'] ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<?php
	}
}
