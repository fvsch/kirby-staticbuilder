<?php

$root = realpath(__DIR__.'/../../');

// Load Kirby classes
require_once $root . '/test/vendor/autoload.php';

// Config
c::set('debug', true);
c::set('staticbuilder', true);
c::set('staticbuilder.baseurl', './');
//c::set('staticbuilder.extension', '/index.html');
c::set('staticbuilder.assets', [
    'assets', 'content', 'thumbs',
    '../static_htaccess' => '.htaccess'
]);
c::set('staticbuilder.filter', function($page) {
    if ($page->intendedTemplate() == 'team') {
        return [false, 'No standalone pages for team members'];
    }
    return Kirby\StaticBuilder\Builder::defaultFilter($page);
});

// Fix root dir
$kirby = kirby();
$kirby->roots->kirby = $root . '/test/vendor/getkirby/kirby';
$kirby->roots->index = __DIR__;

// Load and register StaticBuilder
require_once $root . '/staticbuilder.php';

// Render
echo $kirby->launch();
