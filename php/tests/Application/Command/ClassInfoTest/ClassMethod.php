<?php

namespace A;

class TestClass
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
     * @return self
     */
    public function testMethod(\DateTime $firstParameter, &$secondParameter = true, ...$thirdParameter)
    {
        // NOTE: The second and third parameter descriptions are intentionally missing.
    }
}
