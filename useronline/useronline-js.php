<?php
/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.3 Plugin: WP-UserOnline 2.21								|
|	Copyright (c) 2007 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Useronline Javascript File														|
|	- wp-content/plugins/useronline/useronline-js.php 						|
|																							|
+----------------------------------------------------------------+
*/


### Include wp-config.php
@require('../../../wp-config.php');
cache_javascript_headers();

### Determine useronline.php Path
$useronline_ajax_url = dirname($_SERVER['PHP_SELF']);
if(substr($useronline_ajax_url, -1) == '/') {
$useronline_ajax_url  = substr($useronline_ajax_url, 0, -1);
}
?>
// Variables
var useronline_ajax_url = "<?php echo $useronline_ajax_url; ?>/useronline.php";
var useronline_timeout = <?php echo (get_option('useronline_timeout')*1000); ?>;

// UserOnline JavaScript Init
function useronline_init() {
	// Check Users Count
	if(document.getElementById('useronline-count') != null ) {
		setInterval("get_useronline_count()", useronline_timeout);
	}
	// Check Users Browsing Site
	if(document.getElementById('useronline-browsing-site') != null ) {
		setInterval("get_useronline_browsingsite()", useronline_timeout);
	}
	// Check Users Browsing Page
	if(document.getElementById('useronline-browsing-page') != null) {
		setInterval("get_useronline_browsingpage()", useronline_timeout);
	}
}


// Get UserOnline Count
function get_useronline_count() {
	useronline_count = new sack(useronline_ajax_url);
	useronline_count.setVar("useronline_mode", 'useronline_count');
	useronline_count.method = 'GET';
	useronline_count.element = 'useronline-count';
	useronline_count.runAJAX();
	useronline_count = null;
}


// Get Users Browsing Site
function get_useronline_browsingsite() {
	useronline_browsingsite = new sack(useronline_ajax_url);
	useronline_browsingsite.setVar("useronline_mode", 'useronline_browsingsite');
	useronline_browsingsite.method = 'GET';
	useronline_browsingsite.element = 'useronline-browsing-site';
	useronline_browsingsite.runAJAX();
	useronline_browsingsite = null;
}


// Get Users Browsing Page
function get_useronline_browsingpage() {
	useronline_browsingpage = new sack(useronline_ajax_url);
	useronline_browsingpage.setVar("useronline_mode", 'useronline_browsingpage');
	useronline_browsingpage.method = 'GET';
	useronline_browsingpage.element = 'useronline-browsing-page';
	useronline_browsingpage.runAJAX();
	useronline_browsingpage = null;
}


// Init UserOnline
addLoadEvent = function(f) { var old = window.onload
if (typeof old != 'function') window.onload = f
else { window.onload = function() { old(); f() }}
}
addLoadEvent(useronline_init);