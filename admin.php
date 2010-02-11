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

class UserOnline_Options extends scbAdminPage {
	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __('Useronline Options', $this->textdomain),
			'menu_title' => __('Useronline', $this->textdomain),
		);
	}

	function page_head() {
?>
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
<?php
	}

	function form_handler() {
		if ( empty($_POST['Submit'] ))
			return;

		$bots = array();
		$timeout = intval($_POST['useronline_timeout']);
		$bots_name = explode("\n", trim($_POST['useronline_bots_name']));
		$bots_agent = explode("\n", trim($_POST['useronline_bots_agent']));	
		$bots_keys = array_values((array) $bots_name);
		$bots_vals = array_values((array) $bots_agent);
		$n = max(count($bots_keys), count($bots_vals));

		for($i = 0; $i < $n; $i++)
			$bots[trim($bots_keys[$i])] = trim($bots_vals[$i]);

		$url = trim($_POST['useronline_url']);
		$naming_user = trim($_POST['useronline_naming_user']);
		$naming_users = trim($_POST['useronline_naming_users']);
		$naming_member = trim($_POST['useronline_naming_member']);
		$naming_members = trim($_POST['useronline_naming_members']);
		$naming_guest = trim($_POST['useronline_naming_guest']);
		$naming_guests = trim($_POST['useronline_naming_guests']);
		$naming_bot = trim($_POST['useronline_naming_bot']);
		$naming_bots = trim($_POST['useronline_naming_bots']);
		$naming = array('user' => $naming_user, 'users' => $naming_users, 'member' => $naming_member, 'members' => $naming_members, 'guest' => $naming_guest, 'guests' => $naming_guests, 'bot' => $naming_bot, 'bots' => $naming_bots);
		$template_useronline = trim($_POST['useronline_template_useronline']);
		$template_browsingsite = array($_POST['useronline_separator_browsingsite_members'], $_POST['useronline_separator_browsingsite_guests'], $_POST['useronline_separator_browsingsite_bots'], trim($_POST['useronline_template_browsingsite']));
		$template_browsingpage = array($_POST['useronline_separator_browsingpage_members'], $_POST['useronline_separator_browsingpage_guests'], $_POST['useronline_separator_browsingpage_bots'], trim($_POST['useronline_template_browsingpage']));
		$update_useronline_queries = array();
		$update_useronline_text = array();

		update_option('useronline_timeout', $timeout);
		update_option('useronline_bots', $bots);
		update_option('useronline_url', $url);
		update_option('useronline_naming', $naming);
		update_option('useronline_template_useronline', $template_useronline);
		update_option('useronline_template_browsingsite', $template_browsingsite);
		update_option('useronline_template_browsingpage', $template_browsingpage);

		$this->admin_msg(__('Settings updated.', 'wp-useronline'));
	}

	function page_content() {
		$options_naming = get_option('useronline_naming');
		$options_bots = get_option('useronline_bots');
		$template_browsingsite = get_option('useronline_template_browsingsite');
		$template_browsingpage = get_option('useronline_template_browsingpage');
		$options_bots_name = '';
		$options_bots_agent = '';
		foreach ( $options_bots as $botname => $botagent ) {
			$options_bots_name .= $botname."\n";
			$options_bots_agent .= $botagent."\n";
		}
		$options_bots_name = trim($options_bots_name);
		$options_bots_agent = trim($options_bots_agent);
?>
	<form method="post" action="">
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
					<textarea cols="20" rows="30" name="useronline_bots_name" dir="ltr"><?php echo $options_bots_name; ?></textarea>
					<textarea cols="20" rows="30" name="useronline_bots_agent" dir="ltr"><?php echo $options_bots_agent; ?></textarea>						
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
								<td><input type="text" id="useronline_naming_user" name="useronline_naming_user" value="<?php echo stripslashes($options_naming['user']); ?>" size="20" /></td>
								<td><input type="text" id="useronline_naming_users" name="useronline_naming_users" value="<?php echo stripslashes($options_naming['users']); ?>" size="40" /></td>
							 </tr>
							 <tr>
								<td><input type="text" id="useronline_naming_member" name="useronline_naming_member" value="<?php echo stripslashes($options_naming['member']); ?>" size="20" /></td>
								<td><input type="text" id="useronline_naming_members" name="useronline_naming_members" value="<?php echo stripslashes($options_naming['members']); ?>" size="40" /></td>
							 </tr>
							 <tr>
								 <td><input type="text" id="useronline_naming_guest" name="useronline_naming_guest" value="<?php echo stripslashes($options_naming['guest']); ?>" size="20" /></td>
								<td><input type="text" id="useronline_naming_guests" name="useronline_naming_guests" value="<?php echo stripslashes($options_naming['guests']); ?>" size="40" /></td>
							 </tr>
							 <tr>
								<td><input type="text" id="useronline_naming_bot" name="useronline_naming_bot" value="<?php echo stripslashes($options_naming['bot']); ?>" size="20" /></td>
								<td><input type="text" id="useronline_naming_bots" name="useronline_naming_bots" value="<?php echo stripslashes($options_naming['bots']); ?>" size="40" /></td>
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
							<td><input type="text" id="useronline_separator_browsingsite_members" name="useronline_separator_browsingsite_members" value="<?php echo stripslashes($template_browsingsite[0]); ?>" size="15" /></td>
							<td><input type="text" id="useronline_separator_browsingsite_guests" name="useronline_separator_browsingsite_guests" value="<?php echo stripslashes($template_browsingsite[1]); ?>" size="15" /></td>
							<td><input type="text" id="useronline_separator_browsingsite_bots" name="useronline_separator_browsingsite_bots" value="<?php echo stripslashes($template_browsingsite[2]); ?>" size="15" /></td>
						 </tr>
					</table>
					<br />
					<textarea cols="80" rows="12" id="useronline_template_browsingsite" name="useronline_template_browsingsite"><?php echo htmlspecialchars(stripslashes($template_browsingsite[3])); ?></textarea>
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
							<td><input type="text" id="useronline_separator_browsingpage_members" name="useronline_separator_browsingpage_members" value="<?php echo stripslashes($template_browsingpage[0]); ?>" size="15" /></td>
							<td><input type="text" id="useronline_separator_browsingpage_guests" name="useronline_separator_browsingpage_guests" value="<?php echo stripslashes($template_browsingpage[1]); ?>" size="15" /></td>
							<td><input type="text" id="useronline_separator_browsingpage_bots" name="useronline_separator_browsingpage_bots" value="<?php echo stripslashes($template_browsingpage[2]); ?>" size="15" /></td>
						 </tr>
					</table>
					<br />
					<textarea cols="80" rows="12" id="useronline_template_browsingpage" name="useronline_template_browsingpage"><?php echo htmlspecialchars(stripslashes($template_browsingpage[3])); ?></textarea>
				</td>
			</tr>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-useronline'); ?>" />
		</p>
	</form>
<?php
	}
}

