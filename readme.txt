=== WP-UserOnline ===
Contributors: GamerZ, scribu
Donate link: http://lesterchan.net/wordpress
Tags: useronline, usersonline, wp-useronline, online, users, user, ajax, widget
Requires at least: 2.8
Stable tag: 2.50

Enable you to display how many users are online on your Wordpress blog with detailed statistics.

== Description ==

This plugin enables you to display how many users are online on your Wordpress blog with detailed statistics of where they are and who they are(Members/Guests/Search Bots).

[Demo](http://lesterchan.net/wordpress/useronline/) | [Translations](http://dev.wp-plugins.org/browser/wp-useronline/i18n/) | [Support Forums](http://forums.lesterchan.net/index.php?board=21.0)

== Installation ==

You can either install it automatically from the WordPress admin, or do it manually:

1. Unzip the archive and put the `wp-useronline` folder into your plugins folder (/wp-content/plugins/).
1. Activate the plugin from the Plugins menu.

= Usage =

**General Usage (Without Widget)**

Open `wp-content/themes/<YOUR THEME NAME>/sidebar.php` and add Anywhere:

`
<?php if (function_exists('useronline')): ?>
   <li>
      <h2>UserOnline</h2>
      <ul>
         <li><div id="useronline-count"><?php get_useronline(); ?></div></li>
      </ul>
   </li>
<?php endif; ?>
`

**General Usage (With Widget)**
1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is <strong>UserOnline</strong>.
1. Scroll down for instructions on how to create a *UserOnline Page*.

**UserOnline Page**
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you **ARE** using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `useronline` in the text field and click 'Save'.
1. Type `[page_useronline]` in the post's content area
1. Click 'Publish'

If you **ARE NOT** using nice permalinks, you need to go to `WP-Admin -> Settings -> UserOnline` and under 'UserOnline URL', you need to fill in the URL to the UserOnline Page you created above.

**UserOnline Stats (Outside WP Loop)**

To Display *Most Number Of Users Online* use:

`
<?php if (function_exists('get_most_useronline')): ?>
   <p>Most Users Ever Online Is <?php echo get_most_useronline(); ?> On <?php echo get_most_useronline_date(); ?></p>
<?php endif; ?>
`

To Display *Users Browsing Site* use:

`
<?php if (function_exists('get_users_browsing_site')): ?>
   <div id="useronline-browsing-site"><?php echo get_users_browsing_site(); ?></div>
<?php endif; ?>
`

To Display *Users Browsing A Page* use:

`
<?php if (function_exists('get_users_browsing_page')): ?>
   <div id="useronline-browsing-page"><?php echo get_users_browsing_page(); ?></div>
<?php endif; ?>
`

== Screenshots ==

[WP-UserOnline Screenshots](http://lesterchan.net/wordpress/screenshots/browse/wp-useronline/ "WP-UserOnline Screenshots")

== Frequently Asked Questions ==

[WP-UserOnline Support Forums](http://forums.lesterchan.net/index.php?board=21.0 "WP-UserOnline Support Forums")

== Changelog ==

= 2.60 (2010-02-X) =
* use domaintools.com instead of arin.net
* removed ip2nation code and flag images
* easier uninstallation procedure
* cleaner code

= 2.50 (2009-06-01) =
* NEW: Works For WordPress 2.8 Only
* NEW: Javascript Now Placed At The Footer
* NEW: Uses jQuery Instead Of tw-sack
* NEW: Minified Javascript Instead Of Packed Javascript
* NEW: Renamed useronline-js-packed.js To useronline-js.js
* NEW: Renamed useronline-js.js To useronline-js.dev.js
* NEW: Translate Javascript Variables Using wp_localize_script()
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-useronline.php And Remove wp-useronline-widget.php
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Pages Without Name, Use Pages Use $_SERVER['REQUEST_URI'] Instead

= 2.40 (2008-12-12) =
* NEW: Works For WordPress 2.7 Only
* NEW: Uses plugins_url() And site_url()
* NEW: Cache IPs of ip2nation by Kambiz R. Khojasteh
* NEW: Country Flags Images Are Now Included As Part Of The Plugin
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* NEW: Output Of useronline_page() Applied To "useronline_page" Filter by Kambiz R. Khojasteh
* NEW: Called useronline_textdomain() In create_useronline_table() by Kambiz R. Khojasteh
* FIXED: SSL Support

= 2.31 (2008-07-16) =
* NEW: Works For WordPress 2.6
* FIXED: MYSQL Charset Issue Should Be Solved
* FIXED: Do Not Show WP-Stats Link If There Is No WP-Stats

= 2.30 (2008-06-01) =
* NEW: Works For WordPress 2.5 Only
* NEW: Uses Shortcode API
* NEW: Uses /wp-useronline/ Folder Instead Of /useronline/
* NEW: Uses wp-useronline.php Instead Of useronline.php
* NEW: Uses wp-useronline-widget.php Instead Of useronline-widget.php
* NEW: Renamed useronline-js.php To useronline-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* NEW: Uses useronline-js-packed.js
* NEW: Added Users Online To Dashboard "Right Now"
* NEW: Use number_format_i18n() Instead
* FIXED: Should Use display_name Instead Of user_name If WP-Stats Is Not Installed
* FIXED: XSS Vulnerability

= 2.20 (2007-10-01) =
* NEW: Works For WordPress 2.3 Only
NEW:Templates Options Added
* NEW: Ability To Uninstall WP-UserOnline
* NEW: Uses WP-Stats Filter To Add Stats Into WP-Stats Page

= 2.11 (2007-06-01) =
* NEW: Referral Link Is Now Shown On The UserOnline Page
* FIXED: Uses WordPress's Default Date And Time Format
* FIXED: Able To Leave Blank For 'UserOnline URL' Option To Disable Link To UserOnline Page

= 2.10 (2007-02-01) =
* NEW: Works For WordPress 2.1 Only
* NEW: Renamed useronline-js.js to useronline-js.php To Enable PHP Parsing

= 2.06 (2007-01-02) =
* NEW: useronline.php Now Handles The AJAX Processing Instead Of index.php
* NEW: Localize WP-UserOnline
* FIXED: JavaScript onLoad Function Conflict By zeug
* FIXED: AJAX Not Working On Servers Running On PHP CGI
* FIXED: IP2Nation Will Now Work Whether Or Not WP-Stats Is Activated

= 2.05 (2006-10-01) =
* NEW: UserOnline Is Now Embedded Into A Page, And Hence No More Integrating Of UserOnline Page (Removed wp-useronline.php)
* NEW: Changed In WP-UserOnline Structure: Members Mean Registered Users and Guests Mean Comment Authors
* NEW: get_users_browsing_site(false) And get_users_browsing_page(false) Will Now Return An Array Containing Total Users, Total Members, Total Guests and Total Bots Online
* NEW: Added Widget Title Option To WP-UserOnline Widget
* FIXED: Invalid IP Error
* FIXED: If Site URL Doesn't Match WP Option's Site URL, WP-UserOnline Will Not Work

= 2.04 (2006-07-01) =
* NEW: AJAX Is Now Used For Updating Users Online Every 1 Minute Without Refreshing The Page
* NEW: You Can Now Place Users Online Count And Users Browsing Site Data On The Sidebar As A Widget
* NEW: UserOnline Options Panel And The Code That WP-UserOnline Generated Is XHTML 1.0 Transitional
* NEW: Added Useronline Options In WP Administration Panel Under 'Options -> Useronline'
* NEW: If You Have ip2nation Plugin Installed, The User's/Guest's Country Flag Will Be Displayed

= 2.03 (2006-04-01) =
* NEW: Added get_users_browsing_site(); To Display Users Browsing The Site
* FIXED: wp-stats.php Link
* FIXED: Some Grammer Errors, Credit To xclouds (http://www.xclouds.com/)

= 2.02 (2006-03-01) =
* NEW: No More Install/Upgrade File, It Will Install/Upgrade When You Activate The Plugin.
* FIXED: IP 'Unknown' Error, Credit To Brian Layman (http://www.knitchat.com/)
* FIXED: ON DUPLICATE KEY Error, Credit To Brian Layman (http://www.knitchat.com/)
* FIXED: DUPLICATE KEY Error By Using DELETE First, Credit To Jody Cairns (http://steelwhitetable.org/blog/)

= 2.01 (2006-02-01) =
* NEW: Added Users Browsing Page
* NEW: Added Most Users Ever Online
* FIXED: Added UNIQUE Key Instead Of Primary Key To Test Whether It Will Solve Duplicate Entry Errors
* FIXED: Quotes Issue In Page Title

= 2.00 (2006-01-01) =
* NEW: Compatible With Only WordPress 2.0
* NEW: Better Installer
* NEW: GPL License Added
* NEW: Page Title Added To wp-useronline.php
* NEW: Added Extra Bots, Credit To Greg Perry (http://www.gregrperry.com/)
* FIXED: Cleaner Codes

