<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\GlobalFunctions;

/**
 * Looks for unknown global function names (i.e. used during calls).
 */
class UnknownGlobalFunctionAnalyzer implements AnalyzerInterface
{
    /**
     * @var Visitor\GlobalFunctionUsageFetchingVisitor
     */
    protected $globalFunctionUsageFetchingVisitor;

    /**
     * @param GlobalFunctions $globalFunctions
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalFunctions $globalFunctions, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalFunctionUsageFetchingVisitor = new Visitor\GlobalFunctionUsageFetchingVisitor(
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
