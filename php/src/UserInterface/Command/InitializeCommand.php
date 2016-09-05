<?php

namespace PhpIntegrator\UserInterface\Command;

use ArrayAccess;

use PhpIntegrator\Indexing\ProjectIndexer;

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
     * @param ProjectIndexer $projectIndexer
     */
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
