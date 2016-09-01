<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\GlobalConstantExistanceCheckerInterface;

use PhpIntegrator\Analysis\Visiting\GlobalConstantUsageFetchingVisitor;

/**
 * Looks for unknown global constant names.
 */
class UnknownGlobalConstantAnalyzer implements AnalyzerInterface
{
    /**
     * @var GlobalConstantExistanceCheckerInterface
     */
    protected $globalConstantExistanceChecker;

    /**
     * @var GlobalConstantUsageFetchingVisitor
     */
    protected $globalConstantUsageFetchingVisitor;

    /**
     * @param GlobalConstantExistanceCheckerInterface $globalConstantExistanceChecker
     */
    public function __construct(GlobalConstantExistanceCheckerInterface $globalConstantExistanceChecker)
    {
        $this->globalConstantExistanceChecker = $globalConstantExistanceChecker;

        $this->globalConstantUsageFetchingVisitor = new GlobalConstantUsageFetchingVisitor();
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
        $globalConstants = $this->globalConstantUsageFetchingVisitor->getGlobalConstantList();

        $unknownGlobalConstants = [];

        foreach ($globalConstants as $globalConstant) {
            if (!$this->globalConstantExistanceChecker->doesGlobalConstantExist($globalConstant['name'])) {
                $unknownGlobalConstants[] = $globalConstant;
            }
        }

        return $unknownGlobalConstants;
    }
}
