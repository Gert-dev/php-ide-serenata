<?php

namespace A;

interface ParentInterface
{
    public function parentInterfaceMethod();
}

class ParentClass implements ParentInterface
{

}

interface TestInterface
{
    public function interfaceMethod();
}

class ChildClass extends ParentClass implements TestInterface
{
    public function parentInterfaceMethod()
    {

    }

    public function interfaceMethod()
    {

    }
}
