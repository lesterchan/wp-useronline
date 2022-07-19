# WP-UserOnline
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: useronline, usersonline, wp-useronline, online, users, user, ajax, widget  
Requires at least: 4.0  
Tested up to: 6.0  
Stable tag: 2.88.0  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

Enable you to display how many users are online on your Wordpress blog with detailed statistics.

## Description
This plugin enables you to display how many users are online on your Wordpress site, with detailed statistics of where they are and who they are (Members/Guests/Search Bots).

### Usage (With Widget)
1. Go to `WP-Admin -> Appearance -> Widgets`
1. The widget name is <strong>UserOnline</strong>.
1. Scroll down for instructions on how to create a *UserOnline Page*.

### Usage (Without Widget)
* Open `wp-content/themes/<YOUR THEME NAME>/sidebar.php` and add Anywhere:

```php
<?php if (function_exists('users_online')): ?>
	<p>Users online: <div id="useronline-count"><?php users_online(); ?></div></p>
<?php endif; ?>
```

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-useronline.svg?branch=master)](https://travis-ci.org/lesterchan/wp-useronline)

### Development
[https://github.com/lesterchan/wp-useronline](https://github.com/lesterchan/wp-useronline "https://github.com/lesterchan/wp-useronline")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Screenshots

1. Admin - Dashboard's Right Now
2. UserOnline Page
3. Admin - Settings Page

## Frequently Asked Questions

### Creating A UserOnline Page
1. Go to `WP-Admin -> Pages -> Add New`
1. Type any title you like in the post's title area
1. If you ARE using nice permalinks, after typing the title, WordPress will generate the permalink to the page. You will see an 'Edit' link just beside the permalink.
1. Click 'Edit' and type in `useronline` in the text field and click 'Save'.
1. Type `[page_useronline]` in the post's content area
1. Click 'Publish'

If you ARE NOT using nice permalinks, you need to go to `WP-Admin -> Settings -> UserOnline` and under 'UserOnline URL', you need to fill in the URL to the UserOnline Page you created above.

### To Display Most Number Of Users Online
* Use:
```php
<?php if (function_exists('get_most_users_online')): ?>
   <p>Most Users Ever Online Is <?php echo get_most_users_online(); ?> On <?php echo get_most_users_online_date(); ?></p>
<?php endif; ?>
```

### To Display Users Browsing Site
* Use:
```php
<?php if (function_exists('get_users_browsing_site')): ?>
   <div id="useronline-browsing-site"><?php echo get_users_browsing_site(); ?></div>
<?php endif; ?>
```

### To Display Users Browsing A Page
* Use:
```php
<?php if (function_exists('get_users_browsing_page')): ?>
   <div id="useronline-browsing-page"><?php echo get_users_browsing_page(); ?></div>
<?php endif; ?>
```

### Error on activation: "Parse error: syntax error, unexpected..."

Make sure your host is running PHP 5. The only foolproof way to do this is to add this line to wp-config.php (after the opening `<?php` tag):

`var_dump(PHP_VERSION);`

## Changelog
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
* Show user agent when hovering over IP, instead of address lookup
* Use local time for UserOnline Page
* Fixed 'Strict Standards: Non-static method' warnings
* Update scb Framework

### 2.82
* show most recent visitors first
* fix duplicate entry errors
* fix ajax requests for SSL

### 2.81
* fixed settings page
* fixed "Return to default" buttons
* show user list in admin only to users with 'manage_options' capability
* added 'useronline_bots' filter

### 2.80
* don't show url and referral links for users in the admin area
* smarter detection via ajax requests
* fix SQL errors

### 2.72
* fix fatal error on upgrade

### 2.71
* fix %USERONLINE_COUNT% problem

### 2.70
* added option to link user names to their author page
* allow displaying online users from a different page than the current page
* bundle language files
* [more info](http://scribu.net/wordpress/wp-useronline/wu-2-70.html)

### 2.62 (2010-03-07)
* fix integration with WP-Stats
* fix error with get_admin_page_title()
