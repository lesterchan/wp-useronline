<?php

### Function: WP-UserOnline Menu
add_action('admin_menu', 'useronline_menu');
function useronline_menu() {
	add_submenu_page('index.php',  __('WP-UserOnline', 'wp-useronline'),  __('WP-UserOnline', 'wp-useronline'), 'read', 'wp-useronline/wp-useronline.php', 'display_useronline');

	add_options_page(__('UserOnline', 'wp-useronline'), __('UserOnline', 'wp-useronline'), 'manage_options', 'wp-useronline/useronline-options.php');
}

### Function: Display UserOnline For Admin
function display_useronline() {
	echo '<div class="wrap">'."\n";
	screen_icon();
	echo '<h2>'.__('Users Online Now', 'wp-useronline').'</h2>'."\n";
	echo useronline_page();
	echo '</div>'."\n";
}

