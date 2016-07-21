<?php

use PhpIntegrator\Application;

if (version_compare(PHP_VERSION, '5.4.0') === -1) {
    die('You need at least PHP 5.4, your current version is PHP ' . PHP_VERSION);
}

if (!function_exists('mb_substr')) {
    die('Multibyte String support in your PHP installation is required. See also https://secure.php.net/manual/en/book.mbstring.php');
}

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

// Explicitly set the timezone to avoid warnings in some older PHP 5 versions. Also, this prevents files suddenly being
// picked up as being modified if the user changes the timezone in php.ini.
date_default_timezone_set('UTC');

mb_internal_encoding('UTF-8');

chdir(__DIR__);

require '../vendor/autoload.php';

// Check the SQLite version.
try {
    $sqliteTestConnection = Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'path'   => ':memory:'
    ], new Doctrine\DBAL\Configuration());

    $query = "SELECT sqlite_version()";

    $sqliteVersion = $sqliteTestConnection->query($query)->fetch();
    $sqliteVersion = array_shift($sqliteVersion);

    $requiredSqliteVersion = '3.7.11';

    if (version_compare($sqliteVersion, $requiredSqliteVersion) === -1) {
        die("At least SQLite {$requiredSqliteVersion} is required. The detected version was {$sqliteVersion}.");
    }
} catch (Exception $e) {
    die('Connecting using SQLite failed, do you have an extension to support it enabled in php.ini?');
}

$arguments = $argv;

$response = (new Application())->handle($arguments);

echo $response;
