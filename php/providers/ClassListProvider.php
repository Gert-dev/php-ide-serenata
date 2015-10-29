<?php

namespace PhpIntegrator;

class ClassListProvider extends Tools implements ProviderInterface
{
    /**
     * {@inheritDoc}
     */
    public function execute($args = array())
    {
        $index = json_decode(file_get_contents(Config::get('indexClasses')), true);

        if ($index === false) {
            // The class map hasn't been generated yet, don't error out as it can take a while for it to be generated.
            return [];
        }

        return $index;
    }
}
