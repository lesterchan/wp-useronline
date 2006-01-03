<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.0 Plugin: WP-UserOnline 2.00								|
|	Copyright (c) 2005 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://www.lesterchan.net													|
|																							|
|	File Information:																	|
|	- Useronline Page																	|
|	- wp-useronline.php																|
|																							|
+----------------------------------------------------------------+
*/


### Require WordPress Header
require(dirname(__FILE__).'/wp-blog-header.php');

### Function: UserOnline Page Title
add_filter('wp_title', 'useronline_pagetitle');
function useronline_pagetitle($useronline_pagetitle) {
	return $useronline_pagetitle.' &raquo; UserOnline';
}

### Search Bots Array
 $bots = array('Google Bot' => 'googlebot', 'MSN' => 'msnbot', 'Alex' => 'ia_archiver', 'Lycos' => 'lycos', 'Ask Jeeves' => 'jeeves', 'Altavista' => 'scooter', 'AllTheWeb' => 'fast-webcrawler', 'Inktomi' => 'slurp@inktomi', 'Turnitin.com' => 'turnitinbot', 'Technorati' => 'technorati', 'Yahoo' => 'yahoo', 'Findexa' => 'findexa', 'NextLinks' => 'findlinks', 'Gais' => 'gaisbo', 'WiseNut' => 'zyborg', 'WhoisSource' => 'surveybot', 'Bloglines' => 'bloglines', 'BlogSearch' => 'blogsearch', 'PubSub' => 'ubsub', 'Syndic8' => 'syndic8', 'RadioUserland' => 'userland', 'Gigabot' => 'gigabot');

### Reassign Bots Name
$bots_name = array();
foreach($bots as $botname => $botlookfor) {
	$bots_name[] = $botname;
}

### Get The Users Online
$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline");

### Type Of Users Array
$bots = array();
$guests = array();
$members = array();

### Users Count
$total = array();
$total['bots'] = 0;
$total['guests'] = 0;
$total['members'] = 0;

### Process Those User Who Is Online
if($usersonline) {
	foreach($usersonline as $useronline) {
		if($useronline->username == 'Guest') {
			$guests[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total['guests']++;
		} elseif(in_array($useronline->username, $bots_name)) {
			$bots[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total['bots']++;
		} else {
			$members[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total['members']++;
		}
	}
}

### Nice Text For Bots, Guest And Members
$nicetext = array();
if($total['bots'] > 1) { $nicetext['bots'] = __('Bots'); } else {	$nicetext['bots'] = __('Bot'); }
if($total['guests'] > 1) { $nicetext['guests'] = __('Guests'); } else { $nicetext['guests'] = __('Guest'); }
if($total['members'] > 1) { $nicetext['members'] = __('Members'); } else { $nicetext['members'] = __('Member'); }

### Function: Check IP
function check_ip($ip) {
	if(isset($_COOKIE['wordpressuser_'.COOKIEHASH])) {
		return "(<a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$ip\" target=\"_blank\" title=\"".gethostbyaddr($ip)."\">$ip</a>)";
	}
}
?>
<?php get_header(); ?>
	<div id="content" class="narrowcolumn">
		<p><?php _e('There Are A Total Of'); ?> <b><?php echo$total['members'].' '.$nicetext['members']; ?></b>, <b><?php echo$total['guests'].' '.$nicetext['guests']; ?></b> <?php _e('And'); ?> <b><?php echo$total['bots'].' '.$nicetext['bots']; ?></b> <?php _e('Online Now'); ?>.<b></b> </p>
		<table width="100%" border="0" cellspacing="1" cellpadding="5">
		<?php 
				if($total['members'] > 0) {
					echo 	'<tr><td><h2 class="pagetitle">'.$total['members'].' '.$nicetext['members'].' '.__('Online Now').'</h2></td></tr>'."\n";
				}
		?>
				<?php
					$no=1;
					if($members) {
						foreach($members as $member) {
							echo '<tr>'."\n";
							echo '<td><b>#'.$no.' - <a href="wp-stats.php?author='.$member['username'].'">'.$member['username'].'</a></b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</td>'."\n";
							echo '</tr>'."\n";
							$no++;
						}
					}
					// Print Out Guest
					if($total['guests'] > 0) {
						echo 	'<tr><td><h2 class="pagetitle">'.$total['guests'].' '.$nicetext['guests'].' '.__('Online Now').'</h2></td></tr>'."\n";
					}
					$no=1;
					if($guests) {
						foreach($guests as $guest) {
							echo '<tr>'."\n";
							echo '<td><b>#'.$no.' - '.$guest['username'].'</b> '.check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</td>'."\n";
							echo '</tr>'."\n";
							$no++;
						}
					}
					// Print Out Bots
					if($total['bots'] > 0) {
						echo 	'<tr><td><h2 class="pagetitle">'.$total['bots'].' '.$nicetext['bots'].' '.__('Online Now').'</h2></td></tr>'."\n";
					}
					$no=1;
					if($bots) {
						foreach($bots as $bot) {
							echo '<tr>'."\n";
							echo '<td><b>#'.$no.' - '.$bot['username'].'</b> '.check_ip($bot['ip']).' on '.gmdate('d.m.Y @ H:i', $bot['timestamp']).'<br />'.$bot['location'].' [<a href="'.$bot['url'].'">url</a>]</td>'."\n";
							echo '</tr>'."\n";
							$no++;
						}
					}
					if($total['members'] == 0 && $total['guests'] == 0 && $total['bots']) {
						echo 	'<tr><td><h2 class="pagetitle">'.__('No One Is Online Now').'</h2></td></tr>'."\n";
					}
				?>
				</table>
	</div>
<?php get_sidebar(); ?>
<?php get_footer(); ?>