<?php

class UserOnline_Admin_Integration extends scbAdminPage {

	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __( 'Users Online Now', $this->textdomain ),
			'menu_title' => __( 'WP-UserOnline', $this->textdomain ),
			'page_slug' => 'useronline',
			'parent' => 'index.php',
			'capability' => 'read',
			'action_link' => false,
		);

		add_action( 'rightnow_end', array( $this, 'rightnow' ) );
	}

	function rightnow() {
		$total_users = get_users_online_count();

		$str = _n(
			"There is <strong><a href='%s'>%s user</a></strong> online now.",
			"There are a total of <strong><a href='%s'>%s users</a></strong> online now.",
			$total_users, 'wp-useronline'
		);

		$out = sprintf( $str, add_query_arg( 'page', $this->args['page_slug'], admin_url( 'index.php' ) ), number_format_i18n( $total_users ) );
		$out .= '<br>';

		if ( $tmp = get_users_browsing_site() )
			$out .= $tmp . '<br>';

		$out .= UserOnline_Template::format_most_users();

		echo html( 'p', $out );
	}

	function page_content() {
		echo users_online_page();
	}
}


class UserOnline_Options extends scbAdminPage {

	function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __( 'UserOnline Options', $this->textdomain ),
			'menu_title' => __( 'UserOnline', $this->textdomain ),
			'page_slug' => 'useronline-settings',
		);

		$this->option_name = 'useronline';
	}

	function validate( $options ) {
		$options['timeout'] = absint( $options['timeout'] );
		$options['url'] = trim( $options['url'] );
		$options['names'] = (bool) $options['names'];

		foreach ( $options['templates'] as $key => $template )
			if ( is_array( $template ) )
				$options['templates'][$key]['text'] = trim( $template['text'] );
			else
				$options['templates'][$key] = trim( $template );

		return $options;
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
		jQuery( "#current_naming" ).html( jQuery( "#default_naming" ).html() );

		return false;
	}

	function useronline_default_template( template ) {
		jQuery( '#current_template_' + template ).html( jQuery( '#default_template_' + template ).html() );

		return false;
	}
