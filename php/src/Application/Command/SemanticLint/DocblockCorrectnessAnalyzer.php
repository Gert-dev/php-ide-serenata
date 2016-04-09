<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

use PhpIntegrator\DocParser;
use PhpIntegrator\IndexDatabase;

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
     * Constructor.
     *
     * @param string        $file
     * @param IndexDatabase $indexDatabase
     */
    public function __construct($file, IndexDatabase $indexDatabase)
    {
        $this->file = $file;
        $this->indexDatabase = $indexDatabase;

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
            foreach ($structure['methods'] as $method) {
                // TODO: Validate all the same things as a global function, but if no docblock was found, fetch class
                // information to see if 'hasDocumentation' = true.
            }

            foreach ($structure['properties'] as $property) {

            }

            foreach ($structure['constants'] as $constant) {

            }

            // if (!$structuralElement['docblock']) {
                // if (!$structuralElement['class']) {
                    // TODO: Warning: missing docblock.
                // }
            // }

            // $structuralElement['docblock'];
        }

        $globalFunctions = $this->outlineIndexingVisitor->getGlobalFunctions();

        foreach ($globalFunctions as $function) {
            $docblockIssues = array_merge_recursive(
                $docblockIssues,
                $this->analyzeFunctionDocblock($function)
            );
        }

        $globalConstants = $this->outlineIndexingVisitor->getGlobalConstants();

        foreach ($globalConstants as $constant) {
            // TODO
        }

        // TODO: The OutlineIndexingVisitor does not prepend a leading slash to fully qualified paths. Must
        // happen for the fullType as well as the type. Also update tests (if any generate problems).

        // TODO: Write tests.
        // TODO: Before we enable this for everyone, add support to the linter for disabling certain validation. I can
        // imagine some users will find this behavior too aggressive (or simply have codebases that aren't documented
        // properly yet and don't want to get spammed by warnings).



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
            'superfluousParameter' => []
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
            if (!isset($docblockParameters['$' . $parameter['name']])) {
                $docblockIssues['parameterMissing'][] = [
                    'name'      => $function['name'],
                    'parameter' => $parameter['name'],
                    'line'      => $function['startLine'],
                    'start'     => $function['startPos'],
                    'end'       => $function['endPos']
                ];

                $keysFound[] = '$' . $parameter['name'];
            } elseif (
                $parameter['type'] &&
                $parameter['type'] !== $docblockParameters['$' . $parameter['name']]['type']
            ) {
                $docblockIssues['parameterTypeMismatch'][] = [
                    'name'      => $function['name'],
                    'parameter' => $parameter['name'],
                    'line'      => $function['startLine'],
                    'start'     => $function['startPos'],
                    'end'       => $function['endPos']
                ];
            }
        }

        $superfluousParameterNames = array_intersect($keysFound, array_keys($docblockParameters));

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
