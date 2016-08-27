<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\GlobalFunctions;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of (global) functions.
 */
class GlobalFunctionUsageFetchingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    protected $globalFunctionCallList = [];

    /**
     * @var GlobalFunctions
     */
    protected $globalFunctions;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param GlobalFunctions $globalFunctions
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalFunctions $globalFunctions, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalFunctions = $globalFunctions;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Node\Expr\FuncCall || !$node->name instanceof Node\Name) {
            return;
        }

        $fqcn = $this->typeAnalyzer->getNormalizedFqcn($node->name->toString());

        $globalFunctions = $this->globalFunctions->getGlobalFunctions();

        if (!isset($globalFunctions[$fqcn])) {
            $this->globalFunctionCallList[] = [
                'name'  => $this->fetchClassName($node->name),
                'start' => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'   => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];
        }
    }

    /**
     * Takes a class name and turns it into a string.
     *
     * @param Node\Name $name
     *
     * @return string
     */
    protected function fetchClassName(Node\Name $name)
    {
        $newName = (string) $name;

        if ($name->isFullyQualified() && $newName[0] !== '\\') {
            $newName = '\\' . $newName;
        }

        return $newName;
    }

    /**
     * @return array
     */
    public function getGlobalFunctionCallList()
    {
        return $this->globalFunctionCallList;
    }
}
