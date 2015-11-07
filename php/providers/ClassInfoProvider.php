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

        if (mb_strpos($class, '\\') === 0) {
            $class = substr($class, 1);
        }

        return [
            'success' => true,
            'result'  => $this->getClassInfo($class)
        ];
    }
}
