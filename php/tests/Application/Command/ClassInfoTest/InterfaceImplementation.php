<?php

namespace A;

interface FirstInterface
{
    public function methodFromFirstInterface();
}

interface SecondInterface
{
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
