<?php

namespace PhpIntegrator\Analysis;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Checks if a classlike exists.
 */
class ClasslikeExistanceChecker implements ClasslikeExistanceCheckerInterface
{
    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var array
     */
    protected $classlikeFqcnMap;

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
    public function doesClassExist($fqcn)
    {
        $classlikeFqcnMap = $this->getClasslikeFqcnMap();

        return isset($classlikeFqcnMap[$fqcn]);
    }

    /**
     * @return array
     */
    protected function getClasslikeFqcnMap()
    {
        if ($this->classlikeFqcnMap === null) {
            $this->classlikeFqcnMap = [];

            foreach ($this->indexDatabase->getAllStructuresRawInfo(null) as $element) {
                $this->classlikeFqcnMap[$element['fqcn']] = true;
            }
        }

        return $this->classlikeFqcnMap;
    }
}
