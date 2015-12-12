<?php

use PhpIntegrator\Application;

require 'vendor/autoload.php';

$arguments = $argv;

array_shift($arguments);

$response = (new Application())->handle($arguments);

echo $response;
