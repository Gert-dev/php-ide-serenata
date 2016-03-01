<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexedTest;

class ClassInfoTest extends IndexedTest
{
    protected function getClassInfo($file, $fqsen)
    {
        $path = $this->getPathFor($file);

        $indexDatabase = $this->getDatabaseForTestFile($path);

        $command = new ClassInfo();
        $command->setIndexDatabase($indexDatabase);

        return $command->getClassInfo($fqsen);
    }

    protected function getPathFor($file)
    {
        return __DIR__ . '/ClassInfoTest/' . $file;
    }

    /**
     * @expectedException \UnexpectedValueException
     */
    public function testFailsOnUnknownClass()
    {
        $output = $this->getClassInfo('SimpleClass.php', 'DoesNotExist');
    }

    public function testLeadingSlashIsResolvedCorrectly()
    {
        $fileName = 'SimpleClass.php';

        $this->assertEquals(
            $this->getClassInfo($fileName, 'A\SimpleClass'),
            $this->getClassInfo($fileName, '\A\SimpleClass')
        );
    }

    public function testDataIsCorrectForASimpleClass()
    {
        $fileName = 'SimpleClass.php';

        $output = $this->getClassInfo($fileName, 'A\SimpleClass');

        $this->assertEquals($output, [
            'name'               => 'A\SimpleClass',
            'startLine'          => 10,
            'endLine'            => 13,
            'shortName'          => 'SimpleClass',
            'filename'           => $this->getPathFor($fileName),
            'type'               => 'class',
            'isAbstract'         => false,
            'isBuiltin'          => false,
            'isDeprecated'       => false,

            'descriptions'       => [
                'short' => 'This is the summary.',
                'long'  => 'This is a long description.'
            ],

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

    public function testDataIsCorrectForClassProperties()
    {
        $fileName = 'ClassProperty.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['properties']['testProperty'], [
            'name'               => 'testProperty',
            'startLine'          => 14,
            'endLine'            => 14,
            'isMagic'            => false,
            'isPublic'           => false,
            'isProtected'        => true,
            'isPrivate'          => false,
            'isStatic'           => false,
            'isDeprecated'       => false,
            'hasDocblock'        => true,

            'descriptions'       => [
                'short' => 'This is the summary.',
                'long'  => 'This is a long description.'
            ],

            'return'             => [
                'type'         => 'MyType',
                'resolvedType' => 'A\MyType',
                'description'  => null
            ],

            'override'           => null,

            'declaringClass'     => [
                'name'      => 'A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 15,
                'type'            => 'class',
                'startLineMember' => 14,
                'endLineMember'   => 14
            ]
        ]);
    }

    public function testDataIsCorrectForClassMethods()
    {
        $fileName = 'ClassMethod.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['methods']['testMethod'], [
            'name'               => 'testMethod',
            'isBuiltin'          => false,
            'startLine'          => 19,
            'endLine'            => 22,
            'filename'           => $this->getPathFor($fileName),

            'parameters'         => [
                [
                    'name'        => 'firstParameter',
                    'type'        => 'DateTime',
                    'fullType'    => 'DateTime',
                    'description' => 'First parameter description.',
                    'isReference' => false,
                    'isVariadic'  => false,
                    'isOptional'  => false
                ],

                [
                    'name'        => 'secondParameter',
                    'type'        => null,
                    'fullType'    => null,
                    'description' => null,
                    'isReference' => true,
                    'isVariadic'  => false,
                    'isOptional'  => true
                ],

                [
                    'name'        => 'thirdParameter',
                    'type'        => null,
                    'fullType'    => null,
                    'description' => null,
                    'isReference' => false,
                    'isVariadic'  => true,
                    'isOptional'  => false
                ]
            ],

            'throws'             => [
                '\UnexpectedValueException' => 'when something goes wrong.',
                '\LogicException'           => 'when something is wrong.'
            ],

            'isDeprecated'       => false,
            'hasDocblock'        => true,

            'descriptions'       => [
                'short' => 'This is the summary.',
                'long'  => 'This is a long description.'
            ],

            'return'             => [
                'type'         => 'mixed',
                'resolvedType' => 'mixed',
                'description'  => null
            ],

            'isMagic'            => false,
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => false,
            'override'           => null,
            'implementation'     => null,

            'declaringClass'     => [
                'name'      => 'A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 23,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 23,
                'type'            => 'class',
                'startLineMember' => 19,
                'endLineMember'   => 22
            ]
        ]);
    }

    public function testDataIsCorrectForClassConstants()
    {
        $fileName = 'ClassConstant.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['constants']['TEST_CONSTANT'], [
            'name'               => 'TEST_CONSTANT',
            'isBuiltin'          => false,
            'startLine'          => 14,
            'endLine'            => 14,
            'filename'           => $this->getPathFor($fileName),
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => true,
            'isDeprecated'       => false,
            'hasDocblock'        => true,

            'descriptions'       => [
                'short' => 'This is the summary.',
                'long'  => 'This is a long description.'
            ],

            'return'             => [
                'type'         => 'MyType',
                'resolvedType' => 'A\MyType',
                'description'  => null
            ],

            'declaringClass'     => [
                'name'      => 'A\TestClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 15,
                'type'            => 'class',
                'startLineMember' => 14,
                'endLineMember'   => 14
            ]
        ]);
    }

    public function testDocblockInheritanceWorksProperlyForClasses()
    {
        $fileName = 'ClassDocblockInheritance.php';

        $childClassOutput = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');
        $anotherChildClassOutput = $this->getClassInfo($fileName, 'A\AnotherChildClass');

        $this->assertEquals($childClassOutput['descriptions'], [
            'short' => 'This is the summary.',
            'long'  => 'This is a long description.'
        ]);

        $this->assertEquals(
            $anotherChildClassOutput['descriptions']['long'],
            'Pre. ' . $parentClassOutput['descriptions']['long'] . ' Post.'
        );
    }

    public function testDocblockInheritanceWorksProperlyForMethods()
    {
        $fileName = 'MethodDocblockInheritance.php';

        $traitOutput       = $this->getClassInfo($fileName, 'A\TestTrait');
        $interfaceOutput   = $this->getClassInfo($fileName, 'A\TestInterface');
        $childClassOutput  = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');

        $keysToTestForEquality = [
            'isDeprecated',
            'descriptions',
            'return',
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
            $childClassOutput['methods']['inheritDocBaseClassTest']['descriptions']['long'],
            'Pre. ' . $parentClassOutput['methods']['inheritDocBaseClassTest']['descriptions']['long'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['methods']['inheritDocInterfaceTest']['descriptions']['long'],
            'Pre. ' . $interfaceOutput['methods']['inheritDocInterfaceTest']['descriptions']['long'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['methods']['inheritDocTraitTest']['descriptions']['long'],
            'Pre. ' . $traitOutput['methods']['inheritDocTraitTest']['descriptions']['long'] . ' Post.'
        );
    }

    public function testDocblockInheritanceWorksProperlyForProperties()
    {
        $fileName = 'PropertyDocblockInheritance.php';

        $traitOutput       = $this->getClassInfo($fileName, 'A\TestTrait');
        $childClassOutput  = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');

        $keysToTestForEquality = [
            'isDeprecated',
            'descriptions',
            'return'
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
            $childClassOutput['properties']['inheritDocBaseClassTest']['descriptions']['long'],
            'Pre. ' . $parentClassOutput['properties']['inheritDocBaseClassTest']['descriptions']['long'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['properties']['inheritDocTraitTest']['descriptions']['long'],
            'Pre. ' . $traitOutput['properties']['inheritDocTraitTest']['descriptions']['long'] . ' Post.'
        );
    }

    public function testMethodOverridingIsAnalyzedCorrectly()
    {
        $fileName = 'MethodOverride.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['methods']['parentTraitMethod']['override'], [
            'startLine' => 7,
            'endLine'   => 10,

            'declaringClass' => [
                'name'      => 'A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 13,
                'endLine'   => 21,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\ParentTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 11,
                'type'            => 'trait',
                'startLineMember' => 7,
                'endLineMember'   => 10
            ]
        ]);

        $this->assertEquals($output['methods']['parentMethod']['override'], [
            'startLine' => 17,
            'endLine'   => 20,

            'declaringClass' => [
                'name'      => 'A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 13,
                'endLine'   => 21,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\ParentClass',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 13,
                'endLine'         => 21,
                'type'            => 'class',
                'startLineMember' => 17,
                'endLineMember'   => 20
            ]
        ]);

        $this->assertEquals($output['methods']['traitMethod']['override'], [
            'startLine' => 25,
            'endLine'   => 28,

            'declaringClass' => [
                'name'      => 'A\ChildClass',
                'filename'  =>  $this->getPathFor($fileName),
                'startLine' => 31,
                'endLine'   => 49,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 23,
                'endLine'         => 29,
                'type'            => 'trait',
                'startLineMember' => 25,
                'endLineMember'   => 28
            ]
        ]);
    }

    public function testPropertyOverridingIsAnalyzedCorrectly()
    {
        $fileName = 'PropertyOverride.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['properties']['parentTraitProperty']['override'], [
            'startLine' => 7,
            'endLine'   => 7,

            'declaringClass' => [
                'name'      => 'A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 10,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\ParentTrait',
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
                'name'      => 'A\ParentClass',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 10,
                'endLine'   => 15,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\ParentClass',
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
        $fileName = 'MethodImplementation.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['methods']['parentInterfaceMethod']['implementation'], [
            'startLine' => 7,
            'endLine'   => 7,

            'declaringClass' => [
                'name'      => 'A\ParentInterface',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 5,
                'endLine'   => 8,
                'type'      => 'interface'
            ],

            'declaringStructure' => [
                'name'            => 'A\ParentInterface',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 5,
                'endLine'         => 8,
                'type'            => 'interface',
                'startLineMember' => 7,
                'endLineMember'   => 7
            ]
        ]);

        $this->assertEquals($output['methods']['interfaceMethod']['implementation'], [
            'startLine' => 17,
            'endLine'   => 17,

            'declaringClass' => [
                'name'      => 'A\TestInterface',
                'filename'  => $this->getPathFor($fileName),
                'startLine' => 15,
                'endLine'   => 18,
                'type'      => 'interface'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestInterface',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 15,
                'endLine'         => 18,
                'type'            => 'interface',
                'startLineMember' => 17,
                'endLineMember'   => 17
            ]
        ]);
    }

    public function testMethodParameterTypesFallBackToDocblock()
    {
        $fileName = 'MethodParameterDocblockFallBack.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');
        $parameters = $output['methods']['testMethod']['parameters'];

        $this->assertEquals($parameters[0]['type'], '\DateTime');
        $this->assertEquals($parameters[1]['type'], 'boolean');
        $this->assertEquals($parameters[2]['type'], 'mixed');
        $this->assertEquals($parameters[3]['type'], '\Traversable[]');
    }

    public function testMagicClassPropertiesArePickedUpCorrectly()
    {
        $fileName = 'MagicClassProperties.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $data = $output['properties']['prop1'];

        $this->assertEquals($data['name'], 'prop1');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['descriptions'], [
            'short' => 'Description 1.',
            'long'  => ''
        ]);

        $this->assertEquals($data['return'], [
            'type'         => null,
            'resolvedType' => null,
            'description'  => null
        ]);

        $data = $output['properties']['prop2'];

        $this->assertEquals($data['name'], 'prop2');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['descriptions'], [
            'short' => 'Description 2.',
            'long'  => ''
        ]);

        $this->assertEquals($data['return'], [
            'type'         => null,
            'resolvedType' => null,
            'description'  => null
        ]);

        $data = $output['properties']['prop3'];

        $this->assertEquals($data['name'], 'prop3');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['descriptions'], [
            'short' => 'Description 3.',
            'long'  => ''
        ]);

        $this->assertEquals($data['return'], [
            'type'         => null,
            'resolvedType' => null,
            'description'  => null
        ]);

        $data = $output['properties']['prop4'];

        $this->assertEquals($data['name'], 'prop4');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], true);

        $this->assertEquals($data['descriptions'], [
            'short' => 'Description 4.',
            'long'  => ''
        ]);

        $this->assertEquals($data['return'], [
            'type'         => null,
            'resolvedType' => null,
            'description'  => null
        ]);
    }

    public function testMagicClassMethodsArePickedUpCorrectly()
    {
        $fileName = 'MagicClassMethods.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $data = $output['methods']['magicFoo'];

        $this->assertEquals($data['name'], 'magicFoo');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['parameters'], []);

        $this->assertEquals($data['descriptions'], [
            'short' => null,
            'long'  => null
        ]);

        $this->assertEquals($data['return'], [
            'type'         => 'void',
            'resolvedType' => 'void',
            'description'  => null
        ]);

        $data = $output['methods']['someMethod'];

        $this->assertEquals($data['name'], 'someMethod');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['parameters'], [
            [
                'name'        => '$a,',
                'type'        => null,
                'fullType'    => null,
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ],

            // [
            //     'name'        => '$b,',
            //     'type'        => null,
            //     'fullType'    => null,
            //     'description' => null,
            //     'isReference' => false,
            //     'isVariadic'  => false,
            //     'isOptional'  => true
            // ],

            [
                'name'        => '$c',
                'type'        => 'array',
                'fullType'    => 'array',
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ],

            [
                'name'        => '$d',
                'type'        => '\DateTime',
                'fullType'    => '\DateTime',
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ]
        ]);

        $this->assertEquals($data['descriptions'], [
            'short' => "Description of method Test second line.",
            'long'  => ''
        ]);

        $this->assertEquals($data['return'], [
            'type'         => 'int',
            'resolvedType' => 'int',
            'description'  => null
        ]);

        $data = $output['methods']['magicFooStatic'];

        $this->assertEquals($data['name'], 'magicFooStatic');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 14);
        $this->assertEquals($data['hasDocblock'], true);
        $this->assertEquals($data['isStatic'], true);

        $this->assertEquals($data['parameters'], []);

        $this->assertEquals($data['descriptions'], [
            'short' => null,
            'long'  => null
        ]);

        $this->assertEquals($data['return'], [
            'type'         => 'void',
            'resolvedType' => 'void',
            'description'  => null
        ]);
    }

    public function testDataIsCorrectForClassInheritance()
    {
        $fileName = 'ClassInheritance.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['parents'], ['A\BaseClass', 'A\AncestorClass']);
        $this->assertEquals($output['directParents'], ['A\BaseClass']);

        $this->assertThat($output['constants'], $this->arrayHasKey('INHERITED_CONSTANT'));
        $this->assertThat($output['constants'], $this->arrayHasKey('CHILD_CONSTANT'));

        $this->assertThat($output['properties'], $this->arrayHasKey('inheritedProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('childProperty'));

        $this->assertThat($output['methods'], $this->arrayHasKey('inheritedMethod'));
        $this->assertThat($output['methods'], $this->arrayHasKey('childMethod'));

        $output = $this->getClassInfo($fileName, 'A\BaseClass');

        $this->assertEquals($output['directChildren'], ['A\ChildClass']);
        $this->assertEquals($output['parents'], ['A\AncestorClass']);
    }

    public function testInterfaceImplementationIsCorrectlyProcessed()
    {
        $fileName = 'InterfaceImplementation.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['interfaces'], ['A\BaseInterface', 'A\FirstInterface', 'A\SecondInterface']);
        $this->assertEquals($output['directInterfaces'], ['A\FirstInterface', 'A\SecondInterface']);

        $this->assertThat($output['constants'], $this->arrayHasKey('FIRST_INTERFACE_CONSTANT'));
        $this->assertThat($output['constants'], $this->arrayHasKey('SECOND_INTERFACE_CONSTANT'));

        $this->assertThat($output['methods'], $this->arrayHasKey('methodFromFirstInterface'));
        $this->assertThat($output['methods'], $this->arrayHasKey('methodFromSecondInterface'));
    }

    public function testTraitUsageIsCorrectlyProcessed()
    {
        $fileName = 'TraitUsage.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['traits'], ['A\BaseTrait', 'A\FirstTrait', 'A\SecondTrait']);
        $this->assertEquals($output['directTraits'], ['A\FirstTrait', 'A\SecondTrait']);

        $this->assertThat($output['properties'], $this->arrayHasKey('baseTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('firstTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('secondTraitProperty'));

        $this->assertThat($output['methods'], $this->arrayHasKey('testAmbiguous'));
        $this->assertThat($output['methods'], $this->arrayHasKey('baseTraitMethod'));

        // Test the 'as' keyword for renaming trait method.
        $this->assertThat($output['methods'], $this->arrayHasKey('test1'));
        $this->assertThat($output['methods'], $this->logicalNot($this->arrayHasKey('test')));

        $this->assertTrue($output['methods']['test1']['isPrivate']);
        $this->assertEquals($output['methods']['testAmbiguous']['declaringStructure']['name'], 'A\SecondTrait');
    }

    public function testSpecialTypesAreCorrectlyResolved()
    {
        $fileName = 'ResolveSpecialTypes.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['properties']['basePropSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['basePropStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['basePropThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['propSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['propStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['propThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['methodSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['methodStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['methodThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ChildClass',
            'description'  => null
        ]);

        $output = $this->getClassInfo($fileName, 'A\ParentClass');

        $this->assertEquals($output['properties']['basePropSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['basePropStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['properties']['basePropThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodSelf']['return'], [
            'type'         => 'self',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodStatic']['return'], [
            'type'         => 'static',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);

        $this->assertEquals($output['methods']['baseMethodThis']['return'], [
            'type'         => '$this',
            'resolvedType' => 'A\ParentClass',
            'description'  => null
        ]);
    }
}
