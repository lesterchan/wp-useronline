<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.8 Plugin: WP-UserOnline 2.50								|
|	Copyright (c) 2009 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Useronline Options Page														|
|	- wp-content/plugins/wp-useronline/useronline-options.php			|
|																							|
+----------------------------------------------------------------+
*/


### Variables Variables Variables
$base_name = plugin_basename('wp-useronline/useronline-options.php');
$base_page = 'admin.php?page='.$base_name;
$mode = trim($_GET['mode']);
$useronline_tables = array($wpdb->useronline);
$useronline_settings = array('useronline_most_users', 'useronline_most_timestamp', 'useronline_timeout', 'useronline_bots', 'useronline_url', 'useronline_naming', 'useronline_template_useronline', 'useronline_template_browsingsite', 'useronline_template_browsingpage', 'widget_useronline');


### Form Processing
// Update Options
if(!empty($_POST['Submit'])) {
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
	$useronline_url = trim($_POST['useronline_url']);
	$useronline_naming_user = trim($_POST['useronline_naming_user']);
	$useronline_naming_users = trim($_POST['useronline_naming_users']);
	$useronline_naming_member = trim($_POST['useronline_naming_member']);
	$useronline_naming_members = trim($_POST['useronline_naming_members']);
	$useronline_naming_guest = trim($_POST['useronline_naming_guest']);
	$useronline_naming_guests = trim($_POST['useronline_naming_guests']);
	$useronline_naming_bot = trim($_POST['useronline_naming_bot']);
	$useronline_naming_bots = trim($_POST['useronline_naming_bots']);
	$useronline_naming = array('user' => $useronline_naming_user, 'users' => $useronline_naming_users, 'member' => $useronline_naming_member, 'members' => $useronline_naming_members, 'guest' => $useronline_naming_guest, 'guests' => $useronline_naming_guests, 'bot' => $useronline_naming_bot, 'bots' => $useronline_naming_bots);
	$useronline_template_useronline = trim($_POST['useronline_template_useronline']);
	$useronline_template_browsingsite = array($_POST['useronline_separator_browsingsite_members'], $_POST['useronline_separator_browsingsite_guests'], $_POST['useronline_separator_browsingsite_bots'], trim($_POST['useronline_template_browsingsite']));
	$useronline_template_browsingpage = array($_POST['useronline_separator_browsingpage_members'], $_POST['useronline_separator_browsingpage_guests'], $_POST['useronline_separator_browsingpage_bots'], trim($_POST['useronline_template_browsingpage']));
	$update_useronline_queries = array();
	$update_useronline_text = array();
	$update_useronline_queries[] = update_option('useronline_timeout', $useronline_timeout);
	$update_useronline_queries[] = update_option('useronline_bots', $useronline_bots);
	$update_useronline_queries[] = update_option('useronline_url', $useronline_url);
	$update_useronline_queries[] = update_option('useronline_naming', $useronline_naming);
	$update_useronline_queries[] = update_option('useronline_template_useronline', $useronline_template_useronline);
	$update_useronline_queries[] = update_option('useronline_template_browsingsite', $useronline_template_browsingsite);
	$update_useronline_queries[] = update_option('useronline_template_browsingpage', $useronline_template_browsingpage);
	$update_useronline_text[] = __('Useronline Timeout', 'wp-useronline');
	$update_useronline_text[] = __('Useronline Bots', 'wp-useronline');
	$update_useronline_text[] = __('Useronline URL', 'wp-useronline');
	$update_useronline_text[] = __('Useronline Naming Conventions', 'wp-useronline');
	$update_useronline_text[] = __('User(s) Online Template', 'wp-useronline');
	$update_useronline_text[] = __('User(s) Browsing Site Template', 'wp-useronline');
	$update_useronline_text[] = __('User(s) Browsing Page Template', 'wp-useronline');
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
// Uninstall WP-UserOnline
if(!empty($_POST['do'])) {
	switch($_POST['do']) {		
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
			$deactivate_url = 'plugins.php?action=deactivate&amp;plugin=wp-useronline/wp-useronline.php';
			if(function_exists('wp_nonce_url')) { 
				$deactivate_url = wp_nonce_url($deactivate_url, 'deactivate-plugin_wp-useronline/wp-useronline.php');
			}
			echo '<div class="wrap">';
			echo '<h2>'.__('Uninstall WP-UserOnline', 'wp-useronline').'</h2>';
			echo '<p><strong>'.sprintf(__('<a href="%s">Click Here</a> To Finish The Uninstallation And WP-UserOnline Will Be Deactivated Automatically.', 'wp-useronline'), $deactivate_url).'</strong></p>';
			echo '</div>';
			break;
	// Main Page
	default:
		$useronline_options_naming = get_option('useronline_naming');
		$useronline_options_bots = get_option('useronline_bots');
		$useronline_template_browsingsite = get_option('useronline_template_browsingsite');
		$useronline_template_browsingpage = get_option('useronline_template_browsingpage');
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
<script type="text/javascript">
/* <![CDATA[*/
	function useronline_default_templates(template) {
		var default_template;
		switch(template) {
			case "useronline":
				default_template = "<a href=\"%USERONLINE_PAGE_URL%\" title=\"%USERONLINE_USERS%\"><strong>%USERONLINE_USERS%</strong> <?php _e('Online', 'wp-useronline'); ?></a>";
				break;
		}
		jQuery("#useronline_template_" + template).val(default_template);
	}

	function useronline_default_naming() {
		jQuery("#useronline_naming_user").val("<?php _e('1 User', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_users").val("<?php _e('%USERONLINE_COUNT% Users', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_member").val("<?php _e('1 Member', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_members").val("<?php _e('%USERONLINE_COUNT% Members', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_guest").val("<?php _e('1 Guest', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_guests").val("<?php _e('%USERONLINE_COUNT% Guests', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_bot").val("<?php _e('1 Bot', 'wp-useronline'); ?>");
		jQuery("#useronline_naming_bots").val("<?php _e('%USERONLINE_COUNT% Bots', 'wp-useronline'); ?>");
	}
	function useronline_default_browsing_site() {
		jQuery("#useronline_separator_browsingsite_members").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_separator_browsingsite_guests").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_separator_browsingsite_bots").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_template_browsingsite").val("<?php echo(_c('Users|Template Element', 'wp-useronline')); ?>: <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>");
	}
	function useronline_default_browsing_page() {
		jQuery("#useronline_separator_browsingpage_members").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_separator_browsingpage_guests").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_separator_browsingpage_bots").val("<?php _e(',', 'wp-useronline') ?> ");
		jQuery("#useronline_template_browsingpage").val("<strong>%USERONLINE_USERS%</strong> <?php _e('Browsing This Page.', 'wp-useronline'); ?><br /><?php echo(_c('Users|Template Element', 'wp-useronline')); ?>: <strong>%USERONLINE_MEMBER_NAMES%%USERONLINE_GUESTS_SEPERATOR%%USERONLINE_GUESTS%%USERONLINE_BOTS_SEPERATOR%%USERONLINE_BOTS%</strong>");
	}
/* ]]> */
</script>
<form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php _e('Useronline Options', 'wp-useronline'); ?></h2>
	<h3><?php _e('Useronline Options', 'wp-useronline'); ?></h3>
	<table class="form-table">
		 <tr>
			<th scope="row" valign="top"><?php _e('Time Out', 'wp-useronline'); ?></th>
			<td>
				<input type="text" name="useronline_timeout" value="<?php echo get_option('useronline_timeout'); ?>" size="4" /><br /><?php _e('How long till it will remove the user from the database (In seconds).', 'wp-useronline'); ?>
			</td>
		</tr>
		 <tr>
			<th scope="row" valign="top"><?php _e('UserOnline URL', 'wp-useronline'); ?></th>
			<td>
				<input type="text" name="useronline_url" value="<?php echo get_option('useronline_url'); ?>" size="50" dir="ltr" /><br /><?php _e('URL To UserOnline Page (leave blank if you do not want to link it to the UserOnline Page)<br />Example: http://www.yoursite.com/blogs/useronline/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-useronline'); ?>
			</td>
		</tr>
		<tr> 
			<th scope="row" valign="top"><?php _e('Bots Name/User Agent', 'wp-useronline'); ?></th>
			<td>
				<?php _e('Here are a list of bots and their partial browser agents.<br />On the left column will be the <strong>Bot\'s Name</strong> and on the right column will be their <strong>Partial Browser Agent</strong>.<br />Start each entry on a new line.', 'wp-useronline'); ?>
				<br /><br />
				<textarea cols="20" rows="30" name="useronline_bots_name" dir="ltr"><?php echo $useronline_options_bots_name; ?></textarea>
				<textarea cols="20" rows="30" name="useronline_bots_agent" dir="ltr"><?php echo $useronline_options_bots_agent; ?></textarea>						
			</td> 
		</tr>
		<tr> 
			<td width="30%">
				<strong><?php _e('Naming Conventions:', 'wp-useronline'); ?></strong><br /><br /><br />
				<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
				- %USERONLINE_COUNT%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_naming();" class="button" />
			</td>
			<td>
				<table class="form-table">
					<thead>
						 <tr>
							<th><?php _e('Singular Form', 'wp-useronline'); ?></th>
							<th><?php _e('Plural Form', 'wp-useronline'); ?></th>
						 </tr>
					 </thead>
					 <tbody>
						 <tr>
							<td><input type="text" id="useronline_naming_user" name="useronline_naming_user" value="<?php echo stripslashes($useronline_options_naming['user']); ?>" size="20" /></td>
							<td><input type="text" id="useronline_naming_users" name="useronline_naming_users" value="<?php echo stripslashes($useronline_options_naming['users']); ?>" size="40" /></td>
						 </tr>
						 <tr>
							<td><input type="text" id="useronline_naming_member" name="useronline_naming_member" value="<?php echo stripslashes($useronline_options_naming['member']); ?>" size="20" /></td>
							<td><input type="text" id="useronline_naming_members" name="useronline_naming_members" value="<?php echo stripslashes($useronline_options_naming['members']); ?>" size="40" /></td>
						 </tr>
						 <tr>
							 <td><input type="text" id="useronline_naming_guest" name="useronline_naming_guest" value="<?php echo stripslashes($useronline_options_naming['guest']); ?>" size="20" /></td>
							<td><input type="text" id="useronline_naming_guests" name="useronline_naming_guests" value="<?php echo stripslashes($useronline_options_naming['guests']); ?>" size="40" /></td>
						 </tr>
						 <tr>
							<td><input type="text" id="useronline_naming_bot" name="useronline_naming_bot" value="<?php echo stripslashes($useronline_options_naming['bot']); ?>" size="20" /></td>
							<td><input type="text" id="useronline_naming_bots" name="useronline_naming_bots" value="<?php echo stripslashes($useronline_options_naming['bots']); ?>" size="40" /></td>
						 </tr>
					 </tbody>
				</table>
				<br />
			</td> 
		</tr>
	</table>

	<h3><?php _e('Useronline Templates', 'wp-useronline'); ?></h3>
	<table class="form-table">
		 <tr>
			<td width="30%">
				<strong><?php _e('User(s) Online:', 'wp-useronline'); ?></strong><br /><br /><br />
				<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
				- %USERONLINE_USERS%<br />
				- %USERONLINE_PAGE_URL%<br />
				- %USERONLINE_MOSTONLINE_COUNT%<br />
				- %USERONLINE_MOSTONLINE_DATE%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_templates('useronline');" class="button" />
			</td>
			<td><textarea cols="80" rows="12" id="useronline_template_useronline" name="useronline_template_useronline"><?php echo htmlspecialchars(stripslashes(get_option('useronline_template_useronline'))); ?></textarea></td>
		</tr>
		 <tr>
			<td width="30%">
				<strong><?php _e('User(s) Browsing Site:', 'wp-useronline'); ?></strong><br /><br /><br />
				<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
				- %USERONLINE_USERS%<br />					
				- %USERONLINE_MEMBERS%<br />
				- %USERONLINE_MEMBER_NAMES%<br />
				- %USERONLINE_GUESTS_SEPERATOR%<br />	
				- %USERONLINE_GUESTS%<br />
				- %USERONLINE_BOTS_SEPERATOR%<br />
				- %USERONLINE_BOTS%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_browsing_site();" class="button" />
			</td>
			<td>
				<table class="form-table">
					<thead>
						 <tr>
							<th><?php _e('Member Names Separator', 'wp-useronline'); ?></th>
							<th><?php _e('Guests Separator', 'wp-useronline'); ?></th>
							<th><?php _e('Bots Separator', 'wp-useronline'); ?></th>
						 </tr>
					 </thead>
					 <tr>
						<td><input type="text" id="useronline_separator_browsingsite_members" name="useronline_separator_browsingsite_members" value="<?php echo stripslashes($useronline_template_browsingsite[0]); ?>" size="15" /></td>
						<td><input type="text" id="useronline_separator_browsingsite_guests" name="useronline_separator_browsingsite_guests" value="<?php echo stripslashes($useronline_template_browsingsite[1]); ?>" size="15" /></td>
						<td><input type="text" id="useronline_separator_browsingsite_bots" name="useronline_separator_browsingsite_bots" value="<?php echo stripslashes($useronline_template_browsingsite[2]); ?>" size="15" /></td>
					 </tr>
				</table>
				<br />
				<textarea cols="80" rows="12" id="useronline_template_browsingsite" name="useronline_template_browsingsite"><?php echo htmlspecialchars(stripslashes($useronline_template_browsingsite[3])); ?></textarea>
			</td>
		</tr>
		<tr>
			<td width="30%">
				<strong><?php _e('User(s) Browsing Page:', 'wp-useronline'); ?></strong><br /><br /><br />
				<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
				- %USERONLINE_USERS%<br />					
				- %USERONLINE_MEMBERS%<br />
				- %USERONLINE_MEMBER_NAMES%<br />
				- %USERONLINE_GUESTS_SEPERATOR%<br />	
				- %USERONLINE_GUESTS%<br />
				- %USERONLINE_BOTS_SEPERATOR%<br />
				- %USERONLINE_BOTS%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_browsing_page();" class="button" />
			</td>
			<td>
				<table class="form-table">
					<thead>
						 <tr>
							<th><?php _e('Member Names Separator', 'wp-useronline'); ?></th>
							<th><?php _e('Guests Separator', 'wp-useronline'); ?></th>
							<th><?php _e('Bots Separator', 'wp-useronline'); ?></th>
						 </tr>
					 </thead>
					 <tr>
						<td><input type="text" id="useronline_separator_browsingpage_members" name="useronline_separator_browsingpage_members" value="<?php echo stripslashes($useronline_template_browsingpage[0]); ?>" size="15" /></td>
						<td><input type="text" id="useronline_separator_browsingpage_guests" name="useronline_separator_browsingpage_guests" value="<?php echo stripslashes($useronline_template_browsingpage[1]); ?>" size="15" /></td>
						<td><input type="text" id="useronline_separator_browsingpage_bots" name="useronline_separator_browsingpage_bots" value="<?php echo stripslashes($useronline_template_browsingpage[2]); ?>" size="15" /></td>
					 </tr>
				</table>
				<br />
				<textarea cols="80" rows="12" id="useronline_template_browsingpage" name="useronline_template_browsingpage"><?php echo htmlspecialchars(stripslashes($useronline_template_browsingpage[3])); ?></textarea>
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-useronline'); ?>" />
	</p>
</div>
</form>
<p>&nbsp;</p>

<!-- Uninstall WP-UserOnline -->
<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>"> 
<div class="wrap"> 
	<h3><?php _e('Uninstall WP-UserOnline', 'wp-useronline'); ?></h3>
	<p>
		<?php _e('Deactivating WP-UserOnline plugin does not remove any data that may have been created, such as the useronline options. To completely remove this plugin, you can uninstall it here.', 'wp-useronline'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('WARNING:', 'wp-useronline'); ?></strong><br />
		<?php _e('Once uninstalled, this cannot be undone. You should use a Database Backup plugin of WordPress to back up all the data first.', 'wp-useronline'); ?>
	</p>
	<p style="color: red">
		<strong><?php _e('The following WordPress Options/Tables will be DELETED:', 'wp-useronline'); ?></strong><br />
	</p>
	<table class="widefat">
		<thead>
			<tr>
				<th><?php _e('WordPress Options', 'wp-useronline'); ?></th>
				<th><?php _e('WordPress Tables', 'wp-useronline'); ?></th>
			</tr>
		</thead>
		<tr>
			<td valign="top">
				<ol>
				<?php
					foreach($useronline_settings as $settings) {
						echo '<li>'.$settings.'</li>'."\n";
					}
				?>
				</ol>
			</td>
			<td valign="top" class="alternate">
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