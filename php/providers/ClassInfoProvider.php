<?php

namespace PhpIntegrator;

class ClassInfoProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($args = [])
    {
        $class = $args[0];

        return $this->getClassInfo($class);
    }
}
