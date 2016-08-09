<?php

namespace PhpIntegrator;

use ReflectionClass;

use PhpIntegrator\Application\Command;

use PhpIntegrator\Indexing\IndexDatabase;
use PhpIntegrator\Indexing\IndexStorageItemEnum;

/**
 * Abstract base class for tests that need to test functionality that requires an indexing database to be set up with
 * the contents of a file or folder already indexed.
 */
abstract class IndexedTest extends \PHPUnit_Framework_TestCase
{
    protected function getParser()
    {
        $app = new Application();

        $refClass = new ReflectionClass(Application::class);
        $refMethod = $refClass->getMethod('getParser');

        $refMethod->setAccessible(true);

        return $refMethod->invoke($app);
    }

    protected function getDatabaseForTestFile($testPath, $mayFail = false)
    {
        $indexDatabase = new IndexDatabase(':memory:', 1);

        // Indexing these on every test majorly slows down testing. Instead, we simply don't rely on PHP's built-in
        // structural elements during testing.
        $indexDatabase->insert(IndexStorageItemEnum::SETTINGS, [
            'name'  => 'has_indexed_builtin',
            'value' => 1
        ]);

        $reindexCommand = new Command\Reindex($this->getParser());
        $reindexCommand->setIndexDatabase($indexDatabase);

        $success = $reindexCommand->reindex(
            [$testPath],
            false,
            false,
            false
        );

        if (!$mayFail) {
            $this->assertTrue($success);
        }

        return $indexDatabase;
    }
}
