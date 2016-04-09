<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use UnexpectedValueException;

use PhpIntegrator\DocParser;
use PhpIntegrator\IndexDatabase;

use PhpIntegrator\Application\Command\ClassInfo;

use PhpIntegrator\Indexer\OutlineIndexingVisitor;

/**
 * Analyzes the correctness of docblocks.
 */
class DocblockCorrectnessAnalyzer implements AnalyzerInterface
{
    /**
     * @var OutlineIndexingVisitor
     */
    protected $outlineIndexingVisitor;

    /**
     * @var string
     */
    protected $file;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * @var ClassInfo
     */
    protected $classInfoCommand;

    /**
     * @var array
     */
    protected $classCache = [];

    /**
     * Constructor.
     *
     * @param string        $file
     * @param IndexDatabase $indexDatabase
     */
    public function __construct($file, IndexDatabase $indexDatabase, ClassInfo $classInfoCommand)
    {
        $this->file = $file;
        $this->indexDatabase = $indexDatabase;
        $this->classInfoCommand = $classInfoCommand;

        $this->outlineIndexingVisitor = new OutlineIndexingVisitor();
    }

    /**
     * @inheritDoc
     */
    public function getVisitors()
    {
        return [
            $this->outlineIndexingVisitor
        ];
    }

    /**
     * @inheritDoc
     */
    public function getOutput()
    {
        $docblockIssues = [
            'missingDocumentation'  => [],
            'parameterMissing'      => [],
            'parameterTypeMismatch' => [],
            'superfluousParameter'  => []
        ];

        $structures = $this->outlineIndexingVisitor->getStructures();

        foreach ($structures as $structure) {
            $docblockIssues = array_merge_recursive(
                $docblockIssues,
                $this->analyzeStructureDocblock($structure)
            );

            foreach ($structure['methods'] as $method) {
                $docblockIssues = array_merge_recursive(
                    $docblockIssues,
                    $this->analyzeMethodDocblock($structure, $method)
                );
            }

            foreach ($structure['properties'] as $property) {
                $docblockIssues = array_merge_recursive(
                    $docblockIssues,
                    $this->analyzePropertyDocblock($structure, $property)
                );
            }

            foreach ($structure['constants'] as $constant) {
                $docblockIssues = array_merge_recursive(
                    $docblockIssues,
                    $this->analyzeClassConstantDocblock($structure, $constant)
                );
            }
        }

        $globalFunctions = $this->outlineIndexingVisitor->getGlobalFunctions();

        foreach ($globalFunctions as $function) {
            $docblockIssues = array_merge_recursive(
                $docblockIssues,
                $this->analyzeFunctionDocblock($function)
            );
        }

        // TODO: Write tests.
        // TODO: This new code somehow broke the remaining tests.
        // TODO: Before we enable this for everyone, add support to the linter for disabling certain validation. I can
        // imagine some users will find this behavior too aggressive (or simply have codebases that aren't documented
        // properly yet and don't want to get spammed by warnings).

        return $docblockIssues;
    }

    /**
     * @param array $structure
     *
     * @return array
     */
    protected function analyzeStructureDocblock(array $structure)
    {
        if ($structure['docComment']) {
            return [];
        }

        $docblockIssues = [];

        $classInfo = $this->getClassInfo($structure['fqcn']);

        if ($classInfo && !$classInfo['hasDocumentation']) {
            $docblockIssues['missingDocumentation'][] = [
                'name'  => $structure['name'],
                'line'  => $structure['startLine'],
                'start' => $structure['startPos'],
                'end'   => $structure['endPos']
            ];
        }

        return $docblockIssues;
    }

    /**
     * @param array $structure
     * @param array $method
     *
     * @return array
     */
    protected function analyzeMethodDocblock(array $structure, array $method)
    {
        if ($method['docComment']) {
            return $this->analyzeFunctionDocblock($method);
        }

        $docblockIssues = [];

        $classInfo = $this->getClassInfo($structure['fqcn']);

        if ($classInfo &&
            isset($classInfo['methods'][$method['name']]) &&
            !$classInfo['methods'][$method['name']]['hasDocumentation']
        ) {
            $docblockIssues['missingDocumentation'][] = [
                'name'  => $method['name'],
                'line'  => $method['startLine'],
                'start' => $method['startPos'],
                'end'   => $method['endPos']
            ];
        }

        return $docblockIssues;
    }

