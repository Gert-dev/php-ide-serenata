<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpParser\Node;
use PhpParser\NodeVisitor;

/**
 * Node visitor that fetches information about use statements and the namespace.
 */
class AssociativeUseStatementFetchingVisitor implements NodeVisitor
{
    /**
     * @var array[]
     */
    protected $namespaces = [];

    /**
     * @var int
     */
    protected $lastIndex = 0;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->namespaces[] = [
            'name'          => null,
            'startLine'     => 0,
            'endLine'       => null,
            'useStatements' => []
        ];

        $this->lastIndex = 0;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespaces[] = [
                'name'          => $node->name ? (string) $node->name : '',
                'startLine'     => $node->getLine(),
                'endLine'       => null,
                'useStatements' => []
            ];

            // There is no way to fetch the end of a namespace, so determine it manually (a value of null signifies the
            // end of the file).
            $this->namespaces[$this->lastIndex]['endLine'] = $node->getLine() - 1;

            ++$this->lastIndex;
        } elseif ($node instanceof Node\Stmt\Use_ || $node instanceof Node\Stmt\GroupUse) {
            $prefix = '';

            if ($node instanceof Node\Stmt\GroupUse) {
                $prefix = ((string) $node->prefix) . '\\';
            };

            foreach ($node->uses as $use) {
                // NOTE: The namespace may be null here (intended behavior).
                $this->namespaces[$this->lastIndex]['useStatements'][] = [
                    'fqcn'  => $prefix . ((string) $use->name),
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
