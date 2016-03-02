<?php

namespace A;

trait FirstTrait
{
    protected $firstTraitProperty;

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected $inheritDocTest;

    protected function testAmbiguous()
    {

    }

    protected function test()
    {

    }
}

trait SecondTrait
{
    protected $secondTraitProperty;

    protected function testAmbiguous()
    {

    }
}

trait BaseTrait
{
    protected $baseTraitProperty;

    public function baseTraitMethod()
    {

    }
}

class BaseClass
{
    use BaseTrait;

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected $inheritDocTest;
}

class TestClass extends BaseClass
{
    use FirstTrait, SecondTrait {
        test as private test1;
        SecondTrait::testAmbiguous insteadof testAmbiguous;
    }
}
