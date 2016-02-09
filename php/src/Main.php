<?php

use PhpIntegrator\Application;

// This limit can pose a problem for NameResolver built-in to php-parser (it can go over a nesting level of 300 in e.g.
// a Symfony2 code base). Apart from that, xdebug will only slow down indexing.
if (function_exists('xdebug_disable')) {
    xdebug_disable();
}

chdir(__DIR__);

require '../vendor/autoload.php';

$arguments = $argv;

array_shift($arguments);

$response = (new Application())->handle($arguments);

echo $response;
