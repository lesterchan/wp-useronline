<?php

### Function: WP-UserOnline Menu
add_action('admin_menu', 'useronline_menu');
function useronline_menu() {
	add_submenu_page('index.php',  __('WP-UserOnline', 'wp-useronline'),  __('WP-UserOnline', 'wp-useronline'), 'read', 'wp-useronline/wp-useronline.php', 'display_useronline');
}

### Function: Display UserOnline For Admin
function display_useronline() {
	echo '<div class="wrap">'."\n";
	screen_icon();
	echo '<h2>'.__('Users Online Now', 'wp-useronline').'</h2>'."\n";
	echo useronline_page();
	echo '</div>'."\n";
}

add_action('rightnow_end', 'useronline_rightnow');
function useronline_rightnow() {
	$total_users = get_useronline_count(false);

	$str = _n(
		__('There is <strong><a href="%s">%s user</a></strong> online now.', 'wp-useronline'),
		__('There are a total of <strong><a href="%s">%s users</a></strong> online now.', 'wp-useronline'),
		$total_users
	);

	echo '<p>';
	printf($str, admin_url('index.php?page=wp-useronline/wp-useronline.php'), number_format_i18n($total_users));

	echo '<br />';
	get_users_browsing_site();
	echo '.<br />';
	echo _useronline_most_users();
	echo '</p>'."\n";
}

