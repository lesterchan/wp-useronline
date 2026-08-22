<?php
/**
 * WP-UserOnline class-wp-useronline-settings.php
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * The two settings tabs, built on the Settings API.
 *
 * WP_UserOnline_Admin owns the menu, the tab strip and the report; this class
 * owns register_setting(), the sections, the field_<name>() callbacks, the form
 * on each of the two settings tabs and the sanitiser. Everything the tabs render
 * comes out of do_settings_sections(), so there is no hand-written form table
 * anywhere below.
 *
 * One register_setting() and one option row across both tabs: a tab is a
 * rendering decision, not a storage one. What separates them is the Settings API
 * page each section is registered against -- see tab_bucket() -- which is what
 * stops one tab drawing the other's fields.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Settings {

	/**
	 * Settings group used by register_setting() and settings_fields().
	 */
	const GROUP = 'wp_useronline_options';

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
	 * The Settings API page a tab's sections and fields are registered against.
	 *
	 * Not a page in any URL sense -- there is one of those and it is
	 * WP_UserOnline_Admin::PAGE. It is the bucket do_settings_sections() reads,
	 * and giving each tab one of its own is all that keeps the tabs apart.
	 *
	 * @param string $tab Tab slug.
	 *
	 * @return string
	 */
	public static function tab_bucket( $tab ) {
		return WP_UserOnline_Admin::PAGE . '-' . $tab;
	}

	/**
	 * Register the setting, its sections and its fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		$settings  = self::tab_bucket( WP_UserOnline_Admin::TAB_SETTINGS );
		$templates = self::tab_bucket( WP_UserOnline_Admin::TAB_TEMPLATES );

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
			$settings
		);

		add_settings_field(
			'timeout',
			__( 'Time Out', 'wp-useronline' ),
			array( __CLASS__, 'field_timeout' ),
			$settings,
			self::SECTION_GENERAL
		);

		add_settings_field(
			'url',
			__( 'UserOnline URL', 'wp-useronline' ),
			array( __CLASS__, 'field_url' ),
			$settings,
			self::SECTION_GENERAL
		);

		add_settings_field(
			'names',
			__( 'Link user names?', 'wp-useronline' ),
			array( __CLASS__, 'field_names' ),
			$settings,
			self::SECTION_GENERAL
		);

		add_settings_field(
			'ip_header',
			__( 'Header That Contains The IP', 'wp-useronline' ),
			array( __CLASS__, 'field_ip_header' ),
			$settings,
			self::SECTION_GENERAL
		);

		add_settings_section(
			self::SECTION_NAMING,
			__( 'Naming Conventions', 'wp-useronline' ),
			array( __CLASS__, 'section_naming' ),
			$settings
		);

		add_settings_field(
			'naming',
			__( 'Names', 'wp-useronline' ),
			array( __CLASS__, 'field_naming' ),
			$settings,
			self::SECTION_NAMING
		);

		add_settings_section(
			self::SECTION_TEMPLATES,
			'',
			'__return_empty_string',
			$templates
		);

		add_settings_field(
			'template_useronline',
			__( 'User(s) Online', 'wp-useronline' ),
			array( __CLASS__, 'field_template_useronline' ),
			$templates,
			self::SECTION_TEMPLATES
		);

		add_settings_field(
			'template_browsingsite',
			__( 'User(s) Browsing Site', 'wp-useronline' ),
			array( __CLASS__, 'field_template_browsingsite' ),
			$templates,
			self::SECTION_TEMPLATES
		);

		add_settings_field(
			'template_browsingpage',
			__( 'User(s) Browsing Page', 'wp-useronline' ),
			array( __CLASS__, 'field_template_browsingpage' ),
			$templates,
			self::SECTION_TEMPLATES
		);

		add_settings_section(
			self::SECTION_WPSTATS,
			__( 'WP-Stats', 'wp-useronline' ),
			array( __CLASS__, 'section_wpstats' ),
			$settings
		);

		add_settings_field(
			'stats_display',
			__( 'Show a users online section?', 'wp-useronline' ),
			array( __CLASS__, 'field_stats_display' ),
			$settings,
			self::SECTION_WPSTATS
		);
	}

	/**
	 * Load the Restore Defaults behaviour, on the two settings tabs only.
	 *
	 * The hook is compared against the one add_menu_page() handed back rather
	 * than against a string spelled out here, which is what survives the screen
	 * being moved or renamed. The tab is checked as well: the report tab is the
	 * same admin page and carries no field with a default to restore.
	 *
	 * @param string $hook_suffix Current admin screen.
	 *
	 * @return void
	 */
	public static function enqueue_scripts( $hook_suffix ) {
		$screen_hook = WP_UserOnline_Admin::screen_hook();

		if ( ! is_string( $hook_suffix ) || '' === $screen_hook || $screen_hook !== $hook_suffix ) {
			return;
		}

		if ( WP_UserOnline_Admin::TAB_USERONLINE === WP_UserOnline_Admin::current_tab() ) {
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
	 * Which request header carries the visitor's address.
	 *
	 * Worded exactly as wp-polls, wp-postratings, wp-email and wp-ban word it.
	 * A site owner meeting the same setting in five of these plugins should not
	 * have to work out five times whether it means the same thing.
	 *
	 * @return void
	 */
	public static function field_ip_header() {
		printf(
			'<input type="text" class="regular-text code" id="wp-useronline-ip-header" name="%s" value="%s" placeholder="HTTP_X_FORWARDED_FOR" />',
			esc_attr( self::name( 'ip_header' ) ),
			esc_attr( (string) WP_UserOnline_Options::get( 'ip_header' ) )
		);

		echo '<p class="description">';
		esc_html_e( 'Leave this blank unless the site is behind a reverse proxy or CDN. Blank means the address the web server saw is used.', 'wp-useronline' );
		echo '<br />';
		printf(
			/* translators: 1: an example header name, 2: the WP_USERONLINE_TRUST_PROXY constant, 3: the wp_useronline_trust_proxy filter, all in code spans. */
			esc_html__( 'Example: %1$s. You can also opt in with the %2$s constant or the %3$s filter, which trust the usual proxy headers instead of one you name.', 'wp-useronline' ),
			'<code>HTTP_X_FORWARDED_FOR</code>',
			'<code>WP_USERONLINE_TRUST_PROXY</code>',
			'<code>wp_useronline_trust_proxy</code>'
		);
		echo '<br />';
		printf(
			'<strong>%s</strong>',
			esc_html__( 'Only name a header your proxy sets and overwrites. A visitor can send any header they like, so trusting one your stack does not control lets anyone appear at any address they choose.', 'wp-useronline' )
		);
		echo '</p>';
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
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( self::name( 'templates', 'useronline' ) ); ?>"
				data-wp-useronline-default="<?php echo esc_attr( self::default_for( array( 'templates', 'useronline' ) ) ); ?>"
			><?php echo esc_textarea( $templates['useronline'] ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%PAGE_URL%', '%MOSTONLINE_COUNT%', '%MOSTONLINE_DATE%' ) ) ); ?>
			</p>
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
			<textarea class="large-text code" rows="3"
				name="<?php echo esc_attr( self::name( 'templates', $key, 'text' ) ); ?>"
				data-wp-useronline-default="<?php echo esc_attr( self::default_for( array( 'templates', $key, 'text' ) ) ); ?>"
			><?php echo esc_textarea( $template['text'] ); ?></textarea>
			<p class="description">
				<?php esc_html_e( 'Allowed variables:', 'wp-useronline' ); ?>
				<?php echo wp_kses_post( self::token_list( array( '%USERS%', '%MEMBERS%', '%MEMBER_NAMES%', '%GUESTS_SEPARATOR%', '%GUESTS%', '%BOTS_SEPARATOR%', '%BOTS%' ) ) ); ?>
			</p>

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
			<?php
			/*
			 * A hidden 0 in front of the box, sharing its name.
			 *
			 * An unticked checkbox posts nothing at all, and the sanitiser keeps
			 * whatever the submission did not mention -- deliberately, because
			 * three tabs write one option row and none of them may blank the
			 * others. Together those would mean this box could be ticked and
			 * never unticked: turning it off posts nothing, the sanitiser reads
			 * the silence as "leave it alone", and it comes back ticked.
			 *
			 * With the hidden field the control always says something. PHP keeps
			 * the last of a repeated name, so ticked posts 1 and unticked 0.
			 */
			?>
			<input type="hidden" name="<?php echo esc_attr( self::name( 'stats_display' ) ); ?>" value="0" />
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
	 * Render one settings tab's form.
	 *
	 * Called by WP_UserOnline_Admin::render_page(), which owns the wrap, the
	 * heading, the notices and the tab strip above this. The capability was
	 * checked there, against the tab's own context.
	 *
	 * @param string $tab Tab slug, one of the two settings tabs.
	 *
	 * @return void
	 */
	public static function render_tab( $tab ) {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( self::GROUP );

			/*
			 * The active tab, carried through the save.
			 *
			 * options.php sends the browser back to the referer, and
			 * settings_fields() records the URL this request came in on -- which
			 * has no tab on it at all when a tab was reached as the page's
			 * default. Saving the settings would then land on the report.
			 * Printing the field again overrides it: PHP keeps the last of a
			 * repeated name.
			 */
			printf(
				'<input type="hidden" name="_wp_http_referer" value="%s" />',
				esc_url( WP_UserOnline_Admin::tab_url( $tab ) )
			);

			do_settings_sections( self::tab_bucket( $tab ) );
			submit_button();
			?>
		</form>
		<?php
	}
}
