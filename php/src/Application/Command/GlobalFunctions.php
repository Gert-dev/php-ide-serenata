<?php

namespace PhpIntegrator\Application\Command;

use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that shows a list of global functions.
 */
class GlobalFunctions extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function process(array $arguments)
    {
        $result = [];

        foreach ($this->indexDatabase->getGlobalFunctions() as $function) {
            $result[$function['name']] = $this->getIndexDataAdapter()->getFunctionInfo($function);
        }

        return $this->outputJson(true, $result);
    }
}
