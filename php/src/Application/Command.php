<?php

namespace PhpIntegrator\Application;

use UnexpectedValueException;

use PhpIntegrator\IndexDatabase;
use PhpIntegrator\IndexDataAdapter;

/**
 * Base class for commands.
 */
abstract class Command implements CommandInterface
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 5;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var IndexDataAdapter
     */
    protected $indexDataAdapter;

    /**
     * {@inheritDoc}
     */
    public function execute(array $arguments)
    {
        if (count($arguments) < 1) {
            throw new UnexpectedValueException(
                'Not enough arguments passed. Usage: . <command> <database path> [<additional options>]'
            );
        }

        $databasePath = array_shift($arguments);

        $this->indexDatabase = new IndexDatabase($databasePath, static::DATABASE_VERSION);

        try {
            return $this->process($arguments);
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * Executes the actual command and processes the specified arguments.
     *
     * Operates as a template method.
     *
     * @param array $arguments
     *
     * @return string Output to pass back.
     */
    abstract protected function process(array $arguments);

    /**
     * @return IndexDataAdapter
     */
    protected function getIndexDataAdapter()
    {
        if (!$this->indexDataAdapter) {
            $this->indexDataAdapter = new IndexDataAdapter($this->indexDatabase);
        }

        return $this->indexDataAdapter;
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
}
