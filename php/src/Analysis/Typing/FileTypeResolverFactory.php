<?php

namespace PhpIntegrator\Analysis\Typing;

use UnexpectedValueException;

/**
 * Factory that creates instances of {@see FileTypeResolver}.
 */
class FileTypeResolverFactory
{
    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @var NamespaceImportProviderInterface
     */
    protected $namespaceImportProviderInterface;

    /**
     * @param TypeResolver                     $typeResolver
     * @param NamespaceImportProviderInterface $namespaceImportProviderInterface
     */
    public function __construct(
        TypeResolver $typeResolver,
        NamespaceImportProviderInterface $namespaceImportProviderInterface
    ) {
        $this->typeResolver = $typeResolver;
        $this->namespaceImportProviderInterface = $namespaceImportProviderInterface;
    }

    /**
     * @param string $filePath
     *
     * @throws UnexpectedValueException if no namespaces exist for a file.
     *
     * @return TypeResolver
     */
    public function create($filePath)
    {
        $namespaces = $this->namespaceImportProviderInterface->getNamespacesForFile($filePath);

        if (empty($namespaces)) {
            throw new UnexpectedValueException(
                'No namespace found, but there should always exist at least one namespace row in the database!'
            );
        }

        $useStatements = $this->namespaceImportProviderInterface->getUseStatementsForFile($filePath);

        return new FileTypeResolver($this->typeResolver, $namespaces, $useStatements);
    }
}
