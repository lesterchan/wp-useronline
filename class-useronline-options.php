<?php
/**
 * Settings screen for WP-UserOnline.
 *
 * @package WP-UserOnline
 */

/**
 * Settings screen for the naming conventions and output templates.
 */
class UserOnline_Options extends scbAdminPage {

	/**
	 * Configure the settings page.
	 *
	 * @return void
	 */
	public function setup() {
		$this->textdomain = 'wp-useronline';

		$this->args = array(
			'page_title' => __( 'UserOnline Options', 'wp-useronline' ),
			'menu_title' => __( 'UserOnline', 'wp-useronline' ),
			'page_slug'  => 'useronline-settings',
		);

		$this->option_name = 'useronline';
	}

	/**
	 * Sanitize submitted settings.
	 *
	 * @param array $options  Submitted values.
	 * @param array $old_data Previously stored values.
	 *
	 * @return array
	 */
	public function validate( $options, $old_data = array() ) {
		return UserOnline_Core::sanitize_options( $options );
	}

	/**
	 * Print the page's inline styles and scripts.
	 *
	 * @return void
	 */
	public function page_head() {
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

	/**
	 * Render the settings form.
	 *
	 * @return void
	 */
	public function page_content() {
		$options  = $this->options->get();
		$defaults = $this->options->get_defaults();

		?>
	<form method="post" action="">
		<?php wp_nonce_field( $this->nonce ); ?>
		<table class="form-table">
		<?php
		$rows = array(
			array(
				'title' => __( 'Time Out', 'wp-useronline' ),
				'type'  => 'text',
				'name'  => 'timeout',
				'desc'  => '<br />' . __( 'How long until it will remove the user from the database (in seconds).', 'wp-useronline' ),
				'extra' => 'size="4"',
			),

			array(
				'title' => __( 'UserOnline URL', 'wp-useronline' ),
				'type'  => 'text',
				'name'  => 'url',
				'desc'  => '<br />' . __( 'URL To UserOnline Page<br />Example: http://www.yoursite.com/useronline/<br />Example: http://www.yoursite.com/?page_id=2', 'wp-useronline' ),
			),

			array(
				'title'   => __( 'Link user names?', 'wp-useronline' ),
				'type'    => 'radio',
				'name'    => 'names',
				'choices' => array(
					1 => __( 'Yes', 'wp-useronline' ),
					0 => __( 'No', 'wp-useronline' ),
				),
				'desc'    => '<br />' . __( 'Link user names to their author page', 'wp-useronline' ),
			),
		);

		foreach ( $rows as $row ) {
			echo $this->table_row( $row );
		}

		?>
		<tbody id="default_naming" style="display:none">
			<?php $this->naming_table( $defaults ); ?>
		</tbody>

		<tbody id="current_naming">
			<?php $this->naming_table( $options ); ?>
		</tbody>

		</table>

		<h3><?php esc_html_e( 'Useronline Templates', 'wp-useronline' ); ?></h3>
		<table class="form-table">
			<tbody id="default_template_useronline" style="display:none">
				<?php $this->useronline_template_table( $defaults ); ?>
			</tbody>

			<tbody id="current_template_useronline">
				<?php $this->useronline_template_table( $options ); ?>
			</tbody>

			<?php
			$templates = array(
				'browsingsite' => __( 'User(s) Browsing Site:', 'wp-useronline' ),
				'browsingpage' => __( 'User(s) Browsing Page:', 'wp-useronline' ),
			);
			foreach ( $templates as $name => $title ) {
				?>
				<tbody id="default_template_<?php echo esc_attr( $name ); ?>" style="display:none">
					<?php $this->template_table( $title, $name, $defaults ); ?>
				</tbody>

				<tbody id="current_template_<?php echo esc_attr( $name ); ?>">
					<?php $this->template_table( $title, $name, $options ); ?>
				</tbody>
			<?php } ?>
		</table>
		<p class="submit">
			<input type="submit" name="action" class="button" value="<?php esc_attr_e( 'Save Changes', 'wp-useronline' ); ?>" />
		</p>
	</form>
		<?php
	}

	/**
	 * Render the naming conventions rows.
	 *
	 * @param array $data Values to populate the fields with.
	 *
	 * @return void
	 */
	private function naming_table( $data ) {
		?>
			<tr>
				<td width="30%">
					<strong><?php esc_html_e( 'Naming Conventions:', 'wp-useronline' ); ?></strong><br /><br />
					<?php esc_html_e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %COUNT%<br /><br />
					<input type="button" value="<?php esc_attr_e( 'Restore Defaults', 'wp-useronline' ); ?>" onclick="useronline_default_naming();" class="button" />
				</td>
				<td>
					<table class="form-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Singular Form', 'wp-useronline' ); ?></th>
								<th><?php esc_html_e( 'Plural Form', 'wp-useronline' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php
						foreach ( array( 'user', 'member', 'guest', 'bot' ) as $tmp ) {
							echo "\n<tr>\n";
							foreach ( array( $tmp, $tmp . 's' ) as $type ) {
								echo $this->input(
									array(
										'type'  => 'text',
										'name'  => array( 'naming', $type ),
										'extra' => 'size="30"',
										'desc'  => html( 'td', $type ),
									),
									$data
								);
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

	/**
	 * Render the "users online" template row.
	 *
	 * @param array $data Values to populate the fields with.
	 *
	 * @return void
	 */
	private function useronline_template_table( $data ) {
		?>
			<tr>
				<td width="30%">
					<strong><?php esc_html_e( 'User(s) Online:', 'wp-useronline' ); ?></strong><br /><br />
					<?php esc_html_e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %USERS%<br />
					- %PAGE_URL%<br />
					- %MOSTONLINE_COUNT%<br />
					- %MOSTONLINE_DATE%<br /><br />
					<input type="button" value="<?php esc_attr_e( 'Restore Default Template', 'wp-useronline' ); ?>" onclick="useronline_default_template( 'useronline' );" class="button" />
				</td>
				<td>
					<?php
					echo $this->input(
						array(
							'type' => 'textarea',
							'name' => array( 'templates', 'useronline' ),
						),
						$data
					);
					?>
				</td>
			</tr>
		<?php
	}

	/**
	 * Render a browsing-site/browsing-page template row.
	 *
	 * @param string $title  Row heading.
	 * @param string $option Template key.
	 * @param array  $data   Values to populate the fields with.
	 *
	 * @return void
	 */
	private function template_table( $title, $option, $data ) {
		?>
			<tr>
				<td width="30%">
					<strong><?php echo esc_html( $title ); ?></strong><br /><br />
					<?php esc_html_e( 'Allowed Variables:', 'wp-useronline' ); ?><br />
					- %USERS%<br />
					- %MEMBERS%<br />
					- %MEMBER_NAMES%<br />
					- %GUESTS_SEPARATOR%<br />
					- %GUESTS%<br />
					- %BOTS_SEPARATOR%<br />
					- %BOTS%<br /><br />
					<input type="button" value="<?php esc_attr_e( 'Restore Default Template', 'wp-useronline' ); ?>" onclick="useronline_default_template( '<?php echo esc_js( $option ); ?>' );" class="button" />
				</td>
				<td>
					<table class="form-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Member Names Separator', 'wp-useronline' ); ?></th>
								<th><?php esc_html_e( 'Guests Separator', 'wp-useronline' ); ?></th>
								<th><?php esc_html_e( 'Bots Separator', 'wp-useronline' ); ?></th>
							</tr>
						</thead>
						<tr>
							<?php
							foreach ( array_keys( $this->options->templates[ $option ]['separators'] ) as $type ) {
								echo html(
									'td',
									$this->input(
										array(
											'type'  => 'text',
											'name'  => array( 'templates', $option, 'separators', $type ),
											'extra' => "size='15'",
										),
										$data
									)
								);
							}
							?>
						</tr>
					</table>
					<br />
					<?php
					echo $this->input(
						array(
							'type' => 'textarea',
							'name' => array( 'templates', $option, 'text' ),
						),
						$data
					);
					?>
				</td>
			</tr>
		<?php
	}
}

