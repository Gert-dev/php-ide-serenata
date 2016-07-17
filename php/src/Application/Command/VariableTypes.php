<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Command that retrieves information about the types of a variable.
 */
class VariableTypes extends AbstractCommand
{
    /**
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * @var DeduceTypes
     */
    protected $deduceTypesCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file:', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('charoffset?', 'If set, the input offset will be treated as a character offset instead of a byte offset.');
        $optionCollection->add('name:', 'The name of the variable to examine.')->isa('string');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
        } elseif (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        } elseif (!isset($arguments['name'])) {
            throw new UnexpectedValueException('The name of the variable must be set using --name!');
        }

        $code = $this->getSourceCode(
            isset($arguments['file']) ? $arguments['file']->value : null,
            isset($arguments['stdin']) && $arguments['stdin']->value
        );

        $offset = $arguments['offset']->value;

        if (isset($arguments['charoffset']) && $arguments['charoffset']->value == true) {
            $offset = $this->getCharacterOffsetFromByteOffset($offset, $code);
        }

        $result = $this->getVariableTypes(
           isset($arguments['file']) ? $arguments['file']->value : null,
           $code,
           $arguments['name']->value,
           $offset
       );

       return $this->outputJson(true, $result);
    }

    /**
     * @param string     $file
     * @param string     $code
     * @param string     $name
     * @param int        $offset
     *
     * @return string[]
     */
    public function getVariableTypes($file, $code, $name, $offset)
    {
        if (empty($name) || $name[0] !== '$') {
            throw new UnexpectedValueException('The variable name must start with a dollar sign!');
        }

        $parser = $this->getParser();

        try {
            $nodes = $parser->parse($code);
        } catch (Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        $offsetLine = $this->calculateLineByOffset($code, $offset);

        $queryingVisitor = new VariableTypes\QueryingVisitor(
            $file,
            $code,
            $offset,
            $offsetLine,
            mb_substr($name, 1),
            $this->getTypeAnalyzer(),
            $this->getResolveTypeCommand(),
            $this->getDeduceTypesCommand()
        );

        $scopeLimitingVisitor = new Visitor\ScopeLimitingVisitor($offset);

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($scopeLimitingVisitor);
        $traverser->addVisitor($queryingVisitor);
        $traverser->traverse($nodes);

        return $queryingVisitor->getResolvedTypes($file);
    }

    /**
     * @inheritDoc
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        if ($this->resolveTypeCommand) {
            $this->getResolveTypeCommand()->setIndexDatabase($indexDatabase);
        }

        if ($this->deduceTypesCommand) {
            $this->getDeduceTypesCommand()->setIndexDatabase($indexDatabase);
        }

        parent::setIndexDatabase($indexDatabase);
    }

    /**
     * @return ResolveType
     */
    protected function getResolveTypeCommand()
    {
        if (!$this->resolveTypeCommand) {
            $this->resolveTypeCommand = new ResolveType($this->getParser(), $this->cache);
            $this->resolveTypeCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->resolveTypeCommand;
    }

    /**
     * @return ResolveType
     */
    protected function getDeduceTypesCommand()
    {
        if (!$this->deduceTypesCommand) {
            $this->deduceTypesCommand = new DeduceTypes($this->getParser(), $this->cache);
            $this->deduceTypesCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->deduceTypesCommand;
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
