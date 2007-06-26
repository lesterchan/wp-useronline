<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.1 Plugin: WP-UserOnline 2.20								|
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
$mode = trim($_GET['mode']);
$useronline_tables = array($wpdb->useronline);
$useronline_settings = array('useronline_most_users', 'useronline_most_timestamp', 'useronline_timeout', 'useronline_bots', 'useronline_url', 'widget_useronline');


### Form Processing 
if(!empty($_POST['do'])) {
	// Decide What To Do
	switch($_POST['do']) {
		case __('Update Options', 'wp-useronline'):
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
			break;
		// Uninstall WP-UserOnline
		case __('UNINSTALL WP-UserOnline', 'wp-useronline') :
			if(trim($_POST['uninstall_useronline_yes']) == 'yes') {
				echo '<div id="message" class="updated fade">';
				echo '<p>';
				foreach($useronline_tables as $table) {
					$wpdb->query("DROP TABLE {$table}");
					echo '<font style="color: green;">';
					printf(__('Table \'%s\' has been deleted.', 'wp-useronline'), "<strong><em>{$table}</em></strong>");
					echo '</font><br />';
				}
				echo '</p>';
				echo '<p>';
				foreach($useronline_settings as $setting) {
					$delete_setting = delete_option($setting);
					if($delete_setting) {
						echo '<font color="green">';
						printf(__('Setting Key \'%s\' has been deleted.', 'wp-useronline'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					} else {
						echo '<font color="red">';
						printf(__('Error deleting Setting Key \'%s\'.', 'wp-useronline'), "<strong><em>{$setting}</em></strong>");
						echo '</font><br />';
					}
				}
				echo '</p>';
				echo '</div>'; 
				$mode = 'end-UNINSTALL';
			}
			break;
	}
}


### Determines Which Mode It Is
switch($mode) {
		//  Deactivating WP-UserOnline
		case 'end-UNINSTALL':
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=useronline/useronline.php';
			if(function_exists('wp_nonce_url')) { 
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_useronline/useronline.php');
			}
			echo '<div class="wrap">';
			echo '<h2>'.__('Uninstall WP-UserOnline', 'wp-useronline').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-UserOnline Will Be Deactivated Automatically.', 'wp-useronline'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
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
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
<div class="wrap"> 
	<h2><?php _e('Useronline Options', 'wp-useronline'); ?></h2> 
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
					<input type="text" name="useronline_url" value="<?php echo get_option('useronline_url'); ?>" size="50" /><br /><?php _e('URL To UserOnline Page (leave blank if you do not want to link it to the UserOnline Page)<br />Example: http://www.yoursite.com/blogs/useronline/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-useronline'); ?>
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
		<input type="submit" name="do" class="button" value="<?php _e('Update Options', 'wp-useronline'); ?>" />&nbsp;&nbsp;<input type="button" name="cancel" value="<?php _e('Cancel', 'wp-useronline'); ?>" class="button" onclick="javascript:history.go(-1)" /> 
	</div>
</div>
</form>

<!-- Uninstall WP-UserOnline -->
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
<div class="wrap"> 
	<h2><?php _e('Uninstall WP-UserOnline', 'wp-useronline'); ?></h2>
	<p style="text-align: left;">
		<?php _e('Deactivating WP-UserOnline plugin does not remove any data that may have been created, such as the useronline options. To completely remove this plugin, you can uninstall it here.', 'wp-useronline'); ?>
	</p>
	<p style="text-align: left; color: red">
		<strong><?php _e('WARNING:', 'wp-useronline'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-useronline'); ?>
	</p>
	<p style="text-align: left; color: red">
		<strong><?php _e('The following WordPress Options/Tables will be DELETED:', 'wp-useronline'); ?></strong><br />
	</p>
	<table width="70%"  border="0" cellspacing="3" cellpadding="3">
		<tr class="thead">
			<td align="center"><strong><?php _e('WordPress Options', 'wp-useronline'); ?></strong></td>
			<td align="center"><strong><?php _e('WordPress Tables', 'wp-useronline'); ?></strong></td>
		</tr>
		<tr>
			<td valign="top" style="background-color: #eee;">
				<ol>
				<?php
					foreach($useronline_settings as $settings) {
						echo '<li>'.$settings.'</li>'."\n";
					}
				?>
				</ol>
			</td>
			<td valign="top" style="background-color: #eee;">
				<ol>
				<?php
					foreach($useronline_tables as $tables) {
						echo '<li>'.$tables.'</li>'."\n";
					}
				?>
				</ol>
			</td>
		</tr>
	</table>
	<p>&nbsp;</p>
	<p style="text-align: center;">
		<input type="checkbox" name="uninstall_useronline_yes" value="yes" />&nbsp;<?php _e('Yes', 'wp-useronline'); ?><br /><br />
		<input type="submit" name="do" value="<?php _e('UNINSTALL WP-UserOnline', 'wp-useronline'); ?>" class="button" onclick="return confirm('<?php _e('You Are About To Uninstall WP-UserOnline From WordPress.\nThis Action Is Not Reversible.\n\n Choose [Cancel] To Stop, [OK] To Uninstall.', 'wp-useronline'); ?>')" />
	</p>
</div> 
</form>
<?php
} // End switch($mode)
?>