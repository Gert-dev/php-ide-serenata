<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Application\Command\GlobalFunctionsCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Utility\NodeHelpers;

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
     * @var GlobalFunctionsCommand
     */
    protected $globalFunctionsCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param GlobalFunctionsCommand $globalFunctionsCommand
     * @param TypeAnalyzer           $typeAnalyzer
     */
    public function __construct(GlobalFunctionsCommand $globalFunctionsCommand, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalFunctionsCommand = $globalFunctionsCommand;
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

        $globalFunctions = $this->globalFunctionsCommand->getGlobalFunctions();

        if (!isset($globalFunctions[$fqcn])) {
            $this->globalFunctionCallList[] = [
                'name'  => NodeHelpers::fetchClassName($node->name),
                'start' => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'   => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];
        }
    }

    /**
     * @return array
     */
    public function getGlobalFunctionCallList()
    {
        return $this->globalFunctionCallList;
    }
}
