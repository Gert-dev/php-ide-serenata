<?php

namespace PhpIntegrator\Test;

use ReflectionClass;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Indexing\BuiltinIndexer;

use PhpIntegrator\UserInterface\Command;

use PhpIntegrator\Indexing\IndexDatabase;
use PhpIntegrator\Indexing\IndexStorageItemEnum;

use PhpIntegrator\UserInterface\Application;

use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Abstract base class for tests that need to test functionality that requires an indexing database to be set up with
 * the contents of a file or folder already indexed.
 */
abstract class IndexedTest extends \PHPUnit_Framework_TestCase
{
    static $builtinIndexDatabase;

    /**
     * @return ContainerBuilder
     */
    protected function getApplicationContainer()
    {
        $app = new Application();

        $refClass = new ReflectionClass(Application::class);
        $refMethod = $refClass->getMethod('getContainer');

        $refMethod->setAccessible(true);

        return $refMethod->invoke($app);
    }

    /**
     * @return \PhpParser\Parser
     */
    protected function getParser()
    {
        return $this->getApplicationContainer()->get('parser.phpParser');
    }

    /**
     * @param bool $indexBuiltinItems
     *
     * @return IndexDatabase
     */
    protected function getDatabase()
    {
        return new IndexDatabase(':memory:', 1);
    }

    /**
     * @param string|null $testPath
     * @param bool        $mayFail
     *
     * @return IndexDatabase
     */
    protected function getDatabaseForTestFile($testPath = null, $mayFail = false)
    {
        $indexDatabase = $this->getDatabase();

        // Indexing these on every test majorly slows down testing. Instead, we simply don't rely on PHP's built-in
        // structural elements during testing.
        $indexDatabase->insert(IndexStorageItemEnum::SETTINGS, [
            'name'  => 'has_indexed_builtin',
            'value' => 1
        ]);

        $reindexCommand = new Command\ReindexCommand($this->getParser(), null, $indexDatabase);

        if ($testPath) {
            $success = $reindexCommand->reindex(
                [$testPath],
                false,
                false,
                false,
                [],
                ['test']
            );

            if (!$mayFail) {
                $this->assertTrue($success);
            }
        }

        return $indexDatabase;
    }

    /**
     * @return IndexDatabase
     */
    protected function getDatabaseForBuiltinTesting()
    {
        // Indexing builtin items is a fairy large performance hit to run every test, so keep the property static.
        if (!self::$builtinIndexDatabase) {
            self::$builtinIndexDatabase = $this->getDatabase();

            $builtiinIndexer = new BuiltinIndexer(self::$builtinIndexDatabase, new TypeAnalyzer());
            $builtiinIndexer->index();
        }

        return self::$builtinIndexDatabase;
    }
}
