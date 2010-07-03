jQuery(document).ready(function($) {
	var timeout = parseInt(useronlineL10n.timeout);

	var get_data = function(mode) {
		var data = {
			'action': 'useronline',
			'mode': mode,
			'page_url': location.protocol + '//' + location.host + location.pathname + location.search,
			'page_title': $('title').text()
		};

		$.post(useronlineL10n.ajax_url, data, function(response) {
			$('#useronline-' + mode).html(response);
		});
	}

	$.each(['count', 'browsing-site', 'browsing-page', 'details'], function(i, mode) {
		if ( $('#useronline-' + mode).length )
			setInterval(function() { get_data(mode); }, timeout);
	});
});
