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
* One admin screen with three tabs — who is online now, the settings, the templates — plus a line in the Dashboard's At a Glance panel.
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

Everything the plugin has is at **WP-Admin -> WP-UserOnline**, on three tabs: **Users Online** for who is here right now, **Settings**, and **Templates** for the wording of everything the plugin prints.

## Frequently Asked Questions

### Creating A UserOnline Page
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you ARE using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `useronline` in the text field and click 'Save'.
1. Type `[page_useronline]` in the post's content area
1. Click 'Publish'

If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> WP-UserOnline -> Settings` (the Settings tab) and under 'UserOnline URL', you need to fill in the URL to the UserOnline Page you created above.

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
To put it back that way, filter the capability — the context tells the Users Online tab
from the other two, and each tab checks its own, so this does not open the settings at
the same time:

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
3. Admin - Settings Tab
4. Admin - Templates Tab

## Changelog
### 4.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: Every filter the plugin fires is renamed and the old names are dropped, with no deprecation shims: `useronline_bots`, `useronline_buckets`, `useronline_custom_template`, `useronline_page`, `useronline_display_user` and `useronline_trust_proxy` all become `wp_useronline_*`. This voids the promise made in the 3.0.0 changelog that the filters were unchanged, and is why this release is 4.0.0 rather than 3.0.1.
* BREAKING: `USERONLINE_TRUST_PROXY` is now `WP_USERONLINE_TRUST_PROXY`. A site still defining the old name silently stops trusting its proxy and starts recording the proxy's address for every visitor. See the FAQ.
* BREAKING: The plugin has one screen, at `admin.php?page=wp-useronline` under a top-level WP-UserOnline menu, with three tabs: Users Online, Settings and Templates. The report used to be under Dashboard and the settings under Settings.
* BREAKING: Every tab now requires `manage_options`. The users online listing previously needed only `list_users`. The new `wp_useronline_capability` filter puts it back, for that tab alone.
* BREAKING: The option rows are renamed. `useronline` becomes `wp_useronline_options` and `useronline_most` becomes `wp_useronline_most`, which is no longer autoloaded. Your settings are migrated automatically on the first load after the update.
* BREAKING: The shared, unprefixed `stats_display` option row is no longer read. WP-Stats integration is now a setting of this plugin's own, and WP-Stats asks each plugin for its section through the `wp_stats_sections` filter rather than reading anybody's options. Update all seven WP-Stats-aware plugins together; see the Upgrade Notice.
* BREAKING: Every class is renamed to `WP_UserOnline_*`. `UserOnline`, `UserOnline_Template`, `UserOnline_Options` and the rest no longer exist under those names.
* BREAKING: The refresh script is at `js/wp-useronline.js` rather than `useronline.js`, its localised object is `wpUserOnlineL10n` rather than `useronlineL10n`, and the admin-ajax action it posts is `wp_useronline` rather than `useronline`.
* NEW: The upgrade markers live in their own `wp_useronline_version` option row, holding exactly `plugin` and `db`, and are written together in one update at the end of the upgrade.
* NEW: WP-Stats integration is a setting of the plugin's own, on the Settings tab under WP-Stats, instead of a checkbox added to the WP-Stats options page.
* NEW: `wp_useronline_capability` filters the capability each tab requires, with a context saying which one is asking. The page itself is registered under the report's capability and every tab checks its own, so widening the listing cannot hand over the settings form.
* NEW: The templates are on a Templates tab of their own rather than below the settings. Both tabs post one setting into one option row, and the sanitiser merges what a tab submitted over what is stored, so saving one cannot blank the other.
* NEW: The Restore Defaults behaviour is a proper enqueued script, `js/wp-useronline-admin.js`, rather than markup printed inside the page.
* NEW: The JavaScript is covered by vitest and jsdom, and the PHP suite runs on the network as well as on a single site.
* CHANGED: `WP_UserOnline_Install` owns the table, the migration and the markers, so install and uninstall sit beside each other and neither can drift.
* FIXED: The upgrade markers no longer live inside the settings array. They were kept under a reserved `versions` key that the settings form never posted, so every save had to rescue them from the stored value by hand — which is the arrangement behind the 3.0.0 bug where the marker could not be saved at all once the settings screen had been loaded. They have a row of their own now, and the settings sanitiser has no business with them.
* FIXED: The settings screen no longer emits an inline `style` attribute or an inline `<script>` block.
* FIXED: The WP-Stats checkbox posts an explicit off. An unticked box posts nothing at all, and the sanitiser keeps whatever a tab did not submit, so without it the section could be switched on and never off again.
* NOTE: The template tags, the `[page_useronline]` shortcode and the `useronline-count`, `useronline-browsing-site`, `useronline-browsing-page` and `useronline-details` element ids are unchanged. Everything else that was public has moved; see the Upgrade Notice.

## Upgrade Notice

### 4.0.0

Requires WordPress 6.8 and PHP 8.2.

**Every filter is renamed, with no deprecation shims.** Code hooking an old name stops running, silently:

* `useronline_bots` is now `wp_useronline_bots`
* `useronline_buckets` is now `wp_useronline_buckets`
* `useronline_custom_template` is now `wp_useronline_custom_template`
* `useronline_page` is now `wp_useronline_page`
* `useronline_display_user` is now `wp_useronline_display_user`
* `useronline_trust_proxy` is now `wp_useronline_trust_proxy`

**`USERONLINE_TRUST_PROXY` is now `WP_USERONLINE_TRUST_PROXY`.** Left unrenamed in `wp-config.php`, the plugin stops trusting the proxy and every visitor reports the same IP address. See the FAQ.

**One screen, three tabs, under a WP-UserOnline menu of its own.** `index.php?page=useronline` and `options-general.php?page=useronline-settings` are both gone. Everything is at `admin.php?page=wp-useronline`, which opens on Users Online; the settings are at `&tab=settings` and the templates at `&tab=templates`. Bookmarks and any code linking to the old addresses need updating.

**Every tab now requires `manage_options`.** Users Online previously needed only `list_users`, which included editors on many sites. To restore that:

```php
add_filter( 'wp_useronline_capability', function ( $capability, $context ) {
	return 'useronline' === $context ? 'list_users' : $capability;
}, 10, 2 );
```

**Settings migrate on the first admin page load.** `useronline` becomes `wp_useronline_options`, `useronline_most` becomes `wp_useronline_most`, and the two upgrade markers move into `wp_useronline_version`. Code reading the old rows directly will find them gone. `wp_useronline_most` is no longer autoloaded.

**Update all seven WP-Stats plugins together.** WP-Stats, WP-UserOnline, WP-Polls, WP-PostRatings, WP-EMail, WP-PostViews and WP-DownloadManager shared one unprefixed `stats_display` row. Each now keeps its own copy and deletes the shared row once it has read it, so whichever you update first takes it from the rest. A missing row means "show", so a block you had switched off may reappear — switch it off again under **WP-UserOnline -> Settings -> WP-Stats**, on the Settings tab.

**Classes are renamed to `WP_UserOnline_*`**: `UserOnline` is `WP_UserOnline`, `UserOnline_Template` is `WP_UserOnline_Template`, and so on. The template tags — `users_online()`, `get_users_online()`, `get_users_browsing_site()`, `get_most_users_online()` and the rest — the `[page_useronline]` shortcode, and the `useronline-count`, `useronline-browsing-site`, `useronline-browsing-page` and `useronline-details` element ids are unchanged.

**Custom JavaScript.** `useronline.js` is now `js/wp-useronline.js`, its localised object is `wpUserOnlineL10n` rather than `useronlineL10n`, and the admin-ajax action is `wp_useronline` rather than `useronline`.
