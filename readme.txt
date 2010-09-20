=== WP-UserOnline ===
Contributors: GamerZ, scribu
Donate link: http://lesterchan.net/wordpress
Tags: useronline, usersonline, wp-useronline, online, users, user, ajax, widget
Requires at least: 3.0
Tested up to: 3.0
Stable tag: 2.80

Enable you to display how many users are online on your Wordpress blog with detailed statistics.

== Description ==

**PHP 5 is required since version 2.60.**

This plugin enables you to display how many users are online on your Wordpress site, with detailed statistics of where they are and who they are (Members/Guests/Search Bots).

Links: [Plugin News](http://scribu.net/wordpress/wp-useronline) | [Translating](http://scribu.net/wordpress/translating-plugins.html)

== Installation ==

You can either install it automatically from the WordPress admin, or do it manually:

1. Unzip the archive and put the `wp-useronline` folder into your plugins folder (/wp-content/plugins/).
1. Activate the plugin from the Plugins menu.

= Usage =

**General Usage (With Widget)**

1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is <strong>UserOnline</strong>.
1. Scroll down for instructions on how to create a *UserOnline Page*.


**General Usage (Without Widget)**

Open `wp-content/themes/<YOUR THEME NAME>/sidebar.php` and add Anywhere:

`
<?php if (function_exists('users_online')): ?>
	<p>Users online: <div id="useronline-count"><?php users_online(); ?></div></p>
<?php endif; ?>
`

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
<?php if (function_exists('get_most_users_online')): ?>
   <p>Most Users Ever Online Is <?php echo get_most_users_online(); ?> On <?php echo get_most_users_online_date(); ?></p>
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

1. Right Now text
2. Admin page
3. Settings page

== Frequently Asked Questions ==

= Error on activation: "Parse error: syntax error, unexpected..." =

Make sure your host is running PHP 5. The only foolproof way to do this is to add this line to wp-config.php (after the opening `<?php` tag):

`var_dump(PHP_VERSION);`
<br>

== Changelog ==

= 2.80 =
* don't show url and referral links for users in the admin area
* smarter detection via ajax requests
* fix SQL errors

= 2.72 =
* fix fatal error on upgrade

= 2.71 =
* fix %USERONLINE_COUNT% problem

= 2.70 =
* added option to link user names to their author page
* allow displaying online users from a different page than the current page
* bundle language files
* [more info](http://scribu.net/wordpress/wp-useronline/wu-2-70.html)

= 2.62 (2010-03-07) =
* fix integration with WP-Stats
* fix error with get_admin_page_title()

= 2.61 (2010-02-12) =
* fix fatal error with scbWidget

= 2.60 (2010-02-12) =
* display admin page titles
* use domaintools.com instead of arin.net
* removed ip2nation code and flag images
* simpler uninstallation procedure
* much cleaner code
* [more info](http://scribu.net/wordpress/wp-useronline/wu-2-60.html)

= 2.50 (2009-06-01) =
* new: Works For WordPress 2.8 Only
* new: Javascript Now Placed At The Footer
* new: Uses jQuery Instead Of tw-sack
* new: Minified Javascript Instead Of Packed Javascript
* new: Renamed useronline-js-packed.js To useronline-js.js
* new: Renamed useronline-js.js To useronline-js.dev.js
* new: Translate Javascript Variables Using wp_localize_script()
* new: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* new: Uses New Widget Class From WordPress
* new: Merge Widget Code To wp-useronline.php And Remove wp-useronline-widget.php
* fixed: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* fixed: Pages Without Name, Use Pages Use $_SERVER['REQUEST_URI'] Instead

= 2.40 (2008-12-12) =
* new: Works For WordPress 2.7 Only
* new: Uses plugins_url() And site_url()
* new: Cache IPs of ip2nation by Kambiz R. Khojasteh
* new: Country Flags Images Are Now Included As Part Of The Plugin
* new: Right To Left Language Support by Kambiz R. Khojasteh
* new: Better Translation Using __ngetext() by Anna Ozeritskaya
* new: Output Of useronline_page() Applied To "useronline_page" Filter by Kambiz R. Khojasteh
* new: Called useronline_textdomain() In create_useronline_table() by Kambiz R. Khojasteh
* fixed: SSL Support

= 2.31 (2008-07-16) =
* new: Works For WordPress 2.6
* fixed: MYSQL Charset Issue Should Be Solved
* fixed: Do Not Show WP-Stats Link If There Is No WP-Stats

= 2.30 (2008-06-01) =
* new: Works For WordPress 2.5 Only
* new: Uses Shortcode API
* new: Uses /wp-useronline/ Folder Instead Of /useronline/
* new: Uses wp-useronline.php Instead Of useronline.php
* new: Uses wp-useronline-widget.php Instead Of useronline-widget.php
* new: Renamed useronline-js.php To useronline-js.js and Move The Dynamic Javascript Variables To The PHP Pages
* new: Uses useronline-js-packed.js
* new: Added Users Online To Dashboard "Right Now"
* new: Use number_format_i18n() Instead
* fixed: Should Use display_name Instead Of user_name If WP-Stats Is Not Installed
* fixed: XSS Vulnerability

= 2.20 (2007-10-01) =
* new: Works For WordPress 2.3 Only
* new: Templates Options Added
* new: Ability To Uninstall WP-UserOnline
* new: Uses WP-Stats Filter To Add Stats Into WP-Stats Page

= 2.11 (2007-06-01) =
* new: Referral Link Is Now Shown On The UserOnline Page
* fixed: Uses WordPress's Default Date And Time Format
* fixed: Able To Leave Blank For 'UserOnline URL' Option To Disable Link To UserOnline Page

= 2.10 (2007-02-01) =
* new: Works For WordPress 2.1 Only
* new: Renamed useronline-js.js to useronline-js.php To Enable PHP Parsing

= 2.06 (2007-01-02) =
* new: useronline.php Now Handles The AJAX Processing Instead Of index.php
* new: Localize WP-UserOnline
* fixed: JavaScript onLoad Function Conflict By zeug
* fixed: AJAX Not Working On Servers Running On PHP CGI
* fixed: IP2Nation Will Now Work Whether Or Not WP-Stats Is Activated

= 2.05 (2006-10-01) =
* new: UserOnline Is Now Embedded Into A Page, And Hence No More Integrating Of UserOnline Page (Removed wp-useronline.php)
* new: Changed In WP-UserOnline Structure: Members Mean Registered Users and Guests Mean Comment Authors
* new: get_users_browsing_site(false) And get_users_browsing_page(false) Will Now Return An Array Containing Total Users, Total Members, Total Guests and Total Bots Online
* new: Added Widget Title Option To WP-UserOnline Widget
* fixed: Invalid IP Error
* fixed: If Site URL Doesn't Match WP Option's Site URL, WP-UserOnline Will Not Work

= 2.04 (2006-07-01) =
* new: AJAX Is Now Used For Updating Users Online Every 1 Minute Without Refreshing The Page
* new: You Can Now Place Users Online Count And Users Browsing Site Data On The Sidebar As A Widget
* new: UserOnline Options Panel And The Code That WP-UserOnline Generated Is XHTML 1.0 Transitional
* new: Added Useronline Options In WP Administration Panel Under 'Options -> Useronline'
* new: If You Have ip2nation Plugin Installed, The User's/Guest's Country Flag Will Be Displayed

= 2.03 (2006-04-01) =
* new: Added get_users_browsing_site(); To Display Users Browsing The Site
* fixed: wp-stats.php Link
* fixed: Some Grammer Errors, Credit To xclouds (http://www.xclouds.com/)

= 2.02 (2006-03-01) =
* new: No More Install/Upgrade File, It Will Install/Upgrade When You Activate The Plugin.
* fixed: IP 'Unknown' Error, Credit To Brian Layman (http://www.knitchat.com/)
* fixed: ON DUPLICATE KEY Error, Credit To Brian Layman (http://www.knitchat.com/)
* fixed: DUPLICATE KEY Error By Using DELETE First, Credit To Jody Cairns (http://steelwhitetable.org/blog/)

= 2.01 (2006-02-01) =
* new: Added Users Browsing Page
* new: Added Most Users Ever Online
* fixed: Added UNIQUE Key Instead Of Primary Key To Test Whether It Will Solve Duplicate Entry Errors
* fixed: Quotes Issue In Page Title

= 2.00 (2006-01-01) =
* new: Compatible With Only WordPress 2.0
* new: Better Installer
* new: GPL License Added
* new: Page Title Added To wp-useronline.php
* new: Added Extra Bots, Credit To Greg Perry (http://www.gregrperry.com/)
* fixed: Cleaner Codes

