<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use PhpIntegrator\UserInterface\Command\GlobalConstantsCommand;

use PhpIntegrator\Test\IndexedTest;

class GlobalConstantsCommandTest extends IndexedTest
{
    public function testGlobalConstants()
    {
        $path = __DIR__ . '/GlobalConstantsCommandTest/' . 'GlobalConstants.php.test';

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path);

        $command = new GlobalConstantsCommand(
            $container->get('constantConverter'),
            $container->get('indexDatabase')
        );

        $output = $command->getGlobalConstants();

        $this->assertThat($output, $this->arrayHasKey('\DEFINE_CONSTANT'));
        $this->assertEquals($output['\DEFINE_CONSTANT']['name'], 'DEFINE_CONSTANT');
        $this->assertEquals($output['\DEFINE_CONSTANT']['fqcn'], '\DEFINE_CONSTANT');

        $this->assertThat($output, $this->arrayHasKey('\A\DEFINE_CONSTANT_NAMESPACED'));
        $this->assertEquals($output['\A\DEFINE_CONSTANT_NAMESPACED']['name'], 'DEFINE_CONSTANT_NAMESPACED');
        $this->assertEquals($output['\A\DEFINE_CONSTANT_NAMESPACED']['fqcn'], '\A\DEFINE_CONSTANT_NAMESPACED');

        $this->assertThat($output, $this->arrayHasKey('\A\FIRST_CONSTANT'));
        $this->assertEquals($output['\A\FIRST_CONSTANT']['name'], 'FIRST_CONSTANT');
        $this->assertEquals($output['\A\FIRST_CONSTANT']['fqcn'], '\A\FIRST_CONSTANT');

        $this->assertThat($output, $this->arrayHasKey('\A\SECOND_CONSTANT'));
        $this->assertEquals($output['\A\SECOND_CONSTANT']['name'], 'SECOND_CONSTANT');
        $this->assertEquals($output['\A\SECOND_CONSTANT']['fqcn'], '\A\SECOND_CONSTANT');

        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('SHOULD_NOT_SHOW_UP')));
    }

    public function testBuiltinGlobalConstants()
    {
        $container = $this->createTestContainerForBuiltinStructuralElements();

        $command = new GlobalConstantsCommand(
            $container->get('constantConverter'),
            $container->get('indexDatabase')
        );

        $output = $command->getGlobalConstants();

        $this->assertArraySubset([
            'name'             => 'PHP_EOL',
            'fqcn'             => '\PHP_EOL',
            'startLine'        => 0,
            'endLine'          => 0,
            'filename'         => null,
            'isBuiltin'        => true,
            'isDeprecated'     => false,
            'hasDocblock'      => false,
            'hasDocumentation' => false,

            'defaultValue'     => '"\n"',
            'isPublic'         => true,
            'isProtected'      => false,
            'isPrivate'        => false,
            'isStatic'         => true,
            'shortDescription' => null,
            'longDescription'  => null,
            'typeDescription'  => null
        ], $output['\PHP_EOL']);

        $this->assertEquals([

        ], $output['\PHP_EOL']['types']);
    }
}
