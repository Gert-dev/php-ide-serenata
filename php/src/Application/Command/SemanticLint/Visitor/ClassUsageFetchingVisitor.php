<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpIntegrator\TypeAnalyzer;

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
    public function __construct()
    {
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

        if ($node instanceof Node\Stmt\Use_) {
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Name) {
            // TODO: Constants and functions can also have a fully qualified name, but these are not indexed at the
            // moment. See also https://secure.php.net/manual/en/language.namespaces.importing.php .
            if (!$this->lastNode instanceof Node\Expr\FuncCall &&
                !$this->lastNode instanceof Node\Expr\ConstFetch &&
                !$this->lastNode instanceof Node\Stmt\Namespace_
            ) {
                if ($this->isValidType((string) $node)) {
                    $this->classUsageList[] = [
                        'name'             => (string) $node,
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
         return !$this->getTypeAnalyzer()->isSpecialType($type);
     }

    /**
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
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
