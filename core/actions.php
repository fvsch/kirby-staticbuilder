<?php

namespace Kirby\Plugin\StaticBuilder;

use R;
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

	// Render info page
	echo tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
	return false;
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
		$data['error'] = "Error: Cannot find page for '$uri'";
	}
	else {
		$builder = new Builder();
		if ($confirm) $builder->write($page);
		else $builder->dryrun($page);
		$data['folder']  = $builder->info('folder');
		$data['summary'] = $builder->info('summary');
	}

	// Render info page
	echo tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
	return false;
}
