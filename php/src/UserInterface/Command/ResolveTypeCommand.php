<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

/**
 * Command that resolves local types in a file.
 */
class ResolveTypeCommand extends AbstractCommand
{
    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @var FileTypeResolverFactory
     */
    protected $fileTypeResolverFactory;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('line:', 'The line on which the type can be found, line 1 being the first line.')->isa('number');
        $optionCollection->add('type:', 'The name of the type to resolve.')->isa('string');
        $optionCollection->add('file:', 'The file in which the type needs to be resolved..')->isa('string');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['type'])) {
            throw new UnexpectedValueException('The type is required for this command.');
        } elseif (!isset($arguments['file'])) {
            throw new UnexpectedValueException('A file name is required for this command.');
        } elseif (!isset($arguments['line'])) {
            throw new UnexpectedValueException('A line number is required for this command.');
        }

        $type = $this->resolveType($arguments['type']->value, $arguments['file']->value, $arguments['line']->value);

        return $this->outputJson(true, $type);
    }

    /**
     * Resolves the type.
     *
     * @param string $type
     * @param string $file
     * @param int    $line
     *
     * @throws UnexpectedValueException
     *
     * @return string|null
     */
    public function resolveType($type, $file, $line)
    {
        $fileId = $this->getIndexDatabase()->getFileId($file);

        if (!$fileId) {
            throw new UnexpectedValueException('The specified file is not present in the index!');
        }

        return $this->getFileTypeResolverFactory()->create($file)->resolve($type, $line);
    }

    /**
     * Retrieves an instance of FileTypeResolverFactory. The object will only be created once if needed.
     *
     * @return FileTypeResolverFactory
     */
    protected function getFileTypeResolverFactory()
    {
        if (!$this->fileTypeResolverFactory instanceof FileTypeResolverFactory) {
            $this->fileTypeResolverFactory = new FileTypeResolverFactory(
                $this->getTypeResolver(),
                $this->getIndexDatabase()
            );
        }

        return $this->fileTypeResolverFactory;
    }

    /**
     * Retrieves an instance of TypeResolver. The object will only be created once if needed.
     *
     * @return TypeResolver
     */
    protected function getTypeResolver()
    {
        if (!$this->typeResolver instanceof TypeResolver) {
            $this->typeResolver = new TypeResolver($this->getTypeAnalyzer());
        }

        return $this->typeResolver;
    }
}
