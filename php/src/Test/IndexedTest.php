<?php

namespace PhpIntegrator\Test;

use ReflectionClass;

use PhpIntegrator\UserInterface\Command;

use PhpIntegrator\Indexing\IndexDatabase;
use PhpIntegrator\Indexing\IndexStorageItemEnum;

use PhpIntegrator\UserInterface\Application;

/**
 * Abstract base class for tests that need to test functionality that requires an indexing database to be set up with
 * the contents of a file or folder already indexed.
 */
abstract class IndexedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @return \PhpParser\Parser
     */
    protected function getParser()
    {
        $app = new Application();

        $refClass = new ReflectionClass(Application::class);
        $refMethod = $refClass->getMethod('getParser');

        $refMethod->setAccessible(true);

        return $refMethod->invoke($app);
    }

    /**
     * @param bool $indexBuiltinItems
     *
     * @return IndexDatabase
     */
    protected function getDatabase($indexBuiltinItems = false)
    {
        $indexDatabase = new IndexDatabase(':memory:', 1);

        if (!$indexBuiltinItems) {
            // Indexing these on every test majorly slows down testing. Instead, we simply don't rely on PHP's built-in
            // structural elements during testing.
            $indexDatabase->insert(IndexStorageItemEnum::SETTINGS, [
                'name'  => 'has_indexed_builtin',
                'value' => 1
            ]);
        }

        return $indexDatabase;
    }

    /**
     * @param string|null $testPath
     * @param bool        $mayFail
     *
     * @return IndexDatabase
     */
    protected function getDatabaseForTestFile($testPath = null, $mayFail = false)
    {
        $indexDatabase = $this->getDatabase(false);

        $reindexCommand = new Command\ReindexCommand($this->getParser(), null, $indexDatabase);

        if ($testPath) {
            $success = $reindexCommand->reindex(
                [$testPath],
                false,
                false,
                false
            );

            if (!$mayFail) {
                $this->assertTrue($success);
            }
        }

        return $indexDatabase;
    }
}
