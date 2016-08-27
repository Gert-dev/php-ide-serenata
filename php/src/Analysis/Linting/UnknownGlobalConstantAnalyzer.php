<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\GlobalConstantUsageFetchingVisitor;

use PhpIntegrator\Application\Command\GlobalConstants;

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
     * @param GlobalConstants $globalConstants
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalConstants $globalConstants, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalConstantUsageFetchingVisitor = new GlobalConstantUsageFetchingVisitor(
            $globalConstants,
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
