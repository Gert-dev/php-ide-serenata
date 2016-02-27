<?php

namespace PhpIntegrator;

use ReflectionClass;

use PhpIntegrator\Application\Command;

class ResolveTypeTest extends IndexedTest
{
    public function testResolveType()
    {
        $path = __DIR__ . '/ProjectTest/' . 'ResolveType.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new Command\ResolveType();
        $command->setIndexDatabase($indexDatabase);

        $this->assertEquals($command->resolveType('C', $path, 1), 'C');
        $this->assertEquals($command->resolveType('C', $path, 5), 'A\C');
        $this->assertEquals($command->resolveType('C', $path, 10), 'B\C');
        $this->assertEquals($command->resolveType('DateTime', $path, 10), 'B\DateTime');
        $this->assertEquals($command->resolveType('DateTime', $path, 11), 'DateTime');
        $this->assertEquals($command->resolveType('DateTime', $path, 12), 'DateTime');
    }
}
