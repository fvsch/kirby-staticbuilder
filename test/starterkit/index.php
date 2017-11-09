<?php

$root = realpath(__DIR__.'/../../');

// Load Kirby classes
require_once $root . '/test/vendor/autoload.php';

// Fix root dir
$kirby = kirby();
$kirby->roots->kirby = $root . '/test/vendor/getkirby/kirby';
$kirby->roots->index = __DIR__;

// Render
echo $kirby->launch();
