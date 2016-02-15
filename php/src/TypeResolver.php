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
    public function resolve($type)
    {
        if (empty($type)) {
            return null;
        } elseif ($type[0] === '\\') {
            return $type;
        }

        $typeParts = explode('\\', $type);

        foreach ($this->imports as $import) {
            if ($import['alias'] === $typeParts[0]) {
                array_shift($typeParts);

                $fullName = $import['fqsen'];

                if (!empty($typeParts)) {
                    /*
                     * This block is only executed when relative names are used with more than one part, i.e.:
                     *   use A\B\C;
                     *
                     *   C\D::foo();
                     *
                     * 'C' will be dropped from 'C\D', and the remaining 'D' will be appended to 'A\B\C',
                     * becoming 'A\B\C\D'.
                     */
                    $fullName .= '\\' . implode('\\', $typeParts);
                }

                return $fullName;
            }
        }

        // Still here? There must be no explicit use statement, default to the current namespace.
        $fullName = $this->namespace ?: '';
        $fullName .= '\\' . $type;

        return $fullName;
    }
}
