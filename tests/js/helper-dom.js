/**
 * Shared helpers for the script tests.
 */
import { readFileSync } from 'node:fs';

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
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
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
	};
}

/**
 * The four containers the front end script polls for.
 *
 * The ids are unprefixed on purpose: they are the ones the readme has told
 * theme authors to put on the page since long before 4.0.0, so they are public
 * API and were deliberately left alone when everything else was prefixed.
 *
 * @param {string[]} modes Which containers to render.
 * @return {string} Markup.
 */
export function containerMarkup( modes = [ 'count' ] ) {
	return modes
		.map( function( mode ) {
			return '<div id="useronline-' + mode + '">old</div>';
		} )
		.join( '' );
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
