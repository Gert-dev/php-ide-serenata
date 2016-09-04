<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use PhpIntegrator\Analysis\Typing\TypeDeducer;
use PhpIntegrator\Analysis\Typing\TypeResolver;
use PhpIntegrator\Analysis\Typing\FileTypeResolverFactory;

use PhpIntegrator\Indexing\FileIndexer;
use PhpIntegrator\Indexing\BuiltinIndexer;
use PhpIntegrator\Indexing\ProjectIndexer;
use PhpIntegrator\Indexing\StorageInterface;
use PhpIntegrator\Indexing\CallbackStorageProxy;

use PhpIntegrator\Parsing\PartialParser;
use PhpIntegrator\Parsing\DocblockParser;

use PhpIntegrator\UserInterface\ClasslikeInfoBuilderProviderCachingProxy;

/**
 * Command that initializes a project.
 */
class InitializeCommand extends AbstractCommand
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
     * @var array
     */
    protected $fileModifiedMap;

    /**
     * @var ClassListCommand
     */
    protected $classListCommand;

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
    protected function process(ArrayAccess $arguments)
    {
        $success = $this->initialize();

        return $this->outputJson($success, []);
    }

    /**
     * @return bool
     */
    public function initialize()
    {
        $this->getProjectIndexer()->indexBuiltinItemsIfNecessary();

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
                $this->getSourceCodeStreamReader(),
                $this->getFileModifiedMap()
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
