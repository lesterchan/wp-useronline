/**
 * The `wp-useronline/page-useronline` block.
 *
 * The listing the `[page_useronline]` shortcode renders: who is on the site
 * right now, grouped by members, guests and bots, above the most-ever-online
 * record. It takes no attributes, because the shortcode takes none either --
 * `users_online_page()` is a function of the table and the site's templates and
 * of nothing a post can pass it.
 *
 * The block name is hyphenated where the shortcode is underscored: a block name
 * must match [a-z0-9-] and an underscore is not allowed in one. That is the
 * only reason the two spellings differ.
 *
 * `multiple` is false in block.json, and that is not tidiness. What the render
 * returns carries its own `#useronline-details` element -- the shortcode has no
 * theme markup to sit inside, so the container is part of the answer -- and the
 * refresh script finds that element by id and replaces it on every poll. Two of
 * these in one post is a duplicate id and a script updating whichever one it
 * reached first.
 */

import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';

import metadata from './block.json';

/**
 * The editor view.
 *
 * Capitalised and named rather than an `edit()` shorthand because useBlockProps
 * is a React hook, and the hook rules identify a component by that capital.
 *
 * @return {Element} The editor view.
 */
function Edit() {
	return (
		<div { ...useBlockProps() }>
			{ /* The listing is made of links -- each visitor's page, their
			     referrer, and an address lookup for anyone allowed to see the
			     addresses -- so a live preview is a preview that navigates the
			     editor away from the post being written. */ }
			<div inert="">
				<ServerSideRender block={ metadata.name } />
			</div>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,

	save() {
		return null;
	},
} );
