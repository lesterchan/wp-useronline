<?php
/**
 * Users online widget.
 *
 * @package WP-UserOnline
 */

/**
 * Sidebar widget rendering the users online count and browsing lists.
 */
class UserOnline_Widget extends scbWidget {

	/**
	 * Register the widget with WordPress.
	 */
	public function __construct() {
		$widget_ops = array( 'description' => __( 'WP-UserOnline users online statistics', 'wp-useronline' ) );
		parent::__construct( 'useronline', __( 'UserOnline', 'wp-useronline' ), $widget_ops );
	}

	/**
	 * Print the widget body.
	 *
	 * @param array $instance Saved widget settings.
	 *
	 * @return void
	 */
	public function content( $instance ) {
		if ( empty( $instance['type'] ) ) {
			return;
		}

		$out = '';

		switch ( $instance['type'] ) {
			case 'users_online':
				$out .= html( 'div id="useronline-count"', get_users_online() );
				break;
			case 'users_browsing_page':
				$out .= html( 'div id="useronline-browsing-page"', get_users_browsing_page() );
				break;
			case 'users_browsing_site':
				$out .= html( 'div id="useronline-browsing-site"', get_users_browsing_site() );
				break;
			case 'users_online_browsing_page':
				$out .= html( 'div id="useronline-count"', get_users_online() );
				$out .= html( 'div id="useronline-browsing-page"', get_users_browsing_page() );
				break;
			case 'users_online_browsing_site':
				$out .= html( 'div id="useronline-count"', get_users_online() );
				$out .= html( 'div id="useronline-browsing-site"', get_users_browsing_site() );
				break;
		}

		echo $out;
	}

	/**
	 * Sanitize submitted widget settings.
	 *
	 * @param array $new_instance Submitted settings.
	 * @param array $old_instance Previously saved settings.
	 *
	 * @return array|false Settings to save, or false to keep the old ones.
	 */
	public function update( $new_instance, $old_instance ) {
		if ( ! isset( $new_instance['submit'] ) ) {
			return false;
		}

		$instance          = $old_instance;
		$instance['title'] = isset( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['type']  = isset( $new_instance['type'] ) ? sanitize_key( $new_instance['type'] ) : '';

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
		$title    = $instance['title'];
		$type     = $instance['type'];

		$types = array(
			'users_online'               => __( 'Users Online Count', 'wp-useronline' ),
			'users_browsing_page'        => __( 'Users Browsing Current Page', 'wp-useronline' ),
			'users_browsing_site'        => __( 'Users Browsing Site', 'wp-useronline' ),
			'users_online_browsing_page' => __( 'Users Online Count & Users Browsing Current Page', 'wp-useronline' ),
			'users_online_browsing_site' => __( 'Users Online Count & Users Browsing Site', 'wp-useronline' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'wp-useronline' ); ?>
				<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
			</label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>">
				<?php esc_html_e( 'Statistics Type:', 'wp-useronline' ); ?>
				<select name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" class="widefat">
					<?php foreach ( $types as $value => $label ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $type ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</p>
		<input type="hidden" id="<?php echo esc_attr( $this->get_field_id( 'submit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'submit' ) ); ?>" value="1" />
		<?php
	}
}
