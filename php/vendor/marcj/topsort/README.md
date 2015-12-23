# Topological Sort / Dependency resolver in PHP

[![Build Status](https://travis-ci.org/marcj/topsort.php.svg)](https://travis-ci.org/marcj/topsort.php)
[![Code Climate](https://codeclimate.com/github/marcj/topsort.php/badges/gpa.svg?)](https://codeclimate.com/github/marcj/topsort.php)
[![Test Coverage](https://codeclimate.com/github/marcj/topsort.php/badges/coverage.svg?)](https://codeclimate.com/github/marcj/topsort.php)

This library provides several implementations of a Topological Sort (topSort).
In additional to the plain sorting algorithm it provides several implementations of a Grouped Topological Sort,
means you can pass items with a type which will be grouped together in the sorting. With its implementation
of using strings instead of arrays its over 20x faster than regular implementations.

## What is it?

A topological sort is useful for determining dependency loading. It tells you which elements need to be proceeded first
in order to fulfill all dependencies in the correct order.

Example usage: Unit of Work (relations), simple Package manager, Dependency Injection, ...

Examples:
 
```php
$sorter = new StringSort();

$sorter->add('car1', ['owner1', 'brand1']);
$sorter->add('brand1');
$sorter->add('brand2');
$sorter->add('owner1', ['brand1']);
$sorter->add('owner2', ['brand2']);

$result = $sorter->sort();
// output would be:
[
 'brand1',
 'owner1',
 'car1',
 'brand2',
 'owner2'
]
```

Sometimes you want to group equal types together (imagine a UnitOfWork which wants to combine all elements from the
same type to stored those in one batch):

```php
$sorter = new GroupedStringSort();

$sorter->add('car1', 'car', ['owner1', 'brand1']);
$sorter->add('brand1', 'brand');
$sorter->add('brand2', 'brand');
$sorter->add('owner1', 'user', ['brand1']);
$sorter->add('owner2', 'user', ['brand2']);

$result = $sorter->sort();
// output would be:
[
 'brand2',
 'brand1',
 'owner2',
 'owner1',
 'car1'
]

$groups = $sorter->getGroups();
[
   {type: 'brand', level: 0, position: 0, length: 2},
   {type: 'user', level: 1, position: 2, length: 2},
   {type: 'car', level: 2, position: 4, length: 1},
]
//of course there may be several groups with the same type, if the dependency graphs makes this necessary.

foreach ($groups as $group) {
   $firstItem = $result[$groups->position];
   $allItemsOfThisGroup = array_slice($result, $group->position, $group->length);
}
```

You can only store strings as elements.
To sort PHP objects you can stored its hash instead. `$sorter->add(spl_object_hash($obj1), [spl_object_hash($objt1Dep)])`. 

## Installation

Use composer package: [marcj/topsort)[https://packagist.org/packages/marcj/topsort]
```
{
    "require": {
        "marcj/topsort": "~0.1"
    }
}
```

```php
include 'vendor/autoload.php';

$sorter = new GroupedStringSort;
$sorter->ad(...);

$result = $sorter->sort();
```

## Implementations

tl;dr: Use `FixedArraySort` for normal topSort or `GroupedStringSort` for grouped topSort since its always the fastest
and has a good memory footprint.

### ArraySort

This is the most basic, most inefficient implementation of topSort using plain php arrays.

### FixedArraySort

This uses \SplFixedArray of php and is therefore much more memory friendly.

### StringSort

This uses a string as storage and has therefore no array overhead. It's thus a bit faster and has almost equal
memory footprint like FixedArraySort.
Small drawback: You can not store element ids containing a null byte.

### GroupedArraySort

This is the most basic, not so efficient implementation of grouped topSort using plain php arrays.

### GroupedFixedArraySort

This uses \SplFixedArray of php and is therefore much more memory friendly, but is extremely inefficient,
 because it burns your CPU since it has to shifts all array in php and not in c (like normal GroupedArraySort it does
 with `array_splice`).
  
### GroupedStringSort

This uses a string as storage and has therefore no array operations overhead. It's extremely faster than those implementations
above and has almost equal memory footprint like FixedArraySort.
Small drawback: You can not store element ids containing a null byte.

## Benchmarks

Test data: 1/3 has two edges, 1/3 has one edge and 1/3 has no edges. Use the `benchmark` command in `./bin/console`
to play with it.

### 50 elements

Implementation | Memory       | Duration
---------------|--------------|---------
FixedArraySort |       2,344b | 0.0005s 
ArraySort      |       6,728b | 0.0005s 
StringSort     |       2,008b | 0.0005s 


Implementation        | Memory       | Duration
----------------------|--------------|---------
GroupedFixedArraySort |       2,728b | 0.0013s 
GroupedArraySort      |      10,912b | 0.0010s 
GroupedStringSort     |       2,496b | 0.0010s 


### 1.000 elements

Implementation | Memory       | Duration
---------------|--------------|---------
FixedArraySort |       8,944b | 0.0098s 
ArraySort      |      98,208b | 0.0102s 
StringSort     |       9,224b | 0.0095s 


Implementation        | Memory       | Duration
----------------------|--------------|---------
GroupedFixedArraySort |      35,296b | 0.1467s 
GroupedArraySort      |     132,960b | 0.0497s 
GroupedStringSort     |      36,376b | 0.0248s 


### 10.000 elements

Implementation | Memory       | Duration
---------------|--------------|---------
FixedArraySort |      81,712b | 0.1146s 
ArraySort      |   1,014,000b | 0.1128s 
StringSort     |      91,088b | 0.1144s 


Implementation        | Memory       | Duration
----------------------|--------------|---------
GroupedFixedArraySort |     395,224b | 13.2805s
GroupedArraySort      |   1,454,832b | 5.5021s 
GroupedStringSort     |     391,704b | 0.2504s 


### 100.000 elements

`--` means took too long.

Implementation | Memory       | Duration
---------------|--------------|---------
FixedArraySort |     801,496b | 1.8707s 
ArraySort      |   9,850,048b | 1.9147s 
StringSort     |   1,001,176b | 1.7949s 


Implementation        | Memory       | Duration
----------------------|--------------|---------
GroupedFixedArraySort | --           | --      
GroupedArraySort      | --           | --      
GroupedStringSort     |  -3,795,944b | 6.6949s 

### 1.000.000 elements


Implementation | Memory       | Duration
---------------|--------------|---------
FixedArraySort |   7,995,152b | 86.6527s
ArraySort      |  96,386,112b | 97.5971s
StringSort     |  10,994,248b | 80.8750s
