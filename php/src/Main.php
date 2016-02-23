<?php

use PhpIntegrator\Application;

// Show us pretty much everything so we can properly debug what is going wrong.
error_reporting(E_ALL & ~E_DEPRECATED);

// This limit can pose a problem for NameResolver built-in to php-parser (it can go over a nesting level of 300 in e.g.
// a Symfony2 code base). Also, -1 as a value doesn't work in some setups, see also:
// https://github.com/Gert-dev/php-integrator-base/issues/91
ini_set('xdebug.max_nesting_level', 10000);

// xdebug will only slow down indexing. Very strangely enough, disabling xdebug doesn't seem to disable this nesting
// level in all cases. See also https://github.com/Gert-dev/php-integrator-base/issues/101 .
if (function_exists('xdebug_disable')) {
    xdebug_disable();
}

chdir(__DIR__);

require '../vendor/autoload.php';

$arguments = $argv;

array_shift($arguments);

$response = (new Application())->handle($arguments);

echo $response;
