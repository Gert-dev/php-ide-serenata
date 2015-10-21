<?php

namespace PhpIntegrator;

class MethodsProvider extends Tools implements ProviderInterface
{
    /**
     * Execute the command
     * @param  array  $args Arguments gived to the command
     * @return array Response
     */
    public function execute($args = array())
    {
        $class = $args[0];

        return $this->getClassMetadata($class);
    }
}
