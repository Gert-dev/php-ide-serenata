<?php

namespace A;

class Foo
{
    /**
     * @return Foo
     */
    public function foo() {}
}

class Bar
{
    public function test(Foo $foo1, Foo $foo2, Foo $foo3)
    {
        $foo1 = $foo1;
        $foo2 = $foo2->foo();
        $foo3 = $foo3->// <MARKER>
            shouldNotBeUsed();
    }
}
