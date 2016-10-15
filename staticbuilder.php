<?php

/**
 * Kirby StaticBuilder Plugin
 * @file Main plugin file when installed manually or with Kirby’s CLI. Not used with Composer.
 */

// Using kirby’s autoloader helper
load([
    'kirby\\staticbuilder\\builder'    => __DIR__ . '/src/Builder.php',
    'kirby\\staticbuilder\\controller' => __DIR__ . '/src/Controller.php'
]);

Kirby\StaticBuilder\Controller::register();
