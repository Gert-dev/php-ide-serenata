<?php

namespace PhpIntegrator\Test\UserInterface\Command;

use PhpIntegrator\UserInterface\Command\ClassInfoCommand;

use PhpIntegrator\Test\IndexedTest;

class ClassInfoCommandTest extends IndexedTest
{
    protected function getClassInfo($file, $fqcn)
    {
        $path = $this->getPathFor($file);

        $container = $this->createTestContainer();

        $this->indexTestFile($container, $path);

        $command = new ClassInfoCommand(
            $container->get('typeAnalyzer'),
            $container->get('classlikeInfoBuilder')
        );

        return $command->getClassInfo($fqcn);
    }

    protected function getBuiltinClassInfo($fqcn)
    {
        $container = $this->createTestContainerForBuiltinStructuralElements();

        $command = new ClassInfoCommand(
            $container->get('typeAnalyzer'),
            $container->get('classlikeInfoBuilder')
        );

        return $command->getClassInfo($fqcn);
    }

    protected function getPathFor($file)
    {
        return __DIR__ . '/ClassInfoCommandTest/' . $file;
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testFailsOnUnknownClass()
    {
        $output = $this->getClassInfo('SimpleClass.php.test', 'DoesNotExist');
    }

    public function testLeadingSlashIsResolvedCorrectly()
    {
        $fileName = 'SimpleClass.php.test';

        $this->assertEquals(
            $this->getClassInfo($fileName, 'A\SimpleClass'),
            $this->getClassInfo($fileName, '\A\SimpleClass')
        );
    }

    public function testDataIsCorrectForASimpleClass()
    {
        $fileName = 'SimpleClass.php.test';

        $output = $this->getClassInfo($fileName, 'A\SimpleClass');

        $this->assertEquals($output, [
            'name'               => '\A\SimpleClass',
            'startLine'          => 10,
            'endLine'            => 13,
            'shortName'          => 'SimpleClass',
            'filename'           => $this->getPathFor($fileName),
            'type'               => 'class',
            'isAbstract'         => false,
            'isFinal'            => false,
            'isBuiltin'          => false,
            'isDeprecated'       => false,
            'isAnnotation'       => false,
            'hasDocblock'        => true,
            'hasDocumentation'   => true,
            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'parents'            => [],
            'interfaces'         => [],
            'traits'             => [],
            'directParents'      => [],
            'directInterfaces'   => [],
            'directTraits'       => [],
            'directChildren'     => [],
            'directImplementors' => [],
            'directTraitUsers'   => [],
            'constants'          => [],
            'properties'         => [],
            'methods'            => []
        ]);
    }

    public function testAnnotationClassIsCorrectlyPickedUp()
    {
        $fileName = 'AnnotationClass.php.test';

        $output = $this->getClassInfo($fileName, 'A\AnnotationClass');

        $this->assertTrue($output['isAnnotation']);
    }

    public function testFinalClassIsCorrectlyPickedUp()
    {
        $fileName = 'FinalClass.php.test';

        $output = $this->getClassInfo($fileName, 'A\FinalClass');

        $this->assertTrue($output['isFinal']);
    }

    public function testDataIsCorrectForClassProperties()
    {
        $fileName = 'ClassProperty.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
            'name'               => 'testProperty',
            'startLine'          => 14,
            'endLine'            => 14,
            'defaultValue'       => "'test'",
            'isMagic'            => false,
            'isPublic'           => false,
            'isProtected'        => true,
            'isPrivate'          => false,
            'isStatic'           => false,
            'isDeprecated'       => false,
            'hasDocblock'        => true,
            'hasDocumentation'   => true,
            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'typeDescription'    => null,

            'types'             => [
                [
                    'type'         => 'MyType',
                    'fqcn'         => '\A\MyType',
                    'resolvedType' => '\A\MyType'
                ],

                [
                    'type'         => 'string',
                    'fqcn'         => 'string',
                    'resolvedType' => 'string'
                ]
            ],

            'override'           => null,

            'declaringClass' => [
                'name'      => '\A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 15,
                'type'            => 'class',
                'startLineMember' => 14,
                'endLineMember'   => 14
            ]
        ], $output['properties']['testProperty']);
    }

    public function testPropertyDescriptionAfterVarTagTakesPrecedenceOverDocblockSummary()
    {
        $fileName = 'ClassPropertyDescriptionPrecedence.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals('This is a description after the var tag.', $output['properties']['testProperty']['shortDescription']);
        $this->assertEquals('This is a long description.', $output['properties']['testProperty']['longDescription']);
    }

    public function testCompoundClassPropertyStatementsHaveTheirDocblocksAnalyzedCorrectly()
    {
        $fileName = 'CompoundClassPropertyStatement.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals('A description of the first property.', $output['properties']['testProperty1']['shortDescription']);
        $this->assertEquals('This is a long description.', $output['properties']['testProperty1']['longDescription']);

        $this->assertEquals([
            [
                'type'         => 'Foo1',
                'fqcn'         => '\A\Foo1',
                'resolvedType' => '\A\Foo1'
            ]
        ], $output['properties']['testProperty1']['types']);

        $this->assertEquals('A description of the second property.', $output['properties']['testProperty2']['shortDescription']);
        $this->assertEquals('This is a long description.', $output['properties']['testProperty2']['longDescription']);

        $this->assertEquals([
            [
                'type'         => 'Foo2',
                'fqcn'         => '\A\Foo2',
                'resolvedType' => '\A\Foo2'
            ]
        ], $output['properties']['testProperty2']['types']);
    }

    public function testPropertyTypeDeductionFallsBackToUsingItsDefaultValue()
    {
        $fileName = 'ClassPropertyDefaultValue.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
            [
                'type'         => 'string',
                'fqcn'         => 'string',
                'resolvedType' => 'string'
            ]
        ], $output['properties']['testProperty']['types']);

        $this->assertEquals([
            [
                'type'         => 'null',
                'fqcn'         => 'null',
                'resolvedType' => 'null'
            ]
        ], $output['properties']['testPropertyWithNull']['types']);
    }

    public function testConstantTypeDeductionFallsBackToUsingItsDefaultValue()
    {
        $fileName = 'ClassConstantDefaultValue.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
            [
                'type'         => 'array',
                'fqcn'         => 'array',
                'resolvedType' => 'array'
            ]
        ], $output['constants']['TEST_CONSTANT']['types']);
    }

    public function testDataIsCorrectForClassMethods()
    {
        $fileName = 'ClassMethod.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
            'name'               => 'testMethod',
            'fqcn'              => null,
            'isBuiltin'          => false,
            'startLine'          => 19,
            'endLine'            => 22,
            'filename'           => $this->getPathFor($fileName),

            'parameters'         => [
                [
                    'name'         => 'firstParameter',
                    'typeHint'     => '\DateTimeInterface',
                    'description'  => 'First parameter description.',
                    'defaultValue' => 'null',
                    'isNullable'   => true,
                    'isReference'  => false,
                    'isVariadic'   => false,
                    'isOptional'   => true,

                    'types' => [
                        [
                            'type'         => '\DateTimeInterface',
                            'fqcn'         => '\DateTimeInterface',
                            'resolvedType' => '\DateTimeInterface'
                        ],

                        [
                            'type'         => '\DateTime',
                            'fqcn'         => '\DateTime',
                            'resolvedType' => '\DateTime'
                        ]
                    ]
                ],

                [
                    'name'         => 'secondParameter',
                    'typeHint'     => null,
                    'description'  => null,
                    'defaultValue' => 'true',
                    'isNullable'   => false,
                    'isReference'  => true,
                    'isVariadic'   => false,
                    'isOptional'   => true,
                    'types'        => []
                ],

                [
                    'name'         => 'thirdParameter',
                    'typeHint'     => null,
                    'description'  => null,
                    'defaultValue' => null,
                    'isNullable'   => false,
                    'isReference'  => false,
                    'isVariadic'   => true,
                    'isOptional'   => false,
                    'types'        => []
                ]
            ],

            'throws'             => [
                '\UnexpectedValueException' => 'when something goes wrong.',
                '\LogicException'           => 'when something is wrong.'
            ],

            'isDeprecated'       => false,
            'hasDocblock'        => true,
            'hasDocumentation'   => true,

            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'returnDescription'  => null,
            'returnTypeHint'     => null,

            'returnTypes' => [
                [
                    'type'         => 'mixed',
                    'fqcn'         => 'mixed',
                    'resolvedType' => 'mixed'
                ],

                [
                    'type'         => 'bool',
                    'fqcn'         => 'bool',
                    'resolvedType' => 'bool'
                ]
            ],

            'isMagic'            => false,
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => false,
            'isAbstract'         => false,
            'isFinal'            => false,
            'override'           => null,
            'implementation'     => null,

            'declaringClass'     => [
                'name'      => '\A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 23,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 23,
                'type'            => 'class',
                'startLineMember' => 19,
                'endLineMember'   => 22
            ]
        ], $output['methods']['testMethod']);
    }

    public function testFinalMethodIsCorrectlyPickedUp()
    {
        $fileName = 'FinalClassMethod.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertTrue($output['methods']['finalMethod']['isFinal']);
    }

    public function testDataIsCorrectForClassConstants()
    {
        $fileName = 'ClassConstant.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['constants']['TEST_CONSTANT'], [
            'name'               => 'TEST_CONSTANT',
            'fqcn'              => null,
            'isBuiltin'          => false,
            'startLine'          => 14,
            'endLine'            => 14,
            'defaultValue'       => '5',
            'filename'           => $this->getPathFor($fileName),
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => true,
            'isDeprecated'       => false,
            'hasDocblock'        => true,
            'hasDocumentation'   => true,

            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'typeDescription'    => null,

            'types'             => [
                [
                    'type'         => 'MyType',
                    'fqcn'         => '\A\MyType',
                    'resolvedType' => '\A\MyType'
                ],

                [
                    'type'         => 'string',
                    'fqcn'         => 'string',
                    'resolvedType' => 'string'
                ]
            ],

            'declaringClass'     => [
                'name'      => '\A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 15,
                'type'            => 'class',
                'startLineMember' => 14,
                'endLineMember'   => 14
            ]
        ]);
    }

    public function testConstantDescriptionAfterVarTagTakesPrecedenceOverDocblockSummary()
    {
        $fileName = 'ClassConstantDescriptionPrecedence.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals('This is a description after the var tag.', $output['constants']['TEST_CONSTANT']['shortDescription']);
        $this->assertEquals('This is a long description.', $output['constants']['TEST_CONSTANT']['longDescription']);
    }

    public function testDocblockInheritanceWorksProperlyForClasses()
    {
        $fileName = 'ClassDocblockInheritance.php.test';

        $childClassOutput = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');
        $anotherChildClassOutput = $this->getClassInfo($fileName, 'A\AnotherChildClass');

        $this->assertEquals('This is the summary.', $childClassOutput['shortDescription']);
        $this->assertEquals('This is a long description.', $childClassOutput['longDescription']);

        $this->assertEquals(
            'Pre. ' . $parentClassOutput['longDescription'] . ' Post.',
            $anotherChildClassOutput['longDescription']
        );
    }

    public function testDocblockInheritanceWorksProperlyForMethods()
    {
        $fileName = 'MethodDocblockInheritance.php.test';

        $traitOutput       = $this->getClassInfo($fileName, 'A\TestTrait');
        $interfaceOutput   = $this->getClassInfo($fileName, 'A\TestInterface');
        $childClassOutput  = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');

        $keysToTestForEquality = [
            'hasDocumentation',
            'isDeprecated',
            'longDescription',
            'shortDescription',
            'returnTypes',
            'parameters',
            'throws'
        ];

        foreach ($keysToTestForEquality as $key) {
            $this->assertEquals(
                $childClassOutput['methods']['basicDocblockInheritanceTraitTest'][$key],
                $traitOutput['methods']['basicDocblockInheritanceTraitTest'][$key]
            );

            $this->assertEquals(
                $childClassOutput['methods']['basicDocblockInheritanceInterfaceTest'][$key],
                $interfaceOutput['methods']['basicDocblockInheritanceInterfaceTest'][$key]
            );

            $this->assertEquals(
                $childClassOutput['methods']['basicDocblockInheritanceBaseClassTest'][$key],
                $parentClassOutput['methods']['basicDocblockInheritanceBaseClassTest'][$key]
            );
        }

        $this->assertEquals(
            'Pre. ' . $parentClassOutput['methods']['inheritDocBaseClassTest']['longDescription'] . ' Post.',
            $childClassOutput['methods']['inheritDocBaseClassTest']['longDescription']
        );

        $this->assertEquals(
            'Pre. ' . $interfaceOutput['methods']['inheritDocInterfaceTest']['longDescription'] . ' Post.',
            $childClassOutput['methods']['inheritDocInterfaceTest']['longDescription']
        );

        $this->assertEquals(
            'Pre. ' . $traitOutput['methods']['inheritDocTraitTest']['longDescription'] . ' Post.',
            $childClassOutput['methods']['inheritDocTraitTest']['longDescription']
        );
    }

    public function testDocblockInheritanceWorksProperlyForProperties()
    {
        $fileName = 'PropertyDocblockInheritance.php.test';

        $traitOutput       = $this->getClassInfo($fileName, 'A\TestTrait');
        $childClassOutput  = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');

        $keysToTestForEquality = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'typeDescription',
            'types'
        ];

        foreach ($keysToTestForEquality as $key) {
            $this->assertEquals(
                $childClassOutput['properties']['basicDocblockInheritanceTraitTest'][$key],
                $traitOutput['properties']['basicDocblockInheritanceTraitTest'][$key]
            );

            $this->assertEquals(
                $childClassOutput['properties']['basicDocblockInheritanceBaseClassTest'][$key],
                $parentClassOutput['properties']['basicDocblockInheritanceBaseClassTest'][$key]
            );
        }

        $this->assertEquals(
            $childClassOutput['properties']['inheritDocBaseClassTest']['longDescription'],
            'Pre. ' . $parentClassOutput['properties']['inheritDocBaseClassTest']['longDescription'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['properties']['inheritDocTraitTest']['longDescription'],
            'Pre. ' . $traitOutput['properties']['inheritDocTraitTest']['longDescription'] . ' Post.'
        );
    }

    public function testMethodOverridingIsAnalyzedCorrectly()
    {
        $fileName = 'MethodOverride.php.test';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'description'  => null,
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => false,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ]
                ]
            ]
        ], $output['methods']['__construct']['parameters']);

        $this->assertEquals([
            'startLine'   => 17,
            'endLine'     => 20,
            'wasAbstract' => false,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 13,
                'endLine'   => 26,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 13,
                'endLine'         => 26,
                'type'            => 'class',
                'startLineMember' => 17,
                'endLineMember'   => 20
            ]
        ], $output['methods']['__construct']['override']);

        $this->assertEquals(42, $output['methods']['__construct']['startLine']);
        $this->assertEquals(45, $output['methods']['__construct']['endLine']);

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'description'  => null,
                'defaultValue' => 'null',
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['parentTraitMethod']['parameters']);

        $this->assertEquals([
            'startLine'   => 7,
            'endLine'     => 10,
            'wasAbstract' => false,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 13,
                'endLine'   => 26,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 11,
                'type'            => 'trait',
                'startLineMember' => 7,
                'endLineMember'   => 10
            ]
        ], $output['methods']['parentTraitMethod']['override']);

        $this->assertEquals(47, $output['methods']['parentTraitMethod']['startLine']);
        $this->assertEquals(50, $output['methods']['parentTraitMethod']['endLine']);

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'description'  => null,
                'defaultValue' => 'null',
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['parentMethod']['parameters']);

        $this->assertEquals([
            'startLine'   => 22,
            'endLine'     => 25,
            'wasAbstract' => false,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 13,
                'endLine'   => 26,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 13,
                'endLine'         => 26,
                'type'            => 'class',
                'startLineMember' => 22,
                'endLineMember'   => 25
            ]
        ], $output['methods']['parentMethod']['override']);

        $this->assertEquals(52, $output['methods']['parentMethod']['startLine']);
        $this->assertEquals(55, $output['methods']['parentMethod']['endLine']);

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'description'  => null,
                'defaultValue' => 'null',
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['traitMethod']['parameters']);

        $this->assertEquals([
            'startLine'   => 30,
            'endLine'     => 33,
            'wasAbstract' => false,

            'declaringClass' => [
                'name'      => '\A\ChildClass',
                'filename'  =>  $this->getPathFor($fileName),
                'startLine' => 38,
                'endLine'   => 66,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\TestTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 28,
                'endLine'         => 36,
                'type'            => 'trait',
                'startLineMember' => 30,
                'endLineMember'   => 33
            ]
        ], $output['methods']['traitMethod']['override']);

        $this->assertEquals(57, $output['methods']['traitMethod']['startLine']);
        $this->assertEquals(60, $output['methods']['traitMethod']['endLine']);

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'defaultValue' => 'null',
                'description'  => null,
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['abstractMethod']['parameters']);

        $this->assertEquals($output['methods']['abstractMethod']['override']['wasAbstract'], true);
    }

    public function testPropertyOverridingIsAnalyzedCorrectly()
    {
        $fileName = 'PropertyOverride.php.test';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['properties']['parentTraitProperty']['override'], [
            'startLine' => 7,
            'endLine'   => 7,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 10,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 8,
                'type'            => 'trait',
                'startLineMember' => 7,
                'endLineMember'   => 7
            ]
        ]);

        $this->assertEquals($output['properties']['parentProperty']['override'], [
            'startLine' => 14,
            'endLine'   => 14,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 10,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 10,
                'endLine'         => 15,
                'type'            => 'class',
                'startLineMember' => 14,
                'endLineMember'   => 14
            ]
        ]);
    }

    public function testMethodImplementationIsAnalyzedCorrectly()
    {
        $fileName = 'MethodImplementation.php.test';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'defaultValue' => 'null',
                'description'  => null,
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['parentInterfaceMethod']['parameters']);

        $this->assertEquals([
            'startLine' => 7,
            'endLine'   => 7,

            'declaringClass' => [
                'name'      => '\A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 10,
                'endLine'   => 13,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\ParentInterface',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 8,
                'type'            => 'interface',
                'startLineMember' => 7,
                'endLineMember'   => 7
            ]
        ], $output['methods']['parentInterfaceMethod']['implementation']);

        $this->assertEquals([
            [
                'name'         => 'foo',
                'typeHint'     => 'Foo',
                'description'  => null,
                'defaultValue' => 'null',
                'isNullable'   => true,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,

                'types' => [
                    [
                        'type'         => 'Foo',
                        'fqcn'         => '\A\Foo',
                        'resolvedType' => '\A\Foo'
                    ],

                    [
                        'type'         => 'null',
                        'fqcn'         => 'null',
                        'resolvedType' => 'null'
                    ]
                ]
            ]
        ], $output['methods']['interfaceMethod']['parameters']);

        $this->assertEquals([
            'startLine' => 17,
            'endLine'   => 17,

            'declaringClass' => [
                'name'      => '\A\ChildClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 20,
                'endLine'   => 31,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => '\A\TestInterface',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 15,
                'endLine'         => 18,
                'type'            => 'interface',
                'startLineMember' => 17,
                'endLineMember'   => 17
            ]
        ], $output['methods']['interfaceMethod']['implementation']);
    }

    public function testMethodParameterTypesFallBackToDocblock()
    {
        $fileName = 'MethodParameterDocblockFallBack.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');
        $parameters = $output['methods']['testMethod']['parameters'];

        $this->assertEquals($parameters[0]['types'][0]['type'], '\DateTime');
        $this->assertEquals($parameters[1]['types'][0]['type'], 'boolean');
        $this->assertEquals($parameters[2]['types'][0]['type'], 'mixed');
        $this->assertEquals($parameters[3]['types'][0]['type'], '\Traversable[]');
    }

    public function testMagicClassPropertiesArePickedUpCorrectly()
    {
        $fileName = 'MagicClassProperties.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $data = $output['properties']['prop1'];

        $this->assertEquals($data['name'], 'prop1');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['shortDescription'], 'Description 1.');
        $this->assertEquals($data['longDescription'], '');
        $this->assertEquals($data['typeDescription'], null);

        $this->assertEquals($data['types'], [
            [
                'type'         => 'Type1',
                'fqcn'         => '\A\Type1',
                'resolvedType' => '\A\Type1'
            ]
        ]);

        $data = $output['properties']['prop2'];

        $this->assertEquals($data['name'], 'prop2');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['shortDescription'], 'Description 2.');
        $this->assertEquals($data['longDescription'], '');

        $this->assertEquals($data['types'], [
            [
                'type'         => 'Type2',
                'fqcn'         => '\A\Type2',
                'resolvedType' => '\A\Type2'
            ]
        ]);

        $data = $output['properties']['prop3'];

        $this->assertEquals($data['name'], 'prop3');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['shortDescription'], 'Description 3.');
        $this->assertEquals($data['longDescription'], '');

        $this->assertEquals($data['types'], [
            [
                'type'         => 'Type3',
                'fqcn'         => '\A\Type3',
                'resolvedType' => '\A\Type3'
            ]
        ]);

        $data = $output['properties']['prop4'];

        $this->assertEquals($data['name'], 'prop4');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], true);

        $this->assertEquals($data['shortDescription'], 'Description 4.');
        $this->assertEquals($data['longDescription'], '');

        $this->assertEquals($data['types'], [
            [
                'type'         => 'Type4',
                'fqcn'         => '\A\Type4',
                'resolvedType' => '\A\Type4'
            ]
        ]);
    }

    public function testMagicClassMethodsArePickedUpCorrectly()
    {
        $fileName = 'MagicClassMethods.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $data = $output['methods']['magicFoo'];

        $this->assertEquals($data['name'], 'magicFoo');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);
        $this->assertNull($data['returnTypeHint']);

        $this->assertEquals($data['parameters'], []);

        $this->assertNull($data['shortDescription']);
        $this->assertNull($data['longDescription']);
        $this->assertNull($data['returnDescription']);

        $this->assertEquals($data['returnTypes'], [
            [
                'type'         => 'void',
                'fqcn'         => 'void',
                'resolvedType' => 'void'
            ]
        ]);

        $data = $output['methods']['someMethod'];

        $this->assertEquals($data['name'], 'someMethod');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);
        $this->assertNull($data['returnTypeHint']);

        $this->assertEquals($data['parameters'], [
            [
                'name'         => 'a',
                'typeHint'     => null,
                'description'  => null,
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => false,
                'types'        => []
            ],

            [
                'name'         => 'b',
                'typeHint'     => null,
                'description'  => null,
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => false,
                'types'        => []
            ],

            [
                'name'         => 'c',
                'typeHint'     => null,
                'description'  => null,
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,
                'types'        => [
                    [
                        'type'         => 'array',
                        'fqcn'         => 'array',
                        'resolvedType' => 'array'
                    ]
                ]
            ],

            [
                'name'         => 'd',
                'typeHint'     => null,
                'description'  => null,
                'defaultValue' => null,
                'isNullable'   => false,
                'isReference'  => false,
                'isVariadic'   => false,
                'isOptional'   => true,
                'types'        => [
                    [
                        'type'         => 'Type',
                        'fqcn'         => '\A\Type',
                        'resolvedType' => '\A\Type'
                    ]
                ]
            ]
        ]);

        $this->assertEquals($data['shortDescription'], 'Description of method Test second line.');
        $this->assertEquals($data['longDescription'], '');
        $this->assertNull($data['returnDescription']);

        $this->assertEquals($data['returnTypes'], [
            [
                'type'         => 'TestClass',
                'fqcn'         => '\A\TestClass',
                'resolvedType' => '\A\TestClass'
            ]
        ]);

        $data = $output['methods']['magicFooStatic'];

        $this->assertEquals($data['name'], 'magicFooStatic');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], true);
        $this->assertNull($data['returnTypeHint']);

        $this->assertEquals($data['parameters'], []);

        $this->assertNull($data['shortDescription']);
        $this->assertNull($data['longDescription']);
        $this->assertNull($data['returnDescription']);

        $this->assertEquals($data['returnTypes'], [
            [
                'type'         => 'void',
                'fqcn'         => 'void',
                'resolvedType' => 'void'
            ]
        ]);
    }

    public function testDataIsCorrectForClassInheritance()
    {
        $fileName = 'ClassInheritance.php.test';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['parents'], ['\A\BaseClass', '\A\AncestorClass']);
        $this->assertEquals($output['directParents'], ['\A\BaseClass']);

        $this->assertThat($output['constants'], $this->arrayHasKey('INHERITED_CONSTANT'));
        $this->assertThat($output['constants'], $this->arrayHasKey('CHILD_CONSTANT'));

        $this->assertThat($output['properties'], $this->arrayHasKey('inheritedProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('childProperty'));

        $this->assertThat($output['methods'], $this->arrayHasKey('inheritedMethod'));
        $this->assertThat($output['methods'], $this->arrayHasKey('childMethod'));

        // Do a couple of sanity checks.
        $this->assertEquals('\A\BaseClass', $output['constants']['INHERITED_CONSTANT']['declaringClass']['name']);
        $this->assertEquals('\A\BaseClass', $output['properties']['inheritedProperty']['declaringClass']['name']);
        $this->assertEquals('\A\BaseClass', $output['methods']['inheritedMethod']['declaringClass']['name']);

        $this->assertEquals('\A\BaseClass', $output['constants']['INHERITED_CONSTANT']['declaringStructure']['name']);
        $this->assertEquals('\A\BaseClass', $output['properties']['inheritedProperty']['declaringStructure']['name']);
        $this->assertEquals('\A\BaseClass', $output['methods']['inheritedMethod']['declaringStructure']['name']);

        $output = $this->getClassInfo($fileName, 'A\BaseClass');

        $this->assertEquals($output['directChildren'], ['\A\ChildClass']);
        $this->assertEquals($output['parents'], ['\A\AncestorClass']);
    }

    public function testInterfaceImplementationIsCorrectlyProcessed()
    {
        $fileName = 'InterfaceImplementation.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals(['\A\FirstInterface', '\A\SecondInterface', '\A\BaseInterface'], $output['interfaces']);
        $this->assertEquals(['\A\FirstInterface', '\A\SecondInterface'], $output['directInterfaces']);

        $this->assertThat($output['constants'], $this->arrayHasKey('FIRST_INTERFACE_CONSTANT'));
        $this->assertThat($output['constants'], $this->arrayHasKey('SECOND_INTERFACE_CONSTANT'));

        $this->assertThat($output['methods'], $this->arrayHasKey('methodFromFirstInterface'));
        $this->assertThat($output['methods'], $this->arrayHasKey('methodFromSecondInterface'));

        // Do a couple of sanity checks.
        $this->assertEquals('\A\FirstInterface', $output['constants']['FIRST_INTERFACE_CONSTANT']['declaringClass']['name']);
        $this->assertEquals('\A\FirstInterface', $output['constants']['FIRST_INTERFACE_CONSTANT']['declaringStructure']['name']);
        $this->assertEquals('\A\TestClass', $output['methods']['methodFromFirstInterface']['declaringClass']['name']);
        $this->assertEquals('\A\FirstInterface', $output['methods']['methodFromFirstInterface']['declaringStructure']['name']);

        $this->assertEquals('\A\FirstInterface', $output['constants']['FIRST_INTERFACE_CONSTANT']['declaringClass']['name']);
        $this->assertEquals('\A\FirstInterface', $output['constants']['FIRST_INTERFACE_CONSTANT']['declaringStructure']['name']);
        $this->assertEquals('\A\TestClass', $output['methods']['methodFromFirstInterface']['declaringClass']['name']);
        $this->assertEquals('\A\FirstInterface', $output['methods']['methodFromFirstInterface']['declaringStructure']['name']);
    }

    public function testTraitUsageIsCorrectlyProcessed()
    {
        $fileName = 'TraitUsage.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');
        $baseClassOutput = $this->getClassInfo($fileName, 'A\BaseClass');

        $this->assertEquals(['\A\FirstTrait', '\A\SecondTrait', '\A\BaseTrait'], $output['traits']);
        $this->assertEquals(['\A\FirstTrait', '\A\SecondTrait'], $output['directTraits']);

        $this->assertThat($output['properties'], $this->arrayHasKey('baseTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('firstTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('secondTraitProperty'));

        $this->assertThat($output['methods'], $this->arrayHasKey('testAmbiguous'));
        $this->assertThat($output['methods'], $this->arrayHasKey('testAmbiguousAsWell'));
        $this->assertThat($output['methods'], $this->arrayHasKey('baseTraitMethod'));

        // Do a couple of sanity checks.
        $this->assertEquals('\A\BaseClass', $output['properties']['baseTraitProperty']['declaringClass']['name']);
        $this->assertEquals('\A\BaseClass', $output['methods']['baseTraitMethod']['declaringClass']['name']);

        $this->assertEquals('\A\BaseTrait', $output['properties']['baseTraitProperty']['declaringStructure']['name']);
        $this->assertEquals('\A\BaseTrait', $output['methods']['baseTraitMethod']['declaringStructure']['name']);

        // Test the 'as' keyword for renaming trait method.
        $this->assertThat($output['methods'], $this->arrayHasKey('test1'));
        $this->assertThat($output['methods'], $this->logicalNot($this->arrayHasKey('test')));

        $this->assertTrue($output['methods']['test1']['isPrivate']);
        $this->assertEquals($output['methods']['testAmbiguous']['declaringStructure']['name'], '\A\SecondTrait');
        $this->assertEquals($output['methods']['testAmbiguousAsWell']['declaringStructure']['name'], '\A\FirstTrait');
    }

    public function testSpecialTypesAreCorrectlyResolved()
    {
        $fileName = 'ResolveSpecialTypes.php.test';

        $output = $this->getClassInfo($fileName, 'A\childClass');

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['properties']['basePropSelf']['types']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['properties']['basePropStatic']['types']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['properties']['basePropThis']['types']);

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['properties']['propSelf']['types']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['properties']['propStatic']['types']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['properties']['propThis']['types']);

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['methods']['baseMethodSelf']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['baseMethodStatic']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['baseMethodThis']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['methodSelf']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['methodStatic']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['methodThis']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'childClass',
                'fqcn'         => '\A\childClass',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['methodOwnClassName']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['methods']['baseMethodWithParameters']['parameters'][0]['types']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['baseMethodWithParameters']['parameters'][1]['types']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\childClass'
            ]
        ], $output['methods']['baseMethodWithParameters']['parameters'][2]['types']);

        $output = $this->getClassInfo($fileName, 'A\ParentClass');

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['properties']['basePropSelf']['types']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['properties']['basePropStatic']['types']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['properties']['basePropThis']['types']);

        $this->assertEquals([
            [
                'type'         => 'self',
                'fqcn'         => 'self',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['methods']['baseMethodSelf']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => 'static',
                'fqcn'         => 'static',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['methods']['baseMethodStatic']['returnTypes']);

        $this->assertEquals([
            [
                'type'         => '$this',
                'fqcn'         => '$this',
                'resolvedType' => '\A\ParentClass'
            ]
        ], $output['methods']['baseMethodThis']['returnTypes']);
    }

    public function testMethodDocblockParameterTypesGetPrecedenceOverTypeHints()
    {
        $fileName = 'ClassMethodPrecedence.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals('string[]', $output['methods']['testMethod']['parameters'][0]['types'][0]['type']);
        $this->assertEquals('string[]', $output['methods']['testMethod']['parameters'][0]['types'][0]['fqcn']);
        $this->assertEquals('string', $output['methods']['testMethod']['parameters'][1]['types'][0]['type']);
        $this->assertEquals('string', $output['methods']['testMethod']['parameters'][1]['types'][0]['fqcn']);
    }

    public function testItemsWithoutDocblockAndDefaultValueHaveNoTypes()
    {
        $fileName = 'ClassMethodNoDocblock.php.test';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEmpty($output['methods']['testMethod']['parameters'][0]['types']);
        $this->assertEmpty($output['methods']['testMethod']['returnTypes']);
        $this->assertEmpty($output['properties']['testProperty']['types']);
    }

    public function testCorrectlyFindsClassesInNamelessNamespace()
    {
        $fileName = 'ClassNamelessNamespace.php.test';

        $output = $this->getClassInfo($fileName, 'TestClass');

        $this->assertEquals('\TestClass', $output['name']);
    }

    public function testCorrectlyAnalyzesBuiltinItems()
    {
        $output = $this->getBuiltinClassInfo('\IteratorAggregate');

        $this->assertArraySubset([
            'name'             => '\IteratorAggregate',
            'startLine'        => 0,
            'endLine'          => 0,
            'shortName'        => 'IteratorAggregate',
            'filename'         => null,
            'type'             => 'interface',
            'isAbstract'       => false,
            'isFinal'          => false,
            'isBuiltin'        => true,
            'isDeprecated'     => false,
            'isAnnotation'     => false,
            'hasDocblock'      => false,
            'hasDocumentation' => false,
            'shortDescription' => null,
            'longDescription'  => null,

            'parents'            => ['\Traversable'],
            'interfaces'         => [],
            'traits'             => [],
            'directParents'      => ['\Traversable'],
            'directInterfaces'   => [],
            'directTraits'       => [],
            'directChildren'     => [],
            'directImplementors' => ['\ArrayObject'],
            'directTraitUsers'   => [],
            'constants'          => [],
            'properties'         => []
        ], $output);

        $this->assertArraySubset([
            'name'               => 'getIterator',
            'fqcn'               => null,
            'isBuiltin'          => true,
            'startLine'          => 0,
            'endLine'            => 0,
            'filename'           => null,
            'parameters'         => [],
            'throws'             => [],
            'isDeprecated'       => false,
            'hasDocblock'        => false,
            'hasDocumentation'   => false,
            'returnTypeHint'     => null,

            'returnTypes'        => [
                [
                    'fqcn'         => '\Traversable',
                    'resolvedType' => '\Traversable',
                    'type'         => 'Traversable'
                ]
            ],

            'isMagic'            => false,
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => false,
            'isAbstract'         => true,
            'isFinal'            => false,
            'override'           => null,
            'implementation'     => null,

            'declaringClass' => [
                'name'      => '\IteratorAggregate',
                'filename'  => null,
                'startLine' => 0,
                'endLine'   => 0,
                'type'      => 'interface'
            ],

            'declaringStructure' => [
                'name'            => '\IteratorAggregate',
                'filename'        => null,
                'startLine'       => 0,
                'endLine'         => 0,
                'type'            => 'interface',
                'startLineMember' => 0,
                'endLineMember'   => 0
            ]
        ], $output['methods']['getIterator']);
    }

    /**
     * @expectedException \PhpIntegrator\Analysis\CircularDependencyException
     */
    public function testThrowsExceptionOnCircularDependencyWithClassExtendingItself()
    {
        $fileName = 'CircularDependencyExtends.php.test';

        $output = $this->getClassInfo($fileName, 'A\C');
    }

    /**
     * @expectedException \PhpIntegrator\Analysis\CircularDependencyException
     */
    public function testThrowsExceptionOnCircularDependencyWithClassImplementingItself()
    {
        $fileName = 'CircularDependencyImplements.php.test';

        $output = $this->getClassInfo($fileName, 'A\C');
    }

    /**
     * @expectedException \PhpIntegrator\Analysis\CircularDependencyException
     */
    public function testThrowsExceptionOnCircularDependencyWithClassUsingItselfAsTrait()
    {
        $fileName = 'CircularDependencyUses.php.test';

        $output = $this->getClassInfo($fileName, 'A\C');
    }
}
