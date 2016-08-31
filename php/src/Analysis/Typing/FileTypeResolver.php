<?php

namespace PhpIntegrator\Analysis\Typing;

/**
 * Resolves local types to their FQCN for a file.
 *
 * This is a convenience layer on top of {@see TypeResolver} that accepts a list of namespaces and imports (use
 * statements) for a file and automatically selects the data relevant at the requested line from the list to feed to
 * the underlying resolver.
 */
class FileTypeResolver
{
    /**
     * @var array
     */
    protected $namespaces;

    /**
     * @var array
     */
    protected $imports;

    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @param TypeResolver $typeResolver
     * @param array {
     *     @var string   $fqcn
     *     @var int      $startLine
     *     @var int|null $endLine
     * } $namespaces
     * @param array {
     *     @var string $fqcn
     *     @var string $alias
     *     @var int    $line
     * } $imports
     */
    public function __construct(TypeResolver $typeResolver, array $namespaces, array $imports)
    {
        $this->typeResolver = $typeResolver;
        $this->namespaces = $namespaces;
        $this->imports = $imports;
    }

    /**
     * Resolves and determines the FQCN of the specified type.
     *
     * @param string $type
     * @param int    $line
     *
     * @return string|null
     */
    public function resolve($type, $line)
    {
        $namespaceFqcn = null;
        $relevantImports = [];

        foreach ($this->namespaces as $namespace) {
            if ($this->lineLiesWithinNamespaceRange($line, $namespace)) {
                $namespaceFqcn = $namespace['name'];

                foreach ($this->imports as $import) {
                    if ($import['line'] <= $line && $this->lineLiesWithinNamespaceRange($import['line'], $namespace)) {
                        $relevantImports[] = $import;
                    }
                }

                break;
            }
        }

        return $this->typeResolver->resolve($type, $namespaceFqcn, $relevantImports);
    }

    /**
     * @param int   $line
     * @param array $namespace
     *
     * @return bool
     */
    protected function lineLiesWithinNamespaceRange($line, array $namespace)
    {
        return (
            $line >= $namespace['startLine'] &&
            ($line <= $namespace['endLine'] || $namespace['endLine'] === null)
        );
    }
}
