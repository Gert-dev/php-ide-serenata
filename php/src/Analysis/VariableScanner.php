<?php

namespace PhpIntegrator\Analysis;

use UnexpectedValueException;

use PhpIntegrator\Analysis\Visiting\ScopeLimitingVisitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;

/**
 * Scans for available variables.
 */
class VariableScanner
{
    /**
     * @param Node[] $nodes
     * @param int    $offset
     *
     * @throws UnexpectedValueException
     */
    public function getAvailableVariables(array $nodes, $offset)
    {
        $queryingVisitor = new VariableScanningVisitor($offset);
        $scopeLimitingVisitor = new ScopeLimitingVisitor($offset);

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($scopeLimitingVisitor);
        $traverser->addVisitor($queryingVisitor);
        $traverser->traverse($nodes);

        $variables = $queryingVisitor->getVariablesSortedByProximity();

        // We don't do any type resolution at the moment, but we maintain this format for backwards compatibility.
        $outputVariables = [];

        foreach ($variables as $variable) {
            $outputVariables[$variable] = [
                'name' => $variable,
                'type' => null
            ];
        }

        return $outputVariables;
    }
}
