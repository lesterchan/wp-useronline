<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class UserOnline_WpStats {
	/**
	 * Variables
	 *
	 * @method static
	 *
	 * @var UserOnline_WpStats
	 */
	private static $instance;

	/**
	 * Constructor method
	 *
	 * @access public
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'add_hooks' ) );
	}

	/**
	 * Initializes the plugin object and returns its instance
	 *
	 * @return UserOnline_WpStats
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Adds all the plugin's hooks
	 *
	 * @access public
	 *
	 * @return void
	 */
	public function add_hooks() {
		add_filter( 'wp_stats_page_admin_plugins', array( $this, 'page_admin_general_stats' ) );
		add_filter( 'wp_stats_page_plugins', array( $this, 'page_general_stats' ) );
	}

	/**
	 * Add WP-UserOnline General Stats To WP-Stats Page Options
	 *
	 * @param string $content
	 *
	 * @access public
	 *
	 * @return string
	 */
	public function page_admin_general_stats( $content ) {
		$stats_display = get_option( 'stats_display' );

		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_useronline" value="useronline"' . checked( $stats_display['useronline'], 1, false ) . '/>&nbsp;&nbsp;<label for="wpstats_useronline">'.__( 'WP-UserOnline', 'wp-useronline' ).'</label><br />'."\n";

		return $content;
	}

	/**
	 * Add WP-UserOnline General Stats To WP-Stats Page
	 *
	 * @param string $content
	 *
	 * @access public
	 *
	 * @return string
	 */
	public function page_general_stats( $content ) {
		$stats_display = get_option( 'stats_display' );

		$str = _n(
			'<strong>%s</strong> user online now.',
			'<strong>%s</strong> users online now.',
			get_users_online_count(), 'wp-useronline'
		);

		if ( $stats_display['useronline'] === 1 )
			$content .=
			 html( 'p', html( 'strong', __( 'WP-UserOnline', 'wp-useronline' ) ) )
			.html( 'ul',
				 html( 'li', sprintf( $str, number_format_i18n( get_users_online_count() ) ) )
				.html( 'li', UserOnline_Template::format_most_users() )
			);

		return $content;
	}
}

UserOnline_WpStats::get_instance();
