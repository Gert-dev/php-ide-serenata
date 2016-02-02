<?php

namespace PhpIntegrator\Indexer;

use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Node visitor that fetches information about use statements and the namespace.
 */
class UseStatementFetchingVisitor implements NodeVisitor
{
    /**
     * @var array
     */
    protected $useStatements = [];

    /**
     * @var string|null
     */
    protected $namespace = null;

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = (string) $node->name;
        } elseif ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->useStatements[] = [
                    'fqsen' => (string) $use->name,
                    'alias' => $use->alias
                ];
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function leaveNode(Node $node)
    {

    }

    /**
     * {@inheritDoc}
     */
    public function beforeTraverse(array $nodes)
    {

    }

    /**
     * {@inheritDoc}
     */
    public function afterTraverse(array $nodes)
    {

    }

    /**
     * Retrieves the current namespace, if any.
     *
     * @return string|null
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Retrieves the list of use statements.
     *
     * @return array
     */
    public function getUseStatements()
    {
        return $this->useStatements;
    }
}
