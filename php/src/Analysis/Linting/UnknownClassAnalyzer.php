<?php

namespace PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Analysis\ClasslikeExistanceCheckerInterface;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;
use PhpIntegrator\Analysis\Typing\FileTypeResolver;

use PhpIntegrator\Analysis\Visiting\ClassUsageFetchingVisitor;
use PhpIntegrator\Analysis\Visiting\DocblockClassUsageFetchingVisitor;

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
     * @var ClasslikeExistanceCheckerInterface
     */
    protected $classlikeExistanceChecker;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var FileTypeResolver
     */
    protected $fileTypeResolver;

    /**
     * Constructor.
     *
     * @param ClasslikeExistanceCheckerInterface $classlikeExistanceChecker
     * @param FileTypeResolver $fileTypeResolver
     * @param TypeAnalyzer     $typeAnalyzer
     * @param DocblockParser   $docblockParser
     */
    public function __construct(
        ClasslikeExistanceCheckerInterface $classlikeExistanceChecker,
        FileTypeResolver $fileTypeResolver,
        TypeAnalyzer $typeAnalyzer,
        DocblockParser $docblockParser
    ) {
        $this->typeAnalyzer = $typeAnalyzer;
        $this->fileTypeResolver = $fileTypeResolver;
        $this->classlikeExistanceChecker = $classlikeExistanceChecker;

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
                $fqcn = $this->fileTypeResolver->resolve($classUsage['name'], $classUsage['line']);
            }

            $fqcn = $this->typeAnalyzer->getNormalizedFqcn($fqcn);

            if (!$this->classlikeExistanceChecker->doesClassExist($fqcn)) {
                unset($classUsage['line'], $classUsage['firstPart'], $classUsage['isFullyQualified']);

                $unknownClasses[] = $classUsage;
            }
        }

        return $unknownClasses;
    }
}
