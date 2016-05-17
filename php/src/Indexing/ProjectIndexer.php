<?php

namespace PhpIntegrator\Indexing;

use DateTime;
use Exception;
use FilesystemIterator;
use UnexpectedValueException;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use PhpParser\Lexer;
use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;

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
     * @var Scanner
     */
    protected $scanner;

    /**
     * @var resource|null
     */
    protected $loggingStream;

    /**
     * Whether to stream progress.
     *
     * @var bool
     */
    protected $streamProgress = false;

    /**
     * @param StorageInterface $storage
     * @param BuiltinIndexer   $builtinIndexer
     * @param FileIndexer      $fileIndexer
     * @param Scanner          $scanner
     * @param bool             $showOutput
     * @param bool             $streamProgress
     */
    public function __construct(
        StorageInterface $storage,
        BuiltinIndexer $builtinIndexer,
        FileIndexer $fileIndexer,
        Scanner $scanner
    ) {
        $this->storage = $storage;
        $this->builtinIndexer = $builtinIndexer;
        $this->fileIndexer = $fileIndexer;
        $this->scanner = $scanner;
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
     * @return bool
     */
    public function getStreamProgress()
    {
        return $this->streamProgress;
    }

    /**
     * @param bool $streamProgress
     *
     * @return static
     */
    public function setStreamProgress($streamProgress)
    {
        $this->streamProgress = $streamProgress;
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
        if (!$this->streamProgress) {
            return;
        }

        if ($totalItemCount) {
            $progress = ($itemNumber / $totalItemCount) * 100;
        } else {
            $progress = 100;
        }

        // Yes, we abuse the error channel...
        file_put_contents('php://stderr', $progress . PHP_EOL);
    }

    /**
     * Indexes the specified project.
     *
     * @param string $directory
     */
    public function index($directory)
    {
        $this->logMessage('Pruning removed files...');
        $this->pruneRemovedFiles();

        $this->logMessage('Scanning for files that need (re)indexing...');
        $files = $this->scanner->scan($directory);

        $this->logMessage('Indexing outline...');

        $totalItems = count($files);

        $this->sendProgress(0, $totalItems);

        foreach ($files as $i => $filePath) {
            echo $this->logMessage('  - Indexing ' . $filePath);

            try {
                $this->fileIndexer->index($filePath);
            } catch (IndexingFailedException $e) {
                $this->logMessage('    - ERROR: Indexing failed due to parsing errors!');
            }

            $this->sendProgress($i+1, $totalItems);
        }
    }

    /**
     * Prunes removed files from the index.
     */
    protected function pruneRemovedFiles()
    {
        $fileModifiedMap = $this->storage->getFileModifiedMap();

        foreach ($fileModifiedMap as $fileName => $indexedTime) {
            if (!file_exists($fileName)) {
                $this->logMessage('  - ' . $fileName);

                $this->storage->deleteFile($fileName);
            }
        }
    }
}
