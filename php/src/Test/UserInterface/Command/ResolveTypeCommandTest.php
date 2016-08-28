<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use PhpIntegrator\UserInterface\Command\ResolveTypeCommand;

use PhpIntegrator\Test\IndexedTest;

use PhpIntegrator\Indexing\IndexDatabase;

class ResolveTypeCommandTest extends IndexedTest
{
    public function testCorrectlyResolvesVariousTypes()
    {
        $path = __DIR__ . '/ResolveTypeCommandTest/' . 'ResolveType.php.test';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ResolveTypeCommand($this->getParser(), null, $indexDatabase);

        $this->assertEquals('\C', $command->resolveType('C', $path, 1));
        $this->assertEquals('\A\C', $command->resolveType('C', $path, 5));
        $this->assertEquals('\B\C', $command->resolveType('C', $path, 10));
        $this->assertEquals('\B\DateTime', $command->resolveType('DateTime', $path, 10));
        $this->assertEquals('\DateTime', $command->resolveType('DateTime', $path, 11));
        $this->assertEquals('\DateTime', $command->resolveType('DateTime', $path, 12));
        $this->assertEquals('\C\D\Test', $command->resolveType('D\Test', $path, 13));
        $this->assertEquals('\DateTime', $command->resolveType('DateTime', $path, 18));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new ResolveTypeCommand($this->getParser(), null, new IndexDatabase(':memory:', 1));

        $command->resolveType('\C', 'MissingFile.php', 1);
    }
}
