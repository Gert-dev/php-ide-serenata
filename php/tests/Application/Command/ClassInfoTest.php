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
        $fileName = 'AnnotationClass.php';

        $output = $this->getClassInfo($fileName, 'A\AnnotationClass');

        $this->assertTrue($output['isAnnotation']);
    }

    public function testDataIsCorrectForClassProperties()
    {
        $fileName = 'ClassProperty.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
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
            'hasDocumentation'   => true,
            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'returnDescription'  => null,

            'types'             => [
                [
                    'type'           => 'MyType',
                    'fqcn'           => 'A\MyType',
                    'referencedType' => null
                ]
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
        ], $output['properties']['testProperty']);
    }

    public function testDataIsCorrectForClassMethods()
    {
        $fileName = 'ClassMethod.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals([
            'name'               => 'testMethod',
            'fqsen'              => null,
            'isBuiltin'          => false,
            'startLine'          => 19,
            'endLine'            => 22,
            'filename'           => $this->getPathFor($fileName),

            'parameters'         => [
                [
                    'name'        => 'firstParameter',
                    'typeHint'    => '\DateTime',
                    'description' => 'First parameter description.',
                    'isReference' => false,
                    'isVariadic'  => false,
                    'isOptional'  => false,

                    'types'       => [
                        [
                            'type'           => '\DateTime',
                            'fqcn'           => '\DateTime',
                            'referencedType' => null
                        ]
                    ]
                ],

                [
                    'name'        => 'secondParameter',
                    'typeHint'    => null,
                    'description' => null,
                    'isReference' => true,
                    'isVariadic'  => false,
                    'isOptional'  => true,
                    'types'       => []
                ],

                [
                    'name'        => 'thirdParameter',
                    'typeHint'    => null,
                    'description' => null,
                    'isReference' => false,
                    'isVariadic'  => true,
                    'isOptional'  => false,
                    'types'       => []
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

            'returnTypes'             => [
                [
                    'type'           => 'mixed',
                    'fqcn'           => 'mixed',
                    'referencedType' => null
                ]
            ],

            'isMagic'            => false,
            'isPublic'           => true,
            'isProtected'        => false,
            'isPrivate'          => false,
            'isStatic'           => false,
            'isAbstract'         => false,
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
        ], $output['methods']['testMethod']);
    }

    public function testDataIsCorrectForClassConstants()
    {
        $fileName = 'ClassConstant.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals($output['constants']['TEST_CONSTANT'], [
            'name'               => 'TEST_CONSTANT',
            'fqsen'              => null,
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
            'hasDocumentation'   => true,

            'shortDescription'   => 'This is the summary.',
            'longDescription'    => 'This is a long description.',
            'returnDescription'  => null,

            'types'             => [
                [
                    'type'           => 'MyType',
                    'fqcn'           => 'A\MyType',
                    'referencedType' => null
                ]
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

        $this->assertEquals('This is the summary.', $childClassOutput['shortDescription']);
        $this->assertEquals('This is a long description.', $childClassOutput['longDescription']);

        $this->assertEquals(
            'Pre. ' . $parentClassOutput['longDescription'] . ' Post.',
            $anotherChildClassOutput['longDescription']
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
            $childClassOutput['methods']['inheritDocBaseClassTest']['longDescription'],
            'Pre. ' . $parentClassOutput['methods']['inheritDocBaseClassTest']['longDescription'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['methods']['inheritDocInterfaceTest']['longDescription'],
            'Pre. ' . $interfaceOutput['methods']['inheritDocInterfaceTest']['longDescription'] . ' Post.'
        );

        $this->assertEquals(
            $childClassOutput['methods']['inheritDocTraitTest']['longDescription'],
            'Pre. ' . $traitOutput['methods']['inheritDocTraitTest']['longDescription'] . ' Post.'
        );
    }

    public function testDocblockInheritanceWorksProperlyForProperties()
    {
        $fileName = 'PropertyDocblockInheritance.php';

        $traitOutput       = $this->getClassInfo($fileName, 'A\TestTrait');
        $childClassOutput  = $this->getClassInfo($fileName, 'A\ChildClass');
        $parentClassOutput = $this->getClassInfo($fileName, 'A\ParentClass');

        $keysToTestForEquality = [
            'hasDocumentation',
            'isDeprecated',
            'shortDescription',
            'longDescription',
            'returnDescription',
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
        $fileName = 'MethodOverride.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['methods']['parentTraitMethod']['override'], [
            'startLine'   => 7,
            'endLine'     => 10,
            'wasAbstract' => false,

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
            'startLine'   => 17,
            'endLine'     => 20,
            'wasAbstract' => false,

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
            'startLine'   => 25,
            'endLine'     => 28,
            'wasAbstract' => false,

            'declaringClass' => [
                'name'      => 'A\ChildClass',
                'filename'  =>  $this->getPathFor($fileName),
                'startLine' => 33,
                'endLine'   => 56,
                'type'      => 'class'
            ],

            'declaringStructure' => [
                'name'            => 'A\TestTrait',
                'filename'        => $this->getPathFor($fileName),
                'startLine'       => 23,
                'endLine'         => 31,
                'type'            => 'trait',
                'startLineMember' => 25,
                'endLineMember'   => 28
            ]
        ]);

        $this->assertEquals($output['methods']['abstractMethod']['override']['wasAbstract'], true);
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

        $this->assertEquals($parameters[0]['types'][0]['type'], '\DateTime');
        $this->assertEquals($parameters[1]['types'][0]['type'], 'boolean');
        $this->assertEquals($parameters[2]['types'][0]['type'], 'mixed');
        $this->assertEquals($parameters[3]['types'][0]['type'], '\Traversable[]');
    }

    public function testMagicClassPropertiesArePickedUpCorrectly()
    {
        $fileName = 'MagicClassProperties.php';

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
        $this->assertEquals($data['returnDescription'], null);

        $this->assertEquals($data['types'][0], [
            'type'           => 'Type1',
            'fqcn'           => 'A\Type1',
            'referencedType' => null
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

        $this->assertEquals($data['return'], [
            'type'         => 'Type2',
            'resolvedType' => 'A\Type2',
            'description'  => null
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

        $this->assertEquals($data['return'], [
            'type'         => 'Type3',
            'resolvedType' => 'A\Type3',
            'description'  => null
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

        $this->assertEquals($data['return'], [
            'type'         => 'Type4',
            'resolvedType' => 'A\Type4',
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
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['parameters'], []);

        $this->assertNull($data['shortDescription']);
        $this->assertNull($data['longDescription']);

        $this->assertEquals($data['return'], [
            'type'         => 'void',
            'typeHint'     => 'void',
            'resolvedType' => 'void',
            'description'  => null
        ]);

        $data = $output['methods']['someMethod'];

        $this->assertEquals($data['name'], 'someMethod');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], false);

        $this->assertEquals($data['parameters'], [
            [
                'name'        => 'a',
                'type'        => null,
                'typeHint'    => null,
                'fullType'    => null,
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => false
            ],

            [
                'name'        => 'b',
                'type'        => null,
                'typeHint'    => null,
                'fullType'    => null,
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => false
            ],

            [
                'name'        => 'c',
                'type'        => 'array',
                'typeHint'    => 'array',
                'fullType'    => 'array',
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ],

            [
                'name'        => 'd',
                'type'        => 'Type',
                'typeHint'    => 'Type',
                'fullType'    => 'A\Type',
                'description' => null,
                'isReference' => false,
                'isVariadic'  => false,
                'isOptional'  => true
            ]
        ]);

        $this->assertEquals($data['shortDescription'], 'Description of method Test second line.');
        $this->assertEquals($data['longDescription'], '');

        $this->assertEquals($data['return'], [
            'type'         => 'TestClass',
            'typeHint'     => 'TestClass',
            'resolvedType' => 'A\TestClass',
            'description'  => null
        ]);

        $data = $output['methods']['magicFooStatic'];

        $this->assertEquals($data['name'], 'magicFooStatic');
        $this->assertEquals($data['isMagic'], true);
        $this->assertEquals($data['startLine'], 11);
        $this->assertEquals($data['endLine'], 11);
        $this->assertEquals($data['hasDocblock'], false);
        $this->assertEquals($data['hasDocumentation'], false);
        $this->assertEquals($data['isStatic'], true);

        $this->assertEquals($data['parameters'], []);

        $this->assertNull($data['shortDescription']);
        $this->assertNull($data['longDescription']);

        $this->assertEquals($data['return'], [
            'type'         => 'void',
            'typeHint'     => 'void',
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
        $baseClassOutput = $this->getClassInfo($fileName, 'A\BaseClass');

        $this->assertEquals($output['traits'], ['A\BaseTrait', 'A\FirstTrait', 'A\SecondTrait']);
        $this->assertEquals($output['directTraits'], ['A\FirstTrait', 'A\SecondTrait']);

        $this->assertThat($output['properties'], $this->arrayHasKey('baseTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('firstTraitProperty'));
        $this->assertThat($output['properties'], $this->arrayHasKey('secondTraitProperty'));

        $this->assertThat($output['methods'], $this->arrayHasKey('testAmbiguous'));
        $this->assertThat($output['methods'], $this->arrayHasKey('testAmbiguousAsWell'));
        $this->assertThat($output['methods'], $this->arrayHasKey('baseTraitMethod'));

        $this->assertEquals(
            $output['properties']['inheritDocTest']['longDescription'],
            'Pre. ' . $baseClassOutput['properties']['inheritDocTest']['longDescription'] . ' Post.'
        );

        $this->assertEquals(
            $output['properties']['inheritEntireDocblockTest']['longDescription'],
            $baseClassOutput['properties']['inheritEntireDocblockTest']['longDescription']
        );

        // Test the 'as' keyword for renaming trait method.
        $this->assertThat($output['methods'], $this->arrayHasKey('test1'));
        $this->assertThat($output['methods'], $this->logicalNot($this->arrayHasKey('test')));

        $this->assertTrue($output['methods']['test1']['isPrivate']);
        $this->assertEquals($output['methods']['testAmbiguous']['declaringStructure']['name'], 'A\SecondTrait');
        $this->assertEquals($output['methods']['testAmbiguousAsWell']['declaringStructure']['name'], 'A\FirstTrait');
    }

    public function testSpecialTypesAreCorrectlyResolved()
    {
        $fileName = 'ResolveSpecialTypes.php';

        $output = $this->getClassInfo($fileName, 'A\childClass');

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'self',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['properties']['basePropSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\childClass'
            ]
        ], $output['properties']['basePropStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\childClass'
            ]
        ], $output['properties']['basePropThis']['types']);

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'self',
                'referencedType' => 'A\childClass'
            ]
        ], $output['properties']['propSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\childClass'
            ]
        ], $output['properties']['propStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\childClass'
            ]
        ], $output['properties']['propThis']['types']);

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'self',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['methods']['baseMethodSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\childClass'
            ]
        ], $output['methods']['baseMethodStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\childClass'
            ]
        ], $output['methods']['baseMethodThis']['types']);

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'self',
                'referencedType' => 'A\childClass'
            ]
        ], $output['methods']['methodSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\childClass'
            ]
        ], $output['methods']['methodStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\childClass'
            ]
        ], $output['methods']['methodThis']['types']);

        $this->assertEquals([
            [
                'type'           => 'childClass',
                'fqcn'           => 'A\childClass',
                'referencedType' => null
            ]
        ], $output['methods']['methodOwnClassName']['types']);

        $output = $this->getClassInfo($fileName, 'A\ParentClass');

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'A\ParentClass',
                'referencedType' => null
            ]
        ], $output['properties']['basePropSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['properties']['basePropStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['properties']['basePropThis']['types']);

        $this->assertEquals([
            [
                'type'           => 'self',
                'fqcn'           => 'self',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['methods']['baseMethodSelf']['types']);

        $this->assertEquals([
            [
                'type'           => 'static',
                'fqcn'           => 'static',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['methods']['baseMethodStatic']['types']);

        $this->assertEquals([
            [
                'type'           => '$this',
                'fqcn'           => '$this',
                'referencedType' => 'A\ParentClass'
            ]
        ], $output['methods']['baseMethodThis']['types']);
    }

    public function testMethodDocblockParameterTypesGetPrecedenceOverTypeHints()
    {
        $fileName = 'ClassMethodPrecedence.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertEquals('string[]', $output['methods']['testMethod']['parameters'][0]['type']);
        $this->assertEquals('string[]', $output['methods']['testMethod']['parameters'][0]['fullType']);
        $this->assertEquals('string', $output['methods']['testMethod']['parameters'][1]['type']);
        $this->assertEquals('string', $output['methods']['testMethod']['parameters'][1]['fullType']);
    }

    public function testMethodWithoutDocblockHasNullReturnType()
    {
        $fileName = 'ClassMethodNoDocblock.php';

        $output = $this->getClassInfo($fileName, 'A\TestClass');

        $this->assertNull($output['methods']['testMethod']['parameters'][0]['type']);
        $this->assertNull($output['methods']['testMethod']['parameters'][0]['fullType']);
        $this->assertNull($output['methods']['testMethod']['return']['type']);
        $this->assertNull($output['methods']['testMethod']['return']['resolvedType']);
        $this->assertNull($output['properties']['testProperty']['return']['type']);
        $this->assertNull($output['properties']['testProperty']['return']['resolvedType']);
        $this->assertNull($output['constants']['TEST_CONSTANT']['return']['type']);
        $this->assertNull($output['constants']['TEST_CONSTANT']['return']['resolvedType']);
    }

    /**
     * @expectedException \PhpIntegrator\IndexDataAdapter\CircularDependencyException
     */
    public function testThrowsExceptionOnCircularDependency()
    {
        $fileName = 'CircularDependency.php';

        $output = $this->getClassInfo($fileName, 'A\C');
    }
}
