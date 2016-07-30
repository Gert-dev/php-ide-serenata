<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Indexing;
use PhpIntegrator\DocParser;
use PhpIntegrator\TypeAnalyzer;

use PhpIntegrator\IndexDataAdapter\ProviderCachingProxy;

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
     * @var DocParser
     */
    protected $docParser;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

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

        return $this->reindex(
            $arguments['source']->value,
            isset($arguments['stdin']),
            isset($arguments['verbose']),
            isset($arguments['stream-progress'])
        );
    }

    /**
     * @param string[] $paths
     * @param bool     $useStdin
     * @param bool     $showOutput
     * @param bool     $doStreamProgress
     *
     * @return string
     */
    public function reindex(array $paths, $useStdin, $showOutput, $doStreamProgress)
    {
        $success = true;

        foreach ($paths as $path) {
            if (!$this->reindexItem($path, $useStdin, $showOutput, $doStreamProgress)) {
                $success = false;
                break;
            }
        }

        return $this->outputJson($success, []);
    }

    /**
     * @param string $path
     * @param bool   $useStdin
     * @param bool   $showOutput
     * @param bool   $doStreamProgress
     *
     * @return bool
     */
    protected function reindexItem($path, $useStdin, $showOutput, $doStreamProgress)
    {
        if (!is_dir($path) && !is_file($path) && !$useStdin) {
            throw new UnexpectedValueException('The specified file or directory "' . $path . '" does not exist!');
        }

        $success = true;
        $exception = null;

        try {
            if (is_dir($path)) {
                $success = $this->reindexDirectory($path, $showOutput, $doStreamProgress);
            } else {
                $code = $this->getSourceCodeHelper()->getSourceCode($path, $useStdin);

                $success = $this->reindexFile($path, $code);
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
     * @param string $path
     * @param bool   $showOutput
     * @param bool   $doStreamProgress
     *
     * @return bool
     */
    protected function reindexDirectory($path, $showOutput, $doStreamProgress)
    {
        // Yes, we abuse the error channel...
        $loggingStream = $showOutput ? fopen('php://stdout', 'w') : null;
        $progressStream = $doStreamProgress ? fopen('php://stderr', 'w') : null;

        $this->getProjectIndexer()
            ->setProgressStream($progressStream)
            ->setLoggingStream($loggingStream)
            ->index($path);

        if ($loggingStream) {
            fclose($loggingStream);
        }

        if ($progressStream) {
            fclose($progressStream);
        }

        return true;
    }

    /**
     * @param string $path
     * @param string $code
     *
     * @return bool
     */
    protected function reindexFile($path, $code)
    {
        try {
            $this->getFileIndexer()->index($path, $code);
        } catch (Indexing\IndexingFailedException $e) {
            return false;
        }

        return true;
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
                $this->getSourceCodeHelper()
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
                $this->getDocParser(),
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
            $this->builtinIndexer = new BuiltinIndexer($this->getStorageForIndexers());
        }

        return $this->builtinIndexer;
    }

    /**
     * @return StorageInterface
     */
    protected function getStorageForIndexers()
    {
        if (!$this->storageForIndexers) {
            $this->storageForIndexers = new CallbackStorageProxy($this->indexDatabase, function ($fqcn) {
                $provider = $this->getIndexDataAdapterProvider();

                if ($provider instanceof ProviderCachingProxy) {
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
            $this->fileModifiedMap = $this->indexDatabase->getFileModifiedMap();
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
