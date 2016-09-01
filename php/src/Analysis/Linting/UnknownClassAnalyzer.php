<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Analysis\Visiting\ClassUsageFetchingVisitor;
use PhpIntegrator\Analysis\Visiting\DocblockClassUsageFetchingVisitor;

use PhpIntegrator\UserInterface\Command\ResolveTypeCommand;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\Parsing\DocblockParser;

/**
 * Looks for unknown class names.
 */
class UnknownClassAnalyzer implements AnalyzerInterface
{
    /**
     * @var ClassUsageFetchingVisitor
     */
    protected $classUsageFetchingVisitor;

    /**
     * @var DocblockClassUsageFetchingVisitor
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
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var ResolveTypeCommand
     */
    protected $resolveTypeCommand;

    /**
     * Constructor.
     *
     * @param string             $file
     * @param IndexDatabase      $indexDatabase
     * @param ResolveTypeCommand $resolveTypeCommand
     * @param TypeAnalyzer       $typeAnalyzer
     * @param DocblockParser     $docblockParser
     */
    public function __construct(
        $file,
        IndexDatabase $indexDatabase,
        ResolveTypeCommand $resolveTypeCommand,
        TypeAnalyzer $typeAnalyzer,
        DocblockParser $docblockParser
    ) {
        $this->file = $file;
        $this->typeAnalyzer = $typeAnalyzer;
        $this->resolveTypeCommand = $resolveTypeCommand;
        $this->indexDatabase = $indexDatabase;

        $this->classUsageFetchingVisitor = new ClassUsageFetchingVisitor($typeAnalyzer);
        $this->docblockClassUsageFetchingVisitor = new DocblockClassUsageFetchingVisitor($typeAnalyzer, $docblockParser);
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
            $classMap[$element['fqcn']] = true;
        }

        // Cross-reference the found class names against the class map.
        $unknownClasses = [];

        $classUsages = array_merge(
            $this->classUsageFetchingVisitor->getClassUsageList(),
            $this->docblockClassUsageFetchingVisitor->getClassUsageList()
        );

        foreach ($classUsages as $classUsage) {
            if ($classUsage['isFullyQualified']) {
                $fqcn = $classUsage['name'];
            } else {
                $fqcn = $this->resolveTypeCommand->resolveType(
                    $classUsage['name'],
                    $this->file,
                    $classUsage['line']
                );
            }

            $fqcn = $this->typeAnalyzer->getNormalizedFqcn($fqcn);

            if (!isset($classMap[$fqcn])) {
                unset($classUsage['line'], $classUsage['firstPart'], $classUsage['isFullyQualified']);

                $unknownClasses[] = $classUsage;
            }
        }

        return $unknownClasses;
    }
}
