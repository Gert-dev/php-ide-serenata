<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Linting;
use PhpIntegrator\Analysis\ClasslikeExistanceChecker;
use PhpIntegrator\Analysis\GlobalConstantExistanceChecker;
use PhpIntegrator\Analysis\GlobalFunctionExistanceChecker;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Parsing\PartialParser;
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
     * @var ClassListCommand
     */
    protected $classListCommand;

    /**
     * @var PartialParser
     */
    protected $partialParser;

    /**
     * @var ClasslikeExistanceChecker
     */
    protected $classlikeExistanceChecker;

    /**
     * @var GlobalFunctionExistanceChecker
     */
    protected $globalFunctionExistanceChecker;

    /**
     * @var GlobalConstantExistanceChecker
     */
    protected $globalConstantExistanceChecker;

    /**
     * @var TypeResolver
     */
    protected $typeResolver;

    /**
     * @var FileTypeResolverFactory
     */
    protected $fileTypeResolverFactory;

    /**
     * @var TypeDeducer
     */
    protected $typeDeducer;

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
            $code = $this->getSourceCodeStreamReader()->getSourceCodeFromFile($arguments['file']->value);
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
                $fileTypeResolver = $this->getFileTypeResolverFactory()->create($file);

                $unknownClassAnalyzer = new Linting\UnknownClassAnalyzer(
                    $this->getClasslikeExistanceChecker(),
                    $fileTypeResolver,
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
                    $this->getTypeDeducer(),
                    $this->getClasslikeInfoBuilder(),
                    $this->getTypeAnalyzer(),
                    $file,
                    $code
                );

                foreach ($unknownMemberAnalyzer->getVisitors() as $visitor) {
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
                // These analyzers needs to traverse the nodes separately as it modifies them.
                $traverser = new NodeTraverser(false);

                $docblockCorrectnessAnalyzer = new Linting\DocblockCorrectnessAnalyzer(
                    $code,
                    $this->getClasslikeInfoBuilder(),
                    $this->getDocblockParser(),
                    $this->getTypeAnalyzer(),
                    $this->getDocblockAnalyzer()
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

                // The NameResolver modifies nodes as it goes along. We can't use NodeTraverser's clone functionality
                // as that only (shallowly) clones the nodes themselves, but not their names.
                $nodes = $parser->parse($code);
            }

            $unknownGlobalConstantAnalyzer = null;
            $unknownGlobalFunctionAnalyzer = null;

            if ($unknownGlobalConstantAnalyzer || $retrieveUnknownGlobalFunctions) {
                // These analyzers needs to traverse the nodes separately as it modifies them.
                $traverser = new NodeTraverser(false);

                if ($retrieveUnknownGlobalFunctions) {
                    $unknownGlobalConstantAnalyzer = new Linting\UnknownGlobalConstantAnalyzer(
                        $this->getGlobalConstantExistanceChecker()
                    );

                    foreach ($unknownGlobalConstantAnalyzer->getVisitors() as $visitor) {
                        $traverser->addVisitor($visitor);
                    }
                }

                if ($retrieveUnknownGlobalFunctions) {
                    $unknownGlobalFunctionAnalyzer = new Linting\UnknownGlobalFunctionAnalyzer(
                        $this->getGlobalFunctionExistanceChecker()
                    );

                    foreach ($unknownGlobalFunctionAnalyzer->getVisitors() as $visitor) {
                        $traverser->addVisitor($visitor);
                    }
                }

                try {
                    $traverser->traverse($nodes);
                } catch (Error $e) {
                    // The NameResolver can throw exceptions on things such as duplicate use statements. Seeing as that is
                    // a PHP error, just fetch any output at all.
                    $unknownGlobalConstantAnalyzer = null;
                    $unknownGlobalFunctionAnalyzer = null;
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
     * Retrieves an instance of ClasslikeExistanceChecker. The object will only be created once if needed.
     *
     * @return ClasslikeExistanceChecker
     */
    protected function getClasslikeExistanceChecker()
    {
        if (!$this->classlikeExistanceChecker instanceof ClasslikeExistanceChecker) {
            $this->classlikeExistanceChecker = new ClasslikeExistanceChecker($this->getIndexDatabase());
        }

        return $this->classlikeExistanceChecker;
    }

    /**
     * Retrieves an instance of GlobalFunctionExistanceChecker. The object will only be created once if needed.
     *
     * @return GlobalFunctionExistanceChecker
     */
    protected function getGlobalFunctionExistanceChecker()
    {
        if (!$this->globalFunctionExistanceChecker instanceof GlobalFunctionExistanceChecker) {
            $this->globalFunctionExistanceChecker = new GlobalFunctionExistanceChecker($this->getIndexDatabase());
        }

        return $this->globalFunctionExistanceChecker;
    }

    /**
     * Retrieves an instance of GlobalConstantExistanceChecker. The object will only be created once if needed.
     *
     * @return GlobalConstantExistanceChecker
     */
    protected function getGlobalConstantExistanceChecker()
    {
        if (!$this->globalConstantExistanceChecker instanceof GlobalConstantExistanceChecker) {
            $this->globalConstantExistanceChecker = new GlobalConstantExistanceChecker($this->getIndexDatabase());
        }

        return $this->globalConstantExistanceChecker;
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
