<?php

namespace PhpIntegrator\Application\Command;

use ReflectionClass;

use PhpIntegrator\IndexedTest;

class ClassListTest extends IndexedTest
{
    public function testClassList()
    {
        $path = __DIR__ . '/TestFiles/' . 'ClassList.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ClassList();
        $command->setIndexDatabase($indexDatabase);

        $reflectionClass = new ReflectionClass(get_class($command));
        $method = $reflectionClass->getMethod('getClassList');
        $method->setAccessible(true);

        $output = $method->invoke($command, $path);

        $this->assertThat($output, $this->arrayHasKey('A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('A\SecondClass'));
    }
}
