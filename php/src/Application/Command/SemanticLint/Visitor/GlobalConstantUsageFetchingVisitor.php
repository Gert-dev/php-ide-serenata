<?php

namespace PhpIntegrator\Application\Command\SemanticLint\Visitor;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Application\Command\GlobalConstants;

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
     * @var GlobalConstants
     */
    protected $globalConstants;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @param GlobalConstants $globalConstants
     * @param TypeAnalyzer    $typeAnalyzer
     */
    public function __construct(GlobalConstants $globalConstants, TypeAnalyzer $typeAnalyzer)
    {
        $this->globalConstants = $globalConstants;
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

        $globalConstants = $this->globalConstants->getGlobalConstants();

        if (!isset($globalConstants[$fqcn]) && !$this->isConstantExcluded($node->name->toString())) {
            $this->globalConstantCallList[] = [
                'name'  => $this->fetchClassName($node->name),
                'start' => $node->getAttribute('startFilePos') ? $node->getAttribute('startFilePos')   : null,
                'end'   => $node->getAttribute('endFilePos')   ? $node->getAttribute('endFilePos') + 1 : null
            ];
        }
    }

    /**
     * Takes a class name and turns it into a string.
     *
     * @param Node\Name $name
     *
     * @return string
     */
    protected function fetchClassName(Node\Name $name)
    {
        $newName = (string) $name;

        if ($name->isFullyQualified() && $newName[0] !== '\\') {
            $newName = '\\' . $newName;
        }

        return $newName;
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
