<?php

// The plugin must be enabled in config to be able to run,
// which allows enabling it only for a local dev environment.
if (c::get('plugin.staticbuilder.enabled', false)) {

	require_once __DIR__ . DS . 'core.php';

	$kirby->set('route', [
		'pattern' => 'staticbuilder/site',
		'action'  => 'Kirby\Plugin\StaticBuilder\siteAction',
		'method'  => 'GET|POST'
	]);

	$kirby->set('route', [
		'pattern' => 'staticbuilder/page/(:all)',
		'action'  => 'Kirby\Plugin\StaticBuilder\pageAction',
		'method'  => 'GET|POST'
	]);

}
