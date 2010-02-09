<?php

### Function: Stats Page Link
add_filter('useronline_display_name', 'useronline_stats_page_link');
function useronline_stats_page_link($author) {
	$stats_url = add_query_arg('stats_author', urlencode($author), get_option('stats_url'));

	return '<a href="'.$stats_url.'" title="'.$author.'">'.$author.'</a>';
}

### Function: Plug Into WP-Stats
if ( strpos(get_option('stats_url' ), $_SERVER['REQUEST_URI']) || strpos($_SERVER['REQUEST_URI'], 'stats-options.php') || strpos($_SERVER['REQUEST_URI'], 'wp-stats/wp-stats.php')) {
	add_filter('wp_stats_page_admin_plugins', 'useronline_page_admin_general_stats');
	add_filter('wp_stats_page_plugins', 'useronline_page_general_stats');
}


### Function: Add WP-UserOnline General Stats To WP-Stats Page Options
function useronline_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if ( $stats_display['useronline'] == 1 ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline" checked="checked" />&nbsp;&nbsp;<label for="wpstats_useronline">'.__('WP-UserOnline', 'wp-useronline').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline" />&nbsp;&nbsp;<label for="wpstats_useronline">'.__('WP-UserOnline', 'wp-useronline').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-UserOnline General Stats To WP-Stats Page
function useronline_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if ( $stats_display['useronline'] == 1 ) {
		$content .= '<p><strong>'.__('WP-UserOnline', 'wp-useronline').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> user online now.', '<strong>%s</strong> users online now.', get_useronline_count(), 'wp-useronline'), number_format_i18n(get_useronline_count())).'</li>'."\n";
		$content .= '<li>'.sprintf(_n('Most users ever online was <strong>%s</strong>.', 'Most users ever online was <strong>%s</strong>.', get_most_useronline(), 'wp-useronline'), number_format_i18n(get_most_useronline())).'</li>'."\n";
		$content .= '<li>'.__('On', 'wp-useronline').' <strong>'.get_most_useronline_date().'</strong>.</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}

