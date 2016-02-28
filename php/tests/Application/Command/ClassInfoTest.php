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
        // TODO: ClassDocblockInheritance.php
    }

    public function testDocblockInheritanceWorksProperlyForMethods()
    {
        // TODO: MethodDocblockInheritance.php
        // TODO: Test inheritance with trait, interface, and base class methods as well.
    }

    public function testDocblockInheritanceWorksProperlyForProperties()
    {
        // TODO: PropertyDocblockInheritance.php
        // TODO: Test inheritance with trait properties and base class properties as well.
    }

    public function testMethodOverridingIsAnalyzedCorrectly()
    {
        // TODO
        // TODO: Test declaringClass and declaringStructure.
        // TODO: Override base class method.
        // TODO: Override base class trait method.
        // TODO: Override own trait method.
    }

    public function testPropertyOverridingIsAnalyzedCorrectly()
    {
        // TODO
        // TODO: Test declaringClass and declaringStructure.
        // TODO: Override base class property.
        // TODO: Override base class trait property.
    }

    public function testMethodImplementationIsAnalyzedCorrectly()
    {
        // TODO
        // TODO: Test declaringClass and declaringStructure.
        // TODO: Implement interface method.
        // TODO: Implement base class interface method.
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
