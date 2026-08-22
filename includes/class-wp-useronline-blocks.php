<?php
/**
 * Block registration.
 *
 * @package WP-UserOnline
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The block is added beside the shortcode, never in place of it.**
 * `[page_useronline]` stays registered, documented and supported, and nothing
 * here deprecates it. This plugin has shipped for fifteen years and that
 * shortcode sits in an unknowable number of published pages; a block that
 * replaced it would render literal text on every one of them on update.
 *
 * The block is dynamic. Its `save` returns null in JavaScript, so what lands in
 * post_content is the block comment and no markup at all -- every view
 * re-renders through the callback below. That is what lets a block and a
 * shortcode share one renderer: the markup is decided once, at render time, for
 * both of them. It also matters more here than in most plugins, because this
 * listing is who is online *now* and saved markup would be a photograph of a
 * moment that has passed.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over `users_online_page()`, which is where the rendering lives
 * and which the AJAX endpoint and the REST heartbeat already share. Routing the
 * block through `do_shortcode()` would break it outright the day anybody
 * unregistered the shortcode, and would put a fourth caller behind a callable
 * that three already reach directly.
 *
 * ## Rendering records nobody
 *
 * This is the one thing about a block here that is not true of the sibling
 * plugins' blocks. Everything else in this collection renders data somebody
 * else wrote; this renders *who is on the site*, and the plugin's own recorder
 * writes the viewer into that same table. So a render that recorded would put
 * the person previewing the block into the figure they are previewing, and
 * would do it again on every `/wp/v2/block-renderer/` request the editor makes.
 *
 * It does not, and the reason is structural rather than careful: recording is
 * hooked to `wp_head` and `admin_head`, which a REST request fires neither of,
 * and `users_online_page()` only reads. That is the same line the REST `count`
 * route draws -- a read records nobody, and only the heartbeat writes -- and
 * `tests/test-blocks.php` pins it in both directions, because the tempting
 * "fix" for an empty editor preview is to record first so there is somebody to
 * list.
 *
 * **One `WP_UserOnline_Blocks` class registers all of a plugin's blocks**,
 * rather than a class per block. There is one today; a class whose entire body
 * is a single `register_block_type_from_metadata()` call would be a file per
 * block for no gain.
 *
 * @since 4.0.0
 */
class WP_UserOnline_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title, supports
	 * and script handle all come out of the `block.json` copied there by the
	 * build, so this class never restates any of them.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'page-useronline' => array( __CLASS__, 'render_page_useronline' ),
		);
	}

	/**
	 * Hook block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Register every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcode working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_USERONLINE_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Render the `wp-useronline/page-useronline` block.
	 *
	 * Takes no attributes, because `[page_useronline]` takes none either: the
	 * listing is a function of the table and of the site's templates, and there
	 * is nothing a post could pass it.
	 *
	 * `users_online_page()` asks for the refresh script on the way past, so a
	 * page whose only user of this plugin is the block still gets the script
	 * that keeps the figures moving.
	 *
	 * @return string
	 */
	public static function render_page_useronline() {
		return users_online_page();
	}
}
