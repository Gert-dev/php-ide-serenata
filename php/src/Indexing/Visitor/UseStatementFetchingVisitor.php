<?php

namespace PhpIntegrator\Indexing\Visitor;

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
    protected $namespaces = [];

    /**
     * @var string|null
     */
    protected $lastNamespace = null;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->namespaces[null] = [
            'name'          => null,
            'startLine'     => 0,
            'endLine'       => null,
            'useStatements' => []
        ];

        $this->lastNamespace = null;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $namespace = (string) $node->name;

            $this->namespaces[$namespace] = [
                'name'          => $namespace,
                'startLine'     => $node->getLine(),
                'endLine'       => null,
                'useStatements' => []
            ];

            // There is no way to fetch the end of a namespace, so determine it manually (a value of null signifies the
            // end of the file).
            $this->namespaces[$this->lastNamespace]['endLine'] = $node->getLine() - 1;
            $this->lastNamespace = $namespace;
        } elseif ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                // NOTE: The namespace may be null here (intended behavior).
                $this->namespaces[$this->lastNamespace]['useStatements'][] = [
                    'fqcn' => (string) $use->name,
                    'alias' => $use->alias,
                    'line'  => $node->getLine()
                ];
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function leaveNode(Node $node)
    {

    }

    /**
     * @inheritDoc
     */
    public function beforeTraverse(array $nodes)
    {

    }

    /**
     * @inheritDoc
     */
    public function afterTraverse(array $nodes)
    {

    }

    /**
     * Retrieves a list of namespaces.
     *
     * @return array
     */
    public function getNamespaces()
    {
        return $this->namespaces;
    }
}
