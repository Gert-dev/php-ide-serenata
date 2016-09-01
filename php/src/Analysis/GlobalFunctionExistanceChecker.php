<?php

namespace PhpIntegrator\Analysis;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Checks if a global function exists.
 */
class GlobalFunctionExistanceChecker implements GlobalFunctionExistanceCheckerInterface
{
    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var array
     */
    protected $globalFunctionsFqcnMap;

    /**
     * @param IndexDatabase $indexDatabase
     */
    public function __construct(IndexDatabase $indexDatabase)
    {
        $this->indexDatabase = $indexDatabase;
    }

    /**
     * @inheritDoc
     */
    public function doesGlobalFunctionExist($fqcn)
    {
        $globalFunctionsFqcnMap = $this->getGlobalFunctionsFqcnMap();

        return isset($globalFunctionsFqcnMap[$fqcn]);
    }

    /**
     * @return array
     */
    protected function getGlobalFunctionsFqcnMap()
    {
        if ($this->globalFunctionsFqcnMap === null) {
            $this->globalFunctionsFqcnMap = [];

            foreach ($this->indexDatabase->getGlobalFunctions() as $element) {
                $this->globalFunctionsFqcnMap[$element['fqcn']] = true;
            }
        }

        return $this->globalFunctionsFqcnMap;
    }
}
