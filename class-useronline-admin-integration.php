<?php
/**
 * Admin screens: the Users Online list and the settings page.
 *
 * @package WP-UserOnline
 */

/**
 * Dashboard screen listing everyone currently online.
 */
class UserOnline_Admin_Integration extends scbAdminPage {

	/**
	 * Configure the admin page.
	 *
	 * @return void
	 */
	public function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title'  => __( 'Users Online Now', 'wp-useronline' ),
			'menu_title'  => __( 'WP-UserOnline', 'wp-useronline' ),
			'page_slug'   => 'useronline',
			'parent'      => 'index.php',
			'action_link' => false,
			'capability'  => 'list_users',
		);

		add_action( 'rightnow_end', array( $this, 'rightnow' ) );
	}

	/**
	 * Print the users online summary in the At a Glance panel.
	 *
	 * @return void
	 */
	public function rightnow() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$total_users = get_users_online_count();

		$str = _n(
			"There is <strong><a href='%1\$s'>%2\$s user</a></strong> online now.",
			"There are a total of <strong><a href='%1\$s'>%2\$s users</a></strong> online now.",
			$total_users,
			'wp-useronline'
		);

		$out  = sprintf( $str, add_query_arg( 'page', $this->args['page_slug'], admin_url( 'index.php' ) ), number_format_i18n( $total_users ) );
		$out .= '<br>';

		$tmp = get_users_browsing_site();
		if ( $tmp ) {
			$out .= $tmp . '<br>';
		}

		$out .= UserOnline_Template::format_most_users();

		echo html( 'p', $out );
	}

	/**
	 * Render the page body.
	 *
	 * @return void
	 */
	public function page_content() {
		echo users_online_page();
	}
}
