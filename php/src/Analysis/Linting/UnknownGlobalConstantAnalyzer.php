<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\GlobalConstantUsageFetchingVisitor;

use PhpIntegrator\UserInterface\Command\GlobalConstantsCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

/**
 * Looks for unknown global constant names.
 */
class UnknownGlobalConstantAnalyzer implements AnalyzerInterface
{
    /**
     * @var GlobalConstantUsageFetchingVisitor
     */
    protected $globalConstantUsageFetchingVisitor;

    /**
     * @param GlobalConstantsCommand $globalConstantsCommand
     * @param TypeAnalyzer           $typeAnalyzer
     */
    public function __construct(GlobalConstantsCommand $globalConstantsCommand, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalConstantUsageFetchingVisitor = new GlobalConstantUsageFetchingVisitor(
            $globalConstantsCommand,
            $typeAnalyzer
        );
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->globalConstantUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        return $this->globalConstantUsageFetchingVisitor->getGlobalConstantCallList();
    }
}
