<?php

// The plugin must be enabled in config to be able to run,
// which allows enabling it only for a local dev environment.
if (C::get('plugin.staticbuilder.enabled', false)) {

    $kirby = kirby();

    if (!class_exists('Kirby\Registry')) {
        throw new Exception('Twig plugin requires Kirby 2.3 or higher. Current version: ' . $kirby->version());
    }

    require_once __DIR__ . '/src/functions.php';
    require_once __DIR__ . '/src/Controller.php';
    require_once __DIR__ . '/src/StaticBuilder.php';

    $kirby->set('route', [
        'pattern' => 'staticbuilder',
        'action'  => 'Kirby\Plugin\StaticBuilder\Controller::siteAction',
        'method'  => 'GET|POST'
    ]);

    $kirby->set('route', [
        'pattern' => 'staticbuilder/(:all)',
        'action'  => 'Kirby\Plugin\StaticBuilder\Controller::pageAction',
        'method'  => 'GET|POST'
    ]);

}
