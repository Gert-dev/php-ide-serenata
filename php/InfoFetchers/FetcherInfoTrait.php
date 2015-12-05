<?php

namespace PhpIntegrator;

/**
 * Trait that contains useful functionality for fetching information about various items (classes, functions, ...).
 */
trait FetcherInfoTrait
{
    /**
     * Returns a boolean indicating if the specified value is a class type or not.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isClassType($type)
    {
        return ucfirst($type) === $type;
    }

    /**
     * Retrieves the sole class name from the specified return value statement.
     *
     * @example "null" returns null.
     * @example "FooClass" returns "FooClass".
     * @example "FooClass|null" returns "FooClass".
     * @example "FooClass|BarClass|null" returns null (there is no single type).
     *
     * @param string $returnValueStatement
     *
     * @return string|null
     */
    protected function getSoleClassName($returnValueStatement)
    {
        if ($returnValueStatement) {
            $types = explode(DocParser::TYPE_SPLITTER, $returnValueStatement);

            $classTypes = [];

            foreach ($types as $type) {
                if ($this->isClassType($type)) {
                    $classTypes[] = $type;
                }
            }

            if (count($classTypes) === 1) {
                return $classTypes[0];
            }
        }

        return null;
    }

    /**
     * Resolves and determiens the full return type (class name) for the return value of the specified item.
     *
     * @param array       $info
     * @param string|null $currentClass The class that contains the method (either through inheritance or directly).
     *
     * @return string|null
     */
    protected function determineFullReturnType(array $info, $currentClass = null)
    {
        if (!isset($info['return']['type']) || empty($info['return']['type'])) {
            return null;
        }

        $returnValue = $info['return']['type'];

        if ($returnValue == '$this' || $returnValue == 'self') {
            return isset($info['declaringClass']['name']) ? $info['declaringClass']['name'] : null;
        } elseif ($returnValue == 'static') {
            return $currentClass ?: null;
        }

        $soleClassName = $this->getSoleClassName($returnValue);

        if (!empty($soleClassName)) {
            if ($soleClassName[0] !== "\\") {
                $filename = isset($info['declaringStructure']['filename']) ? $info['declaringStructure']['filename'] : $info['filename'];
                $parser = new FileParser($filename);

                $completedClassName = $parser->getFullClassName($soleClassName, $useStatementFound);

                return $completedClassName;
            }

            return $soleClassName;
        }

        return $returnValue;
    }
}
