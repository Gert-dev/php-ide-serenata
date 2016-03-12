<?php

namespace PhpIntegrator\Application\Command\AvailableVariables;

use UnexpectedValueException;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Visitor that limits the traversed nodes to ones that apply to the scope active at a specific location. Note that
 * nodes after the specified position, but in the same scope, will still be parsed.
 *
 * Inheriting from this visitor is unnecessary as it can simply be added to the traverser you wish to limit all
 * visitors for.
 */
class ScopeLimitingVisitor extends NodeVisitorAbstract
{
    /**
     * @var int
     */
    protected $position;

    /**
     * Constructor.
     *
     * @param int $position
     */
    public function __construct($position)
    {
        $this->position = $position;
    }

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        // Pretty much everything that uses curly braces is seen as a "scope", and as such is only relevant if the
        // position we're looking for is contained in it.
        if ($node instanceof Node\Stmt\ClassLike ||
            $node instanceof Node\Stmt\Function_ ||
            $node instanceof Node\Stmt\ClassMethod ||
            $node instanceof Node\Expr\Closure ||
            $node instanceof Node\Stmt\If_ ||
            $node instanceof Node\Stmt\ElseIf_ ||
            $node instanceof Node\Stmt\Else_ ||
            $node instanceof Node\Stmt\TryCatch ||
            $node instanceof Node\Stmt\Catch_ ||
            $node instanceof Node\Stmt\While_ ||
            $node instanceof Node\Stmt\For_ ||
            $node instanceof Node\Stmt\Foreach_ ||
            $node instanceof Node\Stmt\Do_ ||
            $node instanceof Node\Stmt\Case_
        ) {
            if ($node->getAttribute('startFilePos') >= $this->position ||
                $node->getAttribute('endFilePos') <= $this->position
            ) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }
        }
    }
}
