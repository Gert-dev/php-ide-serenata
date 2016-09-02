<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\UserInterface\Command\GlobalFunctionsCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Utility\NodeHelpers;

use PhpParser\Node;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Node visitor that fetches usages of (global) functions.
 */
class GlobalFunctionUsageFetchingVisitor extends NameResolver
{
    /**
     * @var array
     */
    protected $globalFunctionCallList = [];

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if (!$node instanceof Node\Expr\FuncCall || !$node->name instanceof Node\Name) {
            return;
        }

        $this->globalFunctionCallList[] = [
            'name'          => NodeHelpers::fetchClassName($node->name),
            'namespace'     => NodeHelpers::fetchClassName($this->namespace),
            'isUnqualified' => $node->name->isUnqualified(),
            'start'         => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
            'end'           => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
        ];
    }

    /**
     * @return array
     */
    public function getGlobalFunctionCallList()
    {
        return $this->globalFunctionCallList;
    }
}
