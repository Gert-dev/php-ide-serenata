<?php

namespace PhpIntegrator;

/**
 * Resolves local types to their FQCN.
 */
class TypeResolver
{
    /**
     * @var string|null
     */
    protected $namespace;

    /**
     * @var array
     */
    protected $imports;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * Constructor.
     *
     * @param string|null $namespace The current namespace.
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
        $fullName = $this->namespace ? ($this->namespace . '\\') : '';
        $fullName .= $type;

        return $fullName;
    }

    /**
     * "Unresolves" a FQSEN, turning it back into a name relative to local use statements. If no local type could be
     * determined, null is returned.
     *
     * @param string $type
     *
     * @example With use statement "use A\B as AliasB", unresolving "A\B\C\D" will yield "AliasB\C\D".
     *
     * @return string|null
     */
    public function localize($type)
    {
        $bestLocalizedType = null;

        if (!$type) {
            return null;
        }

        $imports = $this->imports;

        if ($this->namespace) {
            $namespaceParts = explode('\\', $this->namespace);

            // The namespace is also acts as a "use statement".
            $imports[] = [
                'fqsen' => $this->namespace,
                'alias' => array_pop($namespaceParts)
            ];
        }

        $typeFqcn = $this->getTypeAnalyzer()->getNormalizedFqcn($type);

        foreach ($imports as $import) {
            $importFqcn = $this->getTypeAnalyzer()->getNormalizedFqcn($import['fqsen']);

            if (mb_strpos($typeFqcn, $importFqcn) === 0) {
                $localizedType = $import['alias'] . mb_substr($typeFqcn, mb_strlen($importFqcn));

                // die(var_dump(__FILE__ . ':' . __LINE__, $localizedType));

                // It is possible that there are multiple use statements the FQCN could be made relative to (e.g.
                // if a namespace as well as one of its classes is imported), select the closest one in that case.
                if (!$bestLocalizedType || mb_strlen($localizedType) < mb_strlen($bestLocalizedType)) {
                    $bestLocalizedType = $localizedType;
                }
            }
        }

        return $bestLocalizedType;
    }

    /**
     * @return TypeAnalyzer
     */
    protected function getTypeAnalyzer()
    {
        if (!$this->typeAnalyzer) {
            $this->typeAnalyzer = new TypeAnalyzer();
        }

        return $this->typeAnalyzer;
    }
}
