<?php

namespace PhpIntegrator\Application\Command;

use ArrayAccess;
use RuntimeException;
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
    const DATABASE_VERSION = 26;

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
     * @param Parser             $parser
     * @param Cache|null         $cache
     * @param IndexDatabase|null $indexDatabase
     */
    public function __construct(Parser $parser, Cache $cache = null, IndexDatabase $indexDatabase = null)
    {
        $this->parser = $parser;
        $this->cache = $cache ? (new CacheIdPrefixDecorator($cache, $this->getCachePrefix())) : null;
        $this->indexDatabase = $indexDatabase;
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
            $this->cache->setCachePrefix($this->cache->getCachePrefix() . md5($this->databaseFile));
        }

        try {
            return $this->process($processedArguments);
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * @return IndexDatabase
     */
    protected function getIndexDatabase()
    {
        if (!$this->indexDatabase) {
            $this->indexDatabase = new IndexDatabase($this->databaseFile, static::DATABASE_VERSION);
        }

        return $this->indexDatabase;
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
                    $this->getIndexDatabase(),
                    $this->cache
                );
            } else {
                $this->indexDataAdapterProvider = $this->getIndexDatabase();
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
     * @throws RuntimeException When the encoding fails, which should never happen.
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        $output = json_encode([
            'success' => $success,
            'result'  => $data
        ]);

        if (!$output) {
            $errorMessage = json_last_error_msg() ?: 'Unknown';

            throw new RuntimeException(
                'The encoded JSON output was empty, something must have gone wrong! The error message was: ' .
                '"' .
                $errorMessage .
                '"'
            );
        }

        return $output;
    }

    /**
     * @return Parser
     */
    public function getParser()
    {
        return $this->parser;
    }

    /**
     * @param string $code
     *
     * @throws UnexpectedValueException
     *
     * @return \PhpParser\Node[]
     */
    protected function parse($code)
    {
        try {
            $nodes = $this->parser->parse($code);
        } catch (\PhpParser\Error $e) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        if ($nodes === null) {
            throw new UnexpectedValueException('Parsing the file failed!');
        }

        return $nodes;
    }
}
