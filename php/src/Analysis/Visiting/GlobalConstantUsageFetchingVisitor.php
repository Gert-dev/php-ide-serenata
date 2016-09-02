<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Utility\NodeHelpers;

use PhpParser\Node;

use PhpParser\NodeVisitor\NameResolver;

/**
 * Node visitor that fetches usages of (global) constants.
 */
class GlobalConstantUsageFetchingVisitor extends NameResolver
{
    /**
     * @var array
     */
    protected $globalConstantList = [];

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        parent::enterNode($node);

        if (!$node instanceof Node\Expr\ConstFetch) {
            return;
        }

        if (!$this->isConstantExcluded($node->name->toString())) {
            $this->globalConstantList[] = [
                'name'          => NodeHelpers::fetchClassName($node->name),
                'namespace'     => NodeHelpers::fetchClassName($this->namespace),
                'isUnqualified' => $node->name->isUnqualified(),
                'start'         => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'           => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];
        }
    }

   /**
    * @param string $name
    *
    * @return bool
    */
   protected function isConstantExcluded($name)
   {
       return in_array(mb_strtolower($name), ['null', 'true', 'false'], true);
   }

    /**
     * @return array
     */
    public function getGlobalConstantList()
    {
        return $this->globalConstantList;
    }
}
