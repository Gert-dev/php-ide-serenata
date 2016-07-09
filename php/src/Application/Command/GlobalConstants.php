<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;

use PhpIntegrator\IndexDataAdapter;

/**
 * Command that shows a list of global constants.
 */
class GlobalConstants extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        $constants = $this->getGlobalConstants();

        return $this->outputJson(true, $constants);
    }

    /**
     * @return array
     */
    public function getGlobalConstants()
    {
        $constants = [];

        foreach ($this->indexDatabase->getGlobalConstants() as $constant) {
            $constants[$constant['fqcn']] = $this->getIndexDataAdapter()->getConstantInfo($constant);
        }

        return $constants;
    }
}
