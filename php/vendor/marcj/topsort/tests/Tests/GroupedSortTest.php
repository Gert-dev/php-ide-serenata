<?php

namespace MJS\TopSort\Tests;

use MJS\TopSort\CircularDependencyException;
use MJS\TopSort\ElementNotFoundException;
use MJS\TopSort\GroupedTopSortInterface;
use MJS\TopSort\Implementations\GroupedArraySort;
use MJS\TopSort\Implementations\GroupedFixedArraySort;
use MJS\TopSort\Implementations\GroupedStringSort;

class GroupedSortTest extends \PHPUnit_Framework_TestCase
{

    public function provideImplementations()
    {
        return [
            [new GroupedArraySort()],
            [new GroupedStringSort()],
            [new GroupedFixedArraySort()]
        ];
    }

    /**
     * @dataProvider             provideImplementations
     * @expectedException        \MJS\TopSort\CircularDependencyException
     * @expectedExceptionMessage Circular dependency found: car1->owner1->car1
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testCircular(GroupedTopSortInterface $sorter)
    {
        $sorter->add('car1', 'bar', ['owner1']);
        $sorter->add('owner1', 'owner', ['car1']);
        $sorter->sort();
    }

    /**
     * @dataProvider             provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testDisabledCircularException(GroupedTopSortInterface $sorter)
    {
        $sorter->setThrowCircularDependency(false);
        $sorter->add('car1', ['owner1']);
        $sorter->add('owner1', ['car1']);
        $result = $sorter->sort();

        $this->assertEquals(['car1', 'owner1'], $result);
    }

    /**
     * @dataProvider             provideImplementations
     * @expectedException        \MJS\TopSort\ElementNotFoundException
     * @expectedExceptionMessage Dependency `car2` not found, required by `owner1`
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testNotFound(GroupedTopSortInterface $sorter)
    {
        $sorter->setThrowCircularDependency(true);
        $sorter->add('car1', 'car', ['owner1']);
        $sorter->add('owner1', 'owner', ['car2']);
        $sorter->sort();
    }

    /**
     * @dataProvider             provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testNotCircularException(GroupedTopSortInterface $sorter)
    {
        $sorter->setThrowCircularDependency(true);
        $sorter->add('car1', 'car', ['owner1']);
        $sorter->add('owner1', 'owner', ['brand1']);
        $sorter->add('brand1', 'brand', ['car1']);

        try {
            $sorter->sort();
            $this->fail('This must fail');
        } catch( CircularDependencyException $e ) {
            $this->assertEquals(['car1', 'owner1', 'brand1'], $e->getNodes());
            $this->assertEquals('car1', $e->getStart());
            $this->assertEquals('brand1', $e->getEnd());
        }
    }

    public function testConstructor()
    {
        $elements = [
            'car1' => ['car', ['brand1']],
            'car2' => ['car', ['brand2']],
            'brand1' => ['brand'],
            'brand2' => ['brand']
        ];
        $sorter = new GroupedArraySort($elements, true);
        $this->assertTrue($sorter->isThrowCircularDependency());
        $this->assertEquals(['brand1', 'brand2', 'car1', 'car2'], $sorter->sort());
    }

    /**
     * @dataProvider             provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testNotFoundException(GroupedTopSortInterface $sorter)
    {
        $sorter->setThrowCircularDependency(true);
        $sorter->add('car1', 'car', ['owner1']);
        $sorter->add('owner1', 'owner', ['car2']);

        $this->assertEquals(true, $sorter->isThrowCircularDependency());

        try {
            $sorter->sort();
            $this->fail('This must fail');
        } catch( ElementNotFoundException $e ) {
            $this->assertEquals('owner1', $e->getSource());
            $this->assertEquals('car2', $e->getTarget());
        }
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementationsSimple2(GroupedTopSortInterface $sorter)
    {
        $sorter->add('car1', 'car', ['brand1']);
        $sorter->add('brand1', 'brand');
        $sorter->add('car2', 'car');
        $sorter->add('brand2', 'brand', ['car2']);

        $result = $sorter->sort();
        $expected = explode(', ', 'brand1, car1, car2, brand2');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementationsGetGroups(GroupedTopSortInterface $sorter)
    {
        $sorter->add('car1', 'car', ['owner1', 'brand1']);
        $sorter->add('brand1', 'brand');
        $sorter->add('brand2', 'brand');
        $sorter->add('owner1', 'user', ['brand1']);
        $sorter->add('owner2', 'user', ['brand2']);

        $result = $sorter->sort();

        $expected = explode(', ', 'brand1, brand2, owner1, owner2, car1');
        $this->assertEquals($expected, $result);

        $groups = $sorter->getGroups();

        $this->assertEquals(
            [
                'type' => 'brand',
                'level' => 0,
                'position' => 0,
                'length' => 2
            ],
            (array)$groups[0]
        );

        $this->assertEquals(
            [
                'type' => 'user',
                'level' => 1,
                'position' => 2,
                'length' => 2
            ],
            (array)$groups[1]
        );

        $this->assertEquals(
            [
                'type' => 'car',
                'level' => 2,
                'position' => 4,
                'length' => 1
            ],
            (array)$groups[2]
        );

        $this->assertEquals('brand1', $result[$groups[0]->position]);
        $this->assertEquals('brand2', $result[$groups[0]->position + 1]);
        $this->assertEquals('owner1', $result[$groups[1]->position]);
        $this->assertEquals('owner2', $result[$groups[1]->position + 1]);
        $this->assertEquals('car1', $result[$groups[2]->position]);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementationsSimpleDoc(GroupedTopSortInterface $sorter)
    {
        $sorter->add('car1', 'car', ['owner1', 'brand1']);
        $sorter->add('brand1', 'brand');
        $sorter->add('brand2', 'brand');
        $sorter->add('owner1', 'user', ['brand1']);
        $sorter->add('owner2', 'user', ['brand2']);

        $result = $sorter->sort();

        $expected = explode(', ', 'brand1, brand2, owner1, owner2, car1');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementationsSimple(GroupedTopSortInterface $sorter)
    {
        $sorter->add('car1', 'car', ['brand1']);
        $sorter->add('owner1', 'owner', ['car1', 'brand1']);
        $sorter->add('owner2', 'owner', ['car2', 'brand1']);
        $sorter->add('car2', 'car', ['brand2']);
        $sorter->add('brand1', 'brand');
        $sorter->add('brand2', 'brand');

        $result = $sorter->sort();

        $expected = explode(', ', 'brand1, brand2, car1, car2, owner1, owner2');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementations(GroupedTopSortInterface $sorter)
    {
        for ($i = 0; $i < 3; $i++) {
            $sorter->add('car' . $i, 'car', ['owner' . $i, 'brand' . $i]);
            $sorter->add('owner' . $i, 'owner', ['brand' . $i]);
            $sorter->add('brand' . $i, 'brand');
        }

        $result = $sorter->sort();

        $expected = explode(', ', 'brand0, brand1, brand2, owner0, owner1, owner2, car0, car1, car2');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementations2(GroupedTopSortInterface $sorter)
    {
        for ($i = 0; $i < 3; $i++) {
            $sorter->add('brand' . $i, 'brand');
            $sorter->add('car' . $i, 'car', ['owner' . $i, 'brand' . $i]);
            $sorter->add('owner' . $i, 'owner', ['brand' . $i]);
        }

        $result = $sorter->sort();

        $expected = explode(', ', 'brand0, brand1, brand2, owner0, owner1, owner2, car0, car1, car2');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementations3(GroupedTopSortInterface $sorter)
    {
        for ($i = 0; $i < 3; $i++) {
            $sorter->add('brand' . $i, 'brand');
            $sorter->add('owner' . $i, 'owner', ['brand' . $i]);
            $sorter->add('car' . $i, 'car', ['owner' . $i, 'brand' . $i]);
        }

        $result = $sorter->sort();

        $expected = explode(', ', 'brand0, brand1, brand2, owner0, owner1, owner2, car0, car1, car2');
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider provideImplementations
     *
     * @param GroupedTopSortInterface $sorter
     */
    public function testImplementations4(GroupedTopSortInterface $sorter)
    {
        for ($i = 0; $i < 3; $i++) {
            $sorter->add('owner' . $i, 'owner', ['brand' . $i]);
            $sorter->add('brand' . $i, 'brand');
            $sorter->add('car' . $i, 'car', ['owner' . $i, 'brand' . $i]);
        }

        $result = $sorter->sort();

        $expected = explode(', ', 'brand0, brand1, brand2, owner0, owner1, owner2, car0, car1, car2');
        $this->assertEquals($expected, $result);
    }
}