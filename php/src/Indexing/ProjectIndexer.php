<?php

namespace PhpIntegrator\Indexing;

use UnexpectedValueException;

use PhpIntegrator\Utility\SourceCodeStreamReader;

/**
 * Handles project and folder indexing.
 */
class ProjectIndexer
{
    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * @var BuiltinIndexer
     */
    protected $builtinIndexer;

    /**
     * @var FileIndexer
     */
    protected $fileIndexer;

    /**
     * @var SourceCodeStreamReader
     */
    protected $sourceCodeStreamReader;

    /**
     * @var array
     */
    protected $fileModifiedMap;

    /**
     * @var resource|null
     */
    protected $loggingStream;

    /**
     * @var resource|null
     */
    protected $progressStream;

    /**
     * @param StorageInterface       $storage
     * @param BuiltinIndexer         $builtinIndexer
     * @param FileIndexer            $fileIndexer
     * @param SourceCodeStreamReader $sourceCodeStreamReader
     * @param array                  $fileModifiedMap
     */
    public function __construct(
        StorageInterface $storage,
        BuiltinIndexer $builtinIndexer,
        FileIndexer $fileIndexer,
        SourceCodeStreamReader $sourceCodeStreamReader,
        array $fileModifiedMap
    ) {
        $this->storage = $storage;
        $this->builtinIndexer = $builtinIndexer;
        $this->fileIndexer = $fileIndexer;
        $this->sourceCodeStreamReader = $sourceCodeStreamReader;
        $this->fileModifiedMap = $fileModifiedMap;
    }

    /**
     * @return resource|null
     */
    public function getLoggingStream()
    {
        return $this->loggingStream;
    }

    /**
     * @param resource|null $loggingStream
     *
     * @return static
     */
    public function setLoggingStream($loggingStream)
    {
        $this->builtinIndexer->setLoggingStream($loggingStream);

        $this->loggingStream = $loggingStream;
        return $this;
    }

    /**
     * @return resource|null
     */
    public function getProgressStream()
    {
        return $this->progressStream;
    }

    /**
     * @param resource|null $progressStream
     *
     * @return static
     */
    public function setProgressStream($progressStream)
    {
        $this->progressStream = $progressStream;
        return $this;
    }

    /**
     * Logs a single message for debugging purposes.
     *
     * @param string $message
     */
    protected function logMessage($message)
    {
        if (!$this->loggingStream) {
            return;
        }

        fwrite($this->loggingStream, $message . PHP_EOL);
    }

    /**
     * Logs progress for streaming progress.
     *
     * @param int $itemNumber
     * @param int $totalItemCount
     */
    protected function sendProgress($itemNumber, $totalItemCount)
    {
        if (!$this->progressStream) {
            return;
        }

        if ($totalItemCount) {
            $progress = ($itemNumber / $totalItemCount) * 100;
        } else {
            $progress = 100;
        }

        fwrite($this->progressStream, $progress . PHP_EOL);
    }

    /**
     * Indexes the specified project.
     *
     * @param string[] $items
     * @param string[] $extensionsToIndex
     * @param string[] $excludedPaths
     * @param array    $sourceOverrideMap
     */
    public function index(array $items, array $extensionsToIndex, array $excludedPaths = [], $sourceOverrideMap = [])
    {
        $fileModifiedMap = $this->fileModifiedMap;

        // The modification time doesn't matter for files we have direct source code for, as this source code always
        // needs to be indexed (e.g it may simply not have been saved to disk yet).
        foreach ($sourceOverrideMap as $filePath => $source) {
            unset($fileModifiedMap[$filePath]);
        }

        $iterator = new Iterating\MultiRecursivePathIterator($items);
        $iterator = new Iterating\ExtensionFilterIterator($iterator, $extensionsToIndex);
        $iterator = new Iterating\ExclusionFilterIterator($iterator, $excludedPaths);
        $iterator = new Iterating\ModificationTimeFilterIterator($iterator, $fileModifiedMap);

        $this->storage->beginTransaction();

        $this->logMessage('Scanning and indexing files that need (re)indexing...');

        $totalItems = iterator_count($iterator);

        $this->sendProgress(0, $totalItems);

        $i = 0;

        /** @var \SplFileInfo $fileInfo */
        foreach ($iterator as $fileInfo) {
            $filePath = $fileInfo->getPathname();

            $this->logMessage('  - Indexing ' . $filePath);

            $code = null;

            if (isset($sourceOverrideMap[$filePath])) {
                $code = $sourceOverrideMap[$filePath];
            } else {
                try {
                    $code = $this->sourceCodeStreamReader->getSourceCodeFromFile($filePath);
                } catch (UnexpectedValueException $e) {
                    $code = null; // Skip files that we can't read.
                }
            }

            if ($code !== null) {
                try {
                    $this->fileIndexer->index($filePath, $code);
                } catch (IndexingFailedException $e) {
                    $this->logMessage('    - ERROR: Indexing failed due to parsing errors!');
                }
            }

            $this->sendProgress(++$i, $totalItems);
        }

        $this->storage->commitTransaction();
    }

    /**
     * Indexes builtin PHP structural elemens when necessary.
     */
    public function indexBuiltinItemsIfNecessary()
    {
        $hasIndexedBuiltin = $this->storage->getSetting('has_indexed_builtin');

        if (!$hasIndexedBuiltin || !$hasIndexedBuiltin['value']) {
            $this->builtinIndexer->index();

            if ($hasIndexedBuiltin) {
                $this->storage->update(IndexStorageItemEnum::SETTINGS, $hasIndexedBuiltin['id'], [
                    'value' => 1
                ]);
            } else {
                $this->storage->insert(IndexStorageItemEnum::SETTINGS, [
                    'name'  => 'has_indexed_builtin',
                    'value' => 1
                ]);
            }
        }
    }

    /**
     * Prunes removed files from the index.
     */
    public function pruneRemovedFiles()
    {
        foreach ($this->fileModifiedMap as $fileName => $indexedTime) {
            if (!file_exists($fileName)) {
                $this->logMessage('  - ' . $fileName);

                $this->storage->deleteFile($fileName);
            }
        }
    }
}
