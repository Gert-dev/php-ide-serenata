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

        $this->assertEquals($output['methods']['parentTraitMethod']['override']['declaringClass'], [
            'name'      => 'A\ParentClass',
            'filename'  => $this->getPathFor($fileName),
            'startLine' => 13,
            'endLine'   => 21,
            'type'      => 'class'
        ]);

        $this->assertEquals($output['methods']['parentTraitMethod']['override']['declaringStructure'], [
            'name'            => 'A\ParentTrait',
            'filename'        => $this->getPathFor($fileName),
            'startLine'       => 5,
            'endLine'         => 11,
            'type'            => 'trait',
            'startLineMember' => 7,
            'endLineMember'   => 10
        ]);

        $this->assertEquals($output['methods']['parentMethod']['override']['declaringClass'], [
            'name'      => 'A\ParentClass',
            'filename'  => $this->getPathFor($fileName),
            'startLine' => 13,
            'endLine'   => 21,
            'type'      => 'class',
        ]);

        $this->assertEquals($output['methods']['parentMethod']['override']['declaringStructure'], [
            'name'            => 'A\ParentClass',
            'filename'        => $this->getPathFor($fileName),
            'startLine'       => 13,
            'endLine'         => 21,
            'type'            => 'class',
            'startLineMember' => 17,
            'endLineMember'   => 20
        ]);

        $this->assertEquals($output['methods']['traitMethod']['override']['declaringClass'], [
            'name'      => 'A\ChildClass',
            'filename'  =>  $this->getPathFor($fileName),
            'startLine' => 31,
            'endLine'   => 49,
            'type'      => 'class'
        ]);

        $this->assertEquals($output['methods']['traitMethod']['override']['declaringStructure'], [
            'name'            => 'A\TestTrait',
            'filename'        => $this->getPathFor($fileName),
            'startLine'       => 23,
            'endLine'         => 29,
            'type'            => 'trait',
            'startLineMember' => 25,
            'endLineMember'   => 28
        ]);
    }

    public function testPropertyOverridingIsAnalyzedCorrectly()
    {
        $fileName = 'PropertyOverride.php';

        $output = $this->getClassInfo($fileName, 'A\ChildClass');

        $this->assertEquals($output['properties']['parentTraitProperty']['override']['declaringClass'], [
            'name'      => 'A\ParentClass',
            'filename'  => $this->getPathFor($fileName),
            'startLine' => 10,
            'endLine'   => 15,
            'type'      => 'class'
        ]);

        $this->assertEquals($output['properties']['parentTraitProperty']['override']['declaringStructure'], [
            'name'            => 'A\ParentTrait',
            'filename'        => $this->getPathFor($fileName),
            'startLine'       => 5,
            'endLine'         => 8,
            'type'            => 'trait',
            'startLineMember' => 7,
            'endLineMember'   => 7
        ]);

        $this->assertEquals($output['properties']['parentProperty']['override']['declaringClass'], [
            'name'      => 'A\ParentClass',
            'filename'  => $this->getPathFor($fileName),
            'startLine' => 10,
            'endLine'   => 15,
            'type'      => 'class',
        ]);

        $this->assertEquals($output['properties']['parentProperty']['override']['declaringStructure'], [
            'name'            => 'A\ParentClass',
            'filename'        => $this->getPathFor($fileName),
            'startLine'       => 10,
            'endLine'         => 15,
            'type'            => 'class',
            'startLineMember' => 14,
            'endLineMember'   => 14
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
        // TODO
    }

    public function testMagicClassPropertiesArePickedUpCorrectly()
    {
        // TODO
    }

    public function testMagicClassMethodsArePickedUpCorrectly()
    {
        // TODO
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
}
