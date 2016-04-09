<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use PhpIntegrator\IndexDatabase;

use PhpIntegrator\Application\Command\ResolveType;

/**
 * Looks for unknown class names.
 */
class UnknownClassAnalyzer implements AnalyzerInterface
{
    /**
     * @var Visitor\ClassUsageFetchingVisitor
     */
    protected $classUsageFetchingVisitor;

    /**
     * @var Visitor\DocblockClassUsageFetchingVisitor
     */
    protected $docblockClassUsageFetchingVisitor;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * Constructor.
     *
     * @param string        $file
     * @param IndexDatabase $indexDatabase
     */
    public function __construct($file, IndexDatabase $indexDatabase)
    {
        $this->file = $file;
        $this->indexDatabase = $indexDatabase;

        $this->classUsageFetchingVisitor = new Visitor\ClassUsageFetchingVisitor();
        $this->docblockClassUsageFetchingVisitor = new Visitor\DocblockClassUsageFetchingVisitor();
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->classUsageFetchingVisitor,
            $this->docblockClassUsageFetchingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        // Generate a class map for fast lookups.
        $classMap = [];

        foreach ($this->indexDatabase->getAllStructuresRawInfo(null) as $element) {
            $classMap[$element['fqsen']] = true;
        }

        // Cross-reference the found class names against the class map.
        $unknownClasses = [];

        $resolveTypeCommand = new ResolveType();
        $resolveTypeCommand->setIndexDatabase($this->indexDatabase);

        $classUsage = array_merge(
            $this->classUsageFetchingVisitor->getClassUsageList(),
            $this->docblockClassUsageFetchingVisitor->getClassUsageList()
        );

        foreach ($classUsage as $classUsage) {
            if ($classUsage['isFullyQualified']) {
                $fqsen = $classUsage['name'];
            } else {
                $fqsen = $resolveTypeCommand->resolveType(
                    $classUsage['name'],
                    $this->file,
                    $classUsage['line']
                );
            }

            if (!isset($classMap[$fqsen])) {
                unset($classUsage['line'], $classUsage['firstPart'], $classUsage['isFullyQualified']);

                $unknownClasses[] = $classUsage;
            }
        }

        return $unknownClasses;
    }
}
