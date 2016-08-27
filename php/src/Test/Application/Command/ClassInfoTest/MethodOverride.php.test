<?php

namespace A;

trait ParentTrait
{
    protected function parentTraitMethod()
    {

    }
}

class ParentClass
{
    use ParentTrait;

    public function __construct()
    {

    }

    protected function parentMethod()
    {

    }
}

trait TestTrait
{
    public function traitMethod()
    {

    }

    abstract public function abstractMethod();
}

class ChildClass extends ParentClass
{
    use TestTrait;

    public function __construct(Foo $foo)
    {

    }

    protected function parentTraitMethod(Foo $foo = null)
    {

    }

    public function parentMethod(Foo $foo = null)
    {

    }

    protected function traitMethod(Foo $foo = null)
    {

    }

    public function abstractMethod(Foo $foo = null)
    {

    }
}
