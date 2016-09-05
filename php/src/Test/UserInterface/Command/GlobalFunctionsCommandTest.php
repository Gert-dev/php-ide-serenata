<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use PhpIntegrator\UserInterface\Command\GlobalFunctionsCommand;

use PhpIntegrator\Test\IndexedTest;

class GlobalFunctionsCommandTest extends IndexedTest
{
    public function testGlobalFunctions()
    {
        $path = __DIR__ . '/GlobalFunctionsCommandTest/' . 'GlobalFunctions.php.test';

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path);

        $command = new GlobalFunctionsCommand(
            $container->get('functionConverter'),
            $container->get('indexDatabase')
        );

        $output = $command->getGlobalFunctions();

        $this->assertThat($output, $this->arrayHasKey('\A\firstFunction'));
        $this->assertEquals($output['\A\firstFunction']['name'], 'firstFunction');
        $this->assertEquals($output['\A\firstFunction']['fqcn'], '\A\firstFunction');
        $this->assertThat($output, $this->arrayHasKey('\A\secondFunction'));
        $this->assertEquals($output['\A\secondFunction']['name'], 'secondFunction');
        $this->assertEquals($output['\A\secondFunction']['fqcn'], '\A\secondFunction');
        $this->assertThat($output, $this->logicalNot($this->arrayHasKey('shouldNotShowUp')));
    }

    public function testBuiltinGlobalFunctions()
    {
        $container = $this->createTestContainerForBuiltinStructuralElements();

        $command = new GlobalFunctionsCommand(
            $container->get('functionConverter'),
            $container->get('indexDatabase')
        );

        $output = $command->getGlobalFunctions();

        $this->assertArraySubset([
            'name'             => 'urlencode',
            'fqcn'             => '\urlencode',
            'startLine'        => 0,
            'endLine'          => 0,
            'filename'         => null,
            'isBuiltin'        => true,
            'isDeprecated'     => false,
            'hasDocblock'      => false,
            'hasDocumentation' => false,

            'throws'           => [],
            'returnTypeHint'   => null
        ], $output['\urlencode']);

        $this->assertArraySubset([
            [
                'name'         => 'str',
                'typeHint'     => null,
                'types'        => [
                    [
                        'type'         => 'string',
                        'fqcn'         => '\string',
                        'resolvedType' => '\string'
                    ]
                ],
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => false,
            ]
        ], $output['\urlencode']['parameters']);

        $this->assertEquals([
            [
                'fqcn'         => '\string',
                'resolvedType' => '\string',
                'type'         => 'string'
            ]
        ], $output['\urlencode']['returnTypes']);
    }
}
