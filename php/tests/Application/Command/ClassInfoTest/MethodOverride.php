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

    protected function parentMethod()
    {

    }
}

trait TestTrait
{
    public function traitMethod()
    {

    }
}

class ChildClass extends ParentClass
{
    use TestTrait;

    protected function parentTraitMethod()
    {

    }

    public function parentMethod()
    {

    }

    protected function traitMethod()
    {

    }
}
