<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Analysis\Visiting\GlobalFunctionUsageFetchingVisitor;

use PhpIntegrator\Application\Command\GlobalFunctions;

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
     * @param GlobalFunctions $globalFunctions
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalFunctions $globalFunctions, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalFunctionUsageFetchingVisitor = new GlobalFunctionUsageFetchingVisitor(
            $globalFunctions,
            $typeAnalyzer
        );
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
        return $this->globalFunctionUsageFetchingVisitor->getGlobalFunctionCallList();
    }
}
