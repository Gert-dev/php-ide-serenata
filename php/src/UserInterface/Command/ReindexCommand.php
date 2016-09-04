<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionCollection;

use PhpIntegrator\Indexing;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Indexing\FileIndexer;
use PhpIntegrator\Indexing\IndexDatabase;
use PhpIntegrator\Indexing\ProjectIndexer;
use PhpIntegrator\Indexing\BuiltinIndexer;
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
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var ProjectIndexer
     */
    protected $projectIndexer;


    public function __construct(IndexDatabase $indexDatabase, ProjectIndexer $projectIndexer)
    {
        $this->indexDatabase = $indexDatabase;
        $this->projectIndexer = $projectIndexer;
    }


    /**
     * @inheritDoc
     */
    public function attachOptions(OptionCollection $optionCollection)
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
                $this->projectIndexer
                    ->setProgressStream($progressStream)
                    ->setLoggingStream($loggingStream);

                $sourceOverrideMap = [];

                if ($useStdin) {
                    $sourceOverrideMap[$paths[0]] = $this->sourceCodeStreamReader->getSourceCodeFromStdin();
                }

                $this->projectIndexer->index($paths, $extensionsToIndex, $excludedPaths, $sourceOverrideMap);
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
}
