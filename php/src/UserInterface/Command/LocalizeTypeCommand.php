<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Typing\TypeLocalizer;
use PhpIntegrator\Analysis\Typing\FileTypeLocalizerFactory;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Command that makes a FQCN relative to local use statements in a file.
 */
class LocalizeTypeCommand extends AbstractCommand
{
    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var FileTypeLocalizerFactory
     */
    protected $fileTypeLocalizerFactory;



    public function __construct(IndexDatabase $indexDatabase, FileTypeLocalizerFactory $fileTypeLocalizerFactory)
    {
        $this->indexDatabase = $indexDatabase;
        $this->fileTypeLocalizerFactory = $fileTypeLocalizerFactory;
    }




    /**
     * @inheritDoc
     */
    public function attachOptions(OptionCollection $optionCollection)
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

        $type = $this->localizeType($arguments['type']->value, $arguments['file']->value, $arguments['line']->value);

        return $this->outputJson(true, $type);
    }

    /**
     * Resolves the type.
     *
     * @param string $type
     * @param string $file
     * @param int    $line
     */
    public function localizeType($type, $file, $line)
    {
        $fileId = $this->indexDatabase->getFileId($file);

        if (!$fileId) {
            throw new UnexpectedValueException('The specified file is not present in the index!');
        }

        return $this->fileTypeLocalizerFactory->create($file)->resolve($type, $line);
    }
}
