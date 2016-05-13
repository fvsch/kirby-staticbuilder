<?php

namespace Kirby\Plugin\StaticBuilder;

use R;
use Response;
use Tpl;


/**
 * Kirby router action that lists all pages to build and files to copy,
 * and performs the actual build on user confirmation.
 * @return bool
 */
function siteAction() {
	$site = site();
	$confirm = r::is('POST') and r::get('confirm');

	$builder = new Builder();
	if ($confirm) $builder->write($site);
	else $builder->dryrun($site);
	$data = [
		'error'   => false,
		'confirm' => $confirm,
		'folder'  => $builder->info('folder'),
		'summary' => $builder->info('summary')
	];
	return htmlReport($data);
}

/**
 * Similar to siteAction but for a single page.
 * @param $uri
 * @return bool
 */
function pageAction($uri) {
	$page = page($uri);
	$confirm = r::is('POST') and r::get('confirm');
	$data = [
		'error'   => false,
		'confirm' => $confirm,
		'folder'  => null,
		'summary' => []
	];
	if (!$page) {
		$data['error'] = "Error: Cannot find page for \"$uri\"";
	}
	else {
		$builder = new Builder();
		if ($confirm) $builder->write($page);
		else $builder->dryrun($page);
		$data['folder']  = $builder->info('folder');
		$data['summary'] = $builder->info('summary');
	}
	return htmlReport($data);
}

/**
 * Render the HTML report page
 *
 * @param array $data
 * @return Response
 */
function htmlReport($data) {
	// Forcefully remove headers that might have been set by some
	// templates, controllers or plugins when rendering pages.
	header_remove();
	$body = tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
	return new Response($body, 'html', $data['error'] ? 500 : 200);
}
