# WP-UserOnline
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: useronline, usersonline, wp-useronline, online, widget  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 4.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable you to display how many users are online on your WordPress site with detailed statistics.

## Description

WP-UserOnline shows how many people are on your site right now, and where they are. Members are named, guests and search bots are counted, and the whole thing refreshes itself in the background without a page reload.

### Features

* A counter, a "who is browsing this page" line and a "who is browsing the site" line, each usable on its own.
* A full users online page, listing every visitor with the page they are on, where they came from and when they arrived.
* A sidebar widget offering any of those five combinations.
* Every string is a template you can edit, so the wording is yours rather than the plugin's.
* Around three hundred search bots recognised by user agent, and a filter for adding your own.
* An admin screen listing everyone online now, plus a line in the Dashboard's At a Glance panel.
* A section on the WP-Stats page when that plugin is installed.

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage

The simplest way is the widget. Go to `WP-Admin -> Appearance -> Widgets`, add the **UserOnline** widget to a sidebar, and pick which of the five statistics types it should show.

A classic theme can call the template tags directly instead, anywhere in `sidebar.php`, `header.php` or a template part:

```php
<?php if ( function_exists( 'users_online' ) ) : ?>
	<p>Users online: <span id="useronline-count"><?php users_online(); ?></span></p>
<?php endif; ?>
```

The element ids matter: `useronline-count`, `useronline-browsing-site`, `useronline-browsing-page` and `useronline-details` are what the refresh script looks for. A figure printed outside one of them is correct when the page loads and then stays where it is.

To give visitors a page of their own listing everyone online, create a page and put the shortcode in it:

```
[page_useronline]
```

The settings live at **WP-Admin -> WP-UserOnline -> Settings**, and the list of who is online right now at **WP-Admin -> WP-UserOnline -> Users Online**.

## Frequently Asked Questions

### Creating A UserOnline Page
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you ARE using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `useronline` in the text field and click 'Save'.
1. Type `[page_useronline]` in the post's content area
1. Click 'Publish'

If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> WP-UserOnline -> Settings` and under 'UserOnline URL', you need to fill in the URL to the UserOnline Page you created above.

### To Display Most Number Of Users Online
* Use:
```php
<?php if ( function_exists( 'get_most_users_online' ) ) : ?>
	<p>Most Users Ever Online Is <?php echo get_most_users_online(); ?> On <?php echo get_most_users_online_date(); ?></p>
<?php endif; ?>
```

### To Display Users Browsing Site
* Use:
```php
<?php if ( function_exists( 'get_users_browsing_site' ) ) : ?>
	<div id="useronline-browsing-site"><?php echo get_users_browsing_site(); ?></div>
<?php endif; ?>
```

### To Display Users Browsing A Page
* Use:
```php
<?php if ( function_exists( 'get_users_browsing_page' ) ) : ?>
	<div id="useronline-browsing-page"><?php echo get_users_browsing_page(); ?></div>
