<?php

namespace PhpIntegrator\Application;

use ArrayAccess;
use UnexpectedValueException;

use GetOptionKit\OptionParser;
use GetOptionKit\OptionCollection;

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
    const DATABASE_VERSION = 8;

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

        $this->indexDatabase = $this->createIndexDatabase($processedArguments['database']->value);

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
