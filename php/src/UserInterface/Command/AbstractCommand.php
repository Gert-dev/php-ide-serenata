<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;
use RuntimeException;
use UnexpectedValueException;

use Doctrine\Common\Cache\Cache;

use GetOptionKit\OptionParser;
use GetOptionKit\OptionCollection;

use PhpIntegrator\Analysis\Relations;
use PhpIntegrator\Analysis\DocblockAnalyzer;

use PhpIntegrator\Analysis\Typing\TypeAnalyzer;

use PhpIntegrator\Indexing\IndexDatabase;

use PhpIntegrator\UserInterface\Conversion;
use PhpIntegrator\UserInterface\ClasslikeInfoBuilder;
use PhpIntegrator\UserInterface\ClasslikeInfoBuilderProviderInterface;
use PhpIntegrator\UserInterface\ClasslikeInfoBuilderProviderCachingProxy;

use PhpIntegrator\Utility\SourceCodeStreamReader;

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
     * @var ClasslikeInfoBuilder
     */
    protected $classlikeInfoBuilder;

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
     * @var ClasslikeInfoBuilderProviderCachingProxy
     */
    protected $classlikeInfoBuilderProvider;

    /**
     * @var SourceCodeStreamReader
     */
    protected $sourceCodeStreamReader;

    /**
     * @var Conversion\ConstantConverter
     */
    protected $constantConverter;

    /**
     * @var Conversion\ClasslikeConstantConverter
     */
    protected $classlikeConstantConverter;

    /**
     * @var Conversion\PropertyConverter
     */
    protected $propertyConverter;

    /**
     * @var Conversion\ClasslikeConverter
     */
    protected $classlikeConverter;

    /**
     * @var Conversion\FunctionConverter
     */
    protected $functionConverter;

    /**
     * @var Conversion\MethodConverter
     */
    protected $methodConverter;

    /**
     * @var Relations\InheritanceResolver
     */
    protected $inheritanceResolver;

    /**
     * @var Relations\InterfaceImplementationResolver
     */
    protected $interfaceImplementationResolver;

    /**
     * @var Relations\TraitUsageResolver
     */
    protected $traitUsageResolver;

    /**
     * @var DocblockAnalyzer
     */
    protected $docblockAnalyzer;

    /**
     * @var TypeAnalyzer
     */
    protected $typeAnalyzer;

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
     * @return ClasslikeInfoBuilder
     */
    protected function getClasslikeInfoBuilder()
    {
        if (!$this->classlikeInfoBuilder) {
            $this->classlikeInfoBuilder = new ClasslikeInfoBuilder(
                $this->getConstantConverter(),
                $this->getClasslikeConstantConverter(),
                $this->getPropertyConverter(),
                $this->getFunctionConverter(),
                $this->getMethodConverter(),
                $this->getClasslikeConverter(),
                $this->getInheritanceResolver(),
                $this->getInterfaceImplementationResolver(),
                $this->getTraitUsageResolver(),
                $this->getClasslikeInfoBuilderProvider(),
                $this->getTypeAnalyzer()
            );
        }

        return $this->classlikeInfoBuilder;
    }

    /**
     * Retrieves an instance of Conversion\ConstantConverter. The object will only be created once if needed.
     *
     * @return Conversion\ConstantConverter
     */
    protected function getConstantConverter()
    {
        if (!$this->constantConverter instanceof Conversion\ConstantConverter) {
            $this->constantConverter = new Conversion\ConstantConverter();
        }

        return $this->constantConverter;
    }

    /**
     * Retrieves an instance of Conversion\ClasslikeConstantConverter. The object will only be created once if needed.
     *
     * @return Conversion\ClasslikeConstantConverter
     */
    protected function getClasslikeConstantConverter()
    {
        if (!$this->classlikeConstantConverter instanceof Conversion\ClasslikeConstantConverter) {
            $this->classlikeConstantConverter = new Conversion\ClasslikeConstantConverter();
        }

        return $this->classlikeConstantConverter;
    }

    /**
     * Retrieves an instance of Conversion\PropertyConverter. The object will only be created once if needed.
     *
     * @return Conversion\PropertyConverter
     */
    protected function getPropertyConverter()
    {
        if (!$this->propertyConverter instanceof Conversion\PropertyConverter) {
            $this->propertyConverter = new Conversion\PropertyConverter();
        }

        return $this->propertyConverter;
    }

    /**
     * Retrieves an instance of Conversion\ClasslikeConverter. The object will only be created once if needed.
     *
     * @return Conversion\ClasslikeConverter
     */
    protected function getClasslikeConverter()
    {
        if (!$this->classlikeConverter instanceof Conversion\ClasslikeConverter) {
            $this->classlikeConverter = new Conversion\ClasslikeConverter();
        }

        return $this->classlikeConverter;
    }

    /**
     * Retrieves an instance of Conversion\FunctionConverter. The object will only be created once if needed.
     *
     * @return Conversion\FunctionConverter
     */
    protected function getFunctionConverter()
    {
        if (!$this->functionConverter instanceof Conversion\FunctionConverter) {
            $this->functionConverter = new Conversion\FunctionConverter();
        }

        return $this->functionConverter;
    }

    /**
     * Retrieves an instance of Conversion\MethodConverter. The object will only be created once if needed.
     *
     * @return Conversion\MethodConverter
     */
    protected function getMethodConverter()
    {
        if (!$this->methodConverter instanceof Conversion\MethodConverter) {
            $this->methodConverter = new Conversion\MethodConverter();
        }

        return $this->methodConverter;
    }

    /**
     * Retrieves an instance of Relations\InheritanceResolver. The object will only be created once if needed.
     *
     * @return Relations\InheritanceResolver
     */
    protected function getInheritanceResolver()
    {
        if (!$this->inheritanceResolver instanceof Relations\InheritanceResolver) {
            $this->inheritanceResolver = new Relations\InheritanceResolver(
                $this->getDocblockAnalyzer(),
                $this->getTypeAnalyzer()
            );
        }

        return $this->inheritanceResolver;
    }

    /**
     * Retrieves an instance of Relations\InterfaceImplementationResolver. The object will only be created once if needed.
     *
     * @return Relations\InterfaceImplementationResolver
     */
    protected function getInterfaceImplementationResolver()
    {
        if (!$this->interfaceImplementationResolver instanceof Relations\InterfaceImplementationResolver) {
            $this->interfaceImplementationResolver = new Relations\InterfaceImplementationResolver(
                $this->getDocblockAnalyzer(),
                $this->getTypeAnalyzer()
            );
        }

        return $this->interfaceImplementationResolver;
    }

    /**
     * Retrieves an instance of Relations\TraitUsageResolver. The object will only be created once if needed.
     *
     * @return Relations\TraitUsageResolver
     */
    protected function getTraitUsageResolver()
    {
        if (!$this->traitUsageResolver instanceof Relations\TraitUsageResolver) {
            $this->traitUsageResolver = new Relations\TraitUsageResolver(
                $this->getDocblockAnalyzer(),
                $this->getTypeAnalyzer()
            );
        }

        return $this->traitUsageResolver;
    }

    /**
     * @return DocblockAnalyzer
     */
    protected function getDocblockAnalyzer()
    {
        if (!$this->docblockAnalyzer) {
            $this->docblockAnalyzer = new DocblockAnalyzer();
        }

        return $this->docblockAnalyzer;
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
     * @return SourceCodeStreamReader
     */
    protected function getSourceCodeStreamReader()
    {
        if (!$this->sourceCodeStreamReader) {
            $this->sourceCodeStreamReader = new SourceCodeStreamReader();
        }

        return $this->sourceCodeStreamReader;
    }

    /**
     * @return ClasslikeInfoBuilderProviderInterface
     */
    protected function getClasslikeInfoBuilderProvider()
    {
        if (!$this->classlikeInfoBuilderProvider) {
            if ($this->cache) {
                $this->classlikeInfoBuilderProvider = new ClasslikeInfoBuilderProviderCachingProxy(
                    $this->getIndexDatabase(),
                    $this->cache
                );
            } else {
                $this->classlikeInfoBuilderProvider = $this->getIndexDatabase();
            }
        }

        return $this->classlikeInfoBuilderProvider;
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
