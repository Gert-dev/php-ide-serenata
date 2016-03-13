<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Application\Command as BaseCommand;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Command that lints a file's semantics (i.e. it does not deal with syntax errors, as this is already handled by the
 * indexer).
 */
class SemanticLint extends BaseCommand
{
    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file?', 'The file to lint.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('A file name is required for this command.');
        }

        $output = $this->semanticLint($arguments['file']->value, isset($arguments['stdin']));

        return $this->outputJson(true, $output);
    }

    /**
     * @param string $file
     * @param bool   $useStdin
     *
     * @return array
     */
    public function semanticLint($file, $useStdin)
    {
        $fileId = $this->indexDatabase->getFileId($file);

        if (!$fileId) {
            throw new UnexpectedValueException('The specified file is not present in the index!');
        }

        $code = null;

        if ($useStdin) {
            // NOTE: This call is blocking if there is no input!
            $code = file_get_contents('php://stdin');
        } else {
            $code = @file_get_contents($file);
        }

        // Parse the file to fetch the information we need.
        $nodes = [];
        $parser = $this->getParser();

        try {
            $nodes = $parser->parse($code);
        } catch (Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        $unknownClassAnalyzer = new SemanticLint\UnknownClassAnalyzer($file, $this->indexDatabase);
        $unusedUseStatementAnalyzer = new SemanticLint\UnusedUseStatementAnalyzer();

        $traverser = new NodeTraverser(false);

        foreach ($unknownClassAnalyzer->getVisitors() as $visitor) {
            $traverser->addVisitor($visitor);
        }

        foreach ($unusedUseStatementAnalyzer->getVisitors() as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($nodes);

        return [
            'errors' => [
                'unknownClasses' => $unknownClassAnalyzer->getOutput()
            ],

            'warnings' => [
                'unusedUseStatements' => $unusedUseStatementAnalyzer->getOutput()
            ]
        ];
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

            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
        }

        return $this->parser;
    }
}
