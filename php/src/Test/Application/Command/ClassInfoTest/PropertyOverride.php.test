<?php

namespace A;

trait ParentTrait
{
    protected $parentTraitProperty;
}

class ParentClass
{
    use ParentTrait;

    protected $parentProperty;
}

class ChildClass extends ParentClass
{
    use TestTrait;

    protected $parentTraitProperty;
    protected $parentProperty;
}
