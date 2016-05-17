<?php

// The plugin must be enabled in config to be able to run,
// which allows enabling it only for a local dev environment.
if (c::get('plugin.staticbuilder.enabled', false)) {

	if (!class_exists('Kirby\Registry')) {
		throw new Exception('Twig plugin requires Kirby 2.3 or higher. Current version: ' . $kirby->version());
	}

	require_once __DIR__ . DS . 'core' . DS . 'builder.php';
	require_once __DIR__ . DS . 'core' . DS . 'actions.php';

	$kirby->set('route', [
		'pattern' => 'staticbuilder',
		'action'  => 'Kirby\Plugin\StaticBuilder\siteAction',
		'method'  => 'GET|POST'
	]);

	$kirby->set('route', [
		'pattern' => 'staticbuilder/page/(:all)',
		'action'  => 'Kirby\Plugin\StaticBuilder\pageAction',
		'method'  => 'GET|POST'
	]);

	$kirby->set('route', [
		'pattern' => 'staticbuilder/report.css',
		'action'  => 'Kirby\Plugin\StaticBuilder\cssAction'
	]);

}
