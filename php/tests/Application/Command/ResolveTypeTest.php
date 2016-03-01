<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class ResolveTypeTest extends IndexedTest
{
    public function testCorrectlyResolvesVariousTypes()
    {
        $path = __DIR__ . '/ResolveTypeTest/' . 'ResolveType.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ResolveType();
        $command->setIndexDatabase($indexDatabase);

        $this->assertEquals($command->resolveType('C', $path, 1), 'C');
        $this->assertEquals($command->resolveType('C', $path, 5), 'A\C');
        $this->assertEquals($command->resolveType('C', $path, 10), 'B\C');
        $this->assertEquals($command->resolveType('DateTime', $path, 10), 'B\DateTime');
        $this->assertEquals($command->resolveType('DateTime', $path, 11), 'DateTime');
        $this->assertEquals($command->resolveType('DateTime', $path, 12), 'DateTime');
        $this->assertEquals($command->resolveType('D\Test', $path, 13), 'C\D\Test');
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new ResolveType();

        $command = new ResolveType();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $this->assertEquals($command->resolveType('C', 'MissingFile.php', 1), 'C');
    }
}
