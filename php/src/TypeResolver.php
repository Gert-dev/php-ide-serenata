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
     *     @param string|null $fqcn
     *     @param string      $alias
     * } $imports
     */
    public function __construct($namespace, array $imports)
    {
        $this->imports = $imports;
        $this->namespace = $namespace;
    }

    /**
     * Resolves and determines the FQCN of the specified type.
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

        $fullName = null;
        $typeParts = explode('\\', $type);

        foreach ($this->imports as $import) {
            if ($import['alias'] === $typeParts[0]) {
                array_shift($typeParts);

                $fullName = $import['fqcn'];

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

                break;
            }
        }

        if (!$fullName) {
            // Still here? There must be no explicit use statement, default to the current namespace.
            $fullName = $this->namespace ? ($this->namespace . '\\') : '';
            $fullName .= $type;
        }

        return $this->getTypeAnalyzer()->getNormalizedFqcn($fullName, true);
    }

    /**
     * "Unresolves" a FQCN, turning it back into a name relative to local use statements. If no local type could be
     * determined, the FQCN is returned (as that is the only way the type can be referenced locally).
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
                'fqcn' => $this->namespace,
                'alias' => array_pop($namespaceParts)
            ];
        }

        $typeFqcn = $this->getTypeAnalyzer()->getNormalizedFqcn($type);

        foreach ($imports as $import) {
            $importFqcn = $this->getTypeAnalyzer()->getNormalizedFqcn($import['fqcn']);

            if (mb_strpos($typeFqcn, $importFqcn) === 0) {
                $localizedType = $import['alias'] . mb_substr($typeFqcn, mb_strlen($importFqcn));

                // It is possible that there are multiple use statements the FQCN could be made relative to (e.g.
                // if a namespace as well as one of its classes is imported), select the closest one in that case.
                if (!$bestLocalizedType || mb_strlen($localizedType) < mb_strlen($bestLocalizedType)) {
                    $bestLocalizedType = $localizedType;
                }
            }
        }

        return $bestLocalizedType ?: ('\\' . $type);
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
