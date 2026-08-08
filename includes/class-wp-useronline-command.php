<?php
/**
 * The WP-CLI command.
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports who is on the site, from the command line.
 *
 * Registered as `wp useronline` rather than `wp wp-useronline`. A `wp-` prefix
 * is a wordpress.org directory convention for keeping one plugin's download
 * page apart from another's, not a command-naming one, and after the `wp`
 * binary it only stutters; WP-CLI's own ecosystem names commands after the
 * brand -- `wp wc`, `wp yoast`, `wp jetpack`. WP-CLI gives a collision to
 * whichever plugin registered last and a bare noun is claimable by anyone; that
 * is the accepted trade for a word as distinctive as `useronline`.
 *
 * **This command reads and never writes, and the omission is deliberate.** The
 * plugin's admin screen offers no destructive action -- no "clear the table",
 * no "reset the record" -- and the online table maintains itself, because
 * `WP_UserOnline_Recorder::record()` drops timed-out rows on every visit. A
 * `purge` subcommand would therefore be a power this plugin has never given
 * anybody, invented so that the command looks more substantial. Reading who is
 * on the site from a shell or a monitoring cron is the whole use.
 */
class WP_UserOnline_Command extends WP_CLI_Command {

	/**
	 * List who is on the site right now.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Who is on the site.
	 *     $ wp useronline list
	 *
	 *     # Just how many, for a monitoring script.
	 *     $ wp useronline list --format=count
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$users  = WP_UserOnline_Template::compact_list( 'site', 'list' );

		if ( empty( $users ) ) {
			// Nobody online is an answer, not a failure -- and on a quiet site
			// it is the usual one.
			WP_CLI::success( __( 'Nobody is online.', 'wp-useronline' ) );

			return;
		}

		$rows = array();

		foreach ( $users as $user ) {
			$rows[] = array(
				'id'    => (int) $user->user_id,
				'name'  => $user->user_name,
				'type'  => $user->user_type,
				'ip'    => $user->user_ip,
				'page'  => $user->page_title,
				'since' => $user->timestamp,
			);
		}

		$this->print_rows( $format, $rows, array( 'id', 'name', 'type', 'ip', 'page', 'since' ) );
	}

	/**
	 * Show how many are online now, and the record.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp useronline count
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function count( $args, $assoc_args ) {
		unset( $args );

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$rows = array(
			array(
				'field' => 'online',
				'value' => WP_UserOnline_Recorder::count(),
			),
			array(
				'field' => 'most',
				'value' => (int) WP_UserOnline_Options::most( 'count' ),
			),
			array(
				'field' => 'most_date',
				'value' => get_most_users_online_date(),
			),
		);

		$this->print_rows( $format, $rows, array( 'field', 'value' ) );
	}

	/**
	 * Print rows, reducing to the first column when ids were asked for.
	 *
	 * WP_CLI\Utils\format_items() hands `ids` straight to implode(), so a row
	 * that is an associative array comes out as the word `Array` and a PHP
	 * warning -- which is what `--format=ids` did here until it was run against
	 * a real WP-CLI rather than reasoned about. Reducing to the first column is
	 * what makes the documented `| xargs` pipes work.
	 *
	 * @param string $format Requested output format.
	 * @param array  $rows   Rows to print.
	 * @param array  $fields Columns, in order. The first is the id.
	 * @return void
	 */
	private function print_rows( $format, array $rows, array $fields ) {
		if ( 'ids' === $format ) {
			WP_CLI\Utils\format_items( $format, wp_list_pluck( $rows, $fields[0] ), $fields );

			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
