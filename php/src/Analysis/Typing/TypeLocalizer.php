<?php

namespace PhpIntegrator\Analysis\Typing;

/**
 * Resolves FQCN's back to local types based on use statements and the namespace.
 */
class TypeLocalizer
{
    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * Constructor.
     *
     * @param TypeAnalyzer $typeAnalyzer
     */
    public function __construct(TypeAnalyzer $typeAnalyzer)
    {
        $this->typeAnalyzer = $typeAnalyzer;
    }

    /**
     * "Unresolves" a FQCN, turning it back into a name relative to local use statements. If no local type could be
     * determined, the FQCN is returned (as that is the only way the type can be referenced locally).
     *
     * @param string      $type
     * @param string|null $namespaceFqcn
     * @param array {
     *     @var string|null $fqcn
     *     @var string      $alias
     * } $imports
     *
     * @example With use statement "use A\B as AliasB", unresolving "A\B\C\D" will yield "AliasB\C\D".
     *
     * @return string|null
     */
    public function localize($type, $namespaceFqcn, array $imports)
    {
        $bestLocalizedType = null;

        if (!$type) {
            return null;
        }

        $typeFqcn = $this->typeAnalyzer->getNormalizedFqcn($type);

        if ($namespaceFqcn) {
            $namespaceFqcn = $this->typeAnalyzer->getNormalizedFqcn($namespaceFqcn);

            $namespaceParts = explode('\\', $namespaceFqcn);

            if (mb_strpos($typeFqcn, $namespaceFqcn) === 0) {
                $typeWithoutNamespacePrefix = mb_substr($typeFqcn, mb_strlen($namespaceFqcn) + 1);

                $typeWithoutNamespacePrefixParts = explode('\\', $typeWithoutNamespacePrefix);

                // The namespace also acts as a use statement, but the rules are slightly different: in namespace A,
                // the class \A\B becomes B rather than A\B (the latter which would happen if there were a use
                // statement "use A;").
                $imports[] = [
                    'fqcn'  => $namespaceFqcn . '\\' . $typeWithoutNamespacePrefixParts[0],
                    'alias' => $typeWithoutNamespacePrefixParts[0]
                ];
            }
        }

        foreach ($imports as $import) {
            $importFqcn = $this->typeAnalyzer->getNormalizedFqcn($import['fqcn']);

            if (mb_strpos($typeFqcn, $importFqcn) === 0) {
                $localizedType = $import['alias'] . mb_substr($typeFqcn, mb_strlen($importFqcn));

                // It is possible that there are multiple use statements the FQCN could be made relative to (e.g.
                // if a namespace as well as one of its classes is imported), select the closest one in that case.
                if (!$bestLocalizedType || mb_strlen($localizedType) < mb_strlen($bestLocalizedType)) {
                    $bestLocalizedType = $localizedType;
                }
            }
        }

        return $bestLocalizedType ?: $this->typeAnalyzer->getNormalizedFqcn($type);
    }
}
