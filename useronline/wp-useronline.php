<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.0 Plugin: WP-UserOnline 2.04								|
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
require('../../../wp-blog-header.php');

### Function: UserOnline Page Title
add_filter('wp_title', 'useronline_pagetitle');
function useronline_pagetitle($useronline_pagetitle) {
	return $useronline_pagetitle.' &raquo; UserOnline';
}

### Reassign Bots Name
$bots = get_settings('useronline_bots');
$bots_name = array();
foreach($bots as $botname => $botlookfor) {
	$bots_name[] = $botname;
}

### Get The Users Online
$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline");

### Variables Variables Variables
$members = array();
$guests = array();
$bots = array();
$total_users = 0;
$total_members = 0;
$total_guests = 0;
$total_bots = 0;
$nicetext_users = '';
$nicetext_members = '';
$nicetext_guests = '';
$nicetext_bots = '';

### Process Those User Who Is Online
if($usersonline) {
	foreach($usersonline as $useronline) {
		if($useronline->username == 'Guest') {
			$guests[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total_guests++;
		} elseif(in_array($useronline->username, $bots_name)) {
			$bots[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total_bots++;
		} else {
			$members[] = array('username' => stripslashes($useronline->username), 'timestamp' => $useronline->timestamp, 'ip' => $useronline->ip, 'location' => stripslashes($useronline->location), 'url' => stripslashes(urldecode($useronline->url)));
			$total_members++;
		}
	}
	$total_users = ($total_guests+$total_bots+$total_members);
}

###  Nice Text For Users
if($total_users == 1) {
	$nicetext_users = $total_users.' '.__('User');
} else {
	$nicetext_users = number_format($total_users).' '.__('Users');
}

###  Nice Text For Members
if($total_members == 1) {
	$nicetext_members = $total_members.' '.__('Member');
} else {
	$nicetext_members = number_format($total_members).' '.__('Members');
}


###  Nice Text For Guests
if($total_guests == 1) { 
	$nicetext_guests = $total_guests.' '.__('Guest');
} else {
	$nicetext_guests = number_format($total_guests).' '.__('Guests'); 
}

###  Nice Text For Bots
if($total_bots == 1) {
	$nicetext_bots = $total_bots.' '.__('Bot'); 
} else {
	$nicetext_bots = number_format($total_bots).' '.__('Bots'); 
}
?>
<?php get_header(); ?>
	<div id="content" class="narrowcolumn">
		<p><?php if ($total_users == 1) { _e('There is '); } else { _e('There are a total of '); } ?><b><?php echo $nicetext_users; ?></b> online now: <b><?php echo $nicetext_members; ?></b>, <b><?php echo $nicetext_guests; ?></b> and <b><?php echo $nicetext_bots; ?></b>.</p>
		<p>Most users ever online were <b><?php get_most_useronline(); ?></b>, on <b><?php get_most_useronline_date(); ?></b></p>
		<?php
			// Print Out Members
			if($total_members > 0) {
				echo 	'<h2 class="pagetitle">'.$nicetext_members.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($members) {
				$wp_stats = false;
				if(function_exists('get_totalposts')) {
					$wp_stats = true;
				}
				foreach($members as $member) {
					if($wp_stats) {
						echo '<p><b>#'.$no.' - <a href="'.get_settings('home').'/wp-stats.php?author='.$member['username'].'">'.$member['username'].'</a></b> '.ip2nation_country($member['ip']).check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					} else {
						echo '<p><b>#'.$no.' - '.$member['username'].'</b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</p>'."\n";
					}
					$no++;
				}
			}
			// Print Out Guest
			if($total_guests > 0) {
				echo 	'<h2 class="pagetitle">'.$nicetext_guests.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($guests) {
				foreach($guests as $guest) {
					echo '<p><b>#'.$no.' - '.$guest['username'].'</b> '.ip2nation_country($guest['ip']).check_ip($guest['ip']).' on '.gmdate('d.m.Y @ H:i', $guest['timestamp']).'<br />'.$guest['location'].' [<a href="'.$guest['url'].'">url</a>]</p>'."\n";
					$no++;
				}
			}
			// Print Out Bots
			if($total_bots > 0) {
				echo 	'<h2 class="pagetitle">'.$nicetext_bots.' '.__('Online Now').'</h2>'."\n";
			}
			$no=1;
			if($bots) {
				foreach($bots as $bot) {
					echo '<p><b>#'.$no.' - '.$bot['username'].'</b> '.check_ip($bot['ip']).' on '.gmdate('d.m.Y @ H:i', $bot['timestamp']).'<br />'.$bot['location'].' [<a href="'.$bot['url'].'">url</a>]</p>'."\n";
					$no++;
				}
			}
			if($total_users == 0) {
				echo 	'<h2 class="pagetitle">'.__('No One Is Online Now').'</h2>'."\n";
			}
		?>
	</div>
<?php get_sidebar(); ?>
<?php get_footer(); ?>