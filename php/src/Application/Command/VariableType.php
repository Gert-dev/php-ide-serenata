<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\IndexDatabase;

use PhpIntegrator\Application\Command as BaseCommand;

use PhpParser\Lexer;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

/**
 * Command that retrieves information about the type of a variable.
 */
class VariableType extends BaseCommand
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file?', 'The file to examine.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('name:', 'The name of the variable to examine.')->isa('string');
        $optionCollection->add('offset:', 'The character byte offset into the code to use for the determination.')->isa('number');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file']) && (!isset($arguments['stdin']) || !$arguments['stdin']->value)) {
            throw new UnexpectedValueException('Either a --file file must be supplied or --stdin must be passed!');
        } elseif (!isset($arguments['offset'])) {
            throw new UnexpectedValueException('An --offset must be supplied into the source code!');
        } elseif (!isset($arguments['name'])) {
            throw new UnexpectedValueException('The name of the variable must be set using --name!');
        }

        $result = $this->getVariableType(
           isset($arguments['file']) ? $arguments['file']->value : null,
           $arguments['name']->value,
           $arguments['offset']->value,
           isset($arguments['stdin']) && $arguments['stdin']->value
       );

       return $this->outputJson(true, $result);
    }

    /**
     * @param string|null $file
     * @param string      $name
     * @param int         $offset
     * @param bool        $isStdin
     */
    public function getVariableType($file, $name, $offset, $isStdin)
    {
        if (empty($name) || $name[0] !== '$') {
            throw new UnexpectedValueException('The variable name must start with a dollar sign!');
        }

        $code = null;

        if ($isStdin) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');
        } else {
            if (!$file) {
                throw new UnexpectedValueException('The specified file does not exist!');
            }

            $code = @file_get_contents($file);
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

        $queryingVisitor = new VariableType\QueryingVisitor(
            $offset,
            $offsetLine,
            mb_substr($name, 1),
            $this->getResolveTypeCommand()
        );

        $scopeLimitingVisitor = new Visitor\ScopeLimitingVisitor($offset);

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($scopeLimitingVisitor);
        $traverser->addVisitor($queryingVisitor);
        $traverser->traverse($nodes);

        return [
            'type'         => $queryingVisitor->getType(),
            'resolvedType' => $queryingVisitor->getResolvedType($file)
        ];
    }

    // TODO: Move somewhere we can reuse this. Calculates the 1-indexed line.
    protected function calculateLineByOffset($source, $offset)
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * @inheritDoc
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        if ($this->resolveTypeCommand) {
            $this->getResolveTypeCommand()->setIndexDatabase($indexDatabase);
        }

        parent::setIndexDatabase($indexDatabase);
    }

    /**
     * @return ResolveType
     */
    protected function getResolveTypeCommand()
    {
        if (!$this->resolveTypeCommand) {
            $this->resolveTypeCommand = new ResolveType();
            $this->resolveTypeCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->resolveTypeCommand;
    }

    /**
     * @return Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $lexer = new Lexer([
                'usedAttributes' => [
                    'comments', 'startLine', 'startFilePos', 'endFilePos'
                ]
            ]);

            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer, [
                'throwOnError' => false
            ]);
        }

        return $this->parser;
    }
}
