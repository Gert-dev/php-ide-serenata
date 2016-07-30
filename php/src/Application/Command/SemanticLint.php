<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\DocParser;
use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Error;
use PhpParser\Lexer;
use PhpParser\Parser;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Command that lints a file's semantics (i.e. it does not deal with syntax errors, as this is already handled by the
 * indexer).
 */
class SemanticLint extends AbstractCommand
{
    /**
     * @var ClassInfo
     */
    protected $classInfoCommand;

    /**
     * @var DeduceTypes
     */
    protected $deduceTypesCommand;

    /**
     * @var GlobalFunctions
     */
    protected $globalFunctions;

    /**
     * @var GlobalConstants
     */
    protected $globalConstants;

    /**
     * @var ResolveType
     */
    protected $resolveTypeCommand;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var DocParser
     */
    protected $docParser;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('file?', 'The file to lint.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('no-unknown-classes?', 'If set, unknown class names will not be returned.');
        $optionCollection->add('no-unknown-members?', 'If set, unknown class member linting will not be performed.');
        $optionCollection->add('no-unknown-global-functions?', 'If set, unknown global function linting will not be performed.');
        $optionCollection->add('no-unknown-global-constants?', 'If set, unknown global constant linting will not be performed.');
        $optionCollection->add('no-docblock-correctness?', 'If set, docblock correctness will not be analyzed.');
        $optionCollection->add('no-unused-use-statements?', 'If set, unused use statements will not be returned.');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['file'])) {
            throw new UnexpectedValueException('A file name is required for this command.');
        }

        $code = $this->getSourceCodeHelper()->getSourceCode(
            $arguments['file']->value,
            (isset($arguments['stdin']) && $arguments['stdin']->value)
        );

        $output = $this->semanticLint(
            $arguments['file']->value,
            $code,
            !(isset($arguments['no-unknown-classes']) && $arguments['no-unknown-classes']->value),
            !(isset($arguments['no-unknown-members']) && $arguments['no-unknown-members']->value),
            !(isset($arguments['no-unknown-global-functions']) && $arguments['no-unknown-global-functions']->value),
            !(isset($arguments['no-unknown-global-constants']) && $arguments['no-unknown-global-constants']->value),
            !(isset($arguments['no-docblock-correctness']) && $arguments['no-docblock-correctness']->value),
            !(isset($arguments['no-unused-use-statements']) && $arguments['no-unused-use-statements']->value)
        );

        return $this->outputJson(true, $output);
    }

    /**
     * @param string $file
     * @param string $code
     * @param bool   $retrieveUnknownClasses
     * @param bool   $retrieveUnknownMembers
     * @param bool   $retrieveUnknownGlobalFunctions
     * @param bool   $retrieveUnknownGlobalConstants
     * @param bool   $analyzeDocblockCorrectness
     * @param bool   $retrieveUnusedUseStatements
     *
     * @return array
     */
    public function semanticLint(
        $file,
        $code,
        $retrieveUnknownClasses = true,
        $retrieveUnknownMembers = true,
        $retrieveUnknownGlobalFunctions = true,
        $retrieveUnknownGlobalConstants = true,
        $analyzeDocblockCorrectness = true,
        $retrieveUnusedUseStatements = true
    ) {
        // Parse the file to fetch the information we need.
        $nodes = [];
        $parser = $this->getParser();

        $nodes = $parser->parse($code);

        $output = [
            'errors'   => [
                'syntaxErrors' => []
            ],

            'warnings' => []
        ];

        foreach ($parser->getErrors() as $e) {
            $output['errors']['syntaxErrors'][] = [
                'startLine'   => $e->getStartLine() >= 0 ? $e->getStartLine() : null,
                'endLine'     => $e->getEndLine() >= 0 ? $e->getEndLine() : null,
                'startColumn' => $e->hasColumnInfo() ? $e->getStartColumn($code) : null,
                'endColumn'   => $e->hasColumnInfo() ? $e->getEndColumn($code) : null,
                'message'     => $e->getMessage()
            ];
        }

        if ($nodes !== null) {
            $traverser = new NodeTraverser(false);

            $unknownClassAnalyzer = null;

            if ($retrieveUnknownClasses) {
                $unknownClassAnalyzer = new SemanticLint\UnknownClassAnalyzer(
                    $file,
                    $this->indexDatabase,
                    $this->getResolveTypeCommand(),
                    $this->getTypeAnalyzer(),
                    $this->getDocParser()
                );

                foreach ($unknownClassAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unknownMemberAnalyzer = null;

            if ($retrieveUnknownMembers) {
                $unknownMemberAnalyzer = new SemanticLint\UnknownMemberAnalyzer(
                    $this->getDeduceTypesCommand(),
                    $this->getClassInfoCommand(),
                    $this->getResolveTypeCommand(),
                    $this->getTypeAnalyzer(),
                    $file,
                    $code
                );

                foreach ($unknownMemberAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unknownGlobalFunctionAnalyzer = null;

            if ($retrieveUnknownGlobalFunctions) {
                $unknownGlobalFunctionAnalyzer = new SemanticLint\UnknownGlobalFunctionAnalyzer(
                    $this->getGlobalFunctionsCommand(),
                    $this->getTypeAnalyzer()
                );

                foreach ($unknownGlobalFunctionAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unknownGlobalConstantAnalyzer = null;

            if ($retrieveUnknownGlobalFunctions) {
                $unknownGlobalConstantAnalyzer = new SemanticLint\UnknownGlobalConstantAnalyzer(
                    $this->getGlobalConstantsCommand(),
                    $this->getTypeAnalyzer()
                );

                foreach ($unknownGlobalConstantAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unusedUseStatementAnalyzer = null;

            if ($retrieveUnusedUseStatements) {
                $unusedUseStatementAnalyzer = new SemanticLint\UnusedUseStatementAnalyzer(
                    $this->getTypeAnalyzer(),
                    $this->getDocParser()
                );

                foreach ($unusedUseStatementAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $traverser->traverse($nodes);

            $docblockCorrectnessAnalyzer = null;

            if ($analyzeDocblockCorrectness) {
                $fileId = $this->indexDatabase->getFileId($file);

                if (!$fileId) {
                    throw new UnexpectedValueException('The specified file is not present in the index!');
                }

                // This analyzer needs to traverse the nodes separately as it modifies them.
                $traverser = new NodeTraverser(false);

                $docblockCorrectnessAnalyzer = new SemanticLint\DocblockCorrectnessAnalyzer(
                    $code,
                    $this->indexDatabase,
                    $this->getClassInfoCommand()
                );

                foreach ($docblockCorrectnessAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }

                try {
                    $traverser->traverse($nodes);
                } catch (Error $e) {
                    // The NameResolver can throw exceptions on things such as duplicate use statements. Seeing as that is
                    // a PHP error, just fetch any output at all.
                    $docblockCorrectnessAnalyzer = null;
                }
            }

            if ($unknownClassAnalyzer) {
                $output['errors']['unknownClasses'] = $unknownClassAnalyzer->getOutput();
            }

            if ($unknownMemberAnalyzer) {
                $analyzerOutput = $unknownMemberAnalyzer->getOutput();

                $output['errors']['unknownMembers']   = $analyzerOutput['errors'];
                $output['warnings']['unknownMembers'] = $analyzerOutput['warnings'];
            }

            if ($unknownGlobalFunctionAnalyzer) {
                $output['errors']['unknownGlobalFunctions'] = $unknownGlobalFunctionAnalyzer->getOutput();
            }

            if ($unknownGlobalConstantAnalyzer) {
                $output['errors']['unknownGlobalConstants'] = $unknownGlobalConstantAnalyzer->getOutput();
            }

            if ($docblockCorrectnessAnalyzer) {
                $output['warnings']['docblockIssues'] = $docblockCorrectnessAnalyzer->getOutput();
            }

            if ($unusedUseStatementAnalyzer) {
                $output['warnings']['unusedUseStatements'] = $unusedUseStatementAnalyzer->getOutput();
            }
        }

        return $output;
    }

    /**
     * @inheritDoc
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        if ($this->classInfoCommand) {
            $this->getClassInfoCommand()->setIndexDatabase($indexDatabase);
        }

        parent::setIndexDatabase($indexDatabase);
    }

    /**
     * @return ClassInfo
     */
    protected function getClassInfoCommand()
    {
        if (!$this->classInfoCommand) {
            $this->classInfoCommand = new ClassInfo($this->getParser(), $this->cache);
            $this->classInfoCommand->setIndexDatabase($this->indexDatabase);
        }

        return $this->classInfoCommand;
    }

    /**
     * @return DeduceTypes
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
     * @return GlobalFunctions
     */
    protected function getGlobalFunctionsCommand()
    {
        if (!$this->globalFunctions) {
            $this->globalFunctions = new GlobalFunctions($this->getParser(), $this->cache);
            $this->globalFunctions->setIndexDatabase($this->indexDatabase);
        }

        return $this->globalFunctions;
    }

    /**
     * @return GlobalConstants
     */
    protected function getGlobalConstantsCommand()
    {
        if (!$this->globalConstants) {
            $this->globalConstants = new GlobalConstants($this->getParser(), $this->cache);
            $this->globalConstants->setIndexDatabase($this->indexDatabase);
        }

        return $this->globalConstants;
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

    /**
     * @return DocParser
     */
    protected function getDocParser()
    {
        if (!$this->docParser) {
            $this->docParser = new DocParser();
        }

        return $this->docParser;
    }
}
