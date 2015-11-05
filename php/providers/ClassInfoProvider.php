<?php

namespace PhpIntegrator;

class ClassInfoProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute(array $args = [])
    {
        $class = $args[0];

        return [
            'success' => true,
            'result'  => $this->getClassInfo($class)
        ];
    }
}
