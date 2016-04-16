<?php

namespace PhpIntegrator;

/**
 * Provides functionality for analyzing type names.
 */
class TypeAnalyzer
{
    /**
     * @var string
     */
    const TYPE_SPLITTER   = '|';

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

    /**
     * Returns a boolean indicating if the specified type (i.e. from a type hint) is valid according to the passed
     * docblock type identifier.
     *
     * @param string $type
     * @param string $docblockType
     *
     * @return bool
     */
    public function isTypeConformantWithDocblockType($type, $docblockType)
    {
        $docblockTypes = explode(self::TYPE_SPLITTER, $docblockType);

        return $this->isTypeConformantWithDocblockTypes($type, $docblockTypes);
    }

    /**
     * @param string   $type
     * @param string[] $docblockTypes
     *
     * @return bool
     */
    protected function isTypeConformantWithDocblockTypes($type, array $docblockTypes)
    {
        $isPresent = in_array($type, $docblockTypes);

        if (!$isPresent && $type === 'array') {
            foreach ($docblockTypes as $docblockType) {
                // The 'type[]' syntax is also valid for the 'array' type hint.
                if (preg_match('/^.+\[\]$/', $docblockType) === 1) {
                    return true;
                }
            }
        }

        return $isPresent;
    }
}
