<?php

namespace A;

class ParentClass
{
    /**
     * This is the summary.
     *
     * This is a long description.
     *
     * @deprecated
     *
     * @var mixed
     */
    protected $basicDocblockInheritanceBaseClassTest;

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected $inheritDocBaseClassTest;
}

trait TestTrait
{
    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected $basicDocblockInheritanceTraitTest;

    /**
     * This is the summary.
     *
     * This is a long description.
     */
    protected $inheritDocTraitTest;
}

class ChildClass extends ParentClass
{
    use TestTrait;

    protected $basicDocblockInheritanceTraitTest;
    protected $basicDocblockInheritanceBaseClassTest;

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected $inheritDocBaseClassTest;

    /**
     * This is the summary.
     *
     * Pre. {@inheritDoc} Post.
     */
    protected $inheritDocTraitTest;
}
