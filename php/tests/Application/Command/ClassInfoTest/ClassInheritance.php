<?php

namespace A;

class AncestorClass
{

}

class BaseClass extends AncestorClass
{
    const INHERITED_CONSTANT = 5;

    protected $inheritedProperty;

    protected function inheritedMethod()
    {

    }
}

class ChildClass extends BaseClass
{
    const CHILD_CONSTANT = 3;

    protected $childProperty;

    protected function childMethod()
    {

    }
}
