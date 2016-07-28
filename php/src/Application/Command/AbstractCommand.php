<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use UnexpectedValueException;

use Doctrine\Common\Cache\Cache;

use GetOptionKit\OptionParser;
use GetOptionKit\OptionCollection;

use PhpIntegrator\SourceCodeHelper;
use PhpIntegrator\IndexDataAdapter;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpParser\Parser;

/**
 * Base class for commands.
 */
abstract class AbstractCommand implements CommandInterface
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 22;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var IndexDataAdapter
     */
    protected $indexDataAdapter;

    /**
     * @var string
     */
    protected $databaseFile;

    /**
     * @var Parser
     */
    protected $parser;

    /**
     * @var CacheIdPrefixDecorator|null
     */
    protected $cache;

    /**
     * @var CachingParserProxy|null
     */
    protected $cachingParserProxy;

    /**
     * @var IndexDataAdapter\ProviderCachingProxy
     */
    protected $indexDataAdapterProvider;

    /**
     * @var SourceCodeHelper
     */
    protected $sourceCodeHelper;

    /**
     * @param Cache|null $cache
     */
    public function __construct(Parser $parser, Cache $cache = null)
    {
        $this->parser = $parser;
        $this->cache = $cache ? (new CacheIdPrefixDecorator($cache, $this->getCachePrefix())) : null;
    }

    /**
     * @inheritDoc
     */
    public function execute(array $arguments)
    {
        $optionCollection = new OptionCollection();
        $optionCollection->add('database:', 'The index database to use.' )->isa('string');

        $this->attachOptions($optionCollection);

        $processedArguments = null;
        $parser = new OptionParser($optionCollection);

        try {
            $processedArguments = $parser->parse($arguments);
        } catch(\Exception $e) {
            return $this->outputJson(false, $e->getMessage());
        }

        if (!isset($processedArguments['database'])) {
            return $this->outputJson(false, 'No database path passed!');
        }

        $this->databaseFile = $processedArguments['database']->value;

        // Ensure we differentiate caches between databases.
        if ($this->cache) {
            $this->cache->setCachePrefix(md5($this->databaseFile));
        }

        $this->setIndexDatabase($this->createIndexDatabase($this->databaseFile));

        try {
            return $this->process($processedArguments);
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * Creates an index database instance for the database on the specified path.
     *
     * @param string $filePath
     *
     * @return IndexDatabase
     */
    protected function createIndexDatabase($filePath)
    {
        return new IndexDatabase($filePath, static::DATABASE_VERSION);
    }

    /**
     * Sets the indexDatabase to use.
     *
     * @param IndexDatabase $indexDatabase
     *
     * @return $this
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        $this->indexDatabase = $indexDatabase;
        return $this;
    }

    /**
     * Sets up command line arguments expected by the command.
     *
     * Operates as a(n optional) template method.
     *
     * @param OptionCollection $optionCollection
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {

    }

    /**
     * Executes the actual command and processes the specified arguments.
     *
     * Operates as a template method.
     *
     * @param ArrayAccess $arguments
     *
     * @return string Output to pass back.
     */
    abstract protected function process(ArrayAccess $arguments);

    /**
     * @param string|null $file
     * @param bool        $isStdin
     *
     * @throws UnexpectedValueException
     */
    protected function getSourceCode($file, $isStdin)
    {
        return $this->getSourceCodeHelper()->getSourceCode($file, $isStdin);
    }

    /**
     * Calculates the 1-indexed line the specified byte offset is located at.
     *
     * @param string $source
     * @param int    $offset
     *
     * @return int
     */
    protected function calculateLineByOffset($source, $offset)
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * Retrieves the character offset from the specified byte offset in the specified string. The result will always be
     * smaller than or equal to the passed in value, depending on the amount of multi-byte characters encountered.
     *
     * @param string $string
     * @param int    $byteOffset
     *
     * @return int
     */
    protected function getCharacterOffsetFromByteOffset($byteOffset, $string)
    {
        return mb_strlen(mb_strcut($string, 0, $byteOffset));
    }

    /**
     * @return string
     */
    protected function getCachePrefix()
    {
        return '';
    }

    /**
     * @return IndexDataAdapter
     */
    protected function getIndexDataAdapter()
    {
        if (!$this->indexDataAdapter) {
            $this->indexDataAdapter = new IndexDataAdapter($this->getIndexDataAdapterProvider());
        }

        return $this->indexDataAdapter;
    }

    /**
     * @return SourceCodeHelper
     */
    protected function getSourceCodeHelper()
    {
        if (!$this->sourceCodeHelper) {
            $this->sourceCodeHelper = new SourceCodeHelper();
        }

        return $this->sourceCodeHelper;
    }

    /**
     * @return IndexDataAdapter\ProviderInterface
     */
    protected function getIndexDataAdapterProvider()
    {
        if (!$this->indexDataAdapterProvider) {
            if ($this->cache) {
                $this->indexDataAdapterProvider = new IndexDataAdapter\ProviderCachingProxy(
                    $this->indexDatabase,
                    $this->cache
                );
            } else {
                $this->indexDataAdapterProvider = $this->indexDatabase;
            }
        }

        return $this->indexDataAdapterProvider;
    }

    /**
     * Outputs JSON.
     *
     * @param bool  $success
     * @param mixed $data
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        return json_encode([
            'success' => $success,
            'result'  => $data
        ]);
    }

    /**
     * @return Parser
     */
    public function getParser()
    {
        return $this->parser;
    }
}
