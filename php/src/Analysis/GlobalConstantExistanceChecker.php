<?php

namespace PhpIntegrator\Analysis;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Checks if a global constant exists.
 */
class GlobalConstantExistanceChecker implements GlobalConstantExistanceCheckerInterface
{
    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var array
     */
    protected $globalConstantFqcnMap;

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
    public function doesGlobalConstantExist($fqcn)
    {
        $globalConstantFqcnMap = $this->getGlobalConstantFqcnMap();

        return isset($globalConstantFqcnMap[$fqcn]);
    }

    /**
     * @return array
     */
    protected function getGlobalConstantFqcnMap()
    {
        if ($this->globalConstantFqcnMap === null) {
            $this->globalConstantFqcnMap = [];

            foreach ($this->indexDatabase->getGlobalConstants() as $element) {
                $this->globalConstantFqcnMap[$element['fqcn']] = true;
            }
        }

        return $this->globalConstantFqcnMap;
    }
}
