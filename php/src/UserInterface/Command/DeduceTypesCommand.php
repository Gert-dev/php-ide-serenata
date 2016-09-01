<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Analysis\Visiting\TypeQueryingVisitor;

use PhpIntegrator\Parsing\PartialParser;
use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\Utility\SourceCodeHelpers;

/**
 * Allows deducing the types of an expression (e.g. a call chain, a simple string, ...).
 */
class DeduceTypesCommand extends AbstractCommand
{
    /**
     * @var ClassListCommand
     */
    protected $classListCommand;

    /**
     * @var ResolveTypeCommand
     */
    protected $resolveTypeCommand;

    /**
     * @var GlobalFunctionsCommand
     */
    protected $globalFunctionsCommand;

    /**
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var PartialParser
     */
    protected $partialParser;

    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @var FileTypeResolverFactory
     */
    protected $fileTypeResolverFactory;

    /**
     * @var TypeQueryingVisitor
     */
    protected $typeQueryingVisitor;

    /**
     * @var TypeDeducer
     */
    protected $typeDeducer;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file:', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('charoffset?', 'If set, the input offset will be treated as a character offset instead of a byte offset.');
        $optionCollection->add('part+', 'A part of the expression as string. Specify this as many times as you have parts.')->isa('string');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
        $optionCollection->add('ignore-last-element?', 'If set, when determining the parts automatically, the last part of the expression will be ignored (i.e. because it may not be complete).');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('A --file must be supplied!');
        } elseif (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        }

        if (isset($arguments['stdin']) && $arguments['stdin']->value) {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromStdin();
        } else {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromFile($arguments['file']->value);
        }

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = SourceCodeHelpers::getByteOffsetFromCharacterOffset($offset, $code);
        }

        $parts = [];

        if (isset($arguments['part'])) {
            $parts = $arguments['part']->value;
        } else {
            $parts = $this->getPartialParser()->retrieveSanitizedCallStackAt(substr($code, 0, $offset));

            if (!empty($parts) && isset($arguments['ignore-last-element']) && $arguments['ignore-last-element']) {
                array_pop($parts);
            }
        }

        $result = $this->getTypeDeducer()->deduceTypes(
           isset($arguments['file']) ? $arguments['file']->value : null,
           $code,
           $parts,
           $offset
        );

        return $this->outputJson(true, $result);
    }

    /**
     * Retrieves an instance of TypeDeducer. The object will only be created once if needed.
     *
     * @return TypeDeducer
     */
    protected function getTypeDeducer()
    {
        if (!$this->typeDeducer instanceof TypeDeducer) {
            $this->typeDeducer = new TypeDeducer(
                $this->getParser(),
                $this->getClassListCommand(),
                $this->getDocblockParser(),
                $this->getPartialParser(),
                $this->getTypeAnalyzer(),
                $this->getTypeResolver(),
                $this->getFileTypeResolverFactory(),
                $this->getIndexDatabase(),
                $this->getClasslikeInfoBuilder(),
                $this->getFunctionConverter()
            );
        }

        return $this->typeDeducer;
    }

    /**
     * @return ClassListCommand
     */
    protected function getClassListCommand()
    {
        if (!$this->classListCommand) {
            $this->classListCommand = new ClassListCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->classListCommand;
    }

    /**
     * Retrieves an instance of DocblockParser. The object will only be created once if needed.
     *
     * @return DocblockParser
     */
    protected function getDocblockParser()
    {
        if (!$this->docblockParser instanceof DocblockParser) {
            $this->docblockParser = new DocblockParser();
        }

        return $this->docblockParser;
    }

    /**
     * Retrieves an instance of PartialParser. The object will only be created once if needed.
     *
     * @return PartialParser
     */
    protected function getPartialParser()
    {
        if (!$this->partialParser instanceof PartialParser) {
            $this->partialParser = new PartialParser();
        }

        return $this->partialParser;
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
