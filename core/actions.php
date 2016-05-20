<?php

namespace Kirby\Plugin\StaticBuilder;

use R;
use Response;


/**
 * Kirby router action that lists all pages to build and files to copy,
 * and performs the actual build on user confirmation.
 * @return bool
 */
function siteAction() {
	$write = r::is('POST') and r::get('confirm');
	$site = site();
	$builder = new Builder();

	$builder->run($site, $write);

	$data = [
		'mode'    => 'site',
		'error'   => false,
		'confirm' => $write,
		'summary' => $builder->summary
	];
	return $builder->htmlReport($data);
}

/**
 * Similar to siteAction but for a single page.
 * @param $uri
 * @return bool
 */
function pageAction($uri) {
	$write = r::is('POST') and r::get('confirm');
	$page = page($uri);
	$builder = new Builder();
	$data = [
		'mode'    => 'page',
		'error'   => false,
		'confirm' => $write,
		'summary' => []
	];
	if (!$page) {
		$data['error'] = "Error: Cannot find page for \"$uri\"";
	}
	else {
		$builder->run($page, $write);
		$data['summary'] = $builder->summary;
	}
	return $builder->htmlReport($data);
}
