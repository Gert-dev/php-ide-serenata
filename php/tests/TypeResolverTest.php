<?php

namespace PhpIntegrator;

class TypeResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testEmptyTypeReturnsNull()
    {
        $object = new TypeResolver(null, []);

        $this->assertNull($object->resolve(null));
    }

    public function testTypeWithLeadingSlashIsNotResolved()
    {
        $object = new TypeResolver(null, []);

        $this->assertEquals('\A\B', $object->resolve('\A\B'));
    }

    public function testRelativeTypeIsRelativeToNamespace()
    {
        $object = new TypeResolver(null, []);

        $this->assertEquals('\A', $object->resolve('A'));

        $object = new TypeResolver('A', []);

        $this->assertEquals('\A\B', $object->resolve('B'));
    }

    public function testRelativeTypeIsRelativeToUseStatements()
    {
        $object = new TypeResolver('A', [
            [
                'fqsen' => 'B\C',
                'alias' => 'Alias'
            ],

            [
                'fqsen' => 'B\C\D',
                'alias' => 'D'
            ]
        ]);

        $this->assertEquals('\B\C', $object->resolve('Alias'));
        $this->assertEquals('\B\C\E', $object->resolve('Alias\E'));
        $this->assertEquals('\B\C\D', $object->resolve('D'));
        $this->assertEquals('\B\C\D\E', $object->resolve('D\E'));
    }
}
