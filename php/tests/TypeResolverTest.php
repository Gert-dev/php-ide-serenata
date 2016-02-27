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

        $this->assertEquals($object->resolve('\A\B'), '\A\B');
    }

    public function testRelativeTypeIsRelativeToNamespace()
    {
        $object = new TypeResolver(null, []);

        $this->assertEquals($object->resolve('A'), 'A');

        $object = new TypeResolver('A', []);

        $this->assertEquals($object->resolve('B'), 'A\B');
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

        $this->assertEquals($object->resolve('Alias'), 'B\C');
        $this->assertEquals($object->resolve('Alias\E'), 'B\C\E');
        $this->assertEquals($object->resolve('D'), 'B\C\D');
        $this->assertEquals($object->resolve('D\E'), 'B\C\D\E');
    }
}
