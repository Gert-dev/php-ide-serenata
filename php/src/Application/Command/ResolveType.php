<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use LogicException;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\TypeResolver;
use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Application\Command as BaseCommand;

/**
 * Command that resolves local types in a file.
 */
class ResolveType extends BaseCommand
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
        $fileId = $this->indexDatabase->getFileId($file);

        if (!$fileId) {
            throw new UnexpectedValueException('The specified file is not present in the index!');
        }

        $namespace = $this->indexDatabase->getRelevantNamespace($file, $line);

        if (!$namespace) {
            throw new LogicException(
                'No namespace found, but there should always exist at least one namespace row in the database!'
            );
        }

        $useStatements = $this->indexDatabase->getUseStatementsByNamespaceId(
            $namespace['id'],
            $line
        );

        $useStatements = iterator_to_array($useStatements);

        $typeResolver = new TypeResolver($namespace['namespace'], $useStatements);

        return $typeResolver->resolve($type);
    }
}
