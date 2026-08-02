# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-UserOnline follows `_standards/STANDARDS.md` in the parent folder, which is
the contract for all nineteen plugins in the collection. Where this file and
that one disagree, that one wins.

## What it is

Records who is on the site right now — logged-in users, guests and bots, with
the page each is viewing — and renders that as template tags, a
`[page_useronline]` shortcode, a widget and an admin screen. Keeps a
"most ever online" high-water mark. One menu, three flat tabs: **Users Online**,
**Settings**, **Templates**.

## Data

* **A custom table**, `$wpdb->useronline`, registered on the `$wpdb` object.
  Every visitor's row is deleted and rewritten on each request;
  `WP_UserOnline_Recorder::record()` does the delete-then-insert in one
  statement that also purges timed-out rows.
* `wp_useronline_options` (from `useronline`) and `wp_useronline_version`.
* `wp_useronline_most` (from `useronline_most`) — the highest ever count.
  **Migrated by rename, never rebuilt**: there is nowhere else to recover it
  from. No longer autoloaded.
* One of the seven WP-Stats plugins (§13).

**This plugin is the reason §2.1 exists.** Its 3.0.0 kept the upgrade markers
*inside* the settings array, so every save had to manually rescue them from the
stored value — fourteen lines of plumbing — and the marker could not be saved
once the settings screen had been loaded, which made the sanitise step and the
table check re-run on every request. Read §2.1 before proposing anything that
puts a marker back in the settings row.

## Version

**4.0.0, not 3.0.1** (§14). The released 3.0.0 shipped a changelog entry
promising "Template tags, the `[page_useronline]` shortcode and all four filters
are unchanged." This release renames all four filters plus
`USERONLINE_TRUST_PROXY`, which a patch number cannot carry.

## Traps

* **A timeout of zero would purge every row on the next request**, so
  `sanitize()` replaces it with the default rather than storing it.
* **Capabilities are filtered per context, and one of them was a real
  multisite bug.** `wp_useronline_capability` takes a context —
  `useronline`, `settings`, `templates`, `details`. The visitor-detail gate was a
  hardcoded `edit_users` outside the filter; under multisite core's
  `map_meta_cap()` adds `manage_network_users` to that, so a site administrator
  could not see the visitors to their own site and no site could correct it. Now
  `capability( 'details' )` (commit `23bcac1`).
* **Three flat tabs, never nested** (§4.2.1). A Settings tab containing its own
  Settings/Templates strip is worse than the sprawl either was meant to fix. And
  because the tabs are gated differently, the *page* takes the lower capability
  and **each tab checks its own** — skip the second half and filtering the report
  down to `list_users` silently opens the settings form to that role.
* **`[page_useronline]` escapes at the source.** The admin screen, the AJAX
  endpoint and the shortcode each used to decide escaping separately, and the
  shortcode registration passed `users_online_page` directly and got the markup
  raw — a stored XSS the e2e sweep found. Three call sites each deciding is the
  defect; a fourth wrapper would be a fourth place to forget (commit `e49b290`).
* **Hooks are registered unconditionally and the setting is read inside the
  callback.** `add_hooks()` runs on `plugins_loaded`, before `after_setup_theme`;
  reading a setting there merges the defaults, which carry `__()` strings, and
  core's `_load_textdomain_just_in_time()` complains about a translation
  requested that early.
* **`USERONLINE_TRUST_PROXY` became `WP_USERONLINE_TRUST_PROXY`.** Left unrenamed
  in `wp-config.php` the plugin stops trusting the proxy and every visitor
  reports the same IP.
* All four public filters were renamed with **no shims**, per the collection's
  decision. They fail silently.
* **Three of the four refresh modes answer with content; `details` answers with
  its own container.** `users_online_page()` bakes `#useronline-details` into
  what it returns and the `wp_useronline_page` filter is applied to the whole
  element, because `[page_useronline]` has no theme markup to sit inside. So the
  script replaces that element rather than filling it (`WRAPPED_MODES` in
  `js/wp-useronline.js`), and it re-reads the element on every poll, since the
  one it replaced is no longer in the page. Filling it is what nested a second
  `#useronline-details` inside the first on every timeout.
* `useronline.js` → `js/wp-useronline.js`, `useronlineL10n` → `wpUserOnlineL10n`,
  AJAX action `useronline` → `wp_useronline`. The element ids
  (`useronline-count`, `useronline-browsing-site`, `useronline-browsing-page`,
  `useronline-details`) are **unchanged** — themes style them.
* `WP_UserOnline_Install` holds both install and uninstall side by side so the
  two cannot drift, and so the suite can drive either directly.
  `uninstall.php` is four lines calling into it.

## WP-Stats coupling

`migrate_stats_display()` reads the shared `stats_display` row honouring both
in-the-wild array shapes, defaults to **on** when the row is absent (a sibling
already migrated), and the migration then deletes the shared row — which is what
§13.2 requires.

`_standards/RESUME.md` flags `class-wp-useronline-options.php:381` as a hazard
because no test covers it (the family test checks uninstall only). The
uninstall list is clean, which is the half that matters. If you touch this,
check it against §13.2 rather than against that note.

## Tests

`test-recorder.php` covers the delete-then-insert and the bot identification;
`test-install.php` the table creation and the marker writes; `test-wpstats.php`
the §13 contract. `tests/e2e/` (5 specs, 73 tests) reached 68/75 in the third
sweep — two real bugs and five test-side fixes that were **never re-run**
(`_standards/RESUME.md`). Verify before trusting.

## Pending, not started

`_standards/RESUME.md` task #17 renames the settings screen heading from
"Options" to "<Name> Settings"; task #20 brings the proxy-header field to the
canonical label and description used by wp-polls and wp-postratings.
