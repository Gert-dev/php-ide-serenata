<?php

namespace PhpIntegrator\Analysis\Typing;

use LogicException;

/**
 * Factory that creates instances of {@see FileTypeLocalizer}.
 */
class FileTypeLocalizerFactory
{
    /**
     * @var TypeLocalizer
     */
    protected $typeLocalizer;

    /**
     * @var NamespaceImportProviderInterface
     */
    protected $namespaceImportProviderInterface;

    /**
     * @param TypeLocalizer                    $typeLocalizer
     * @param NamespaceImportProviderInterface $namespaceImportProviderInterface
     */
    public function __construct(
        TypeLocalizer $typeLocalizer,
        NamespaceImportProviderInterface $namespaceImportProviderInterface
    ) {
        $this->typeLocalizer = $typeLocalizer;
        $this->namespaceImportProviderInterface = $namespaceImportProviderInterface;
    }

    /**
     * @param string $filePath
     *
     * @throws LogicException if no namespaces exist for a file.
     *
     * @return TypeLocalizer
     */
    public function create($filePath)
    {
        $namespaces = $this->namespaceImportProviderInterface->getNamespacesForFile($filePath);

        if (empty($namespaces)) {
            throw new LogicException(
                'No namespace found, but there should always exist at least one namespace row in the database!'
            );
        }

        $useStatements = $this->namespaceImportProviderInterface->getUseStatementsForFile($filePath);

        return new FileTypeLocalizer($this->typeLocalizer, $namespaces, $useStatements);
    }
}
