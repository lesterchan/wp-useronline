<?php

class UserOnline_Admin_Page extends scbAdminPage {
	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __('Users Online Now', $this->textdomain),
			'menu_title' => __('WP-UserOnline', $this->textdomain),
			'page_slug' => 'useronline',
			'parent' => 'index.php',
			'capability' => 'read',
			'action_link' => false,
		);

		add_action('rightnow_end', array($this, 'rightnow'));
	}

	function rightnow() {
		$total_users = get_users_online_count();

		$str = _n(
			"There is <strong><a href='%s'>%s user</a></strong> online now.",
			"There are a total of <strong><a href='%s'>%s users</a></strong> online now.",
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
			'page_slug' => 'useronline',
		);
	}

	function page_head() {
?>
<style type="text/css">
.form-table td {vertical-align: top}
.form-table .form-table {margin-top: 0}
.form-table .form-table th, .form-table .form-table td {padding: 0}
.form-table textarea {width: 100%; height: 150px}
</style>

<script type="text/javascript">
	function useronline_default_naming() {
		jQuery("#current_naming").html(jQuery("#default_naming").html());

		return false;
	}

	function useronline_default_template(template) {
		jQuery('#current_template_' + template).html(jQuery('#default_template_' + template).html());

		return false;
	}
</script>
<?php
	}

	function form_handler() {
		if ( empty($_POST['Submit'] ))
			return;

		$options = array(
			'timeout' => absint($_POST['timeout']),
			'url' => trim($_POST['url']),
			'names' => (bool) $_POST['names']
		);
		UserOnline_Core::$options->update(stripslashes_deep($options));

		$naming = array_map('trim', stripslashes_deep($_POST['useronline_naming']));
		UserOnline_Core::$naming->update($naming);

		$templates = $_POST['useronline_templates'];
		foreach ( $templates as $name => &$template )
			$template['text'] = trim($template['text']);
		UserOnline_Core::$templates->update(stripslashes_deep($templates));

		$this->admin_msg(__('Settings updated.', 'wp-useronline'));
	}

	function page_content() {
		$naming = get_option('useronline_naming');
?>
	<form method="post" action="">
		<table class="form-table">
<?php
		$rows = array(
			array(
				'title' => __('Time Out', 'wp-useronline'),
				'type' => 'text',
				'name' => 'timeout',
				'desc' => '<br />' . __('How long until it will remove the user from the database (In seconds).', 'wp-useronline'),
				'extra' => 'size="4"'
			),

			array(
				'title' => __('UserOnline URL', 'wp-useronline'),
				'type' => 'text',
				'name' => 'url',
				'desc' => '<br />' . __('URL To UserOnline Page<br />Example: http://www.yoursite.com/useronline/<br />Example: http://www.yoursite.com/?page_id=2', 'wp-useronline'),
			),

			array(
				'title' => __('User Names', 'wp-useronline'),
				'type' => 'checkbox',
				'name' => 'names',
				'desc' => __('Link user names to their author page', 'wp-useronline'),
			),
		);

		foreach ( $rows as $row )
			echo $this->table_row($row, UserOnline_Core::$options->get());
?>
		<tbody id="default_naming" style="display:none">
			<?php $this->naming_table(UserOnline_Core::$naming->get_defaults()); ?>
		</tbody>

		<tbody id="current_naming">
			<?php $this->naming_table(UserOnline_Core::$naming->get()); ?>
		</tbody>

		</table>

		<h3><?php _e('Useronline Templates', 'wp-useronline'); ?></h3>
		<table class="form-table">
			<tbody id="default_template_useronline" style="display:none">
				<?php $this->useronline_template_table(UserOnline_Core::$templates->get_defaults('useronline')); ?>
			</tbody>

			<tbody id="current_template_useronline">
				<?php $this->useronline_template_table(UserOnline_Core::$templates->get('useronline')); ?>
			</tbody>

			<?php
			$templates = array(
				'browsingsite' => __('User(s) Browsing Site:', 'wp-useronline'),
				'browsingpage' => __('User(s) Browsing Page:', 'wp-useronline'),
			);
			foreach ( $templates as $name => $title ) { ?>
				<tbody id="default_template_<?php echo $name; ?>" style="display:none">
					<?php $this->template_table($title, $name, UserOnline_Core::$templates->get_defaults($name)); ?>
				</tbody>

				<tbody id="current_template_<?php echo $name; ?>">
					<?php $this->template_table($title, $name, UserOnline_Core::$templates->get($name)); ?>
				</tbody>
			<?php } ?>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e('Save Changes', 'wp-useronline'); ?>" />
		</p>
	</form>
<?php
	}

	private function naming_table($naming) {
?>
			<tr>
				<td width="30%">
					<strong><?php _e('Naming Conventions:', 'wp-useronline'); ?></strong><br /><br /><br />
					<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />
					- %COUNT%<br /><br />
					<input type="button" value="<?php _e('Restore Defaults', 'wp-useronline'); ?>" onclick="useronline_default_naming();" class="button" />
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
						<?php foreach ( array('user', 'member', 'guest', 'bot') as $type ) { ?>
							<tr>
							<?php echo $this->input(array(
								'type' => 'text',
								'names' => array("useronline_naming[$type]", "useronline_naming[{$type}s]"),
								'values' => array($naming[$type], $naming[$type . 's']),
								'extra' => 'size="30"',
								'desc' => html('td', '%input%')
							));	?>
							</tr>
						<?php } ?>
						</tbody>
					</table>
					<br />
				</td>
			</tr>
<?php
	}

	private function useronline_template_table($template) {
?>
			<tr>
				<td width="30%">
					<strong><?php _e('User(s) Online:', 'wp-useronline'); ?></strong><br /><br />
					<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
					- %USERS%<br />
					- %PAGE_URL%<br />
					- %MOSTONLINE_COUNT%<br />
					- %MOSTONLINE_DATE%<br /><br />
					<input type="button" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_template('useronline');" class="button" />
				</td>
				<td>
					<?php echo $this->input(array(
						'type' => 'textarea',
						'name' => 'useronline_templates[useronline]',
						'value' => $template,
					)); ?>
				</td>
			</tr>
<?php
	}

	private function template_table($title, $option, $template) {
?>
			<tr>
				<td width="30%">
					<strong><?php echo $title; ?></strong><br /><br />
					<?php _e('Allowed Variables:', 'wp-useronline'); ?><br />	
					- %USERS%<br />
					- %MEMBERS%<br />
					- %MEMBER_NAMES%<br />
					- %GUESTS_SEPERATOR%<br />
					- %GUESTS%<br />
					- %BOTS_SEPERATOR%<br />
					- %BOTS%<br /><br />
					<input type="button" value="<?php _e('Restore Default Template', 'wp-useronline'); ?>" onclick="useronline_default_template('<?php echo $option; ?>');" class="button" />
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
							<?php foreach ( $template['separators'] as $type => $sep ) {
								echo $this->input(array(
									'type' => 'text',
									'name' => "useronline_templates[$option][separators][$type]",
									'value' => $sep,
									'desc' => html('td', '%input%'),
									'extra' => "size='15'",
								));
							} ?>
						</tr>
					</table>
					<br />
					<?php echo $this->input(array(
						'type' => 'textarea',
						'name' => "useronline_templates[$option][text]",
						'value' => $template['text']
					)); ?>
				</td>
			</tr>
<?php
	}
}

