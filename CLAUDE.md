# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

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
* `wp_useronline_options` (from `useronline`) and `wp_useronline_version`, which
  holds the `plugin` and `db` upgrade markers and nothing else.
* `wp_useronline_most` (from `useronline_most`) — the highest ever count.
  **Migrated by rename, never rebuilt**: there is nowhere else to recover it
  from. No longer autoloaded.
* It contributes a section to **WP-Stats**, a separate plugin, by answering the
  `wp_stats_sections` filter.

**Never put an upgrade marker back inside the settings array.** 3.0.0 did, and
every save had to manually rescue it from the stored value — fourteen lines of
plumbing — because a sanitize callback is a function from what the form posted
to what gets stored, and the settings form never posts a marker. Worse, the
marker could not be saved once the settings screen had been loaded, so the
sanitise step and the table check re-ran on every request.

## Version

**4.0.0 — the major skipped a number.** The earlier 3.0.0 shipped a changelog
entry promising "Template tags, the `[page_useronline]` shortcode and all four
filters are unchanged", and 4.0.0 renames all four filters plus
`USERONLINE_TRUST_PROXY` — a promise a patch number cannot break.

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
* **Three flat tabs, never nested.** A Settings tab containing its own
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
* All four public filters were renamed with **no shims**. They fail silently:
  nothing errors, the plugin just stops asking the site's code what it thinks.
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

`stats_display` was an unprefixed row that WP-Stats and several companion
plugins all wrote into; none of them owned it. `migrate_stats_display()` reads
it honouring both in-the-wild array shapes and defaults to **on** when the row
is absent — absent means "a sibling migrated it away first", never "the site
opted out". Reading absence as an opt-out would make this plugin's block vanish
from the stats page of any site that updated a sibling first, silently.

The migration deletes the shared row once it has folded it in. **Uninstall must
not**, because a sibling that has not upgraded is still reading it — which is
why the row is deliberately absent from `all_option_names()`.

## WP-CLI and REST

`wp useronline list|count`, and `useronline/v1` with two routes: a read that
records nobody, and a heartbeat that records the caller.

**Neither route takes a nonce, and that is deliberate.** A nonce cannot
authenticate a logged-out visitor -- anonymous nonces come from one session every
such caller shares, so verifying one proves only that the caller can compute a
value everybody already has. Two tests pin the *absence*, because the obvious
tidy-up is to add the check and it would break every visitor who is not signed
in while protecting nobody. What actually holds is narrower: every field is
validated, a `page_url` that does not resolve to this site is ignored, and the
write touches nothing but the caller's own row.

**The command reads and never writes.** No screen in this plugin offers a
destructive action and the table maintains itself, since recording a visit also
purges rows that have timed out. A `purge` subcommand would be a power the
browser has never given anybody; a test asserts none exists.

**Reading the count records nobody**, so a monitoring script polling it does not
appear in the figure it is reading. That is why the read and the heartbeat are
two routes rather than one.

## The block

One block, `wp-useronline/page-useronline`, wrapping `[page_useronline]`. The
shortcode stays registered, documented and undeprecated: it sits in published
pages nobody here can survey, so the block is an addition beside it and never a
replacement. Neither calls the other — both end at `users_online_page()`, which
the AJAX endpoint and the REST heartbeat already share. The name is hyphenated
where the shortcode is underscored only because a block name cannot hold an
underscore, and it keeps the `wp-` prefix that the command and the namespace
drop, because a block name is written into `post_content` and outlives the post.

**Rendering records nobody, and that is the thing to not break.** Recording is
hooked to `wp_head` and `admin_head`; the editor's preview arrives on
`/wp/v2/block-renderer/`, which fires neither, so previewing the listing does
not put the previewer into it and a preview refreshed ten times writes nothing
ten times. The tempting fix for a preview reading "No one is online now." is to
record first so there is somebody to list — that is the same line the read
route already refuses to cross, and two tests pin it, including one that the
most-ever-online figure is the recorder's to move and never the renderer's.

`multiple` is false in `block.json`: the render carries its own
`#useronline-details` element, and the refresh script finds that element by id
and replaces it, so two in one page is a duplicate id and a script updating
whichever it reached first.

`build/` is generated by `bin/build` from `src/`, is gitignored, and ships;
`src/` is committed and does not. Both directories need a silence-is-golden
`index.php`, and the ones under `build/` are written by `bin/build` after the
compile rather than committed, since a clean build would delete them.

## Migrations, and why they are tested through a browser

`maybe_upgrade()` runs from `add_hooks()` on `plugins_loaded`, so every request
reaches it — activation hooks do not fire on a plugin update, which is the usual
reason a migration never runs at all. Three rows move and each fails
differently, which is what `tests/e2e/upgrade.spec.js` is organised around:

* `useronline` becomes `wp_useronline_options`, re-sanitised on the way, with
  the `versions` key 3.0.0 kept inside the settings dropped;
* `useronline_most` becomes `wp_useronline_most` **by rename and never by
  rebuild** — it is the site's highest ever count and there is nowhere else to
  recover it from, so losing it is silent and permanent. The test reads the
  renamed row raw *and* checks the figure reaches the Users Online screen;
* `stats_display` folds in as above, in both directions.

Two things its fixtures rely on: **a `wp eval` call is itself an upgrade
request**, because WP-CLI reaches `plugins_loaded` like any other — so seed the
fixture and read it back inside one call — and **read rows raw**, because
`WP_UserOnline_Options::get()` merges over the defaults and cannot tell a
written row from an absent one.

## Tests

`bin/test.sh` runs PHPUnit, `bin/test-multisite.sh` the network pass, and
`bin/test-e2e.sh` the Playwright suite. **Run them rather than trusting a note
about their last result** — CI is the authority, and this file cannot be.

`test-recorder.php` covers the delete-then-insert and the bot identification;
`test-install.php` the table creation and the marker writes; `test-wpstats.php`
the sections contract.

## Pending

Nothing outstanding. The settings heading reads "UserOnline Settings" and the
proxy-header field carries the canonical "Header That Contains The IP" label —
check `class-wp-useronline-settings.php` before renaming either.
