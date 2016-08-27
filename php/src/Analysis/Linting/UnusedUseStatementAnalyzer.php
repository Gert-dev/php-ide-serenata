<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Visiting\ClassUsageFetchingVisitor;
use PhpIntegrator\Analysis\Visiting\UseStatementFetchingVisitor;
use PhpIntegrator\Analysis\Visiting\DocblockClassUsageFetchingVisitor;

use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

/**
 * Looks for unused use statements.
 */
class UnusedUseStatementAnalyzer implements AnalyzerInterface
{
    /**
     * @var ClassUsageFetchingVisitor
     */
    protected $classUsageFetchingVisitor;

    /**
     * @var UseStatementFetchingVisitor
     */
    protected $useStatementFetchingVisitor;

    /**
     * @var DocblockClassUsageFetchingVisitor
     */
    protected $docblockClassUsageFetchingVisitor;

    /**
     * Constructor.
     *
     * @param TypeAnalyzer   $typeAnalyzer
     * @param DocblockParser $docblockParser
     */
    public function __construct(TypeAnalyzer $typeAnalyzer, DocblockParser $docblockParser)
    {
        $this->classUsageFetchingVisitor = new ClassUsageFetchingVisitor();
        $this->useStatementFetchingVisitor = new UseStatementFetchingVisitor();
        $this->docblockClassUsageFetchingVisitor = new DocblockClassUsageFetchingVisitor($typeAnalyzer, $docblockParser);
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->classUsageFetchingVisitor,
            $this->useStatementFetchingVisitor,
            $this->docblockClassUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        // Cross-reference the found class names against the class map.
        $unknownClasses = [];
        $namespaces = $this->useStatementFetchingVisitor->getNamespaces();

        $classUsage = array_merge(
            $this->classUsageFetchingVisitor->getClassUsageList(),
            $this->docblockClassUsageFetchingVisitor->getClassUsageList()
        );

        foreach ($classUsage as $classUsage) {
            $relevantAlias = $classUsage['firstPart'];

            if (!$classUsage['isFullyQualified'] && isset($namespaces[$classUsage['namespace']]['useStatements'][$relevantAlias])) {
                // Mark the accompanying used statement, if any, as used.
                $namespaces[$classUsage['namespace']]['useStatements'][$relevantAlias]['used'] = true;
            }
        }

        $unusedUseStatements = [];

        foreach ($namespaces as $namespace => $namespaceData) {
            $useStatementMap = $namespaceData['useStatements'];

            foreach ($useStatementMap as $alias => $data) {
                if (!array_key_exists('used', $data) || !$data['used']) {
                    $unusedUseStatements[] = $data;
                }
            }
        }

        return $unusedUseStatements;
    }
}
