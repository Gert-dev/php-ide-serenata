<?php

namespace PhpIntegrator\Application\Command\SemanticLint;

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
     * @var array
     */
    protected $useStatementMap = [];

    /**
     * @var Node|null
     */
    protected $lastNode;

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                // NOTE: The namespace may be null here (intended behavior).
                $this->useStatementMap[(string) $use->alias] = [
                    'name'      => (string) $use->name,
                    'alias'     => (string) $use->alias,
                    'start'     => $use->getAttribute('startFilePos') ? $use->getAttribute('startFilePos')   : null,
                    'end'       => $use->getAttribute('endFilePos')   ? $use->getAttribute('endFilePos') + 1 : null
                ];
            }
        }

        // We don't care about these names at the moment.
        if ($node instanceof Node\Expr\ConstFetch ||
            $node instanceof Node\Expr\FuncCall ||
            $node instanceof Node\Stmt\Use_ ||
            $this->lastNode instanceof Node\Stmt\Namespace_) {
            // TODO: Constants and functions can also have a fully qualified name, but these are not indexed at the
            // moment. See also https://secure.php.net/manual/en/language.namespaces.importing.php .
            $this->lastNode = $node;

            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }

        if ($node instanceof Node\Name) {
            if ($this->isValidType((string) $node)) {
                $unresolvedName = (string) $node;
                $unresolvedFirstPart = $node->getFirst();

                $this->classUsageList[] = [
                    'name'      => $unresolvedName,
                    'firstPart' => $unresolvedFirstPart,
                    'line'      => $node->getAttribute('startLine')       ? $node->getAttribute('startLine')      : null,
                    'start'     => $node->getAttribute('startFilePos')    ? $node->getAttribute('startFilePos')   : null,
                    'end'       => $node->getAttribute('endFilePos')      ? $node->getAttribute('endFilePos') + 1 : null
                ];
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
        return !in_array($type, ['self', 'static', 'parent']) && $type[0] !== '\\';
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

    /**
     * Retrieves the use statement map.
     *
     * @return array
     */
    public function getUseStatementMap()
    {
        return $this->useStatementMap;
    }
}
