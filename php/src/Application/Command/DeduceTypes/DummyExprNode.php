<?php

namespace PhpIntegrator\Application\Command\DeduceTypes;

use PhpParser\Node;

/**
 * Dummy expression that can be inserted in locations were an expression node is expected to be present, but it should
 * not actually contain anything useful.
 */
class DummyExprNode extends Node\Expr
{
    /**
     * @inheritDoc
     */
    public function getSubNodeNames()
    {
        return [];
    }
}
