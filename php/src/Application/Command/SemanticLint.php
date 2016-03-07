<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Application\Command as BaseCommand;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Command that lints a file's semantics (i.e. it does not deal with syntax errors, as this is already handled by the
 * indexer).
 */
class SemanticLint extends BaseCommand
{
    /**
     * @var PhpParser\Parser
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

        $classUsageFetchingVisitor = new SemanticLint\ClassUsageFetchingVisitor();
        $useStatementFetchingVisitor = new SemanticLint\UseStatementFetchingVisitor();

        $traverser = new NodeTraverser(false);
        $traverser->addVisitor($classUsageFetchingVisitor);
        $traverser->addVisitor($useStatementFetchingVisitor);
        $traverser->traverse($nodes);

        // Generate a class map for fast lookups.
        $classMap = [];

        foreach ($this->indexDatabase->getAllStructuralElementsRawInfo(null) as $element) {
            $classMap[$element['fqsen']] = true;
        }

        // Cross-reference the found class names against the class map.
        $unknownClasses = [];
        $namespaces = $useStatementFetchingVisitor->getNamespaces();

        $resolveTypeCommand = new ResolveType();
        $resolveTypeCommand->setIndexDatabase($this->indexDatabase);

        foreach ($classUsageFetchingVisitor->getClassUsageList() as $classUsage) {
            $relevantAlias = $classUsage['firstPart'];

            if (!$classUsage['isFullyQualified'] && isset($namespaces[$classUsage['namespace']]['useStatements'][$relevantAlias])) {
                // Mark the accompanying used statement, if any, as used.
                $namespaces[$classUsage['namespace']]['useStatements'][$relevantAlias]['used'] = true;
            }

            if ($classUsage['isFullyQualified']) {
                $fqsen = $classUsage['name'];
            } else {
                $fqsen = $resolveTypeCommand->resolveType(
                    $classUsage['name'],
                    $file,
                    $classUsage['line']
                );
            }

            if (!isset($classMap[$fqsen])) {
                unset($classUsage['line'], $classUsage['firstPart'], $classUsage['isFullyQualified']);

                $unknownClasses[] = $classUsage;
            }
        }

        $unusedUseStatements = [];

        foreach ($namespaces as $namespace => $namespaceData) {
            $useStatementMap = $namespaceData['useStatements'];

            foreach ($useStatementMap as $alias => $data) {
                if (!array_key_exists('used', $data) || !$data['used']) {
                    $unusedUseStatements[] = $data;
                }
            }
        }

        return [
            'errors' => [
                'unknownClasses' => $unknownClasses
            ],

            'warnings' => [
                'unusedUseStatements' => $unusedUseStatements
            ]
        ];
    }

    /**
     * @return PhpParser\Parser
     */
    protected function getParser()
    {
        if (!$this->parser) {
            $lexer = new Lexer([
                'usedAttributes' => [
                    'startLine', 'startFilePos', 'endFilePos'
                ]
            ]);

            $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7, $lexer);
        }

        return $this->parser;
    }
}
