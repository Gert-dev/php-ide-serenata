<?php

namespace PhpIntegrator;

use UnexpectedValueException;

/**
 * Main application class.
 */
class Application
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 1;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * Handles the application process.
     *
     * @param array $arguments The arguments to pass.
     *
     * @return mixed
     */
    public function handle(array $arguments)
    {
        try {
            $command = $this->parseRequiredArguments($arguments);

            if ($command === '--class-list') {
                // TODO: Class list with constructor information.
            } elseif ($command === '--class-info') {
                // TODO: Class info on specified FQSEN.
            } elseif ($command === '--functions') {
                // TODO: Global functions.
            } elseif ($command === '--constants') {
                $constants = $this->indexDatabase->getConnection()->createQueryBuilder()
                    ->select('*')
                    ->from(IndexStorageItemEnum::CONSTANTS)
                    ->where('structural_element_id IS NULL')
                    ->execute()
                    ->fetchAll();

                die(var_dump($constants));
                // TODO: Global constants, still need to parse them into the appropriate format.

                return $constants;
            } elseif ($command === '--reindex') {
                return $this->reindexProject($arguments);
            }

            throw new UnexpectedValueException("Unknown command {$command}");
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * Parses required arguments for the application and fetches the command to execute.
     *
     * @param array $arguments
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function parseRequiredArguments(array &$arguments)
    {
        if (count($arguments) < 2) {
            throw new UnexpectedValueException(
                'Not enough arguments passed. Usage: Main.php <database path> <command> [<additional options>]'
            );
        }

        $databasePath = array_shift($arguments);
        $this->indexDatabase = new IndexDatabase($databasePath, static::DATABASE_VERSION);

        $command = array_shift($arguments);

        return $command;
    }

    /**
     * Reindexes the project using the specified arguments.
     *
     * @param array $arguments
     *
     * @throws UnexpectedValueException
     */
    protected function reindexProject(array $arguments)
    {
        @unlink($this->databasePath); // TODO: Remove me. for testing purposes.

        if (empty($arguments)) {
            throw new UnexpectedValueException('The path to index is required for this command.');
        }

        $projectPath = array_shift($arguments);

        $indexer = new Indexer($this->indexDatabase);

        $indexer->indexProject($projectPath);
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
