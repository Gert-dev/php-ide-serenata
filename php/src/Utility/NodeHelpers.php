<?php

namespace PhpIntegrator\Utility;

use PhpParser\Node;

/**
 * Contains static helper functions for working with nodes.
 */
class NodeHelpers
{
    /**
     * Takes a class name and turns it into its string representation.
     *
     * @param Node\Name $name
     *
     * @return string
     */
    public static function fetchClassName(Node\Name $name)
    {
        $newName = (string) $name;

        if ($name->isFullyQualified() && $newName[0] !== '\\') {
            $newName = '\\' . $newName;
        }

        return $newName;
    }
}
