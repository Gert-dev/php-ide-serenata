<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows a list of global constants.
 */
class GlobalConstants extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function process(ArrayAccess $arguments)
    {
        $constants = [];

        foreach ($this->indexDatabase->getGlobalConstants() as $constant) {
            $constants[$constant['name']] = $this->getIndexDataAdapter()->getConstantInfo($constant);
        }

        return $this->outputJson(true, $constants);
    }
}
