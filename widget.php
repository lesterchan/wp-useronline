<?php

class UserOnline_Widget extends scbWidget {
	function __construct() {
		$widget_ops = array( 'description' => __( 'WP-UserOnline users online statistics', 'wp-useronline' ) );
		parent::__construct( 'useronline', __( 'UserOnline', 'wp-useronline' ), $widget_ops );
	}

	function content( $instance ) {
		$out = '';

		switch( $instance['type'] ) {
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

	function update( $new_instance, $old_instance ) {
		if ( !isset( $new_instance['submit'] ) )
			return false;

		$instance = $old_instance;
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['type'] = strip_tags( $new_instance['type'] );

		return $instance;
	}

	function form( $instance ) {
		global $wpdb;
		$instance = wp_parse_args( (array) $instance, array(
			'title' => __( 'UserOnline', 'wp-useronline' ),
			'type' => 'users_online'
		) );
		$title = esc_attr( $instance['title'] );
		$type = esc_attr( $instance['type'] );
?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'wp-useronline' ); ?> <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Statistics Type:', 'wp-useronline' ); ?>
				<select name="<?php echo $this->get_field_name( 'type' ); ?>" id="<?php echo $this->get_field_id( 'type' ); ?>" class="widefat">
					<option value="users_online"<?php selected( 'users_online', $type ); ?>><?php _e( 'Users Online Count', 'wp-useronline' ); ?></option>
					<option value="users_browsing_page"<?php selected( 'users_browsing_page', $type ); ?>><?php _e( 'Users Browsing Current Page', 'wp-useronline' ); ?></option>
					<option value="users_browsing_site"<?php selected( 'users_browsing_site', $type ); ?>><?php _e( 'Users Browsing Site', 'wp-useronline' ); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="users_online_browsing_page"<?php selected( 'users_online_browsing_page', $type ); ?>><?php _e( 'Users Online Count & Users Browsing Current Page', 'wp-useronline' ); ?></option>
					<option value="users_online_browsing_site"<?php selected( 'users_online_browsing_site', $type ); ?>><?php _e( 'Users Online Count & Users Browsing Site', 'wp-useronline' ); ?></option>
				</select>
			</label>
		</p>
		<input type="hidden" id="<?php echo $this->get_field_id( 'submit' ); ?>" name="<?php echo $this->get_field_name( 'submit' ); ?>" value="1" />
<?php
	}
}

