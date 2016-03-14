<?php

namespace A;

class ParentClass
{
    /**
     * @var self
     */
    protected $basePropSelf;

    /**
     * @var static
     */
    protected $basePropStatic;

    /**
     * @var $this
     */
    protected $basePropThis;

    /**
     * @return self
     */
    public function baseMethodSelf()
    {

    }

    /**
     * @return static
     */
    public function baseMethodStatic()
    {

    }

    /**
     * @return $this
     */
    public function baseMethodThis()
    {

    }
}

// NOTE: Deliberately started with a lower case character here.
class childClass extends ParentClass
{
    /**
     * @var self
     */
    protected $propSelf;

    /**
     * @var static
     */
    protected $propStatic;

    /**
     * @var $this
     */
    protected $propThis;

    /**
     * @return self
     */
    public function methodSelf()
    {

    }

    /**
     * @return static
     */
    public function methodStatic()
    {

    }

    /**
     * @return $this
     */
    public function methodThis()
    {

    }

    /**
     * @return childClass
     */
    public function methodOwnClassName()
    {

    }
}
