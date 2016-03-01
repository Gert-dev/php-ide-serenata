<?php

namespace A;

trait FirstTrait
{
    const FIRST_TRAIT_CONSTANT = 1;

    protected $firstTraitProperty;

    protected function testAmbiguous()
    {

    }

    protected function test()
    {

    }
}

trait SecondTrait
{
    const SECOND_TRAIT_CONSTANT = 1;

    protected $secondTraitProperty;

    protected function testAmbiguous()
    {

    }
}

trait BaseTrait
{
    const BASE_TRAIT_CONSTANT = 3;

    protected $baseTraitProperty;

    public function baseTraitMethod()
    {

    }
}

class BaseClass
{
    use BaseTrait;
}

class TestClass extends BaseClass
{
    use FirstTrait, SecondTrait {
        test as private test1;
        SecondTrait::testAmbiguous insteadof testAmbiguous;
    }
}
