<?php

namespace A;

class Foo
{

}

$foo = new Foo();
$foo->foo();
Foo::bar();
$foo->fooProp = 5;
Foo::$barProp = 5;
Foo::CONSTANT;
