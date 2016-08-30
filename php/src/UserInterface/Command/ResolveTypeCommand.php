<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use LogicException;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolver;

/**
 * Command that resolves local types in a file.
 */
class ResolveTypeCommand extends AbstractCommand
{
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
     */
    public function resolveType($type, $file, $line)
    {
        $namespaces = $this->getIndexDatabase()->getNamespacesForFile($file);

        if (empty($namespaces)) {
            $fileId = $this->getIndexDatabase()->getFileId($file);

            if (!$fileId) {
                throw new UnexpectedValueException('The specified file is not present in the index!');
            }

            throw new LogicException(
                'No namespace found, but there should always exist at least one namespace row in the database!'
            );
        }

        $useStatements = $this->getIndexDatabase()->getUseStatementsForFile($file);

        $typeResolver = new TypeResolver($this->getTypeAnalyzer());
        $fileTypeResolver = new FileTypeResolver($typeResolver, $namespaces, $useStatements);

        return $fileTypeResolver->resolve($type, $line);
    }
}
