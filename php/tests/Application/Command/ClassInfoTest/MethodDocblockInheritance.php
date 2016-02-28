<?php

namespace A;

class ParentClass
{
    /**
     * This is the summary.
     *
     * This is a long description.
     *
     * @param \DateTime $firstParameter  First parameter description.
     *
     * @throws \UnexpectedValueException when something goes wrong.
     * @throws \LogicException           when something is wrong.
     *
     * @deprecated
     *
     * @return mixed
     */
    protected function basicDocblockInheritanceBaseClassTest()
    {

    }

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected function inheritDocBaseClassTest()
    {

    }
}

interface TestInterface
{
    /**
     * This is the summary.
     *
     * This is a long description.
     */
    public function basicDocblockInheritanceInterfaceTest();

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected function inheritDocInterfaceTest()
    {

    }
}

trait TestTrait
{
    /**
     * This is the summary.
     *
     * This is a long description.
     */
    public function basicDocblockInheritanceTraitTest()
    {

    }

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected function inheritDocTraitTest()
    {

    }
}

class ChildClass extends ParentClass implements TestInterface
{
    use TestTrait;

    public function basicDocblockInheritanceTraitTest()
    {

    }

    public function basicDocblockInheritanceInterfaceTest()
    {

    }

    protected function basicDocblockInheritanceBaseClassTest()
    {

    }

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected function inheritDocBaseClassTest()
    {

    }

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected function inheritDocInterfaceTest()
    {

    }

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected function inheritDocTraitTest()
    {

    }
}
