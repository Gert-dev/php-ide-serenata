<?php

namespace A;

interface FirstInterface
{
    const FIRST_INTERFACE_CONSTANT = 1;

    public function methodFromFirstInterface();
}

interface SecondInterface
{
    const SECOND_INTERFACE_CONSTANT = 2;

    public function methodFromSecondInterface();
}

interface BaseInterface
{
    
}

class BaseClass implements BaseInterface
{

}

class TestClass extends BaseClass implements FirstInterface, SecondInterface
{

}
