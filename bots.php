<?php

function useronline_get_bots() {
	$bots = array(
		'Google Bot' => 'google',
		'MSN' => 'msnbot',
		'BingBot' => 'bingbot',
		'Alex' => 'ia_archiver',
		'Lycos' => 'lycos',
		'Ask Jeeves' => 'jeeves',
		'Altavista' => 'scooter',
		'AllTheWeb' => 'fast-webcrawler',
		'Inktomi' => 'slurp@inktomi',
		'Turnitin.com' => 'turnitinbot',
		'Technorati' => 'technorati',
		'Yahoo' => 'yahoo',
		'Findexa' => 'findexa',
		'NextLinks' => 'findlinks',
		'Gais' => 'gaisbo',
		'WiseNut' => 'zyborg',
		'WhoisSource' => 'surveybot',
		'Bloglines' => 'bloglines',
		'BlogSearch' => 'blogsearch',
		'PubSub' => 'pubsub',
		'Syndic8' => 'syndic8',
		'RadioUserland' => 'userland',
		'Gigabot' => 'gigabot',
		'Become.com' => 'become.com',
		'Baidu' => 'baidu',
		'Yandex' => 'yandex',
		'Amazon' => 'amazonaws.com',
		'Ahrefs' => 'AhrefsBot'
	);

	return apply_filters( 'useronline_bots', $bots );
}