    /**
     * @param array $structure
     * @param array $property
     *
     * @return array
     */
    protected function analyzePropertyDocblock(array $structure, array $property)
    {
        if ($property['docComment']) {
            // TODO: Warn if there is no @var tag.
            return [];
        }

        $docblockIssues = [];

        $classInfo = $this->getClassInfo($structure['fqcn']);

        if ($classInfo &&
            isset($classInfo['properties'][$property['name']]) &&
            !$classInfo['properties'][$property['name']]['hasDocumentation']
        ) {
            $docblockIssues['missingDocumentation'][] = [
                'name'  => $property['name'],
                'line'  => $property['startLine'],
                'start' => $property['startPos'],
                'end'   => $property['endPos']
            ];
        }

        return $docblockIssues;
    }

    /**
     * @param array $structure
     * @param array $constant
     *
     * @return array
     */
    protected function analyzeClassConstantDocblock(array $structure, array $constant)
    {
        $docblockIssues = [
            'missingDocumentation' => []
        ];

        if (!$constant['docComment']) {
            $docblockIssues['missingDocumentation'][] = [
                'name'  => $constant['name'],
                'line'  => $constant['startLine'],
                'start' => $constant['startPos'],
                'end'   => $constant['endPos']
            ];

            return $docblockIssues;
        }

        // TODO: Warn if there is no @var tag.
        return $docblockIssues;
    }

    /**
     * @param array $function
     *
     * @return array
     */
    protected function analyzeFunctionDocblock(array $function)
    {
        $docblockIssues = [
            'missingDocumentation'  => [],
            'parameterMissing'      => [],
            'parameterTypeMismatch' => [],
            'superfluousParameter'  => []
        ];

        if (!$function['docComment']) {
            $docblockIssues['missingDocumentation'][] = [
                'name'  => $function['name'],
                'line'  => $function['startLine'],
                'start' => $function['startPos'],
                'end'   => $function['endPos']
            ];

            return $docblockIssues;
        }

        $result = $this->getDocParser()->parse($function['docComment'], [DocParser::PARAM_TYPE], $function['name']);

        $keysFound = [];
        $docblockParameters = $result['params'];

        foreach ($function['parameters'] as $parameter) {
            $dollarName = '$' . $parameter['name'];

            if (isset($docblockParameters[$dollarName])) {
                $keysFound[] = $dollarName;
            }

            if (!isset($docblockParameters[$dollarName])) {
                $docblockIssues['parameterMissing'][] = [
                    'name'      => $function['name'],
                    'parameter' => $dollarName,
                    'line'      => $function['startLine'],
                    'start'     => $function['startPos'],
                    'end'       => $function['endPos']
                ];
            } elseif (
                $parameter['type'] &&
                $parameter['type'] !== $docblockParameters[$dollarName]['type']
            ) {
                $docblockIssues['parameterTypeMismatch'][] = [
                    'name'      => $function['name'],
                    'parameter' => $dollarName,
                    'line'      => $function['startLine'],
                    'start'     => $function['startPos'],
                    'end'       => $function['endPos']
                ];
            }
        }

        $superfluousParameterNames = array_values(array_diff(array_keys($docblockParameters), $keysFound));

        if (!empty($superfluousParameterNames)) {
            $docblockIssues['superfluousParameter'][] = [
                'name'       => $function['name'],
                'parameters' => $superfluousParameterNames,
                'line'       => $function['startLine'],
                'start'      => $function['startPos'],
                'end'        => $function['endPos']
            ];
        }

        return $docblockIssues;
    }

    /**
     * @param string $fqcn
     *
     * @return array|null
     */
    protected function getClassInfo($fqcn)
    {
        if (!isset($classCache[$fqcn])) {
            try {
                $classCache[$fqcn] = $this->classInfoCommand->getClassInfo($fqcn);
            } catch (UnexpectedValueException $e) {
                $classCache[$fqcn] = null;
            }
        }

        return $classCache[$fqcn];
    }

    /**
     * @return DocParser
     */
    protected function getDocParser()
    {
        if (!$this->docParser) {
            $this->docParser = new DocParser();
        }

        return $this->docParser;
    }
}
