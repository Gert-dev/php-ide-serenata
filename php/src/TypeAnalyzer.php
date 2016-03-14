<?php

namespace PhpIntegrator;

/**
 * Provides functionality for analyzing type names.
 */
class TypeAnalyzer
{
    /**
     * Indicates if a type is "special", i.e. it is not an actual class type, but rather a basic type (e.g. "int",
     * "bool", ...) or another special type (e.g. "$this", "false", ...).
     *
     * @param string $type
     *
     * @see https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md#keyword
     *
     * @return bool
     */
    public function isSpecialType($type)
    {
        return in_array($type, [
            'string',
            'int',
            'bool',
            'float',
            'object',
            'mixed',
            'array',
            'resource',
            'void',
            'null',
            'callable',
            'false',
            'true',
            'self',
            'static',
            'parent',
            '$this'
        ]);
    }
}
