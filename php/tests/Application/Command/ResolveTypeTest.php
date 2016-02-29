<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class ResolveTypeTest extends IndexedTest
{
    public function testResolveType()
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
        $this->assertEquals($command->resolveType('A\Test', $path, 13), 'A\Test');
    }
}
