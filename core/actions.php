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
	$site = site();
	$builder = new Builder();
	$confirm = r::is('POST') and r::get('confirm');

	if ($confirm) $builder->write($site);
	else $builder->dryrun($site);

	$data = [
		'mode'    => 'site',
		'error'   => false,
		'confirm' => $confirm,
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
	$page = page($uri);
	$builder = new Builder();
	$confirm = r::is('POST') and r::get('confirm');
	$data = [
		'mode'    => 'page',
		'error'   => false,
		'confirm' => $confirm,
		'folder'  => null,
		'summary' => []
	];
	if (!$page) {
		$data['error'] = "Error: Cannot find page for \"$uri\"";
	}
	else {
		if ($confirm) $builder->write($page);
		else $builder->dryrun($page);
		$data['summary'] = $builder->summary;
	}
	return $builder->htmlReport($data);
}

/**
 * Serve CSS file for report page
 */
function cssAction() {
	$css = file_get_contents(__DIR__ . DS . '..' . DS . 'assets' . DS . 'report.css');
	return new Response($css, 'css');
}
