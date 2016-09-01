<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\GlobalConstantUsageFetchingVisitor;

use PhpIntegrator\UserInterface\Command\GlobalConstantsCommand;

/**
 * Looks for unknown global constant names.
 */
class UnknownGlobalConstantAnalyzer implements AnalyzerInterface
{
    /**
     * @var GlobalConstantsCommand
     */
    protected $globalConstantsCommand;

    /**
     * @var GlobalConstantUsageFetchingVisitor
     */
    protected $globalConstantUsageFetchingVisitor;

    /**
     * @param GlobalConstantsCommand $globalConstantsCommand
     */
    public function __construct(GlobalConstantsCommand $globalConstantsCommand)
    {
        $this->globalConstantsCommand = $globalConstantsCommand;

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
        $globalConstants = $this->globalConstantsCommand->getGlobalConstants();

        $detectedConstants = $this->globalConstantUsageFetchingVisitor->getGlobalConstantList();

        $unknownGlobalConstants = [];

        foreach ($detectedConstants as $detectedConstant) {
            if (!isset($globalConstants[$detectedConstant['name']])) {
                $unknownGlobalConstants[] = $detectedConstant;
            }
        }

        return $unknownGlobalConstants;
    }
}
