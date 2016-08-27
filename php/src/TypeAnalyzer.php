<?php

namespace PhpIntegrator;

/**
 * Provides functionality for analyzing type names.
 */
class TypeAnalyzer implements TypeNormalizerInterface
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
        $isReservedKeyword = in_array($type, [
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

        return $isReservedKeyword || $this->isArraySyntaxTypeHint($type);
    }

    /**
     * @param string $type
     *
     * @return bool
     */
    public function isClassType($type)
    {
        return !$this->isSpecialType($type);
    }

    /**
     * @inheritDoc
     */
    public function getNormalizedFqcn($fqcn)
    {
        if ($fqcn && $fqcn[0] !== '\\') {
            return '\\' . $fqcn;
        }

        return $fqcn;
    }

    /**
     * Splits a docblock type specification up into different (docblock) types.
     *
     * @param string $typeSpecification
     *
     * @example "int|string" becomes ["int", "string"].
     *
     * @return string[]
     */
    public function getTypesForTypeSpecification($typeSpecification)
    {
        return explode(self::TYPE_SPLITTER, $typeSpecification);
    }

    /**
     * Returns a boolean indicating if the specified type (i.e. from a type hint) is valid according to the passed
     * docblock type identifier.
     *
     * @param string $type
     * @param string $typeSpecification
     *
     * @return bool
     */
    public function isTypeConformantWithDocblockType($type, $typeSpecification)
    {
        $docblockTypes = $this->getTypesForTypeSpecification($typeSpecification);

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
                if ($this->isArraySyntaxTypeHint($docblockType)) {
                    return true;
                }
            }
        }

        return $isPresent;
    }

    /**
     * @param string $type
     */
    protected function isArraySyntaxTypeHint($type)
    {
        return (preg_match('/^.+\[\]$/', $type) === 1);
    }
}
