<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Indexing;
use PhpIntegrator\TypeAnalyzer;
use PhpIntegrator\Parsing\DocblockParser;
use PhpIntegrator\IndexDataAdapterProviderCachingProxy;

use PhpIntegrator\Indexing\Scanner;
use PhpIntegrator\Indexing\FileIndexer;
use PhpIntegrator\Indexing\BuiltinIndexer;
use PhpIntegrator\Indexing\ProjectIndexer;
use PhpIntegrator\Indexing\StorageInterface;
use PhpIntegrator\Indexing\CallbackStorageProxy;
use PhpIntegrator\Indexing\IndexStorageItemEnum;

use PhpParser\ParserFactory;

/**
 * Command that reindexes a file or folder.
 */
class Reindex extends AbstractCommand
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
     * @var DocblockParser
     */
    protected $docblockParser;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

    /**
     * @var DeduceTypes
     */
    protected $deduceTypes;

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
                $this->getDeduceTypes(),
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
                $provider = $this->getIndexDataAdapterProvider();

                if ($provider instanceof IndexDataAdapterProviderCachingProxy) {
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
     * @return DeduceTypes
     */
    protected function getDeduceTypes()
    {
        if (!$this->deduceTypes) {
            $this->deduceTypes = new DeduceTypes($this->getParser(), $this->cache, $this->getIndexDatabase());
        }

        return $this->deduceTypes;
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
