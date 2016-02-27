<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class ClassListTest extends IndexedTest
{
    public function testClassList()
    {
        $path = __DIR__ . '/TestFiles/' . 'ClassList.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ClassList();
        $command->setIndexDatabase($indexDatabase);

        $output = $command->getClassList($path);

        $this->assertThat($output, $this->arrayHasKey('A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('A\SecondClass'));
    }
}
