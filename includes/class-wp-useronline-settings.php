<?php
/**
 * WP-UserOnline class-wp-useronline-settings.php
 *
 * @package wp-useronline
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings screen, built on the Settings API.
 *
 * Replaces the scbAdminPage form the plugin used before 3.0.0. The option name
 * is unchanged, so existing settings carry over.
 *
 * @since 3.0.0
 */
class WP_UserOnline_Settings {

	/**
	 * Settings group used by register_setting() and settings_fields().
	 */
	const GROUP = 'useronline_settings';

	/**
	 * Settings page slug.
	 */
	const PAGE = 'wp-useronline-settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the settings page under the Settings menu.
	 *
	 * @return void
	 */
	public function add_page() {
		add_options_page(
			__( 'UserOnline Options', 'wp-useronline' ),
			__( 'UserOnline', 'wp-useronline' ),
			WP_UserOnline_Admin::capability( 'settings' ),
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			WP_UserOnline_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'WP_UserOnline_Options', 'sanitize' ),
				'default'           => WP_UserOnline_Options::defaults(),
			)
		);

		add_settings_section(
			'useronline_general',
			__( 'General', 'wp-useronline' ),
			'__return_empty_string',
			self::PAGE
		);

		add_settings_field(
			'timeout',
			__( 'Time Out', 'wp-useronline' ),
			array( $this, 'field_timeout' ),
			self::PAGE,
			'useronline_general'
		);

		add_settings_field(
			'url',
			__( 'UserOnline URL', 'wp-useronline' ),
			array( $this, 'field_url' ),
			self::PAGE,
			'useronline_general'
		);

		add_settings_field(
			'names',
			__( 'Link user names?', 'wp-useronline' ),
			array( $this, 'field_names' ),
			self::PAGE,
			'useronline_general'
		);

		add_settings_section(
			'useronline_naming',
			__( 'Naming Conventions', 'wp-useronline' ),
			array( $this, 'section_naming' ),
			self::PAGE
		);

		add_settings_field(
			'naming',
			__( 'Names', 'wp-useronline' ),
			array( $this, 'field_naming' ),
			self::PAGE,
			'useronline_naming'
		);

		add_settings_section(
			'useronline_templates',
			__( 'Templates', 'wp-useronline' ),
			'__return_empty_string',
			self::PAGE
		);

		add_settings_field(
			'template_useronline',
			__( 'User(s) Online', 'wp-useronline' ),
			array( $this, 'field_template_useronline' ),
			self::PAGE,
			'useronline_templates'
		);

		add_settings_field(
			'template_browsingsite',
			__( 'User(s) Browsing Site', 'wp-useronline' ),
			array( $this, 'field_template_browsingsite' ),
			self::PAGE,
			'useronline_templates'
		);

		add_settings_field(
			'template_browsingpage',
			__( 'User(s) Browsing Page', 'wp-useronline' ),
			array( $this, 'field_template_browsingpage' ),
			self::PAGE,
			'useronline_templates'
		);
	}

	/**
	 * Render a list of template tokens as inline code.
	 *
	 * @param string[] $tokens Literal tokens, e.g. %USERS%.
	 *
	 * @return string
	 */
	private static function token_list( array $tokens ) {
		return '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $tokens ) ) . '</code>';
	}

	/**
	 * Build a name attribute for a nested option key.
	 *
	 * @param string ...$keys Nested keys.
	 *
	 * @return string
	 */
	private function name( ...$keys ) {
		return WP_UserOnline_Options::OPTION . '[' . implode( '][', $keys ) . ']';
	}

	/**
	 * Print a text input bound to a nested option key.
	 *
	 * @param array  $keys  Nested keys.
	 * @param string $value Current value.
	 * @param string $size  Input size attribute.
	 *
	 * @return void
	 */
	private function text_input( array $keys, $value, $size = '30' ) {
		printf(
			'<input type="text" name="%1$s" value="%2$s" size="%3$s" data-useronline-default="%4$s" />',
			esc_attr( call_user_func_array( array( $this, 'name' ), $keys ) ),
			esc_attr( $value ),
			esc_attr( $size ),
			esc_attr( $this->default_for( $keys ) )
		);
	}

	/**
	 * Look up the default for a nested option key.
	 *
	 * @param array $keys Nested keys.
	 *
	 * @return string
	 */
	private function default_for( array $keys ) {
		$value = WP_UserOnline_Options::defaults();

		foreach ( $keys as $key ) {
			if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
				return '';
			}
			$value = $value[ $key ];
		}

		// Scalar, not just string: the timeout default is an int, and returning
		// '' for it made Restore Defaults clear the field instead of resetting
		// it to 300.
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Print a "restore defaults" button for a group of fields.
	 *
	 * @param string $target CSS selector scope.
	 *
	 * @return void
	 */
	private function restore_button( $target ) {
		printf(
			'<p><button type="button" class="button useronline-restore" data-target="%1$s">%2$s</button></p>',
			esc_attr( $target ),
			esc_html__( 'Restore Defaults', 'wp-useronline' )
		);
	}

	/**
	 * Time out field.
	 *
	 * @return void
	 */
	public function field_timeout() {
		$this->text_input( array( 'timeout' ), WP_UserOnline_Options::get( 'timeout' ), '4' );
		echo '<p class="description">' . esc_html__( 'How long until it will remove the user from the database (in seconds).', 'wp-useronline' ) . '</p>';
	}

	/**
	 * UserOnline URL field.
	 *
	 * @return void
	 */
	public function field_url() {
		printf(
			'<input type="url" class="regular-text" name="%1$s" value="%2$s" />',
			esc_attr( $this->name( 'url' ) ),
			esc_attr( WP_UserOnline_Options::get( 'url' ) )
		);
		echo '<p class="description">' . esc_html__( 'URL to the page showing who is online.', 'wp-useronline' ) . '</p>';
	}

	/**
	 * Link user names field.
	 *
	 * @return void
	 */
	public function field_names() {
		$names = (int) WP_UserOnline_Options::get( 'names' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( $this->name( 'names' ) ); ?>" value="1" <?php checked( 1, $names ); ?> />
				<?php esc_html_e( 'Yes', 'wp-useronline' ); ?>
			</label>
			<br />
			<label>
				<input type="radio" name="<?php echo esc_attr( $this->name( 'names' ) ); ?>" value="0" <?php checked( 0, $names ); ?> />
				<?php esc_html_e( 'No', 'wp-useronline' ); ?>
			</label>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Link user names to their author page.', 'wp-useronline' ); ?></p>
		<?php
	}

	/**
	 * Naming section description.
	 *
	 * @return void
	 */
	public function section_naming() {
		echo '<p>' . esc_html__( 'Allowed variable:', 'wp-useronline' ) . ' <code>%COUNT%</code></p>';
	}

	/**
	 * Naming conventions fields.
	 *
	 * @return void
	 */
	public function field_naming() {
		$naming = WP_UserOnline_Options::get( 'naming' );
		?>
		<table class="widefat striped" id="useronline-naming">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Type', 'wp-useronline' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Singular Form', 'wp-useronline' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Plural Form', 'wp-useronline' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array( 'user', 'member', 'guest', 'bot' ) as $type ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $type ); ?></th>
					<td><?php $this->text_input( array( 'naming', $type ), $naming[ $type ] ); ?></td>
					<td><?php $this->text_input( array( 'naming', $type . 's' ), $naming[ $type . 's' ] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->restore_button( '#useronline-naming' );
	}

	/**
	 * Users online template field.
	 *
	 * @return void
	 */
	public function field_template_useronline() {
		$templates = WP_UserOnline_Options::get( 'templates' );
		?>
		<div id="useronline-template-useronline">
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%PAGE_URL%', '%MOSTONLINE_COUNT%', '%MOSTONLINE_DATE%' ) ) ); ?>
			</p>
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( $this->name( 'templates', 'useronline' ) ); ?>"
				data-useronline-default="<?php echo esc_attr( $this->default_for( array( 'templates', 'useronline' ) ) ); ?>"
			><?php echo esc_textarea( $templates['useronline'] ); ?></textarea>
		</div>
		<?php
		$this->restore_button( '#useronline-template-useronline' );
	}

	/**
	 * Browsing site template field.
	 *
	 * @return void
	 */
	public function field_template_browsingsite() {
		$this->render_browsing_template( 'browsingsite' );
	}

	/**
	 * Browsing page template field.
	 *
	 * @return void
	 */
	public function field_template_browsingpage() {
		$this->render_browsing_template( 'browsingpage' );
	}

	/**
	 * Render one of the browsing templates and its separators.
	 *
	 * @param string $key Either 'browsingsite' or 'browsingpage'.
	 *
	 * @return void
	 */
	private function render_browsing_template( $key ) {
		$templates = WP_UserOnline_Options::get( 'templates' );
		$template  = $templates[ $key ];
		$id        = 'useronline-template-' . $key;
		?>
		<div id="<?php echo esc_attr( $id ); ?>">
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%MEMBERS%', '%MEMBER_NAMES%', '%GUESTS_SEPARATOR%', '%GUESTS%', '%BOTS_SEPARATOR%', '%BOTS%' ) ) ); ?>
			</p>
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( $this->name( 'templates', $key, 'text' ) ); ?>"
				data-useronline-default="<?php echo esc_attr( $this->default_for( array( 'templates', $key, 'text' ) ) ); ?>"
			><?php echo esc_textarea( $template['text'] ); ?></textarea>

			<p>
				<?php foreach ( array( 'members', 'guests', 'bots' ) as $sep ) : ?>
					<label style="margin-right:1em">
						<?php
						/* translators: %s: separator type, one of members, guests or bots. */
						echo esc_html( sprintf( __( '%s separator', 'wp-useronline' ), $sep ) );
						?>
						<?php $this->text_input( array( 'templates', $key, 'separators', $sep ), $template['separators'][ $sep ], '8' ); ?>
					</label>
				<?php endforeach; ?>
			</p>
		</div>
		<?php
		$this->restore_button( '#' . $id );
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( WP_UserOnline_Admin::capability( 'settings' ) ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-useronline' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'UserOnline Options', 'wp-useronline' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );
				do_settings_sections( self::PAGE );
				submit_button();
				?>
			</form>
		</div>
		<script>
		( function () {
			document.addEventListener( 'click', function ( e ) {
				var button = e.target.closest( '.useronline-restore' );
				if ( ! button ) {
					return;
				}
				var scope = document.querySelector( button.dataset.target );
				if ( ! scope ) {
					return;
				}
				scope.querySelectorAll( '[data-useronline-default]' ).forEach( function ( field ) {
					field.value = field.dataset.useronlineDefault;
				} );
			} );
		}() );
		</script>
		<?php
	}
}
