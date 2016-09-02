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

        $unknownGlobalFunctions = [];

        foreach ($globalFunctions as $globalFunction) {
            if ($this->globalFunctionExistanceChecker->doesGlobalFunctionExist($globalFunction['name'])) {
                continue;
            } elseif ($globalFunction['isUnqualified']) {
                // Unqualified global function calls, such as "array_walk", could refer to "array_walk" in the current
                // namespace (e.g. "\A\array_walk") or, if not present in the current namespace, the root namespace
                // (e.g. "\array_walk").
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
