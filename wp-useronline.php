<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.0 Plugin: WP-UserOnline 2.01								|
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

### Reassign Bots Name
$bots_name = array();
foreach($bots as $botname => $botlookfor) {
	$bots_name[] = $botname;
}

### Get The Users Online
$usersonline = $wpdb->get_results("SELECT * FROM $wpdb->useronline");

### Type Of Users Array
$members = array();
$guests = array();
$bots = array();

### Users Count
$total = array();
$total['users'] = 0;
$total['members'] = 0;
$total['guests'] = 0;
$total['bots'] = 0;

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
	$total['users'] = ($total['guests']+$total['bots']+$total['members']);
}

### Nice Text For Bots, Guest And Members
$nicetext = array();
$nicetext['users'] = '';
$nicetext['members'] = '';
$nicetext['guests'] = '';
$nicetext['bots'] = '';

###  Nice Text For Users
if($total['users'] > 1) {
	$nicetext['users'] = $total['users'].' '.__('Users');
} else {
	$nicetext['users'] = $total['users'].' '.__('User');
}

###  Nice Text For Members
if($total['members'] > 1) {
	$nicetext['members'] = $total['members'].' '.__('Members');
} else {
	$nicetext['members'] = $total['members'].' '.__('Member');
}


###  Nice Text For Guests
if($total['guests'] > 1) { 
	$nicetext['guests'] = $total['guests'].' '.__('Guests');
} else {
	$nicetext['guests'] = $total['guests'].' '.__('Guest'); 
}

###  Nice Text For Bots
if($total['bots'] > 1) {
	$nicetext['bots'] = $total['bots'].' '.__('Bots'); 
} else {
	$nicetext['bots'] = $total['bots'].' '.__('Bot'); 
}

### Function: Check IP
function check_ip($ip) {
	if(isset($_COOKIE['wordpressuser_'.COOKIEHASH])) {
		return "(<a href=\"http://ws.arin.net/cgi-bin/whois.pl?queryinput=$ip\" target=\"_blank\" title=\"".gethostbyaddr($ip)."\">$ip</a>)";
	}
}
?>
<?php get_header(); ?>
	<div id="content" class="narrowcolumn">
		<p>There are a total of <b><?php echo $nicetext['users']; ?></b> online now.</p>
		<p>Out of which, there are <b><?php echo $nicetext['members']; ?></b>, <b><?php echo $nicetext['guests']; ?></b> and <b><?php echo $nicetext['bots']; ?></b>.</p>
		<p>Most users ever online was <b><?php get_most_useronline(); ?></b> on <b><?php get_most_useronline_date(); ?></b></p>
		<table width="100%" border="0" cellspacing="1" cellpadding="5">
		<?php
			// Print Out Members
			if($total['members'] > 0) {
				echo 	'<tr><td><h2 class="pagetitle">'.$nicetext['members'].' '.__('Online Now').'</h2></td></tr>'."\n";
			}
			$no=1;
			if($members) {
				foreach($members as $member) {
					echo '<tr>'."\n";
					echo '<td><b>#'.$no.' - <a href="'.get_settings('home').'/wp-stats.php?author='.$member['username'].'">'.$member['username'].'</a></b> '.check_ip($member['ip']).' on '.gmdate('d.m.Y @ H:i', $member['timestamp']).'<br />'.$member['location'].' [<a href="'.$member['url'].'">url</a>]</td>'."\n";
					echo '</tr>'."\n";
					$no++;
				}
			}
			// Print Out Guest
			if($total['guests'] > 0) {
				echo 	'<tr><td><h2 class="pagetitle">'.$nicetext['guests'].' '.__('Online Now').'</h2></td></tr>'."\n";
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
				echo 	'<tr><td><h2 class="pagetitle">'.$nicetext['bots'].' '.__('Online Now').'</h2></td></tr>'."\n";
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
			if($total['users'] == 0) {
				echo 	'<tr><td><h2 class="pagetitle">'.__('No One Is Online Now').'</h2></td></tr>'."\n";
			}
		?>
		</table>
	</div>
<?php get_sidebar(); ?>
<?php get_footer(); ?>