<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;
use PhpIntegrator\IndexDatabase;

class LocalizeTypeTest extends IndexedTest
{
    public function testCorrectlyLocalizesVariousTypes()
    {
        $path = __DIR__ . '/LocalizeTypeTest/' . 'LocalizeType.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new LocalizeType();
        $command->setIndexDatabase($indexDatabase);

        $this->assertEquals($command->localizeType('C', $path, 1), null);
        $this->assertEquals('A\C', $command->localizeType('A\C', $path, 5));
        $this->assertEquals('B\C', $command->localizeType('B\C', $path, 10));
        $this->assertEquals('B\DateTime', $command->localizeType('B\DateTime', $path, 10));
        $this->assertEquals('DateTime', $command->localizeType('DateTime', $path, 11));
        $this->assertEquals('DateTime', $command->localizeType('DateTime', $path, 12));
        $this->assertEquals('DateTime', $command->localizeType('\DateTime', $path, 12));
        $this->assertEquals('D\Test', $command->localizeType('C\D\Test', $path, 13));
        $this->assertEquals('E', $command->localizeType('C\D\E', $path, 14));
        $this->assertEquals('H', $command->localizeType('F\G\H', $path, 16));
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testThrowsExceptionOnUnknownFile()
    {
        $command = new LocalizeType();
        $command->setIndexDatabase(new IndexDatabase(':memory:', 1));

        $command->localizeType('C', 'MissingFile.php', 1);
    }
}
