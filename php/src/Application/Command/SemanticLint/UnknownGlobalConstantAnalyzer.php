<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\GlobalConstants;

/**
 * Looks for unknown global constant names.
 */
class UnknownGlobalConstantAnalyzer implements AnalyzerInterface
{
    /**
     * @var Visitor\GlobalConstantUsageFetchingVisitor
     */
    protected $globalConstantUsageFetchingVisitor;

    /**
     * @param GlobalConstants $globalConstants
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalConstants $globalConstants, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalConstantUsageFetchingVisitor = new Visitor\GlobalConstantUsageFetchingVisitor(
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
