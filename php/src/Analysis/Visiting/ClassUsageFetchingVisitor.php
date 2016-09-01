<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of class, trait, and interface names.
 */
class ClassUsageFetchingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array
     */
    protected $classUsageList = [];

    /**
     * @var Node|null
     */
    protected $lastNode;

    /**
     * @var TypeAnalyzer|null
     */
    protected $typeAnalyzer = null;

    /**
     * @var string|null
     */
    protected $lastNamespace = null;

    /**
     * Constructor.
     */
    public function __construct(TypeAnalyzer $typeAnalyzer)
    {
        $this->typeAnalyzer = $typeAnalyzer;

        $this->lastNamespace = null;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->lastNamespace = (string) $node->name;
        }

        if ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Name) {
            if (!$this->lastNode instanceof Node\Expr\FuncCall &&
                !$this->lastNode instanceof Node\Expr\ConstFetch &&
                !$this->lastNode instanceof Node\Stmt\Namespace_
            ) {
                $name = (string) $node;

                if ($this->isValidType($name)) {
                    $this->classUsageList[] = [
                        'name'             => $name,
                        'firstPart'        => $node->getFirst(),
                        'isFullyQualified' => $node->isFullyQualified(),
                        'namespace'        => $this->lastNamespace,
                        'line'             => $node->getAttribute('startLine')    ? $node->getAttribute('startLine')      : null,
                        'start'            => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                        'end'              => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
                    ];
                }
            }
        }

        $this->lastNode = $node;
    }

    /**
     * @param string $type
     *
     * @return bool
     */
     protected function isValidType($type)
     {
         return $this->typeAnalyzer->isClassType($type);
     }

    /**
     * Retrieves the class usage list.
     *
     * @return array
     */
    public function getClassUsageList()
    {
        return $this->classUsageList;
    }
}
