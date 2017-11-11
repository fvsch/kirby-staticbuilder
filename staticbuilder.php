<?php

/**
 * Kirby StaticBuilder Plugin
 * @file Main plugin file when installed manually or with Kirby’s CLI. Not used with Composer.
 */

// Using kirby’s autoloader helper
load([
    'kirbystaticbuilder\\builder' => __DIR__ . '/src/Builder.php',
    'kirbystaticbuilder\\plugin'  => __DIR__ . '/src/Plugin.php'
]);

KirbyStaticBuilder\Plugin::register();
