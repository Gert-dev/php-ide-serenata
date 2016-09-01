<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\GlobalFunctionUsageFetchingVisitor;

use PhpIntegrator\UserInterface\Command\GlobalFunctionsCommand;

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
     * @var GlobalFunctionsCommand
     */
    protected $globalFunctionsCommand;

    /**
     * @param GlobalFunctionsCommand $globalFunctions
     */
    public function __construct(GlobalFunctionsCommand $globalFunctionsCommand)
    {
        $this->globalFunctionsCommand = $globalFunctionsCommand;

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
        $globalFunctions = $this->globalFunctionsCommand->getGlobalFunctions();

        $detectedFunctions = $this->globalFunctionUsageFetchingVisitor->getGlobalFunctionCallList();

        $unknownGlobalFunctions = [];

        foreach ($detectedFunctions as $detectedFunction) {
            if (!isset($globalFunctions[$detectedFunction['name']])) {
                $unknownGlobalFunctions[] = $detectedFunction;
            }
        }

        return $unknownGlobalFunctions;
    }
}
