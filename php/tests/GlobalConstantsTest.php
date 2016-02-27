<?php

namespace PhpIntegrator;

use ReflectionClass;

use PhpIntegrator\Application\Command;

class GlobalConstantsTest extends IndexedTest
{
    public function testGlobalConstants()
    {
        $path = __DIR__ . '/ProjectTest/' . 'GlobalConstants.php';

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new Command\GlobalConstants();
        $command->setIndexDatabase($indexDatabase);

        $reflectionClass = new ReflectionClass(get_class($command));
        $method = $reflectionClass->getMethod('getGlobalConstants');
        $method->setAccessible(true);

        $output = $method->invoke($command, $path);

        $this->assertThat($output, $this->arrayHasKey('FIRST_CONSTANT'));
        $this->assertThat($output, $this->arrayHasKey('SECOND_CONSTANT'));
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('SHOULD_NOT_SHOW_UP')));
    }
}
