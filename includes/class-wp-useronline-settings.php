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
 * The settings screen, built on the Settings API.
 *
 * WP_UserOnline_Admin owns the menu and the screens; this class owns
 * register_setting(), the sections, the field_<name>() callbacks and the
 * sanitiser. Everything the screen renders comes out of do_settings_sections(),
 * so there is no hand-written form table anywhere below.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Settings {

	/**
	 * Settings group used by register_setting() and settings_fields().
	 */
	const GROUP = 'wp_useronline_options';

	/**
	 * Settings page slug.
	 */
	const PAGE = 'wp-useronline-settings';

	/**
	 * General settings section.
	 */
	const SECTION_GENERAL = 'wp_useronline_general';

	/**
	 * Naming conventions section.
	 */
	const SECTION_NAMING = 'wp_useronline_naming';

	/**
	 * Templates section.
	 */
	const SECTION_TEMPLATES = 'wp_useronline_templates';

	/**
	 * WP-Stats integration section.
	 */
	const SECTION_WPSTATS = 'wp_useronline_wpstats';

	/**
	 * Hook the settings up.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
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
			self::SECTION_GENERAL,
			__( 'General', 'wp-useronline' ),
			'__return_empty_string',
			self::PAGE
		);

		add_settings_field(
			'timeout',
			__( 'Time Out', 'wp-useronline' ),
			array( __CLASS__, 'field_timeout' ),
			self::PAGE,
			self::SECTION_GENERAL
		);

		add_settings_field(
			'url',
			__( 'UserOnline URL', 'wp-useronline' ),
			array( __CLASS__, 'field_url' ),
			self::PAGE,
			self::SECTION_GENERAL
		);

		add_settings_field(
			'names',
			__( 'Link user names?', 'wp-useronline' ),
			array( __CLASS__, 'field_names' ),
			self::PAGE,
			self::SECTION_GENERAL
		);

		add_settings_section(
			self::SECTION_NAMING,
			__( 'Naming Conventions', 'wp-useronline' ),
			array( __CLASS__, 'section_naming' ),
			self::PAGE
		);

		add_settings_field(
			'naming',
			__( 'Names', 'wp-useronline' ),
			array( __CLASS__, 'field_naming' ),
			self::PAGE,
			self::SECTION_NAMING
		);

		add_settings_section(
			self::SECTION_TEMPLATES,
			__( 'Templates', 'wp-useronline' ),
			'__return_empty_string',
			self::PAGE
		);

		add_settings_field(
			'template_useronline',
			__( 'User(s) Online', 'wp-useronline' ),
			array( __CLASS__, 'field_template_useronline' ),
			self::PAGE,
			self::SECTION_TEMPLATES
		);

		add_settings_field(
			'template_browsingsite',
			__( 'User(s) Browsing Site', 'wp-useronline' ),
			array( __CLASS__, 'field_template_browsingsite' ),
			self::PAGE,
			self::SECTION_TEMPLATES
		);

		add_settings_field(
			'template_browsingpage',
			__( 'User(s) Browsing Page', 'wp-useronline' ),
			array( __CLASS__, 'field_template_browsingpage' ),
			self::PAGE,
			self::SECTION_TEMPLATES
		);

		add_settings_section(
			self::SECTION_WPSTATS,
			__( 'WP-Stats', 'wp-useronline' ),
			array( __CLASS__, 'section_wpstats' ),
			self::PAGE
		);

		add_settings_field(
			'stats_display',
			__( 'Show a users online section?', 'wp-useronline' ),
			array( __CLASS__, 'field_stats_display' ),
			self::PAGE,
			self::SECTION_WPSTATS
		);
	}

	/**
	 * Load the Restore Defaults behaviour, on the settings screen only.
	 *
	 * @param string $hook_suffix Current admin screen.
	 *
	 * @return void
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		if ( ! is_string( $hook_suffix ) || false === strpos( $hook_suffix, self::PAGE ) ) {
			return;
		}

		wp_enqueue_script(
			'wp-useronline-admin',
			WP_USERONLINE_URL . 'js/wp-useronline-admin.js',
			array(),
			WP_USERONLINE_VERSION,
			true
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
	private static function name( ...$keys ) {
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
	private static function text_input( array $keys, $value, $size = '30' ) {
		printf(
			'<input type="text" name="%1$s" value="%2$s" size="%3$s" data-wp-useronline-default="%4$s" />',
			esc_attr( call_user_func_array( array( __CLASS__, 'name' ), $keys ) ),
			esc_attr( $value ),
			esc_attr( $size ),
			esc_attr( self::default_for( $keys ) )
		);
	}

	/**
	 * Look up the default for a nested option key.
	 *
	 * @param array $keys Nested keys.
	 *
	 * @return string
	 */
	private static function default_for( array $keys ) {
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
	private static function restore_button( $target ) {
		printf(
			'<p><button type="button" class="button wp-useronline-restore" data-target="%1$s">%2$s</button></p>',
			esc_attr( $target ),
			esc_html__( 'Restore Defaults', 'wp-useronline' )
		);
	}

	/**
	 * Time out field.
	 *
	 * @return void
	 */
	public static function field_timeout() {
		self::text_input( array( 'timeout' ), WP_UserOnline_Options::get( 'timeout' ), '4' );
		echo '<p class="description">' . esc_html__( 'How long until it will remove the user from the database (in seconds).', 'wp-useronline' ) . '</p>';
	}

	/**
	 * UserOnline URL field.
	 *
	 * @return void
	 */
	public static function field_url() {
		printf(
			'<input type="url" class="regular-text" name="%1$s" value="%2$s" />',
			esc_attr( self::name( 'url' ) ),
			esc_attr( WP_UserOnline_Options::get( 'url' ) )
		);
		echo '<p class="description">' . esc_html__( 'URL to the page showing who is online.', 'wp-useronline' ) . '</p>';
	}

	/**
	 * Link user names field.
	 *
	 * @return void
	 */
	public static function field_names() {
		$names = (int) WP_UserOnline_Options::get( 'names' );
		?>
		<fieldset>
			<label>
				<input type="radio" name="<?php echo esc_attr( self::name( 'names' ) ); ?>" value="1" <?php checked( 1, $names ); ?> />
				<?php esc_html_e( 'Yes', 'wp-useronline' ); ?>
			</label>
			<br />
			<label>
				<input type="radio" name="<?php echo esc_attr( self::name( 'names' ) ); ?>" value="0" <?php checked( 0, $names ); ?> />
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
	public static function section_naming() {
		echo '<p>' . esc_html__( 'Allowed variable:', 'wp-useronline' ) . ' <code>%COUNT%</code></p>';
	}

	/**
	 * Naming conventions fields.
	 *
	 * @return void
	 */
	public static function field_naming() {
		$naming = WP_UserOnline_Options::get( 'naming' );
		?>
		<table class="widefat striped" id="wp-useronline-naming">
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
					<td><?php self::text_input( array( 'naming', $type ), $naming[ $type ] ); ?></td>
					<td><?php self::text_input( array( 'naming', $type . 's' ), $naming[ $type . 's' ] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		self::restore_button( '#wp-useronline-naming' );
	}

	/**
	 * Users online template field.
	 *
	 * @return void
	 */
	public static function field_template_useronline() {
		$templates = WP_UserOnline_Options::get( 'templates' );
		?>
		<div id="wp-useronline-template-useronline">
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%PAGE_URL%', '%MOSTONLINE_COUNT%', '%MOSTONLINE_DATE%' ) ) ); ?>
			</p>
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( self::name( 'templates', 'useronline' ) ); ?>"
				data-wp-useronline-default="<?php echo esc_attr( self::default_for( array( 'templates', 'useronline' ) ) ); ?>"
			><?php echo esc_textarea( $templates['useronline'] ); ?></textarea>
		</div>
		<?php
		self::restore_button( '#wp-useronline-template-useronline' );
	}

	/**
	 * Browsing site template field.
	 *
	 * @return void
	 */
	public static function field_template_browsingsite() {
		self::render_browsing_template( 'browsingsite' );
	}

	/**
	 * Browsing page template field.
	 *
	 * @return void
	 */
	public static function field_template_browsingpage() {
		self::render_browsing_template( 'browsingpage' );
	}

	/**
	 * Render one of the browsing templates and its separators.
	 *
	 * @param string $key Either 'browsingsite' or 'browsingpage'.
	 *
	 * @return void
	 */
	private static function render_browsing_template( $key ) {
		$templates = WP_UserOnline_Options::get( 'templates' );
		$template  = $templates[ $key ];
		$id        = 'wp-useronline-template-' . $key;
		?>
		<div id="<?php echo esc_attr( $id ); ?>">
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%MEMBERS%', '%MEMBER_NAMES%', '%GUESTS_SEPARATOR%', '%GUESTS%', '%BOTS_SEPARATOR%', '%BOTS%' ) ) ); ?>
			</p>
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( self::name( 'templates', $key, 'text' ) ); ?>"
				data-wp-useronline-default="<?php echo esc_attr( self::default_for( array( 'templates', $key, 'text' ) ) ); ?>"
			><?php echo esc_textarea( $template['text'] ); ?></textarea>

			<?php foreach ( array( 'members', 'guests', 'bots' ) as $separator ) : ?>
				<p>
					<label>
						<?php
						/* translators: %s: separator type, one of members, guests or bots. */
						echo esc_html( sprintf( __( '%s separator', 'wp-useronline' ), $separator ) );
						?>
						<?php self::text_input( array( 'templates', $key, 'separators', $separator ), $template['separators'][ $separator ], '8' ); ?>
					</label>
				</p>
			<?php endforeach; ?>
		</div>
		<?php
		self::restore_button( '#' . $id );
	}

	/**
	 * WP-Stats section description.
	 *
	 * @return void
	 */
	public static function section_wpstats() {
		echo '<p>' . esc_html__( 'This only does anything when the WP-Stats plugin is installed.', 'wp-useronline' ) . '</p>';
	}

	/**
	 * WP-Stats section toggle.
	 *
	 * @return void
	 */
	public static function field_stats_display() {
		?>
		<fieldset>
			<label>
				<input type="checkbox" value="1"
					name="<?php echo esc_attr( self::name( 'stats_display' ) ); ?>"
					<?php checked( (bool) WP_UserOnline_Options::get( 'stats_display' ) ); ?> />
				<?php esc_html_e( 'Show a users online section on the WP-Stats page.', 'wp-useronline' ); ?>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
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
		<?php
	}
}
