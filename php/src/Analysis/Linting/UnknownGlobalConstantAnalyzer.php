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
            if ($this->globalConstantExistanceChecker->doesGlobalConstantExist($globalConstant['name'])) {
                continue;
            } elseif ($globalConstant['isUnqualified']) {
                // Unqualified global constant calls, such as "PHP_EOL", could refer to "PHP_EOL" in the current
                // namespace (e.g. "\A\PHP_EOL") or, if not present in the current namespace, the root namespace
                // (e.g. "\PHP_EOL").
                $fqcnForCurrentNamespace = '\\' . $globalConstant['namespace'] . '\\' . $globalConstant['name'];

                if ($this->globalConstantExistanceChecker->doesGlobalConstantExist($fqcnForCurrentNamespace)) {
                    continue;
                }

                $fqcnForRootNamespace = '\\' . $globalConstant['name'];

                if ($this->globalConstantExistanceChecker->doesGlobalConstantExist($fqcnForRootNamespace)) {
                    continue;
                }
            }

            unset($globalConstant['namespace'], $globalConstant['isUnqualified']);

            $unknownGlobalConstants[] = $globalConstant;
        }

        return $unknownGlobalConstants;
    }
}
