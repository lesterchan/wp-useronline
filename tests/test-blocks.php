<?php
/**
 * Tests for the blocks.
 *
 * @package WP-UserOnline
 */

/**
 * The block, and the promise that it is an addition rather than a replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is
 * one line -- but the four things a later change could quietly break:
 *
 * * the shortcode still works, because it sits in published pages everywhere;
 * * the block and the shortcode render the *same* markup, because they are
 *   meant to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops one dying with the other;
 * * **rendering records nobody.** This plugin's renderer draws a picture of who
 *   is on the site, and the plugin also writes the viewer into the table that
 *   picture comes from. Those two facts are one line of code apart, and the
 *   tempting fix for an empty editor preview is to close the gap.
 */
class WP_UserOnline_Blocks_Test extends WP_UserOnline_TestCase {

	/**
	 * The block this plugin registers.
	 *
	 * @var string
	 */
	const BLOCK = 'wp-useronline/page-useronline';

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshot the global state these tests deliberately break.
	 *
	 * Two tests below unregister the shortcode or the block on purpose, to
	 * prove neither entry point is implemented in terms of the other. Both
	 * registries are process-global and WP_UnitTestCase restores neither, so
	 * without this the first such test silently disarms every test that runs
	 * after it -- and they fail with `[page_useronline]` rendering as literal
	 * text, which reads as a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_blocks();
	}

	/**
	 * Put both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_blocks();

		parent::tear_down();
	}

	/**
	 * Return the block registry to exactly the one registered block.
	 *
	 * Unregisters before registering rather than registering conditionally:
	 * the plugin has already registered it on `init` by the time any test
	 * runs, and registering a second time is a doing_it_wrong notice that the
	 * suite fails on.
	 *
	 * @return void
	 */
	private function restore_blocks() {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK ) ) {
			unregister_block_type( self::BLOCK );
		}

		WP_UserOnline_Blocks::register();
	}

	/**
	 * A crowd, so that the listing has something to list.
	 *
	 * Two members, a guest and a bot, each with an address and a timestamp of
	 * its own. The addresses because the table's unique key is the timestamp,
	 * the type and the address, and the timestamps because the listing is
	 * ordered newest first with no tie-break -- so two rows written in the same
	 * second make "#1 - Ada" a coin toss rather than an assertion.
	 *
	 * @return void
	 */
	private function record_crowd() {
		$now = strtotime( current_time( 'mysql' ) );

		$this->record_row(
			array(
				'timestamp' => gmdate( 'Y-m-d H:i:s', $now ),
				'user_type' => 'member',
				'user_id'   => 5,
				'user_name' => 'Ada',
				'user_ip'   => '198.51.100.11',
			)
		);
		$this->record_row(
			array(
				'timestamp' => gmdate( 'Y-m-d H:i:s', $now - 10 ),
				'user_type' => 'member',
				'user_id'   => 6,
				'user_name' => 'Grace',
				'user_ip'   => '198.51.100.12',
			)
		);
		$this->record_row(
			array(
				'timestamp' => gmdate( 'Y-m-d H:i:s', $now - 20 ),
				'user_ip'   => '198.51.100.13',
			)
		);
		$this->record_row(
			array(
				'timestamp'  => gmdate( 'Y-m-d H:i:s', $now - 30 ),
				'user_type'  => 'bot',
				'user_name'  => 'Googlebot',
				'user_ip'    => '198.51.100.14',
				'user_agent' => 'Googlebot/2.1',
			)
		);
	}

	// --- registration ----------------------------------------------------

	/**
	 * The block registers, under the prefixed name.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * the command and the REST namespace does not carry: those drop it, because
	 * a collision there is survivable and visible. A block name is written into
	 * post_content and stays there for the life of the post, so a collision
	 * would render another plugin's block inside somebody's published pages.
	 *
	 * @return void
	 */
	public function test_the_block_registers_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( self::BLOCK ), 'The listing block registers.' );

		$this->assertFalse( $registry->is_registered( 'useronline/page-useronline' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The block is dynamic, so it carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that neither does.
	 * Here it is also the difference between a listing and a photograph: what
	 * this renders is who is online at the moment somebody looks.
	 *
	 * @return void
	 */
	public function test_the_block_is_dynamic() {
		$this->assertIsCallable(
			WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->render_callback,
			'The listing block renders server-side.'
		);
	}

	/**
	 * Only one of these may sit in a post.
	 *
	 * `users_online_page()` bakes `#useronline-details` into what it returns --
	 * the shortcode has no theme markup to sit inside, so the container is part
	 * of the answer -- and the refresh script finds that element by id and
	 * replaces it on every poll. A second block on the page is a duplicate id
	 * and a script updating whichever one it reached first.
	 *
	 * @return void
	 */
	public function test_the_block_cannot_be_added_twice() {
		$supports = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK )->supports;

		$this->assertFalse( $supports['multiple'], 'The listing carries a unique element id, so one to a post.' );
	}

	// --- the shortcode survives ------------------------------------------

	/**
	 * Adding the block did not unregister the shortcode.
	 *
	 * If this ever fails, the block has stopped being an addition and become a
	 * replacement, and every published page holding `[page_useronline]` renders
	 * literal text.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_still_registered() {
		$this->assertTrue( shortcode_exists( 'page_useronline' ), 'The shortcode survives the block.' );
	}

	// --- the block and the shortcode agree -------------------------------

	/**
	 * The block and the shortcode render the same listing identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_render_the_same_markup() {
		$this->record_crowd();

		$block     = WP_UserOnline_Blocks::render_page_useronline();
		$shortcode = do_shortcode( '[page_useronline]' );

		$this->assertStringContainsString( 'id="useronline-details"', $block, 'The block rendered the listing.' );
		$this->assertStringContainsString( '#1 - Ada', $block, 'And the listing has the crowd in it.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * They agree when there is nobody to list, too.
	 *
	 * The empty case is a different branch of the renderer, and it is the one
	 * an editor previewing the block on a quiet site will actually see.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_agree_when_nobody_is_online() {
		$block = WP_UserOnline_Blocks::render_page_useronline();

		$this->assertStringContainsString( 'No one is online now.', $block, 'The empty listing says so.' );
		$this->assertSame( do_shortcode( '[page_useronline]' ), $block, 'And the two entry points agree about it.' );
	}

	/**
	 * The site's templates reach the block, because they reach the renderer.
	 *
	 * Every string in this listing is a setting somebody can edit, and a block
	 * that rendered its own wording would be a second place to change them.
	 *
	 * @return void
	 */
	public function test_the_block_renders_through_the_sites_templates() {
		$this->record_crowd();

		$naming          = WP_UserOnline_Options::get( 'naming' );
		$naming['users'] = '%COUNT% people about the place';

		$this->set_options( array( 'naming' => $naming ) );
		$this->reset_statics();

		$this->assertStringContainsString( '4 people about the place', WP_UserOnline_Blocks::render_page_useronline(), 'The block uses the stored naming.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The block does not render by running the shortcode.
	 *
	 * Routing a block through do_shortcode() would break it outright the day
	 * anybody unregistered the shortcode -- and a site that has decided its
	 * editors may not use shortcodes is a real thing. So: unregister the
	 * shortcode, and assert the block carries on rendering.
	 *
	 * @return void
	 */
	public function test_the_block_renders_with_the_shortcode_unregistered() {
		$this->record_crowd();

		remove_shortcode( 'page_useronline' );

		$this->assertStringContainsString( 'id="useronline-details"', WP_UserOnline_Blocks::render_page_useronline(), 'The block does not need the shortcode.' );
	}

	/**
	 * The shortcode does not render by running the block.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcode_renders_with_the_block_unregistered() {
		$this->record_crowd();

		unregister_block_type( self::BLOCK );

		$this->assertStringContainsString( 'id="useronline-details"', do_shortcode( '[page_useronline]' ), 'The shortcode does not need the block.' );
	}

	// --- rendering records nobody ----------------------------------------

	/**
	 * Rendering the block writes no row into the table.
	 *
	 * The whole of this plugin's difference from its siblings. Recording is
	 * hooked to wp_head and admin_head, and the editor's preview arrives on
	 * /wp/v2/block-renderer/ which fires neither -- so the person writing the
	 * post does not appear in the listing they are previewing, and a preview
	 * refreshed ten times does not write ten times.
	 *
	 * Asserted rather than reasoned about, because the obvious fix for an
	 * editor preview that says "No one is online now." is to record first so
	 * there is somebody to list.
	 *
	 * @return void
	 */
	public function test_rendering_the_block_records_nobody() {
		$this->record_crowd();

		$before = $this->rows();

		WP_UserOnline_Blocks::render_page_useronline();

		$this->assertSame( $before, $this->rows(), 'Rendering the listing did not add anybody to it.' );
	}

	/**
	 * Nor does it move the most-ever-online record.
	 *
	 * That figure is the one number in this plugin with nowhere to recover it
	 * from, so a render that nudged it upward would be a permanent lie told by
	 * a preview. `record()` is the only thing that may touch it, and rendering
	 * is not recording.
	 *
	 * @return void
	 */
	public function test_rendering_the_block_does_not_move_the_most_ever_record() {
		$this->record_crowd();

		WP_UserOnline_Options::update_most( 1, time() );

		WP_UserOnline_Blocks::render_page_useronline();

		$this->assertSame( 1, (int) WP_UserOnline_Options::most( 'count' ), 'The high-water mark is the recorder\'s to move, not the renderer\'s.' );
	}

	/**
	 * The block does ask for the refresh script, though.
	 *
	 * The one side effect the render is supposed to have, and it is a side
	 * effect on this request only. A page whose sole use of this plugin is the
	 * block still needs the script, or the figures it shows are correct once
	 * and then stay where they are.
	 *
	 * @return void
	 */
	public function test_the_block_asks_for_the_refresh_script() {
		$this->assertFalse( WP_UserOnline_Template::needs_script(), 'Nothing has asked for it yet.' );

		WP_UserOnline_Blocks::render_page_useronline();

		$this->assertTrue( WP_UserOnline_Template::needs_script(), 'A block-only page gets the refresh script.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the listing.
	 *
	 * The tests above call the callback directly, which does not prove the
	 * registration wired it to the name that gets saved into post_content.
	 * This goes through do_blocks(), the way a published post does.
	 *
	 * @return void
	 */
	public function test_a_saved_block_renders_through_the_block_parser() {
		$this->record_crowd();

		$rendered = do_blocks( '<!-- wp:wp-useronline/page-useronline /-->' );

		$this->assertStringContainsString( 'id="useronline-details"', $rendered, 'The saved block renders the listing.' );
		$this->assertStringContainsString( '#1 - Ada', $rendered, 'With the crowd in it.' );
	}

	/**
	 * And it records nobody on the way through the parser either.
	 *
	 * The direct call above proves the callback is a read; this proves nothing
	 * between the parser and the callback writes on its behalf.
	 *
	 * @return void
	 */
	public function test_a_saved_block_records_nobody_either() {
		$this->record_crowd();

		$before = $this->rows();

		do_blocks( '<!-- wp:wp-useronline/page-useronline /-->' );

		$this->assertSame( $before, $this->rows(), 'Rendering a published post did not add a row.' );
	}
}
