<?php

namespace PhpIntegrator\Test\Application\Command;

use PhpIntegrator\Application\Command\ClassListCommand;

use PhpIntegrator\Test\IndexedTest;

class ClassListCommandTest extends IndexedTest
{
    public function testClassList()
    {
        $path = __DIR__ . '/ClassListCommandTest/' . 'ClassList.php.test';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ClassListCommand($this->getParser(), null, $indexDatabase);

        $output = $command->getClassList($path);

        $this->assertThat($output, $this->arrayHasKey('\A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('\A\SecondClass'));
    }
}
