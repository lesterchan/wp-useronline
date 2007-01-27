<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.1 Plugin: WP-UserOnline 2.10								|
|	Copyright (c) 2007 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://www.lesterchan.net													|
|																							|
|	File Information:																	|
|	- Useronline Options Page														|
|	- wp-content/plugins/useronline/useronline-options.php				|
|																							|
+----------------------------------------------------------------+
*/


### Variables Variables Variables
$base_name = plugin_basename('useronline/useronline-options.php');
$base_page = 'admin.php?page='.$base_name;

### If Form Is Submitted
if($_POST['Submit']) {
	$useronline_bots = array();
	$useronline_timeout = intval($_POST['useronline_timeout']);
	$useronline_bots_name = explode("\n", trim($_POST['useronline_bots_name']));
	$useronline_bots_agent = explode("\n", trim($_POST['useronline_bots_agent']));	
	$useronline_bots_keys = array_values((array) $useronline_bots_name);
	$useronline_bots_vals = array_values((array) $useronline_bots_agent);
	$n = max(count($useronline_bots_keys), count($useronline_bots_vals));
		for($i = 0; $i < $n; $i++) {
		$useronline_bots[trim($useronline_bots_keys[$i])] = trim($useronline_bots_vals[$i]);
	}
	$useronline_url = addslashes(trim($_POST['useronline_url']));
	$update_useronline_queries = array();
	$update_useronline_text = array();
	$update_useronline_queries[] = update_option('useronline_timeout', $useronline_timeout);
	$update_useronline_queries[] = update_option('useronline_bots', $useronline_bots);
	$update_useronline_queries[] = update_option('useronline_url', $useronline_url);
	$update_useronline_text[] = __('Useronline Timeout', 'wp-useronline');
	$update_useronline_text[] = __('Useronline Bots', 'wp-useronline');
	$update_useronline_text[] = __('Useronline URL', 'wp-useronline');
	$i=0;
	$text = '';
	foreach($update_useronline_queries as $update_useronline_query) {
		if($update_useronline_query) {
			$text .= '<font color="green">'.$update_useronline_text[$i].' '.__('Updated', 'wp-useronline').'</font><br />';
		}
		$i++;
	}
	if(empty($text)) {
		$text = '<font color="red">'.__('No Useronline Option Updated', 'wp-useronline').'</font>';
	}
}

### Get Useronline Bots
$useronline_options_bots = get_option('useronline_bots');
$useronline_options_bots_name = '';
$useronline_options_bots_agent = '';
foreach($useronline_options_bots as $botname => $botagent) {
	$useronline_options_bots_name .= $botname."\n";
	$useronline_options_bots_agent .= $botagent."\n";
}
$useronline_options_bots_name = trim($useronline_options_bots_name);
$useronline_options_bots_agent = trim($useronline_options_bots_agent);
?>
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<div class="wrap"> 
	<h2><?php _e('Useronline Options', 'wp-useronline'); ?></h2> 
	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
		<fieldset class="options">
			<legend><?php _e('Useronline Options', 'wp-useronline'); ?></legend>
			<table width="100%"  border="0" cellspacing="3" cellpadding="3">
				 <tr valign="top">
					<th align="left" width="30%"><?php _e('Time Out', 'wp-useronline'); ?></th>
					<td align="left">
						<input type="text" name="useronline_timeout" value="<?php echo get_option('useronline_timeout'); ?>" size="4" /><br /><?php _e('How long till it will remove the user from the database (In seconds).', 'wp-useronline'); ?>
					</td>
				</tr>
				 <tr valign="top">
					<th align="left" width="30%"><?php _e('UserOnline URL', 'wp-useronline'); ?></th>
					<td align="left">
						<input type="text" name="useronline_url" value="<?php echo get_option('useronline_url'); ?>" size="50" /><br /><?php _e('URL To UserOnline Page<br />Example: http://www.yoursite.com/blogs/useronline/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-useronline'); ?>
					</td>
				</tr>
				<tr valign="top"> 
					<th align="left" width="30%"><?php _e('Bots Name/User Agent', 'wp-useronline'); ?></th>
					<td align="left">
						<?php _e('Here are a list of bots and their partial browser agents.<br />On the left column will be the <strong>Bot\'s Name</strong> and on the right column will be their <strong>Partial Browser Agent</strong>.<br />Start each entry on a new line.', 'wp-useronline'); ?>
						<br /><br />
						<textarea cols="20" rows="30" name="useronline_bots_name"><?php echo $useronline_options_bots_name; ?></textarea>
						<textarea cols="20" rows="30" name="useronline_bots_agent"><?php echo $useronline_options_bots_agent; ?></textarea>						
					</td> 
				</tr>
			</table>
		</fieldset>
		<div align="center">
			<input type="submit" name="Submit" class="button" value="<?php _e('Update Options', 'wp-useronline'); ?>" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-useronline'); ?>" class="button" onclick="javascript:history.go(-1)" /> 
		</div>
	</form> 
</div> 