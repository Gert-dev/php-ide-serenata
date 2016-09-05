<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use PhpIntegrator\UserInterface\Command\ClassListCommand;

use PhpIntegrator\Test\IndexedTest;

class ClassListCommandTest extends IndexedTest
{
    public function testClassList()
    {
        $path = __DIR__ . '/ClassListCommandTest/' . 'ClassList.php.test';

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path);

        $command = new ClassListCommand(
            $container->get('constantConverter'),
            $container->get('classlikeConstantConverter'),
            $container->get('propertyConverter'),
            $container->get('functionConverter'),
            $container->get('methodConverter'),
            $container->get('classlikeConverter'),
            $container->get('inheritanceResolver'),
            $container->get('interfaceImplementationResolver'),
            $container->get('traitUsageResolver'),
            $container->get('classlikeInfoBuilderProvider'),
            $container->get('typeAnalyzer'),
            $container->get('indexDatabase')
        );

        $output = $command->getClassList($path);

        $this->assertThat($output, $this->arrayHasKey('\A\FirstClass'));
        $this->assertThat($output, $this->arrayHasKey('\A\SecondClass'));
    }
}
