<?php

namespace A;

class Foo
{
    public function someMethod()
    {
        self::$test = 3;
    }
}

$foo = new Foo();
$foo->foo();
Foo::bar();
$foo->fooProp = 5;
Foo::$barProp = 5;
Foo::CONSTANT;

$bar = new \stdClass();
$bar->stdClassProp = 3;
$b = $bar->stdClassProp;
