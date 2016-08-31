<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Indexing;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Indexing\Scanner;
use PhpIntegrator\Indexing\FileIndexer;
use PhpIntegrator\Indexing\BuiltinIndexer;
use PhpIntegrator\Indexing\ProjectIndexer;
use PhpIntegrator\Indexing\StorageInterface;
use PhpIntegrator\Indexing\CallbackStorageProxy;

use PhpIntegrator\Parsing\PartialParser;
use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\UserInterface\ClasslikeInfoBuilderProviderCachingProxy;

/**
 * Command that reindexes a file or folder.
 */
class ReindexCommand extends AbstractCommand
{
    /**
     * @var ProjectIndexer
     */
    protected $projectIndexer;

    /**
     * @var FileIndexer
     */
    protected $fileIndexer;

    /**
     * @var BuiltinIndexer
     */
    protected $builtinIndexer;

    /**
     * @var Scanner
     */
    protected $scanner;

    /**
     * @var array
     */
    protected $fileModifiedMap;

    /**
     * @var ClassListCommand
     */
    protected $classListCommand;

    /**
     * @var ClassInfoCommand
     */
    protected $classInfoCommand;

    /**
     * @var ResolveTypeCommand
     */
    protected $resolveTypeCommand;

    /**
     * @var GlobalFunctionsCommand
     */
    protected $globalFunctionsCommand;

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
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var TypeDeducer
     */
    protected $typeDeducer;

    /**
     * @var StorageInterface
     */
    protected $storageForIndexers;

    /**
     * @inheritDoc
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {
        $optionCollection->add('source+', 'The file or directory to index. Can be passed multiple times to process multiple items at once.')->isa('string');
        $optionCollection->add('exclude+', 'An absolute path to exclude. Can be passed multiple times.')->isa('string');
        $optionCollection->add('extension+', 'An extension (without leading dot) to index. Can be passed multiple times.')->isa('string');
        $optionCollection->add('stdin?', 'If set, file contents will not be read from disk but the contents from STDIN will be used instead.');
        $optionCollection->add('v|verbose?', 'If set, verbose output will be displayed.');
        $optionCollection->add('s|stream-progress?', 'If set, progress will be streamed. Incompatible with verbose mode.');
    }

    /**
     * @inheritDoc
     */
    protected function process(ArrayAccess $arguments)
    {
        if (!isset($arguments['source']) || empty($arguments['source'])) {
            throw new UnexpectedValueException('At least one file or directory to index is required for this command.');
        }

        $success = $this->reindex(
            $arguments['source']->value,
            isset($arguments['stdin']),
            isset($arguments['verbose']),
            isset($arguments['stream-progress']),
            isset($arguments['exclude'], $arguments['exclude']->value) ? $arguments['exclude']->value : [],
            isset($arguments['extension'], $arguments['extension']->value) ? $arguments['extension']->value : []
        );

        return $this->outputJson($success, []);
    }

    /**
     * @param string[] $paths
     * @param bool     $useStdin
     * @param bool     $showOutput
     * @param bool     $doStreamProgress
     * @param string[] $excludedPaths
     * @param string[] $extensionsToIndex
     *
     * @return bool
     */
    public function reindex(
        array $paths,
        $useStdin,
        $showOutput,
        $doStreamProgress,
        array $excludedPaths = [],
        array $extensionsToIndex = ['php']
    ) {
        if ($useStdin) {
            if (count($paths) > 1) {
                throw new UnexpectedValueException('Reading from STDIN is only possible when a single path is specified!');
            } elseif (!is_file($paths[0])) {
                throw new UnexpectedValueException('Reading from STDIN is only possible for a single file!');
            }
        }

        $success = true;
        $exception = null;

        try {
            // Yes, we abuse the error channel...
            $loggingStream = $showOutput ? fopen('php://stdout', 'w') : null;
            $progressStream = $doStreamProgress ? fopen('php://stderr', 'w') : null;

            try {
                $this->getProjectIndexer()
                    ->setProgressStream($progressStream)
                    ->setLoggingStream($loggingStream);

                $sourceOverrideMap = [];

                if ($useStdin) {
                    $sourceOverrideMap[$paths[0]] = $this->getSourceCodeStreamReader()->getSourceCodeFromStdin();
                }

                $this->getProjectIndexer()->index($paths, $extensionsToIndex, $excludedPaths, $sourceOverrideMap);
            } catch (Indexing\IndexingFailedException $e) {
                $success = false;
            }

            if ($loggingStream) {
                fclose($loggingStream);
            }

            if ($progressStream) {
                fclose($progressStream);
            }
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($exception) {
            throw $exception;
        }

        return $success;
    }

    /**
     * @return ProjectIndexer
     */
    protected function getProjectIndexer()
    {
        if (!$this->projectIndexer) {
            $this->projectIndexer = new ProjectIndexer(
                $this->getStorageForIndexers(),
                $this->getBuiltinIndexer(),
                $this->getFileIndexer(),
                $this->getScanner(),
                $this->getSourceCodeStreamReader()
            );
        }

        return $this->projectIndexer;
    }

    /**
     * @return FileIndexer
     */
    protected function getFileIndexer()
    {
        if (!$this->fileIndexer) {
            $this->fileIndexer = new FileIndexer(
                $this->getStorageForIndexers(),
                $this->getTypeAnalyzer(),
                $this->getDocblockParser(),
                $this->getTypeDeducer(),
                $this->getParser()
            );
        }

        return $this->fileIndexer;
    }

    /**
     * @return BuiltinIndexer
     */
    protected function getBuiltinIndexer()
    {
        if (!$this->builtinIndexer) {
            $this->builtinIndexer = new BuiltinIndexer($this->getStorageForIndexers(), $this->getTypeAnalyzer());
        }

        return $this->builtinIndexer;
    }

    /**
     * @return StorageInterface
     */
    protected function getStorageForIndexers()
    {
        if (!$this->storageForIndexers) {
            $this->storageForIndexers = new CallbackStorageProxy($this->getIndexDatabase(), function ($fqcn) {
                $provider = $this->getClasslikeInfoBuilderProvider();

                if ($provider instanceof ClasslikeInfoBuilderProviderCachingProxy) {
                    $provider->clearCacheFor($fqcn);
                }
            });
        }

        return $this->storageForIndexers;
    }

    /**
     * @return Scanner
     */
    protected function getScanner()
    {
        if (!$this->scanner) {
            $this->scanner = new Scanner($this->getFileModifiedMap());
        }

        return $this->scanner;
    }

    /**
     * @return array
     */
    protected function getFileModifiedMap()
    {
        if (!$this->fileModifiedMap) {
            $this->fileModifiedMap = $this->getIndexDatabase()->getFileModifiedMap();
        }

        return $this->fileModifiedMap;
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
                $this->getClassInfoCommand(),
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
