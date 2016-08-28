<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\GlobalFunctionUsageFetchingVisitor;

use PhpIntegrator\Application\Command\GlobalFunctionsCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

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
    public function __construct(GlobalFunctionsCommand $globalFunctionsCommand, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalFunctionUsageFetchingVisitor = new GlobalFunctionUsageFetchingVisitor(
            $globalFunctionsCommand,
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
