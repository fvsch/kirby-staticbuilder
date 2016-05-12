<?php

namespace Kirby\Plugin\StaticBuilder;

use R;
use Tpl;


/**
 * Kirby router action that
 * @return bool
 */
function siteAction() {
	$confirm = r::is('POST') and r::get('confirm');
	$builder = new Builder();
	$data = [
		'error'   => false,
		'confirm' => $confirm,
		'folder'  => $builder->folder,
		'summary' => $builder->rebuild($confirm)
	];

	// Render info page
	echo tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
	return false;
}

/**
 *
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
		$data['folder']  = $builder->folder;
		$data['summary'] = [$builder->page($page, $confirm)];
	}

	// Render info page
	echo tpl::load(__DIR__ . DS . '..' . DS . 'templates' . DS . 'html.php', $data);
	return false;
}
