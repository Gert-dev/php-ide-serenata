<?php

namespace PhpIntegrator\Test\Analysis\Typing;

use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

class TypeResolverTest extends \PHPUnit_Framework_TestCase
{
    protected function getTypeAnalyzer()
    {
        return new TypeAnalyzer();
    }

    public function testEmptyTypeReturnsNull()
    {
        $object = new TypeResolver($this->getTypeAnalyzer(), null, []);

        $this->assertNull($object->resolve(null));
    }

    public function testTypeWithLeadingSlashIsNotResolved()
    {
        $object = new TypeResolver($this->getTypeAnalyzer(), null, []);

        $this->assertEquals('\A\B', $object->resolve('\A\B'));
    }

    public function testRelativeTypeIsRelativeToNamespace()
    {
        $object = new TypeResolver($this->getTypeAnalyzer(), null, []);

        $this->assertEquals('\A', $object->resolve('A'));

        $object = new TypeResolver($this->getTypeAnalyzer(), 'A', []);

        $this->assertEquals('\A\B', $object->resolve('B'));
    }

    public function testRelativeTypeIsRelativeToUseStatements()
    {
        $object = new TypeResolver($this->getTypeAnalyzer(), 'A', [
            [
                'fqcn' => 'B\C',
                'alias' => 'Alias'
            ],

            [
                'fqcn' => 'B\C\D',
                'alias' => 'D'
            ]
        ]);

        $this->assertEquals('\B\C', $object->resolve('Alias'));
        $this->assertEquals('\B\C\E', $object->resolve('Alias\E'));
        $this->assertEquals('\B\C\D', $object->resolve('D'));
        $this->assertEquals('\B\C\D\E', $object->resolve('D\E'));
    }
}
