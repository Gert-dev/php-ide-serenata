<?php

namespace PhpIntegrator;

use ReflectionClass;

use PhpIntegrator\Application\Command;

/**
 * Abstract base class for tests that need to test functionality that requires an indexing database to be set up with
 * the contents of a file or folder already indexed.
 */
abstract class IndexedTest extends \PHPUnit_Framework_TestCase
{
    protected function getDatabaseForTestFile($testPath)
    {
        $indexDatabase = new IndexDatabase(':memory:', 1);

        $reindexCommand = new Command\Reindex();
        $reindexCommand->setIndexDatabase($indexDatabase);

        $reindexOutput = $reindexCommand->reindex(
            $testPath,
            false,
            false,
            false
        );

        $reindexOutput = json_decode($reindexOutput, true);

        $this->assertNotNull($reindexOutput);
        $this->assertTrue($reindexOutput['success']);

        return $indexDatabase;
    }
}
