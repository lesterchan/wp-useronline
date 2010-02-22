<?php

class UserOnline_Admin_Page extends scbAdminPage {
	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __('Users Online Now', $this->textdomain),
			'menu_title' => __('WP-UserOnline', $this->textdomain),
			'parent' => 'index.php',
			'capability' => 'read',
			'action_link' => false,
		);
		
		add_action('rightnow_end', array($this, 'rightnow'));
	}

	function rightnow() {
		$total_users = get_users_online_count();

		$str = _n(
			'There is <strong><a href="%s">%s user</a></strong> online now.',
			'There are a total of <strong><a href="%s">%s users</a></strong> online now.',
			$total_users, 'wp-useronline'
		);

		echo '<p>';
		printf($str, add_query_arg('page', $this->args['page_slug'], admin_url('index.php')), number_format_i18n($total_users));

		echo '<br />';
		users_browsing_site();
		echo '.<br />';
		echo UserOnline_Template::format_most_users();
		echo '</p>'."\n";
	}

	function page_content() {
		echo users_online_page();	
	}
}

class UserOnline_Options extends scbAdminPage {
	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __('UserOnline Options', $this->textdomain),
			'menu_title' => __('UserOnline', $this->textdomain),
		);
	}

	function page_head() {
?>
	<script type="text/javascript">
		function useronline_default_templates(template) {
			var default_template;
			if ( "useronline" == template )
				default_template = "<a href=\"%USERONLINE_PAGE_URL%\" title=\"%USERONLINE_USERS%\"><strong>%USERONLINE_USERS%</strong> <?php _e('Online', 'wp-useronline'); ?></a>";

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
	</script>
<?php
	}

	function form_handler() {
		if ( empty($_POST['Submit'] ))
			return;

		$timeout = intval($_POST['useronline_timeout']);
		$url = trim(stripslashes($_POST['useronline_url']));

		$bots = array();
		$bots_name = explode("\n", trim(stripslashes($_POST['useronline_bots_name'])));
		$bots_agent = explode("\n", trim(stripslashes($_POST['useronline_bots_agent'])));

		$bots_keys = array_values((array) $bots_name);
		$bots_vals = array_values((array) $bots_agent);
		$n = max(count($bots_keys), count($bots_vals));

		for ( $i = 0; $i < $n; $i++ )
			$bots[trim($bots_keys[$i])] = trim($bots_vals[$i]);

		$naming = array();
		foreach ( array('user', 'users', 'member', 'members', 'guest', 'guests', 'bot', 'bots') as $key )
			$naming[$key] = trim(stripslashes($_POST["useronline_naming_$key"]));

		$template_useronline = trim(stripslashes($_POST['useronline_template_useronline']));

		foreach ( array('browsingsite', 'browsingpage') as $key ) {
			$template = array();
			foreach ( array('members', 'guests', 'bots') as $type )
				$template[] = stripslashes($_POST["useronline_separator_{$key}_{$type}"]);
			$template[] = trim(stripslashes($_POST["useronline_template_{$key}"]));
			update_option("useronline_template_{$key}", $template);
		}

		update_option('useronline_timeout', $timeout);
		update_option('useronline_bots', $bots);
		update_option('useronline_url', $url);
		update_option('useronline_naming', $naming);
		update_option('useronline_template_useronline', $template_useronline);

		$this->admin_msg(__('Settings updated.', 'wp-useronline'));
	}

	function page_content() {
		$naming = get_option('useronline_naming');

		$bots = get_option('useronline_bots');
		$bots_name = implode("\n", array_keys($bots));
		$bots_agent = implode("\n", array_values($bots));
?>
	<form method="post" action="">
		<table class="form-table">
			 <tr>
				<th scope="row" valign="top"><?php _e('Time Out', 'wp-useronline'); ?></th>
				<td>
					<input type="text" name="useronline_timeout" value="<?php echo esc_attr(get_option('useronline_timeout')); ?>" size="4" /><br /><?php _e('How long till it will remove the user from the database (In seconds).', 'wp-useronline'); ?>
				</td>
			</tr>
			 <tr>
				<th scope="row" valign="top"><?php _e('UserOnline URL', 'wp-useronline'); ?></th>
				<td>
					<input type="text" name="useronline_url" value="<?php echo esc_attr(get_option('useronline_url')); ?>" size="50" dir="ltr" /><br /><?php _e('URL To UserOnline Page (leave blank if you do not want to link it to the UserOnline Page)<br />Example: http://www.yoursite.com/blogs/useronline/<br />Example: http://www.yoursite.com/blogs/?page_id=2', 'wp-useronline'); ?>
				</td>
			</tr>
			<tr>
				<th scope="row" valign="top"><?php _e('Bots Name/User Agent', 'wp-useronline'); ?></th>
				<td>
					<?php _e('Here are a list of bots and their partial browser agents.<br />On the left column will be the <strong>Bot\'s Name</strong> and on the right column will be their <strong>Partial Browser Agent</strong>.<br />Start each entry on a new line.', 'wp-useronline'); ?>
					<br /><br />
					<textarea cols="20" rows="30" name="useronline_bots_name" dir="ltr"><?php echo esc_html($bots_name); ?></textarea>
					<textarea cols="20" rows="30" name="useronline_bots_agent" dir="ltr"><?php echo esc_html($bots_agent); ?></textarea>
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
							<?php foreach ( array('user', 'member', 'guest', 'bot') as $type ) {
								echo
								html('tr',
									html('td', "<input id='useronline_naming_$type' name='useronline_naming_$type' value='"
										.esc_attr($naming[$type]) . "' type='text' size='20' />")
									.html('td', "<input id='useronline_naming_{$type}s' name='useronline_naming_{$type}s' value='"
										.esc_attr($naming[$type . 's']) . "' type='text' size='40' />")
								);
							} ?>
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
				<td><textarea cols="80" rows="12" id="useronline_template_useronline" name="useronline_template_useronline"><?php echo htmlspecialchars(get_option('useronline_template_useronline')); ?></textarea></td>
			</tr>
<?php $this->template(__('User(s) Browsing Site:', 'wp-useronline'), 'site'); ?>
<?php $this->template(__('User(s) Browsing Page:', 'wp-useronline'), 'page'); ?>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-useronline'); ?>" />
		</p>
	</form>
<?php
	}
	
	private function template($title, $option) {
		$template = get_option("useronline_template_browsing$option");
?>
			<tr>
				<td width="30%">
					<strong><?php echo $title; ?></strong><br /><br /><br />
					<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
					- %USERONLINE_USERS%<br />					
					- %USERONLINE_MEMBERS%<br />
					- %USERONLINE_MEMBER_NAMES%<br />
					- %USERONLINE_GUESTS_SEPERATOR%<br />	
					- %USERONLINE_GUESTS%<br />
					- %USERONLINE_BOTS_SEPERATOR%<br />
					- %USERONLINE_BOTS%<br /><br />
					<input type="button" name="RestoreDefault" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_browsing_<?php echo $option; ?>();" class="button" />
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
						 	<?php foreach ( array('members', 'guests', 'bots') as $i => $type ) {
						 		$name = "useronline_separator_browsing{$option}_{$type}";
						 		echo
						 		html('td',
						 			"<input type='text' id='$name' name='$name' value='" . esc_attr($template[$i]) . "' size='15' />"
						 		);
							} ?>
						 </tr>
					</table>
					<br />
					<textarea cols="80" rows="12" id="useronline_template_browsing<?php echo $option; ?>" name="useronline_template_browsing<?php echo $option; ?>"><?php echo htmlspecialchars($template[3]); ?></textarea>
				</td>
			</tr>
<?php
	}
}

