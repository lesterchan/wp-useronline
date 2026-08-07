<?php
/**
 * Tests for the `wp useronline` WP-CLI command.
 *
 * @package WP-UserOnline
 */

/**
 * The command reads and never writes, so what is worth pinning is that it reads
 * the right rows, reports an empty site as an answer rather than a failure, and
 * has not quietly grown a destructive subcommand -- the plugin's admin offers
 * no such action, and a command that invented one would be handing out a power
 * the browser never gave anybody.
 */
class WP_UserOnline_CLI_Test extends WP_UserOnline_TestCase {

	/**
	 * Clears everything the stand-in recorded for the previous test.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes     = array();
		WP_CLI::$warnings      = array();
		WP_CLI::$logs          = array();
		WP_CLI::$confirmations = array();
		WP_CLI::$commands      = array();
		WP_CLI::$items         = array();
	}

	/**
	 * Runs one subcommand the way WP-CLI would.
	 *
	 * @param string $subcommand Method to call.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function run_command( $subcommand, $args = array(), $assoc_args = array() ) {
		$command = new WP_UserOnline_Command();
		$command->$subcommand( $args, $assoc_args );
	}

	/**
	 * The rows the last format_items() call was given.
	 *
	 * @return array
	 */
	protected function listed_rows() {
		$this->assertNotEmpty( WP_CLI::$items, 'The command formatted a table.' );

		$last = end( WP_CLI::$items );

		return $last['items'];
	}

	// --- registration ----------------------------------------------------

	/**
	 * The command registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_command_registers_as_useronline() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		WP_UserOnline::register_command();

		$this->assertArrayHasKey( 'useronline', WP_CLI::$commands, 'The command is registered as `wp useronline`.' );
		$this->assertSame( 'WP_UserOnline_Command', WP_CLI::$commands['useronline'], 'WP_UserOnline_Command is what handles it.' );
		$this->assertArrayNotHasKey( 'wp-useronline', WP_CLI::$commands, 'The plugin slug is not also claimed as a command.' );
	}

	/**
	 * The command writes nothing, and that is the design rather than an
	 * oversight.
	 *
	 * @return void
	 */
	public function test_the_command_offers_no_destructive_subcommand() {
		$methods = get_class_methods( 'WP_UserOnline_Command' );

		$this->assertNotEmpty( $methods, 'The command declares subcommands at all, so the check below means something.' );

		foreach ( array( 'delete', 'purge', 'clear', 'reset', 'truncate' ) as $forbidden ) {
			$this->assertNotContains(
				$forbidden,
				$methods,
				'The plugin\'s admin offers no destructive action, so neither does the command: ' . $forbidden . '.'
			);
		}
	}

	// --- list ------------------------------------------------------------

	/**
	 * Listing returns the recorded visitors.
	 *
	 * @return void
	 */
	public function test_list_returns_who_is_online() {
		$this->record_row(
			array(
				'user_name' => 'Alice',
				'user_ip'   => '198.51.100.7',
			)
		);
		$this->record_row(
			array(
				'user_name' => 'Bob',
				'user_ip'   => '198.51.100.8',
			)
		);

		$this->run_command( 'list_' );

		$names = wp_list_pluck( $this->listed_rows(), 'name' );

		$this->assertContains( 'Alice', $names, 'The first visitor is listed.' );
		$this->assertContains( 'Bob', $names, 'And so is the second.' );
	}

	/**
	 * Each listed row carries the page the visitor is on.
	 *
	 * @return void
	 */
	public function test_list_carries_the_page_each_visitor_is_on() {
		$this->record_row(
			array(
				'user_name'  => 'Alice',
				'page_title' => 'The about page',
			)
		);

		$this->run_command( 'list_' );

		$row = $this->listed_rows()[0];

		$this->assertSame( 'The about page', $row['page'], 'The row says which page they are reading.' );
		$this->assertSame( 'guest', $row['type'], 'And whether they are a guest or a member.' );
	}

	/**
	 * A quiet site is reported as a success, not an error.
	 *
	 * @return void
	 */
	public function test_list_with_nobody_online_is_not_an_error() {
		$this->run_command( 'list_' );

		$this->assertNotEmpty( WP_CLI::$successes, 'Nobody online is reported on the success channel.' );
		$this->assertEmpty( WP_CLI::$items, 'No table is printed when there is nothing to put in it.' );
	}

	/**
	 * Listing records nobody: reading who is online must not add the reader.
	 *
	 * @return void
	 */
	public function test_list_does_not_record_the_caller() {
		$this->record_row();

		$before = $this->rows();

		$this->run_command( 'list_' );

		$this->assertSame( $before, $this->rows(), 'A monitoring script does not appear in the figures it is reading.' );
	}

	// --- count -----------------------------------------------------------

	/**
	 * Counting reports the current number and the record.
	 *
	 * @return void
	 */
	public function test_count_reports_online_and_the_record() {
		$this->record_row( array( 'user_ip' => '198.51.100.7' ) );
		$this->record_row( array( 'user_ip' => '198.51.100.8' ) );

		$this->run_command( 'count' );

		$values = wp_list_pluck( $this->listed_rows(), 'value', 'field' );

		$this->assertSame( 2, $values['online'], 'Both visitors are counted.' );
		$this->assertArrayHasKey( 'most', $values, 'And the record is reported beside it.' );
	}

	/**
	 * Counting records nobody either.
	 *
	 * @return void
	 */
	public function test_count_does_not_record_the_caller() {
		$this->record_row();

		$before = $this->rows();

		$this->run_command( 'count' );

		$this->assertSame( $before, $this->rows(), 'Asking how many are online does not make it one more.' );
	}
}
