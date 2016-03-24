<?php

/**
 * @return \DateTime
 */
function global_function()
{

}

class ParentClass
{
    /**
     * @var \\DateTime
     */
    public $testProperty;
}

class Bar extends ParentClass
{

}
