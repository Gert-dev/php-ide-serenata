<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\GlobalFunctionExistanceCheckerInterface;

use PhpIntegrator\Analysis\Visiting\GlobalFunctionUsageFetchingVisitor;

/**
 * Looks for unknown global function names (i.e. used during calls).
 */
class UnknownGlobalFunctionAnalyzer implements AnalyzerInterface
{
    /**
     * @var GlobalFunctionUsageFetchingVisitor
     */
    protected $globalFunctionUsageFetchingVisitor;

    /**
     * @var GlobalFunctionExistanceCheckerInterface
     */
    protected $globalFunctionExistanceChecker;

    /**
     * @param GlobalFunctionExistanceCheckerInterface $globalFunctionExistanceChecker
     */
    public function __construct(GlobalFunctionExistanceCheckerInterface $globalFunctionExistanceChecker)
    {
        $this->globalFunctionExistanceChecker = $globalFunctionExistanceChecker;

        $this->globalFunctionUsageFetchingVisitor = new GlobalFunctionUsageFetchingVisitor();
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->globalFunctionUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        $globalFunctions = $this->globalFunctionUsageFetchingVisitor->getGlobalFunctionCallList();

        // die(var_dump(__FILE__ . ':' . __LINE__, $globalFunctions));

        $unknownGlobalFunctions = [];

        foreach ($globalFunctions as $globalFunction) {
            if ($this->globalFunctionExistanceChecker->doesGlobalFunctionExist($globalFunction['name'])) {
                continue;
            } elseif ($globalFunction['isUnqualified']) {
                $fqcnForCurrentNamespace = $globalFunction['namespace'] . '\\' . $globalFunction['name'];

                if ($this->globalFunctionExistanceChecker->doesGlobalFunctionExist($fqcnForCurrentNamespace)) {
                    continue;
                }

                $fqcnForRootNamespace = '\\' . $globalFunction['name'];

                if ($this->globalFunctionExistanceChecker->doesGlobalFunctionExist($fqcnForRootNamespace)) {
                    continue;
                }
            }

            unset($globalFunction['namespace'], $globalFunction['isUnqualified']);

            $unknownGlobalFunctions[] = $globalFunction;
        }

        return $unknownGlobalFunctions;
    }
}
