<?php

namespace PhpIntegrator;

/**
 * Resolves local types to their FQCN.
 */
class TypeResolver
{
    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var array
     */
    protected $imports;

    /**
     * Constructor.
     *
     * @param string $namespace The current namespace.
     * @param array {
     *     @param string|null $fqsen
     *     @param string      $alias
     * } $imports
     */
    public function __construct($namespace, array $imports)
    {
        $this->imports = $imports;
        $this->namespace = $namespace;
    }

    /**
     * Resolves and determines the FQSEN of the specified type.
     *
     * @param string $type
     *
     * @return string|null
     */
    public function getFullTypeForDocblockType($type)
    {
        if (empty($type)) {
            return null;
        }

        $soleClassName = $this->getSoleClassName($type);

        if (!empty($soleClassName)) {
            if ($soleClassName[0] !== "\\") {
                $soleClassNameParts = explode('\\', $soleClassName);

                foreach ($this->imports as $import) {
                    if ($import['alias'] === $soleClassNameParts[0]) {
                        array_shift($soleClassNameParts);

                        $fullName = $import['fqsen'];

                        if (!empty($soleClassNameParts)) {
                            /*
                             * This block is only executed when relative names are used with more than one part, i.e.:
                             *   use A\B\C;
                             *
                             *   C\D::foo();
                             *
                             * 'C' will be dropped from 'C\D', and the remaining 'D' will be appended to 'A\B\C',
                             * becoming 'A\B\C\D'.
                             */
                            $fullName .= '\\' . implode('\\', $soleClassNameParts);
                        }

                        return $fullName;
                    }
                }

                // Still here? There must be no explicit use statement, default to the current namespace.
                $fullName = $this->namespace ?: '';
                $fullName .= '\\' . $soleClassName;

                return $fullName;
            }

            return $soleClassName;
        }

        return $type;
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
     * Returns a boolean indicating if the specified value is a class type or not.
     *
     * @param string $type
     *
     * @return bool
     */
    protected function isClassType($type)
    {
        return ucfirst($type) === $type && $type !== '$this';
    }
}
