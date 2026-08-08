/**
 * Shared helpers for the script tests.
 */
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const PLUGIN_ROOT = join( dirname( fileURLToPath( import.meta.url ) ), '..', '..' );

/**
 * Read one of the plugin's own files off disk.
 *
 * Through node:path rather than `new URL( '<literal>', import.meta.url )`:
 * Vite rewrites that pattern into an asset URL it serves over http, which
 * readFileSync then refuses to open. It only does so for a literal, so the
 * concatenated form here used to work by accident.
 *
 * @param {string} name Path relative to the plugin root.
 * @return {string} File contents.
 */
function pluginFile( name ) {
	return readFileSync( join( PLUGIN_ROOT, name ), 'utf8' );
}

/**
 * Evaluate one of the plugin's scripts in the current jsdom page.
 *
 * The scripts are IIFEs with no exports that attach listeners as they run, so
 * they are loaded the way a browser would rather than imported.
 *
 * Anything the script reads as it evaluates -- the l10n object for the front
 * end script -- has to exist on window first. Evaluate once per test file: a
 * second evaluation adds a second set of listeners and every handler then
 * fires twice.
 *
 * @param {string} name Path relative to the plugin root.
 */
export function loadScript( name ) {
	new Function( pluginFile( name ) )();
}

/**
 * The localisation object the front end script reads.
 *
 * Values arrive from wp_localize_script() as strings, which is why the script
 * coerces the timeout rather than trusting it.
 *
 * @return {Object} l10n object.
 */
export function l10nFixture() {
	return {
		ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
		timeout: '30000',
		// Empty for a logged-out visitor, which is what the server sends them.
		nonce: '',
	};
}

/**
 * The four containers the front end script polls for.
 *
 * The ids are unprefixed on purpose: they are the ones the readme has told
 * theme authors to put on the page since long before 4.0.0, so they are public
 * API and were deliberately left alone when everything else was prefixed.
 *
 * The details container is not built here: it is the one the plugin's own PHP
 * emits, so it is read out of that file instead. A fixture written by hand
 * would be a test of the fixture.
 *
 * @param {string[]} modes Which containers to render.
 * @return {string} Markup.
 */
export function containerMarkup( modes = [ 'count' ] ) {
	return modes
		.map( function( mode ) {
			if ( 'details' === mode ) {
				return detailsMarkup( 'old' );
			}

			return '<div id="useronline-' + mode + '">old</div>';
		} )
		.join( '' );
}

/**
 * The opening tag users_online_page() wraps the detailed report in.
 *
 * Read out of includes/template-tags.php rather than written out here, because
 * the whole question the details tests ask is whether the server's wrapper and
 * the script's target are the same element. A hand-written copy would agree
 * with the script by construction and prove nothing about the plugin; this
 * throws instead, loudly, the day that wrapper moves or changes shape.
 *
 * @return {string} The literal opening tag, e.g. `<div id="useronline-details">`.
 */
export function detailsWrapper() {
	const found = pluginFile( 'includes/template-tags.php' ).match(
		/\$output\s*=\s*'(<div id="useronline-details">)'/,
	);

	if ( ! found ) {
		throw new Error(
			'users_online_page() no longer opens with a #useronline-details wrapper; the refresh script targets one.',
		);
	}

	return found[ 1 ];
}

/**
 * The detailed report as the server sends it: content inside its own container.
 *
 * This is both what the page renders for [page_useronline] and what the
 * details mode of the AJAX endpoint answers with -- one string, because they
 * are one function.
 *
 * @param {string} body What the container holds.
 * @return {string} Markup.
 */
export function detailsMarkup( body ) {
	return detailsWrapper() + body + '</div>';
}

/**
 * The settings screen markup the Settings API renders around one field group.
 *
 * @return {string} Markup.
 */
export function settingsMarkup() {
	return (
		'<table id="wp-useronline-naming">' +
		'<tr><td>' +
		'<input type="text" name="wp_useronline_options[naming][user]"' +
		' value="changed" data-wp-useronline-default="1 User" />' +
		'<input type="text" name="wp_useronline_options[naming][users]"' +
		' value="changed too" data-wp-useronline-default="%COUNT% Users" />' +
		'</td></tr>' +
		'</table>' +
		'<p><button type="button" class="button wp-useronline-restore"' +
		' data-target="#wp-useronline-naming">Restore Defaults</button></p>' +
		'<input type="text" id="outside" value="untouched"' +
		' data-wp-useronline-default="a default" />'
	);
}
