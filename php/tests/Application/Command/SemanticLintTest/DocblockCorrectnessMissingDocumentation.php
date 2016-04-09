<?php

namespace A;

function some_function()
{

}

/**
 * Documentation.
 */
class Base
{
    /**
     * Documentation.
     */
    protected $someBaseClassProperty;

    /**
     * Documentation.
     */
    protected function someBaseClassMethod()
    {

    }
}

class C extends Base
{
    const SOME_CONST = 5;

    protected $someProperty;
    protected $someBaseClassProperty;

    protected function someBaseClassMethod()
    {

    }

    protected function someMethod()
    {

    }
}

class MissingDocumentation
{

}
