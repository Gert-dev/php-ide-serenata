<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use PhpIntegrator\Analysis\Conversion\ConstantConverter;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Command that shows a list of global constants.
 */
class GlobalConstantsCommand extends AbstractCommand
{
    /**
     * @var ConstantConverter
     */
    protected $constantConverter;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @param ConstantConverter $constantConverter
     * @param IndexDatabase     $indexDatabase
     */
    public function __construct(ConstantConverter $constantConverter, IndexDatabase $indexDatabase)
    {
        $this->constantConverter = $constantConverter;
        $this->indexDatabase = $indexDatabase;
    }

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
            $constants[$constant['fqcn']] = $this->constantConverter->convert($constant);
        }

        return $constants;
    }
}
