<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Linting;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\Parsing\DocblockParser;

use PhpParser\Error;
use PhpParser\NodeTraverser;

/**
 * Command that lints a file's semantics (i.e. it does not deal with syntax errors, as this is already handled by the
 * indexer).
 */
class SemanticLintCommand extends AbstractCommand
{
    /**
     * @var ClassInfoCommand
     */
    protected $classInfoCommand;

    /**
     * @var DeduceTypesCommand
     */
    protected $deduceTypesCommand;

    /**
     * @var GlobalFunctionsCommand
     */
    protected $globalFunctions;

    /**
     * @var GlobalConstantsCommand
     */
    protected $globalConstants;

    /**
     * @var ResolveTypeCommand
     */
    protected $resolveTypeCommand;

    /**
     * @var DocblockParser
     */
    protected $docblockParser;

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

        $code = null;

        if (isset($arguments['stdin']) && $arguments['stdin']->value) {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromStdin();
        } else {
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromFile($arguments['file']);
        }

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
                $unknownClassAnalyzer = new Linting\UnknownClassAnalyzer(
                    $file,
                    $this->getIndexDatabase(),
                    $this->getResolveTypeCommand(),
                    $this->getTypeAnalyzer(),
                    $this->getDocblockParser()
                );

                foreach ($unknownClassAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unknownMemberAnalyzer = null;

            if ($retrieveUnknownMembers) {
                $unknownMemberAnalyzer = new Linting\UnknownMemberAnalyzer(
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
                $unknownGlobalFunctionAnalyzer = new Linting\UnknownGlobalFunctionAnalyzer(
                    $this->getGlobalFunctionsCommand(),
                    $this->getTypeAnalyzer()
                );

                foreach ($unknownGlobalFunctionAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unknownGlobalConstantAnalyzer = null;

            if ($retrieveUnknownGlobalFunctions) {
                $unknownGlobalConstantAnalyzer = new Linting\UnknownGlobalConstantAnalyzer(
                    $this->getGlobalConstantsCommand(),
                    $this->getTypeAnalyzer()
                );

                foreach ($unknownGlobalConstantAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $unusedUseStatementAnalyzer = null;

            if ($retrieveUnusedUseStatements) {
                $unusedUseStatementAnalyzer = new Linting\UnusedUseStatementAnalyzer(
                    $this->getTypeAnalyzer(),
                    $this->getDocblockParser()
                );

                foreach ($unusedUseStatementAnalyzer->getVisitors() as $visitor) {
                    $traverser->addVisitor($visitor);
                }
            }

            $traverser->traverse($nodes);

            $docblockCorrectnessAnalyzer = null;

            if ($analyzeDocblockCorrectness) {
                $fileId = $this->getIndexDatabase()->getFileId($file);

                if (!$fileId) {
                    throw new UnexpectedValueException('The specified file is not present in the index!');
                }

                // This analyzer needs to traverse the nodes separately as it modifies them.
                $traverser = new NodeTraverser(false);

                $docblockCorrectnessAnalyzer = new Linting\DocblockCorrectnessAnalyzer(
                    $code,
                    $this->getIndexDatabase(),
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
     * @return ClassInfoCommand
     */
    protected function getClassInfoCommand()
    {
        if (!$this->classInfoCommand) {
            $this->classInfoCommand = new ClassInfoCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->classInfoCommand;
    }

    /**
     * @return DeduceTypesCommand
     */
    protected function getDeduceTypesCommand()
    {
        if (!$this->deduceTypesCommand) {
            $this->deduceTypesCommand = new DeduceTypesCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->deduceTypesCommand;
    }

    /**
     * @return ResolveTypeCommand
     */
    protected function getResolveTypeCommand()
    {
        if (!$this->resolveTypeCommand) {
            $this->resolveTypeCommand = new ResolveTypeCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->resolveTypeCommand;
    }

    /**
     * @return GlobalFunctionsCommand
     */
    protected function getGlobalFunctionsCommand()
    {
        if (!$this->globalFunctions) {
            $this->globalFunctions = new GlobalFunctionsCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->globalFunctions;
    }

    /**
     * @return GlobalConstantsCommand
     */
    protected function getGlobalConstantsCommand()
    {
        if (!$this->globalConstants) {
            $this->globalConstants = new GlobalConstantsCommand($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->globalConstants;
    }

    /**
     * @return DocblockParser
     */
    protected function getDocblockParser()
    {
        if (!$this->docblockParser) {
            $this->docblockParser = new DocblockParser();
        }

        return $this->docblockParser;
    }
}
