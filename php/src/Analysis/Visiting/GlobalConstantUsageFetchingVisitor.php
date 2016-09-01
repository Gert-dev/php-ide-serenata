<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Utility\NodeHelpers;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Node visitor that fetches usages of (global) constants.
 */
class GlobalConstantUsageFetchingVisitor extends NodeVisitorAbstract
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
        if (!$node instanceof Node\Expr\ConstFetch) {
            return;
        }

        if (!$this->isConstantExcluded($node->name->toString())) {
            $this->globalConstantList[] = [
                'name'  => NodeHelpers::fetchClassName($node->name),
                'start' => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'   => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
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