<?php endif; ?>
```

### Every visitor shows the same IP address

Your site is behind a reverse proxy or CDN — Cloudflare, a load balancer, nginx in
front of Apache — so the address PHP sees is the proxy's, not the visitor's.

The real address is in the `X-Forwarded-For` header, but WP-UserOnline ignores that
header by default: any client can send it with any value, so trusting it blindly lets
a visitor forge their address. Opt in only if a proxy you control actually sets it, by
adding this to `wp-config.php` above the `/* That's all, stop editing! */` line:

```php
define( 'WP_USERONLINE_TRUST_PROXY', true );
```

If you need to decide at runtime — say, only trust it for requests arriving from your
load balancer — use the filter instead:

```php
add_filter( 'wp_useronline_trust_proxy', function () {
	return isset( $_SERVER['REMOTE_ADDR'] ) && '10.0.0.1' === $_SERVER['REMOTE_ADDR'];
} );
```

With neither set, the plugin records `REMOTE_ADDR` — correct on a plain host, and the
proxy's address behind one.

Both the constant and the filter were renamed in 4.0.0, from `USERONLINE_TRUST_PROXY`
and `useronline_trust_proxy`. The old names do nothing at all now, so if your site was
already opted in, rename them or every visitor will start showing the proxy's address.

### Who can see the users online screen?

Anyone with `manage_options`, which in practice means administrators. Before 4.0.0 the
screen was open to anyone who could `list_users`, which on many sites included editors.
To put it back that way, filter the capability — the context tells the two screens
apart, so this does not open the settings screen at the same time:

```php
add_filter( 'wp_useronline_capability', function ( $capability, $context ) {
	return 'useronline' === $context ? 'list_users' : $capability;
}, 10, 2 );
```

### Can I change the list of search bots?

Yes. `wp_useronline_bots` filters the whole list, whose keys are the names shown in the
users online list and whose values are the case-insensitive fragment looked for in the
user agent:

```php
add_filter( 'wp_useronline_bots', function ( $bots ) {
	$bots['My Crawler'] = 'mycrawler';

	return $bots;
} );
```

### The plugin will not activate

WP-UserOnline 4.0 and later requires WordPress 6.8 and PHP 8.2. WordPress checks both and
refuses to activate the plugin on anything older, telling you which one is short.

To see what your host is running, look at `Tools -> Site Health -> Info -> Server`, or
install [WP-ServerInfo](https://wordpress.org/plugins/wp-serverinfo/).

If you cannot upgrade, WP-UserOnline 3.0.0 is the last release supporting WordPress 6.0
and PHP 7.4.

## Screenshots

1. Admin - Dashboard's Right Now
2. UserOnline Page
3. Admin - Settings Page

## Changelog
### 4.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4. A site on an older stack simply will not be offered the update.
* BREAKING: Every filter the plugin fires is renamed and the old names are dropped, with no deprecation shims: `useronline_bots`, `useronline_buckets`, `useronline_custom_template`, `useronline_page`, `useronline_display_user` and `useronline_trust_proxy` all become `wp_useronline_*`. This voids the promise made in the 3.0.0 changelog that the filters were unchanged, and is why this release is 4.0.0 rather than 3.0.1.
* BREAKING: `USERONLINE_TRUST_PROXY` is now `WP_USERONLINE_TRUST_PROXY`. A site still defining the old name silently stops trusting its proxy and starts recording the proxy's address for every visitor. See the FAQ.
* BREAKING: The two screens moved into one top-level WP-UserOnline menu, Users Online first and Settings last, at `admin.php?page=wp-useronline` and `admin.php?page=wp-useronline-settings`. They used to be under Dashboard and under Settings respectively.
* BREAKING: Both screens now require `manage_options`. The users online screen previously needed only `list_users`. The new `wp_useronline_capability` filter puts it back, per screen.
* BREAKING: The option rows are renamed. `useronline` becomes `wp_useronline_options` and `useronline_most` becomes `wp_useronline_most`, which is no longer autoloaded. Your settings are migrated automatically on the first load after the update.
* BREAKING: The shared, unprefixed `stats_display` option row is no longer read. WP-Stats integration is now a setting of this plugin's own, and WP-Stats asks each plugin for its section through the `wp_stats_sections` filter rather than reading anybody's options. Update all seven WP-Stats-aware plugins together; see the Upgrade Notice.
* BREAKING: Every class is renamed to `WP_UserOnline_*`. `UserOnline`, `UserOnline_Template`, `UserOnline_Options` and the rest no longer exist under those names.
* BREAKING: The refresh script is at `js/wp-useronline.js` rather than `useronline.js`, its localised object is `wpUserOnlineL10n` rather than `useronlineL10n`, and the admin-ajax action it posts is `wp_useronline` rather than `useronline`.
* NEW: The upgrade markers live in their own `wp_useronline_version` option row, holding exactly `plugin` and `db`, and are written together in one update at the end of the upgrade.
* NEW: WP-Stats integration is a setting on the plugin's own settings screen, under WP-Stats, instead of a checkbox added to the WP-Stats options page.
* NEW: `wp_useronline_capability` filters the capability each screen requires, with a context saying which screen is asking.
* NEW: The settings screen's Restore Defaults behaviour is a proper enqueued script, `js/wp-useronline-admin.js`, rather than markup printed inside the page.
* NEW: The JavaScript is covered by vitest and jsdom, and the PHP suite runs on the network as well as on a single site.
* CHANGED: `WP_UserOnline_Install` owns the table, the migration and the markers, so install and uninstall sit beside each other and neither can drift.
* FIXED: The upgrade markers no longer live inside the settings array. They were kept under a reserved `versions` key that the settings form never posted, so every save had to rescue them from the stored value by hand — which is the arrangement behind the 3.0.0 bug where the marker could not be saved at all once the settings screen had been loaded. The sanitiser is now a pure function of what was posted and reads no options at all.
* FIXED: The settings screen no longer emits an inline `style` attribute or an inline `<script>` block.
* NOTE: The template tags, the `[page_useronline]` shortcode and the `useronline-count`, `useronline-browsing-site`, `useronline-browsing-page` and `useronline-details` element ids are unchanged. Everything else that was public has moved; see the Upgrade Notice.

### 3.0.0
* NEW: Dropped the bundled WP-SCB Framework. The plugin no longer ships ~3,600 lines of third party framework code and has no submodule.
* NEW: Rewritten on WordPress core APIs: the Settings API for the options screen, WP_Widget for the widget, dbDelta plus a schema version for the table, and register_activation_hook for install.
* NEW: Classes moved to includes/class-useronline-*.php, matching the folder structure in the Plugin Handbook and the class-*.php naming in the WordPress Coding Standards.
* NEW: The refresh script is now vanilla JavaScript using fetch(). jQuery is no longer enqueued by this plugin at all.
* NEW: Dropped useronline.dev.js. The script ships as a single readable useronline.js, which is under a kilobyte gzipped.
* NEW: Requires WordPress 6.0 and PHP 7.4.
* NEW: Tested up to WordPress 7.0.
* NEW: Internal version markers moved inside the useronline option instead of their own rows, so the plugin owns fewer autoloaded options.
* NEW: X-Forwarded-For is ignored unless the site opts in. Behind a reverse proxy or CDN, define USERONLINE_TRUST_PROXY as true, or use the useronline_trust_proxy filter, to keep recording visitor IPs instead of the proxy IP. See the FAQ.
* NEW: PHPUnit test suite and GitHub Actions CI.
* FIXED: A user browsing wp-admin no longer shows the previously listed user's page title, URL and referrer in place of their own.
* FIXED: The Users Online Count widget never loaded the refresh script, so its number never updated. This affected earlier releases too.
* FIXED: Restore Defaults cleared the Time Out field instead of resetting it to 300.
* FIXED: Settings are re-sanitised on upgrade, not only when saved, so values stored by older versions are cleaned up.
* FIXED: The upgrade marker could not be saved once the settings screen had been loaded, which made the sanitise step and the table check re-run on every request.
* FIXED: The AJAX endpoint validates the requested mode before recording anything, and rejects page URLs that do not belong to this site.
* FIXED: Page titles and names containing quotes or backslashes are no longer mangled when recorded.
* FIXED: Uninstall on multisite used a deprecated function, stopped at 100 sites, and dropped the table once per option instead of once per site.
* FIXED: Undefined array key warnings when no members are online and when REMOTE_ADDR is not set.
* NOTE: This entry originally said the template tags, the [page_useronline] shortcode and all four filters were unchanged. That was true of 3.0.0, but 4.0.0 renames all four filters and the proxy constant. The template tags and the shortcode are still unchanged. Themes calling UserOnline_Core or UserOnline_Template directly needed updating in 3.0.0, and again in 4.0.0.

### 2.88.9
* FIXED: Check scbWidget exists first before loading scbWidget. Props @whiteshadow.

### 2.88.8
* FIXED: Remove widget code from useronline_init

### 2.88.7
* FIXED: WP SCB Framework use init hook
* FIXED: Widget now loads seperately in it is own hook

### 2.88.6
* FIXED: Revert WP SCB Framework to use plugins_loaded hook

### 2.88.5
* FIXED: Update WP SCB Framework to fix load_textdomain_just_in_time warning
* FIXED: Remove load_plugin_textdomain since it is no longer needed since WP 4.6

### 2.88.4
* FIXED: Add load_plugin_textdomain during init
* NEW: Update bots

### 2.88.3
* FIXED: Strip all tags before inserting data into the DB.

### 2.88.2
* FIXED: Fixed XS. Props Alex.

### 2.88.1
* FIXED: Fixed XSS. Props Juampa Rodriguez.

### 2.88.0
* NEW: Bump to WordPress 6.0.
* FIXED: Fixed XSS. Props @steffinstanly.

### 2.85.6
* NEW: Bump to WordPress 5.6
* NEW: Added more bots
* NEW: Remove hardcoded Archive text in page title
* FIXED: Update SCB Framework to support PHP 8

### 2.85.5
* NEW: Bump to WordPress 5.4
* NEW: Added more bots

### 2.87.4
* NEW: Bump to WordPress 5.3
* NEW: Added more bots
* FIXED: Update SCB Framework To Remove contextual_help

### 2.87.3
* FIXED: Duplicated Settings Saved admin_notices
* FIXED: Missing arrow
* FIXED: Updated bots list

### 2.87.2
* NEW: Bump to 4.9
* FIXED: Notices in SCB Framework

### 2.87.1
* NEW: Bump to 4.7
* NEW: New useronline_custom_template filter

### 2.87
* NEW: Remove po/mo files from the plugin
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: Update SCB Framework
* FIXED: Incompatible scbAdminPage::validate()

### 2.86
* FIXED: Notices in Widget Constructor for WordPress 4.3

### 2.85
* NEW: Uses WordPress native uninstall.php

### 2.84
* NEW: Bump to 4.0

### 2.83
* CHANGED: Show user agent when hovering over IP, instead of address lookup
* CHANGED: Use local time for UserOnline Page
* FIXED: 'Strict Standards: Non-static method' warnings
* FIXED: Update scb Framework

### 2.82
* CHANGED: Show most recent visitors first
* FIXED: Duplicate entry errors
* FIXED: AJAX requests for SSL

### 2.81
* FIXED: Settings page
* FIXED: "Return to default" buttons
* CHANGED: Show user list in admin only to users with 'manage_options' capability
* NEW: Added 'useronline_bots' filter

### 2.80
* CHANGED: Do not show url and referral links for users in the admin area
* CHANGED: Smarter detection via AJAX requests
* FIXED: SQL errors

### 2.72
* FIXED: Fatal error on upgrade

### 2.71
* FIXED: %USERONLINE_COUNT% problem

### 2.70
* NEW: Added option to link user names to their author page
* NEW: Allow displaying online users from a different page than the current page
* NEW: Bundle language files
* NOTE: [more info](https://scribu.net/wordpress/wp-useronline/wu-2-70.html)

### 2.62 (2010-03-07)
* FIXED: Integration with WP-Stats
* FIXED: Error with get_admin_page_title()

## Upgrade Notice

### 4.0.0. This is a major release and it changes things you may have customised. Read this before updating from 3.0.0.

**Why 4.0.0 and not 3.0.1.** The 3.0.0 changelog told you, in as many words, that "all four filters are unchanged". That promise no longer holds: all four have been renamed, along with a fifth filter and one constant, and a version number ending in a bug-fix digit has no business carrying that. The line in the 3.0.0 changelog has been corrected to say so.

**Your server has to be new enough.** WP-UserOnline now needs WordPress 6.8 and PHP 8.2. If your site is older than that, WordPress will not offer you the update at all. Ask your host to move you to a current PHP before updating.

**Every filter has been renamed, and the old names are gone.** There are no deprecation shims: code hooking an old name simply stops running, silently. If you have any of these in a theme or a snippet plugin, rename them:

* `useronline_bots` is now `wp_useronline_bots`
* `useronline_buckets` is now `wp_useronline_buckets`
* `useronline_custom_template` is now `wp_useronline_custom_template`
* `useronline_page` is now `wp_useronline_page`
* `useronline_display_user` is now `wp_useronline_display_user`
* `useronline_trust_proxy` is now `wp_useronline_trust_proxy`

**And the proxy constant with them.** If `wp-config.php` defines `USERONLINE_TRUST_PROXY`, rename it to `WP_USERONLINE_TRUST_PROXY`. Left as it was, the plugin stops trusting your proxy and every visitor starts showing the same IP address. See the FAQ.

**The screens have moved into one menu.** Users Online was under Dashboard and the settings were under Settings; both are now under a single **WP-UserOnline** menu, with Users Online first and Settings last. Bookmarks to `index.php?page=useronline` become `admin.php?page=wp-useronline`, and `options-general.php?page=useronline-settings` becomes `admin.php?page=wp-useronline-settings`.

**Both screens now require `manage_options`.** The Users Online screen used to be open to anyone who could `list_users`, which included editors on many sites. If you want it back that way, filter it:

```php
add_filter( 'wp_useronline_capability', function ( $capability, $context ) {
	return 'useronline' === $context ? 'list_users' : $capability;
}, 10, 2 );
```

**Your settings move themselves.** The first page load after the update renames the `useronline` row to `wp_useronline_options` and the `useronline_most` row to `wp_useronline_most`, and puts the two upgrade markers in a row of their own, `wp_useronline_version`. You do not have to do anything, but code that reads those old rows directly will find them gone. `wp_useronline_most` is no longer autoloaded, which is a small saving on every page load.

**If you use WP-Stats, update all seven plugins together.** WP-Stats, WP-UserOnline, WP-Polls, WP-PostRatings, WP-EMail, WP-PostViews and WP-DownloadManager all used to share one unprefixed option row, `stats_display`. Each of them now keeps its own copy and deletes the shared row once it has read it, so whichever you update first takes it away from the rest. Every plugin treats a missing row as "show my block" rather than "hide it", so nothing disappears — but a block you had deliberately switched off may come back. Switch it off again under **WP-UserOnline -> Settings -> WP-Stats**, where "Show a users online section on the WP-Stats page" now lives.

**Classes were renamed too.** Everything is `WP_UserOnline_*` now: `UserOnline` is `WP_UserOnline`, `UserOnline_Template` is `WP_UserOnline_Template`, and so on. The template tags — `users_online()`, `get_users_online()`, `get_users_browsing_site()`, `get_most_users_online()` and the rest — and the `[page_useronline]` shortcode are unchanged, and so are the `useronline-count`, `useronline-browsing-site`, `useronline-browsing-page` and `useronline-details` element ids your theme puts on the page.

**Custom JavaScript.** The refresh script is at `js/wp-useronline.js` rather than `useronline.js`, its localised object is `wpUserOnlineL10n` rather than `useronlineL10n`, and the admin-ajax action it posts is `wp_useronline` rather than `useronline`.
