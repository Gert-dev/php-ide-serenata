<?php

use PhpIntegrator\Application;

// This limit can pose a problem for NameResolver built-in to php-parser (it can go over a nesting level of 300 in e.g.
// a Symfony2 code base).
ini_set('xdebug.max_nesting_level', -1);

chdir(__DIR__);

require '../vendor/autoload.php';

$arguments = $argv;

array_shift($arguments);

$response = (new Application())->handle($arguments);

echo $response;
