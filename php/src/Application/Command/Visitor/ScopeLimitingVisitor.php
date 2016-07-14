<?php

namespace PhpIntegrator\Application\Command\Visitor;

use PhpIntegrator\Application\Command\VariableType\DummyExprNode;

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
     * @inheritDoc
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
            $node instanceof Node\Stmt\TryCatch ||
            $node instanceof Node\Stmt\While_ ||
            $node instanceof Node\Stmt\For_ ||
            $node instanceof Node\Stmt\Foreach_ ||
            $node instanceof Node\Stmt\Do_ ||
            $node instanceof Node\Stmt\Switch_
        ) {
            $endFilePos = $node->getAttribute('endFilePos');
            $startFilePos = $node->getAttribute('startFilePos');

            if ($startFilePos >= $this->position || $endFilePos <= $this->position) {
                return NodeTraverser::DONT_TRAVERSE_CHILDREN;
            }

            if ($node instanceof Node\Stmt\If_) {
                // If-statements have the entire block containing the if statement, the elseif statements and the else
                // statement included in their start and end position. As we want to differentiate between these, we
                // have to ensure nodes from all statements, except the one where the position is located in, are
                // ignored.
                $elseIfNodes = array_reverse($node->elseifs);

                foreach ($elseIfNodes as $elseIfNode) {
                    if ($elseIfNode->getAttribute('startFilePos') < $this->position) {
                        $node->stmts = [];
                        $node->elseifs = [$elseIfNode];
                        $node->cond = new DummyExprNode();
                        break;
                    }
                }

                if ($node->else && $node->else->getAttribute('startFilePos') < $this->position) {
                    $node->stmts = [];
                    $node->elseifs = [];
                    $node->cond = new DummyExprNode();
                }
            } elseif ($node instanceof Node\Stmt\Switch_) {
                // Case statements do encompass their statements with their start and end position, but they do not
                // encompass whitespace or additional newlines between each other. This means that a set of subsequent
                // case statements don't describe one contiguous region of code. We work around this by using the
                // parent switch node instead.
                $caseNodes = array_reverse($node->cases);

                foreach ($caseNodes as $caseNode) {
                    if ($caseNode->getAttribute('startFilePos') < $this->position) {
                        $node->cases = [$caseNode];
                        break;
                    }
                }
            } elseif ($node instanceof Node\Stmt\TryCatch) {
                $catchNodes = array_reverse($node->catches);

                foreach ($catchNodes as $catchNode) {
                    if ($catchNode->getAttribute('startFilePos') < $this->position) {
                        $node->stmts = [];
                        $node->catches = [$catchNode];
                        break;
                    }
                }

                // Finally statements have no own node, so use the first statement as a reference point instead. This
                // won't be entirely correct, but it's the best we can do. See also
                // https://github.com/nikic/PHP-Parser/issues/254
                if ($node->finallyStmts && $node->finallyStmts[0]->getAttribute('startFilePos') < $this->position) {
                    $node->stmts = [];
                    $node->catches = [];
                }
            }
        }
    }
}
