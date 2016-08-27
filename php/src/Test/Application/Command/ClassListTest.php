<?php

namespace PhpIntegrator\Test\Application\Command;

use PhpIntegrator\Application\Command\ClassList;

use PhpIntegrator\Test\IndexedTest;

class ClassListTest extends IndexedTest
{
    public function testClassList()
    {
        $path = __DIR__ . '/ClassListTest/' . 'ClassList.php.test';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ClassList($this->getParser(), null, $indexDatabase);

        $output = $command->getClassList($path);

        $this->assertThat($output, $this->arrayHasKey('\A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('\A\SecondClass'));
    }
}
