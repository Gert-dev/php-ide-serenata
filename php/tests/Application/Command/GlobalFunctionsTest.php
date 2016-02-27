<?php

namespace PhpIntegrator\Application\Command;

use ReflectionClass;

use PhpIntegrator\IndexedTest;

class GlobalFunctionsTest extends IndexedTest
{
    public function testGlobalFunctions()
    {
        $path = __DIR__ . '/TestFiles/' . 'GlobalFunctions.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new GlobalFunctions();
        $command->setIndexDatabase($indexDatabase);

        $reflectionClass = new ReflectionClass(get_class($command));
        $method = $reflectionClass->getMethod('getGlobalFunctions');
        $method->setAccessible(true);

        $output = $method->invoke($command, $path);

        $this->assertThat($output, $this->arrayHasKey('firstFunction'));
        $this->assertThat($output, $this->arrayHasKey('secondFunction'));
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('shouldNotShowUp')));
    }
}
