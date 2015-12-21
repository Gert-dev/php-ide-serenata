<?php

namespace PhpIntegrator;

use PhpParser\Node;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Node visitor that fetches class dependencies (base class names, used interface names and trait names).
 */
class DependencyFetchingVisitor extends NameResolver
{
    /**
     * A mapping of FQSENs (Fully Qualified Structural Element Name) to a list of their dependencies (also FQSENs).
     *
     * @var array
     */
    protected $fqsenDependencyMap = [];

    /**
     * @var Node\Stmt\Class_|null
     */
    protected $currentStructuralElement;

    /**
     * {@inheritDoc}
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if ($node instanceof Node\Stmt\Class_) {
            $this->currentStructuralElement = $node;

            $fqcn = $node->namespacedName->toString();

            $this->fqsenDependencyMap[$fqcn] = [];

            if ($node->extends) {
                $this->fqsenDependencyMap[$fqcn][] = $node->extends->toString();
            }

            foreach ($node->implements as $implementedInterface) {
                $this->fqsenDependencyMap[$fqcn][] = $implementedInterface->toString();
            }
        } elseif ($node instanceof Node\Stmt\Interface_) {
            $this->currentStructuralElement = $node;

            $fqcn = $node->namespacedName->toString();

            $this->fqsenDependencyMap[$fqcn] = [];

            if ($node->extends) {
                $extends = $node->extends;

                if (is_array($extends)) {
                    $extends = array_shift($extends);
                }

                $this->fqsenDependencyMap[$fqcn][] = $extends->toString();
            }
        } elseif ($node instanceof Node\Stmt\Trait_) {
            $this->currentStructuralElement = $node;

            $fqcn = $node->namespacedName->toString();

            $this->fqsenDependencyMap[$fqcn] = [];
        } elseif ($node instanceof Node\Stmt\TraitUse) {
            $fqcn = $this->currentStructuralElement->namespacedName->toString();

            foreach ($node->traits as $trait) {
                $this->fqsenDependencyMap[$fqcn][] = $trait->toString();
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function leaveNode(Node $node)
    {
        parent::leaveNode($node);

        if ($this->currentStructuralElement === $node) {
            $this->currentStructuralElement = null;
        }
    }

    /**
     * Retrieves the dependency map.
     *
     * @return array
     */
    public function getFqsenDependencyMap()
    {
        return $this->fqsenDependencyMap;
    }
}
