<?php

namespace PhpIntegrator\Analysis\Visiting;

use PhpIntegrator\Application\Command\GlobalConstantsCommand;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

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
    protected $globalConstantCallList = [];

    /**
     * @var GlobalConstantsCommand
     */
    protected $globalConstantsCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param GlobalConstantsCommand $globalConstantsCommand
     * @param TypeAnalyzer           $typeAnalyzer
     */
    public function __construct(GlobalConstantsCommand $globalConstantsCommand, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalConstantsCommand = $globalConstantsCommand;
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * @inheritDoc
     */
    public function enterNode(Node $node)
    {
        if (!$node instanceof Node\Expr\ConstFetch) {
            return;
        }

        $fqcn = $this->typeAnalyzer->getNormalizedFqcn($node->name->toString());

        $globalConstants = $this->globalConstantsCommand->getGlobalConstants();

        if (!isset($globalConstants[$fqcn]) && !$this->isConstantExcluded($node->name->toString())) {
            $this->globalConstantCallList[] = [
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
    public function getGlobalConstantCallList()
    {
        return $this->globalConstantCallList;
    }
}
