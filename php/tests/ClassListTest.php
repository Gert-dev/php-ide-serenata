<?php

namespace PhpIntegrator;

use ReflectionClass;

use PhpIntegrator\Application\Command;

class ProjectTest extends IndexedTest
{
    public function testClassInheritance()
    {
        $path = __DIR__ . '/ProjectTest/' . 'ClassList.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new Command\ClassList();
        $command->setIndexDatabase($indexDatabase);

        $reflectionClass = new ReflectionClass(get_class($command));
        $method = $reflectionClass->getMethod('getClassList');
        $method->setAccessible(true);

        $output = $method->invoke($command, $path);

        $this->assertThat($output, $this->arrayHasKey('A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('A\SecondClass'));
    }
}
