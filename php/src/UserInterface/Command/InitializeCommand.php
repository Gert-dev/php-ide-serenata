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


    public function __construct(ProjectIndexer $projectIndexer)
    {
        $this->projectIndexer = $projectIndexer;
    }

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
        $this->projectIndexer->indexBuiltinItemsIfNecessary();

        return true;
    }
}
