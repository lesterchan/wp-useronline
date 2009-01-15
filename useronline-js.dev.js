/*
+----------------------------------------------------------------+
|																							|
|	WordPress 2.8 Plugin: WP-UserOnline 2.50								|
|	Copyright (c) 2008 Lester "GaMerZ" Chan									|
|																							|
|	File Written By:																	|
|	- Lester "GaMerZ" Chan															|
|	- http://lesterchan.net															|
|																							|
|	File Information:																	|
|	- Useronline Javascript File														|
|	- wp-content/plugins/wp-useronline/useronline-js.js					|
|																							|
+----------------------------------------------------------------+
*/


// Variables
useronlineL10n.timeout = parseInt(useronlineL10n.timeout);

// UserOnline JavaScript Init
function useronline_init() {
	if(jQuery('#useronline-count').length) {
		setInterval("get_useronline_count()", useronlineL10n.timeout);
	}
	if(jQuery('#useronline-browsing-site').length) {
		setInterval("get_useronline_browsingsite()", useronlineL10n.timeout);
	}
	if(jQuery('#useronline-browsing-page').length) {
		setInterval("get_useronline_browsingpage()", useronlineL10n.timeout);
	}
}

// Get UserOnline Count
function get_useronline_count() {
	jQuery.ajax({type: 'GET', url: useronlineL10n.ajax_url, data: 'useronline_mode=useronline_count', cache: false, success: function (data) { jQuery('#useronline-count').html(data);}});
}

// Get Users Browsing Site
function get_useronline_browsingsite() {
	jQuery.ajax({type: 'GET', url: useronlineL10n.ajax_url, data: 'useronline_mode=useronline_browsingsite', cache: false, success: function (data) { jQuery('#useronline-browsing-site').html(data);}});
}

// Get Users Browsing Page
function get_useronline_browsingpage() {
	jQuery.ajax({type: 'GET', url: useronlineL10n.ajax_url, data: 'useronline_mode=useronline_browsingpage', cache: false, success: function (data) { jQuery('#useronline-browsing-page').html(data);}});
}

// Init UserOnline
addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
addLoadEvent(useronline_init);