</script>
<?php
	}

	function page_content() {
		$options = $this->options->get();
		$defaults = $this->options->get_defaults();
?>
	<form method="post" action="options.php">
		<?php settings_fields( $this->option_name ); ?>

		<table class="form-table">
<?php
		$rows = array(
			array(
				'title' => __( 'Time Out', 'wp-useronline' ),
				'type' => 'text',
				'name_tree' => 'timeout',
				'desc' => '<br />' . __( 'How long until it will remove the user from the database ( In seconds ).', 'wp-useronline' ),
				'extra' => 'size="4"'
			),

			array(
				'title' => __( 'UserOnline URL', 'wp-useronline' ),
				'type' => 'text',
				'name_tree' => 'url',
				'desc' => '<br />' . __( 'URL To UserOnline Page<br />Example: http://www.yoursite.com/useronline/<br />Example: http://www.yoursite.com/?page_id=2', 'wp-useronline' ),
			),

			array(
				'title' => __( 'User Names', 'wp-useronline' ),
				'type' => 'checkbox',
				'name_tree' => 'names',
				'desc' => __( 'Link user names to their author page', 'wp-useronline' ),
			),
		);

		foreach ( $rows as $row )
			echo $this->table_row( $row );
?>
		<tbody id="default_naming" style="display:none">
			<?php $this->formdata = $defaults; $this->naming_table(); ?>
		</tbody>

		<tbody id="current_naming">
			<?php $this->formdata = $options; $this->naming_table(); ?>
		</tbody>

		</table>

		<h3><?php _e( 'Useronline Templates', 'wp-useronline' ); ?></h3>
		<table class="form-table">
			<tbody id="default_template_useronline" style="display:none">
				<?php $this->formdata = $defaults; $this->useronline_template_table(); ?>
			</tbody>

			<tbody id="current_template_useronline">
				<?php $this->formdata = $options; $this->useronline_template_table(); ?>
			</tbody>

			<?php
			$templates = array(
				'browsingsite' => __( 'User(s) Browsing Site:', 'wp-useronline' ),
				'browsingpage' => __( 'User(s) Browsing Page:', 'wp-useronline' ),
			);
			foreach ( $templates as $name => $title ) { ?>
				<tbody id="default_template_<?php echo $name; ?>" style="display:none">
					<?php $this->formdata = $defaults; $this->template_table( $title, $name ); ?>
				</tbody>

				<tbody id="current_template_<?php echo $name; ?>">
					<?php $this->formdata = $options; $this->template_table( $title, $name ); ?>
				</tbody>
			<?php } ?>
		</table>
		<p class="submit">
			<input type="submit" name="Submit" class="button" value="<?php _e( 'Save Changes', 'wp-useronline' ); ?>" />
		</p>
	</form>
<?php
	}

	private function naming_table() {
?>
			<tr>
				<td width="30%">
					<strong><?php _e( 'Naming Conventions:', 'wp-useronline' ); ?></strong><br /><br />
					<?php _e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %COUNT%<br /><br />
					<input type="button" value="<?php _e( 'Restore Defaults', 'wp-useronline' ); ?>" onclick="useronline_default_naming();" class="button" />
				</td>
				<td>
					<table class="form-table">
						<thead>
							<tr>
								<th><?php _e( 'Singular Form', 'wp-useronline' ); ?></th>
								<th><?php _e( 'Plural Form', 'wp-useronline' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
							foreach ( array( 'user', 'member', 'guest', 'bot' ) as $tmp ) {
								echo "\n<tr>\n";
								foreach ( array( $tmp, $tmp . 's' ) as $type ) {
									echo $this->input( array(
										'type' => 'text',
										'name_tree' => array( 'naming', $type ),
										'extra' => 'size="30"',
										'desc' => html( 'td', '%input%' )
									) );
								}
								echo "\n</tr>\n";
							}
						?>
						</tbody>
					</table>
					<br />
				</td>
			</tr>
<?php
	}

	private function useronline_template_table() {
?>
			<tr>
				<td width="30%">
					<strong><?php _e( 'User(s) Online:', 'wp-useronline' ); ?></strong><br /><br />
					<?php _e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %USERS%<br />
					- %PAGE_URL%<br />
					- %MOSTONLINE_COUNT%<br />
					- %MOSTONLINE_DATE%<br /><br />
					<input type="button" value="<?php _e( 'Restore Default Template', 'wp-useronline' ); ?>" onclick="useronline_default_template( 'useronline' );" class="button" />
				</td>
				<td>
					<?php echo $this->input( array(
						'type' => 'textarea',
						'name_tree' => array( 'templates', 'useronline' ),
					) ); ?>
				</td>
			</tr>
<?php
	}

	private function template_table( $title, $option ) {
?>
			<tr>
				<td width="30%">
					<strong><?php echo $title; ?></strong><br /><br />
					<?php _e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %USERS%<br />
					- %MEMBERS%<br />
					- %MEMBER_NAMES%<br />
					- %GUESTS_SEPERATOR%<br />
					- %GUESTS%<br />
					- %BOTS_SEPERATOR%<br />
					- %BOTS%<br /><br />
					<input type="button" value="<?php _e( 'Restore Default Template', 'wp-useronline' ); ?>" onclick="useronline_default_template( '<?php echo $option; ?>' );" class="button" />
				</td>
				<td>
					<table class="form-table">
						<thead>
							<tr>
								<th><?php _e( 'Member Names Separator', 'wp-useronline' ); ?></th>
								<th><?php _e( 'Guests Separator', 'wp-useronline' ); ?></th>
								<th><?php _e( 'Bots Separator', 'wp-useronline' ); ?></th>
							</tr>
						</thead>
						<tr>
							<?php foreach ( array_keys( $this->options->templates[$option]['separators'] ) as $type ) {
								echo html( 'td', $this->input( array(
									'type' => 'text',
									'name_tree' => array( 'templates', $option, 'separators', $type ),
									'extra' => "size='15'",
								) ) );
							} ?>
						</tr>
					</table>
					<br />
					<?php echo $this->input( array(
						'type' => 'textarea',
						'name_tree' => array( 'templates', $option, 'text' )
					) ); ?>
				</td>
			</tr>
<?php
	}
}

